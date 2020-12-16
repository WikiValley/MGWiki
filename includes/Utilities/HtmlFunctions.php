<?php

namespace MediaWiki\Extension\MGWikiDev\Utilities;

use Title;

use MediaWiki\Extension\MGWikiDev\Utilities\GetMessage as Msg;
use MediaWiki\Extension\MGWikiDev\Utilities\PhpFunctions as PhpF;

/**
 * Divers outils de mise en forme HTML
 */
class HtmlFunctions {

	public static function parseError( $text ) {
		return '<span class="mgw-parse-error">' . $text . '</span>';
	}

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

	/**
	 * @param array $hidden [ 'name' => 'value' ]
	 * @param string $ctrlFn 'mw.maFonction()'
	 */
	public static function actionButton (
		$action,
		$label,
		$check = [ 'add' => 'false', 'del' => 'false' ],
		$ctrlFn = '',
		$hidden = [] )
		{
		$extra = ' extra="' . json_encode( $hidden ) . '"';
		return '<button class="mgw-action-button" action="' . $action 	. '" '
			. ' checkadd="' . $check['add'] . '" checkdel="' . $check['del'] . '" '
			. ' control="' . $ctrlFn . '" ' . $extra . '>' . $label . '</button>';
	}

	/**
	 * @param int $a
	 */
	public static function reverseCheck( $a, $checked = '' ) {
		return '<input type="checkbox" class="mgw-check-reverse" name="reverse-' . $a . '" value="true" ' . $checked . '>
			<label for="reverse">rev</label>';
	}

	/**
	 */
	public static function tagShowArray( $content ) {
		return '<div class="mgw-show-array" >' . $content . '</div>';
	}

	/**
	 * @param string $content '<tr><td> (...) </tr></td>'
	 * @param string $class 'active'|'inactive'|'deleted'
	 */
	public static function tagRowTable( $content, $class ) {
		return '<table  class="mgw-row-table mgw-' . $class . '" >' . $content . '</table>';
	}


	/**
	 * @param string $text le texte du lien
	 * @param int $id page_id
	 */
  public static function linkPageId( $text, $id ) {
		$title = Title::newFromID( $id );
		if ( !is_null( $title ) ) {
			return '<a class="mgw-link" href="' . $title->getFullURL() . '">' . $text . '</a>';
		}
		else {
			return $text;
		}
	}


  /**
   * @param array $reqData
   */
  public static function set_select( $reqData, $triOptions, &$tri, &$select )
  {
		$tri[1]['rev'] = ( empty($reqData['reverse-a']) ) ? '' : 'checked';
		$tri[2]['rev'] = ( empty($reqData['reverse-b']) ) ? '' : 'checked';

    $tri[1]['val'] = ( isset( $reqData['tri1'] ) )
			? $reqData['tri1'] : 'nom';

    $tri[2]['val'] = ( isset( $reqData['tri2'] ) )
			? $reqData['tri2'] : '' ;

    foreach ( $triOptions as $value ) {

      if ( isset($reqData['tri1'] ) && $reqData['tri1'] == $value ) {
				$select[1][$value] = 'selected';
			} else {
				$select[1][$value] = '';
			}

      if ( isset($reqData['tri2'] ) && $reqData['tri2'] == $value ) {
				$select[2][$value] = 'selected';
			} else {
				$select[2][$value] = '';
			}

      if ( !in_array( 'selected', $select[2] ) ) {
				$select[2]['...'] = 'selected';
			} else {
				$select[2]['...'] = '';
			}
    }
	}

  /**
   * @param array $reqData
   */
  public static function set_check( &$reqData, $filtreOptions, &$check )
  {
    foreach ( $filtreOptions as $value ) {

			PhpF::empty( $reqData[$value] );

      $check[$value]['view'] =
				( $reqData[$value] == 'view' )
				? 'checked' : '';

      $check[$value]['hide'] =
				( $reqData[$value] == 'hide' )
				? 'checked' : '';

      $check[$value]['only'] =
				( $reqData[$value] == 'only' )
				? 'checked' : '';

      if ( !in_array(
					'checked',
					[
						$check[$value]['view'],
						$check[$value]['hide'],
						$check[$value]['only']
					] )
				) {
				if ( $value == 'archive' ) {
					$check[$value]['hide'] = 'checked';
				}
				else {
        	$check[$value]['view'] = 'checked';
				}
			}
    }
  }

