<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

/**
 * Divers outils de mise en forme HTML
 */
class HtmlFunctions {

  /**
   * @param MGWStatus $status
   */
	public static function alertMessage( $status ) {
		$icon = ( $status->done() )
			? '<span class="oo-ui-iconElement-icon oo-ui-icon-check oo-ui-image-success" style="height:30px;"></span>'
			: '<span class="oo-ui-iconElement-icon oo-ui-icon-alert oo-ui-image-warning" style="height:30px;"></span>' ;
		$backgroundColor = ( $status->done() ) ? '#d5fdf4' : '#fef6e7' ;
		$borderColor = ( $status->done() ) ? '#14866d' : '#ffcc33' ;
		$outcome = ( $status->done() ) ? 'done' : 'failed' ;
		return '
			<div class="mgw-alert" status="' . $outcome . '"
        style="background:'.$backgroundColor.'; border:solid 1px '.$borderColor.';
				font-size:small; margin:15px; padding:5px;"
			>' . $icon . '<i style="margin-left:40px;">' . $status->mess() . '</i></div>' ;
	}

  public static function onclickButton( $value, $href ) {
    return '<input type="button" value="' . $value . '"
        onclick="document.location.href=\'' . $href . '\'">';
  }
}
