<?php
/**
 * MGWiki - “marguerite” (ox-eye daisy) feature
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWiki
 */

/**
 * Class for the “marguerite” (ox-eye daisy) feature.
 */
class MGWikiMarguerite {

	/**
	 * Hook function for MediaWiki’s hook "BeforePageDisplay".
	 *
	 * This hook simply adds the JavaScript module ext.mgwiki.edit.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return true
	 */
	static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {

		$out->addModules( 'ext.mgwiki' );

		return true;
	}
}
