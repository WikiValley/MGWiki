<?php
/**
 * MGWiki
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 */

namespace MediaWiki\Auth;

use StatusValue;
use User;

/**
 * This represents the field emailtoken aiming at connecting the user after s/he is redirected through Special:Invitation
 *
 * @ingroup Auth
 */
class EmailTokenAuthenticationRequest extends AuthenticationRequest {
	/** @var string|null Email address */
	public $emailtoken;

	public function getFieldInfo() {
		$config = \ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		$ret = [
			'emailtoken' => [
				'type' => 'string',
				'label' => wfMessage( 'authmanager-emailtoken-label' ),
				'help' => wfMessage( 'authmanager-emailtoken-help' ),
				'optional' => true,
			],
		];

		if ( !$config->get( 'EnableEmail' ) ) {
			unset( $ret['emailtoken'] );
		}

		return $ret;
	}
}
