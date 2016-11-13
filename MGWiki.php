<?php
/**
 * MGWiki
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 */

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;

class MGWiki {

	/**
	 * 
	 */
	public static function onParserFirstCallInit( $parser ) {

		$parser->setFunctionHook( 'isusersysop', 'MGWiki::pfuncIsUserSysop', Parser::SFH_OBJECT_ARGS );
	}

	/**
	 * Parser function returning 1 if the given user is a sysop, else an empty string.
	 *
	 * In case of error, returns an HTML red warning, which can be catch by #iferror from ParserFunctions.
	 *
	 * @param $parser Parser
	 * @param $frame PPFrame
	 * @param $args array
	 * @return string
	 */
	public static function pfuncIsUserSysop( $parser, $frame, $args ) {
		$username = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
		if( !$username ) {
			return '<strong class="error">' . wfMessage( 'mgwiki-isusersysop-nousername' )->text() . '</strong>';
		}
		if( User::isIP( $username ) ) {
			return '0';
		}
		$user = User::newFromName( $username );
		if( !$user ) {
			return '<strong class="error">' . wfMessage( 'mgwiki-isusersysop-badusername' )->text() . '</strong>';
		}
		if( in_array( 'sysop', $user->getGroups() ) ) {
			return '1';
		}
		return '0';
	}

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
		if ( $action != 'edit' ) {
			return true;
		}

