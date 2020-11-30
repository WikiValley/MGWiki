<?php
function listDir( $chemin, $extension = null ) {
  $return = [];
  $filetype = filetype( $chemin );
  $do = true;
  if ( !is_null( $extension ) && ( preg_match("/$extension$/", $chemin ) < 1 ) ) {
    $do = false;
  }

  // On retourne le chemin
  if ( !is_dir($chemin) && $do ){
    $return[] = $chemin;
  }

  // Si $chemin est un dossier on appelle la fonction explorer() pour chaque élément (fichier ou dossier) du dossier$chemin
  if( is_dir($chemin) ){
    $me = opendir($chemin);
    while( $child = readdir($me) ){
      if( $child != '.' && $child != '..' ){
          $return = array_merge( listDir( $chemin.DIRECTORY_SEPARATOR.$child, $extension ), $return );
      }
    }
  }
  return $return;
}

# $target = 'base' || 'extension' || ''
function getPath( $target )
{
  $curPath = explode( '/', getcwd() );
  $path = ''; $i = 1;
  while ( isset( $curPath[$i] ) && ( $curPath[$i] != 'wiki' ) ) {
    $path .= '/' . $curPath[$i]; $i ++;
  }
  $path .= '/wiki';
  switch ( $target ) {
    case 'base':
      return $path;
      break;
    case 'extension':
      return $path . '/extensions/MGWikiDev';
      break;
  }
}
?>
