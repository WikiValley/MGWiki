<?php
/**
 * Copyright © 2021 Wiki Valley
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * A module that sends a notification.
 *
 * @ingroup API
 */
class ApiMGWikiSendNotification extends ApiBase {

	public function execute() {

		# General config
		$config = $this->getConfig();
		$wgMGWikiUserProperties = $config->get( 'MGWikiUserProperties' );
		$wgMGWikiDefaultCreatorNewAccounts = $config->get( 'MGWikiDefaultCreatorNewAccounts' );
		$wgPasswordSender = $config->get( 'PasswordSender' );

		# Services
		$applicationFactory = \SMW\ApplicationFactory::getInstance();
		$store = $applicationFactory->getStore( 'SMW\SQLStore\SQLStore' );

		# Parameters
		$params = $this->extractRequestParams();
		$page = \Title::newFromText( $params['page'] );
		$studentUser = $this->getUser();
		$studentUserPage = $studentUser->getUserPage();

		# Check the page given in parameter does exist
		if( !$page->exists() ) {
			$this->dieWithError( 'apierror-mgwiki-page-does-not-exist' );
			return;
		}

		# Get the student’s user page
		if( !$studentUserPage->exists() ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->warn(
				'The student ' . $studentUserPage->getText() . ' has no user page.'
			);
			$this->dieWithError( 'apierror-mgwiki-no-user-page-for-student' );
			return;
		}

		# Get the referrer name from SMW property "Responsable référent" on the student’s page
		$complete = null;
		$studentProperties = MGWiki::collectSemanticData( [ $wgMGWikiUserProperties['referrer'] ], $store->getSemanticData( SMW\DIWikiPage::newFromTitle( $studentUserPage ) ), $complete );
		if( ! $studentProperties[$wgMGWikiUserProperties['referrer']] instanceof \Title ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'The referrer for ' . $studentUserPage->getText() . ' is not valid.'
			);
			$this->dieWithError( 'apierror-mgwiki-no-referrer-for-student' );
			return;
		}

		# Get the referrer and check the user does exist
		$referrerUser = \User::newFromName( $studentProperties[$wgMGWikiUserProperties['referrer']]->getText() );
		if( !$referrerUser->getId() ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'The referrer for ' . $studentUserPage->getText() . ' does not exist.'
			);
			$this->dieWithError( 'apierror-mgwiki-no-existing-referrer-for-student' );
			return;
		}

		# Get the referrer’s email
		$from = new \MailAddress( $wgPasswordSender, $this->msg( 'emailsender' )->inContentLanguage()->text() );
		$to = \MailAddress::newFromUser( $referrerUser );
		if( !$to->toString() ) {
			$subject = $this->msg( 'mgwiki-subject-email-notification-webmaster', $studentUser->getName() )->text();
			$to = \MailAddress::newFromUser( \User::newFromName( $wgMGWikiDefaultCreatorNewAccounts ) );
			$body = $this->msg( 'mgwiki-content-email-notification-webmaster', $studentUser->getName(), $referrerUser->getName() )->text();
			\UserMailer::send( $to, $from, $subject, $body );

			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'The referrer ' . $referrerUser->getName() . ' has no email.'
			);
			$this->dieWithError( 'apierror-mgwiki-referrer-has-no-email' );
			return;
		}

		# Send the email
		$subject = $this->msg( 'mgwiki-subject-email-notification', $studentUser->getName(), $page->getPrefixedText() )->text();
		$body = $this->msg( 'mgwiki-content-email-notification', $studentUser->getName(), $page->getPrefixedText() )->text();
		$status = \UserMailer::send( $to, $from, $subject, $body );

		# Check the email was sent
		if( !$status->isOK() ) {
			\MediaWiki\Logger\LoggerFactory::getInstance( 'mgwiki' )->error(
				'Error when sending email to referrer ' . $referrerUser->getName() . ': ' . $status->getWikiText( false, false, 'en' )
			);
			$this->dieWithError( 'apiwarn-mgwiki-error-when-sending-email' );
		}
	}

	public function getAllowedParams() {
		return [
			'page' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
		];
	}

	public function needsToken() {
		return 'csrf';
	}
}
