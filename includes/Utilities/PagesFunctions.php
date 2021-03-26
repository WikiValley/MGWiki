<?php

namespace MediaWiki\Extension\MGWiki\Utilities;

use Title;
use WikiPage;
use CommentStoreComment;
use WikitextContent;
use MovePage;
use Parser;
use PageArchive;
use ContentHandler;
use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\MGWiki\Utilities\MGWStatus as Status;
use MediaWiki\Extension\MGWiki\Utilities\DataFunctions as DbF;
use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;

/**
  * Ensemble de fonctions statiques sur les pages
  */
class PagesFunctions
{
  //////////////////////////////////////
  // FONCTIONS DE MANIPULATION DES PAGES

  /**
   * @param Title|string|int $target
   * @return WikiPage|null
   */
  public function getPageFromAny ( $target ) {

    if ( is_int( $target ) ) {
      return WikiPage::newFromId( $target );
    }
    elseif ( is_string( $target ) ) {
      return WikiPage::factory( Title::newFromText( $target ) );
    }
    elseif ( $target instanceof Title ) {
      return WikiPage::factory( $target );
    }
    elseif ( $target instanceof WikiPage ) {
      return $target;
    }
    else return null;
  }

  /**
   * @param int|string $target (int)page_id|(string)page_title
   * @param string $summary
   * @param User $user
   * @param string $content
   *
   * @return MGWStatus
   */
  public static function edit( $target, $summary, $user, $content = null, $create_new = false ) {

    $article = self::getPageFromAny( $target );
    if ( !$article ) {
      return Status::newFailed( $target . ' : page invalide', 'MGW_PAGE_INVALID' );
    }

    $is_new = ( $article->getId() == 0 );
    $title = $article->getTitle()->getFullText();

    if ( $is_new && !$create_new ) {
      return Status::newFailed( 'La page ' . $title . ' n\'existe pas.', 'MGW_PAGE_MISSING' );
    }
    if ( $is_new && !$content ) {
      return Status::newFailed( 'Impossible de créer la page ' . $title .
      ' : $content NULL', 'MGW_PAGE_NO_CONTENT' );
    }

    $pageUpdater = $article->newPageUpdater( $user );

    if ( !$content ) {
      // enregistrement avec modification 'fantôme' pour réctualiser les parsers
      $content = $article->getContent()->getNativeData();
      $content = preg_replace('/\<\!\-\-mgwupdate[0-9]+\-\-\>/', '', $content);
      $content .= '<!--mgwupdate' . wfTimestamp() . '-->';
    }

    if ( !( $content instanceof WikitextContent ) && is_string( $content ) ) {
      $content = new WikitextContent( $content );
    }
    $summary = \CommentStoreComment::newUnsavedComment( $summary );
    $flags = ( $is_new ) ? EDIT_NEW : EDIT_MINOR;

    $pageUpdater->setContent( 'main', $content );
    $revision = $pageUpdater->saveRevision(	$summary, $flags );

    //DEPRECATED: $ret = $article->doEditContent( $content, $summary, $flags, false, $user );
// valeurs de retour:
// null/RevisionStoreRecord
//
// $pageUpdater->isUnchanged();
// $pageUpdater->wasSuccessful();
// $pageUpdater->getStatus();

    if ( ! $revision || ! $pageUpdater->wasSuccessful() ) {
      $act = ( $is_new ) ? 'création' : 'màj';
      $mess = 'Echec à la tentative de '.$act.' de la page "'.$title.
        '" : ' . $pageUpdater->getStatus()->getHTML();
      return Status::newFailed( $mess, 'MGW_EDIT_ERROR' );
    }
    else {
      return Status::newDone( 'La page '.$title.' a été rafraîchie', $revision );
    }
  }

  /**
   * @param int|string|Title|WikiPage $page
   * @param string $summary
   *
   * @return MGWStatus
   */
  public static function delete( $page, $reason ) {

    $page = self::getPageFromAny( $page );

    if ( $page && $page->getId() > 0 ) {
      $titletext = $page->getTitle()->getFullText();
      $delete = $page->doDeleteArticleReal( $reason );
      if ( ! $delete->isOK() ) {
        return Status::newFailed( 'La page '.$titletext.' n\'a pas pu être supprimée ( ' .
          $delete->getMessage() .' )', $titletext );
      }
      return Status::newDone( 'La page '.$titletext.'a été supprimée', $titletext );
    }
    return Status::newFailed( 'La page n\'a pas pu être supprimée ( page inexistante )' );
  }

  //////////////////////////////////////
  // FONCTIONS SUR LES MODELES INCLUS

