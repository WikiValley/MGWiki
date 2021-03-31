<?php

namespace MediaWiki\Extension\MGWiki\Utilities;

use User;
use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\MGWiki\Utilities\DataFunctions as DbF;
use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWiki\Utilities\MGWStatus as Status;

/**
  * Fonctions sur les utilisateurs
  */
class UsersFunctions
{

  /**
   * l'utilisateur a-t-il confirmé son compte ?
   * @param User|string|int $target
   * @param array &$data = []
   * @return bool|null (null = utilisateur inexistant)
   */
  public function user_status( $target, &$data = [] ) {
    global $wgDBprefix;
    $target = self::getUserFromAny( $target );
    if ( !$target ) {
      return null;
    }

    # informations sur l'invitation & l'authentification email
		$sql = 'SELECT user_id, user_email_authenticated, user_email_token_expires FROM '
			. $wgDBprefix . 'user WHERE user_id = ' . $target->getId();
		$req = DbF::mysqli_query( $sql );

		if ( $req ) {
      $data['email_authenticated'] = (int)$req[0]['user_email_authenticated'];
			if ( $req[0]['user_email_token_expires'] ) {
				$data['last_invite'] = wfTimestamp( TS_UNIX, (int)$req[0]['user_email_token_expires'] );
				$data['last_invite'] = strtotime('-7 days', $data['last_invite'] );
        $data['last_invite'] = strtotime('-1 hour', $data['last_invite'] );
			}
			else $data['last_invite'] = null;
		}
		else return null;

    $data[ 'first_edit' ] = $target->getFirstEditTimestamp();
    $data[ 'last_edit' ] = $target->getLatestEditTimestamp();
    $data[ 'user_id' ] = $target->getId();

    if ( !$data['email_authenticated'] && !$data[ 'first_edit' ] )
      $data['user_status'] = MGW_NEVERCONFIRMED; // -1
    elseif ( $data['email_authenticated'] && $data[ 'first_edit' ] )
      $data['user_status'] = MGW_CONFIRMED; // 0
    elseif ( !$data['email_authenticated'] && $data[ 'first_edit' ] )
      $data['user_status'] = MGW_UNCONFIRMED; // 1
    else
      $data['user_status'] = MGW_NOEDITS; // 2

    return (bool)$data['email_authenticated'];
  }

  /**
   * @param User|string|int $target
   * @param bool $allow_new = false (return null if user is new)
   *
   * @return User|null
   */
  public function getUserFromAny ( $target, $allow_new = false ) {
    if ( is_int( $target ) ) {
      $target = User::newFromId( $target );
    }
    elseif ( is_string( $target ) ) {
      $target = User::newFromName( $target );
    }
    elseif ( !( $target instanceof User ) ) {
      return null;
    }

    if ( !$target || (!$allow_new && $target->getId() < 1) ) {
      return null;
    }
    return $target;
  }

  /**
   * @param string $nom
   * @param string $prenom
   * @param bool $check
   *
   * @return User|null
   */
  public function getUserFromNames ( $prenom, $nom = null, $check = false ) {
    if ( ! is_null( $nom ) ) {
      $name = $prenom . ' ' . strtoupper( $nom );
    }
    else $name = $prenom;
    $user = User::newFromName ($name);
    if ( $check && $user->getId() <= 0 ) return null;
    else return $user;
  }

  public function countUsersWithName ( $name ) {
    global $wgDBprefix;
    $sql = "SELECT user_name FROM {$wgDBprefix}user WHERE user_name LIKE '{$name}%'";
    $res = DbF::mysqli_query($sql, false);
    return count($res);
  }

  /**
   * @param string $name
   * @param bool $check (retourne null si inexistant, 0 sinon)
   * @return int|null
   */
  public function getUserIdFromName ( $name, $check = false ) {
    $user = User::newFromName ($name);
    if ( $check && $user->getId() <= 0 ) return null;
    else return $user->getId();
  }

  /**
   * @param int $id
   *
   * @return User object
   */
  public function getUserFromId ( $id ) {
    return User::newFromId ( $id );
  }

  /**
   * @param int $id
   *
   * @return string
   */
  public function getUserNameFromId ( $id ) {
    $user = User::newFromId ( $id );
    return $user->getName();
  }


