<?php
/**
 * MGWiki - development version
 * General functions file.
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @author Alexandre Brulet
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWiki
 */

class MGWikiDev {

	/**
	 * Divers
	 */
  public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
    //Modules pour toutes les pages
    $out->addModules('ext.mgwiki-dev');
  }
}
