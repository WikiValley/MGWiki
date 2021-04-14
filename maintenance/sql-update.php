<?php
/**
 * mise à jour les fichiers sql/*.sql et config/db-tables.json
 * depuis mgwiki.sql
 */

$main = file_get_contents('../mgwiki.sql');
$main = str_replace( '
', '', $main );

$continue = true;

$queue = $main;
$tables = [];

while ( $continue ) {
  $test = preg_match( '/(CREATE TABLE IF NOT EXISTS \/\*_\*\/([a-z_]+) [^;]+;)(.*)/', $queue, $matches );
  if ( $test > 0 ) {
    $file = '../sql/addTable-' . $matches[2] . '.sql';
    file_put_contents( $file, $matches[1] );
    $queue = $matches[3];
    $tables[] = $matches[2];
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

if ( count( $tables ) > 0 ) {
  file_put_contents( '../config/db-tables.json', json_encode( $tables ) );
}

echo 'OK
';
