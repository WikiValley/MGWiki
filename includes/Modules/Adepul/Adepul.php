<?php

namespace MediaWiki\Extension\MGWiki\Modules\Adepul;

use MGWiki;
use Title;
use User;
use WikiPage;
use WikitextContent;

class Adepul {

	/**
	 * Get the user from the official ADEPUL database.
	 *
	 * @param string $code_adepul ADEPUL id.
	 * @return object|false|null Object with the various data describing the ADEPUL user, or false if non-existing ADEPUL user, or null if error.
	 */
	public static function getUserFromOfficialADEPUL( $code_adepul ) {
		global $wgMGWikiUserEndpointADEPUL, $wgMGWikiSecretKeyADEPUL;
		$infoADEPUL = \Http::get( $wgMGWikiUserEndpointADEPUL . '?t_idf=' . $code_adepul . '&cle=' . $wgMGWikiSecretKeyADEPUL );
		if( !$infoADEPUL ) {
			return null;
		}
		$infoADEPUL = json_decode( $infoADEPUL );
		if( $infoADEPUL === null ) {
			return null;
		}
		if( $infoADEPUL->existe === 'NON' ) {
			return false;
		}
		return $infoADEPUL;
	}

	/**
	 * Get (or create) the user corresponding to an ADEPUL id.
	 *
	 * @param string $code_adepul ADEPUL id.
	 * @param string|null $creator Username creating a new user, or $wgMGWikiDefaultCreatorNewAccounts if null.
	 * @return User|null MediaWiki user, possibly just created with the specific MGWiki process.
	 */
	public static function getUserByADEPUL( $code_adepul, $creator = null ) {
		global $wgUser;
		global $wgMGWikiUserProperties, $wgMGWikiDefaultCreatorNewAccounts, $wgMGWikiFillADEPULCode;

		$codeAdepulTitle = Title::newFromText( 'Property:' . $wgMGWikiUserProperties['codeAdepul'] );
		$codeAdepul = $codeAdepulTitle->getDBkey();

		// Create property instance
		$property = new \SMWDIProperty( $codeAdepul );
		$property->setPropertyTypeId( \SMW\DataValues\StringValue::TYPE_ID );
		$dataItem = new \SMWDIBlob( $code_adepul );
		$dataValue = \SMW\DataValueFactory::getInstance()->newDataValueByItem(
			$dataItem,
			$property
		);

		// Create a description that represents the condition
		$descriptionFactory = new \SMW\Query\DescriptionFactory();
		$namespaceDescription = $descriptionFactory->newNamespaceDescription(
			NS_USER
		);
		$descriptionAdepul = $descriptionFactory->newSomeProperty(
			$property,
			$descriptionFactory->newValueDescription( $dataItem )
		);
		$description = $descriptionFactory->newConjunction( array(
			$namespaceDescription,
			$descriptionAdepul
		) );
		$propertyValue = \SMW\DataValueFactory::getInstance()->newPropertyValueByLabel(
			$codeAdepul
		);

		$description->addPrintRequest(
			new \SMW\Query\PrintRequest( \SMW\Query\PrintRequest::PRINT_PROP, null, $propertyValue )
		);

		// Create query object
		$query = new \SMWQuery(
			$description
		);

		$query->querymode = \SMWQuery::MODE_INSTANCES;

		// Try to match condition against the store
		$queryResult = \SMW\ApplicationFactory::getInstance()->getStore()->getQueryResult( $query );

		if( $queryResult->getCount() === 0 ) {
			# Create user
			$backupWgUser = $wgUser;
			$creator = $creator ?: $wgMGWikiDefaultCreatorNewAccounts;
			$wgUser = User::newFromName( $creator );
			if( !$wgUser || $wgUser->getId() === 0 ) {
				if( $creator !== $wgMGWikiDefaultCreatorNewAccounts ) {
					$wgUser = User::newFromName( $wgMGWikiDefaultCreatorNewAccounts );
				} else {
					throw new \Exception( 'Creator account "' . $creator . '" not found' );
				}
			}
			$adhAdepul = self::getUserFromOfficialADEPUL( $code_adepul );
			if( !$adhAdepul ) {
				throw new \Exception( 'ADEPUL code "' . $code_adepul . '" unknown on MGWiki and on ADEPUL' );
			}
			$prenom = $adhAdepul->prenom;
			$nom = $adhAdepul->nom;
			$mail = $adhAdepul->mail;
			$profession = $adhAdepul->profession;
			$specialite = $adhAdepul->specialite;
			$username = $prenom . ' ' . strtoupper( $nom );
			$userData = [];
			$userData[$wgMGWikiUserProperties['firstname']] = $prenom;
			$userData[$wgMGWikiUserProperties['lastname']] = $nom;
			$userData[$wgMGWikiUserProperties['institution']] = Title::newFromText( 'ADEPUL', NS_PROJECT );
			$userData[$wgMGWikiUserProperties['codeAdepul']] = $code_adepul;
			$userData[$wgMGWikiUserProperties['email']] = $mail;
			$user = User::newFromName( $username );
			if( $user->getId() !== 0 ) {
				if( ! $wgMGWikiFillADEPULCode ) {
					throw new \Exception( 'ADEPUL code not found but corresponding user found on MGWiki' );
				}
				$userTitle = Title::newFromText( $username, NS_USER );
				$userArticle = WikiPage::factory( $userTitle );
				$summary = wfMessage( 'mgwiki-create-userpage' )->inContentLanguage()->text();
				if( ! $userArticle->exists() ) {
					$content = new WikitextContent( self::userTemplate( $username, $userData ) );
				} elseif( ! preg_match( '/\{\{Personne[ \n]*(?:\||\}\})/', $userArticle->getContent()->getText() ) ) {
					$content = new WikitextContent( self::userTemplate( $username, $userData ) . $userArticle->getContent()->getText() );
				} else {
					$content = new WikitextContent( preg_replace(
						'/\{\{Personne[^}]+\}\}/',
						function( $matches ) use( $code_adepul, $wgMGWikiUserProperties ) {
							$template = MGWiki::addParameterTemplate( $matches[0], $wgMGWikiUserProperties['codeAdepul'], $code_adepul );
							if( $template === null ) throw new Exception( 'Conflicting ADEPUL code with existing and expected values' );
							return $template;
						},
						$userArticle->getContent()->getText()
					) );
				}
				$flags = EDIT_NEW;
				$userArticle->doEditContent( $content, $summary, $flags, false, $wgUser );
			}
			throw new Exception( "We are about to create the user $username on MGWiki with ADEPUL code $code_adepul and email $mail" );
			MGWiki::createUser( $username, $userData );
			$wgUser = $backupWgUser;
			$user = User::newFromName( $username );
			return $user;
		} elseif( $queryResult->getCount() > 1 ) {
			throw new \Exception( 'There are multiple users on MGWiki with the ADEPUL code "' . $code_adepul . '"' );
		} elseif( $queryResult->getCount() === 1 ) {
			$userValue = $queryResult->getResults()[0];
			$username = $userValue->getDBkey();
			$user = User::newFromName( $username );
			return $user;
		}

		return null;
	}

