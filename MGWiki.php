<?php
/**
 * MGWiki
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 */

if( !function_exists( 'wfLoadExtension' ) ) {

	$GLOBALS['wgExtensionCredits']['other'][] = array(
		'path' => __FILE__,
		'name' => 'MGWiki',
		'version' => '0.1',
		'author' => array( 'Sébastien Beyou' ),
		'url' => 'https://mgwiki.univ-lyon1.fr',
		'descriptionmsg' => 'mgwiki-desc',
		'license-name' => 'GPL-3.0+'
	);

	$GLOBALS['wgMessagesDirs']['MGWiki'] = __DIR__ . '/i18n';

	$GLOBALS['wgHooks']['userCan'][] = 'MGWiki::onuserCan';
	$GLOBALS['wgHooks']['sfHTMLBeforeForm'][] = 'MGWiki::onsfHTMLBeforeForm';
	$GLOBALS['wgHooks']['PrefsEmailAudit'][] = 'MGWiki::onPrefsEmailAudit';
	$GLOBALS['wgHooks']['SMW::SQLStore::AfterDataUpdateComplete'][] = 'MGWiki::onSMW_SQLStore_AfterDataUpdateComplete';

	$GLOBALS['wgGroupPermissions']['sysop']['mgwikimanageusers'] = true;
	
	# Initial delay, in seconds, after a newly registered user is considered inactive
	$GLOBALS['wgMGWikiInitialDelayBeforeInactive'] = 14*24*60*60;
}

class MGWiki {

	const nomField = 'Nom';
	const prenomField = 'Prénom';
	const emailField = 'E-mail';
	const typeDeGroupeField = 'Type_de_groupe';
	const statutPersonneField = 'Statut_personne';
	const fields = array( self::nomField, self::prenomField, self::emailField );

