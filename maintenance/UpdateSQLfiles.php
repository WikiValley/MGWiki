<?php
/**
 * Fichier de maintenance pour mettre à jour les fichiers sql/*.sql depuis mgwiki.sql
 */

$main = file_get_contents('../mgwiki.sql');
$main = str_replace( '
', '', $main );

$continue = true;

$tables = $main;
while ( $continue ) {
  $test = preg_match( '/(CREATE TABLE IF NOT EXISTS \/\*_\*\/([a-z_]+) [^;]+;)(.*)/', $tables, $matches );
  if ( $test > 0 ) {
    $file = '../sql/addTable-' . $matches[2] . '.sql';
    file_put_contents( $file, $matches[1] );
    $tables = $matches[3];
  }
  else break;
}

$indexes = $main;
while ( $continue ) {
  $test = preg_match( '/(CREATE INDEX \/\*i\*\/([a-z_]+) [^;]+;)(.*)/', $indexes, $matches );
  if ( $test > 0 ) {
    $file = '../sql/addIndex-' . $matches[2] . '.sql';
    file_put_contents( $file, $matches[1] );
    $indexes = $matches[3];
  }
  else break;
}

echo 'OK
';
