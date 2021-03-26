<?php
namespace MediaWiki\Extension\MGWiki\Foreign;
use SMW;
use SMWDataItem;
use SMW\DIWikiPage;

/**
 * Ensemble de fonctions statiques pour gérer les données sémantiques
 */
class MGWSemanticMediaWiki {

  /**
   * @param Title $title
   * @return SMW\SemanticData
   */
  public static function getSemanticData( $title ) {
  		$store = &smwfGetStore();
      return $store->getSemanticData( SMW\DIWikiPage::newFromTitle( $title ) );
  }

	/**
	 * Collect the requested data.
	 *
	 * @param string[] $fields Field names
	 * @param SMW\SemanticData|Title $target
	 * @param bool $complete Is set to true or false depending if all fields were found in the data
	 * @return array Requested data
	 */
	public static function collectSemanticData( array $fields, $target, &$complete ) {

    if ( $target instanceof \Title ) {
      $target = self::getSemanticData( $target );
    }
    if ( !( $target instanceof SMW\SemanticData ) ) {
      throw new \Exception('Erreur: le deuxième argument doit être soit de la classe "Title "soit "SMW\\SemanticData"', 1);
    }

		# Init
		$data = array();
		$count = 0;

		# Retrieve values
		$properties = $target->getProperties();

		# Normalise keys
		$mapNormalisation = [];
		foreach( $fields as $field )
			$mapNormalisation[str_replace( ' ', '_', $field )] = $field;

		# Iterate over existing properties and search requested properties
		foreach( $properties as $key => $diProperty ) {
			$values = $target->getPropertyValues( $diProperty );
			#echo "property ".$diProperty->getKey()." found with ".count( $values )." values and type ".current( $values )->getDIType()."\n";
			if ( !in_array( $diProperty->getKey(), array_keys( $mapNormalisation ) ) )
				continue;
			#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") found with ".count( $values )." values and type ".current( $values )->getDIType()."\n";
			if ( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_BLOB ) {
				#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") = ".current( $values )->getString()."\n";
				$data[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getString();
				$count++;
			}
			elseif ( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_WIKIPAGE ) {
				#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") = ".current( $values )->getTitle()."\n";
				$data[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getTitle();
				$count++;
			}
			elseif ( count( $values ) == 1 && current( $values )->getDIType() == SMWDataItem::TYPE_BOOLEAN ) {
				#echo "property ".$diProperty->getKey()." (".$mapNormalisation[$diProperty->getKey()].") = ".(current( $values )->getBoolean()?'true':'false')."\n";
				$data[$mapNormalisation[$diProperty->getKey()]] = current( $values )->getBoolean();
				$count++;
			}
		}

		# Check if we have all mandatory values
		$complete = false;
		if ( $count == count( $fields ) ) $complete = true;

		return $data;
	}
}
