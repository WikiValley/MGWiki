<?php
/**
 * MGWiki
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 */

namespace MediaWiki\Extension\MGWiki\Modules\Auth;

use Config;
use User;
use MediaWiki\Auth\AbstractPrimaryAuthenticationProvider;
use MediaWiki\Auth\AuthManager;
use MediaWiki\Auth\AuthenticationRequest;
use MediaWiki\Auth\AuthenticationResponse;

class EmailTokenPrimaryAuthenticationProvider
	extends AbstractPrimaryAuthenticationProvider
{
	/** @var bool */
	protected $sendConfirmationEmail;

	/**
	 * Construct this authentication provider.
	 *
	 * @param array $params
	 *  - sendConfirmationEmail: (bool) send an email asking the user to confirm their email
	 *    address to finalize its account creation
	 */
	public function __construct( $params = [] ) {
		if ( isset( $params['sendConfirmationEmail'] ) ) {
			$this->sendConfirmationEmail = (bool)$params['sendConfirmationEmail'];
		}
	}

	/**
	 * Set the internal configuration: check if email is activated.
	 *
	 * @param Config $config Global MediaWiki configuration.
	 * @return void
	 */
	public function setConfig( Config $config ) {
		parent::setConfig( $config );

		if ( $this->sendConfirmationEmail === null ) {
			$this->sendConfirmationEmail = $this->config->get( 'EnableEmail' )
				&& $this->config->get( 'EmailAuthentication' );
		}
	}

	/**
	 * Return the authentication requests for this specific authentication type.
	 *
	 * @param string $action Authentication action, one of AuthManager::ACTION_*.
	 * @param array $options Options (unused here).
	 * @return AuthenticationRequest[] The email authentication request for login only.
	 */
	public function getAuthenticationRequests( $action, array $options ) {
		switch ( $action ) {
			case AuthManager::ACTION_LOGIN:
			case AuthManager::ACTION_LOGIN_CONTINUE:
				return [ new EmailTokenAuthenticationRequest() ];

			default:
				return [];
		}
	}

	public function beginPrimaryAuthentication( array $reqs ) {
		$req = AuthenticationRequest::getRequestByClass( $reqs, EmailTokenAuthenticationRequest::class );
		if ( !$req || !$req->emailtoken ) {
			return AuthenticationResponse::newAbstain();
		}

		$user = User::newFromConfirmationCode( $req->emailtoken, User::READ_LATEST );
		if ( !( $user instanceof User ) || !$user->getId() ) {
			return AuthenticationResponse::newFail( wfMessage( 'mgwiki-bad-email-token' ) );
		}
		$username = $user->getName();
		$user->setEmail( $user->getEmail() );
		$user->confirmEmail();
		return AuthenticationResponse::newPass( $username );
	}

	public function beginPrimaryAccountCreation( $user, $creator, array $reqs ) {
		/*if (
			$this->sendConfirmationEmail
			&& $user->getEmail()
			&& !$this->manager->getAuthenticationSessionData( 'no-email' )
		) {
			$status = $user->sendConfirmationMail();
			$user->saveSettings();
			if ( $status->isGood() ) {
				// TODO show 'confirmemail_oncreate' success message
			} else {
				// TODO show 'confirmemail_sendfailed' error message
				$this->logger->warning( 'Could not send confirmation email: ' .
					$status->getWikiText( false, false, 'en' ) );
			}
		}*/

		return AuthenticationResponse::newPass();
	}

	public function accountCreationType() {
		return self::TYPE_NONE;
	}

	public function testUserExists( $username, $flags = User::READ_NORMAL ) {
		$username = User::getCanonicalName( $username, 'usable' );
		if ( $username === false ) {
			return false;
		}

		list( $db, $options ) = \DBAccessObjectUtils::getDBOptions( $flags );
		return (bool)wfGetDB( $db )->selectField(
			[ 'user' ],
			[ 'user_id' ],
			[ 'user_name' => $username ],
			__METHOD__,
			$options
		);
	}

	public function testUserCanAuthenticate( $username ) {
		$user = User::newFromName( $username );
		return $user->isEmailConfirmationPending();
	}

	public function providerAllowsAuthenticationDataChange( AuthenticationRequest $req,
		$checkData = true
	) {
		return \StatusValue::newGood( 'ignored' );
	}

	public function providerChangeAuthenticationData( AuthenticationRequest $req ) {
	}
}
