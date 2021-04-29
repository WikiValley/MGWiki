#!/usr/bin/php
<?php
/**
 * Programme pour automatiser les màj MGWiki
 *
 * MGWiki V 1.0
 *
 * Actions:
 *   backup --save|--restore|--purge (sauvegarde DB + fichiers)
 *	 check_hooks (vérification +/- création des hooks dans mediawiki core)
 *   refresh_pages (màj ou création des pages depuis le répertoire /Refreshpages/ )
 *   rename_pages (renomme les pages selon la liste établie dans /Refreshpages/.renamepages.txt)
 *   install --version=<version> (installe mediawiki sous le répertoire /var/www/html/<version> )
 *   import --version=<version> (importe une sauveg)
 *   list_groupes (établit la liste des groupes existants => maintenance-list_groupes-report.json)
 */

global $IP;
$IP = getenv( "MW_INSTALL_PATH" ) ?: __DIR__ . "/../../..";
if ( !is_readable( "$IP/maintenance/Maintenance.php" ) ) {
	die( "MW_INSTALL_PATH needs to be set to your MediaWiki installation.\n" );
}
require_once ( "$IP/maintenance/Maintenance.php" );

global $MGW_IP;
$MGW_IP = $IP . '/extensions/MGWiki';

// externalisation des fonctions principales:
require_once ( __DIR__ . "/Updater/MgwBackup.php");
require_once ( __DIR__ . "/Updater/MgwCheckHooks.php");
require_once ( __DIR__ . "/Updater/MgwInstall.php");

class MgwUpdater extends Maintenance {

	use MgwBackup;
	use MgwCheckHooks;
	use MgwInstall;

  private $user;
	private $summary;
  private $target;
	private $config;
	private $mw_version;

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Programme d'automatisation des màj de MGWiki";
		$this->summary = "Mise à jour MGWiki 1.0";
		$this->config = json_decode( file_get_contents('Updater/updater-config.json'), true );

		$this->addDescription( file_get_contents( 'mgw-updater.description.txt' )	);

		$this->addArg( "action", "Action à réaliser.", false ); // 'list_groupes'

		$this->addOption( "dry", "Simulation seulement.", false, false, 'dry' );

