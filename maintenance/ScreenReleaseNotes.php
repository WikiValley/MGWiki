<?php
/**
 * UPDATES:
 * Script de maintenance ayant pour but de flécher la documentation
 * sur les modifications apportées à MediaWiki pouvant impacter
 * l'extension MGWiki.
 *
 * @return maintenance-report.txt
 */

if ( !isset( $includeTools ) ) include ('Tools.php');
if ( !isset( $self ) ) $self = 'MGWikiDev';
if ( !isset( $mw_releases ) ) $mw_releases = array(
  '34' => '1',
  '35' => '1');

if (!isset( $searchInCode ) ) $searchInCode = array(
  [
    'type'    => 'classe',
    'regexp'  => '/^use ([a-zA-Z]+\\\)*([a-zA-Z]+)[ ]?;/',
    'match'   => 2 ],
  [
    'type'    => 'classe',
    'regexp'  => '/extends (\\\)?([a-zA-Z]+) /',
    'match'   => 2 ],
  [
    'type'    => 'skin',
    'regexp'  => '/wfLoadSkin\( \'([a-zA-Z]+)\' \)/',
    'match'   => 1 ],
  [
    'type'    => 'extension',
    'regexp'  => '/wfLoadExtension\( \'([a-zA-Z]+)\' \)/',
    'match'   => 1 ],
  [
    'type'    => 'variable globale',
    'regexp'  => '/wg[a-zA-Z]+/',
    'match'   => 0 ]
);

/**
 * Recherche et extraction des commentaires de versions
 */
