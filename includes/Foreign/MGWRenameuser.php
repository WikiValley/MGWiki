<?php
/**
 * Adaptation de SpecialRenameUser.php
 * ! DEPENDANCE : extension Renameuser
 * https://www.mediawiki.org/wiki/Extension:Renameuser
 */

namespace MediaWiki\Extension\MGWikiDev\Foreign;

use MediaWiki\Extension\MGWikiDev\Utilities\GetJsonPage;
use Title;
use User;
use MovePage;
use RenameuserSQL;
use MediaWiki\MediaWikiServices;
use Html;

class MGWRenameuser {

	/**
	 *
	 * @param string $oldname
   * @param string $newname
   * @param bool $movepages
   * @param bool $suppressRedirect
   * @param string $reason
	 *
	 * @throws PermissionsError
	 * @throws ReadOnlyError
	 * @throws UserBlockedError
   *
   * @return array [ 'done' => bool, 'message' => bool, ]
	 */
	public function execute( $oldname, $newname, $movepages, $suppressRedirect, $reason ) {
		global $wgContLang, $wgCapitalLinks, $wgUser;

    // ! on AUTORISE les utilisateurs à renommer leur propre compte
		$user = &$wgUser;
		if ( !( $user->isAllowed( 'renameuser' ) || $user->getName() == $oldname ) ) {
			throw new PermissionsError( 'renameuser' );
		}
		if ( $user->isBlocked() ) {
			throw new UserBlockedError( $wgUser->mBlock );
		}

		$oldusername = Title::makeTitle( NS_USER, $oldname );
		$newusername = Title::makeTitleSafe( NS_USER, $wgContLang->ucfirst( $newname ) );

		$oun = is_object( $oldusername ) ? $oldusername->getText() : '';
		$nun = is_object( $newusername ) ? $newusername->getText() : '';
		$token = $user->getEditToken();

		if ( $oun == '' ) {
      $message = str_replace( '<nowiki>$1</nowiki>', $oldname, wfMessage( 'renameusererrorinvalid' )->text() );
      return [ 'done' => false, 'message' => $message ];
		}
		if ( $nun == '' ) {
      $message = str_replace( '<nowiki>$1</nowiki>', $newname, wfMessage( 'renameusererrorinvalid' )->text() );
      return [ 'done' => false, 'message' => $message ];
		}

		// Suppress username validation of old username
		$olduser = User::newFromName( $oldusername->getText(), false );
		$newuser = User::newFromName( $newusername->getText(), 'creatable' );

		// It won't be an object if for instance "|" is supplied as a value
		if ( !is_object( $olduser ) ) {
      $message = str_replace( '<nowiki>$1</nowiki>', $oldname, wfMessage( 'renameusererrorinvalid' )->text() );
      return [ 'done' => false, 'message' => $message ];
		}
		if ( !is_object( $newuser ) || !User::isCreatableName( $newuser->getName() ) ) {
      $message = str_replace( '<nowiki>$1</nowiki>', $newname, wfMessage( 'renameusererrorinvalid' )->text() );
      return [ 'done' => false, 'message' => $message ];
		}

		// Check for the existence of lowercase oldusername in database.
		// Until r19631 it was possible to rename a user to a name with first character as lowercase
		if ( $oldusername->getText() !== $wgContLang->ucfirst( $oldusername->getText() ) ) {
			// oldusername was entered as lowercase -> check for existence in table 'user'
			$dbr = wfGetDB( DB_REPLICA );
			$uid = $dbr->selectField( 'user', 'user_id',
				[ 'user_name' => $oldusername->getText() ],
				__METHOD__ );
			if ( $uid === false ) {
				if ( !$wgCapitalLinks ) {
					$uid = 0; // We are on a lowercase wiki but lowercase username does not exists
				} else {
					// We are on a standard uppercase wiki, use normal
					$uid = $olduser->idForName();
					$oldusername = Title::makeTitleSafe( NS_USER, $olduser->getName() );
				}
			}
		} else {
			// oldusername was entered as upperase -> standard procedure
			$uid = $olduser->idForName();
		}

		if ( $uid === 0 ) {
      $message = str_replace( '<nowiki>$1</nowiki>', $oldname, wfMessage( 'renameusererrordoesnotexist' )->text() );
      return [ 'done' => false, 'message' => $message ];
		}

		if ( $newuser->idForName() !== 0 ) {
      $message = str_replace(
				['<nowiki>$1</nowiki>', '{{GENDER:$1|utilisateur|utilisatrice}}'],
				[ $oldname,'utilisateur.ice' ],
				wfMessage( 'renameusererrorexists' )->text() );
      return [ 'done' => false, 'message' => $message ];
		}

		// Do the heavy lifting...
		$rename = new RenameuserSQL(
			$oldusername->getText(),
			$newusername->getText(),
			$uid,
			$wgUser,
			[ 'reason' => $reason ]
		);
		if ( !$rename->rename() ) {
			return [ 'done' => false, 'message' => 'User '.$oldname.' does not exist, bailing out' ];
		}

		// If this user is renaming his/herself, make sure that MovePage::move()
		// doesn't make a bunch of null move edits under the old name!
		if ( $user->getId() === $uid ) {
			$user->setName( $newusername->getText() );
		}

		// Move any user pages
		if ( $movepages ) {
			$dbr = wfGetDB( DB_REPLICA );

			$pages = $dbr->select(
				'page',
				[ 'page_namespace', 'page_title' ],
				[
					'page_namespace' => [ NS_USER, NS_USER_TALK ],
					$dbr->makeList( [
						'page_title ' . $dbr->buildLike( $oldusername->getDBkey() . '/', $dbr->anyString() ),
						'page_title = ' . $dbr->addQuotes( $oldusername->getDBkey() ),
					], LIST_OR ),
				],
				__METHOD__
			);

			$output = '';
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();
			foreach ( $pages as $row ) {
				$oldPage = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
				$newPage = Title::makeTitleSafe( $row->page_namespace,
					preg_replace( '!^[^/]+!', $newusername->getDBkey(), $row->page_title ) );

				$movePage = new MovePage( $oldPage, $newPage );
				$validMoveStatus = $movePage->isValidMove();

				# Do not autodelete or anything, title must not exist
				if ( $newPage->exists() && !$validMoveStatus->isOK() ) {
					$link = $linkRenderer->makeKnownLink( $newPage );
					$output .= Html::rawElement(
						'li',
						[ 'class' => 'mw-renameuser-pe' ],
						wfMessage( 'renameuser-page-exists' )->rawParams( $link )->escaped()
					);
				} else {
					$logReason = wfMessage(
						'renameuser-move-log', $oldusername->getText(), $newusername->getText()
					)->inContentLanguage()->text();

					$moveStatus = $movePage->move( $user, $logReason, !$suppressRedirect );

					if ( $moveStatus->isOK() ) {
						# oldPage is not known in case of redirect suppression
						$oldLink = $linkRenderer->makeLink( $oldPage, null, [], [ 'redirect' => 'no' ] );

						# newPage is always known because the move was successful
						$newLink = $linkRenderer->makeKnownLink( $newPage );

						$output .= Html::rawElement(
							'li',
							[ 'class' => 'mw-renameuser-pm' ],
							wfMessage( 'renameuser-page-moved' )->rawParams( $oldLink, $newLink )->escaped()
						);
					} else {
						$oldLink = $linkRenderer->makeKnownLink( $oldPage );
						$newLink = $linkRenderer->makeLink( $newPage );
						$output .= Html::rawElement(
							'li', [ 'class' => 'mw-renameuser-pu' ],
							wfMessage( 'renameuser-page-unmoved' )->rawParams( $oldLink, $newLink )->escaped()
						);
					}
				}
			}
		}

		// Output success message stuff :)

		$message = str_replace(
			['<nowiki>$1</nowiki>', '<nowiki>$2</nowiki>', '{{GENDER:$1|utilisateur|utilisatrice}}', '{{GENDER:$1|renommé|renommée}}'],
			[ $oldname, $newname, 'utilisateur.ice', 'renomé.e' ],
			wfMessage( 'renameusersuccess' )->text() );
		if ( $output ) $message .= $output . 'ATTENTION: la prise en compte des modifications peut prendre du temps.';

		return [ 'done' => true, 'message' => $message ];
	}
}
