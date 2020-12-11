<?php

use MediaWiki\Extension\MGWikiDev\Parsers;
use MediaWiki\Extension\MGWikiDev\Utilities\UsersFunctions as UserF;

/**
 * MGWiki - development version
 * General functions and hooks.
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @author Alexandre Brulet
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWikiDev
 */
class MGWikiDevHooks {

	/**
	 * Chargement de l'extension
	 */
  public static function onExtensionLoad() {

    define("MGW_DB_UNSET", 0);
    define("MGW_DB_UNCHANGED", 1);
    define("MGW_DB_INSERTED", 2);
    define("MGW_DB_UPDATED", 3);
    define("MGW_DB_DROPED", 4);
    define("MGW_DB_ERROR", 5);

    // définition des variables de type string
    global $wgMgwStringVars;
    $wgMgwStringVars = [
    	'utilisateur_nom',
    	'utilisateur_prenom',
    	'institution_nom',
      'groupe_type_nom'
    ];
    // définition des niveaux de permissions
    global $wgMgwLevels;
    $wgMgwLevels = [
      0 => 'U0',
      1 => 'U1',
      2 => 'U2',
      3 => 'U3',
      4 => 'sysop'
    ];
  }

	/**
	 * Chargement du module MGWikiDev
	 */
  public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
    //Modules pour toutes les pages
    $out->addModules('ext.mgwiki-dev');
  }

	/**
   * ! CUSTOM HOOK ! (cf readme -> ApiMain.php changes)
	 * Autoriser l'API getjson quelque soit l'utilisateur
	 */
  public static function onApiAllow( $module, $user )
  {
    global $wgRequest;
    if ( $wgRequest->getText( 'action' ) == 'getjson' )
    {
      return true;
    }
    return false;
  }

   // Register any render callbacks with the parser
   public static function onParserFirstCallInit( Parser $parser ) {

      // Create a function hook associating the "example" magic word with renderExample()
      $parser->setFunctionHook( 'mgw-onclick', [ Parsers::class, 'onclickSpan' ] );
   }

 	/**
 	 * Hook: LoadExtensionSchemaUpdates
   *
   * fonction exécutée par maintenance/Update.php
   *
 	 * @param DatabaseUpdater $updater
 	 */
 	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {

 		$dir = __DIR__ . '/sql';
    $tables = array(
      'mgw_utilisateur',
      'mgw_archive_utilisateur',
      'mgw_institution',
      'mgw_archive_institution',
      'mgw_groupe',
      'mgw_archive_groupe',
      'mgw_groupe_membre',
      'mgw_archive_groupe_membre',
      'mgw_groupe_type',
      'mgw_archive_groupe_type',
      'mgw_institution_groupe'
    );

    foreach( $tables as $table ) {
      $tableSQLFile = "$dir/addTable-" . $table . ".sql";
      $indexSQLfile = "$dir/addIndex-" . $table . "_lookup.sql";
      $updater->addExtensionTable( 'mgw_' . $table, $tableSQLFile );
      if ( file_exists($indexSQLfile) ) {
        $updater->addExtensionIndex( $table, $table.'_lookup', $indexSQLfile );
      }
    }
  }

 	/**
 	 * login avec mail + authentification insensible à la casse
 	 */
  public static function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {
    global $wgRequest;
    // uniquement pour le formulaire de login
    if ( $wgRequest->getGlobalRequestURL() == '/wiki/index.php/Sp%C3%A9cial:Connexion' )
    {
      $formDescriptor['username']['label'] = 'E-mail  ou  nom d\'utilisateur';
      $formDescriptor['username']['filter-callback'] = function ( $val, $array ) {
        $val = htmlspecialchars($val, ENT_QUOTES );

        // si mail valide on récupère le nom d'utilisateur correspondant
        if ( preg_match( '/@/', $val ) > 0 )
          $r = UserF::emailExists( $val );

        // on corrige les erreurs de casse
        else $r = UserF::userExists( $val, false );

        if ( !$r )  return htmlspecialchars_decode( $val, ENT_QUOTES ); // = utilisateur inconnu
        else        return $r;
      };
    }
  }
}
