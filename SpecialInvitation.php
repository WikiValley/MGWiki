<?php
/**
 * Implements Special:Invitation
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
 * @ingroup SpecialPage
 */

use MediaWiki\Auth\AuthManager;

/**
 * Special page allows users to request email confirmation message, and handles
 * processing of the confirmation code when the link in the email is followed
 *
 * @ingroup SpecialPage
 * @author Brion Vibber
 * @author Rob Church <robchur@gmail.com>
 */
#class Invitation extends AuthManagerSpecialPage {
class Invitation extends LoginSignupSpecialPage {
	public function __construct() {
		parent::__construct( 'Invitation', 'editmyprivateinfo' );
		#var_dump($this);
		$data = [];
		$data['emailtoken'] = '69e48e046e9d1afa99e297433a9c5ab5';
		$data[$this->getTokenName()] = $this->getToken()->toString();
		var_dump($this->getToken()->toString());
		$this->setRequest( $data, true );
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * Get the default action for this special page, if none is given via URL/POST data.
	 * Subclasses should override this (or override loadAuth() so this is never called).
	 * @param string $subPage Subpage of the special page.
	 * @return string an AuthManager::ACTION_* constant.
	 */
	protected function getDefaultAction( $subPage ) {
		return AuthManager::ACTION_LOGIN;
	}

	protected function getLoginSecurityLevel() {
		return false;
	}

	protected function isSignup() {
		return false;
	}

	/**
	 * @param bool $direct True if the action was successful just now; false if that happened
	 *    pre-redirection (so this handler was called already)
	 * @param StatusValue|null $extraMessages
	 * @return void
	 */
	protected function successfulAction( $direct = false, $extraMessages = null ) {
	}

	/**
	 * Logs to the authmanager-stats channel.
	 * @param bool $success
	 * @param string|null $status Error message key
	 */
	protected function logAuthResult( $success, $status = null ) {
	}

	/**
	 * Main execution point
	 *
	 * @param null|string $code Confirmation code passed to the page
	 * @throws PermissionsError
	 * @throws ReadOnlyError
	 * @throws UserNotLoggedIn
	 */
	function execute( $code ) {
		// Ignore things like master queries/connections on GET requests.
		// It's very convenient to just allow formless link usage.
		Profiler::instance()->getTransactionProfiler()->resetExpectations();

		$this->setHeaders();

		$this->checkReadOnly();
		$this->checkPermissions();

		// This could also let someone check the current email address, so
		// require both permissions.
		if ( !$this->getUser()->isAllowed( 'viewmyprivateinfo' ) ) {
			throw new PermissionsError( 'viewmyprivateinfo' );
		}

		$this->loadAuth( '', AuthManager::ACTION_LOGIN );
		var_dump($this->authAction);
		var_dump($this->authRequests);
		#var_dump( $this->trySubmit() );

		parent::execute( $code );
		
		if ( $code === null || $code === '' ) {
			$this->requireLogin( 'confirmemail_needlogin' );
		} else {
			$this->attemptConfirm( $code );
		}
	}

	/**
	 * Attempt to confirm the user's email address and show success or failure
	 * as needed; if successful, take the user to log in
	 *
	 * @param string $code Confirmation code
	 */
	function attemptConfirm( $code ) {

		# Confirm email
		$user = User::newFromConfirmationCode( $code, User::READ_LATEST );
		if ( !is_object( $user ) ) {
			$this->getOutput()->addWikiMsg( 'confirmemail_invalid' );

			return;
		}

		$user->confirmEmail();
		$user->saveSettings();

		# Connect the user
			var_dump('specialinvationok');
			#exit;
		

		$message = $this->getUser()->isLoggedIn() ? 'confirmemail_loggedin' : 'confirmemail_success';
		$this->getOutput()->addWikiMsg( $message );

		if ( !$this->getUser()->isLoggedIn() ) {
			$title = SpecialPage::getTitleFor( 'Userlogin' );
			$this->getOutput()->returnToMain( true, $title );
		}
	}
}
