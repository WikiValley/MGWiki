<?php

namespace MediaWiki\Extension\MGWiki\Modules\Auth;

use SpecialPage;

use MediaWiki\Extension\MGWiki\Foreign\MGWRenameuser;
use MediaWiki\Extension\MGWiki\Foreign\MGWReplaceText;

use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWiki\Utilities\HtmlFunctions as HtmlF;
use MediaWiki\Extension\MGWiki\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWiki\Utilities\MgwFunctions as MgwF;

/**
 * page spéciale pour modifier les données des comptes utilisateurs:
 * E-mail, nom d'utilisateur (Prénom NOM)
 */
class SpecialMgwChangeCredentials extends \SpecialPage {

	private $special;

	public function __construct() {
		$this->special = 'MgwChangeCredentials';
		parent::__construct( $this->special );
	}

	public function execute( $sub ) {

		global $wgUser;

		////////////
		// VARIABLES
		$mess = [];
		$done = false;

		$reqData = $this->set_reqData();
		$targetUser = $this->set_targetUser( $reqData );

		if ( $targetUser && !$this->permission( $targetUser ) ) {
			$mess[] = "Vous n'avez pas les permissions nécessaires pour réaliser cette action.";
			$done = true;
		}

		if ( !$done ) {

			$has_template = false;
			$userData = $this->get_userData( $targetUser, $has_template );

			if ( $reqData['user_id'] && !$targetUser ) {
				$mess[] = wfMessage('mgw-credentials-unknown-user', $reqData['user_id'] )->plain();
			}
			if ( $reqData['user_name'] && !$targetUser ) {
				$mess[] = wfMessage('mgw-credentials-unknown-user', $reqData['user_name'] )->plain();
			}

			if ( $targetUser && !$has_template ) {
				$mess[] = wfMessage('mgw-credentials-no-template')->plain();
				$mess[] = wfMessage('mgw-credentials-make-template')->plain();
				$userData['prenom'] = $targetUser->getName();
			}

			if ( $targetUser && !$userData['email'] ) {
				$mess[] = wfMessage('mgw-credentials-no-email')->plain();
			}

			$new_name = ( $reqData['prenom'] && $reqData['nom'] ) ? $reqData['prenom'] . ' ' . $reqData['nom'] : '';

			////////////
			// CALLBACKS

	    # SUBMIT // vérification des demandes de changements avant validation définitive
	    if ( $reqData['submit'] == 'submit' ) {
				$mess = [];
				if ( $new_name ) {
					$current_name = $targetUser->getName();
					$new_name_check = strtolower( str_replace( '-', ' ', $new_name ) );
					$current_name_check = strtolower( str_replace( '-', ' ', $current_name ) );

					# changement de nom dû à la normalisation de la casse
					if ( ( $new_name != $current_name ) &&
							 ( $new_name_check == $current_name_check ) ) {
						$mess[] = "<br>Le nom d'utilisateur.ice va être modifié pour des raisons de normalisation.<br>" .
							"Il s'écrira désormais: '<strong>$new_name</strong>'.";
					}
					# changement de nom
					elseif ( $new_name != $current_name ) {
						$mess[] = "Le nom d'utilisateur.ice va être modifié. " .
						  "Il s'écrira désormais: '<strong>$new_name</strong>'.<br>";
					}
				}
				if ( $reqData['email'] != $userData['email'] ) {
					$mess[] = 'L\'adresse courriel va être modifiée:'.
						' il sera nécessaire la confirmer. <br>Souhaitez-vous continuer ?';
				}
				if ( !$mess ) {
					$mess[] = 'Aucune modification n\'a été apportée.';
				}
				else {
					$mess[] = $this->displayConfirm();
				}
	    }

			# CONFIRM // modifications en tant que telles
	    if ( $reqData['submit'] == 'confirm' ) {
				$mess = [];

				# CHANGE MAIL
				if ( $reqData['email'] != $targetUser->getEmail() ) {
					$targetUser->setEmail( $reqData['email'] );
					$targetUser->saveSettings();
					$targetUser->sendConfirmationMail('mgw-confirm');
					$mess[] = wfMessage( 'mgw-credentials-changemail-feedback', $reqData['email'] )->plain();
				}

				# RENAME USER
				if ( $new_name != $targetUser->getName() ) {
					$old_name = $targetUser->getName();
					$summary = wfMessage('mgw-credentials-changename-replacetex-summary');

					# 1. màj du modèle Personne
					$userPage = \WikiPage::factory( $targetUser->getUserPage() );
					$new_content = PageF::updatePageTemplateInfos (
						$userPage,
						'Personne',
						['Prénom' => $reqData['prenom'], 'Nom' => $reqData['nom'] ],
						false, true, true
					);
					if ( $new_content ) {
						$mgwStatus = PageF::edit( $userPage, $summary, $wgUser, $new_content, true );
						if ( !$mgwStatus->done() ) $mess[] = $mgwStatus->mess();
					}

					# 2. renameuser
					$reason = wfMessage('mgw-credentials-changename-reason')->plain();
					$mgwStatus = MGWRenameuser::execute( $old_name, $new_name, true, false, $reason );
					$mess[] = str_replace(['<li','</li'],['<p','</p'], $mgwStatus->mess() );

					# 3. correction du nom de l'utilisateur dans les pages
					if ( $mgwStatus->done() && strlen( $old_name ) > 5 && preg_match( '/^.+ .+$/', $old_name ) > 0 ) {
						MGWReplaceText::do( [
							"target" => $old_name,
					 	  "replace" => $new_name,
					 	  "user" => $wgUser->getId(),
					 	 	"summary" => $summary,
					 	  "nsall" => true,
					 	  "announce" => false
						] );
						// pour les écritures prénom minuscule ...
						MGWReplaceText::do( [
							"target" => lcfirst($old_name),
					 	  "replace" => $new_name,
					 	  "user" => $wgUser->getId(),
					 	 	"summary" => $summary,
					 	  "nsall" => true,
					 	  "announce" => false
						] );
					}
				}
				$done = true;
	    }
		}

		////////////
		// AFFICHAGE
    $this->setHeaders();
    $out = $this->getOutput();
		$out->enableOOUI();

		# INFO
		if ( $mess ) {
			$mess = implode( '<br>', $mess );
			if ( $done ) {
				$out->addHTML( $mess );
			}
			else $out->addHTML( HtmlF::alertMessage( $done, $mess ) );
		}

		# SEARCH
    if ( ! $targetUser ) {
      $this->displaySearchForm( $reqData );
			return;
    }

    # FORM
    if ( ! $done ) {
			if ( $userData ) {
				foreach ( $userData as $field => $value ) {
					if ( $field != 'full-template-string' && !$reqData[$field] ) $reqData[$field] = $value;
				}
			}
      $this->displayMainForm( $reqData, $targetUser );
    }

		# END
		$show = ( !$done || $mess );
		$this->displayEnd( $reqData['returnto'], $show );
  }