  public function getArchiveContent( $page, $revId = 0 ) {
    global $wgUser;
    if ( ! $revId ) {
      $revId = $page->getLastRevisionId();
    }
    $revision = $page->getArchivedRevision( $revId );
    return $revision->getContent( \revision::FOR_THIS_USER, $wgUser )->getNativeData();
  }

  /**
    * recherche la valeur des paramètres d'un modèle inclus
    *
    * @param Wikipage|Title|string|int $target
    * @param string $template : nom du modèle
    * @param array $fields : champs recherchés [ 'champs' => 'champ dans le modèle' ]
    * @param bool $archive = false
    *
    * @return array [ [ "field" => data, ... ], [...] ]|null ( null = page ou content inexistants )
    */
	public function getPageTemplateInfos ( $target, $template, $fields, $archive = false ) {
    $page = self::getPageFromAny( $target );
    if ( !$page ) return null;

    if ( $archive ) {
      $content = self::getArchiveContent( $page );
    }
    else {
      $content = $page->getContent();
      if ( $content ) $content = $content->getNativeData();
    }

    if ( !$content ) return null;
    return self::getTemplateInfos ( $content, $template, $fields );
  }

  /**
    * recherche la valeur des paramètres d'un modèle inclus
    *
    * @param string $content
    * @param string $template : nom du modèle
    * @param array $fields : champs recherchés [ 'champs' => 'champ dans le modèle' ]
    *
    * @return array [ [ "field" => data, ... ], [...] ]
    */
	public function getTemplateInfos ( $content, $template, $fields ) {

    # on échappe les retours à la ligne pour simplifier les regexp
    $content = str_replace( "\n", '\\n', $content );
    $templates = self::parseTemplates( $content, true );

  	$return = [];
    foreach ( $templates as $key => $temp ) {
      if ( $temp['type'] == 'template' && $temp['title'] == $template ) {
        $ret = [];
        $ret['full-template-string'] = str_replace( '\\n', "\n", '{{' . $temp['string'] . '}}' );
        foreach ( $fields as $field => $tpl_field ) {
          if ( isset( $temp['fields'][$tpl_field]['value'] ) ) {
            $ret[$field] = str_replace( '\\n', "\n", $temp['fields'][$tpl_field]['value'] );
          }
        }
        $return[] = $ret;
      }
    }
		return $return;
	}

  /**
    * màj d'un modèle inclus
    *
    * @param WikiPage|string|int $page|$page_fullname|$page_id
    * @param string $template : nom du modèle
    * @param array $data [ 'field' => 'value',... ]
    * @param bool $inline = retours à la ligne entre chaque argument si false
    * @param bool $append = ajout du modèle en début de page si true, fin si false
    * @param bool $null (false) true = retourne null en l'absence de modif
    *
    * @return string ( $content )
    */
  public function updatePageTemplateInfos ( $target, $template, $data, $inline = false, $append = true, $null = false ) {
    global $wgUser;

    $page = self::getPageFromAny( $target );

    if ( ! $page ) return null;

    // récupération du contenu actuel
    $content = ( $page->getId() > 0 ) ? $page->getContent()->getNativeData() : '';

    // do stuff ...
    if ( $content )
      $new_content = self::updateTemplateInfos( $content, $template, $data, $inline, $append );
    else $new_content = self::makeTemplate( $content, $template, $data, $inline );

    if ( !$null || $new_content != $content ) {
      return $new_content;
    }
    else return null;
  }

  /**
   * @param string $template
   * @param array $data
   * @param bool $inline
   */
  public function makeTemplate( $template, $data, $inline ){

    $template = [];
    $template[] = "{{".$template;
    foreach ( $data as $field => $value ) {
      $template[] = "|$field=$value";
    }
    $template[] = "}}";
    $glue = ( $inline ) ? '' : '
';
    return implode( $glue, $template );
  }

  /**
    * @param string $content (wikitexte)
    * @param string $template : nom du modèle
    * @param array $fields nommés tels que dans les modèles des pages
    * @param bool $inline = false (retours à la ligne entre chaque argument si false)
    * @param int $n = 1 ( mise à jour du nième modèle dans la page si multiples. Tous les modèles si n = 0 )
    *
    * @return string ( $content )
    */
  public function deleteTemplateFields ( $content, $template, $fields, $inline = false, $n = 1 ) {
    $data = [];
    foreach ( $fields as $field ) $data[$field] = '';
    return self::updateTemplateInfos (
      $content,
      $template,
      $data,
      $inline,
      true,
      true,
      $n
    );
  }

