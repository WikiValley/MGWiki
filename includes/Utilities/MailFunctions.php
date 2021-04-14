<?php

namespace MediaWiki\Extension\MGWiki\Utilities;

use UserMailer;
use MailAddress;

class MailFunctions {

	/**
	 * Envoi d'un mail formatté HTML
   * @param string $sender (e-mail)
   * @param string|array $recipients (e-mails)
	 * @param string $subject
   * @param string $body (HTML)
   * @return Status (mediawiki)
	 */
  public static function send ( $sender, $recipients, $subject, $body ) {

    $mail_from = new MailAddress( $sender );

    $mail_to = [];
    if ( !is_array( $recipients ) ) {
      $mail_to[] = new MailAddress( $recipients );
    }
    else {
      foreach ( $recipients as $email ) {
        $mail_to[] = new MailAddress( $email );
      }
    }

    $mailer = new UserMailer();

    return $mailer->send(
      $mail_to,
      $mail_from,
      $subject,
      $body,
      array( 'contentType' => 'text/html; charset=UTF-8' )
    );
  }

	/**
	 * Gestion des rapports de bug
	 * @param string $message le message public
   * @param string $e le rapport d'erreur envoyé par mail au responsable technique
   * @return string message destiné à l'affichage utilisateur
	 */
  public static function bug ( $message, $e = '' ) {
    global $wgMgwBugMailto; // liste des destinataires des notif bug à définir dans Localsettings.php
    global $wgUser;
    global $wgEmergencyContact;

    if ( empty( $e ) ) {
      $e = $message;
    }

    if ( !isset( $wgMgwBugMailto ) || !is_array( $wgMgwBugMailto ) || count( $wgMgwBugMailto ) == 0 ) {
      $wgMgwBugMailto = [ 'Webmaster' ];
    }

    $mail_to = [];
    foreach ( $wgMgwBugMailto as $userName ) {
      $user = \User::newFromName( $userName );
      if ( !is_null( $user ) ) {
        $mail_to[] = $user->getEmail();
      }
    }

    // send mail (rapport de bug)
    self::send(
      $wgEmergencyContact,
      $mail_to,
      wfMessage('mgw-bug-email-subject')->text(),
      wfMessage('mgw-bug-email-body', [ date('Y-m-d H:i:s'), $wgUser->getName(), $message . ': ' . $e ] )->parse()
    );

    // return public error message:
    return wfMessage('mgw-bug-message', [ $message ] )->parse();
  }
}