	private function set_reqData() {

		global $_POST, $_GET;
    $reqData = $_POST;

		if ( isset( $_GET['user_id'] ) ) $reqData['user_id'] = $_GET['user_id'];
		if ( isset( $_GET['user_name'] ) ) $reqData['user_name'] = $_GET['user_name'];
		if ( isset( $_GET['returnto'] ) ) $reqData['returnto'] = $_GET['returnto'];

    PhpF::empty( $reqData['user_id'] );
    PhpF::empty( $reqData['user_name'] );
    PhpF::empty( $reqData['nom'] );
    PhpF::empty( $reqData['prenom'] );
    PhpF::empty( $reqData['email'] );
    PhpF::empty( $reqData['returnto'] );
    PhpF::empty( $reqData['submit'] );

		if ( $reqData['user_id'] ) $reqData['user_id'] = (int)$reqData['user_id'];
		if ( $reqData['prenom'] ) {
    	$reqData['prenom'] = htmlspecialchars( $reqData['prenom'] );
			$reqData['prenom'] = MgwF::sanitize_prenom( $reqData['prenom'] );
		}
		if ( $reqData['nom'] ) {
    	$reqData['nom'] = htmlspecialchars( $reqData['nom'] );
			$reqData['nom'] = strtoupper( $reqData['nom'] );
		}

		if ( $reqData['returnto']
					&& preg_match( '/^http/', $reqData['returnto'] ) != 1
					&& preg_match( '/^\/wiki\/index.php\//', $reqData['returnto'] ) != 1 ) {
			$reqData['returnto'] = '/wiki/index.php/' . $reqData['returnto'];
		}
		if ( !$reqData['returnto'] ) {
			$reqData['returnto'] = $this->selfUrl();
		}

		return $reqData;
	}

	private function set_targetUser( &$reqData ) {
    if ( $reqData['user_id'] ) {
      $targetUser = \User::newFromId( $reqData['user_id'] );
    }
		elseif ( $reqData['user_name'] ) {
      $targetUser = \User::newFromName( $reqData['user_name'] );
      if ( $targetUser->getId() < 1 ) {
        $targetUser = null;
      }
    }
		else $targetUser = null;

		if ( $targetUser ) {
			$reqData['user_name'] = $targetUser->getName();
			$reqData['user_id'] = $targetUser->getId();
		}

		return $targetUser;
	}

	private function get_userData( $targetUser, &$has_template ) {

		if ( !$targetUser ) return [];

		$userData = PageF::getPageTemplateInfos (
			$targetUser->getUserPage(),
			'Personne',
			[	'prenom' => 'Prénom', 'nom' => 'Nom' ]
		);
		if ( !$userData ) $userData = [];
		else {
			$userData = $userData[0];
		  $has_template = true;
		}

		$userData['email'] = $targetUser->getEmail();

		return $userData;
	}

