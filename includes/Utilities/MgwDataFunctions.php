<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\MGWikiDev\Classes\MGWStatus as Status;

/**
  * Ensemble de fonctions statiques pour gérer les données dans les tables mgw
  */
class MgwDataFunctions {


  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param array $select // sous la forme: [ 'champs SANS PREFIXE', 'valeur' ]
   * @param array $data // sous la forme: [ 'champs SANS PREFIXE' => 'valeur', 'page_id' => 5, etc. ]
   * @param int $updater_id
   * @return MGWStatus
   */
  public static function update_or_insert( $table, $select, $data, $updater_id ) {

    $update = self::update( $table, $select, $data, $updater_id );

    if ( $update->done() ) {
      return $update;
    }

    if ( $update->extra == MGW_DB_UNSET ) {
      $data = array_merge( $data, $select );
      return self::insert( $table, $data, $updater_id );
    }

    else {
      return $update;
    }
  }

  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param array $data // sous la forme: [ 'champs SANS PREFIXE' => 'valeur', 'page_id' => 5, etc. ]
   * @param int $updater_id
   * @return MGWStatus
   */
  public static function insert( $table, $data, $updater_id ) {

    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );

      $insert = [];
      foreach ( $data as $field => $value ) {
        $insert[ $table . '_' . $field ] = $value;
      }
      $insert[ $table . '_updater_id' ] = $updater_id;
      $insert[ $table . '_update_time' ] = $dbw->timestamp( date("Y-m-d H:i:s") );

