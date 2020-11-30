<?php
/**
 * Adaptation de SpecialUserMerge.php
 * ! DEPENDANCE : extension UserMerge
 * https://www.mediawiki.org/wiki/Extension:UserMerge
 */

namespace MediaWiki\Extension\MGWikiDev\Foreign;

use User;
use Status;
use MediaWiki\MediaWikiServices;
use MergeUser;  // extension UserMerge
use UserMergeLogger; // extension UserMerge

class MGWUserMerge {

	/**
	 * @param string $oldname
   * @param string|null $newname
   * @param bool $delete
	 * @param callable $msg Function that returns a Message object
	 * @return Status
	 */
	public function execute( $oldname, $newname, $delete, /* callable */ $msg ) {
		global $wgUser;

		// Validate old user
		$oldUser = User::newFromName( $oldname );
		if ( !$oldUser || $oldUser->getId() === 0 ) {
			return [
				'done' => false,
				'message' => wfMessage('usermerge-badolduser')->text()
			];
		}
		if ( $wgUser->getId() === $oldUser->getId() ) {
			return [
				'done' => false,
				'message' => wfMessage('usermerge-noselfdelete', $wgUser->getName() )->parse()
			];
		}
		$protectedGroups = MediaWikiServices::getInstance()->getMainConfig()->get( 'UserMergeProtectedGroups' );
		if ( count( array_intersect( $oldUser->getGroups(), $protectedGroups ) ) ) {
			return [
				'done' => false,
				'message' => wfMessage( 'usermerge-protectedgroup', $oldUser->getName() )->parse()
			];
		}

		// validate new user
		if ( $newname !== null ) {
			$newUser = User::newFromName( $newname );
			if ( !$newUser || $newUser->getId() === 0 ) {
				return [
					'done' => false,
					'message' => wfMessage('usermerge-badnewuser')->text()
				];
			}
		}

		// check if the users are different
		$newUser = User::newFromName( $newname );
		// Handle "Anonymous" as a special case for user deletion
		if ( $newname === null ) {
			$newUser = User::newFromName( '(compte erronné)' );
			$newUser->mId = 0;
		}
		$oldUser = User::newFromName( $oldname );
		if ( $newname !== null && $newUser->getName() === $oldUser->getName() ) {
			return [
				'done' => false,
				'message' => wfMessage('usermerge-same-old-and-new-user')->text()
			];
		}

		// Validation passed, let's merge the user now.
		$message = '';
		$um = new MergeUser( $oldUser, $newUser, new UserMergeLogger() );
		$um->merge( $wgUser, __METHOD__ );

		$message .= wfMessage( 'usermerge-success' )->rawParams(
			$oldUser->getName(), $oldUser->getId(),
			$newUser->getName(), $newUser->getId() )->parse();

		if ( $delete ) {
			$failed = $um->delete( $wgUser, $msg );
			$message .= wfMessage( 'usermerge-userdeleted')->rawParams( $oldUser->getName(), $oldUser->getId() )->escaped() ;
			if ( $failed ) return [
				'done' => false,
				'message' => wfMessage('usermerge-page-unmoved')->text()
			];
		}
		return [
			'done' => true,
			'message' => $message
		];
	}
}
