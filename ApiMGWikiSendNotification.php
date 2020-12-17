<?php
/**
 * Copyright © 2019 Wiki Valley
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

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;

/**
 * A module that sends a notification.
 *
 * @ingroup API
 */
class ApiSendNotification extends ApiBase {
	public function execute() {

		# Get general config
		$config = $this->getConfig();
		$wgMGWikiUserProperties = $config->get( 'MGWikiUserProperties' );
		$wgMGWikiDefaultCreatorNewAccounts = $config->get( 'MGWikiDefaultCreatorNewAccounts' );
		$wgPasswordSender = $config->get( 'PasswordSender' );

		# Get parameters
		$params = $this->extractRequestParams();
		$apiResult = $this->getResult();

		$page = $params['page']; # TODO échapper ?
		$apprenant = $params['apprenant']; # TODO échapper ?

		$apprenantUser = \User::newFromName( $params['apprenant'] );

		# Formateur à récupérer depuis SMW propriété "Responsable référent" à partir de la page de l’apprenant
		$complete = null;
		$apprenantProperties = MGWiki::collectSemanticData( [ $wgMGWikiUserProperties['referrer'] ], $store->getSemanticData( SMW\DIWikiPage::newFromTitle( $apprenantUser->getUserPage() ) ), $complete );
		$referrerUser = \User::newFromName( $apprenantProperties[$wgMGWikiUserProperties['referrer']];

		$from = new \MailAddress( $wgPasswordSender, $this->msg( 'emailsender' )->inContentLanguage()->text() );
		$to = \MailAddress::newFromUser( $referrerUser );
		if( !$to ) {
			$subject = $this->msg( 'mgwiki-subject-email-notification-webmaster' )->text();
			$to = \MailAddress::newFromUser( \User::newFromName( $wgMGWikiDefaultCreatorNewAccounts ) );
			$body = [
				'text' => $this->msg( 'mgwiki-content-email-notification-webmaster', nom-référent = $apprenantUser->getName() )->text(),
			];
			\UserMailer::send( $to, $from, $subject, $body );

			$apiResult->addValue( null, "result", "noreferreremail" );
			return;
		}
		$subject = $this->msg( 'mgwiki-subject-email-notification' )->text();
		$body = [
			'text' => $this->msg( 'mgwiki-content-email-notification', page = $page,  )->text(),
		];
		\UserMailer::send( $to, $from, $subject, $body );

		$apiResult->addValue( null, "result", "success" );
	}

	public function mustBePosted() {
		return true;
	}

	public function isReadMode() {
		return false;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'page' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'apprenant' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	public function needsToken() {
		return true;
	}
}