  private function displaySearchForm( $reqData ) {

    $out = $this->getOutput( );
		$out->addModules('ext.mgwiki.ooui-search');
		$out->addHeadItems( HtmlF::include_resource_file ( 'ooui-search.css', 'style' ) );

		# construction des données à destination de ext.mgw-ooui-search
    $user_id = ( $reqData['user_id'] ) ? [ $reqData['user_id'] ] : [];
    $user_name = ( $reqData['user_name'] ) ? [ $reqData['user_name'] ] : [];
		$hiddenFields = [
			"user_id" => [
				"data" => $user_id,
				"label" => $user_name,
				"multiple" => false,
				"required" => true
			]
		];
		$out->addHTML( '<div id="mgw-ooui-data-transfer" hidden>' . json_encode($hiddenFields). '</div>' );
    $out->addHTML( HtmlF::form( 'open', $hiddenInputs = [ 'user_id' => $reqData['user_id'], 'returnto' => $reqData['returnto'] ] ) );
		$out->addHTML( '<br>' . new \OOUI\ButtonInputWidget( [
			'name' => 'submit',
			'label' => 'modifier',
			'value' => 'edit',
			'type' => 'submit',
			'flags' => [ 'primary', 'progressive' ]
			] )
    );
    $out->addHTML( HtmlF::form( 'close' ) );
  }

  private function displayMainForm( $reqData, $targetUser ) {
		global $wgUser;
    $out = $this->getOutput( );
		$username = $targetUser->getName();
		$out->addWikiText( "<h3>[[Utilisateur:$username|$username]]</h3>");

		if ( $wgUser->getName() == $username ) {
			$out->addHTML( '<br>' . (string)new \OOUI\ButtonWidget( [
				'href' => SpecialPage::getTitleFor( 'ChangePassword' )->getLinkURL( [
					'returnto' => str_replace('/wiki/index.php/','', $reqData['returnto'] ) ] ),
				'label' => wfMessage( 'prefs-resetpass' )->text(),
			] ) );
		}

    $out->addHTML( HtmlF::form( 'open', $hiddenInputs = [
			'user_id' => $reqData['user_id'],
			'returnto' => $reqData['returnto'] ] )
		);
		$out->addHTML( new \OOUI\FieldLayout(
			new \OOUI\TextInputWidget( [
				'name' => 'prenom',
				'type' => 'text',
				'value' => $reqData['prenom'],
				'required' => true
			] ),
			[
				'label' => 'Prénom',
				'align' => 'top'
			]
		));
		$out->addHTML( new \OOUI\FieldLayout(
			new \OOUI\TextInputWidget( [
				'name' => 'nom',
				'type' => 'text',
				'value' => $reqData['nom'],
				'required' => true
			] ),
			[
				'label' => 'Nom',
				'align' => 'top'
			]
		));
		$out->addHTML( new \OOUI\FieldLayout(
			new \OOUI\TextInputWidget( [
				'name' => 'email',
				'type' => 'email',
				'value' => $reqData['email'],
				'required' => true
			] ),
			[
				'label' => 'E-mail',
				'align' => 'top'
			]
		));
		$out->addHTML( new \OOUI\FieldLayout(
			new \OOUI\ButtonInputWidget( [
				'name' => 'submit',
				'value' => 'submit',
				'label' => 'Soumettre les modifications',
				'type' => 'submit',
				'flags' => [ 'progressive']
			] ),
			[
				'label' => null,
				'align' => 'top',
			]
		));
    $out->addHTML( HtmlF::form( 'close' ) );
  }

	private function displayConfirm( ) {
		$out = $this->getOutput();
		$out->addModules('ext.mgwiki.specialchangecredentials');
		return '<div id="mgw-confirm" style="text-align:center;"></div>';
	}

	private function displayEnd( $url, $show ) {
		$out = $this->getOutput();
		if ( $show ) {
			$out->addHTML( '<br><hr/><br>' . new \OOUI\ButtonWidget( [
					'href' => $url,
					'label' => 'retour'
				] ) );
		}
		else	$out->redirect( $url );
	}

	private function permission( $targetUser ) {
		global $wgUser;
		return ( $wgUser->getName() == $targetUser->getName() ||
						in_array( 'sysop', $wgUser->getGroups() ) ||
						MgwF::is_user_referent( $wgUser, $targetUser ) ||
					  MgwF::is_user_instit_referent( $wgUser, $targetUser ) );
	}

	/**
	 * @param array $get associative array of GET request parameters
	 * [key => value]
	 */
	private function selfURL( array $get = [] ) {
		return SpecialPage::getTitleFor( $this->special )->getLinkURL( $get );
	}

	protected function getGroupName() {
		return 'mgwiki';
	}
}