function extractReleaseNotes ( $relN, $relV ) {
  $file = "RELEASE-NOTES-$relN.$relV.txt";

  $ofile = fopen( $file, 'a');
  $url = 'https://phabricator.wikimedia.org/source/mediawiki/browse/REL'.$relN.'_'.$relV.'/RELEASE-NOTES-'.$relN.'.'.$relV;
  $curl = "
";
  $curl = shell_exec( "curl $url" );
  fputs($ofile, $curl);
  fclose($ofile);
  // lecture du fichier html
  $ofile = fopen($file, 'r');
  $out = '';
  $endL = '
';
  while(! feof( $ofile ) ) {
    $line = fgets( $ofile );
    preg_match('/^.*<td class="phabricator-source-code">(.*)/', $line, $matchL);
    preg_match( '/<a data-n="([0-9]+)">/', $line, $matchN );
    if ( isset($matchL[1]) ){
      $out .= htmlspecialchars_decode($matchL[1],ENT_QUOTES) . '@ligne@' . $matchN[1] . $endL;
    }
  }
  fclose($ofile);

  // réécriture sans les balises html
  $ofile = fopen($file, 'w');
  fwrite ( $ofile , $out) ;
  fclose ( $ofile ) ;

  // lecture des données
  $ofile = fopen($file, 'r');
  $data = [];
  $h1 = '';
  $h2 = '';
  $h3 = '';
  $h4 = '';
  $l = '1';
  $text = null;
  $plain = false;
  while(! feof( $ofile ) ) {
    $line = fgets( $ofile );
    $line = explode( '@ligne@', $line );
    if ( isset( $line[1] ) ) {
      preg_match('/[0-9]+/', $line[1], $matches);
      $line[1] = $matches[0];
    }
    $done = false;
    $title = false;
    $screen = preg_match('/^(= )(.*)( =)/', $line[0], $matches);
    if ( isset($screen) && $screen > 0 ) {
      $new_h1 = $matches[2];
      $new_h2 = '';
      $new_h3 = '';
      $new_h4 = '';
      $new_l = $line[1];
      $done = true;
      $title = true;
    }
    $screen = preg_match('/^(== )(.*)( ==)/', $line[0], $matches);
    if ( isset($screen) && $screen > 0 ) {
      $new_h2 = $matches[2];
      $new_h3 = '';
      $new_h4 = '';
      $new_l = $line[1];
      $done = true;
      $title = true;
    }
    $screen = preg_match('/^(=== )(.*)( ===)/', $line[0], $matches);
    if ( isset($screen) && $screen > 0 ) {
      $new_h3 = $matches[2];
      $new_h4 = '';
      $new_l = $line[1];
      $done = true;
      $title = true;
    }
    $screen = preg_match('/^(==== )(.*)( ====)/', $line[0], $matches);
    if ( isset($screen) && $screen > 0 ) {
      $new_h4 = $matches[2];
      $new_l = $line[1];
      $done = true;
      $title = true;
    }
    $screen = preg_match('/^[a-zA-Z0-9]/', $line[0], $matches);
    if ( isset($screen) && $screen < 1 ) {
      $screen2 = preg_match('/^\*/', $line[0]);
      if ( isset($screen2) && $screen2 > 0 ) {
        $done = true;
        $new_l = $line[1];
      }
    } else $plain = true;

    if ($title) $plain = false;
    $empty = ["","
"];
    if ( $done && !$plain && !is_null($text) && !in_array($text,$empty) ) {
      $data[] = [
        'h1' => $h1,
        'h2' => $h2,
        'h3' => $h3,
        'h4' => $h4,
        'l' => $l,
        'url' => $url,
        'text' => $text
      ];
      if ( isset( $new_h1 ) ) $h1 = $new_h1;
      if ( isset( $new_h2 ) ) $h2 = $new_h2;
      if ( isset( $new_h3 ) ) $h3 = $new_h3;
      if ( isset( $new_h4 ) ) $h4 = $new_h4;
      if ( isset( $new_l ) ) $l = $new_l;
      $text = null;
    }

    if ( !$title ) {
      if ( is_null( $text ) ) $text = $line[0];
      else $text .= $endL . $line[0];
    }
  }
  fclose( $ofile );
  unlink( $file );
  return( $data );
}

/**
 * Recherche et extraction des classes, skins et $wg<Var> dans le code MGWiki ...
 */
function screenCode ( $searchInCode, $self ) {
  $return = [];
  $endL = '
';
  $mgw_phpfiles = listDir( getPath( 'extension' ), 'php' );    // recherche dans tous les fichiers .php de l'extension
  $mgw_phpfiles[] = getPath( 'base' ) . '/LocalSettings.php';  // on ajoute LocalSettings
  foreach ( $mgw_phpfiles as $key => $file ) {
    $ofile = fopen($file, 'r');
    while(! feof( $ofile ) ) {
      $line = fgets( $ofile );
      $done = false;
      foreach( $searchInCode as $num => $exp ) {
        preg_match( $exp['regexp'], $line, $matches );
        $m = preg_match('/'.$self.'/', $line );
        $n = $exp['match'];
        if ( isset( $matches[ $n ] ) && $m < 1 ){
          if ( sizeof( $return ) > 0 ) {
            foreach ( $return as $key => $value ) {
              if ( $return[$key]['string'] == $matches[ $n ] && in_array( $file, $return[$key]['files'])) {
                $done = true;
              }
              if ( $return[$key]['string'] == $matches[ $n ] && !in_array( $file, $return[$key]['files'])) {
                $return[$key]['files'][] = $file;
                $done = true;
              }
            }
          }
          if ( !$done ) {
            $return[] = [
              'string' => $matches[ $n ],
              'files' => [ $file ],
              'type'  => $exp['type']
            ];
          }
        }
      }
    }
    fclose($ofile);
  }
  return $return;
}

// import des releases notes au format html
$releaseNotes = [];
foreach ( $mw_releases as $relV => $relN ) {
  $data = extractReleaseNotes( $relN, $relV );
  $releaseNotes = array_merge( $releaseNotes, $data );
}

// recherche des classes utilisées dans les releases notes
$includedClasses = screenCode( $searchInCode, $self );
$endL = '
';
$out = '
----------------------------------------
ScreenReleaseNotes - ' . date('Y-m-d H:i:s') . '
----------------------------------------
Les modifications suivantes peuvent impacter le fonctionnement de MGWiki.
Sources :
';
foreach ( $mw_releases as $relV => $relN ) {
  $out .= '  https://phabricator.wikimedia.org/source/mediawiki/browse/REL'.$relN.'_'.$relV.'/RELEASE-NOTES-'.$relN.'.'.$relV.$endL;
}

try
{
  $out1 = null;
  foreach ( $includedClasses as $classKey => $classArray ) {
    $out1 = $endL . '  -- ' . $classArray['string'] . ' ('. $classArray['type'] .') --' . $endL;
    $out1 .= '    ' . implode( $endL . '    ', $classArray['files'] ) . $endL . $endL;
    $out2 = null;
    $url = null;
    foreach ( $releaseNotes as $noteKey => $noteArray ) {
      if ( preg_match( '/'.$classArray['string'].'/', $noteArray['text'] ) > 0 ) {
        if ( is_null( $out2 ) ) $out2 = '';
        if ( $noteArray['url'] != $url ) {
          $url = $noteArray['url'];
          $out2 .= '  ' . strtoupper( $noteArray['h1'] ) . $endL . $endL;
          //$out2 .= $url . $endL . $endL;
        }
        if ( $noteArray['h2'] != '' ) $chapter[] = $noteArray['h2'];
        if ( $noteArray['h3'] != '' ) $chapter[] = $noteArray['h3'];
        if ( $noteArray['h4'] != '' ) $chapter[] = $noteArray['h4'];
        if ( isset( $chapter ) ) {
          $out2 .= '(l.'. $noteArray['l'] .') >> ' . implode( ' > ', $chapter) . ' :' . $endL;
          $out2 .= $noteArray['text'] . $endL . $endL;
          unset($chapter);
        }
      }
    }
    if ( !is_null( $out2 ) ) $out1 .= $out2;
    else $out1 = null;
    if ( !is_null( $out1 ) ) {
      $out .= $out1;
      $out1 = null;
    }
  }
} catch (\Exception $e) {
  $out = $e;
}

$report = fopen('maintenance-report.txt', 'a');
fputs($report, $endL . $out);
fclose($report);
echo $out . $endL . '
==================================
=> voir: maintenance-report.txt <=
==================================
';
?>
