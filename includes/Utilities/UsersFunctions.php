<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

use User;

/**
  * Fonctions sur les pages
  */
class UsersFunctions
{
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

  /**
   * @param int $id
   *
   * @return User object
   */
  public function getUserFromId ( $id ) {
    return User::newFromId ( $id );
  }
}
