<?php
/**
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
 * @ingroup Auth
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
