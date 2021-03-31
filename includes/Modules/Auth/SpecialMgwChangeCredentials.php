<?php

namespace MediaWiki\Extension\MGWiki\Modules\Auth;

use SpecialPage;

use MediaWiki\Extension\MGWiki\Foreign\MGWRenameuser;
use MediaWiki\Extension\MGWiki\Foreign\MGWReplaceText;

use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWiki\Utilities\HtmlFunctions as HtmlF;
use MediaWiki\Extension\MGWiki\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWiki\Utilities\MgwFunctions as MgwF;
use MediaWiki\Extension\MGWiki\Utilities\UsersFunctions as UserF;
use MediaWiki\Extension\MGWiki\Utilities\MailFunctions as MailF;
use MediaWiki\Extension\MGWiki\Utilities\DataFunctions as DbF;

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
			if ( $new_name && $new_name != $targetUser->getName() ) {
				$user_exists = UserF::getUserFromAny( $new_name );
				$new_name_check = strtolower( str_replace( '-', ' ', $new_name ) );
				$current_name_check = strtolower( str_replace( '-', ' ', $targetUser->getName() ) );
				$sanitize_change = ( $new_name_check == $current_name_check );

				# compte déjà existant après correction de la casse = bug ...
				if ( $sanitize_change && $user_exists ) {
					MailF::bug('',"Echec au renommage automatique de $current_name en $new_name : compte déjà existant.");
					$new_name = $current_name;
					$sanitize_change = false;
					$user_exists = null;
				}
				$name_change = $new_name != $targetUser->getName();
			}

			////////////
			// CALLBACKS

			# NOTIFY
			$notify = [
				'create' => false,
				'confirm' => false
			];
			if ( isset( $reqData['notify'] ) ) {
				if ( $reqData['notify'] == 'create' ) {
					$status = $targetUser->sendConfirmationMail( 'mgw-create' );
					if ( $status->isOK() )
						$mess[] = 'Une demande de confirmation de compte a été envoyée à l\'adresse: ' . $targetUser->getEmail();
					$notify['create'] = true;
				}
				if ( $reqData['notify'] == 'confirm' ) {
					$status = $targetUser->sendConfirmationMail( 'mgw-confirm' );
					if ( $status->isOK() )
						$mess[] = 'Une demande de confirmation de courriel a été envoyée à l\'adresse: ' . $targetUser->getEmail();
					$notify['confirm'] = true;
				}
				if ( !$status->isOK() ) {
					$mess[] = MailF::bug( '', $status->getHTML() );
				}
			}

	    # SUBMIT // vérification des demandes de changements avant validation définitive
	    if ( $reqData['submit'] == 'submit' ) {
				$continue = true;
				$mess = [];
				if ( $new_name && $new_name != $targetUser->getName() ) {
					# compte déjà existant
					if ( $user_exists ) {
						$nom = $reqData['nom'];
						$prenom = $reqData['prenom'];
						$this->addUserNameNumber( $prenom, $nom );
						$mess[] = "Le nom d'utilisateur.ice <strong>$new_name</strong> est déjà utilisé."
							. " Il est associé au courriel <u>" . $user_exists->getEmail() . '</u>'
							. "<br>S'il ne s'agit pas de la même personne, afin de différencier les deux utilisateurs, vous pouvez ajouter:<ul> "
							. "<li>un deuxième nom</li><li>un deuxième prénom</li></ul>"
							. "A défaut, un nombre sera ajouté automatiquement après le nom: <strong>"
							. $prenom . ' ' . $nom . "</strong>";
					}
					# changement de nom dû à la normalisation de la casse
					elseif ( $sanitize_change ) {
						$mess[] = "<br>Le nom d'utilisateur.ice va être modifié pour des raisons de normalisation.<br>" .
							"Il s'écrira désormais: <strong>$new_name</strong>";
					}
					# changement de nom
					elseif ( $name_change ) {
						$mess[] = "Le nom d'utilisateur.ice va être modifié. " .
						  "Il s'écrira désormais: <strong>$new_name</strong><br>";
					}
				}
				if ( $reqData['email'] != $userData['email'] ) {
					if ( ! $this->is_email_new( $reqData['email'] ) ) {
						$mess[] = 'Changement d\'adresse courriel impossible: cette adresse est déjà utilisée par un autre utilisateur.';
						$continue = false;
					}
					else
						$mess[] = 'L\'adresse courriel va être modifiée:'.
							' il sera nécessaire la confirmer. <br>Souhaitez-vous continuer ?';
				}
				if ( !$mess ) {
					$mess[] = 'Aucune modification n\'a été apportée.';
					$continue = false;
				}
				if ( $continue ) {
					$mess[] = $this->displayConfirm();
				}
	    }

			# CONFIRM // modifications en tant que telles
	    if ( $reqData['submit'] == 'confirm' ) {
				$mess = [];

				# CHANGE MAIL
				if ( $reqData['email'] != $targetUser->getEmail() && $this->is_email_new( $reqData['email'] ) ) {
					$targetUser->setEmail( $reqData['email'] );
					$targetUser->saveSettings();
					$targetUser->sendConfirmationMail('mgw-confirm');
					$mess[] = wfMessage( 'mgw-credentials-changemail-feedback', $reqData['email'] )->plain();
				}

				# RENAME USER
				if ( $new_name && $new_name != $targetUser->getName() ) {
					if ( $user_exists ) {
						$this->addUserNameNumber( $reqData['prenom'], $reqData['nom'] );
						$new_name = $reqData['prenom'] . ' ' . $reqData['nom'];
					}
					$old_name = $targetUser->getName();
					$summary = wfMessage('mgw-credentials-changename-replacetex-summary');

					# 1. màj du modèle Personne
					$userPage = \WikiPage::factory( $targetUser->getUserPage() );
					$new_content = PageF::updatePageTemplateInfos (
						$userPage,
						'Personne',
						[ 'Prénom' => $reqData['prenom'], 'Nom' => $reqData['nom'] ],
						false, true, true
					);
					if ( $new_content ) {
						$mgwStatus = PageF::edit( $userPage, $summary, $wgUser, $new_content, true );
						if ( !$mgwStatus->done() ) $mess[] = $mgwStatus->mess();
					}

					# 2. renameuser (
					#! à partir de là $userPage est une redirection et l'utilisateur perd sa connexion
					if ( $wgUser->getName() == $old_name ) {
						$info = "<br>Il est nécessaire de vous re-connecter avec ce nouvel identifiant pour reprendre la navigation.";
					}
					else $info = '';
					$reason = wfMessage('mgw-credentials-changename-reason')->plain();
					$mgwStatus = MGWRenameuser::execute( $old_name, $new_name, true, false, $reason );
					$mess[] = str_replace(['<li','</li'],['<p','</p'], $mgwStatus->mess() );
					if ( $info ) $mess[] = $info;

					# 3. correction du nom de l'utilisateur dans les pages GROUPE et INSTITUTION
					# pour conserver la mécanique sémantique
					if ( $mgwStatus->done() && strlen( $old_name ) > 5 && preg_match( '/^.+ .+$/', $old_name ) > 0 ) {
						MGWReplaceText::do( [
							"target" => $old_name,
					 	  "replace" => $new_name,
					 	  "user" => $wgUser->getId(),
					 	 	"summary" => $summary,
					 	  "ns" => [ NS_GROUP, NS_PROJECT ],
					 	  "announce" => false
						] );
						// pour les écritures prénom minuscule ...
						MGWReplaceText::do( [
							"target" => lcfirst($old_name),
					 	  "replace" => $new_name,
					 	  "user" => $wgUser->getId(),
					 	 	"summary" => $summary,
					 	  "ns" => [ NS_GROUP, NS_PROJECT ],
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
		$out->addModules('ext.mgwiki.specialchangecredentials');

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
      $this->displayMainForm( $reqData, $userData, $targetUser, $notify );
    }

		# END
		$this->displayEnd( $reqData['returnto'], $done, $mess );
  }

	private function set_reqData() {

		global $_POST, $_GET;
    $reqData = $_POST;

		if ( isset( $_GET['user_id'] ) ) $reqData['user_id'] = $_GET['user_id'];
		if ( isset( $_GET['user_name'] ) ) $reqData['user_name'] = $_GET['user_name'];
		if ( isset( $_GET['returnto'] ) ) $reqData['returnto'] = $_GET['returnto'];
		if ( isset( $_GET['notify'] ) ) $reqData['notify'] = $_GET['notify'];

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
			$reqData['prenom'] = trim( MgwF::sanitize_prenom( $reqData['prenom'] ) );
		}
		if ( $reqData['nom'] ) {
    	$reqData['nom'] = htmlspecialchars( $reqData['nom'] );
			$reqData['nom'] = trim( strtoupper( $reqData['nom'] ) );
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

  private function displayMainForm( $reqData, $userData, $targetUser, $notify ) {
		global $wgUser;
    $out = $this->getOutput( );
		$out->addModules('ext.mgwiki.specialchangecredentials');
		$out->addHeadItems( HtmlF::include_resource_file ( 'specialchangecredentials.css', 'style' ) );
		$username = $targetUser->getName();

		if ( $wgUser->getName() == $username ) {
			# l'utilisateur gère son propre compte: lien vers "changer mon mot de passe"
			$out->setPageTitle( "Modifier mon compte: $username");
			$out->addHTML( '<br>' . (string)new \OOUI\ButtonWidget( [
				'href' => SpecialPage::getTitleFor( 'MgwChangePassword' )->getLinkURL( [
					'returnto' => str_replace('/wiki/index.php/','', $reqData['returnto'] ) ] ),
				'label' => wfMessage( 'prefs-resetpass' )->text(),
			] ) );
		}
		else {
			# un référent ou un administrateur gère le compte: affichage des infos du compte
			$out->setPageTitle( "Administrer le compte: $username");
			$data = [];
			$info_li = [];
			$userStatus = UserF::user_status( $targetUser, $data );
			if ( is_null( $userStatus ) ) {
				$out->addHTML( HtmlF::alertMessage( false, 'UTILISATEUR INEXISTANT' ) );
				return;
			}
			elseif ( $data['user_status'] === MGW_CONFIRMED ) {
				$title_class = 'mgw-user-ok';
				$info_title = "✓ compte actif";
				$info_li[] = "Adresse courriel confirmée le " . date( 'd-m-Y', wfTimestamp( TS_UNIX, $data['email_authenticated'] ) );
				$info_li[] = "Dernière activité le " . date( 'd-m-Y', wfTimestamp( TS_UNIX, $data['last_edit'] ) );
				$info_action = 'talk';
			}
			elseif ( $data['user_status'] === MGW_NEVERCONFIRMED ) {
				$title_class = 'mgw-user-wrong';
				$info_title = "✖ compte non confirmé";
				$info_li[] = "Adresse courriel non confirmée";
				$info_li[] = "Aucune activité enregistrée pour cet utilisateur.ice";
				$string = 'Invitation suite à création de compte envoyée';
				if ( !$notify['create'] ) $string .= " le " . date( 'd-m-Y', wfTimestamp( TS_UNIX, $data['last_invite'] ) );
				$info_li[] = $string;
				$info_action = 'create';
			}
			elseif ( $data['user_status'] === MGW_UNCONFIRMED ) {
				$title_class = 'mgw-user-incomplete';
				$info_title = "? compte actif - confirmation courriel en attente";
				$string = 'Adresse courriel non confirmée';
				if ( !$notify['confirm'] ) $string .= "(demande de confirmation envoyée le "
					. date( 'd-m-Y', wfTimestamp( TS_UNIX, $data['last_invite'] ) ) . ")";
				$info_li[] = $string;
				$info_li[] = "Dernière activité le " . date( 'd-m-Y', wfTimestamp( TS_UNIX, $data['last_edit'] ) );
				$info_action = "confirm";
			}
			elseif ( $data['user_status'] === MGW_NOEDITS ) {
				# cas d'usage théorique, ne devrait pas exister
				$title_class = 'mgw-user-incomplete';
				$info_title = "? compte confirmé - inactif";
				$info_li[] = "Adresse courriel confirmée le " . date( 'd-m-Y', wfTimestamp( TS_UNIX, $data['email_authenticated'] ) );
				$info_li[] = "Aucune activité enregistrée pour cet utilisateur.ice";
				$info_action = 'talk';
			}
			$info_body = '';
			foreach ( $info_li as $li ) {
				$info_body .= '<li>' . $li . '</li>';
			}
			$mess = "<p class=\"{$title_class}\"><strong>{$info_title}</strong><p><ul>{$info_body}</ul>";
			$out->addHTML( $mess );
			if ( $info_action == 'talk' ) {
				$out->addHTML( '<div class="mgw-ooui-submit">' . (string)new \OOUI\ButtonWidget( [
					'href' => '/wiki/index.php?title=Discussion_utilisateur:' . $targetUser->getName() . '&action=edit',
					'label' => 'Adresser un message',
					'flags' => ['progressive']
				] ) . '</div>');
			}
			if ( $info_action == 'create' ) {
				$out->addHTML( '<div class="mgw-ooui-submit">' . (string)new \OOUI\ButtonWidget( [
					'href' => SpecialPage::getTitleFor( 'MgwChangeCredentials' )->getLinkURL( [
						'notify' => 'create',
						'user_id' => $targetUser->getId(),
						'returnto' => str_replace('/wiki/index.php/','', $reqData['returnto'] )
					] ),
					'label' => 'Renvoyer une invitation',
					'flags' => ['progressive']
				] ) . "</div>" );
			}
			if ( $info_action == 'confirm' ) {
				$out->addHTML( '<div class="mgw-ooui-submit">' . (string)new \OOUI\ButtonWidget( [
					'href' => SpecialPage::getTitleFor( 'MgwChangeCredentials' )->getLinkURL( [
						'notify' => 'confirm',
						'user_id' => $targetUser->getId(),
						'returnto' => str_replace('/wiki/index.php/','', $reqData['returnto'] )
					] ),
					'label' => 'Renouveler la demande de confirmation de courriel',
					'flags' => ['progressive']
				] ) . "</div>");
			}
			$out->addHTML('<hr/>');
		}

    $out->addHTML( HtmlF::form( 'open', $hiddenInputs = [
			'user_id' => $reqData['user_id'],
			'returnto' => $reqData['returnto'] ] )
		);
		$out->addHTML( '<p><strong>Prénom</strong></p><div id="mgw-field-prenom">' .
			new \OOUI\TextInputWidget( [
		    'infusable' => true,
		    'id' => 'mgw-prenom',
				'name' => 'prenom',
				'type' => 'text',
				'value' => $reqData['prenom'],
				'required' => true
			] ) . '<span class="mgw-hidden-data" style="display:none;">'.$userData['prenom'].'</span></div>'
		);
		$out->addHTML( '<p><strong>Nom</strong></p><div id="mgw-field-nom">' .
			new \OOUI\TextInputWidget( [
		    'infusable' => true,
		    'id' => 'mgw-nom',
				'name' => 'nom',
				'type' => 'text',
				'value' => $reqData['nom'],
				'required' => true
			] ) . '<span class="mgw-hidden-data" style="display:none;">'.$userData['nom'].'</span></div>'
		);
		$out->addHTML( '<p><strong>Courriel</strong></p><div id="mgw-field-email">' .
			new \OOUI\TextInputWidget( [
		    'infusable' => true,
		    'id' => 'mgw-email',
				'name' => 'email',
				'type' => 'email',
				'value' => $reqData['email'],
				'required' => true
			] ) . '<span class="mgw-hidden-data" style="display:none;">'.$userData['email'].'</span></div>'
		);
		$out->addHTML( '<div class="mgw-ooui-submit mgw-flex">' . new \OOUI\ButtonInputWidget( [
				'name' => 'submit',
				'value' => 'submit',
				'label' => 'Soumettre les modifications',
				'type' => 'submit',
				'flags' => [ 'progressive']
			] ) . new \OOUI\ButtonWidget( [
				'label' => 'restaurer',
		    'infusable' => true,
		    'id' => 'mgw-restore-btn',
			] ) . '</div>' );
    $out->addHTML( HtmlF::form( 'close' ) );
  }

	private function displayConfirm( ) {
		$out = $this->getOutput();
		return '<div id="mgw-confirm" style="text-align:center;"></div>';
	}

	private function displayEnd( $url, $done, $mess ) {
		$out = $this->getOutput();
		if ( !$done || $mess ) {
			$label = ( $done ) ? 'ok' : 'retour';
			$out->addHTML( '<hr/><br>' . new \OOUI\ButtonWidget( [
					'href' => $url,
					'label' => $label
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

	private function is_email_new( $email ) {
		global $wgDBprefix;
		$sql = "SELECT user_name FROM {$wgDBprefix}user WHERE user_email LIKE '$email'";
		$req = DbF::mysqli_query( $sql );
		return ( !(bool)$req );
	}

	private function addUserNameNumber( $prenom, &$nom ) {
		global $wgDBprefix;
		# 1. liste des homonymes:
		$sql = "SELECT user_name FROM {$wgDBprefix}user WHERE user_name LIKE '".
			$prenom . ' ' . $nom . "%'";
		$req = DbF::mysqli_query( $sql );
		if ( $req ) {
			$n = count( $req ) + 1;
			$nom = $nom . " " . $n;
		}
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
