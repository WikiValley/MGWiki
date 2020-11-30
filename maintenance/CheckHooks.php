<?php
/**
 * Fichier de maintenance pour vérifier les Hooks utilisés par MGWiki lors des mises à jour de MediaWiki.
 * Les Hooks spécifiques à MGWiki sont automatiquement insérés (si l'accroche du code n'a pas changé).
 * (doit être exécuté avec les droits d'écriture)
 */
if ( !isset($includeTools) ) include ('Tools.php');

if ( !isset($customHooks) ) $customHooks = [
  "ApiAllow" => [
    "fileIdentifier" => 'class ApiMain extends ApiBase',                  // chaîne de caractères unique permettant d'identifier le fichier (ici: includes/api/ApiMain.php)
    "stringIdentifier" => '!$user->isAllowed( \'read\' )',                // chaîne de caractères unique permettant d'identifier le lieu d'insertion (ici : l.1419)
    "customCode" => '&& !Hooks::run( \'ApiAllow\', [ $module, $user ] )'  // code à insérer après stringIdentifier
  ]
];

function checkHooks( $customHooks )
{
  $info = json_decode(file_get_contents( getPath('extension') . '/extension.json' ), true );
  $endL = '
';
  $report = fopen('maintenance-report.txt', 'a');
  fputs($report, $endL . '
--------------------------------
CheckHooks - ' . date('Y-m-d H:i:s') . '
--------------------------------' . $endL );

  foreach ($info['Hooks'] as $key => $value) {
    $shell = ' && stdbuf -oL grep -r -n -E "Hooks::run(WithoutAbort)?\( \'' . $key . '\'" | head -n1';
    $grep = shell_exec( 'cd ' . getPath('base') . '/includes' . $shell );
    if ( is_null( $grep ) ) { $grep = shell_exec( 'cd ' . getPath('base') . '/maintenance' . $shell ); }
    if ( is_null( $grep ) ) { $grep = shell_exec( 'cd ' . getPath('base') . '/skins' . $shell ); }
    if ( is_null( $grep ) ) { $grep = shell_exec( 'cd ' . getPath('base') . '/extensions' . $shell ); }
    if ( is_null( $grep ) ) {
     if ( array_key_exists( $key, $customHooks ) ) {
       $add = addHook( $customHooks[$key] );
       fputs($report, $key . $add . $endL);
       echo $key . $add . $endL;
     }
     else {
       fputs($report, $key . ' : ECHEC' . $endL);
       echo $key . ' : ECHEC' . $endL;
     }
    }
    else {
      fputs($report, $key . ' : OK' . $endL);
      echo $key . ' : OK' . $endL ;
    }
  }
  echo $endL . '
  ==================================
  => voir: maintenance-report.txt <=
  ==================================
  ';
  fclose($report);
}

# insère le hook customisé dans MediaWiki-core
# @param array $hook
# @return bool
function addHook( $hook )
{
  $grep = shell_exec( 'cd ' . getPath('base') . ' && grep -r -n -E "' . $hook[ 'fileIdentifier' ] . '"' );
  if ( is_null( $grep ) ) {
    $mess = ' : ECHEC (hook customisé)  : occurence "' . $hook[ 'fileIdentifier' ] . '" introuvable dans les fichiers';
  }
  else {
    $grep = explode( ':', $grep );
    $file = getPath( 'base' ) . '/' . $grep[0];
    $code = file_get_contents( $file );
    $ret = substr_count($code, $hook['stringIdentifier']);
    switch ( $ret ) {
      case 0:
        $mess = ' : ECHEC (MGW-custom) : '. $file .' : "' . $hook[ 'stringIdentifier' ] . '" introuvable';
        break;
      case 1:  // ajout du code
        $code = str_replace( $hook['stringIdentifier'], $hook['stringIdentifier'] . ' ' . $hook['customCode'], $code );
        file_put_contents( $file, $code);
        $mess = ' : OK (customized)';
        break;
      default:
        $mess = ' : ECHEC (MGW-custom) : '. $file .' : "' . $hook[ 'stringIdentifier' ] . '" se trouve plusieurs fois.
  "' . $hook['customCode'] . '" doit être ajouté manuellement.';
        break;
    }
  }
  return $mess;
}

checkHooks( $customHooks );
?>
