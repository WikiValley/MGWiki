<?php
/**
 * Adaptation de SpecialRenameUser.php
 * ! DEPENDANCE : extension ReplaceText
 *
 * TODO: hack pour permettre le remplacement du texte dans les anciennes versions
 * i.e. effacement complet et définitif de données
 * => ReplaceTextSearch et ReplaceTextJob
 *
 * TODO: boucle en cas de nombre de pages max atteint (250)
 *
 * https://www.mediawiki.org/wiki/Extension:Replace_Text
 */

namespace MediaWiki\Extension\MGWiki\Foreign;

use User;
use MWNamespace;
use ReplaceTextSearch;
use ReplaceTextJob;
use TitleArrayFromResult;
use MediaWiki\Extension\MGWiki\Utilities\MGWStatus as Status;

class MGWReplaceText {
	private $user;
	private $target;
	private $replacement;
	private $namespaces;
	private $category;
	private $prefix;
	private $useRegex;
	private $doAnnounce;
	private $rename;

	private $nsall;
	private $ns;
	private $summary;
	private $status;

	/**
	 * @param $args list of arrays:
	 *	[ "target" => string ] MANDATORY Target text to find.
	 *	[ "replace" => string ] MANDATORY Text to replace.
	 *	[ "regex" => bool ] This is a regex (false).
	 *	[ "user" => string ] The user to attribute this to (uid 1).
	 *	[ "summary" => string ] Alternate edit summary (%r is where to place the replacement text, %f the text to look for.)
	 *	[ "nsall" => bool ] Search all canonical namespaces (false) If true, this option overrides the ns option.
	 *	[ "ns" => array ] Comma separated namespaces to search in (Main).
	 *	[ 'rename' => bool ] Rename page titles instead of replacing contents.
	 *  [ "announce" => bool ] Do announce edits on Special:RecentChanges or watchlists.
	 *
	 * test:
	 * MGWReplaceText::do( [ "target" => "interne TEST1", "target" => "anonyme", "regex" => false, "nsall" => true, "summary" => "MGW replacetext test.", "user" => "Webmaster" ] );
	 */


	/**
	 * @param array $args
	 * @return MGWStatus
	 */
	public static function do ( $args ) {
		$replace = new MGWReplaceText( $args );
 		return $replace->execute();
	}


	/**
	 * @param array $args
	 * @return MGWReplaceText
	 */
	public function __construct( $args ) {

		$this->category = null;
		$this->prefix = null;

    foreach ($args as $arg => $value ) {
      switch ( $arg ) {

				case "user":
					$user = is_numeric( $value ) ?
						User::newFromId( $value ) :
						User::newFromName( $value );

					if ( get_class( $user ) !== 'User' ) {
						$this->status = Status::newFailed(	'Couldn\'t translate '.$value.' to a user.');
					}
					$this->user = $user;
					break;

				case "target":
					$this->target = $value;
					break;

				case "replace":
					$this->replacement = $value;
					break;

				case "summary":
					$this->summary = $value;
					break;

				case "nsall":
					$this->nsall = $value;
					break;

				case "ns":
					$this->ns = $value;
					break;

				case "regex":
					$this->useRegex = $value;
					break;

				case "rename":
					$this->rename = $value;
					break;

				case "announce":
					$this->doAnnounce = $value;
					break;

				default:
					$this->status = Status::newFailed(	"Paramètre [ '" . $arg .
					"' => '" . $value . "' ] inconnu." );
			}
		}

		if ( is_null( $this->target ) ) {
			$this->status = Status::newFailed(	"You have to specify a target." );
		}

		if ( is_null( $this->replacement ) ) {
			$this->status = Status::newFailed(	"You have to specify replacement text." );
		}

		if ( is_null( $this->rename ) ) {
			$this->rename = false;
		}

		if ( is_null( $this->nsall ) && is_null( $this->ns ) ) {
			$namespaces = [ NS_MAIN ];
		} else {
			$canonical = MWNamespace::getCanonicalNamespaces();
			$canonical[NS_MAIN] = "_";
			$namespaces = array_flip( $canonical );
			if ( is_null( $this->nsall ) ) {
				$namespaces = $this->ns;
			}
		}
		$this->namespaces = $namespaces;
		if ( $this->namespaces === [] ) {
			$this->status = Status::newFailed(	"No matching namespaces." );
		}

		if ( is_null( $this->doAnnounce ) ) {
			$this->doAnnounce = true;
		}

		if ( is_null( $this->status ) ) {
			$this->status = Status::newDone( 'ReplaceText object construction done.');
		}
  }

	private function getSummary( $target, $replacement ) {
		$msg = wfMessage( 'replacetext_editsummary', $target, $replacement )->plain();
		if ( isset( $this->summary ) ) {
			$msg = str_replace( [ '%f', '%r' ], [ $this->target, $this->replacement ], $this->summary );
		}
		return $msg;
	}

	private function replaceTitles( $titles, $target, $replacement, $useRegex, $rename ) {
		foreach ( $titles as $title ) {
			$params = [
				'target_str'      => $target,
				'replacement_str' => $replacement,
				'use_regex'       => $useRegex,
				'user_id'         => $this->user->getId(),
				'edit_summary'    => $this->getSummary( $target, $replacement ),
				'doAnnounce'      => $this->doAnnounce
			];

			if ( $rename ) {
				$params[ 'move_page' ] = true;
				$params[ 'create_redirect' ] = true;
				$params[ 'watch_page' ] = false;
			}

			$job = new ReplaceTextJob( $title, $params );
			if ( $job->run() !== true ) {
				$this->status->extra[] = [
					"title" => $title,
					"done" => false,
					"message" => "Trouble replacing on the page '$title'."
				];
			}
			else $this->status->extra[] = [
				"title" => $title,
				"done" => true,
				"message" => "Replacing on '$title' done."
			];
		}
	}


	/**
	 * @return MGWStatus
	 */
	public function execute() {

		if ( !$this->status->done() ) {
			return $this->status;
		}

		if ( $this->rename ) {
			$res = ReplaceTextSearch::getMatchingTitles(
				$this->target,
				$this->namespaces,
				$this->category,
				$this->prefix,
				$this->useRegex
			);
		} else {
			$res = ReplaceTextSearch::doSearchQuery(
				$this->target,
				$this->namespaces,
				$this->category,
				$this->prefix,
				$this->useRegex
			);
		}

		$titles = new TitleArrayFromResult( $res );

		if ( count( $titles ) === 0 ) {
			$this->status->set_done( false );
			$this->status->set_message( 'No targets found to replace.' );
			return $this->status;
		}

		$this->replaceTitles(
			$titles, $this->target, $this->replacement, $this->useRegex, $this->rename
		);
		$this->status->set_message( 'ReplaceText done (see details).' );

		return $this->status;
	}
}
