<?php
namespace MediaWiki\Extension\MGWiki;
use Parser;
use User;
use MediaWiki\Extension\MGWiki\Utilities\HtmlFunctions as HtmlF;
/**
 * MGWiki - parser functions
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWiki
 */

/**
 * Class for some parser functions.
 */
class MGWikiParserFunctions {

	/**
	 * Hook function for MediaWiki’s hook "ParserFirstCallInit".
	 *
	 * @param Parser $parser Parser object where will be registered the new parser functions.
	 * @return true
	 */
	public static function onParserFirstCallInit( &$parser ) {

		$parser->setFunctionHook(
			'isusersysop',
			[ self::class, 'pfuncIsUserSysop' ],
			Parser::SFH_OBJECT_ARGS
		);

		$parser->setFunctionHook( 'mgw-onclick', [ self::class, 'onclickSpan' ] );

		return true;
	}

	/**
	 * Parser function returning 1 if the given user is a sysop, else an empty string.
	 *
	 * This result is compatible with SemanticMediaWiki: you can directly use the result to set some semantic
	 * property, and hence you can get the adminness as a data source in SemanticMediaWiki.
	 *
	 * In case of error, returns an HTML red warning, which can be caught by #iferror from ParserFunctions.
	 *
	 * @param Parser $parser Parser Parser object where is registered the parser function.
	 * @param PPFrame $frame
	 * @param array $args
	 * @return string Result of the parser function.
	 */
	public static function pfuncIsUserSysop( $parser, $frame, $args ) {

		try {
			$username = isset( $args[0] ) ? trim( $frame->expand( $args[0] ) ) : '';
			if( !$username ) {
				return '<strong class="error">' . wfMessage( 'mgwiki-isusersysop-nousername' )->text() . '</strong>';
			}
			if( User::isIP( $username ) ) {
				return '0';
			}
			$user = User::newFromName( $username );
			if( !$user ) {
				return '<strong class="error">' . wfMessage( 'mgwiki-isusersysop-badusername' )->text() . '</strong>';
			}
			if( in_array( 'sysop', $user->getGroups() ) ) {
				return '1';
			}
		} catch( Exception $e ) {
			return '<strong class="error">' . wfMessage( 'mgwiki-parserfunction-internalerror' )->text() . '</strong>';
		}
		return '0';
	}

  // Render the output of {{#mgw-onclick:wikitext|url|tooltip|data|target}}.
  public static function onclickSpan( $parser, $include, $url, $tooltip = '', $data = '', $target = 'self' ) {
    if ( preg_match( '/^http/', $url ) != 1 ) {
      $url = '/wiki/index.php/' . $url;
    }
    $json['url'] = $url;
    $json['tooltip'] = $tooltip;
    $json['data'] = $data;
    $json['target'] = $target;
    $json = json_encode( $json );
    $output = '<span class="mgw-post-link" style="display:none">' .
                '<p class="mgw-post-link-data">' . $json . '</p>' .
                '<p class="mgw-post-link-inner">' . $include . '</p>' .
              '</span>';

    return [ $output, 'isHTML' => false ];
  }
}
