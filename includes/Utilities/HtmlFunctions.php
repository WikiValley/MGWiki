<?php

namespace MediaWiki\Extension\MGWiki\Utilities;

/**
 * Divers outils de mise en forme HTML
 */
class HtmlFunctions {

  /**
   * renvoie le contenu du/des fichier(s) du répertoire /resources/
   * @param string|string[] $files
   * @param string $tag 'style'|'script'
   * @return string
   */
  public static function include_resource_file ( $files, $tag ) {
    if ( is_array( $files ) ) {
      $return = '<'.$tag.'>';
      foreach ( $files as $file ) {
        $return .= self::get_resource_file ( $file );
      }
      return $return . '</'.$tag.'>';
    }
    if ( is_string( $files ) ) {
      return '<'.$tag.'>'.self::get_resource_file ( $files ).'</'.$tag.'>';
    }
  }

  public static function get_resource_file ( $file ) {
    $content = file_get_contents( __DIR__.'/../../resources/' . $file );
    $content = preg_replace( '/\/\*.*\*\//', '', $content );
    return $content;
  }

	/**
	 * @param string $do 'open'|'close'
	 * @param array $hiddenInputs [ 'input_name' => value, ... ]
   * @param string $action URL
   * @param string $method 'get'|'post'
	 * @return string HTML
	 */
	public static function form( $do, $hiddenInputs = [], $action = '', $method = 'post' ) {
    if ( !$action ) {
      $action = $_SERVER['PHP_SELF'];
    }
		switch ( $do ) {
			case 'open':
				$return = '<form action="' . $action . '" method="' . $method . '" >';
				foreach ( $hiddenInputs as $name => $value ) {
					$type = ( $name == 'submit' ) ? ' type="submit"' : '';
					$return .= '<input' . $type . ' name="' . $name . '" value="' . $value . '" hidden>';
				}
				return $return;
				break;
			case 'close':
				return '</form>';
				break;
		}
  }

  /**
   * @param bool $done
	 * @param string $mess
	 * @return string HTML
   */
	public static function alertMessage( $done, $mess ) {
		$icon = ( $done )
			? '<span class="oo-ui-iconElement-icon oo-ui-icon-check oo-ui-image-success" style="height:30px;"></span>'
			: '<span class="oo-ui-iconElement-icon oo-ui-icon-alert oo-ui-image-warning" style="height:30px;"></span>' ;
		$backgroundColor = ( $done ) ? '#d5fdf4' : '#fef6e7' ;
		$borderColor = ( $done ) ? '#14866d' : '#ffcc33' ;
		$outcome = ( $done ) ? 'done' : 'failed' ;
		return '
			<div class="mgw-alert" status="' . $outcome . '"
        style="background:'.$backgroundColor.'; border:solid 1px '.$borderColor.';
				font-size:small; margin:15px; padding:5px;"
			>' . $icon . '<i style="margin-left:40px;">' . $mess . '</i></div>' ;
	}

  public static function edit_button_img( $url, $tooltip = '', $type = 'form', $blank = false ) {    
    $tooltip = ( $tooltip ) ? ' title="' . $tooltip . '"' : '';
    $blank = ( $blank ) ? ' target="_blank"' : '';
    $img = '<img src="' . wfMgwConfig('images', $type.'-edit-button') . '" width="20" height="20">';
    return '<a href="'.$url.'"' . $blank . $tooltip . '>' . $img .'</a>';
  }

  public static function admin_link( $text, $color, $url, $tooltip = '', $blank = false ) {
    $colors = wfMgwConfig('images', 'admin-link-colors')[$color];
    $tooltip = ($tooltip) ? ' title="'.$tooltip.'"':'';
    $blank = ( $blank ) ? '_blank' : '_self';
    $onclick = ' onclick="window.open(\''.$url.'\', \''.$blank.'\')"';

    return '<span class="mgw-admin-link" ' . $onclick . $tooltip .
      'style="border-color: '.$colors[0].'; color: '.$colors[1].';">'.$text.'</span>';
  }
}
