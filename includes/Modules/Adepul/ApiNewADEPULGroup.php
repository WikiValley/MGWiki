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

/**
 * A module that creates a new ADEPUL group.
 *
 * @ingroup API
 */
class ApiNewADEPULGroup extends \ApiBase {
	public function execute() {
		global $wgMGWikiSecretKeyADEPUL;
		$params = $this->extractRequestParams();
		$apiResult = $this->getResult();

		$code_action = $params['code_action'];
		$intitule_ac = $params['intitule_ac'];
		$intitule_long_ac = $params['intitule_long_ac'];
		$formateur = $params['formateur'];
		$cle = $params['cle'];

		if( $wgMGWikiSecretKeyADEPUL === null || !hash_equals( $wgMGWikiSecretKeyADEPUL, $cle ) ) {
			$this->dieWithError( 'mgwiki-wrong-secret-key' );
		}

		$annee = intval( preg_replace( '/^[A-Z]([0-9]{2}).*/', '$1', $code_action ) );
		$annee = $annee ? '20' . $annee : '';

		try {
			$formateurUser = Adepul::getUserByADEPUL( $formateur );
			$formateurUsername = $formateurUser->getName();
		} catch( Exception $e ) {
			$this->dieWithError( 'mgwiki-wrong-secret-key' );
		}

		$title = "DPC ADEPUL $annee - $intitule_ac";
		$wikitext = "{{Groupe
|Archivé=Non
|Description=$intitule_long_ac
|Institution de rattachement=ADEPUL
|Tuteur ou modérateur=$formateurUsername
|Membres=
|Type de groupe=DPC
|Titre de la formation=$intitule_ac
|Année=$annee
|Code action=$code_action
}}
{{Groupe2}}";
		$groupTitle = Title::newFromText( $title, 730 );
		$groupArticle = WikiPage::factory( $groupTitle );
		$content = new WikitextContent( $wikitext );
		$summary = wfMessage( 'mgwiki-create-grouppage' )->inContentLanguage()->text();
		$flags = EDIT_NEW;
		$groupArticle->doEditContent( $content, $summary, $flags, false, $formateurUser );

		$apiResult->addValue( null, "result", "success" );
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
}
