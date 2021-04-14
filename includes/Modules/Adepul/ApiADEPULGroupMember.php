<?php
namespace MediaWiki\Extension\MGWiki\Modules\Adepul;
/**
 * Copyright © 2019 Wiki Valley
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
 */

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Extension\MGWiki\Modules\Adepul\Adepul;

/**
 * A module that adds an ADEPUL member in an ADEPUL group.
 *
 * @ingroup API
 */
class ApiADEPULGroupMember extends \ApiBase {
	public function execute() {
		global $wgMGWikiSecretKeyADEPUL, $wgMGWikiUserProperties;
		$params = $this->extractRequestParams();
		$apiResult = $this->getResult();

		$code_action = $params['code_action'];
		$membres = $params['membres'] ?: $params['membre'];
		$cle = $params['cle'];

		if( $wgMGWikiSecretKeyADEPUL === null || !hash_equals( $wgMGWikiSecretKeyADEPUL, $cle ) ) {
			$this->dieWithError( 'mgwiki-wrong-secret-key' );
		}

		// Get ADEPUL group page
		$groupTitle = Adepul::getADEPULGroup( $code_action );
		if( $groupTitle === null ) {
			$this->dieWithError( 'mgwiki-unknown-group' );
		}
		$groupArticle = WikiPage::factory( $groupTitle );
		$groupContent = $groupArticle->getContent();
		if( $groupContent === null || $groupContent->getModel() !== CONTENT_MODEL_WIKITEXT ) {
			$this->dieWithError( 'mgwiki-bad-group' );
		}
		$groupWikitext = $groupContent->getText();
		if( !preg_match( '/\{\{Groupe[\n |](?:[^}]+\})+\}/', $groupWikitext, $groupTemplate, PREG_OFFSET_CAPTURE ) ) {
			$this->dieWithError( 'mgwiki-bad-adepul-group' );
		}
		if( !preg_match( '/^\| *' . $wgMGWikiUserProperties['moderator'] . ' *= *([^|]*)/m', $groupTemplate[0][0], $formateurUsername ) ) {
			$this->dieWithError( 'mgwiki-bad-adepul-group' );
		}
		if( !preg_match( '/^\| *' . $wgMGWikiUserProperties['membersList'] . ' *= *([^|]*)/m', $groupTemplate[0][0], $membersList, PREG_OFFSET_CAPTURE ) ) {
			$this->dieWithError( 'mgwiki-bad-adepul-group' );
		}
		$formateurUsername = trim( $formateurUsername[1] );
		$formateurUser = User::newFromName( $formateurUsername );

		// Get existing members
		$existingMembers = explode( ',', $membersList[1][0] );
		$existingMembers = array_map( function($s) { return trim($s); }, $existingMembers );
		if( count( $existingMembers ) === 1 && $existingMembers[0] === '' ) {
			$existingMembers = [];
		}

		// Retrieve members to be added
		$membres = explode( ',', $membres );
		$errorsMembers = [];
		$listMembers = [];
		foreach( $membres as $membre ) {
			try {
				$memberUser = Adepul::getUserByADEPUL( $membre, $formateurUsername );
				$memberUser = $memberUser->getName();
				if( !in_array( $memberUser, $existingMembers ) ) {
					$listMembers[] = $memberUser;
				}
			} catch( Exception $e ) {
				$errorsMembers[] = [ $membre, $e->getMessage() ];
			}
		}
		if( count( $listMembers ) === 0 && count( $errorsMembers ) === 0 ) {
			$apiResult->addValue( null, "result", "success" );
			return;
		}

		$newWikitext = substr( $groupWikitext, 0, $groupTemplate[0][1] ) . // Before template Groupe
			substr( $groupTemplate[0][0], 0, $membersList[1][1] ) . // In template Groupe, before the parameter 'Membres' and including this parameter name until its '='
			implode( ', ', $listMembers ) . ( count( $existingMembers ) && $existingMembers[0] ? ', ' : '' ) . // Add our new members
			substr( $groupTemplate[0][0], $membersList[1][1] ) . // In template Groupe, just after the parameter 'Membres'
			substr( $groupWikitext, $groupTemplate[0][1] + strlen( $groupTemplate[0][0] ) ); // After template Groupe

		$newGroupContent = new WikitextContent( $newWikitext );

		$summary = wfMessage( 'mgwiki-add-member-grouppage' )->inContentLanguage()->text();
		$groupArticle->doEditContent( $newGroupContent, $summary, 0, false, $formateurUser );

		$apiResult->addValue( null, "result", "success" );
		if( count( $errorsMembers ) ) {
			$apiResult->addValue( null, "errors", $errorsMembers );
		}
	}

	public function mustBePosted() {
		return true;
	}

	public function isReadMode() {
		return false;
	}

	public function isWriteMode() {
		return true;
	}

	public function getAllowedParams() {
		return [
			'code_action' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'membre' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'membres' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'cle' => [
				ApiBase::PARAM_TYPE => 'string',
			],
		];
	}

	public function needsToken() {
		return false;
	}
}
