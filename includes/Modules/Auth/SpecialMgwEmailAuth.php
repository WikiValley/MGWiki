<?php
/**
 * MGWiki
 * Implements Special:MgwEmailAuth
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 */

namespace MediaWiki\Extension\MGWiki\Modules\Auth;

use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AuthenticationResponse;
use MediaWiki\Auth\CreatedAccountAuthenticationRequest;

/**
 * Special page allows users to request email confirmation message, and handles
 * processing of the confirmation code when the link in the email is followed
 *
 * Derived from SpecialConfirmemail.php with heavy customisations.
 * @author Brion Vibber
 * @author Rob Church <robchur@gmail.com>
 */
#class Invitation extends AuthManagerSpecialPage {
class SpecialMgwEmailAuth extends \LoginSignupSpecialPage {
	public function __construct() {
		parent::__construct( 'MgwEmailAuth' );
		$this->setListed( false );
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
		return AuthManager::ACTION_LOGIN_CONTINUE;
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

	protected function beforeExecute( $subPage ) {
		$data = [];
		$data['emailtoken'] = $subPage;
		$data[$this->getTokenName()] = $this->getToken()->toString();
		$this->setRequest( $data, true );
		parent::beforeExecute( $subPage );
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
		\Profiler::instance()->getTransactionProfiler()->resetExpectations();
		#$authManager = AuthManager::singleton();

		$this->setHeaders();

		$this->checkReadOnly();
		$this->checkPermissions();

		# Check the code has a good format
		if( !$code || !preg_match( '/^[0-9a-f]{32}$/', $code ) ) {
			$this->getOutput()->addWikiMsg( 'mgwiki-bad-email-token' );
			$this->getOutput()->returnToMain();
			return;
		}
		// This could also let someone check the current email address, so
		// require both permissions.
		#if ( !$this->getUser()->isAllowed( 'viewmyprivateinfo' ) ) {
		#	throw new PermissionsError( 'viewmyprivateinfo' );
		#}

		$this->loadAuth( '', AuthManager::ACTION_LOGIN );
		#var_dump($this->authAction);
		#var_dump($this->authRequests);
		$status = $this->trySubmit();
		#var_dump($this->authRequests);

		$response = $status->getValue();
		#var_dump($status);
		#var_dump($response);
		switch( $response->status ) {
			case AuthenticationResponse::PASS:
				# Update session data to immediately connect the user
				$this->setSessionUserForCurrentRequest();

				# Confirm the email (it was the authentication token)
				$user = \User::newFromName( $response->username );
				$user->confirmEmail();
				$user->saveSettings();

				$this->successfulAction( true );
				$this->getOutput()->redirect( \SpecialPage::getSafeTitleFor( 'MgwChangePassword' )->getFullURL() );
				break;

			case AuthenticationResponse::FAIL:
				$this->getOutput()->addWikiMsg( 'mgwiki-bad-email-token' );
				$this->getOutput()->returnToMain();
				break;
			default:
				throw new LogicException( 'invalid AuthenticationResponse' );
		}
		#if( $response->status == AuthenticationResponse::PASS ) {
			#foreach( $this->authRequests as &$req ) {
			#	if( $req instanceof MediaWiki\Extension\MGWiki\Modules\Auth\EmailTokenAuthenticationRequest ) {
			#		$req->username = $response->username;
			#	}
			#}
			#$returnToUrl = $this->getPageTitle( 'return' )
			#	->getFullURL( $this->getPreservedParams( true ), false, PROTO_HTTPS );
			#$name = $response->username;
			#$id = User::newFromName( $name );
			#$reqCreation = new CreatedAccountAuthenticationRequest( $id, $name );
			#var_dump($this->authRequests);
			#$response2 = $authManager->beginAuthentication( $this->authRequests, $returnToUrl );
			/*if( $response2->status == AuthenticationResponse::PASS )
				var_dump('aull right');*/
		#	$this->setSessionUserForCurrentRequest();
		#}
		#parent::execute( $code );

		#if ( $code === null || $code === '' ) {
		#	$this->requireLogin( 'confirmemail_needlogin' );
		#} else {
		#	$this->attemptConfirm( $code );
		#}
	}
}
