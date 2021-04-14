<?php
namespace MediaWiki\Extension\MGWiki\Modules\Notifications;
use MediaWiki\Extension\MGWiki\Modules\Notifications\MGWikiSendNotification as MgwSendNotif;
use ApiBase;
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

		$params = $this->extractRequestParams();
		$title = \Title::newFromText( $params['title'] );

		$context = new \DerivativeContext( $this->getContext() );
		$context->setTitle( $title );

		if ( $params['module'] == 'get' ) {
			// on retourne la liste des destinataires
			$r = $this->getResult();
			$status = MgwSendNotif::getRecipientsList( $context );
			if ( is_array( $status ) ) {
				foreach ( $status as $key => $value ) {
					$r->addValue( null, $key, $value );
				}
				return;
			}
		} else {
			$status = MgwSendNotif::doSendNotification( $context );
		}

		if( !$status->isGood() ) {
			$errors = $status->getErrors();
			if( count( $errors ) !== 1 ) {
				$this->dieWithError( 'unknown-error' );
				return;
			}
			$error = $errors[0];
			$message = $error['message'];
			if( $message instanceof \MessageSpecifier ) {
				$message = $message->getKey();
			}
			$this->dieWithError( $message );
		}
	}

	public function getAllowedParams() {
		return [
			'title' => [
				ApiBase::PARAM_TYPE => 'string',
				ApiBase::PARAM_REQUIRED => true,
			],
			'module' => [
				ApiBase::PARAM_TYPE => 'string'
			],
		];
	}

	public function needsToken() {
		return 'csrf';
	}
}
