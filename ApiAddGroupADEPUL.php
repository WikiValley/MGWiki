<?php
/**
 * Copyright © 2007 Iker Labarga "<Firstname><Lastname>@gmail.com"
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

/**
 * A module that allows for editing and creating pages.
 *
 * Currently, this wraps around the EditPage class in an ugly way,
 * EditPage.php should be rewritten to provide a cleaner interface,
 * see T20654 if you're inspired to fix this.
 *
 * @ingroup API
 */
class ApiAddGroupADEPUL extends ApiBase {
	public function execute() {
		global $wgMGWikiSecretKeyADEPUL;
		$params = $this->extractRequestParams();
		$apiResult = $this->getResult();

		$code_action = $params['code_action'];
		$intitule_ac = $params['intitule_ac'];
		$intitule_long_ac = $params['intitule_long_ac'];
		$formateur = $params['formateur'];
		$cle = $params['cle'];

		if( $cle !== $wgMGWikiSecretKeyADEPUL ) {
			$this->dieWithError( 'mgwiki-wrong-secret-key' );
		}

		$annee = intval( preg_replace( '/^[A-Z]([0-9]{2}).*/', '$1', $code_action ) );
		$annee = $annee ? '20' . $annee : '';

		$formateurUser = $this->getUserByADEPUL( $formateurAdepul );

		$title = "DPC ADEPUL $annee - $intitule_ac";
		$wikitext = "{{Groupe
|Archivé=Non
|Description=$intitule_long_ac
|Institution de rattachement=ADEPUL
|Tuteur ou modérateur=$formateurUser
|Membres=
|Type de groupe=DPC
|Titre de la formation=$intitule_ac
|Année=$annee
}}
{{Groupe2}}";
		$groupTitle = $userTitle = Title::newFromText( $title, NS_MAIN );
		$groupArticle = WikiPage::factory( $groupTitle );
		$content = new WikitextContent( $wikitext );
		$summary = wfMessage( 'mgwiki-create-grouppage' )->inContentLanguage()->text();
		$flags = EDIT_NEW;
		$groupArticle->doEditContent( $content, $summary, $flags, false, $formateurUser );

		$apiResult->addValue( null, $this->getModuleName(), $r );
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
			'intitule_ac' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'intitule_long_ac' => [
				ApiBase::PARAM_TYPE => 'string',
			],
			'formateur' => [
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

	public function getUserADEPUL( $code_adepul ) {
		global $wgMGWikiUserEndpointADEPUL, $wgMGWikiSecretKeyADEPUL;
		$infoADEPUL = file_get_contents( $wgMGWikiUserEndpointADEPUL . '?t_idf=' . $code_adepul . '&cle=' . $wgMGWikiSecretKeyADEPUL );
		if( !$infoADEPUL ) {
			$this->dieWithError( 'mgwiki-wrong-secret-key' );
		}
		$infoADEPUL = json_decode( $infoADEPUL );
		if( $infoADEPUL === null ) {
			$this->dieWithError( 'mgwiki-wrong-secret-key' );
		}
		if( $infoADEPUL->existe === 'NON' ) {
			return false;
		}
		return $infoADEPUL;
	}

	public function getUserByADEPUL( $code_adepul ) {
		global $wgUser;
		global $wgMGWikiUserProperties;
		list( $query, $params ) = SMWQueryProcessor::getQueryAndParamsFromFunctionParams( [ "[[{$wgMGWikiUserProperties['codeAdepul']}::$code_adepul]]" ], SMW_OUTPUT_RAW, 1000, false );
		$result = SMWQueryProcessor::getResultFromQuery( $query, $params, SMW_OUTPUT_RAW, null );
		$matches = null;
		if( preg_match( '/^\[\[(.*)\]\]$/', $result, $matches ) ) {
			return $matches[1];
		} else {
			return;
			# Create user
			$backupWgUser = $wgUser;
			$wgUser = User::newFromName( 'Alexandre BRULET' );
			$adhAdepul = self::getUserADEPUL( $code_adepul );
			$prenom = $adhAdepul->prenom;
			$nom = $adhAdepul->nom;
			$mail = $adhAdepul->mail;
			$profession = $adhAdepul->profession;
			$specialite = $adhAdepul->specialite;
			$username = $prenom . ' ' . strtoupper( $nom );
			$userData = [];
			$userData[$wgMGWikiUserProperties['firstname']] = $prenom;
			$userData[$wgMGWikiUserProperties['lastname']] = $nom;
			$userData[$wgMGWikiUserProperties['institution']] = 'ADEPUL';
			$userData[$wgMGWikiUserProperties['codeAdepul']] = $code_adepul;
			$userData[$wgMGWikiUserProperties['email']] = $mail;
			#$userData[$wgMGWikiUserProperties['']] = ;
			MGWiki::createUser( $username, $userData );
			$wgUser = $backupWgUser;
			return $username;
		}
	}
}