      $dbw->insert(
        'mgw_' . $table,
        $insert
      );
    } catch (\Exception $e) {
      return Status::newFailed( $e, MGW_DB_ERROR );
    }
    return Status::newDone( 'Entrée ajoutée à la table mgw_' . $table, MGW_DB_INSERTED );
  }

  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param array $select // sous la forme: [ 'champs SANS PREFIXE' => 'valeur' ]
   * @param array $data // sous la forme: [ 'champs SANS PREFIXE' => 'valeur', 'page_id' => 5, etc. ]
   * @param int $updater_id
   * @return MGWStatus
   */
  public static function update( $table, $select, $data, $updater_id ) {

    global $wgMgwStringVars;

    $sel = array_keys( $select )[0];
    if ( in_array( $table . '_' . $sel, $wgMgwStringVars ) ) {
      $select[$sel] = "'" . $select[$sel] . "'";
    }

    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );
      $update = [];

      // $data = [] -> màj update_time et updater_id seulement
      if ( count( $data ) > 0 ) {

        // 1. on récupère les données actuelles
        $res = $dbw->select(
          'mgw_' . $table,
          [
            '*'
          ],
          $table . '_' . $sel .' = ' . $select[$sel]
        );
        if ( $res->result->num_rows < 1 ) {
          return Status::newFailed(
            'Aucune ligne ne correspond à la requête ' . $table . '_' . $sel . ' = ' . $select[$sel] .
              ' dans la table mgw_' . $table,
            MGW_DB_UNSET
          );
        }

        // 2. on vérifie la nouveauté des valeurs proposées
        $oldData = self::extractResData( $res );
        $oldData = $oldData[0];
        $same_data = true;

        foreach ( $data as $field => $value ) {
          if ( $oldData[ $table . '_' . $field ] != $value ) {
            $same_data = false;
            break;
          }
        }
        if ( $same_data ) {
          return Status::newDone(
            'Table mgw_' . $table . ' : ' . $sel . ' = ' . $select[$sel] . ' déjà à jour.',
            MGW_DB_UNCHANGED
          );
        }

        // 3. on archive les données actuelles
        $archive = self::archive( $table, $oldData, false, $updater_id );
        if ( ! $archive->done() ) {
          return $archive;
        }

        // 4. on met à jour la table
        $update = [];
        foreach ( $data as $field => $value ) {
          $update[ $table . '_' . $field ] = $value;
        }
      }
      $update[ $table . '_updater_id' ] = $updater_id;
      $update[ $table . '_update_time' ] = $dbw->timestamp( date("Y-m-d H:i:s") );

      foreach ( $update as $field => $value ) {
        if ( is_string( $value ) ) {
          $value = "'" . $value . "'";
        }
        $array[] = $field . ' = ' . $value;
      }
      $array = implode( ',', $array );

      $sql = 'UPDATE MGW_mgw_' . $table . ' SET ' . $array .
        ' WHERE ' . $table . '_' . $sel . ' = ' . $select[$sel] . ';';

      $dbw->query( $sql );

    } catch (\Exception $e) {
      return Status::newFailed( $e, MGW_DB_ERROR );
    }
    return Status::newDone( 'La table mgw_' . $table . ' a été mise à jour.', MGW_DB_UPDATED );
  }

  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param array $select // sous la forme: [ 'champs SANS PREFIXE' => 'valeur' ]
   * @param int $updater_id
   * @return MGWStatus
   */
  public static function delete( $table, $select, $updater_id ) {

    global $wgMgwStringVars;

    $sel = array_keys( $select )[0];
    if ( in_array( $table . '_' . $sel, $wgMgwStringVars ) ) {
      $select[$sel] = "'" . $select[$sel] . "'";
    }

    try {
      $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );
      $update = [];

      // 1. on récupère les données actuelles
      $res = $dbw->select(
        'mgw_' . $table,
        [
          '*'
        ],
        $table . '_' . $sel .' = ' . $select[$sel]
      );
      if ( $res->result->num_rows < 1 ) {
        return Status::newFailed(
          'Aucune ligne ne correspond à la requête ' . $table . '_' . $sel .' = ' . $select[$sel] .
            ' dans la table mgw_' . $table,
          MGW_DB_UNSET
        );
      }

      // 2. on archive les données actuelles
      $row = self::extractResData( $res );
      $row = $row[0];
      $archive = self::archive( $table, $row, true, $updater_id );
      if ( ! $archive->done() ) {
        return $archive;
      }

      // 4. on supprime la ligne de la table
      $dbw->delete( 'mgw_' . $table, $table . '_' . $sel . ' = ' . $select[$sel] );

    } catch (\Exception $e) {
      return Status::newFailed( $e, MGW_DB_ERROR );
    }
    return Status::newDone( 'La suppression a été effectuée dans la table mgw_' . $table, $row );
  }


  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param array $rowData // résultat d'une ligne de la requête SELECT * FROM mgw_$table
   * @param bool $droped // si l'archive conçerne une délétion
   * @param int $updater_id // obligatoire si l'archive conçerne une délétion
   * @return MGWStatus
   */
  public static function archive( $table, $rowData, $droped = false, $updater_id ) {

    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );

      if ( $droped && is_null( $updater_id ) ) {
        throw new \Exception('Erreur: $updater_id = null alors que $droped = true', 1);
      }

      if ( $droped ) {
        $rowData[ $table . '_drop_updater_id' ] = $updater_id;
        $rowData[ $table . '_drop_time' ] = $dbw->timestamp( date("Y-m-d H:i:s") );
      }

      $dbw->insert(
        'mgw_archive_' . $table,
        $rowData
      );
    } catch (\Exception $e) {
      return Status::newFailed( $e, MGW_DB_ERROR );
    }
    return Status::newDone( 'Table mgw_archive' . $table . 'mise à jour.', MGW_DB_INSERTED );
  }

  /**
   * Extraire la liste des résultats d'un objet IResultWrapper
   * Normaliser le format (string|int)
   * @param IResultWrapper $res
   * @return array
   */
  public static function extractResData( &$res, $prefix = '' ) {

    if ( $res->result->num_rows == 0 ) {
      return null;
    }

    global $wgMgwStringVars;
    $ret = [];
    $i = 0;
    while ( $i < $res->result->num_rows ) {
      $row = $res->fetchRow();
      $row = array_filter(
        $row,
        function( $k ) {
          return is_string( $k );
        },
        ARRAY_FILTER_USE_KEY
      );
      # on rétablit string vs/int
      foreach ( $row as $field => $value ) {
        if ( !in_array( $field, $wgMgwStringVars ) ) {
          $row[$field] = (int)$value;
        }
      }

      # on supprime le préfixe si demandé
      if ( !empty( $prefix ) ) {
        $prefix = str_replace( 'archive_', '', $prefix );
        foreach ( $row as $field => $value ) {
          if ( preg_match( '/'.$prefix.'/', $field ) > 0 ){
            $newfield = str_replace( $prefix . '_', '', $field );
            $row[$newfield] = $value;
            unset( $row[$field] );
          }
        }
      }

      $ret[] = $row;
      $i++;
    }
    return $ret;
  }

  /**
   * Requêtes dans les tables mgw.
   * !!! les noms sont donnés sans les préfixes.
   * @param string $table ex.: 'utilisateurs'
   * @param array $columns ex.: [ 'id', 'user_id', etc. ]
   * @param string $select ex.: 'nom = "Foo"'
   * @param array $opts ex.: [ 'ORDER BY' => 'nom DESC' ]
   *
   * @return array
   */
  public static function select_clean( $table, $columns, $select = '', $opts = [] ) {

    $prefix = str_replace( 'archive_', '', $table );

    // réimplémentation des préfixes
    foreach ( $columns as $key => $value ) {
      if ( $value != 'archive_id' ) {
        if ( preg_match( '/archive_id/', $select ) < 1 ) {
          $select = preg_replace( '/^' . $value . '/', $prefix . '_' . $value, $select );
        }
        foreach ( $opts as $kkey => $vvalue ) {
          $opts[$kkey] = preg_replace( '/^' . $value . '/', $prefix . '_' . $value, $vvalue );
        }
        $columns[$key] = $prefix . '_' . $value;
      }
    }
    $table_full = 'mgw_' . $table;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->select(
			$table_full,
			$columns,
			$select,
			__METHOD__,
			$opts
		);

		return self::extractResData( $res, $table );
  }

}
