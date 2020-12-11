<?php
/**
 * Adaptation de SpecialUserMerge.php
 * ! DEPENDANCE : extension UserMerge
 * ! NE PAS FUSIONNER SANS DELETE (ancien compte toujours valide)
 * ! DELETE SEUL NE FONCTIONNE PAS
 * => utiliser seulement la fusion avec l'option delete
 *
 * ! la trace de l'utilisateur peut persister dans le contenu des pages
 *
 * https://www.mediawiki.org/wiki/Extension:UserMerge
 */

namespace MediaWiki\Extension\MGWikiDev\Foreign;

use User;
use MediaWiki\MediaWikiServices;
use MergeUser;  // extension UserMerge
use UserMergeLogger; // extension UserMerge
use MediaWiki\Extension\MGWikiDev\Classes\MGWStatus as Status;

class MGWUserMerge {

	/**
	 * @param int $old_id
   * @param int|null $new_id # !! null NE FONCTIONNE PAS
   * @param bool $delete
	 * @param callable $msg Function that returns a Message object
	 * @return Status
	 */
	public function execute( $old_id, $new_id, $delete, /* callable */ $msg ) {
		global $wgUser;

		// Validate old user
		$oldUser = User::newFromId( $old_id );
		if ( !$oldUser ) {
			return Status::newFailed( wfMessage('usermerge-badoldid')->text() );
		}
		if ( $wgUser->getId() === $old_id ) {
			return Status::newFailed( wfMessage('usermerge-noselfdelete', $wgUser->getName() )->parse() );
		}
		$protectedGroups = MediaWikiServices::getInstance()->getMainConfig()->get( 'UserMergeProtectedGroups' );
		if ( count( array_intersect( $oldUser->getGroups(), $protectedGroups ) ) ) {
			return Status::newFailed( wfMessage( 'usermerge-protectedgroup', $oldUser->getName() )->parse() );
		}

		// validate new user
		if ( $new_id !== null ) {
			$newUser = User::newFromId( $new_id );
			if ( !$newUser ) {
				return Status::newFailed( wfMessage('usermerge-badnewuserid')->text() );
			}
		}
		// Handle "Anonymous" as a special case for user deletion
		if ( $new_id === null ) {
			$newUser = User::newFromName( 'Anonymous' );
			$newUser->mId = 0;
		}

		if ( $new_id !== null && $newUser->getName() === $oldUser->getName() ) {
			return Status::newFailed( wfMessage('usermerge-same-old-and-new-user')->text() );
		}

		// Validation passed, let's merge the user now.
		$message = '';
		$um = new MergeUser( $oldUser, $newUser, new UserMergeLogger() );
		$um->merge( $wgUser, __METHOD__ );

		$message .= wfMessage( 'usermerge-success' )->rawParams(
			$oldUser->getName(), $old_id,
			$newUser->getName(), $new_id )->parse();

		if ( $delete ) {
			$failed = $um->delete( $wgUser, $msg );
			$message .= wfMessage( 'usermerge-userdeleted')->rawParams( $oldUser->getName(), $old_id )->escaped() ;
			if ( $failed ) return Status::newFailed( wfMessage('usermerge-page-unmoved')->text() );
		}
		return Status::newDone( $message );
	}
}
