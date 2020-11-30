<?php
$self = 'MGWikiDev';
/**
 * Insertion de hooks customisés dans le code de Mediawiki
 */
$customHooks = [
  "ApiAllow" => [
    "fileIdentifier" => 'class ApiMain extends ApiBase',                  // chaîne de caractères unique permettant d'identifier le fichier (ici: includes/api/ApiMain.php)
    "stringIdentifier" => '!$user->isAllowed( \'read\' )',                // chaîne de caractères unique permettant d'identifier le lieu d'insertion (ici : l.1419)
    "customCode" => '&& !Hooks::run( \'ApiAllow\', [ $module, $user ] )'  // code à insérer après stringIdentifier
  ]
];

/**
 * Liste des versions de MediaWiki conçernées par cette màj
 */
$mw_releases = array(
  '34' => '1',
  '35' => '1');

/**
 * déclaration des chaînes de caractères à rechercher dans les releases notes
 */
$searchInCode = array(
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

include ('Tools.php');
$includeTools = true;

include ('CheckHooks.php');
include ('ScreenReleaseNotes.php');

?>
