<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

use MediaWiki\Extension\MGWikiDev\Utilities\GetJsonPage;

/**
 * Obtention de messages d'interface depuis la page MediaWiki:MGWiki-messages.json
 */
class GetMessage {

	/**
   * @param string $mess : clé du message
   * @param array $args : liste des arguments à passer dans le message ( $1, $2, etc. )
   * @param bool $html : format HTML autorisé ou non
   * @return string $return
	 */
	public static function get( $mess, $args = [], $html = true ) {

    $messages = GetJsonPage::getData('messages');

    // le message existe
    if ( isset( $messages[ $mess ] ) ) {
      $out = $messages[ $mess ];
      if ( sizeof($args) > 0 ) {
        foreach ( $args as $key => $arg ) {
          $needle = '$' . ($key + 1);
          $out = str_replace( $needle, $arg, $out );
        }
      }
			return $out;
    }

    // le message n'existe pas
    if ( $html ) {
      $link = GetJsonPage::getLink('messages');
			return '<a href="'.$link.'">&lt;' . $mess . '&gt;</a>';
    }
    else return '<' . $mess . '>';
  }
}