  /**
    * @param string $content (wikitexte)
    * @param string $template : nom du modèle
    * @param array $data [ 'field' => 'value',... ]
    * @param bool $inline = false (retours à la ligne entre chaque argument si false)
    * @param bool $append = true (ajout du modèle en début de page si true, fin si false)
    * @param bool $delete = false (suppression du champs du modèle)
    * @param int $n = 1 ( mise à jour du nième modèle dans la page si multiples. Tous les modèles si n = 0 )
    *
    * @return string ( $content )
    */
  public function updateTemplateInfos ( $content, $template, $data, $inline = false, $append = true, $delete = false, $n = 1 ) {
    $content = str_replace( "\n", '\n', $content );

    // récupération des données incluses dans le modèle
    $templates = self::parseTemplates( $content, true );
    $indexes = [];
    foreach ( $templates as $key => $temp ) {
      if ( $temp['title'] == $template )
        $indexes[] = $key;
    }
     # modèle absent: on l'ajoute
    if ( !$indexes && !$delete ) {
      $add = '{{' . $template;
      if ( !$inline ) {
        $add .= '\\n|';
      }
      foreach ( $data as $field => $value ) {
        $add .= '|' . $field . ' =' . $value;
        if ( !$inline ) {
          $add .= '\\n';
        }
      }
      $add .= '}}';
      $content = ( $append ) ? $add . $content : $content . $add;
    }
    else {
      foreach ( $indexes as $key => $index ) {
        if ( ($key + 1) == $n || $n == 0 ) {

          $template_string = $templates[$index]['string'];

           # modèle présent: màj champs par champs
          foreach ( $data as $field => $value ) {
              # champs inexistant: on l'ajoute en fin de modèle
            if ( !$delete && !isset( $templates[$index]['fields'][$field] ) ) {
              $add = '|' . $field . '=' . $value;
              if ( ! $inline ) {
                $add = $add . '\\n';
              }
              $template_string = $template_string . $add;
            }
             # champs existant: màj si nécessaire
            elseif ( !$delete && $value != $templates[$index]['fields'][$field]['value'] ) {
              if ( ! $inline ) {
                $value = $value . '\\n';
              }
              $template_string = str_replace(
                $templates[$index]['fields'][$field]['string'],
                '|' . $field . '=' . $value,
                $template_string
              );
            }
            elseif ( $delete ) {
              $needle = [
                $templates[$index]['fields'][$field]['string'],
                $templates[$index]['fields'][$field]['string'] . '\\n'
              ];
              $template_string = str_replace( $needle, '', $template_string );
            }
          }
          $content = str_replace( $templates[$index]['string'], $template_string, $content );
        }
      }
    }
    $content = str_replace( '\\n', "\n", $content );
		return $content;
  }

