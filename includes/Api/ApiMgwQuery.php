<?php
/**
 * API for internal mgw extension queries
 * http://localhost/wiki/api.php?action=mgw&query=list_users&string=ale
 */
namespace MediaWiki\Extension\MGWiki\Api;

use ApiBase;
use MediaWiki\Extension\MGWiki\Utilities\MgwFunctions as MgwF;
use MediaWiki\Extension\MGWiki\Utilities\DataFunctions as DbF;
use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;

class ApiMgwQuery extends ApiBase {

	public function execute( ) {
		global $wgDBprefix;
		global $wgUser;
    $params = $this->extractRequestParams();
		$r = $this->getResult();

		try {
			if ( !isset( $params['query'] ) ) {
				throw new \Exception("Erreur : le paramètre '&query=' doit être défini.");
			}

      switch ( $params['query'] ) {
        case 'user_id' :
          // requête sur la base de la table MGW_user, à affiner sur la base nom/prénom depuis MGW_mgw_utilisateur
          // compléter requête affinée sur les groupes
          $query = 'SELECT user_id as data, user_name as label '.
           'FROM ' . $wgDBprefix . 'user ';
          $query .=  ( isset( $params['string'] ) && !empty( $params['string'] ) )
						? 'WHERE user_name LIKE "' . strtolower( $params['string'] ) . '%" COLLATE latin1_general_ci '
						: '';
          $query .= 'ORDER BY user_name';
          $query .= ( isset( $params['limit'] ) )
            ? ' LIMIT ' . $params['limit']
						: '';
          $res = DbF::mysqli_query( $query );
	        break;

        case 'membres_id' :
          // requête sur la base de la table MGW_user, à affiner sur la base nom/prénom depuis MGW_mgw_utilisateur
          // compléter requête affinée sur les groupes
          $query = 'SELECT user_id as data, user_name as label '.
           'FROM ' . $wgDBprefix . 'user ';
          $query .=  ( isset( $params['string'] ) && !empty( $params['string'] ) )
						? 'WHERE user_name LIKE "' . strtolower( $params['string'] ) . '%" COLLATE latin1_general_ci '
						: '';
          $query .= 'ORDER BY user_name';
          $query .= ( isset( $params['limit'] ) )
            ? ' LIMIT ' . $params['limit']
						: '';
          $res = DbF::mysqli_query( $query );
          break;

				case 'archetypes':
					$query = 'SELECT archetype_id as data, archetype_nom as label '.
					 'FROM ' . $wgDBprefix . 'mgw_archetype ';
					$query .= ( isset( $params['string'] ) && !empty( $params['string'] ) )
						? 'WHERE archetype_nom LIKE "' . strtolower( $params['string'] ) . '%" COLLATE utf8_general_ci '
						: '';
					 'ORDER BY archetype_nom';
         	$query .= ( isset( $params['limit'] ) )
           	? ' LIMIT ' . $params['limit']
						: '';
					$res = DbF::mysqli_query( $query );
					break;

				case 'adherente':
					$res = [];
					foreach( wfMgwConfig( 'mgw-data','institution-type' ) as $data => $label ) {
						$res[] = [
							"data" => $data,
							"label" => $label
						];
					}
					break;

				case 'logo':
					$query = 'SELECT page_title as data, page_title as label '.
					 'FROM ' . $wgDBprefix . 'page WHERE page_namespace = 6 ';
					$query .= ( isset( $params['string'] ) && !empty( $params['string'] ) )
						? 'AND page_title LIKE "' . strtolower( $params['string'] ) . '%" COLLATE utf8_general_ci '
						: '';
					 'ORDER BY page_title';
					$query .= ( isset( $params['limit'] ) )
						? ' LIMIT ' . $params['limit']
						: '';
					$res = DbF::mysqli_query( $query );
					break;

				case 'institution_id':
					$sql = "SELECT institution_nom as label, institution_id as data " .
						"FROM {$wgDBprefix}mgw_institution WHERE institution_adherente = 1";
					$res = DbF::mysqli_query($sql);
					break;

				case 'archetype_id':
					$sql = "SELECT archetype_nom as label, archetype_id as data " .
						"FROM {$wgDBprefix}mgw_archetype " .
						"WHERE archetype_id IN (SELECT define_archetype_id FROM {$wgDBprefix}mgw_define " .
							"WHERE define_institution_id = {$params['institution_id']})";
					$res = DbF::mysqli_query($sql);
					break;

				case 'time_change':
					if ( $params['type'] == 'archetype' ) {
						$res = DbF::select('archetype', false, ['default_duration'],['id'=>['=',$params['data']]]);
						$n = $res[0]['default_duration'];
					}
					else $n = $params['data'];
					$res['new'] = PhpF::date(PhpF::tmstp_add( $params['timestamp'], MgwF::duration_read( (int)$n, true ) ) );

				case 'mw_groups':
					$res = [];
					$sql = "SELECT ug_group FROM {$wgDBprefix}user_groups GROUP BY ug_group ORDER BY ug_group";
					$req = DbF::mysqli_query( $sql, false );
					foreach ( $req as $row ) {
						$res[] = [
							"data" => $row['ug_group'],
							"label" => $row['ug_group']
						];
					}
					break;

				case 'membre_status':
					if ( !isset( $params['user_name'] ) ) {
						throw new \Exception("Le paramètre 'user_name' doit être défini", 1);
					}

					$sql = 'SELECT user_id, user_email_authenticated, user_email_token_expires FROM '
						. $wgDBprefix . 'user WHERE user_name LIKE "' . $params['user_name'] . '"';
					$req = DbF::mysqli_query( $sql );

					if ( $req ) {
						$req = $req[0];
						if ( $req['user_email_token_expires'] ) {
							$tmstp = wfTimestamp(TS_UNIX,(int)$req['user_email_token_expires']);
							$tmstp = strtotime('-7 days', $tmstp);
							$req['user_invite_time'] = date( 'd-m-Y à H:i:s', strtotime('-1 hour', $tmstp) );
						}
						else $req['user_invite_time'] = null;
					}
					else $req['user_id'] = 0;

					$res = $req;
					break;

        default:
          break;
      }

			# retour des valeurs
			if ( $res ) {
				foreach ( $res as $key => $value ) {
					$r->addValue( null, $key, $value );
				}
			}
		} catch (\Exception $e) {
			$r->addValue( null, "erreur", $e );
		}
  }

  protected function getAllowedParams() {
		return [
			'query' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'string' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'level' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'limit' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'institution_id' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'archetype_id' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'user_id' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'user_name' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'mw_groups' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'data' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'type' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'timestamp' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}
}
