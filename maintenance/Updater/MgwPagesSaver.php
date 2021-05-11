<?php
/**
 * Programme pour automatiser la constitution du dossier maintenance/RefreshPages
 * depuis les pages du site bêta
 * à partir de la liste des titre inclue dans refreshpages.txt (1 titre par ligne)
 */

trait MgwPagesSaver {

	private function save_pages() {

		global $MGW_IP;

		# 1. on vide le répertoire de travail
    $dir_name = $MGW_IP . '/maintenance/Refreshpages';
    $dir = opendir( $dir_name );  // ouvre le répertoire
    $files = readdir( $dir );

    while ( $file = readdir( $dir ) ) {
	    if ( !in_array( $file, ['.refreshpages.txt','.renamepages.txt', '.', '..' ] ) ) { // exceptions
				unlink( $dir_name . '/' . $file );  // supprime chaque fichier du répertoire
    	}
    }
		closedir( $dir );

		# 2. on reconstitue les fichiers
		$handle = fopen( $dir_name . '/' . '.refreshpages.txt', 'r');
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
						file_put_contents( $dir_name . '/' . str_replace( [':',' ', '/', "\n"], ['~~','_', '°°', ''], $buffer ), $content );
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

	private function refresh_pages() {
		global $MGW_IP;

		# MAJ CONTENU
    $filelist = array();
    $dir_name = $MGW_IP . '/maintenance/Refreshpages';
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

	private function rename_pages() {
		global $MGW_IP;
		$fails = '';
		$array = explode( "\n", file_get_contents( "$MGW_IP/maintenance/Refreshpages/.renamepages.txt" ) );

		foreach ( $array as $key => $line ) {
			if ( $line && preg_match( '/"(.*)"[ ]?=>[ ]?"(.*)"/', $line, $matches ) > 0 ) {

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
			elseif ( $line ) {
				$report = $key++ . ' "' . $line . '" : ligne invalide' . "\n";
				$fails .= $report;
				echo $report;
			}
		}

		if ( $fails ) {
			file_put_contents( 'maintenance-rename_pages-fails-report.txt', $fails );
			echo "\n" . '... des erreurs sont survenues : consulter "maintenance-rename_pages-fails-report.txt"' . "\n";
		}
		else {
	    echo "\n... OK\n";
		}
    return 'done';
	}
}
