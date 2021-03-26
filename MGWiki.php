<?php
/**
 * MGWiki - general functions related to user management
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWiki
 */

declare(strict_types=1);

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;

use MediaWiki\Extension\MGWiki\Utilities\MgwFunctions as MgwF;
use MediaWiki\Extension\MGWiki\Utilities\MailFunctions as MailF;
use MediaWiki\Extension\MGWiki\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWiki\Foreign\MGWRenameuser as Renameuser;
use MediaWiki\Extension\MGWiki\Foreign\MGWSemanticMediaWiki as SmwF;

class MGWiki {

	/**
	 * callback au chargement de l'extension (ne peut être placé sous un namespace)
	 */
	public static function onExtensionLoad() {
		/**
		 * accesseur pour les variables de configuration
		 * => config/<conf>.json
		 * @param $conf
		 * @param $item
		 * @return array|null
		 */
		function wfMgwConfig( string $conf, string $item = "" ) {
			$return = file_get_contents( __DIR__ . '/config/' . $conf . ".json" );
			$return = json_decode( $return, true );
			if ( empty( $item ) ) return $return;
			else return $return[ $item ];
		}
	}

	/**
	 * callback au chargement de php maintenance/update.php
	 *
	 * @param DatabaseUpdater|MysqlUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {

		/* tables additionnelles pour l'extension MGWiki.
		 * mgw_tasks = table de tâches en cours (permet de reprendre à distance une tâche non finie)
		 * mgw_stats = table destinée à l'observation de l'usage du site
		 */

		$dir = str_replace('includes', 'sql', __DIR__);
		$tables = wfMgwConfig('db-tables');

