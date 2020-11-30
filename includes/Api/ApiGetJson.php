<?php
/**
 * API to provide json content without 'read' permission
 */
namespace MediaWiki\Extension\MGWikiDev\Api;

use ApiCrossWiki;
use MediaWiki\Extension\MGWikiDev\Utilities\GetJsonPage;

class ApiGetJson extends \ApiBase {

	public function execute( ) {
		global $IP;
    $params = $this->extractRequestParams();
		$r = $this->getResult();

		try {
			if ( !isset( $params['service'] ) ) {
				throw new \Exception("Erreur : service non défini.");
			}

			$Json = new GetJsonPage($params['service']);

			# arguments 'key' et 'subkeys': retourne la 1ère valeur trouvée
			if ( isset( $params['key'] ) && isset( $params['parents'] ) ) {
				$data = $Json->getSubData( $params['key'], $params['parents'] );
			}
			elseif ( isset( $params['key'] ) ) {
				$data = $Json->getSubData( $params['key'] );
			}

			# arguments 'merge' et 'parents': aggrégation de toutes les valeurs trouvées
			elseif ( isset( $params['merge'] ) && isset( $params['parents'] ) ) {
				$data = $Json->mergeSubData( $params['merge'], $params['parents'] );
			}
			elseif ( isset( $params['merge'] ) ) {
				$data = $Json->mergeSubData( $params['merge'] );
			}

			# absence d'argument: retourne la totalité du contenu
			else {
				$data = $Json->getFullData();
			}

			# retour des valeurs
			if ( !is_null($data) ) {
				foreach ( $data as $key => $value ) {
					$r->addValue( null, $key, $value );
				}
			}
		} catch (\Exception $e) {
			$r->addValue( null, "erreur", $e );
		}
  }

  protected function getAllowedParams() {
		return [
			'service' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'key' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'merge' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'parents' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_ISMULTI => true,
			]
		];
	}
}