  /**
   * Vérification de l'existence de l'utilisateur dans la base MediaWiki
   * retourne le nom d'utilisateur s'il existe
   *
   * @param string|int $val user_name|user_id
   * @param bool $case_sensitive
   * @return string|bool user_name|false
   */
  public function userExists( $val, $case_sensitive = false ) {

    if ( is_int( $val ) || preg_match( '/^[0-9]+$/', $val ) > 0 ) {
      if ( !is_int( $val ) ) $val = (int)$val;
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbr = $lb->getConnectionRef( DB_REPLICA );
      $res = $dbr->select( 'user', [ 'user_name' ], 'user_id = ' . $val );
      if ( $res->numRows() > 0 ) {
        $row = $res->fetchRow();
        return $row['user_name'];
      }
      return false;
    }

    if ( $case_sensitive ) {
      $user = User::newFromName ( $val );
      if ( $user->getId() > 0 ) return $val;
      else return false;
    }

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $lb->getConnectionRef( DB_REPLICA );
    $res = $dbr->select( 'user', [ 'user_name' ] );
    if ( $res->numRows() > 0 ) {
      foreach ( $res as $row ) {
        if ( PhpF::sansAccent( strtolower( $val ) ) == PhpF::sansAccent( strtolower( $row->user_name ) ) )
         return $row->user_name;
      }
    }

    return false;
  }

  /**
   * recherche l'existence de noms semblables
   * @return array
   */
  public static function userNamesLike( $prenom, $nom ) {
    global $wgDBprefix;

    $prenom = explode( ' ', str_replace( ['-','_','.',',',':','/','\\'], ' ', PhpF::sansAccent( strtolower( $prenom ) ) ) );
    $nom = explode( ' ', str_replace( ['-','_','.',',',':','/','\\'], ' ', PhpF::sansAccent( strtolower( $nom ) ) ) );

    $i = 0;
    foreach( $prenom as $key => $sub ) {
      $prenom[$key] = '(' . $sub . ')';
      if ( $i > 0 ) $prenom[$key] .= '?';
      $i++;
    }
    $i = 0;
    foreach( $nom as $key => $sub ) {
      $nom[$key] = '(' . $sub . ')';
      if ( $i > 0 ) $nom[$key] .= '?';
      $i++;
    }

    $screen = array_merge($prenom, $nom);
    $screen = implode('.*',$screen );

    $sql = "SELECT user_id, user_name FROM {$wgDBprefix}user";
    $res = DbF::mysqli_query( $sql );

    $return = [];
    if ( $res ) {
      foreach ( $res as $row ) {
        $string = str_replace( ['-','_','.',',',':','/','\\'], ' ', PhpF::sansAccent( strtolower( $row['user_name'] ) ) );
        if ( preg_match( "/.*{$screen}.*/", $string ) > 0 ) {
          $row['user_id'] = (int)$row['user_id'];
          $return[] = $row;
        }
      }
    }
    return $return;
  }

  /**
   * Vérification de l'existence de l'utilisateur dans les tables mgw
   * retourne 'Prénom NOM' s'il existe
   *
   * @param array|string|int $val nom,prenom|user_name|user_id
   * @param bool $case_sensitive
   * @return string|bool user_name|false
   */
  public function mgwUserExists( $mode, $val, $case_sensitive = false ) {

    switch ( $mode ) {
      case 'user_id':
        $lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
        $dbr = $lb->getConnectionRef( DB_REPLICA );
        $res = $dbr->select( 'mgw_utilisateur', [ 'utilisateur_id' ], 'utilisateur_user_id = ' . $val );
        if ( $res->numRows() > 0 ) {
          return true;
        }
        return false;
        break;

      default :
        return false;
        break;
    }
  }

  /**
   * Vérification de l'existence du mail
   *
   * @param string $email
   * @return array [ ['user_id'=> (int), 'user_name'=> (string)] ]
   */
  public function emailExists( $email ) {
    global $wgDBprefix;
    $sql = "SELECT user_id, user_name FROM {$wgDBprefix}user WHERE user_email LIKE '{$email}' ORDER BY user_id";
    $res = DbF::mysqli_query( $sql );
    if ( $res ) {
      foreach( $res as $row )
        $row['user_id'] = (int)$row['user_id'];
      return $res;
    }
    return [];
  }

  ////////////////////////////////////////////////////////////////////////////////
  // FONCTIONS A SUPPRIMER (-> MgwDataFonctions )
  // ...