  /**
    * @param string $content
    * @param bool $multiple
    *
    * @return array|null
    */
	public function parseTemplates ( $content, $multiple = false ) {

    // on retire les liens pour les mettre de côté (confusions lors de l'analyse)
    global $j;
    global $saved_links;
    $saved_links = [];
    $j = 0;
    while ( preg_match('/\[\[([^\{\}]*)\]\]/', $content ) == 1 ) {
      $content = preg_replace_callback(
        '/\[\[([^\{\}]*)\]\]/',
        function ($matches) {
          global $j;
          global $saved_links;
          $saved_links[$j] = $matches[1];
          return '°°'.$j.'°°';
        },
        $content,
        1
      );
      $j++;
    }

    // on recherche tous les modèles présents
    $j = 0;
    while ( preg_match('/\{\{([^\{\}]*)\}\}/', $content ) == 1 ) {
      $content = preg_replace_callback(
        '/\{\{([^\{\}]*)\}\}/',
        function ($matches) {
          global $j;
          return '~~'.$j.'~~'.$matches[1].'~~'.$j.'~~';
        },
        $content,
        1
      );
      $j++;
    }

    // extraction de la chaîne et du titre
    $tpl = [];
    for ( $i = 0; $i < $j; $i++ ) {
      preg_match( '/(~~'.$i.'~~(.*)~~'.$i.'~~)/', $content, $matches );
      $title = str_replace( '\\n', '', $matches[2] );
      $title = trim($title);
      if ( preg_match( '/^\#/', $title ) > 0 ) {
        $tpl[$i]['type'] = 'parser';
        if ( preg_match( '/^(([^:]*):)/', $title, $m ) > 0 ) {
          $title = str_replace( $m[1], $m[2].'|', $title );
        }
      }
      else {
        $tpl[$i]['type'] = 'template';
      }
      preg_match( '/^[^\|]*/', $title, $m );
      $content = str_replace( $matches[1], '~~'.$i.'~~', $content );
      $tpl[$i]['string'] = $matches[2];
      $tpl[$i]['title'] = $m[0];
    }

    // extraction des champs
      # on remplace ':' par '|' si parser && premier champs
    foreach ( $tpl as $key => $array ) {
      if ( $array['type'] == 'parser' &&
          preg_match( '/^(([^:]*):)/', $array['string'], $m ) > 0 ) {
        $string = str_replace( $m[1], $m[2].'|', $array['string'] );
      }
      else {
        $string = $array['string'];
      }
       # on extrait les champs à partir de '^|'
      $tpl[$key]['fields'] = [];
      if ( preg_match_all( '/\|[^\|]*/', $string, $m ) > 0 ) {
        foreach ( $m[0] as $kkey => $vvalue ) {

           # on archive la chaîne brute
          if ( $array['type'] == 'parser' && $kkey == 0 ) {
            $vvalue = ':' . substr( $vvalue, 1 );
          }
          $tpl[$key]['fields'][$kkey]['string'] = $vvalue;

           # on nettoie les retours à la ligne
            //TODO: impossible d'attraper '/(\\n)+$/' avec preg_match... (?)
            $vvalue = str_replace('\n', '~°~°', $vvalue );
            $vvalue = preg_replace( '/(~°~°)+$/', '', $vvalue );
            $vvalue = str_replace('~°~°', '\n', $vvalue );

           # on recherche la présence d'un titre de champs
          if ( $array['type'] == 'template' ) {
            preg_match( '/^([^=]*)(=)?(.*)$/', $vvalue, $n );
            if ( empty( $n[2]) ) {
              $tpl[$key]['fields'][$kkey]['title'] = '';
              $tpl[$key]['fields'][$kkey]['value'] = substr( $vvalue, 1 );
            }
            else {
              $tpl[$key]['fields'][$kkey]['title'] = trim( substr( $n[1], 1 ) );
              $tpl[$key]['fields'][$kkey]['value'] = $n[3];
            }
          }
          else {
            $tpl[$key]['fields'][$kkey]['title'] = '';
            $tpl[$key]['fields'][$kkey]['value'] = substr( $vvalue, 1 );
          }
        }
      }
    }

     # on reconstruit les modèles et parsers inclus (ordre inverse)
    for ( $i = $j-1; $i > -1; $i-- ) {
      foreach ( $tpl as $key => $value ) {
        $tpl[$key]['string'] = str_replace(
         '~~'.$i.'~~',
         '{{'.$tpl[$i]['string'].'}}',
         $tpl[$key]['string']
        );
        foreach ( $tpl[$key]['fields'] as $kkey => $vvalue ) {
          $tpl[$key]['fields'][$kkey]['string'] = str_replace(
           '~~'.$i.'~~',
           '{{'.$tpl[$i]['string'].'}}',
           $tpl[$key]['fields'][$kkey]['string']
          );
          $tpl[$key]['fields'][$kkey]['value'] = str_replace(
            '~~'.$i.'~~',
            '{{'.$tpl[$i]['string'].'}}',
            $tpl[$key]['fields'][$kkey]['value']
          );
        }
      }
    }

     # on ré-intègre les liens
    foreach ( $saved_links as $num => $link ) {
      foreach ( $tpl as $key => $value ) {
        $tpl[$key]['string'] = str_replace(
         '°°'.$num.'°°',
         '[['.$link.']]',
         $tpl[$key]['string']
        );
        foreach ( $tpl[$key]['fields'] as $kkey => $vvalue ) {
          $tpl[$key]['fields'][$kkey]['string'] = str_replace(
            '°°'.$num.'°°',
            '[['.$link.']]',
           $tpl[$key]['fields'][$kkey]['string']
          );
          $tpl[$key]['fields'][$kkey]['value'] = str_replace(
            '°°'.$num.'°°',
            '[['.$link.']]',
            $tpl[$key]['fields'][$kkey]['value']
          );
        }
      }
    }

     # on renomme les tableaux avec les titres
    foreach ( $tpl as $key => $array ) {
      if ( !empty( $array['fields'] ) ) {
        foreach ( $array['fields'] as $kkey => $aarray ) {
          if ( !empty( $array['fields'][$kkey]['title'] ) ) {
            $tpl[$key]['fields'][ $array['fields'][$kkey]['title'] ] = $array['fields'][$kkey];
            unset( $tpl[$key]['fields'][$kkey] );
          }
        }
        if ( !empty( $array['title'] ) && !$multiple ) {
          $tpl[ $array['title'] ] = $tpl[$key];
          unset( $tpl[$key] );
        }
      }
    }

    return $tpl;
  }
}