	/**
	 * @param string $url
	 * @param array $select
 	 * @param array $check
	 * @param string $msg_prefix préfixe des messages à récupérer
	 *
	 * @return string
	 */
  public static function makeHeadForm ( $url, $select, $check, $tri, $headInfos, $msg_prefix )
  {
    $return = '
			<form action="' . $url . '" method="post" id="mgw-admin-form">';

    // TRI
    $return .= '
        <fieldset id="mgw-fieldset-tri">
					<legend>Tri:</legend>
                <label for="tri1">1°</label>
                <select id="tri1" name="tri1" >';
    foreach ( $headInfos['triOptions'] as $triOption )
      $return .= '<option value="'.$triOption.'" '.$select[1][$triOption].'>'.$triOption.'</option>';
    $return .= '</select>' . self::reverseCheck( 'a', $tri[1]['rev'] ) . '<br>
                <label for="tri2">2°</label>
                <select id="tri2" name="tri2" >
                  <option value="" '.$select[2]['...'].'>...</option>';
    foreach ( $headInfos['triOptions'] as $triOption )
      $return .= '<option value="'.$triOption.'" '.$select[2][$triOption].'>'.$triOption.'</option>';
    $return .= '</select>' . self::reverseCheck( 'b', $tri[2]['rev'] ) . '
        </fieldset>';
    // FILTRES
    $return .= '
        <fieldset id="mgw-fieldset-filtre"><legend>Filtres:</legend>
          <table>
            <tr>
              <td></td>
              <td><button onclick="mw.mgwFiltresShow()">voir</button></td>
              <td><button onclick="mw.mgwFiltresHide()">masquer</button></td>
              <td>seul</td>
            </tr>';
    $writeTitle = false;
    foreach ( $headInfos['filtreOptions'] as $filtreOption ) {
      foreach ( $headInfos['filtreOptionsTitles'] as $title => $titleOptions ) {
        if ( in_array( $filtreOption, $titleOptions ) && !$writeTitle ){
          $return .= '<tr><td>' . Msg::get( $msg_prefix.'-'.$title ) . '</td><td></td><td></td><td></td></tr>';
          $writeTitle = true;
        }
        elseif ( !in_array( $filtreOption, $titleOptions ) )
          $writeTitle = false;
      }
      $return .= '
            <tr>
              <td>' . Msg::get( $msg_prefix.'-'.$filtreOption ) . '</td>
              <td><input type="radio" id="mgw-'.$filtreOption.'-view" class="mgw-'.$filtreOption.' mgw-view mgw-radio"
                name="'.$filtreOption.'" value="view" '.$check[$filtreOption]['view'].'></td>
              <td><input type="radio" id="mgw-'.$filtreOption.'-hide" class="mgw-'.$filtreOption.' mgw-hide mgw-radio"
                name="'.$filtreOption.'" value="hide" '.$check[$filtreOption]['hide'].'></td>
              <td><input type="radio" id="mgw-'.$filtreOption.'-only" class="mgw-'.$filtreOption.' mgw-only mgw-radio"
                name="'.$filtreOption.'" value="only" '.$check[$filtreOption]['only'].'></td>
            </tr>';
    }
    $return .= '
          </table>
        </fieldset>
				<div id="mgw-admin-buttons">';

		if ( count( $headInfos['headActions'] ) > 0 ) {
			foreach ( $headInfos['headActions'] as $action => $label ) {
				$return .= self::actionButton( $action, $label );
			}
		}

		if ( count( $headInfos['headHiddenFields'] ) > 0 ) {
			foreach ( $headInfos['headHiddenFields'] as $field => $value ) {
				$return .= '<input id="mgw-hiddenfield-' . $field . '" type="hidden"
											name="' . $field . '" value="' . $value . '" />';
			}
		}

		$return .= '
					<input id="mgw-hidden-field-add" type="hidden" name="add" value="" />
					<input id="mgw-hidden-field-del" type="hidden" name="del" value="" />

					<input id="mgw-action-submit" type="submit" name="action" value="" hidden >
				</div>
      </form>';
    return $return;
  }

	/**
	 * @param array $showArray
 	 * @param array $actionInfo
	 * @return void
	 */
	public static function sort_show_array( &$show_array, $triRef ) {
		// tri d'un tableau à 2 dimentions
		global $tri;
		$tri = $triRef;
		uasort ( $show_array , function ( $a, $b ) {
			global $tri;
			$ltA = ( $tri[1]['rev'] == 'checked' ) ? 1 : -1 ;
			$gtA = ( $tri[1]['rev'] == 'checked' ) ? -1 : 1 ;
			$ltB = ( $tri[2]['rev'] == 'checked' ) ? 1 : -1 ;
			$gtB = ( $tri[2]['rev'] == 'checked' ) ? -1 : 1 ;

			if ( $a[$tri[1]['val']] == $b[$tri[1]['val']] ) {
				if ( !empty( $tri[2]['val'] ) ) {
					if ( $a[$tri[2]['val']] == $b[$tri[2]['val']] ) return 0;
					return ( $a[$tri[2]['val']] < $b[$tri[2]['val']] ) ? $ltB : $gtB;
				}
				return 0;
			}

			return ( $a[$tri[1]['val']] < $b[$tri[1]['val']] ) ? $ltA : $gtA;
		});
	}
}
