<?php

namespace MediaWiki\Extension\MGWiki\Utilities;

use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\MGWiki\Utilities\MGWStatus as Status;
use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;

/**
  * Ensemble de fonctions statiques pour gérer les données dans les tables mgw
  */
class DataFunctions {


  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param array $where // sous la forme: [ 'champs SANS PREFIXE' => [ '=', 'valeur'] ]
   * @param array $data // sous la forme: [ 'champs SANS PREFIXE' => 'valeur', 'page_id' => 5, etc. ]
   * @param int $updater_id
   * @param bool $anyway màj même si valeurs inchangées
   *
   * @return MGWStatus
   */
  public static function update_or_insert( $table, $where, $data, $updater_id, $anyway = false ) {

    if ( count( $where ) == 0 ) {
      return self::insert( $table, $data, $updater_id );
    }

    $update = self::update( $table, $where, $data, $updater_id, $anyway );

    if ( $update->done() ) {
      return $update;
    }

    if ( $update->extra == 'MGW_DB_UNSET' ) {
      $add = [];
      foreach ( $where as $field => $arr ) {
        $add[ $field ] = $arr[1];
      }
      $data = array_merge( $data, $add );
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
   * @param bool $return_id (default: false) retourne l'id de la nouvelle ligne
   *
   * @return MGWStatus
   */
  public static function insert( $table, $data, $updater_id, $return_id = false ) {

    $table_full = 'mgw_' . $table;

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
        $table_full,
        $insert
      );

      $extra = 'MGW_DB_INSERTED';

      # récupération de l'id de l'enregistrement
      if ( $return_id ) {
        $ret = self::select( $table, false, ['id'],
          [ 'updater_id' => [ '=', $updater_id ],
            'update_time' => [ '=', $insert[ $table . '_update_time' ] ] ]);
        $extra = $ret[0]['id'];
      }
    } catch (\Exception $e) {
      return Status::newFailed( $e, 'MGW_DB_ERROR' );
    }
    return Status::newDone(
      wfMessage( 'mgw-data-insert-success', [ $table_full ] )->text(),
      $extra
    );
  }

  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param bool $archive
   * @param array $where // sous la forme: [ 'champs SANS PREFIXE' => [ '=', 'valeur' ] ]
   * @param array $data // sous la forme: [ 'champs SANS PREFIXE' => 'valeur', 'page_id' => 5, etc. ]
   * @param int $updater_id
   * @param bool $anyway màj même si valeurs inchangées
   * @param bool $dry (default = false) si VRAI, pas de mise en archive
   *
   * @return MGWStatus
   */
  public static function update( $table, $where, $data, $updater_id, $anyway = false, $dry = false ) {

    global $wgDBprefix;
    $where = self::sanitize_where( $table, $where );
    $table_full = 'mgw_' . $table;

    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );
      $update = [];

