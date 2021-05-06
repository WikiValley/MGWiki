<?php
/**
 * API for internal mgw extension actions
 */
namespace MediaWiki\Extension\MGWiki\Api;

use ApiBase;
use MediaWiki\Extension\MGWiki\Utilities\MgwFunctions as MgwF;
use MediaWiki\Extension\MGWiki\Utilities\MailFunctions as MailF;
use MediaWiki\Extension\MGWiki\Utilities\PagesFunctions as PageF;

class ApiMgwAction extends ApiBase {

	public function execute( ) {
		global $wgUser;
    $params = $this->extractRequestParams();
		$r = $this->getResult();

		try {
			if ( !isset( $params['query'] ) ) {
				throw new \Exception("Erreur : le paramètre '&action=' doit être défini.");
			}

      switch ( $params['query'] ) {

				case 'invite':

					if ( !isset( $params['user_name'] ) ) {
						$info = "Le paramètre 'user_name' doit être défini";
						$res['done'] = [
							'status' => 'failed',
							'info' => $info,
							'fatal' => true
						];
						throw new \Exception( $info, 1);
					}

					$targetUser = \User::newFromName( $params['user_name'] );
					if ( $targetUser->getId() < 1 ) {
						$info = "Utilisateur inconnu";
						$res['done'] = [
							'status' => 'failed',
							'info' => $info,
							'fatal' => true
						];
						throw new \Exception( $info, 1);
					}

					if ( !( in_array( 'sysop', $wgUser->getGroups() )
							|| MgwF::is_user_referent( $wgUser, $targetUser ) ) )
					{
						$info = "Vous n'avez pas les permissions nécessaires";
						$res['done'] = [
							'status' => 'failed',
							'info' => $info,
							'fatal' => false
						];
						throw new \Exception( $info, 1);
					}
					$status = $targetUser->sendConfirmationMail( 'mgw-create' );
					if ( $status->isOK() ) {
						$res['done'] = [
							'status' => 'done'
						];
					}
					else {
						$res['done'] = [
							'status' => 'failed',
							'info' => $status->getHTML(),
							'fatal' => true
						];
						throw new \Exception( $info, 1);
					}
					break;

				case 'groupe_archive':
					if ( !isset( $params['groupe_name'] ) ) {
						$info = "Le paramètre 'groupe_name' doit être défini";
						$res['done'] = [
							'status' => 'failed',
							'info' => $info,
							'fatal' => true
						];
						throw new \Exception( $info, 1);
					}
					if ( !isset( $params['archive'] ) ) {
						$info = "Le paramètre 'archive' doit être défini";
						$res['done'] = [
							'status' => 'failed',
							'info' => $info,
							'fatal' => true
						];
						throw new \Exception( $info, 1);
					}
					if ( !( in_array( 'sysop', $wgUser->getGroups() ) || MgwF::is_groupe_referent( $wgUser, $params['groupe_name'] ) ) ) {
						$info = "Vous n'avez pas les permissions nécessaires";
						$res['done'] = [
							'status' => 'failed',
							'info' => $info,
							'fatal' => false
						];
						throw new \Exception( $info, 1);
					}
					if ( $params['archive'] == 'do' ) {
						$archive = true;
						$mess = "archivé";
					}
					elseif ( $params['archive'] == 'undo' ) {
						$archive = false;
						$mess = "rétabli";
					}
					else {
						$info = "Le paramètre 'archive' doit être 'do' ou 'undo";
						$res['done'] = [
							'status' => 'failed',
							'info' => $info,
							'fatal' => true
						];
						throw new \Exception( $info, 1);
					}
					// do stuf...
					$status = MgwF::archive_groupe( $params['groupe_name'], $archive );
					 # enregistrement "blanc" de la page utilisateur pour réactualiser les données sémantiques
					PageF::edit( $wgUser->getUserPage(), 'réactualisation automatisée', $wgUser );

					$info = "Le groupe " . $params['groupe_name'] . " a bien été " . $mess .
						'. Il se peut que l\'affichage se mette à jour avec un certain délai.'.
						"\n\nSi votre tableau de bord n'affiche aucune modification, réactualisez vos informations personnelles.\n\n";
					if ( $status->done() ) {
						$res['done'] = [
							'status' => 'done',
							'info' => $info,
							'details' => $status->mess()
						];
					}
					else {
						$res['done'] = [
							'status' => 'failed',
							"info" => $status->mess(),
							"fatal" => true
						];
						throw new \Exception( $status->mess(), 1);
					}
					break;

        default:
          break;
      }
		} catch (\Exception $e) {
			if ( $res['done']['fatal'] ) {
				$res['done']['info'] .= ' Un administrateur a été prévenu.';
				MailF::bug( $e );
			}
		}

		# retour des valeurs
		if ( $res ) {
			foreach ( $res as $key => $value ) {
				$r->addValue( null, $key, $value );
			}
		}
  }

  protected function getAllowedParams() {
		return [
			'query' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'user_name' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'groupe_name' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'archive' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}
}
