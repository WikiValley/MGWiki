<?php
/**
 * MGWiki
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license LGPL-2.0+
 */

$GLOBALS['wgExtensionCredits']['other'][] = array(
	'path' => __FILE__,
	'name' => 'MGWiki',
	'version' => '0.1',
	'author' => array( 'Sébastien Beyou' ),
	'url' => 'https://mgwiki.univ-lyon1.fr',
	'descriptionmsg' => 'mgwiki-desc',
	'license-name' => 'LGPL-2.0+'
);

$GLOBALS['wgMessagesDirs']['MGWiki'] = __DIR__ . '/i18n';

$GLOBALS['wgHooks']['sfHTMLBeforeForm'][] = 'MGWiki::onsfHTMLBeforeForm';
$GLOBALS['wgHooks']['PrefsEmailAudit'][] = 'MGWiki::onPrefsEmailAudit';
$GLOBALS['wgHooks']['SMW::SQLStore::AfterDataUpdateComplete'][] = 'MGWiki::onSMW_SQLStore_AfterDataUpdateComplete';

$GLOBALS['wgGroupPermissions']['sysop']['mgwikimanageusers'] = true;

class MGWiki {

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

	public static function onSMW_SQLStore_AfterDataUpdateComplete( SMWSQLStore3 $store, SMWSemanticData $semanticData, SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator ) {

		global $wgUser;

		# Get user
		$subject = $semanticData->getSubject();
		if( $subject->getNamespace() != NS_USER ) return;
		$user = User::newFromName( $subject->getDBkey() );
		if( !$user->getId() ) return;

		# Check permissions
		if( $wgUser->getId() != $user->getId() && !$wgUser->isAllowed( 'mgwikimanageusers' ) ) return;

		# Get properties after they are saved
		$properties = $semanticData->getProperties();
		if( count( $properties ) == 0 ) return;

		# Search if there is an E-mail property, and if so update the email preference
		if( array_key_exists( 'E-mail', $properties ) ) {
			$emailValues = $semanticData->getPropertyValues( $properties['E-mail'] );
			if( count( $emailValues ) == 0 || current( $emailValues )->getDIType() != SMWDataItem::TYPE_BLOB ) return;
			$email = current( $emailValues )->getString();
			$wgUser->setEmail( $email );
			$wgUser->saveSettings();
		}
	}	
}