  /**
   * Cas d'usage: demande de suppression de compte.
   * $fullDeletion = true implique un effaçement complet de l'utilisateur
   * y compris dans les tables d'archive.
   *
   * @param int $user_id
   * @param bool $fullDeletion
   * @param int updater_id
   * @return array [ 'done' => bool, 'message' => string ]
   */
  public function deleteMGWUser( $user_id, $fullDeletion = false, $updater_id = null ) {

    if ( is_null($updater_id) ) {
      global $wgUser;
      $updater_id = $wgUser->getId();
    }

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbw = $lb->getConnectionRef( DB_MASTER );
    $res = $dbw->select(
      'mgw_utilisateur',
      [
        'utilisateur_user_id'
      ],
      'utilisateur_user_id = ' . $user_id
    );

    if ( sizeof( $res < 1 ) ) {
      return [
        'done' => false,
        'message' => 'Suppression impossible: l\'utilisateur ' . $user_id .
          ' n\'existe pas dans la table utilisateurs de mgwiki.'
      ];
    }
    elseif ( sizeof( $res > 1 ) ) {
      return [
        'done' => false,
        'message' => 'Suppression impossible: : l\'utilisateur ' . $user_id . ' figure ' .
          sizeof( $res ) . ' fois dans la table mgw_utilisateur. <br>Veuillez contacter le responsable technique.'
      ];
    }

    # suppression simple: on archive avec les champs
    # utilisateur_drop_updater_id et utilisateur_drop_time renseignés
    if ( !$fullDeletion ) {
      $archive = self::archiveMGWUser(
        $res[0]->utilisateur_id,
        $res[0]->utilisateur_user_id,
      	$res[0]->utilisateur_nom,
      	$res[0]->utilisateur_prenom,
        $res[0]->utilisateur_level,
        $res[0]->utilisateur_updater_id,
        $res[0]->utilisateur_update_time,
        $updater_id,
        date('Y-m-d H:i:s')
      );
    }
    # suppression complète: on vide les archives de toutes les lignes mentionnant l'utilisateur
    else $archive = self::dropUserFromArchive( $res[0]->utilisateur_id, $user_id, $updater_id );

    $drop = self::dropMGWUser( $user_id );

    $username = $res[0]->utilisateur_prenom . ' ' . $res[0]->utilisateur_nom;

    if ( is_bool( $drop ) && is_bool( $archive ) && !$fullDeletion ) {
      $done = $drop;
      $message = $username . 'a été retiré des utilisateurs. Les archives n\'ont pas été effaçées.';
    }
    elseif ( is_bool( $drop ) && is_bool( $archive ) && $fullDeletion ) {
      $done = $drop;
      $message = $username . 'a été retiré des utilisateurs ainsi que des archives.';
    }
    else {
      $done = false;
      $message = 'Erreur à la suppression de ' . $username . ' :';
      if ( !is_bool( $archive ) ) $message .= '<br>' . $archive;
      if ( !is_bool( $update ) ) $message .= '<br>' . $archive;
    }
    return [ 'done' => $done, 'message' => $message ];
  }

