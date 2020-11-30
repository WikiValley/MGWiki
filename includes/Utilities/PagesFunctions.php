<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

use Title;
use WikiPage;
use CommentStoreComment;
use WikitextContent;

/**
  * Fonctions sur les pages
  */
class PagesFunctions
{
  /**
    * @param string $pagename : titre de la page
    * @param int $namespace : constante de l'espace de nom de la page (ex.: 'NS_MAIN')
    * @param bool $check = false (return null if title does not exist)
    *
    * @return mixed Title ou null
    */
  public function getTitleFromText ( $pagename, $namespace, $check = false ) {
    $title = Title::newFromText( $pagename, $namespace );
    if ( $check && $title->getArticleID() <= 0 ) {
      return null;
    } else return $title;
  }

  /**
    * @param Title $title
    * @param bool $check = false (return null if page does not exist)
    *
    * @return mixed WikiPage ou null
    */
  public function getPageFromTitle ( Title $title, $check = false ) {
    if ( $check ) {
      if ( $title->getArticleID() <= 0 ) return null;
    }
    return WikiPage::factory( $title );
  }

  /**
    * @param string $pagename : titre de la page
    * @param int $namespace : constante de l'espace de nom de la page (ex.: 'NS_MAIN')
    * @param bool $check = false (return null if title or page does not exist)
    *
    * @return mixed WikiPage ou null
    */
  public function getPageFromTitleText ( $pagename, $namespace, $check = false ) {
    $title = self::getTitleFromText( $pagename, $namespace, $check );
    if ( is_null( $title ) ) {
      return null;
    }
    return self::getPageFromTitle( $title, $check );
  }

  /**
    * @param string $pagename : titre de la page
    * @param int $namespace : constante de l'espace de nom de la page (ex.: 'NS_MAIN')
    *
    * @return mixed string ou null
    */
	public function getPageContentFromTitleText ( $pagename, $namespace ) {
    $page = self::getPageFromTitleText( $pagename, $namespace, true );
    if ( is_null( $page ) ) {
      return null;
    }
		return $page->getContent()->getNativeData();
	}

  /**
    * recherche l'existence d'une redirection
    * renvoie le texte du titre de la page redirigée
    *
    * @param WikiPage $page : titre de la page
    * @return mixed string ou false
    */
	public function getPageRedirect ( $page ) {
		$return = [];
		$content = $page->getContent()->getNativeData();
    $screen = preg_match( '/^\#REDIRECTION \[\[(.*)\]\]/', $content, $matches );
    if ( $screen > 0 ) return $matches[1];
		return null;
	}

  /**
    * recherche la valeur des paramètres d'un modèle inclus
    *
    * @param WikiPage $page : titre de la page
    * @param string $template : nom du modèle
    * @param array $fields : champs recherchés
    *
    * @return mixed array( field => data, ... ) ou null
    */
	public function getPageTemplateInfos ( $page, $template, $fields ) {
		$return = [];
		$content = $page->getContent()->getNativeData();
		$content = str_replace( '}}','',$content );
		$content = explode('{{', $content );
		foreach ( $content as $key => $string ) {
			$screen = preg_match( '/^' . $template . '/', $string);
			if ( $screen > 0 ) {
				$data = explode( '|', $string );
				foreach ( $fields as $kkey => $field ) {
					foreach ( $data as $kkkey => $dat ) {
						$screen = preg_match('/^'.$field.'/', $dat, $matches );
						if ( $screen > 0 ) {
							$dat = trim(str_replace( $field.'=', '', $dat ));
							$return[$field] = $dat;
						}
					}
				}
			}
		}
    if ( sizeof( $return ) == 0 ) $return = null;
		return $return;
	}

  /**
    * écriture du contenu d'une page
    *
    * @param WikiPage $page
    * @param string $newtext
    * @param string $edit_summary
    * @param int $flags
    *
    * @return bool
    */
  public function writeContent ( $page, $newtext, $edit_summary, $flags = 0 ) {
    global $wgUser;
    $newcontent = new WikitextContent( $newtext );
    // cf: docs/pageupdater.txt
    $updater = $page->newPageUpdater( $wgUser );
    $updater->setContent( 'main', $newcontent ); // SlotRecord::MAIN = 'main'
    $updater->setRcPatrolStatus( 1 ); // RecentChange::PRC_PATROLLED = 1
    $comment = CommentStoreComment::newUnsavedComment( $edit_summary );
    $newRev = $updater->saveRevision( $comment, $flags );

    return ( !is_null( $newRev ) && $updater->wasSuccessful() );
  }
}
