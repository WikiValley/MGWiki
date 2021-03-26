#!/usr/bin/php
<?php
/**
 * Programme pour automatiser la constitution du dossier maintenance/RefreshPages
 * depuis les pages du site bêta
 * à partir de la liste des titre inclue dans refreshpages.txt (1 titre par ligne)
 */

$IP = getenv( "MW_INSTALL_PATH" ) ?: __DIR__ . "/../../..";
if ( !is_readable( "$IP/maintenance/Maintenance.php" ) ) {
	die( "MW_INSTALL_PATH needs to be set to your MediaWiki installation.\n" );
}
require_once ( "$IP/maintenance/Maintenance.php" );

class PageSaver extends Maintenance {

	public function __construct() {
		parent::__construct();
		$this->mDescription = "Programme pour automatiser la constitution du dossier maintenance/RefreshPages".
	  	"depuis les pages du site bêta à partir de la liste des titre inclue dans refreshpages.txt (1 titre par ligne)";
	}

	/**
	 * @inheritDoc
	 */
	public function execute() {

		# 1. on vide le répertoire de travail
    $dir_name = __DIR__ . '/Refreshpages';
    $dir = opendir( $dir_name );  // ouvre le répertoire
    $files = readdir( $dir );

    while ( $files = readdir( $dir ) ) {
	    if ( !in_array( $files, ['.refreshpages.txt','.renamepages.txt', '.', '..' ] ) ) { // exceptions
				unlink( $files );  // supprime chaque fichier du répertoire
    	}
    }
		closedir( $dir );

		# 2. on reconstitue les fichiers
		$handle = fopen('.refreshpages.txt', 'r');
		if ($handle)
		{
			/*Tant que l'on est pas à la fin du fichier*/
			while (!feof($handle))
			{
				/*On lit la ligne courante*/
				$buffer = fgets($handle);
				if ($buffer) {
					$title = \Title::newFromText($buffer);
					if ( $title->getArticleID() > 0 ) {
						$page = \WikiPage::factory($title);
						$content = $page->getContent()->getNativeData();
						file_put_contents( str_replace( [':',' ', '/'], ['~~','_', '°°'], $buffer), $content );
					}
					else {
						echo $buffer . " : page inconnue\n";
					}
				}
			}
			/*On ferme le fichier*/
			fclose($handle);
		}
    echo "\ndone\n";
	}
}

$maintClass = "PageSaver";
require_once RUN_MAINTENANCE_IF_MAIN;