	/**
	 * Check permissions for actions.
	 *
	 * @param Title $title Title of the page
	 * @param User $user User about to do the action
	 * @param string $action Requested action
	 * @param bool|mixed $result True or false to authorize or deny the action
	 */
	public static function onuserCan( Title &$title, User &$user, $action, &$result ) {

		global $wgMGWikiForms;

		# Only edit permission is checked
		if( $action != 'edit' )
			return true;

		# Check permissions when the user wants to edit someone else’s user page
		if( $title->getNamespace() == NS_USER && $title->getText() == $user->getName() ) {
			return true;
		}

		# Check permissions for forms
		$titleEnglish = '';
		$ns = MWNamespace::getCanonicalName( $title->getNamespace() );
		if( $ns ) $titleEnglish .= $ns . ':';
		$titleEnglish .= $title->getText();
		foreach( $wgMGWikiForms as $form => $params ) {
			if( array_key_exists( 'RegexPageName', $params ) && preg_match( $params['RegexPageName'], $titleEnglish ) && array_key_exists( 'RequiredRights', $params ) && is_string( $params['RequiredRights'] ) ) {
				if( !$user->isAllowed( $params['RequiredRights'] ) ) {
					$result = false;
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Display a warning if the user account and user page don’t together exist or are missing.
	 *
	 * @param Title $targetTitle Page title
	 * @param string $pre_form_html String displayed just before the form
	 * @return true
	 */
	public static function onsfHTMLBeforeForm( $targetTitle, &$pre_form_html ) {

		# Only use this hook on user pages
		if( empty( $targetTitle ) || $targetTitle->getNamespace() != NS_USER ) return;

		# Get the user account
		$user = User::newFromName( $targetTitle->getText() )->getId();

		if( $targetTitle->exists() xor $user ) {

			$pre_form_html = '<div class="warningbox">';
			if( $targetTitle->exists() ) $pre_form_html .= wfMessage( 'mgwiki-userpage-without-useraccount' )->escaped();
			else $pre_form_html .= wfMessage( 'mgwiki-useraccount-without-userpage' )->escaped();
			$pre_form_html .= "</div>\n";
		}

		return true;
	}

	public static function onPrefsEmailAudit( $user, $oldaddr, $newaddr ) {

		# Get the wiki page
		$title = Title::newFromText( $user->getName(), NS_USER );
		if( $title->getArticleID() == -1 ) return;
		$subject = SMW\DIWikiPage::newFromTitle( $title );

		# Get the properties on the page
		$store = SMW\StoreFactory::getStore();
		$semanticData = $store->getSemanticData( $subject );

		# Add the email to the data values and save
		$emailValue = new SMWDIBlob( $newaddr );
		$semanticData->addPropertyValue( self::emailField, $emailValue );
		$store->updateData( $semanticData );
	}

	/**
	 * When a user page is modified by SemanticMediaWiki, create the corresponding MediaWiki user or update the email
	 *
	 * Only the user or 'admins' with the right 'mgwikimanageusers' can report the email address in the user preferences.
	 * If $wgNewUserLog is true (default), add an entry in the 'newusers' log when a user is created.
	 * 
	 * @param SMWSQLStore3 $store SemanticMediaWiki store
	 * @param SMWSemanticData $semanticData Semantic data
	 * @param SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator Differences on property values
	 * @return true
	 */
	public static function onSMW_SQLStore_AfterDataUpdateComplete( SMWSQLStore3 $store, SMWSemanticData $semanticData, SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator ) {

		$result1 = self::onFormPersonne( $store, $semanticData, $compositePropertyTableDiffIterator );

		$result2 = self::onFormGEPouGAPP( $store, $semanticData, $compositePropertyTableDiffIterator );

		if( $result1 || $result2 ) return $result1 && $result2;
	}

	/**
	 * When a user page is modified by SemanticMediaWiki with Form:Personne, create the corresponding MediaWiki user or update the email
	 *
	 * Only the user or 'admins' with the right 'mgwikimanageusers' can report the email address in the user preferences.
	 * If $wgNewUserLog is true (default), add an entry in the 'newusers' log when a user is created.
	 * 
	 * @param SMWSQLStore3 $store SemanticMediaWiki store
	 * @param SMWSemanticData $semanticData Semantic data
	 * @param SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator Differences on property values
	 * @return true
	 */
	protected static function onFormPersonne( SMWSQLStore3 $store, SMWSemanticData $semanticData, SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator ) {

		global $wgUser;

		#$logger = MediaWiki\Logger\LoggerFactory::getInstance( "semantic_studies" );
		#$logger->error( 'aaaaaaaaa' );
		#ob_start();
		#var_dump($store);
		#var_dump($semanticData);
		#var_dump($compositePropertyTableDiffIterator);
		#$a = ob_get_clean();
		#$logger->error( $a );

		# Get user
		$subject = $semanticData->getSubject();
		if( $subject->getNamespace() != NS_USER ) return;
		$user = User::newFromName( $subject->getDBkey() );

		# Check permissions
		if( ($wgUser->getId() && $wgUser->getId() != $user->getId()) && !$wgUser->isAllowed( 'mgwikimanageusers' ) ) return;

		# Get properties after they are saved
		$properties = $semanticData->getProperties();
		if( count( $properties ) == 0 ) return;

		# Search if there is an email property
		$email = '';
		if( array_key_exists( self::emailField, $properties ) ) {
			$emailValues = $semanticData->getPropertyValues( $properties[self::emailField] );
			if( count( $emailValues ) == 0 || current( $emailValues )->getDIType() != SMWDataItem::TYPE_BLOB ) return;
			$email = current( $emailValues )->getString();
		}

		# If the user doesn’t exist, create it
		if( self::createUser( $subject->getDBkey(), [ self::emailField => $email ] ) );

		# Or just update the email
		elseif( $email ) {
			if( $wgUser->getEmail() == $email ) return;
			$wgUser->setEmail( $email );
			$wgUser->saveSettings();
		}

		return true;
	}

	/**
	 * When a page for GEP or GAPP is created or modified by SemanticMediaWiki with Form:GEP ou GAPP, create the corresponding MediaWiki users
	 *
	 * Only the user or 'admins' with the right 'mgwikimanageusers' can report the email address in the user preferences.
	 * If $wgNewUserLog is true (default), add an entry in the 'newusers' log when a user is created.
	 * 
	 * @param SMWSQLStore3 $store SemanticMediaWiki store
	 * @param SMWSemanticData $semanticData Semantic data
	 * @param SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator Differences on property values
	 * @return true
	 */
	protected static function onFormGEPouGAPP( SMWSQLStore3 $store, SMWSemanticData $semanticData, SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator ) {

		global $wgUser;

		# Get page
		$subject = $semanticData->getSubject();
		if( $subject->getNamespace() != NS_MAIN ) return;

		# Check permissions
		#if( !$wgUser->isAllowed( 'mgwikimanageusers' ) ) return;

		# Get property values
		$statements = self::collectSemanticData( [ self::typeDeGroupeField ], $semanticData, $complete );

		# Search if there is a property 'Participant GEP ou GAPP'
		if( !$semanticData->hasSubSemanticData() ) return;
		$subSemanticData = $semanticData->getSubSemanticData();
		$createdUsers = array();
		foreach( $subSemanticData as $user => $userSemanticData ) {

			$userData = self::collectSemanticData( self::fields, $userSemanticData, $complete );

			# Check if we have all mandatory values
			if( $complete ) {

				$userData[self::statutPersonneField] = $statements[self::typeDeGroupeField] == 'GEP' ? 'Interne' : 'Médecin';
				$username = $userData[self::prenomField].' '.$userData[self::nomField];
				self::createUser( $username, $userData );

				$createdUsers[] = $userData;
			}
		}
	}

	/**
	 * Collect the requested data.
	 *
	 * @param array $fields Field names
	 * @param SMW\SemanticData $semanticData Semantic data
	 * @param bool $complete Is set to true or false depending if all fields were found in the data
	 * @return array Requested data
	 */
	protected static function collectSemanticData( array $fields, SMW\SemanticData $semanticData, &$complete ) {

		# Init
		$userData = array();
		$count = 0;

		# Retrieve values
		$properties = $semanticData->getProperties();

		foreach( $properties as $key => $diProperty ) {
			$values = $semanticData->getPropertyValues( $diProperty );
			if( in_array( $diProperty->getKey(), $fields ) && count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_BLOB ) {
				$userData[$diProperty->getKey()] = current( $values )->getString();
				$count++;
			}
		}

		# Check if we have all mandatory values
		$complete = false;
		if( $count == count( $fields ) ) $complete = true;

		return $userData;
	}

	/**
	 * Create a user.
	 *
	 * @param string $username Username
	 * @param string|null $email E-mail
	 * @param array $groups Groups
	 * @return bool The user was created
	 */
	protected static function createUser( $username, $userData = [], array $groups = [] ) {

		global $wgUser, $wgNewUserLog;

		$user = User::newFromName( $username );
		if( $user->getId() )
			return false;

		$properties = [];
		if( array_key_exists( self::emailField, $userData ) && is_string( $userData[self::emailField] ) )
			$properties['email'] = $userData[self::emailField];
		
		# Create the user and add log entry
		if( version_compare( $wgVersion, '1.27.0' ) >= 0 ) {
			//$data = $properties; // Not to send the confirmation email through AuthManager since I want to customise it
			$data = [];
			$data['username'] = $username;
			$data['password'] = $password;
			$data['retype'] = $password;

			# This comes from AuthManagerAuthPlugin
			$reqs = AuthManager::singleton()->getAuthenticationRequests( AuthManager::ACTION_CREATE );
			$reqs = AuthenticationRequest::loadRequestsFromSubmission( $reqs, $data );
			$res = AuthManager::singleton()->beginAccountCreation( $wgUser, $reqs, 'null:' );
			switch ( $res->status ) {
				case AuthenticationResponse::PASS:
					return true;
				case AuthenticationResponse::FAIL:
					// Hope it's not a PreAuthenticationProvider that failed...
					$msg = $res->message instanceof \Message ? $res->message : new \Message( $res->message );
					$this->logger->info( __METHOD__ . ': Authentication failed: ' . $msg->plain() );
					return false;
				default:
					throw new \BadMethodCallException(
						'AuthManager does not support such simplified account creation'
					);
			}
			
		} else {
			$user = User::createNew( $username, $properties );
			if( !$user instanceof User )
				return false;
			if( $wgNewUserLog ) {
				$logEntry = new ManualLogEntry( 'newusers', 'create2' );
				$logEntry->setPerformer( $wgUser );
				$logEntry->setTarget( $user->getUserPage() );
				$logEntry->setParameters( array( '4::userid' => $user->getId() ) );
				$logid = $logEntry->insert();
				$logEntry->publish( $logid );
			}
		}

		# Add template on userpage
		$userTitle = Title::newFromText( $username, NS_USER );
		$userArticle = WikiPage::factory( $userTitle );
		$summary = wfMessage( 'mgwiki-create-userpage' )->inContentLanguage()->text();
		$content = new WikitextContent( wfMessage( 'mgwiki-template-new-userpage',
			$username, $userData[self::prenomField], $userData[self::nomField], $userData[self::emailField], $userData[self::statutPersonneField]
		)->inContentLanguage()->plain() );
		$flags = EDIT_NEW;
		$userArticle->doEditContent( $content, $summary, $flags, false, $wgUser );

		# Send email
		$user->sendConfirmationMail( 'created_by_mgwiki' );

		return true;
	}
}