  /**
   * @param int $id
   * @param string $user_nom
   * @param string $user_prenom
   * @param int $updater_id
   * @return MGWStatus
   */
  public static function insertMGWUser( $user_id, $user_nom, $user_prenom, $updater_id ) {
    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );
      $dbw->insert(
        'mgw_utilisateur',
        [
          'utilisateur_user_id' => $user_id,
          'utilisateur_nom' => $user_nom,
          'utilisateur_prenom' => $user_prenom,
          'utilisateur_updater_id' => $updater_id,
          'utilisateur_update_time' => $dbw->timestamp( date("Y-m-d H:i:s") )
        ]
      );
    } catch (\Exception $e) {
      return Status::newFailed( $e );
    }
    return Status::newDone( $user_prenom . ' ' . $user_nom . ' a été ajouté à la table mgw_utilisateur.');
  }

  /**
   * @param int $id
   * @param array $data
   * @return bool
   */
  public static function updateMGWUser( $user_id, $user_nom, $user_prenom, $updater_id ) {
    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );

      $res = $dbw->select(
        'mgw_utilisateur',
        [
          '*'
        ],
        'utilisateur_user_id = ' . $user_id
      );

      if ( $res->result->num_rows < 1 ) {
        return Status::newFailed( 'Utilisateur inconnu dans la table mgw_utilisateur');
      }

      $row = $res->fetchRow();

      if ( $row['utilisateur_nom'] == $user_nom && $row['utilisateur_prenom'] == $user_prenom ) {
        return Status::newFailed( 'Table mgw_utilisateur déjà à jour.');
      }

      $archive = self::archiveMGWUser(
        $row['utilisateur_id'],
        $row['utilisateur_user_id'],
        $row['utilisateur_nom'],
        $row['utilisateur_prenom'],
        $row['utilisateur_level'],
        $row['utilisateur_updater_id'],
        $row['utilisateur_update_time']
      );

      if ( ! $archive->done() ) {
        return $archive;
      }

      $dbw->update(
        'mgw_utilisateur',
        [
          'utilisateur_nom' => $user_nom,
          'utilisateur_prenom' => $user_prenom,
          'utilisateur_updater_id' => $updater_id,
          'utilisateur_update_time' => $dbw->timestamp( date("Y-m-d H:i:s") )
        ],
        'utilisateur_user_id = ' . $user_id
      );
    } catch (\Exception $e) {
      return Status::newFailed( $e );
    }
    return Status::newDone( $user_prenom . ' ' . $user_nom . ' a été mis à jour dans la table mgw_utilisateur.');
  }


  /**
   * @param int $id
   * @param string $user_nom
   * @param string $user_prenom
   * @param int $updater_id
   * @return bool|string
   */
  public static function archiveMGWUser(
      $utilisateur_id,
      $utilisateur_user_id,
      $utilisateur_nom,
      $utilisateur_prenom,
      $utilisateur_level,
      $utilisateur_updater_id,
      $utilisateur_update_time,
      $utilisateur_drop_updater_id = null
    ) {
    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );

      if ( !is_null( $utilisateur_drop_updater_id ) ) {
        $utilisateur_drop_time = $dbw->timestamp( date("Y-m-d H:i:s") );
      } else {
        $utilisateur_drop_time = null;
      }
      $dbw->insert(
        'mgw_utilisateurs_archive',
        [
          'utilisateur_id' =>  $utilisateur_id,
          'utilisateur_user_id' =>  $utilisateur_user_id,
          'utilisateur_nom' =>	$utilisateur_nom,
          'utilisateur_prenom' =>	$utilisateur_prenom,
          'utilisateur_level' =>  $utilisateur_level,
          'utilisateur_updater_id' => $utilisateur_updater_id,
          'utilisateur_update_time' =>  $utilisateur_update_time,
          'utilisateur_drop_updater_id' => $utilisateur_drop_updater_id,
          'utilisateur_drop_time' => $utilisateur_drop_time
        ]
      );
    } catch (\Exception $e) {
      return Status::newFailed( $e );
    }
    return Status::newDone( 'Archive: ok' );
  }


  /**
   * @param int $id
   * @return bool
   */
  public static function dropMGWUser( $user_id, $updater_id ) {
    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );

      $res = $dbw->select(
        'mgw_utilisateur',
        [
          '*'
        ],
        'utilisateur_user_id = ' . $user_id
      );

      if ( $res->result->num_rows < 1 ) {
        return Status::newFailed( 'Utilisateur inconnu dans la table mgw_utilisateur');
      }

      $row = $res->fetchRow();
      $archive = self::archiveMGWUser(
        $row['utilisateur_id'],
        $row['utilisateur_user_id'],
        $row['utilisateur_nom'],
        $row['utilisateur_prenom'],
        $row['utilisateur_level'],
        $row['utilisateur_updater_id'],
        $row['utilisateur_update_time'],
        $updater_id
      );

      if ( ! $archive->done() ) {
        return $archive;
      }

      $dbw->delete(
        'mgw_utilisateur',
        'utilisateur_user_id = ' . $user_id
      );
    } catch (\Exception $e) {
      return Status::newFailed( $e );
    }
    return Status::newDone( 'l\'utilisateur' . $user_id . ' a été supprimé.' );
  }

  /**
   * Suppression complète: on ne laisse que la trace de la suppression elle-même.
   *
   * @param int $utilisateur_id,
   * @param int $utilisateur_user_id,
   * @param int $utilisateur_updater_id
   * @return bool
   */
  private function dropUserFromArchive( $utilisateur_id, $utilisateur_user_id, $utilisateur_updater_id ) {
    try {
  		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
      $dbw = $lb->getConnectionRef( DB_MASTER );
      $dbw->delete(
        'mgw_utilisateurs_archive',
        'utilisateur_user_id = ' . $utilisateur_user_id
      );
      $dbw->insert(
        'mgw_utilisateurs_archive',
        [
          'utilisateur_id' =>  $utilisateur_id,
          'utilisateur_user_id' =>  $utilisateur_user_id,
          'utilisateur_nom' =>	'compte supprimé',
          'utilisateur_prenom' =>	'comte supprimé',
          'utilisateur_level' =>  null,
          'utilisateur_updater_id' => $utilisateur_updater_id,
          'utilisateur_update_time' =>  date('Y-m-d H:i:s'),
          'utilisateur_drop_updater_id' => $utilisateur_updater_id,
          'utilisateur_drop_time' => date('Y-m-d H:i:s')
        ]
      );
    } catch (\Exception $e) {
      return $e;
    }
    return true;
  }

  /**
   * @param int $id
   * @return int
   */
  public function getLevel( $id ) {
  }
}