		/* BACKUP */
		$this->addOption( "all",
			"Option utilisée dans différents contextes (backup --purge, backup --save, backup --restore)", false, false, 'a' );
		$this->addOption( "db", "db uniquement", false, false, 'B' );
		$this->addOption( "files", "fichiers uniquement", false, false, 'F' );
		$this->addOption( "LocalSettings", "LocalSettings uniquement", false, false, 'L' );
		$this->addOption( "purge", "Tous les dossiers de sauvegarde seront supprimés sauf le dernier", false, false, 'p' );
		$this->addOption( "save", "Sauvegarde", false, false, 's' );
		$this->addOption( "restore", "Restauration d'une archive", false, false, 'r' );
		$this->addOption( "version", "Mediawiki version number", false, true, 'V' );
		$this->addOption( "backup_dir", "Full backup path.", false, true, 'D' );
		$this->addOption( "backup_name", "Backup name.", false, true, 'N' );
		$this->addOption( "backup_sql", "SQL filename.", false, true, 'Q' );
		$this->addOption( "backup_copy", "Name of directory for files copy.", false, true, 'C' );
	}

	private function getTarget() {
		$ret = $this->getArg( 0 );
		if ( !$ret ) {
			$this->error( "You have to specify an action.", true );
		}
		return [ $ret ];
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {
    $this->user = User::newFromId( 1 );
		$this->target = $this->getTarget();
		if (!getenv( "MW_VERSION" )){
			// mediawiki < 1.35
			global $wgVersion;
			$version = $wgVersion;
		}
		else {
			$version = getenv( "MW_VERSION" );
		}
		$this->mw_version = ( $this->getOption( "version" ) ) ? $this->getOption( "version" ) : $version ;
    $done = $this->{'do_'.$this->target[0]}();
    echo $done . "\n";
	}

  private function do_list_groupes() {
    # constructeurs
    $groupe_controller = new MediaWiki\Extension\MGWikiDev\Specials\Groupe;
    $groupe_constructor = new MediaWiki\Extension\MGWikiDev\Classes\MGWSpecialConstructor ( 'groupe' );
    $set = ['display'=>'pages', 'action' => 'show' ];
    $groupe_constructor->set( $set );
    $list = $groupe_controller->list( $groupe_constructor->getQuery() );

    # on établit la liste des membres
    $fails = [];
    foreach( $list as $key => $groupe ) {

      $fails[$key]['groupe'] = 'Groupe:'.$groupe['page_name'];
      $fails[$key]['fails'] = false;
      $fails[$key]['users'] = [];

      if ( $groupe['template'] ) {

        $list[$key]['referent'] = trim($groupe['referent']);
        $list[$key]['referent_id'] = User::newFromName($list[$key]['referent'])->getId();
         # récupération des utilisateurs non reconnus
        if ( $list[$key]['referent_id'] == 0 ) {
          $fails[$key]['fails'] = true;
          $fails[$key]['users'][] = $list[$key]['referent'];
        }

        $list[$key]['membres'] = explode(',', $groupe['membres'] );
        foreach ($list[$key]['membres'] as $kkey => $membre) {
          $membre = trim($membre);
          if (!$membre){
            unset($list[$key]['membres'][$kkey]);
          }
          else {
            $list[$key]['membres'][$kkey] = $membre;
            $user = User::newFromName($membre);
            $list[$key]['membres_ids'][$kkey] = $user->getId();
             if ( $list[$key]['membres_ids'][$kkey] == 0 ) {
               $fails[$key]['fails'] = true;
               $fails[$key]['users'][] = $membre;
             }
          }
        }
      }
    }

    # liste des échecs
    $report = '';
    foreach ( $fails as $fail ) {
      if ( $fail['fails'] ) {
        $report .= $fail['groupe'] . "\n";
        foreach ( $fail['users'] as $user ) {
          $report .= $user . "\n";
        }
        $report .= "\n";
      }
    }

    # on sauvegarde le résultat
    if ( !$report ) {
      file_put_contents( 'maintenance-list_groupes-report.json', json_encode($list) );
      unlink('maintenance-list_groupes-fails-report.txt');
      return 'done';
    }
    else {
      file_put_contents( 'maintenance-list_groupes-fails-report.txt', $report );
      return "\nCertains utilisateurs n\'ont pas été reconnus\n\n" .
            "Veuillez corriger les groupes avant de poursuivre\n" .
            "=> maintenance-list_groupes-fails-report.txt\n";
    }
  }

	private function do_rename_pages() {
		echo "Renommage des pages... \n\n";

		$fails = '';
		$handle = fopen('Refreshpages/.renamepages.txt', 'r');
		if ($handle)
		{
			/*Tant que l'on est pas à la fin du fichier*/
			$i = 1;
			while (!feof($handle))
			{
				/*On lit la ligne courante*/
				$buffer = fgets($handle);
				if ($buffer) {
					if ( preg_match( '/"(.*)"[ ]?=>[ ]?"(.*)"/', $buffer, $matches ) > 0 ) {
						$oldTitle = \Title::newFromText( $matches[1] );
						if ( $oldTitle->getArticleID() == 0 ) {
							$report = $matches[1] . " : page inconnue\n";
							$fails .= $report;
							echo $report;
						}
						else {
							# on vérifie que la redirection n'est pas déjà faite
							$page = \WikiPage::factory($oldTitle);
							if ( $page->isRedirect() ) {
								$target = $page->getRedirectTarget()->getFullText();
								if ( $target == $matches[2] ) {
									echo $matches[1] . ' => ' . $target . " (redirection déjà présente)\n";
								}
								else {
									$report = $matches[1] . ' : une autre redirection existe vers la page "' . $target . '"' . "\n";
									$fails .= $report;
									echo $report;
								}
							}
							else {
								# on renomme la page
								$newTitle = \Title::newFromText( $matches[2] );
								$move = new \MovePage( $oldTitle, $newTitle );
								$move->move( $this->user, $this->summary, true );
								echo $matches[1] . ' => ' . $matches[2] . "\n";
							}
						}
					}
					else {
						$report = $i . ' "' . $buffer . '" : ligne invalide' . "\n";
						$fails .= $report;
						echo $report;
					}
				}
				$i++;
			}
			/*On ferme le fichier*/
			fclose($handle);
		}
		if ( $fails ) {
			file_put_contents( 'maintenance-rename_pages-fails-report.txt', $fails );
			echo "\n" . '... des erreurs sont survenues : consulter "maintenance-rename_pages-fails-report.txt"' . "\n";
		}
		else {
	    echo "\n\n... OK\n";
		}
    return 'done';
	}

  private function do_refresh_pages() {

		# MAJ CONTENU
    $filelist = array();
    $dir_name = __DIR__ . '/Refreshpages';
    if ( $handle = opendir( $dir_name ) ) {
        while ($entry = readdir($handle)) {
            $filelist[] = $entry;
        }
        closedir( $handle );
    }

    echo "Mise à jour des pages...\n\n";
    foreach ( $filelist as $file ) {
			if ( !in_array( $file, ['.PagesSaver.php','.refreshpages.txt','.renamepages.txt','.','..'] ) ) {
				$page = str_replace( ['~~','_','°°'], [':',' ','/'], $file );
	      if ( preg_match( '/^[\.]*$/', $page ) == 0 ) {
	        $content = file_get_contents( $dir_name . '/' . $file );
	        $status = \MediaWiki\Extension\MGWiki\Utilities\PagesFunctions::edit(
	          $page,
	          $this->summary,
	          $this->user,
	          $content,
						true
	        );
	        echo $page . ' ' . str_replace( ['<p>','</p>'], '', $status->mess() ) . "\n\n";
	      }
			}
    }
    echo "\n... OK\n";

    return 'done';
  }

	/**
	 * @param string $module 'save'|'restore'|'purge'
	 */
	private function do_backup ( $module = null, $version = null ) {

	 	global $wgMGW_backup_dir;
		global $wgDBname;

		if ( !$module ) $module = ( $this->getOption( "save" ) ) ? 'save' : null;
		if ( !$module ) $module = ( $this->getOption( "restore" ) ) ? 'restore' : null;
		if ( !$module ) $module = ( $this->getOption( "purge" ) ) ? 'purge' : null;
		if ( !$module ) {
			echo "vous devez choisir une option parmi --save, --restore ou --purge.\n";
			return 'annulation';
		}
	 	$directory = ( $this->getOption( "backup_dir" ) ) ? $this->getOption( "backup_dir" ) : $wgMGW_backup_dir;
	 	$backup = ( $this->getOption( "backup_name" ) ) ? $this->getOption( "backup_name" ) : '';
		$sql_file = ( $this->getOption( "backup_sql" ) ) ? $this->getOption( "backup_sql" ) : '';
		$copy_dir = ( $this->getOption( "backup_copy" ) ) ? $this->getOption( "backup_copy" ) : '';

		// SAVE
	 	if ( $module == "save" ) {

			$this->backup_purge_all( $directory );

			echo "\nSauvegarde ... \n\n";

	 		$all = ( ( !$this->getOption( "db" ) && !$this->getOption( "files" ) )
				|| $this->getOption( "all" ) );

	    if ( !$backup ) $this->make_backup_name( $backup );

			if ( $this->check_backup_dir( $backup, $directory, true ) )
				$directory = $directory . '/' . $backup;
			else return "annulation";

			# DB
	 		if ( $all || $this->getOption( "db" ) ) {
				if ( !$sql_file )	$sql_file = $backup . '.sql';
				echo $this->backup_db_save( $sql_file, $directory );
			}

			# FILES
	 		if ( $all || $this->getOption( "files" ) ) {
				if ( !$copy_dir )	$copy_dir = $backup . '.copy';
	 			echo $this->backup_files_save( $copy_dir, $directory );
			}

	 		return "... sauvegarde terminée.\n";
	 	}

		// RESTORE
	 	elseif ( $module == "restore" ) {
			echo "\nRestauration d\'une sauvegarde ... \n\n";

	 		$all = ( ( !$this->getOption( "db" ) && !$this->getOption( "files" )
				&& !$this->getOption( "LocalSettings" ) ) || $this->getOption( "all" ) );

	    if ( !$backup && !$this->screen_backups( $directory, $backup ) )
				return "annulation";

			if ( $this->check_backup_dir( $backup, $directory, false ) )
				$directory = $directory . '/' . $backup;
			else return "annulation";

			$newDB = ( $version ) ? 'mediawiki_' . ( str_replace( '.', '_', $version ) ) : '';
			$newPATH = ( $version ) ? '/var/www/html/' . $version : '';

			# DB
	 		if ( $all || $this->getOption( "db" ) ) {
				if ( !$sql_file ) $sql_file = $backup . '.sql';
		 		echo $this->backup_db_restore( $sql_file, $directory, $newDB ) . "\n";
			}
			# FILES
	 		if ( $all || $this->getOption( "files" ) ) {
				if ( !$copy_dir )	$copy_dir = $backup . '.copy';
	 			echo $this->backup_files_restore( $copy_dir, $directory, $newPATH ) . "\n";
			}
	 		if ( $this->getOption( "LocalSettings" ) ) {
				if ( !$copy_dir )	$copy_dir = $backup . '.copy';
	 			echo $this->backup_files_restore( $copy_dir, $directory, $newPATH, 'LocalSettings' ) . "\n";
			}
			# màj LocalSettings si nouvelle db
			if ( $version ) {
				$localSettings = file_get_contents( $newPATH . '/LocalSettings.php' );
				$localSettings = str_replace( '$wgDBname = "' . $wgDBname . '";', '$wgDBname = "' . $newDB . '";', $localSettings );
				file_put_contents( $newPATH . '/LocalSettings.php', $localSettings );
			}
	 		return "... fin de la restauration\n";
	 	}

	 	// PURGE
	 	elseif ( $module == "purge" ) {
			$this->backup_purge( $directory );
	 		return '';
	 	}
	}

	private function do_check_hooks( $version = '' ) {
		echo "Vérification des Hooks spécifiques à MGWiki ...\n";
		return $this->checkHooks( $version );
	}

	private function do_install() {
	  if ( ! $this->getOption( "version" ) ) echo "l'argument --version doit être précisé";
		return $this->install_mediawiki();
	}

	private function do_import() {
	  if ( ! $this->getOption( "version" ) ) echo "l'argument --version doit être précisé";
		return $this->import_version( $this->getOption( "version" ) );
	}

	private function shell( $cmd, &$console_out, $string = true ) {
		$console_out = [];
		$result_code = 2;
		exec( $cmd, $console_out, $result_code );
		if ( $string ) $console_out = implode( "\n", $console_out );
		return $result_code;
	}

	private function shell_dry( $cmd ) {
		$console_out = [];
		$result_code = 2;
		exec( $cmd, $console_out, $result_code );
		return $result_code;
	}

	private function do_test() {
		$shell_out = '';
		$shell = $this->shell( "cd /var/www/backup/test && ls", $shell_out );
		$ls = explode( "\n", $shell_out );
		echo '/'.$shell_out.'/';
	}
}

$maintClass = "MgwUpdater";
require_once RUN_MAINTENANCE_IF_MAIN;
