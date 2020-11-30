<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

/**
  * Fonctions diverses
  */
class PhpFunctions
{
  /**
    * recherche récursivement une clé
    * @return mixed (première valeur retrouvée ou null)
    */
  public function recursiveArrayKey ( $needle, $array )
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
  public function recursiveArrayKeyMerge ( $needle, $array )
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

  private function recursiveIterator( $array ) {
    $iterator  = new \RecursiveArrayIterator( $array );
    $recursive = new \RecursiveIteratorIterator(
        $iterator,
        \RecursiveIteratorIterator::SELF_FIRST
    );
    return $recursive;
  }

  /**
    * recherche les doublons dans un tableau
    * @return mixed array ou null
    */
  public function array_doublons( $array ) {
    if ( !is_array( $array ) ) return false;
    $r_valeur = Array();
    $array_unique = array_unique($array);

    if ( count( $array ) - count( $array_unique ) ) {
      for ( $i=0; $i< count( $array ); $i++ ) {
        if ( !array_key_exists( $i, $array_unique ) )
          $r_valeur[] = $array[$i];
      }
    }
    return $r_valeur;
  }
}
