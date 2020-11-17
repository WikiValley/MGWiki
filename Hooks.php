<?php

use MediaWiki\Extension\MGWikiDev\Parsers;

/**
 * MGWiki - development version
 * General functions and hooks.
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @author Alexandre Brulet
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWikiDev
 */
class MGWikiHooks {

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
}
