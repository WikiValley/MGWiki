<?php

namespace MediaWiki\Extension\MGWiki;
use MediaWiki\Extension\MGWiki\Utilities\HtmlFunctions as HtmlF;
use MediaWiki\Extension\MGWiki\Utilities\UsersFunctions as UserF;
use MediaWiki\Extension\MGWiki\Utilities\MgwFunctions as MgwF;
use MediaWiki\Revision\RevisionStore;

/**
 * MGWiki general functions and hooks.
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @author Alexandre Brulet
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWikiDev
 */
class MGWikiHooks {

	/**
	 * Skinning & admin interfaces for MGWiki
	 *
   * NB: MGW 0.2 -> PATCH TEMPORAIRE DANS ext.mgwiki.js
	 * pour le réglage des valeurs par défaut des formulaires GEP/GAPP/etc.
	 *
	 * @param OutputPage $out
	 * @param Skin $skin
	 * @return true
	 */
	static function onBeforePageDisplay( &$out, &$skin ) {
		global $wgUser;

		// SENDMASSMAIL
		// Affiche un bandeau d'information sur la page d'édition du corps du mail
		$string = wfMgwConfig( 'sendmassmail', 'body-page' )['string'];
		if ( preg_match( '/\/'.$string.'$/', $out->getTitle()->getFullText() ) > 0 ) {
			$out->prependHTML( '<div class="smw-editpage-help">' . wfMessage( 'mgw-massmail-bodypage-info' )->parse() . '</div>' );
		}

		// STYLE GENERAL
		$out->addModules( 'ext.mgwiki' );
			# styles css séparés du module pour cause de lenteur...
		$out->addHeadItems( HtmlF::include_resource_file ( 'ext.mgwiki.css', 'style' ) );

		// SELON LES NS
		$namespace = $skin->getRelevantTitle()->getNamespace();
		$titletext = $skin->getRelevantTitle()->getText();
		$action = $skin->getRequest()->getVal( 'action' );

		# NS_USER
    if ( $namespace == NS_USER && !$action ) {
			$targetUser = \User::newFromName( $titletext );
			if ( $targetUser && $targetUser->getId() > 0 ) {
	      $out->addModules( 'ext.mgwiki.userpage' );
				$out->addHeadItems( HtmlF::include_resource_file ( 'userpage.css', 'style' ) );

					# l'utilisateur est sur sa propre page:
					# liens vers les formulaires Personne + changeCredentials
				if ( $titletext == $wgUser->getName() ) {

					$form_url = '/wiki/index.php?title=Utilisateur:'.$titletext.'&action=formedit';
					$form_tooltip = wfMessage('mgw-userpage-formlink-tooltip')->text();
					$cred_url = '/wiki/index.php/Special:MgwChangeCredentials?'.
						'user_name=' . $titletext . '&returnto=' . 'Utilisateur:' . $titletext;
					$cred_tooltip = wfMessage('mgw-userpage-credlink-tooltip')->text();
					$inner = HtmlF::edit_button_img( $form_url, $form_tooltip ) .
									 HtmlF::admin_link( wfMessage('mgw-userpage-formlink')->text(), 'bleu', $form_url, $form_tooltip ) .
									 HtmlF::admin_link( wfMessage('mgw-userpage-credlink')->text(), 'orange', $cred_url, $cred_tooltip );
					$out->prependHTML( '<span style="text-align:center; display:block">'. $inner .'</span>' );

					$out->addHTML('<div id="mgw-hide-edit" value="true" hidden></div>');
				}
				  # si référent ou sysop: credentials
				elseif ( MgwF::is_user_referent( $wgUser, $targetUser ) ||
			 					 MgwF::is_user_instit_referent( $wgUser, $targetUser ) ||
							 	 in_array('sysop', $wgUser->getGroups() ) )
				{
					$cred_url = '/wiki/index.php/Special:MgwChangeCredentials?'.
						'user_name=' . $titletext . '&returnto=Utilisateur:' . $titletext;
					$cred_tooltip = wfMessage('mgw-userpage-credlink-tooltip-admin')->text();
					$inner = HtmlF::admin_link( wfMessage('mgw-userpage-credlink-admin')->text(), 'orange', $cred_url, $cred_tooltip );
					$out->prependHTML( '<span style="text-align:center; display:block">'. $inner .'</span>' );

					if ( !in_array('sysop', $wgUser->getGroups() ) ) {
						$out->addHTML('<div id="mgw-hide-edit" value="true" hidden></div>');
					}
				}
				else {
					# balise pour masquer les liens d'édition (via userpage.js)
					$out->addHTML('<div id="mgw-hide-edit" value="true" hidden></div>');
					$out->addHTML('<div id="mgw-hide-formedit" value="true" hidden></div>');
				}
			}
    }

		# en-tête de dernière Màj
		if ( in_array( $namespace, [ NS_MAIN, NS_USER ] ) && !$action ) {
			$revisionRecord = \WikiPage::factory( $skin->getRelevantTitle() )->getRevisionRecord();
			if ( $revisionRecord ) {
				$tmstp = date( 'd-m-Y', wfTimestamp( TS_UNIX, $revisionRecord->getTimestamp() ) );
				$edit_user = $revisionRecord->getUser();
				$edit_user = '<strong><a href="'.$edit_user->getUserPage()->getFullURL().'">' .
					$edit_user->getName() . '</a></strong> (<a href="'.
					$edit_user->getTalkPage()->getFullURL() . '">discuter</a>)';
				$header = wfMessage('mgw-contentheader-lastedit', $tmstp, $edit_user )->plain();

				$out->prependHTML('<div class="mgw-contentheader-editinfo">' . $header . '</div>' );
			}
		}

		# en-tête créateur
		if ( in_array( $namespace, [ NS_RECIT ] ) && !$action ) {
			// ! Deprecated > MW 1.35 => utiliser RevisionStore::getFirstRevision($wikiPage)
			$revision = \WikiPage::factory( $skin->getRelevantTitle() )->getOldestRevision();
			$edit_user = \User::newFromID( $revision->getUser() );
			$edit_user = '<strong><a href="'.$edit_user->getUserPage()->getFullURL().'">' .
				$edit_user->getName() . '</a></strong> (<a href="'.
				$edit_user->getTalkPage()->getFullURL() . '">discuter</a>)';
			$header = wfMessage('mgw-contentheader-creator', $edit_user )->plain();

			$out->prependHTML('<div class="mgw-contentheader-editinfo">' . $header . '</div>' );
		}
		return true;
	}

