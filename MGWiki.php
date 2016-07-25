<?php
/**
 * MGWiki
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 */

class MGWiki {

	const nomField = 'Nom';
	const prenomField = 'Prénom';
	const emailField = 'E-mail';
	const typeDeGroupeField = 'Type_de_groupe';
	const statutPersonneField = 'Statut_personne';
	const statutAdditionnelPersonneField = 'Statut_additionnel_personne';
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
			if( array_key_exists( 'RegexPageName', $params ) && preg_match( $params['RegexPageName'], $titleEnglish ) && array_key_exists( 'RequiredRight', $params ) && is_string( $params['RequiredRight'] ) ) {
				if( !$user->isAllowed( $params['RequiredRight'] ) ) {
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
	 * Only the user or 'admins' with the right 'mgwikimanagelevel1' can report the email address in the user preferences.
	 * If $wgNewUserLog is true (default), add an entry in the 'newusers' log when a user is created.
	 * 
	 * @param SMWSQLStore3 $store SemanticMediaWiki store
	 * @param SMWSemanticData $semanticData Semantic data
	 * @param SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator Differences on property values
	 * @return true
	 */
	public static function onSMW_SQLStore_AfterDataUpdateComplete( SMWSQLStore3 $store, SMWSemanticData $semanticData, SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator ) {

		global $wgMGWikiForms;

		# Get title with namespace in English
		$title = $semanticData->getSubject()->getTitle();
		$titleEnglish = '';
		$ns = MWNamespace::getCanonicalName( $title->getNamespace() );
		if( $ns ) $titleEnglish .= $ns . ':';
		$titleEnglish .= $title->getText();

		# Get the user who made the change
		# It is executed as a job, so $wgUser is not the real user who made the change
		$statements = self::collectSemanticData( [ '_LEDT' ], $semanticData, $complete );
		if( !array_key_exists( '_LEDT', $statements ) || $statements['_LEDT']->getNamespace() != 2 )
			return;
		$editor = User::newFromName( $statements['_LEDT']->getText() );
		$editor->load();

		# Search the form
		foreach( $wgMGWikiForms as $form => $params ) {
			if( array_key_exists( 'RegexPageName', $params ) && preg_match( $params['RegexPageName'], $titleEnglish ) ) {

				self::synchroniseMediaWikiGroups( $title, $editor, $form, $params, $store, $semanticData, $compositePropertyTableDiffIterator );
				break;
			}
		}

		return true;
	}

	/**
	 * Synchronise the requested groups from the semantic form with MediaWiki groups.
	 *
	 * @param Title $title Title of the subject page.
	 * @param User $editor Last editor of the page.
	 * @param string $form Name of this form.
	 * @param array $paramsForm Parameters for this form type.
	 * @param SMWSQLStore3 $store SemanticMediaWiki store.
	 * @param SMWSemanticData $semanticData Semantic data.
	 * @param SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator Differences on property values.
	 * @return true
	 */
	private static function synchroniseMediaWikiGroups( Title $title, User $editor, $form, array $paramsForm, $store, $semanticData, $compositePropertyTableDiffIterator ) {

		global $wgMGWikiFielsGroups, $wgMGWikiUserProperties;

		# Default groups to be added
		$groups = [];
		$editOwnUserpage = false;
		$complete = null;

		# Check if the user edits her/his own userpage
		if( $title->getNamespace() == NS_USER ) {
			$user = User::newFromName( $title->getDBkey() );
			$user->load();
			if( $editor->isLoggedIn() && $editor->getId() == $user->getId() )
				$editOwnUserpage = array_key_exists( 'EditOwnUserpage', $paramsForm ) && $paramsForm['EditOwnUserpage'];
		}

		# Check permissions
		if( !$editor->isAllowed( $paramsForm['RequiredRight'] ) && !$editOwnUserpage )
			return;

		# Iterate over the fields groups
		$defaultGroups = self::searchFieldsGroups( $title, $editor, $semanticData, $editOwnUserpage );

		# Iterate over the subobjects
		if( array_key_exists( 'SubObjects', $paramsForm ) && $paramsForm['SubObjects'] ) {
			if( $semanticData->hasSubSemanticData() ) {
				$subSemanticData = $semanticData->getSubSemanticData();
				$createdUsers = array();
				foreach( $subSemanticData as $user => $userSemanticData ) {

					# Create users
					$userData = self::collectSemanticData( self::fields, $userSemanticData, $complete );
					if( $complete ) {
						$userData[self::statutPersonneField] = $statements[self::typeDeGroupeField] == 'GEP' ? 'Interne' : 'Médecin';
						$username = $userData[self::prenomField].' '.$userData[self::nomField];
						self::createUser( $username, $userData );
						$createdUsers[] = $userData;

						# User groups
						$groups = array_merge( $defaultGroups, self::searchFieldsGroups( false, $editor, $userSemanticData, $editOwnUserpage ) );
						self::addMediaWikiGroups( $username, $groups, $editOwnUserpage );
					}
				}
			}
		}

		# Standalone form (Personne)
		elseif( $title->getNamespace() == NS_USER ) {
			# Search if there is an email property
			$email = '';
			$statements = self::collectSemanticData( [ $wgMGWikiUserProperties['email'] ], $semanticData, $complete );
			if( array_key_exists( $wgMGWikiUserProperties['email'], $statements ) )
				$email = $statements[$wgMGWikiUserProperties['email']];

			# If the user doesn’t exist, create it
			if( self::createUser( $title->getText(), [ $wgMGWikiUserProperties['email'] => $email ] ) );

			# Or just update the email
			elseif( $email && $user->getEmail() != $email ) {
				$user->setEmail( $email );
				$user->saveSettings();
			}

			# And update the user groups
			self::addMediaWikiGroups( $user, $defaultGroups, $editOwnUserpage );
		}
	}

	private static function searchFieldsGroups( $title, $editor, $semanticData, $editOwnUserpage ) {

		global $wgMGWikiFieldsGroups;

		$groups = [];
		$complete = null;

		foreach( $wgMGWikiFieldsGroups as $property => $paramsProperty ) {

			# Get data
			$statements = self::collectSemanticData( [ $property ], $semanticData, $complete );

			# Check permissions
			$canEditOwnUserpage = array_key_exists( 'EditOwnUserpage', $paramsProperty ) && $paramsProperty['EditOwnUserpage'];
			if( !$editor->isAllowed( $paramsProperty['RequiredRight'] ) && !($editOwnUserpage && $canEditOwnUserpage) )
				continue;

			# Get the group to be added
			if( array_key_exists( $property, $statements ) )
				$groups[$property] = $paramsProperty['MapFromProperty'][$statements[$property]];
			elseif( $title && array_key_exists( 'MapFromTitle', $paramsProperty ) ) {
				foreach( $paramsProperty['MapFromTitle'] as $regex => $group ) {
					if( preg_match( $regex, $title->getText() ) )
						$groups[$property] = $group;
				}
			}
			if( !array_key_exists( $property, $groups ) && in_array( '', $paramsProperty['Groups'] ) )
				$groups[$property] = '';
		}

		return $groups;
	}

	private static function addMediaWikiGroups( $user, $groups, $editOwnUserpage ) {

		global $wgMGWikiFieldsGroups;

		if( is_string( $user ) )
			$user = User::newFromName( $user );

		foreach( $groups as $property => $valueProperty ) {

			# Collect currently subscribed groups
			$uniqueGroup = null;
			$effectiveGroups = [];
			foreach( $wgMGWikiFieldsGroups[$property]['Groups'] as $g ) {
				$effectiveGroupe[$g] = false;
				if( $g && in_array( $g, $user->getGroups() ) ) {
					$effectiveGroups[$g] = true;
					if( $uniqueGroup === null ) $uniqueGroup = $g;
					else $uniqueGroup = false;
				}
			}
			if( in_array( '', $wgMGWikiFieldsGroups[$property]['Groups'] ) && $uniqueGroup === null ) {
				$effectiveGroups[''] = true;
				$uniqueGroup = '';
			}

			# Is it what we want? If so, continue
			echo ( $uniqueGroup === $valueProperty );
			if( $uniqueGroup === $valueProperty )
				continue;

			# Else remove the user from the groups	
			$removedGroups = [];
			foreach( $effectiveGroups as $g => $v ) {
				if( $g && $v ) {
					$user->removeGroup( $g );
					$removedGroups[] = $g;
				}
			}

			# If a group is wanted, add it
			if( !$valueProperty )
				continue;
			$user->addGroup( $valueProperty );
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

		# Normalise keys
		$mapNormalisation = [];
		foreach( $fields as $field )
			$mapNormalisation[str_replace( ' ', '_', $field )] = $field;

		# Iterate over existing properties and search requested properties
		foreach( $properties as $key => $diProperty ) {
			$values = $semanticData->getPropertyValues( $diProperty );
			if( !in_array( $diProperty->getKey(), array_keys( $mapNormalisation ) ) )
				continue;
			if( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_BLOB ) {
				$userData[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getString();
				$count++;
			}
			elseif( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_WIKIPAGE ) {
				$userData[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getTitle();
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
		global $wgMGWikiUserProperties;

		$user = User::newFromName( $username );
		if( $user->getId() )
			return false;

		foreach( $wgMGWikiUserProperties as $k => $v )
			$wgMGWikiUserProperties[$k] = str_replace( ' ', '_', $v );

		$properties = [];
		if( array_key_exists( $wgMGWikiUserProperties['email'], $userData ) && is_string( $userData[$wgMGWikiUserProperties['email']] ) )
			$properties['email'] = $userData[$wgMGWikiUserProperties['email']];
		
		# Create the user and add log entry
		if( version_compare( $wgVersion, '1.27.0' ) >= 0 ) {
			//$data = $properties; // Not to send the confirmation email through AuthManager since I want to customise it
			$data = [];
			$data['username'] = $username;
			$data['password'] = '';
			$data['retype'] = '';

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
			$username, $userData[$wgMGWikiUserProperties['firstname']], $userData[$wgMGWikiUserProperties['lastname']], $userData[$wgMGWikiUserProperties['email']], $userData[$wgMGWikiUserProperties['statutPersonne']], $userData[$wgMGWikiUserProperties['statutAdditionnelPersonne']]
		)->inContentLanguage()->plain() );
		$flags = EDIT_NEW;
		$userArticle->doEditContent( $content, $summary, $flags, false, $wgUser );

		# Send email
		$user->sendConfirmationMail( 'created_by_mgwiki' );

		return true;
	}
}