		# Check permissions for forms
		$titleEnglish = '';
		$ns = MWNamespace::getCanonicalName( $title->getNamespace() );
		if ( $ns ) $titleEnglish .= $ns . ':';
		$titleEnglish .= $title->getText();
		foreach ( $wgMGWikiForms as $form => $params ) {
			# For each registered form type is associated a resulting page name, check the permissions on this page, not the form itself
			if ( array_key_exists( 'RegexPageName', $params ) && preg_match( $params['RegexPageName'], $titleEnglish ) ) {
				if ( $title->getNamespace() == NS_USER && $title->getText() == $user->getName() && array_key_exists( 'EditOwnUserpage', $params ) && $params['EditOwnUserpage'] === true ) {
					return true;
				}
				if ( array_key_exists( 'RequiredRight', $params ) && is_string( $params['RequiredRight'] && !$user->isAllowed( $params['RequiredRight'] ) ) ) {
					# Unauthorised user, and all further permissions hooks must be skipped since this result is authoritative
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
		if ( empty( $targetTitle ) || $targetTitle->getNamespace() != NS_USER ) return;

		# Get the user account
		$user = User::newFromName( $targetTitle->getText() )->getId();

		if ( $targetTitle->exists() xor $user ) {

			$pre_form_html = '<div class="warningbox">';
			if ( $targetTitle->exists() ) $pre_form_html .= wfMessage( 'mgwiki-userpage-without-useraccount' )->escaped();
			else $pre_form_html .= wfMessage( 'mgwiki-useraccount-without-userpage' )->escaped();
			$pre_form_html .= "</div>\n";
		}

		return true;
	}

	public static function onPrefsEmailAudit( $user, $oldaddr, $newaddr ) {

		global $wgMGWikiUserProperties;

		# Normalise value
		$emailProperty = $wgMGWikiUserProperties['email'];

		# Get the wiki page
		$title = Title::newFromText( $user->getName(), NS_USER );
		if ( $title->getArticleID() == -1 ) return;
		$article = WikiPage::factory( $title );
		$summary = wfMessage( 'mgwiki-changed-email' )->inContentLanguage()->text();

		# Get the content
		$oldContent = $article->getContent();
		if( $oldContent->getModel() != CONTENT_MODEL_WIKITEXT ) {
			return;
		}

		# Update the content
		$content = new WikitextContent( preg_replace( '/\| *'.preg_quote($emailProperty,'/').' *=[a-zA-Z0-9@._+-]+/', "|$emailProperty = $newaddr\n", $oldContent->getNativeData() ) );

		# And edit
		$flags = EDIT_MINOR | EDIT_SUPPRESS_RC | EDIT_UPDATE;
		$status = $article->doEditContent( $content, $summary, $flags, false, $wgUser );
		if( !$status->isOK() ) {
			return;
		}
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
	 * @return bool|void
	 */
	public static function onSMW_SQLStore_AfterDataUpdateComplete( SMWSQLStore3 $store, SMWSemanticData $semanticData, SMW\SQLStore\CompositePropertyTableDiffIterator $compositePropertyTableDiffIterator ) {

		global $wgMGWikiForms;

		# Get title with namespace in English
		$title = $semanticData->getSubject()->getTitle();
		$titleEnglish = '';
		$ns = MWNamespace::getCanonicalName( $title->getNamespace() );
		if ( $ns ) $titleEnglish .= $ns . ':';
		$titleEnglish .= $title->getText();

		# Get the user who made the change
		# It is executed as a job, so $wgUser is not the real user who made the change
		$statements = self::collectSemanticData( [ '_LEDT' ], $semanticData, $complete );
		if ( !array_key_exists( '_LEDT', $statements ) || !$statements['_LEDT'] instanceof Title || $statements['_LEDT']->getNamespace() != 2 )
			return;
		$editor = User::newFromName( $statements['_LEDT']->getText() );
		if( !$editor ) {
			return false;
		}
		$editor->load();

		# Search the form
		foreach ( $wgMGWikiForms as $form => $params ) {
			if ( array_key_exists( 'RegexPageName', $params ) && preg_match( $params['RegexPageName'], $titleEnglish ) ) {

				self::synchroniseMediaWikiGroups( $title, $editor, $form, $params, $store, $semanticData, $compositePropertyTableDiffIterator );

				# Delete the page if requested
				if ( array_key_exists( 'EphemeralPage', $params ) && $params['EphemeralPage'] ) {
					$page = WikiPage::factory( $title );
					$deleteStatus = $page->doDeleteArticleReal( wfMessage( 'mgwiki-ephemeral-page-deleted' )->inContentLanguage()->text() );
				}

				break;
			}
		}

		return true;
	}

	/**
	 * Update the property on the userpage “last-date-edited-by-user-her/himself”
	 * 
	 * @param WikiPage $wikiPage The WikiPage (object) being saved.
         * @param User $user The User (object) saving the article.
         * @param Content $content The new article content, as a Content object.
         * @param string $summary The article summary (comment).
         * @param integer $isMinor Minor flag.
         * @param null $isWatch Watch flag (not used, aka always null).
         * @param null $section Section number (not used, aka always null).
         * @param integer $flags See WikiPage::doEditContent documentation for flags' definition.
         * @param Status $status Status (object).
	 */
	public static function onPageContentSave( &$wikiPage, &$user, &$content, &$summary, $isMinor, $isWatch, $section, &$flags, &$status ) {

		global $wgMGWikiUserProperties;

		# If not the userpage, not in the scope of this hook
		if( !$user->getUserPage()->equals( $wikiPage->getTitle() ) ) {
			return true;
		}

		# Always update the “last-date-edited-by-user-her/himself”
		if( preg_match( "/(^|\r?\n) *\| *" . preg_quote( $wgMGWikiUserProperties['timestamp'], '/' ) . " *=.*(\r?\n|$)/", $content->getNativeData() ) ) {
			$content = new WikitextContent( preg_replace( "/(^|\r?\n) *\| *" . preg_quote( $wgMGWikiUserProperties['timestamp'], '/' ) . " *=.*(\r?\n|$)/",
			              '$1|' . $wgMGWikiUserProperties['timestamp'] . '=' . wfTimestamp() . '$2',
			              $content->getNativeData() ) );
		} else {
			$content = new WikitextContent( preg_replace( "/((^|\r?\n) *\| *" . preg_quote( $wgMGWikiUserProperties['email'], '/' ) . " *=.*)(\r?\n|$)/",
			              "\$1\n|" . $wgMGWikiUserProperties['timestamp'] . '=' . wfTimestamp() . '$3',
			              $content->getNativeData() ) );
		}
	}

	/**
	 * Redirect the user just after login if her/his semantic property says
	 * s/he should update her/his informations.
	 */
	static function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {

		global $wgUser;
		global $wgMGWikiUserProperties;

		$store = &smwfGetStore();
		$complete = null;

		$update = self::collectSemanticData( [ $wgMGWikiUserProperties['requiredUserUpdate'] ], $store->getSemanticData( SMW\DIWikiPage::newFromTitle( $wgUser->getUserPage() ) ), $complete );

		if( count( $update ) == 1 && $update[$wgMGWikiUserProperties['requiredUserUpdate']] ) {
			$returnTo = $wgUser->getUserPage()->getPrefixedText();
			$returnToQuery = [ 'action' => 'formedit' ];
			$type = 'successredirect';
		}

		return true;
	}

	/**
	 * 
	 *
	 * @param SpecialPage $specialPage
	 * @param string|null $subpage
	 */
	static function onSpecialPageAfterExecute( $specialPage, $subpage ) {

		global $wgUser, $wgOut;

		# After the user has changed her/his password, send her/him to her/his userpage in form-edition to confirm her/his data
		if( $specialPage->getName() == 'ChangeCredentials' && $specialPage->getRequest()->wasPosted() ) {

			$wgOut->redirect( $wgUser->getUserPage()->getFullURL( [ 'action' => 'formedit' ] ) );
			$wgOut->output();
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

		global $wgMGWikiFieldsGroups, $wgMGWikiUserProperties, $wgMGWikiGroups;
		global $wgUser;

		# Default groups to be added
		$groups = [];
		$editOwnUserpage = false;
		$complete = null;

		# Check if the user edits her/his own userpage
		if ( $title->getNamespace() == NS_USER ) {
			$user = User::newFromName( $title->getDBkey() );
			if( $user instanceof User ) {
				$user->load();
				if ( $editor->isLoggedIn() && $editor->getId() == $user->getId() )
					$editOwnUserpage = array_key_exists( 'EditOwnUserpage', $paramsForm ) && $paramsForm['EditOwnUserpage'];
			}
		}

		# Check permissions
		if ( !$editor->isAllowed( $paramsForm['RequiredRight'] ) && !$editOwnUserpage )
			return;

		# Iterate over the fields groups
		$defaultGroups = self::searchFieldsGroups( $title, $editor, $semanticData, $editOwnUserpage );

		# Get moderator’s institution if defined
		$institution = [];
		if( array_key_exists( 'InstitutionFromModerator', $paramsForm ) && $paramsForm['InstitutionFromModerator'] ) {
			$moderator = self::collectSemanticData( [ $wgMGWikiUserProperties['moderator'] ], $semanticData, $complete );
			if( count( $moderator ) == 1 ) {
				$institution = self::collectSemanticData( [ $wgMGWikiUserProperties['institution'] ],
				                                          $store->getSemanticData( SMW\DIWikiPage::newFromTitle( $moderator[$wgMGWikiUserProperties['moderator']] ) ),
			        	                                  $complete );
				if( count( $institution ) != 1 ) {
					$institution = [];
				}
				$institution[$wgMGWikiUserProperties['referrer']] = $moderator[$wgMGWikiUserProperties['moderator']]->getText();
			}
		}
		elseif( array_key_exists( 'InstitutionFromCreator', $paramsForm ) && $paramsForm['InstitutionFromCreator'] ) {
			$creator = self::collectSemanticData( [ '_LEDT' ], $semanticData, $complete );
			if( count( $creator ) == 1 ) {
				$institution = self::collectSemanticData( [ $wgMGWikiUserProperties['institution'] ],
				                                          $store->getSemanticData( SMW\DIWikiPage::newFromTitle( $creator['_LEDT'] ) ),
			        	                                  $complete );
				if( count( $institution ) != 1 ) {
					$institution = [];
				}
				$institution[$wgMGWikiUserProperties['referrer']] = $creator['_LEDT']->getText();
			}
		}

		# Iterate over the subobjects
		if ( array_key_exists( 'SubObjects', $paramsForm ) && $paramsForm['SubObjects'] ) {
			$content = '';
			$templates = [];
			if ( $semanticData->hasSubSemanticData() ) {
				$subSemanticData = $semanticData->getSubSemanticData();
				$createdUsers = [];
				if ( array_key_exists( 'MergeNewUsers', $paramsForm ) && is_array( $paramsForm['MergeNewUsers'] ) ) {
					$templates = $paramsForm['MergeNewUsers'];
					$article = WikiPage::factory( $title );
					# Get the content
					$contentObject = $article->getContent();
					if( $contentObject->getModel() == CONTENT_MODEL_WIKITEXT ) {
						$content = $contentObject->getNativeData();
					}
				}
				foreach ( $subSemanticData as $user => $userSemanticData ) {

					# Create users
					$propertiesToBeSearched = array_values( $wgMGWikiUserProperties );
					$userData = self::collectSemanticData( $propertiesToBeSearched, $userSemanticData, $complete );
					$userData = array_merge( $institution, $userData );
					if ( array_key_exists( $wgMGWikiUserProperties['firstname'], $userData ) && array_key_exists( $wgMGWikiUserProperties['lastname'], $userData ) ) {
						# Iterate over the fields groups
						$userGroups = self::searchFieldsGroups( false, $editor, $userSemanticData, $editOwnUserpage );
						$groups = array_merge( $defaultGroups, $userGroups );
						#echo "userData = ";var_dump($userData);
						#echo "defaultGroups = ";var_dump($defaultGroups);
						#echo "userGroups = ";var_dump($userGroups);

						# If there is a person status, override add it
						#foreach ( $wgMGWikiFieldsGroups as $k => $v ) {
						#	if ( array_key_exists( $k, $groups ) ) {
						#		$userData[$k] = array_flip( $v['MapFromProperty'] )[$groups[$k]];
						#	}
						#}
						#echo "userData (bis) = ";var_dump($userData);

						$username = $userData[$wgMGWikiUserProperties['firstname']].' '.$userData[$wgMGWikiUserProperties['lastname']];
						self::createUser( $username, $userData );
						$createdUsers[] = $userData;

						# User groups
						self::addMediaWikiGroups( $username, $groups, $editOwnUserpage );

						# Replace templates with lists
						if( $content ) {
							foreach( $templates as $template => $list ) {
								$content = preg_replace( '/\{\{ *' . $wgMGWikiUserProperties[$template] . "[ \|\n].*?\}\}\n?/s", '', $content );
								if( !preg_match( '/\| *' . $wgMGWikiUserProperties[$list] . " *=(.*?) *([\|\n])/", $content ) ) {
									$content = preg_replace( '/\| *' . $wgMGWikiUserProperties['moderator'] . " *=(?:.*?) *\n/", "$0|" . $wgMGWikiUserProperties[$list] . ' = ' . $username . "\n", $content );
								} else {
									$content = preg_replace( '/\| *' . $wgMGWikiUserProperties[$list] . " *= *(.*?) *([\|\n])/", '|' . $wgMGWikiUserProperties[$list] . ' = $1, ' . $username . '$2', $content );
								}
							}
						}
					}
				}
			}

			# Save
			if ( $content && count( $template ) ) {

				# Update the content
				$contentObject = new WikitextContent( $content );

				# And edit
				$flags = EDIT_MINOR | EDIT_UPDATE;
				$summary = wfMessage( 'mgwiki-summary-rewrite-grouppage' )->inContentLanguage()->text();
				$status = $article->doEditContent( $contentObject, $summary, $flags, false, $wgUser );
				if( !$status->isOK() ) {
					# Error
				}
			}
		}

		# Standalone form (Personne)
		elseif ( $title->getNamespace() == NS_USER ) {
			# Search if there is an email property
			$email = '';
			$statements = self::collectSemanticData( [ $wgMGWikiUserProperties['email'] ], $semanticData, $complete );
			if ( array_key_exists( $wgMGWikiUserProperties['email'], $statements ) )
				$email = $statements[$wgMGWikiUserProperties['email']];

			# If the user doesn’t exist, create it
			if ( self::createUser( $title->getText(), [ $wgMGWikiUserProperties['email'] => $email ] ) );

			# Or just update the email
			elseif ( $email && $user->getEmail() != $email ) {
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
			#echo "\$statements ($property) = ";var_dump($statements);

			# Check permissions
			$canEditOwnUserpage = array_key_exists( 'EditOwnUserpage', $paramsProperty ) && $paramsProperty['EditOwnUserpage'];
			if ( !$editor->isAllowed( $paramsProperty['RequiredRight'] ) && !($editOwnUserpage && $canEditOwnUserpage) )
				continue;

			# Get the group to be added
			if ( array_key_exists( $property, $statements ) )
				$groups[$property] = $paramsProperty['MapFromProperty'][$statements[$property]];
			elseif ( $title && array_key_exists( 'MapFromTitle', $paramsProperty ) ) {
				#echo "Title = ".$title->getText()." MapFromTitle ($property) =";var_dump($paramsProperty['MapFromTitle']);
				foreach( $paramsProperty['MapFromTitle'] as $regex => $group ) {
					if ( preg_match( $regex, $title->getText() ) )
						$groups[$property] = $group;
				}
			}
			if ( !array_key_exists( $property, $groups ) && in_array( '', $paramsProperty['Groups'] ) )
				$groups[$property] = '';
			#echo "\$groups ($property) = ";var_dump($groups);
		}

		return $groups;
	}

	private static function addMediaWikiGroups( $user, $groups, $editOwnUserpage ) {

		global $wgMGWikiFieldsGroups;

		if ( is_string( $user ) )
			$user = User::newFromName( $user );

		foreach( $groups as $property => $valueProperty ) {

			# Check permissions
			if ( !$user->isAllowed( $wgMGWikiFieldsGroups[$property]['RequiredRight'] ) && !( array_key_exists( 'EditOwnUserpage', $wgMGWikiFieldsGroups[$property] ) && $wgMGWikiFieldsGroups[$property]['EditOwnUserpage'] === true && $editOwnUserpage ) ) {
				continue;
			}

			# Collect currently subscribed groups
			$uniqueGroup = null;
			$effectiveGroups = [];
			foreach( $wgMGWikiFieldsGroups[$property]['Groups'] as $g ) {
				$effectiveGroupe[$g] = false;
				if ( $g && in_array( $g, $user->getGroups() ) ) {
					$effectiveGroups[$g] = true;
					if ( $uniqueGroup === null ) $uniqueGroup = $g;
					else $uniqueGroup = false;
				}
			}
			if ( in_array( '', $wgMGWikiFieldsGroups[$property]['Groups'] ) && $uniqueGroup === null ) {
				$effectiveGroups[''] = true;
				$uniqueGroup = '';
			}

			# Is it what we want? If so, continue
			if ( $uniqueGroup === $valueProperty ) {
				continue;
			}

			# Else remove the user from the groups	
			$removedGroups = [];
			foreach( $effectiveGroups as $g => $v ) {
				if ( $g && $v ) {
					$user->removeGroup( $g );
					$removedGroups[] = $g;
				}
			}

			# If a group is wanted, add it
			if ( !$valueProperty )
				continue;
			$user->addGroup( $valueProperty );
		}
	}

	/**
	 * Collect the requested data.
	 *
	 * @param string[] $fields Field names
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
		#echo "mapNormalisation = ";var_dump($mapNormalisation);

		# Iterate over existing properties and search requested properties
		foreach( $properties as $key => $diProperty ) {
			$values = $semanticData->getPropertyValues( $diProperty );
			#echo "property ".$diProperty->getKey()." found with ".count( $values )." values and type ".current( $values )->getDIType()."\n";
			if ( !in_array( $diProperty->getKey(), array_keys( $mapNormalisation ) ) )
				continue;
			#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") found with ".count( $values )." values and type ".current( $values )->getDIType()."\n";
			if ( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_BLOB ) {
				#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") = ".current( $values )->getString()."\n";
				$userData[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getString();
				$count++;
			}
			elseif ( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_WIKIPAGE ) {
				#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") = ".current( $values )->getTitle()."\n";
				$userData[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getTitle();
				$count++;
			}
			elseif ( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_BOOLEAN ) {
				#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") = ".(current( $values )->getBoolean()?'true':'false')."\n";
				$userData[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getBoolean();
				$count++;
			}
		}

		# Check if we have all mandatory values
		$complete = false;
		if ( $count == count( $fields ) ) $complete = true;

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

		global $wgUser, $wgNewUserLog, $wgVersion;
		global $wgMGWikiUserProperties;

		$username = User::getCanonicalName( $username, 'creatable' );
		$user = User::newFromName( $username );
		if ( $user->getId() )
			return false;

		$properties = [];
		if ( array_key_exists( $wgMGWikiUserProperties['email'], $userData ) && is_string( $userData[$wgMGWikiUserProperties['email']] ) )
			$properties['email'] = $userData[$wgMGWikiUserProperties['email']];
		
		# Create the user and add log entry
		if ( false && version_compare( $wgVersion, '1.27.0' ) >= 0 ) {
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
					return false;
				default:
					throw new \BadMethodCallException(
						'AuthManager does not support such simplified account creation'
					);
			}
			
		} else {
			$user = User::createNew( $username, $properties );
			if ( !$user instanceof User )
				return false;
			if ( $wgNewUserLog ) {
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
		$email = array_key_exists( $wgMGWikiUserProperties['email'], $userData ) ? $userData[$wgMGWikiUserProperties['email']] : '';
		$statutPers = array_key_exists( $wgMGWikiUserProperties['statutPersonne'], $userData ) ? $userData[$wgMGWikiUserProperties['statutPersonne']] : '';
		$statutAddPers = array_key_exists( $wgMGWikiUserProperties['statutAdditionnelPersonne'], $userData ) ? $userData[$wgMGWikiUserProperties['statutAdditionnelPersonne']] : '';
		$institution = array_key_exists( $wgMGWikiUserProperties['institution'], $userData ) ? $userData[$wgMGWikiUserProperties['institution']]->getPrefixedText() : '';
		$referrer = array_key_exists( $wgMGWikiUserProperties['referrer'], $userData ) ? $userData[$wgMGWikiUserProperties['referrer']] : '';
		$content = new WikitextContent( wfMessage( 'mgwiki-template-new-userpage',
			$username, $userData[$wgMGWikiUserProperties['firstname']], $userData[$wgMGWikiUserProperties['lastname']],
			$email, $statutPers, $statutAddPers, $institution, $referrer
		)->inContentLanguage()->plain() );
		$flags = EDIT_NEW;
		$userArticle->doEditContent( $content, $summary, $flags, false, $wgUser );

		# Send email
		$user->sendConfirmationMail( 'created_by_mgwiki' );

		return true;
	}
}