      // $data = [] -> màj update_time et updater_id seulement
      if ( count( $data ) > 0 ) {

        // 1. on récupère les données actuelles
        if ( !$dry ) {
          $res = $dbw->select(
            $table_full,
            [
              '*'
            ],
            $where
          );
          if ( $res->result->num_rows < 1 ) {
            return Status::newFailed(
              wfMessage( 'mgw-data-empty-row', [ $where, $table_full ] )->text(),
              'MGW_DB_UNSET'
            );
          }
          $oldData = self::extractResData( $res );
          $oldData = $oldData[0];

          // 2. on vérifie la nouveauté des valeurs proposées
          if ( !$anyway ) {
            $same_data = true;
            foreach ( $data as $field => $value ) {
              if ( $oldData[ $table . '_' . $field ] != $value ) {
                $same_data = false;
                break;
              }
            }
            if ( $same_data ) {
              return Status::newDone(
                wfMessage( 'mgw-data-empty-update', [ $table_full, $where ] )->text(),
                'MGW_DB_UNCHANGED'
              );
            }
          }

          // 3. on archive les données actuelles
          $archive = self::archive( $table, $oldData, false, $updater_id );
          if ( ! $archive->done() ) {
            return $archive;
          }
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

      $sql = 'UPDATE ' . $wgDBprefix . $table_full . ' SET ' . $array .
        ' WHERE ' . $where . ';';

      $dbw->query( $sql );

    } catch (\Exception $e) {
      return Status::newFailed( $e, 'MGW_DB_ERROR' );
    }
    return Status::newDone(
      wfMessage( 'mgw-data-update-success', [ $table_full ] )->text(),
      'MGW_DB_UPDATED'
    );
  }

  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param bool $archive
   * @param array $where // sous la forme: [ 'champs SANS PREFIXE' => [ '=', 'valeur'] ]
   * @param int $updater_id
   * @return MGWStatus
   */
  public static function delete( $table, $where, $updater_id ) {

    // préparation des variables
    $where = self::sanitize_where( $table, $where );
    $table_full = 'mgw_' . $table;

    try {
      $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );
      $update = [];

      // 1. on récupère les données actuelles
      $res = $dbw->select(
        $table_full,
        [
          '*'
        ],
        $where
      );
      $rows = self::extractResData( $res );
      if ( is_null( $rows ) ) {
        return Status::newFailed(
          wfMessage( 'mgw-data-empty-row', [ $where, $table_full ] )->text(),
          'MGW_DB_UNSET'
        );
      }

      // 2. on archive les données actuelles
      $row = $rows[0];
      $archive = self::archive( $table, $row, true, $updater_id );
      if ( ! $archive->done() ) {
        return $archive;
      }

      // 4. on supprime la ligne de la table
      $dbw->delete( $table_full, $where );

    } catch (\Exception $e) {
      return Status::newFailed( $e, 'MGW_DB_ERROR' );
    }
    self::sanitize_row( $table, $row );
    return Status::newDone(
      wfMessage( 'mgw-data-delete-success', [ $table_full ] )->text(),
      $row
    );
  }


  /**
   * @param string $table // nom de la table sans préfixe ('utilisateur', 'groupe_membre', etc.)
   * @param array $rowData // résultat d'une ligne de la requête SELECT * FROM mgw_$table
   * @param bool $droped // si l'archive conçerne une délétion
   * @param int $updater_id // obligatoire si l'archive conçerne une délétion
   * @return MGWStatus
   */
  public static function archive( $table, $rowData, $droped = false, $updater_id ) {

    $table_full = 'mgw_' . $table;

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
        $table_full . '_archive',
        $rowData
      );
    } catch (\Exception $e) {
      return Status::newFailed( $e, 'MGW_DB_ERROR' );
    }
    return Status::newDone(
      wfMessage( 'mgw-data-update-success', [ $table_full . '_archive' ] )->text(),
      'MGW_DB_INSERTED'
    );
  }

  /**
   * Extraire la liste des résultats d'un objet IResultWrapper
   * Normaliser le format (string|int)
   * @param IResultWrapper $res
   * @return array
   */
  public static function extractResData( &$res, $prefix = '' ) {

    if ( $res->numRows() == 0 ) {
      return null;
    }

    $ret = [];
    $i = 0;
    while ( $i < $res->numRows() ) {
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
        PhpF::int( $row[$field], false, true );
        /*
        if ( !in_array( $field, wfMgwConfig( 'mgw-data', 'stringvars' ) ) ) {
          $row[$field] = (int)$value;
        }
        */
      }

      # on supprime le préfixe si demandé
      if ( !empty( $prefix ) ) {
        self::sanitize_row( $prefix, $row );
      }

      $ret[] = $row;
      $i++;
    }
    return $ret;
  }

  /**
   * Requêtes simples dans les tables mgw_.
   * !!! les noms sont donnés sans préfixe.
   * pour les requêtes complexes, utiliser query()
   *
   * @param string $table main part, i.e.: 'utilisateur'
   * @param bool $archive
   * @param array $columns ex.: [ 'id', 'user_id', etc. ]
   * @param string $where ex.: [ 'nom' => [ '=', 'Foo' ] ]
   * @param array $opts ex.: [ 'ORDER BY' => 'nom DESC' ]
   * @param bool $mgw table MGWiki ? (default true)
   *
   * @return array
   */
  public static function select( $table, $archive = false, $columns = [], $where = [], $opts = [], $mgw = true ) {

    if ( $mgw ) {
      $table_full = ( $archive ) ? 'mgw_' . $table . '_archive' : 'mgw_' . $table;
    }
    else $table_full = $table;

    // préparation des variables
    self::sanitize_columns( $table, $columns );
    $where = ( count( $where ) > 0 ) ? self::sanitize_where( $table, $where ) : '';
    if ( count( $opts ) > 0 ) {
      self::sanitize_opts( $table, $opts );
    }

    // requête
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->select(
			$table_full,
			$columns,
			$where,
			__METHOD__,
			$opts
		);

		return self::extractResData( $res, $table );
  }

  /**
   * @param string $sql
   * @param array $blob : blob type query
   * @return array|null
   */
  public static function plain_query( $sql ) {
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $lb->getConnectionRef( DB_REPLICA );
		$res = $dbr->query( $sql );
		return self::extractResData( $res );
  }

  /**
   * Requête brute
   * (permet d'accéder aux données blob)
   * @param string $sql
   * @param bool $empty_to_null
   *
   * @return array|null
   */
  public function mysqli_query( $sql, $empty_to_null = true, $void = false ) {
    global $wgDBserver, $wgDBname, $wgDBuser, $wgDBpassword;
    $res = mysqli_connect( $wgDBserver, $wgDBuser, $wgDBpassword, $wgDBname );
		$result = mysqli_query( $res, $sql ) or die ('Erreur avec la requête "' . $sql . '" : ' . $res->error );
    if ( !$void ) {
  		$total = mysqli_num_rows( $result );
  		if ( $total ) {
        $r = [];
  			while ( $row = mysqli_fetch_assoc( $result ) ) {
  				$r[] = $row;
  			}
        return $r;
  		}
      return ($empty_to_null) ? null : [];
    }
  }

  /**
   * @param string $table ex.: 'utilisateurs'
   * @param string $where ex.: [ 'nom' => [ '=', 'Foo' ] ]
   * @return void
   */
  public static function sanitize_where( $table, $where ) {
    $w = '';
    $i = 0;
    foreach ( $where as $field => $arr ) {
      # variables string entre guillemets
      if ( in_array( $table . '_' . $field, wfMgwConfig( 'mgw-data', 'stringvars' ) ) ) {
        $arr[1] = '"' . $arr[1] . '"';
      }
      # plusieurs conditions
      if ( $i > 0 ) {
        $w .= ' AND ';
      }
      $w .= $table . '_' . $field . ' ' . $arr[0] . ' ' . $arr[1];
      $i++;
    }
    return $w;
  }

  /**
   * @param string $table ex.: 'utilisateurs'
   * @param array $columns ex.: [ 'id', 'user_id', etc. ]
   * @return void
   */
  public static function sanitize_columns( $table, &$columns ) {
    if ( count( $columns ) == 0 ) {
      $columns = ['*'];
    }
    else {
      foreach ( $columns as $key => $field ) {
        if ( $field != '*' ){
          $columns[$key] = $table . '_' . $field;
        }
      }
    }
  }

  /**
   * @param string $table ex.: 'utilisateurs'
   * @param array $opts ex.: [ 'ORDER BY' => 'nom DESC' ]
   * @return void
   */
  public static function sanitize_opts( $table, &$opts ) {
    foreach ( $opts as $key => $value ) {
      if ( preg_match( '/^[a-z]/', $value) > 0 ) {
        $opts[$key] = preg_replace( '/^' . $value . '/', $table . '_' . $value, $value );
      }
    }
  }

  /**
   * @param string $table
   * @param array $row
   * @return void
   */
  public static function sanitize_row( $table, &$row ) {
    foreach ( $row as $field => $value ) {
      $newfield = str_replace( $table . '_', '', $field );
      $row[ $newfield ] = $value;
      unset( $row[$field] );
    }
  }

  /**
   * retourne les identifiants text_id et rev_id
   * à partir de rev_id ou de page_id + timestamp
   * @param int $rev_id
   * @param int $page_id
   * @param int $timestamp
   * @return int|array|null $text_id|['text_id' => int, 'rev_id' => int ]
   */
  public function getTextId( $rev_id = null, $page_id = null, $timestamp = null ) {

    global $wgDBprefix;

    // on recherche d'abord dans les pages actives
    if ( $rev_id ) {
      $where = ' rev_id = ' . $rev_id;
    }
    elseif ( $page_id && $timestamp ) {
      $where = ' rev_page = ' . $page_id .
        ' AND rev_timestamp < ' . ($timestamp + 2) .
        ' ORDER BY rev_id DESC LIMIT 1';
    }
    else {
      throw new \Exception("Erreur DataFunctions::getTextId : mauvais argument(s)" , 1);
    }
		$query = 'SELECT rev_text_id, rev_id FROM ' . $wgDBprefix . 'revision WHERE' . $where;
		$res = self::plain_query( $query );
    if ( $res ) {
      return $res[0]['rev_text_id'];
    }

		// si null, on recherche dans les archives
    if ( $rev_id ) {
      $where = ' ar_rev_id = ' . $rev_id;
    }
    elseif ( $page_id && $timestamp ) {
      $where = ' ar_page_id = ' . $page_id .
        ' AND ar_timestamp < ' . ($timestamp + 2) .
        ' ORDER BY ar_rev_id DESC LIMIT 1';
    }
    else {
      throw new \Exception("Erreur DataFunctions::getTextId : mauvais argument(s)" , 1);
    }
		$query = 'SELECT ar_text_id FROM ' . $wgDBprefix . 'archive WHERE' . $where;
		$res = self::plain_query( $query );
    if ( $res ) {
      return $res[0]['ar_text_id'];
    }

    // aucun résultat
    return null;
  }

  /**
   * @param string $needle
   * @param string $table
   * @param bool $mgw préfixe 'mgw_' ?
   * @param string $searchfield nom complet de la colonne de recherche
   * @param array $output ['field1', 'field2', ... ]
   * @param bool $ci true = case_insensitive
   *
   * @return array|null
   */
  public static function dbTextScreen_ci( $needle, $table, $mgw, $searchfield, $output, $ci = true ){
		global $wgDBprefix;
		if ($ci) $needle = strtolower($needle);
    $search = ($ci) ? "LCASE(CONVERT({$searchfield} USING utf8))" : $searchfield;
    $mgw = ( $mgw ) ? 'mgw_' : '';
    $output = implode(', ', $output);
		$sql = "SELECT {$output} FROM {$wgDBprefix}{$mgw}{$table} WHERE {$search} LIKE '{$needle}'";
    return self::mysqli_query($sql);
  }
}
