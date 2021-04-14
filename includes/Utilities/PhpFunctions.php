<?php

namespace MediaWiki\Extension\MGWiki\Utilities;

/**
  * Fonctions php diverses
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
    * recherche récursivement une paire 'clé' => 'valeur'
    * @return bool
    */
  public function recursiveArrayKeyValue ( $key, $value, $array )
  {
    $recursive = self::recursiveIterator( $array );
    foreach ( $recursive as $kkey => $vvalue ) {
      if ( $key === $kkey && $value === $vvalue ) {
          return true;
      }
    }
    return false;
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

  /**
    * recherche récursivement une clé, fusionne le résultat si plusieurs occurences
    * @return mixed (valeur, array ou array_merge)
    */
  public function recursiveArrayKeyValueCount ( $key, $value, $array )
  {
    $recursive = self::recursiveIterator( $array );
    $ret = 0;
    foreach ($recursive as $kkey => $vvalue) {
      if ( $key === $kkey && $value == $vvalue ) {
          $ret++;
      }
    }
    return $ret;
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

  /**
   * sets $var to $val if null, empty or undefined
   * @param mixed &$var
   */
   public function false ( &$var, $val = false ) {
     if ( !isset( $var ) || is_null( $var ) || empty( $var ) ) {
       $var = $val;
     }
   }

 /**
  * sets $var to $val if empty, false or undefined
  * @param mixed &$var
  */
  public function null ( &$var, $val = null ) {
    if ( !isset( $var ) || empty( $var ) || !$var ) {
      $var = $val;
    }
  }

  /**
   * sets $var to $val if null, undefined or false
   * @param mixed &$var
   * @param mixed $val replacement value (default = empty)
   * @param bool $int_escape wether to consider 0 as false or not
   * @return void (changes applied by reference)
   */
  public function empty ( &$var, $val = '', $int_escape = false ) {
    if ( !isset( $var ) || is_null( $var ) ||
    ( (!$int_escape && !$var) || ( $int_escape && !$var && $var !== 0 ) ) ) {
     $var = $val;
    }
  }

  /**
   * sets $var to int if numeric, null or empty elseaway
   * @param mixed &$var
   * @param bool $empty
   * @param bool $keep
   */
  public function int ( &$var, $empty = false, $keep = false ) {
    $int = (int)$var;
    if ( 'test'.$int == 'test'.$var ) {
      $var = $int;
    }
    elseif ( !$keep ) {
      $var = ( $empty ) ? '' : null;
    }
  }

  public static function sansAccent( $string ){
    $ins = ['À','Á','Â','Ã','Ä','Å','à','á','â','ã','ä','å','Ò','Ó','Ô','Õ','Ö','Ø','ò','ó','ô','õ','ö','ø','È','É','Ê','Ë','è','é','ê','ë','Ç','ç','Ì','Í','Î','Ï','ì','í','î','ï','Ù','Ú','Û','Ü','ù','ú','û','ü','ÿ','Ñ','ñ','œ' ,'æ'];
    $out = ['A','A','A','A','A','A','a','a','a','a','a','a','O','O','O','O','O','O','o','o','o','o','o','o','E','E','E','E','e','e','e','e','C','c','I','I','I','I','i','i','i','i','U','U','U','U','u','u','u','u','y','N','n','oe','ae'];
    return str_replace( $ins, $out ,$string );
  }
}
