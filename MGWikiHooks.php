<?php
/**
 * MGWikiHooks
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license LGPL-2.0+
 */

$GLOBALS['wgExtensionCredits']['other'][] = array(
	'path' => __FILE__,
	'name' => 'MGWiki Hooks',
	'version' => '0.1',
	'author' => array( 'Sébastien Beyou' ),
	'url' => 'https://mgwiki.univ-lyon1.fr',
	'descriptionmsg' => 'mgwikihooks-desc',
	'license-name' => 'LGPL-2.0+'
);

$GLOBALS['wgMessagesDirs']['MGWikiHooks'] = __DIR__ . '/i18n';

$GLOBALS['wgHooks']['sfHTMLBeforeForm'][] = 'MGWikiHooks::onsfHTMLBeforeForm';
$GLOBALS['wgHooks']['PrefsEmailAudit'][] = 'MGWikiHooks::onPrefsEmailAudit';
$GLOBALS['wgHooks']['SMWSQLStore3::updateDataBefore'][] = 'MGWikiHooks::onSMW_SQLStore_AfterDataUpdateComplete';

class MGWikiHooks {

	/**
	 * Display a warning if the user account and user page don’t together exist or are missing.
	 * 
	 * @param Title $targetTitle Page title
	 * @param string $pre_form_html String displayed just before the form
	 */
	public static function onsfHTMLBeforeForm( $targetTitle, &$pre_form_html ) {

		# Only use this hook on user pages
		if( $targetTitle->getNamespace() != NS_USER ) return;

		# Get the user account
		$user = User::newFromName( $targetTitle->getText() )->getId();

		if( $targetTitle->exists() xor $user ) {

			$pre_form_html = '<div class="infoMessage">';
			if( $targetTitle->exists() ) $pre_form_html .= wfMessage( 'userpage-without-useraccount' )->escaped();
			else $pre_form_html .= wfMessage( 'useraccount-without-userpage' )->escaped();
			$pre_form_html .= "</div>\n";
		}

		return true;
	}

	public static function onPrefsEmailAudit( $user, $oldaddr, $newaddr ) {

		
	}

	public static function onSMW_SQLStore_AfterDataUpdateComplete( SMWSQLStore3 $store, SMWSemanticData $semanticData ) {//, CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator ) {

		#var_dump( $store );
		#echo "\n\n------------------------\n\n";
		#var_dump( $semanticData );
		#echo "\n\n------------------------\n\n";
		#var_dump( $compositePropertyTableDiffIterator );
		#exit;
	}	
}
