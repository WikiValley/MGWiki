<?php
/**
 * MGWiki - add a button to request a review of the current page to the referrer of the student.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWiki
 *
 * @file
 * @ingroup Actions
 */

/**
 * MGWiki - add a button to request a review of the current page to the referrer of the student.
 *
 * @ingroup Actions
 */
class MGWikiSendNotificationAction extends FormAction {

	public function getName() {
		return 'send-notification';
	}

	protected function getDescription() {
		return '';
	}

	public function onSubmit( $data ) {
		return \MGWikiSendNotification::doSendNotification( $this->getContext() );
	}

	protected function checkCanExecute( User $user ) {
		// Must be logged in
		if ( $user->isAnon() ) {
			throw new UserNotLoggedIn( 'mgwiki-send-notification-anon-text', 'mgwiki-send-notification-no-login' );
		}

		parent::checkCanExecute( $user );
	}

	protected function usesOOUI() {
		return true;
	}

	protected function getFormFields() {
		return [
			'intro' => [
				'type' => 'info',
				'vertical-label' => true,
				'raw' => true,
				'default' => $this->msg( 'mgwiki-send-notification-confirm-top' )->parse()
			]
		];
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setWrapperLegendMsg( 'mgwiki-send-notification-confirm-title' );
		$form->setSubmitTextMsg( 'mgwiki-send-notification-confirm-button' );
	}

	public function onSuccess() {
		$this->getOutput()->addWikiMsg( 'mgwiki-send-notification-success' );
	}
}
