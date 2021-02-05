<?php
/**
 * MGWiki - add a button to request a review of the current page to the referrer of the student.
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWiki
 */

declare(strict_types=1);

class MGWikiSendNotification {

	/**
	 * Include the module ext.mgwiki.send-notification.
	 *
	 * Only on articles when viewing the current revision
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$context = $out->getContext();
		$config = $context->getConfig();
		$wgMGWikiSendNotificationNamespaces = $config->get( 'MGWikiSendNotificationNamespaces' );

		$title = $out->getTitle();
		$action = \Action::getActionName( $context );
		$revisionId = $out->getRevisionId();

		if( $out->isArticle() && in_array( $title->getNamespace(), $wgMGWikiSendNotificationNamespaces ) && $action === 'view' && ( $revisionId == 0 || $revisionId == $out->getTitle()->getLatestRevID() ) && $context->getRequest()->getVal( 'oldid' ) === null ) {
		#if( $out->isArticle() && $action === 'view' && $out->isRevisionCurrent() && $context->getRequest()->getVal( 'oldid' ) === null ) { // For 1.35+
			$out->addModules( 'ext.mgwiki.send-notification' );
		}
	}

	/**
	 * Add the link in the toolbox to send a notification to the referrer.
	 *
	 * Only on articles when viewing the current revision
	 *
	 * This hook is for 1.35+.
	 */
	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ) {
		$out = $skin->getOutput();
		$context = $out->getContext();
		$config = $context->getConfig();
		$wgMGWikiSendNotificationNamespaces = $config->get( 'MGWikiSendNotificationNamespaces' );

		$title = $out->getTitle();
		$action = \Action::getActionName( $context );

		if( $out->isArticle() && in_array( $title->getNamespace(), $wgMGWikiSendNotificationNamespaces ) && $action === 'view' && $out->isRevisionCurrent() && $context->getRequest()->getVal( 'oldid' ) === null ) {
			$sidebar['TOOLBOX'][] = [
				'msg' => 'mgwiki-toolbox-link',
				'href' => $title->getLocalURL( [ 'action' => 'send-notification' ] ),
				'class' => 'mgwiki-send-notification',
			];
		}
	}

	/**
	 * Add the link in the toolbox to send a notification to the referrer.
	 *
	 * Only on articles when viewing the current revision
	 *
	 * This hook is for 1.34-.
	 */
	public static function onBaseTemplateToolbox( $template, &$sidebar ) {
		$skin = $template->getSkin();
		$out = $skin->getOutput();
		$context = $out->getContext();
		$config = $context->getConfig();
		$wgMGWikiSendNotificationNamespaces = $config->get( 'MGWikiSendNotificationNamespaces' );

		$title = $out->getTitle();
		$action = \Action::getActionName( $context );
		$revisionId = $out->getRevisionId();

		if( $out->isArticle() && in_array( $title->getNamespace(), $wgMGWikiSendNotificationNamespaces ) && $action === 'view' && ( $revisionId == 0 || $revisionId == $out->getTitle()->getLatestRevID() ) && $context->getRequest()->getVal( 'oldid' ) === null ) {
			$sidebar['mgwiki-send-notification'] = [
				'msg' => 'mgwiki-toolbox-link',
				'href' => $title->getLocalURL( [ 'action' => 'send-notification' ] ),
				'class' => 'mgwiki-send-notification',
			];
		}
	}

	/**
	 * Send the notification.
	 *
	 * @param \IContextSource $context Context containing, between other things, the user and the title.
	 * @return \Status
	 */
	public static function doSendNotification( \IContextSource $context ) {
		return self::execute( $context, true );
	}

	/**
	 * Return recipients list.
	 *
	 * @param \IContextSource $context Context containing, between other things, the user and the title.
	 * @return array|\Status
	 */
	public static function getRecipientsList( $context ) {
		return self::execute( $context, false );
	}

	/**
	 * Send the notification or get the recipient.
	 *
	 * If there is an error, the status contains it.
	 *
	 * @param \IContextSource $context Context containing, between other things, the user and the title.
	 * @param bool $send send notif (true) or return recipient (false)
	 * @return array|\Status
	 */
	public static function execute( \IContextSource $context, $send = true ) {

		# General config
		$config = $context->getConfig();
		$wgMGWikiUserProperties = $config->get( 'MGWikiUserProperties' );
		$wgMGWikiDefaultCreatorNewAccounts = $config->get( 'MGWikiDefaultCreatorNewAccounts' );
		$wgPasswordSender = $config->get( 'PasswordSender' );

		# Services
		$store = \SMW\StoreFactory::getStore();

		# Parameters
		$title = $context->getTitle();
		$studentUser = $context->getUser();
		$studentUserPage = $studentUser->getUserPage();

		# Check the page given in parameter does exist
		if( !$title->exists() ) {
			return \Status::newFatal( 'apierror-mgwiki-page-does-not-exist' );
		}

		# Get the student’s user page
		if( !$studentUserPage->exists() ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->warning(
				'The student ' . $studentUserPage->getText() . ' has no user page.'
			);
			return \Status::newFatal( 'apierror-mgwiki-no-user-page-for-student' );
		}

		# Get the referrer name from SMW property "Responsable référent" on the student’s page
		$complete = null;
		$studentProperties = \MGWiki::collectSemanticData(
			[ $wgMGWikiUserProperties['referrer'] ],
			$store->getSemanticData( SMW\DIWikiPage::newFromTitle( $studentUserPage ) ),
			$complete
		);
		if( ! $studentProperties[$wgMGWikiUserProperties['referrer']] instanceof \Title ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'The referrer for ' . $studentUserPage->getText() . ' is not valid.'
			);
			return \Status::newFatal( 'apierror-mgwiki-no-referrer-for-student' );
		}

		# Get the referrer and check the user does exist
		$referrerUser = \User::newFromName( $studentProperties[$wgMGWikiUserProperties['referrer']]->getText() );
		if( !$referrerUser->getId() ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'The referrer for ' . $studentUserPage->getText() . ' does not exist.'
			);
			return \Status::newFatal( 'apierror-mgwiki-no-existing-referrer-for-student' );
		}

		# Renvoie le destinataire sans envoyer le message.
		if ( !$send ) {
			# (variable de retour au format array en prévision d'un choix multiple au futur)
			$recipients[] = [
				"user_id" => $referrerUser->getId(),
				"user_name" => $referrerUser->getName()
			];
			return $recipients;
		}

		# Get the referrer’s email
		$from = new \MailAddress( $wgPasswordSender, $context->msg( 'emailsender' )->inContentLanguage()->text() );
		$to = \MailAddress::newFromUser( $referrerUser );
		if( !$to->toString() ) {
			$subject = $context->msg( 'mgwiki-subject-email-notification-webmaster', $studentUser->getName() )->text();
			$to = \MailAddress::newFromUser( \User::newFromName( $wgMGWikiDefaultCreatorNewAccounts ) );
			$body = $context->msg( 'mgwiki-content-email-notification-webmaster', $studentUser->getName(), $referrerUser->getName() )->text();
			\UserMailer::send( $to, $from, $subject, $body );

			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'The referrer ' . $referrerUser->getName() . ' has no email.'
			);
			return \Status::newFatal( 'apierror-mgwiki-referrer-has-no-email' );
		}

		# Send the email
		$subject = $context->msg( 'mgwiki-subject-email-notification', $studentUser->getName() )->text();
		$body = $context->msg(
			'mgwiki-content-email-notification',
			$studentUser->getName(),
			$title->getPrefixedText(),
			$title->getFullURL() )->plain();
		$status = \UserMailer::send( $to, $from, $subject, $body, [ 'contentType' => 'text/html; charset=UTF-8' ] );

		# Check the email was sent
		if( !$status->isOK() ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'Error when sending email to referrer ' . $referrerUser->getName() . ': ' . $status->getWikiText( false, false, 'en' )
			);
			return \Status::newFatal( 'apiwarn-mgwiki-error-when-sending-email' );
		}

		return \Status::newGood();
	}
}