  /**
   * TODO
   * Gestion des de mails de création / confirmation / passwd-reset
   * en combinaison avec SpecialMgwEmailAuth
   */
  public static function onUserSendConfirmationMail( $user, &$mail, $info ) {

    global $wgUser, $wgLang;

    if ( !in_array( $info['type'], [ 'mgw-create', 'mgw-confirm', 'mgw-reset' ] ) )
      return;

      # on différencie les demandes de confirmation déclenchées par
      # l'utilisateur lui-même / par quelqu'un d'autre
    $intro = ( $wgUser->getId() == $user->getId() ) ? "Vous avez" : $wgUser->getName() . " a";

    $subj_mess = 'confirmemail_subject_' . $info['type'];
    $body_mess = 'confirmemail_body_' . $info['type'];

    $mail['subject'] = wfMessage( $subj_mess )->text();

    $mail['body'] = wfMessage( $body_mess,
      $intro,
      $user->getName(),
      $info['confirmURL'],
      $wgLang->userTimeAndDate( $info['expiration'], $user )
    )->text();
  }

	/**
   * ! CUSTOM HOOK ! (cf readme -> ApiMain.php changes)
	 * Autoriser l'API getjson quel que soit l'utilisateur
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

 	/**
 	 * login avec mail + authentification insensible à la casse
 	 */
  public static function onAuthChangeFormFields( $requests, $fieldInfo, &$formDescriptor, $action ) {

    // uniquement pour le formulaire de login
    global $wgRequest;
    if ( $wgRequest->getGlobalRequestURL() == '/wiki/index.php/Sp%C3%A9cial:Connexion' )
    {
      $formDescriptor['username']['label'] = 'E-mail  ou  nom d\'utilisateur';
      $formDescriptor['username']['class'] = 'HTMLTextField';
      $formDescriptor['username']['filter-callback'] = function ( $val, $array ) {

        // sécurité
        $val = htmlspecialchars( $val, ENT_QUOTES );

        // si email connu on récupère le nom d'utilisateur correspondant
        if ( preg_match( '/@/', $val ) > 0 ) {
					$users = UserF::emailExists( $val );
					if ( $users && count($users) == 1 ) $user_name = $users[0]['user_name'];
					elseif ( $users && count($users) > 1 ) {
					  return $val.' est utilisé par plusieurs comptes';
					}
					else {
						return $val.' : inconnu';
					}
				}

        // on corrige les erreurs de casse et d'accents
        else $user_name = UserF::userExists( $val, false );

        if ( !$user_name ) {
          return $val.' : inconnu.e';
        }

        return $user_name;
      };
    }
  }

	/**
	 * CUSTOM
	 */
	public static function onChangeMailFormFields( &$fields ) {
		$fields['NewEmail']['validation-callback'] = function( $email, $allData ){
		 		$r = UserF::emailExists( $email );
				if ( $r ) {
					$users = [];
					foreach ( $r as $row ) $users[] = $row['user_name'];
				  return 'E-mail est déjà utilisé pour les comptes: ' . implode(', ', $users) . '.';
				}
				return true;
		};
	}
}
