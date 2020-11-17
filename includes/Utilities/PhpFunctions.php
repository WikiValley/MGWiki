<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

/**
  * Fonctions diverses
  */
trait PhpFunctions
{
  /**
    * recherche récursivement une clé
    * @return mixed (première valeur retrouvée ou null)
    */
  protected function recursiveArrayKey ( $needle, $array )
  {
    $recursive = self::recursiveIterator( $array );
    foreach ( $recursive as $key => $value ) {
      if ( $key === $needle ) {
          return $value;
      }
    }
    return null;
  }

  /**
    * recherche récursivement une clé, fusionne le résultat si plusieurs occurences
    * @return mixed (valeur, array ou array_merge)
    */
  protected function recursiveArrayKeyMerge ( $needle, $array )
  {
    $recursive = self::recursiveIterator( $array );
    $ret = [];
    foreach ($recursive as $key => $value) {
      if ($key === $needle) {
          $ret[] = $value;
      }
    }
    switch ( sizeof( $ret ) ) {

      case 0:
        return null;
        break;

      case 1:
        return $ret[0];
        break;

      default:
        $merge = [];
        foreach ( $ret as $key => $value ) {
          if ( is_array( $value ) ) {
            foreach ( $value as $kkey => $vvalue ) {
              $merge[$kkey] = $vvalue;
            }
          }
          else {
            $merge[] = $value;
          }
        }
        return $merge;
        break;
    }
  }

  protected function recursiveIterator( $array ) {
    $iterator  = new \RecursiveArrayIterator( $array );
    $recursive = new \RecursiveIteratorIterator(
        $iterator,
        \RecursiveIteratorIterator::SELF_FIRST
    );
    return $recursive;
  }
}