	/**
	 * Get an ADEPUL group.
	 *
	 * @param string $code_action ADEPUL action id.
	 * @return Title|null MediaWiki page of the ADEPUL group.
	 */
	public static function getADEPULGroup( $code_action ) {
		global $wgUser;
		global $wgMGWikiUserProperties;

		$codeActionTitle = Title::newFromText( 'Property:' . $wgMGWikiUserProperties['codeActionAdepul'] );
		$codeAction = $codeActionTitle->getDBkey();

		// Create property instance
		$property = new \SMWDIProperty( $codeAction );
		$property->setPropertyTypeId( \SMW\DataValues\StringValue::TYPE_ID );
		$dataItem = new \SMWDIBlob( $code_action );
		$dataValue = \SMW\DataValueFactory::getInstance()->newDataValueByItem(
			$dataItem,
			$property
		);

		// Create a description that represents the condition
		$descriptionFactory = new \SMW\Query\DescriptionFactory();
		$description = $descriptionFactory->newSomeProperty(
			$property,
			$descriptionFactory->newValueDescription( $dataItem )
		);
		$propertyValue = \SMW\DataValueFactory::getInstance()->newPropertyValueByLabel(
			$codeAction
		);

		$description->addPrintRequest(
			new \SMW\Query\PrintRequest( \SMW\Query\PrintRequest::PRINT_PROP, null, $propertyValue )
		);

		// Create query object
		$query = new \SMWQuery(
			$description
		);

		$query->querymode = \SMWQuery::MODE_INSTANCES;

		// Try to match condition against the store
		$queryResult = \SMW\ApplicationFactory::getInstance()->getStore()->getQueryResult( $query );

		if( $queryResult->getCount() === 0 ) {
			return null;
		} elseif( $queryResult->getCount() > 1 ) {
			throw new \Exception(); // TODO improve
		} elseif( $queryResult->getCount() === 1 ) {
			$groupValue = $queryResult->getResults()[0];
			$group = Title::newFromText( $groupValue->getDBkey(), $groupValue->getNamespace() );
			return $group;
		}

		return null;
	}

	/**
	 * Add a given parameter with a value on a template wikitext.
	 *
	 * @param string $template Template wikitext.
	 * @param string $key Parameter key.
	 * @param string $value Parameter value.
	 * @return string|null Template wikitext with the given parameter or null if the parameter exists with another value.
	 */
	public static function addParameterTemplate( $template, $key, $value ) {
		$keyRegex = preg_replace( '/[ _]/', '[ _]', $key );
		if( preg_match( '/\|[ \n]*' . $keyRegex . ' *= *([^|}]*)/', $template, $matches ) ) {
			$matches[1] = trim( $matches[1] );
			if( $matches[1] && $matches[1] !== $value ) return null;
			return preg_replace( '/\|[ \n]*(' . $keyRegex . ') *= *([^|}]*)/', '|' . $key . ' = ' . $value . "\n", $template );
		}
		return preg_replace( '/\n?\}\}$/', "\n|" . $key . ' = ' . $value . "\n}}", $template );
	}
}
