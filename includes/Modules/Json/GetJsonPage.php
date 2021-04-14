<?php

namespace MediaWiki\Extension\MGWiki\Modules\Json;

use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use Title;
use WikiPage;

/**
  * Class to handle json data from a wiki page
  * allow administrators to manage interface easily
  */
class GetJsonPage
{
  private $jsonData;  // array
  private $service;   // string

  /**
 	 * @param string $service
   */
  public function __construct( $service )
  {
    $this->jsonData = self::retrieveData( $service );
  }

  /**
 	 * récupérer les données en usage statique, sans constructeur
	 * @param string $service
   */
  public static function getData( $service )
  {
    return self::retrieveData( $service );
  }

  /**
 	 * récupérer l'url de la page
	 * @param string $service
   */
  public static function getLink( $service )
  {
    return self::getFullURL( $service );
  }

  public function getFullData()
  {
    return $this->jsonData;
  }

  /**
   * Retrieve the first mached Json data from a key (recursive search)
   * @param string $key
   * @param array $parents (optionnal): a list of parent keys to be searched  (in tree order)
   * @return mixed value or array
   */
  public function getSubData( $key, $parents = [] )
  {
    $ret = $this->jsonData;
    if ( !is_array( $parents ) ) {
      throw new \Exception("Erreur GetJsonPage::getSubData(" . $key . ", " . strval($subkeys) . ") :  le deuxième argument doit être un tableau.", 1);
    }
    if ( sizeof( $parents ) > 0 ) {
      foreach ($parents as $index => $parent) {
          $ret = PhpF::recursiveArrayKey( $parent, $ret );
      }
    }
    $ret = PhpF::recursiveArrayKey( $key, $ret );
		return $ret;
  }

  /**
   * Merge all Json data corresponding to a same key (recursive search)
   * @param string $key : values to be merged are direct children of this key
   * @param array $parents (optionnal) : a list of parent keys to be the searched (out of any tree order)
   * @return array
   */
  public function mergeSubData( $key, $parents = [] )
  {
    if ( !is_array( $parents ) ) {
      throw new \Exception("Erreur GetJsonPage::mergeSubData(" . $key . ", " . strval($parents) . ") :  le deuxième argument doit être un tableau.", 1);
    }
    if ( sizeof( $parents ) > 0 ) {
      foreach ($parents as $index => $parent) {
        $temp = $this->getSubData($parent);
        if ( isset( $ret ) ) {
          $ret = array_merge( $ret, PhpF::recursiveArrayKeyMerge( $key, $temp ) );
        }
        else {
          $ret = PhpF::recursiveArrayKeyMerge( $key, $temp );
        }
      }
    }
    else {
      $ret = PhpF::recursiveArrayKeyMerge( $key, $this->jsonData );
    }
		return $ret;
  }

  private function retrieveData( $service )
  {
    //if ( !isset( wfMgwConfig('json-pages', $service ) ) ) throw new \Exception("La cible '" . $service . "' n'existe pas.", 1);

		$title = Title::newFromText(
      wfMgwConfig('json-pages', $service )['title'],
      constant( wfMgwConfig('json-pages', $service )['namespace'] ) );
 		if ( $title->getArticleID() == -1 ) {
      throw new \Exception("La page json relative à $service n'existe pas.", 1);
    }
		$page = WikiPage::factory( $title );

    return json_decode( $page->getContent()->getNativeData(), true );
  }

  private function getFullURL( $service )
  {
    //if (!isset(wfMgwConfig('json-pages', $service ))) throw new \Exception("La cible '" . $service . "' n'existe pas.", 1);

		$title = Title::newFromText(
      wfMgwConfig('json-pages', $service )['title'],
      constant( wfMgwConfig('json-pages', $service )['namespace'] ) );
 		if ( $title->getArticleID() == -1 ) {
      throw new \Exception("La page json relative à $service n'existe pas.", 1);
    }
    return $title->getFullURL();
  }
}
