<?php

class MGWikiBug {

	/**
	 * Gestion des rapports de bug
   *
	 * @param string $message le message public
   * @param string $e le rapport d'erreur
   *
   * @return string message destiné à l'affichage utilisateur
	 */
  public static function send ( $message, $e ) {

    global $wgMgwBugMailto; // liste des destinataires des notif bug à définir dans Localsettings.php
    global $wgUser; //

    if ( !isset( $wgMgwBugMailto ) || !is_array( $wgMgwBugMailto ) || count( $wgMgwBugMailto ) == 0 ) {
      $wgMgwBugMailto = ['Webmaster'];
    }

    $mail_to = [];
    foreach ( $wgMgwBugMailto as $userName ) {
      $user = \User::newFromName( $userName );
      if (!is_null( $user ) ) {
        $mail_to[] = new \MailAddress( $user->getEmail() );
      }
    }

    // send mail (rapport de bug)
    $mailer = new \UserMailer();
    $mail_from = new \MailAddress('mgwiki@univ-lyon1.fr');
    $body ='
Une erreur est survenue dans MGWiki.
Date: ' . date('Y-m-d H:i:s') . '
Utilisateur: ' . $wgUser->getName() . '
Rapport: ' . $e;

    $mailer->send( $mail_to, $mail_from, 'MGWiki: RAPPORT DE BUG', $body );

    // return public error message:
      return 'Une erreur est survenue: ' . $message . '.<br />Un administrateur a été prévenu, nous essayons de corriger ce problème dans les meilleurs délais.';
  }
}
