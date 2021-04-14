<?php

namespace MediaWiki\Extension\MGWiki\Modules\Auth;

use SpecialPage;

use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWiki\Utilities\HtmlFunctions as HtmlF;
use MediaWiki\Extension\MGWiki\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWiki\Utilities\MgwFunctions as MgwF;

/**
 * page spéciale pour demander la résiliation de son compte MGWiki
 * E-mail, nom d'utilisateur (Prénom NOM)
 */
class SpecialMgwDeleteAccountQuery extends \SpecialPage {

	private $special;

	public function __construct() {
		$this->special = 'MgwDeleteAccountQuery';
		parent::__construct( $this->special );
	}

	public function execute( $sub ) {

		global $wgUser;

		////////////
		// VARIABLES
		$mess = [];

		$reqData = $this->set_reqData();
		$targetUser = $this->set_targetUser( $reqData );

		if ( $reqData['user_id'] && !$targetUser ) {
			$mess[] = wfMessage('mgw-credentials-unknown-user', $reqData['user_id'] )->plain();
		}
		if ( $reqData['user_name'] && !$targetUser ) {
			$mess[] = wfMessage('mgw-credentials-unknown-user', $reqData['user_name'] )->plain();
		}

		$done = false;

		////////////
		// CALLBACKS

    # SUBMIT // confirmation de la demande de résiliation
    if ( $reqData['submit'] == 'submit' ) {
			$mess = [];

			if ( !$mess ) {
				$mess[] = 'Aucune modification n\'a été apportée.';
			}
			else {
				$mess[] = $this->displayConfirm();
			}
    }

		# CONFIRM // demande de résiliation en tant que telle
    if ( $reqData['submit'] == 'confirm' ) {
			$mess = [];

			$done = true;
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

    # FORM
    if ( ! $done ) {
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
    PhpF::empty( $reqData['returnto'] );
    PhpF::empty( $reqData['submit'] );

		if ( $reqData['user_id'] ) $reqData['user_id'] = (int)$reqData['user_id'];

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

  private function displayMainForm( $reqData, $targetUser ) {
    $out = $this->getOutput( );
		$username = $targetUser->getName();
		$out->addWikiText( "<h3>[[Utilisateur:$username|$username]]</h3>");
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
		$out->addModules('ext.mgw-credentials');
		return '<div id="mgw-confirm" style="text-align:center;"></div>';
	}

	private function displayEnd( $url, $show ) {
		$out = $this->getOutput();
		if ( $show ) {
			$out->addHTML( '<br>' . new \OOUI\ButtonWidget( [
					'href' => $url,
					'label' => 'retour'
				] ) );
		}
		else	$out->redirect( $url );
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