		foreach( $tables as $table ) {
			$table_file = "$dir/addTable-" . $table . ".sql";
			$index_file = "$dir/addIndex-" . $table . "_lookup.sql";
			$updater->addExtensionTable( $table, $table_file );
			if ( file_exists( $index_file ) ) {
				$updater->addExtensionIndex( $table, $table . '_lookup', $index_file );
			}
		}
	}

	/**
	 * TODO: harmoniser la gestion des droits sur les groupes U0 / U1 / U2 / U3
	 *
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
			if ( array_key_exists( 'RegexPageName', $params )
					 && preg_match( $params['RegexPageName'], $titleEnglish ) ) {

				if ( $title->getNamespace() == NS_USER
					   && $title->getText() == $user->getName()
						 && array_key_exists( 'EditOwnUserpage', $params )
						 && $params['EditOwnUserpage'] === true ) {
					return true;
				}

				if ( array_key_exists( 'RequiredRight', $params )
					   && is_string( $params['RequiredRight'] )
					   && !$user->isAllowed( $params['RequiredRight'] ) ) {
					# Unauthorised user, and all further permissions hooks must be skipped since this result is authoritative
					$result = false;
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * NB: FONCTIONS TEMPORAIRES ( MGW-0.2 debug )
	 * 1/ modif pages UTILISATEUR
	 * 2/ modif pages GROUPE
	 *
	 * TODO:
	 * 1/ màj MGW 2.0: intégration de la gestion des utilisateurs & groupes détachée des modèles inclus
	 * 2/ màj MW > 1.35: transfert des fonctions persistantes vers Hooks::onMultiContentSave()
	 * modif pages GROUPE: création d'utilisateurs
	 */
	public static function onPageContentSaveComplete( $wikiPage, $user, $content,
		$summary, $isMinor, $isWatch, $section, $flags, $revision, $status,
		$originalRevId, $undidRevId
	) {

		$hookSummary = 'MGWiki::onPageContentSaveComplete';

		////////////////////
		// PAGES UTILISATEUR

		if ( $wikiPage->getTitle()->getNamespace() == NS_USER && $summary != $hookSummary ) {
			$changes = false;
			$native_content = $content->getNativeData();

			# If self userpage, update the “last-date-edited-by-user-her/himself”
			if ( $wikiPage->getTitle()->getText() == $user->getName() ) {
				global $wgMGWikiUserProperties;

				if ( preg_match(
						"/(^|\r?\n) *\| *" . preg_quote( $wgMGWikiUserProperties['timestamp'], '/' ) . " *=.*(\r?\n|$)/",
						$native_content )
				) {
					$native_content = preg_replace(
						"/(^|\r?\n) *\| *" . preg_quote( $wgMGWikiUserProperties['timestamp'], '/' ) . " *=.*(\r?\n|$)/",
						'$1|' . $wgMGWikiUserProperties['timestamp'] . '=' . wfTimestamp() . '$2',
						$native_content );
				} else {
					$native_content = preg_replace(
						"/((^|\r?\n) *\| *" . preg_quote( $wgMGWikiUserProperties['lastname'], '/' ) . " *=.*)(\r?\n|$)/",
						"\$1\n|" . $wgMGWikiUserProperties['timestamp'] . '=' . wfTimestamp() . '$3',
						$native_content );
				}
				$changes = true;
			}

			# TEMPORAIRE: on efface le champs E-mail du formulaire Personne s'il existe
			# raison: ménage poco à poco sans déclencher une modif de toutes les pages utilisateur
			# par un administrateur...
			if ( preg_match('/E\-mail/', $native_content ) ) {
				$native_content = PageF::deleteTemplateFields ( $native_content, 'Personne', [ 'E-mail' ] );
				$changes = true;
			}

			if ( $changes ) {
				$status = PageF::edit( $wikiPage, $hookSummary, $user, $native_content );
			}
		}

		////////////////////////////////////////////////////////////
		// CREATION DE NOUVEAUX UTILISATEURS DEPUIS LES PAGES GROUPE

		if ( $wikiPage->getTitle()->getNamespace() == NS_GROUP && $summary != $hookSummary ) {
			# préparation des variables
			$create = false;
			$newUsers = [];
			$bugs = [];
			$redirectURL = '';
			$native_content = $content->getNativeData();

			# données sur le groupe
			$tplGroupe = PageF::getTemplateInfos ( $native_content, 'Groupe', [
				'Type de groupe' => 'Type de groupe',
				'Institution de rattachement' => 'Institution de rattachement',
				'Tuteur ou modérateur' => 'Tuteur ou modérateur',
				'Année' => 'Année',
				'Statut personne' => 'Statut personne'
			] );
			# nouveaux participants
			$nouveauxParticipants = PageF::getTemplateInfos ( $native_content, 'Participant Groupe', [
				'Nom' => 'Nom',
				'Prénom' => 'Prénom',
				'E-mail' => 'E-mail',
				'Statut personne' => 'Statut personne',
				'Statut additionnel personne' => 'Statut additionnel personne'
			] );

			# debug
			if ( count( $tplGroupe ) != 1 && $nouveauxParticipants ) {
				$create = true;
				$bugs[] = MailF::bug(
					"Le modèle {{Groupe}} de la page est corrompu, abandon de la création des utilisateurs.'",
					$wikiPage->getTitle()->getFullText()
				);
			}

			# création des nouveaux utilisateurs un à un
			if ( count( $tplGroupe ) == 1 && $nouveauxParticipants ) {
				$create = true;
				foreach ( $nouveauxParticipants as $nouveau ) {
					# création du compte
					$nouveau['Nom'] = strtoupper( $nouveau['Nom'] );
					$nouveau['Prénom'] = MgwF::sanitize_prenom( $nouveau['Prénom'] );
					$username = $nouveau['Prénom'] . ' ' . $nouveau['Nom'];
					$userData = array_merge( $nouveau, $tplGroupe[0] );
					if ( isset( $userData['Année'] ) && in_array( $userData['Type de groupe'], ['GEP', 'Stage praticien'] ) )
						$userData['Année de promotion'] = $userData['Année'];
					if ( isset( $userData['Tuteur ou modérateur'] ) )
						$userData['Responsable référent'] = $userData['Tuteur ou modérateur'];

		      $new_user = User::newFromName ( $username );
		      if ( $new_user->getId() == 0 ) {
						if ( self::createUser( $username, $userData ) ) {
							$newUsers[] = $username;
						}
						else {
							$bugs[] = 'Echec à la création de l\'utilisateur ' . $username;
						}
					}

					# on efface le modèle {{Nouveau Participant}} correspondant
					$native_content = str_replace( $nouveau['full-template-string'], '', $native_content );

					# on ajoute l'utilisateur à la liste des membres
					$tplInfos = PageF::getTemplateInfos ( $native_content, 'Groupe', ['Membres' => 'Membres'] );
					if ( $tplInfos && isset( $tplInfos[0]['Membres'] ) )
						$membres = explode(',', $tplInfos[0]['Membres'] );
					else $membres = [];
					$membres[] = $username;
					$membres = implode(',', $membres);
					$native_content = PageF::updateTemplateInfos ( $native_content, 'Groupe', [ 'Membres' => $membres ] );
				}
			}

			# on supprime la pages "fantôme" Groupe:Nouveaux_utilisateurs
			if ( preg_match('/^Groupe:Nouveaux utilisateurs/', $wikiPage->getTitle()->getFullText() ) > 0 ) {
				$mgwStatus = PageF::delete( $wikiPage, 'Suppression automatique d\'une page temporaire');
				if ( !$mgwStatus->done() ) $bug[] = $mgwStatus->mess();
				$redirectURL = '/wiki/index.php/MGWiki:Accueil';
			}

			if ( $create ) {

				# on met à jour la page groupe
				PageF::edit( $wikiPage, $hookSummary, $user, $native_content );

				# on informe l'utilisateur du résultat de la création des comptes
				$feedback = '';
				if ( $newUsers ) {
					$userlist = '';
					foreach ( $newUsers as $newUser ) {
						$userlist .= '<br>* [[Utilisateur:' . $newUser . '|' . $newUser . ']]';
					}
					$feedback .= wfMessage( 'mgw-createaccount-feedback', $userlist )->plain();
				}
				if ( $bugs ) {
					$feedback .= "<br><br>'''Des erreurs sont survenues:'''<br>";
					foreach ( $bugs as $bug ) {
						$feedback .= '<br>* ' . $bug;
					}
				}
				MgwF::afterSubmitInfo( $feedback, 'wikitext', 'continuer', $redirectURL );
			}
		}
 	}

	/**
	 * Redirect the user just after login if her/his semantic property says
	 * s/he should update her/his informations.
	 */
	static function onPostLoginRedirect( &$returnTo, &$returnToQuery, &$type ) {
		global $wgUser;
		if ( self::userRequireUpdate() ) {
			$returnTo = $wgUser->getUserPage()->getPrefixedText();
			$returnToQuery = [ 'action' => 'formedit' ];
			$type = 'successredirect';
		}
		return true;
	}

	/**
	 * @param SpecialPage $specialPage
	 * @param string|null $subpage
	 */
	static function onSpecialPageAfterExecute( $specialPage, $subpage ) {

		global $wgUser, $wgOut;

		# After the user has changed her/his password, send her/him to her/his userpage in form-edition to confirm her/his data
		//if( $specialPage->getName() == 'ChangeCredentials' && $specialPage->getRequest()->wasPosted() ) {
		// autres sp : 'MgwChangePassword'
		if ( self::userRequireUpdate() ) {
			$wgOut->redirect( $wgUser->getUserPage()->getFullURL( [ 'action' => 'formedit' ] ) );
			$wgOut->output();
		}

		return true;
	}

	/**
	 *
	 * Display a warning if the user account and user page don’t together exist or are missing.
	 *
	 * @param Title $targetTitle Page title
	 * @param string $pre_form_html String displayed just before the form
	 * @return true
	 */
	public static function onHTMLBeforeForm( $targetTitle, &$pre_form_html ) {

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

		if ( self::userRequireUpdate() ) {
			$pre_form_html = '<div class="warningbox">';
			$pre_form_html .= wfMessage( 'mgwiki-userpage-update-needed' )->plain();
			$pre_form_html .= "</div>\n";
		}

		return true;
	}

	/**
	 * Create a user.
	 *
	 * @param string $username Username
	 * @param string|null $email E-mail
	 * @param array $groups Groups
	 * @return bool The user was created
	 */
	public static function createUser( string $username, $userData = [], array $groups = [] ) {

		global $wgUser, $wgNewUserLog, $wgVersion;
		global $wgMGWikiUserProperties;

		$username = User::getCanonicalName( $username, 'creatable' );
		$user = User::newFromName( $username );
		if ( $user->getId() )
			return false;

		$properties = [];
		if ( array_key_exists( $wgMGWikiUserProperties['email'], $userData )
			&& is_string( $userData[$wgMGWikiUserProperties['email']] ) )
			$properties['email'] = $userData[$wgMGWikiUserProperties['email'] ];
			/*
		if ( array_key_exists( $userData['E-mail'] ) )
			$properties['email'] = $userData['E-mail'];
*/
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
		$content = new WikitextContent( self::userTemplate( $username, $userData ) );
		$flags = EDIT_NEW;
		$userArticle->doEditContent( $content, $summary, $flags, false, $wgUser );

		# Send email
		$user->sendConfirmationMail( 'mgw-create' );

		# Set groups
		if ( isset( $userData['Statut personne'] ) ) {
			if ( $userData['Statut personne'] == 'Interne' ) {
				$user->addGroup('interne');
			}
			elseif ( $userData['Statut personne'] == 'Médecin' ) {
				$user->addGroup('médecin');
			}
			elseif ( $userData['Statut personne'] == 'Scientifique' ) {
				$user->addGroup('scientifique');
			}
		}

		if ( isset( $userData['Statut additionnel personne'] )
			&& in_array( $userData['Statut additionnel personne'], ['Tuteur', 'Modérateur', 'Formateur', 'MSU'] ) ) {
				$user->addGroup('U2');
		}
		return true;
	}

	public static function userTemplate( $username, $userData ) {

		global $wgMGWikiUserProperties;
		//$email = array_key_exists( $wgMGWikiUserProperties['email'], $userData ) ? $userData[$wgMGWikiUserProperties['email']] : '';
		$statutPers = array_key_exists( $wgMGWikiUserProperties['statutPersonne'], $userData ) ? $userData[$wgMGWikiUserProperties['statutPersonne']] : '';
		$statutAddPers = array_key_exists( $wgMGWikiUserProperties['statutAdditionnelPersonne'], $userData ) ? $userData[$wgMGWikiUserProperties['statutAdditionnelPersonne']] : '';
		$institution = array_key_exists( $wgMGWikiUserProperties['institution'], $userData ) ? $userData[$wgMGWikiUserProperties['institution']] : '';
		$referrer = array_key_exists( $wgMGWikiUserProperties['referrer'], $userData ) ? $userData[$wgMGWikiUserProperties['referrer']] : '';
		$codeAdepul = array_key_exists( $wgMGWikiUserProperties['codeAdepul'], $userData ) ? $userData[$wgMGWikiUserProperties['codeAdepul']] : '';
		$year = array_key_exists( $wgMGWikiUserProperties['year'], $userData ) ? $userData[$wgMGWikiUserProperties['year']] : '';

		// wfMessage ne semble pas fonctionner au-delà de 10 arguments ??
		$content = "{{Personne\n|Titre=".
			"\n|Prénom=".$userData[$wgMGWikiUserProperties['firstname']].
			"\n|Nom=".$userData[$wgMGWikiUserProperties['lastname']].
			//"\n|E-mail=".$email. // ??
			"\n|Date de dernière modification=\n|Statut personne=".$statutPers.
			"\n|Statut additionnel personne=".$statutAddPers.
			"\n|Spécialité ou profession=\n|Institution de rattachement=".$institution.
			"\n|Responsable référent=".$referrer.
			"\n|Année de thèse=\n|Présentation=\n|Code ADEPUL=".$codeAdepul.
			"\n|Année de promotion=".$year."\n}}\n{{Personne2\n|Rapports et conflits d'intérêts=\n}}";
		/*
		$content = wfMessage( 'mgwiki-template-new-userpage',
			[
				$username,
				$userData[$wgMGWikiUserProperties['firstname']],
				$userData[$wgMGWikiUserProperties['lastname']],
				$email,
				$statutPers,
				$statutAddPers,
				$institution,
				$referrer,
				$year,
				$codeAdepul
			] )->inContentLanguage()->plain();
*/
		return $content;
	}

	public static function userRequireUpdate() {
		global $wgUser;
		global $wgMGWikiUserProperties;

		//$store = &smwfGetStore();
		$complete = null;

		$update = SmwF::collectSemanticData(
			[ $wgMGWikiUserProperties['requiredUserUpdate'] ],
			//$store->getSemanticData( SMW\DIWikiPage::newFromTitle( $wgUser->getUserPage() ) ),
			SmwF::getSemanticData( $wgUser->getUserPage() ),
			$complete
		);

		return ( count( $update ) == 1 && $update[$wgMGWikiUserProperties['requiredUserUpdate']] );
	}

	/**
	 * NB: DEPRECATED (suppression des mails des pages utilisateurs)
 	 * TODO: vérifier la mécanique de màj
	 */
	 /*
	public static function onPrefsEmailAudit( $user, $oldaddr, $newaddr ) {

		global $wgMGWikiUserProperties, $wgUser;

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
		$content = new WikitextContent( preg_replace( '/\| *'.preg_quote($emailProperty,'/').' *= *[a-zA-Z0-9@._+-]+/', "|$emailProperty=$newaddr", $oldContent->getNativeData() ) );

		# And edit
		$flags = EDIT_MINOR | EDIT_SUPPRESS_RC | EDIT_UPDATE;
		$status = $article->doEditContent( $content, $summary, $flags, false, $wgUser );
		if( !$status->isOK() ) {
			return;
		}
	}
	*/

	/**
	 * When a user page is modified by SemanticMediaWiki, create the corresponding MediaWiki user or update the email
	 * (...)
	 */
		/*
		public static function onSMW_SQLStore_AfterDataUpdateComplete(
			SMWSQLStore3 $store,
			SMWSemanticData $semanticData,
			SMW\SQLStore\CompositePropertyTableDiffIterator
			$compositePropertyTableDiffIterator )
		{

		!!! MECANIQUE CASSEE (bug incompréhensible sur la propriété e-mail)
		=> remplacement par MGWiki::onPageContentSaveComplete

			return true;
		}
		*/

		/*
		public static function onPageContentSave( )

		!!! comportement erratique...
		=> transfert à onPageContentSaveComplete
		*/

	/**
	 * FONCTION ORPHELINE ???
	 *
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
		if ( $title->getNamespace() === NS_USER ) {
			if( strpos( $title->getDBkey(), '/' ) !== false ) {
				return;
			}
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
			$moderator = SmwF::collectSemanticData( [ $wgMGWikiUserProperties['moderator'] ], $semanticData, $complete );
			if( count( $moderator ) == 1 ) {
				$institution = SmwF::collectSemanticData(
					[ $wgMGWikiUserProperties['institution'] ],
					$store->getSemanticData( SMW\DIWikiPage::newFromTitle( $moderator[$wgMGWikiUserProperties['moderator']] ) ),
					$complete
				);
				if( count( $institution ) != 1 ) {
					$institution = [];
				}
				$institution[$wgMGWikiUserProperties['referrer']] = $moderator[$wgMGWikiUserProperties['moderator']]->getText();
			}
		}
		elseif( array_key_exists( 'InstitutionFromCreator', $paramsForm ) && $paramsForm['InstitutionFromCreator'] ) {
			$creator = SmwF::collectSemanticData( [ '_LEDT' ], $semanticData, $complete );
			if( count( $creator ) == 1 ) {
				$institution = SmwF::collectSemanticData(
					[ $wgMGWikiUserProperties['institution'] ],
					$store->getSemanticData( SMW\DIWikiPage::newFromTitle( $creator['_LEDT'] ) ),
					$complete
				);
				if( count( $institution ) != 1 ) {
					$institution = [];
				}
				$institution[$wgMGWikiUserProperties['referrer']] = $creator['_LEDT']->getText();
			}
		}

		# Iterate over the subobjects
		$content = '';
		$templates = [];
		if ( array_key_exists( 'SubObjects', $paramsForm ) && $paramsForm['SubObjects'] ) {
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
					$userData = SmwF::collectSemanticData( $propertiesToBeSearched, $userSemanticData, $complete );
					$userData = array_merge( $institution, $userData );

					if ( array_key_exists( $wgMGWikiUserProperties['firstname'], $userData ) && array_key_exists( $wgMGWikiUserProperties['lastname'], $userData ) ) {
						# Iterate over the fields groups
						$userGroups = self::searchFieldsGroups( null, $editor, $userSemanticData, $editOwnUserpage );
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
			if ( $content && count( $templates ) ) {

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
			$statements = SmwF::collectSemanticData( [ $wgMGWikiUserProperties['email'] ], $semanticData, $complete );
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

	/**
	 * FONCTION ORPHELINE ?
	 */
	private static function searchFieldsGroups( $title, $editor, $semanticData, $editOwnUserpage ) {

		global $wgMGWikiFieldsGroups;

		$groups = [];
		$complete = null;

		foreach( $wgMGWikiFieldsGroups as $property => $paramsProperty ) {

			# Get data
			$statements = SmwF::collectSemanticData( [ $property ], $semanticData, $complete );
			#echo "\$statements ($property) = ";var_dump($statements);

			# Check permissions
			$canEditOwnUserpage = array_key_exists( 'EditOwnUserpage', $paramsProperty ) && $paramsProperty['EditOwnUserpage'];
			if ( !$editor->isAllowed( $paramsProperty['RequiredRight'] ) && !($editOwnUserpage && $canEditOwnUserpage) )
				continue;

			# Get the group to be added
			if ( array_key_exists( $property, $statements ) ) {
				$value = $statements[$property];
				// If the property is not explicitely defined (and has the type Page)
				if( $value instanceof Title ) {
					$value = $value->getText();
				}
				if( ! is_string( $value ) ) { // Mainly boolean
					$value = (string) $value;
				}
				$groups[$property] = $paramsProperty['MapFromProperty'][$value];
			} elseif ( $title && array_key_exists( 'MapFromTitle', $paramsProperty ) ) {
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

	/**
	 * FONCTION ORPHELINE ?
	 */
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
}
