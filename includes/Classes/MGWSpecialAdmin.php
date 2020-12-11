<?php
/**
 * Archétype pour les pages Special:Admin
 */

namespace MediaWiki\Extension\MGWikiDev\Classes;

use SpecialPage;
use MediaWiki\Extension\MGWikiDev\Utilities\GetMessage as Msg;


class SpecialAdmin extends SpecialPage {

	/**
	 * @var array sort values
	 */
	private $select = [];

	/**
	 * @var array filter values
	 */
	private $check = [];

	/**
	 * @var array
	 */
	private $triOptions, $filtreOptions, $filtreOptionsTitles, $headActions, $headHiddenFields;


	/**
	 * @param array $specialSettings
	 * SpecialPage function __construct() args
	 * ex.: [ 'MySpecialPage', 'permission' ]
	 *
	 * ! à définir dans la classe fille:
	 * $this->triOptions = [];
	 * $this->filtreOptions = [];
	 * $this->filtreOptionsTitles = [];
	 * $this->headActions = [ 'action' => 'label' ]
	 * $this->headHiddenFields = [ 'name' => 'value' ]
	 */
	public function __construct( $specialSettings )
  {
		parent::__construct( ...$specialSettings );

		$out = $this->getOutput();
		$out->addModules('ext.mgwiki-specialadmin');
  }


	/*******************
	 **  TO OVERRIDE  **
	 *******************/

	/**
	 * TODO OVERRIDE
	 */
	public function execute( $sub )
  {
		# les fonctions doivent être appelées dans l'ordre suivant:
		$reqData = $this->set_reqData(); // TO OVERRIDE
    $this->set_select( $reqData );
    $this->set_check( $reqData );

    // (...)

    $out->addHTML( $this->makeHeadForm() );

		$out->addHTML( $this->makeHeadTable() ); // TO OVERRIDE (optionnel)

    // construction des données
    if ( isset( $reqData['action'] ) && $reqData['action'] == 'show' ) {
      $showArray = $this->getShowArray( );
    }

    // définition des actions (...)

		$this->sortShowArray( $showArray, $actionInfo = [] );

		$out->addHTML( $this->displayShowArray( $showArray ) ); // TO OVERRIDE

    $out->addHTML( $this->displayFooterActions() ); // TO OVERRIDE (optionnel)
  }

	/**
	 * TODO OVERRIDE
	 */
	private function set_reqData() {
		return $this->getRequest()->getPostValues();
	}

	/**
	 * TODO OVERRIDE
	 */
	private function makeHeadTable () {}

	/**
	 * TODO OVERRIDE
	 * @param array $row
	 * @return string
	 */
  private function displayShowArray ( &$row ) {
		$content = '';
		foreach( $showArray as $row ) {
			$table = '<tr><td> (...) </tr></td>';
			$class = 'active'; // 'active'|'inactive'|'deleted'
	    $content .= $this->tagRowTable( $table, $class );
			// (...)
		}
		return $this->tagShowArray( $content );
  }

	/**
	 * TODO OVERRIDE
	 * @return array $showArray
	 */
	private function getShowArray () {}

	/**
	 * TODO OVERRIDE
	 */
	private function displayFooterActions() {}


	/***********************
	 **  TO USE fuctions  **
	 ***********************/
	/**
	 * TODO USE in $this->displayShowArray()
	 * @param array $hidden [ 'name' => 'value' ]
	 * @param string $ctrlFn 'mw.maFonction()'
	 */
	private function actionButton (
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
	 * TODO USE in $this->displayShowArray()
	 */
	private function tagShowArray( $content ) {
		return '<div class="mgw-show-array" >' . $content . '</div>';
	}

	/**
	 * TODO USE in $this->displayShowArray()
	 * @param string $content '<tr><td> (...) </tr></td>'
	 * @param string $class 'active'|'inactive'|'deleted'
	 */
	private function tagRowTable( $content, $class ) {
		return '<table  class="mgw-row-table mgw-' . $class . '" >' . $content . '</table>';
	}

	/**
	 * TODO USE in $this->displayShowArray()
	 */
	private function addBox ( $value ) {
		return '<input type="checkbox" class="mgw-check-add" name="check-add" value="' . $value . '">';
	}

	/**
	 * TODO USE in $this->displayShowArray()
	 */
	private function delBox ( $value ) {
		return '<input type="checkbox" class="mgw-check-del" name="check-del" value="' . $value . '">';
	}

	/**
	 * TODO USE in $this->displayShowArray()
	 */
	private function addAllBox ( $label ) {
		return '<input id="mgw-check-add-all" type="checkbox" onclick="mw.mgwAddAll()" >
			<label for="mgw-check-add-all">' . $label . '</label>';
	}

	/**
	 * TODO USE in $this->displayShowArray()
	 */
	private function delAllBox ( $label ) {
		return '<input id="mgw-check-del-all" type="checkbox" onclick="mw.mgwDelAll()" >
			<label for="mgw-check-del-all">' . $label . '</label>';
	}


	/***********************
	 **  Native fuctions  **
	 ***********************/

  /**
   * @param array $reqData
   */
  private function set_select( &$reqData )
  {
		global $tri;
		$select = &$this->select;

    $tri[1] = ( isset( $reqData['tri1'] ) )
			? $reqData['tri1'] : 'user_id';

    $tri[2] = ( isset( $reqData['tri2'] ) )
			? $reqData['tri2'] : '';

    foreach ( $this->triOptions as $value ) {

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

		return $tri;
	}

  /**
   * @param array $reqData
   */
  private function set_check( &$reqData )
  {
    $check = &$this->check;

    foreach ( $this->filtreOptions as $value ) {

      $check[$value]['view'] =
				( isset( $reqData[$value] ) && $reqData[$value] == 'view' )
				? 'checked' : '';

      $check[$value]['hide'] =
				( isset( $reqData[$value] ) && $reqData[$value] == 'hide' )
				? 'checked' : '';

      $check[$value]['only'] =
				( isset( $reqData[$value] ) && $reqData[$value] == 'only' )
				? 'checked' : '';

      if ( !in_array(
					'checked',
					[
						$check[$value]['view'],
						$check[$value]['hide'],
						$check[$value]['only']
					] )
				) {
        $check[$value]['view'] = 'checked';
			}
    }
  }

	/**
	 * @param string $msg_prefix
	 * le préfixe des messages à récupérer
	 *
	 * @return string
	 */
  private function makeHeadForm ( $msg_prefix )
  {
    $select = &$this->select;
    $check = &$this->check;

    $return = '
			<form action="' . $this->selfURL() . '" method="post" >';

    // TRI
    $return .= '
        <fieldset><legend>Tri</legend>
          <table>
            <tr>
              <td>
                <label for="tri1">Première clé :</label>
                <select id="tri1" name="tri1" >';
    foreach ( $this->triOptions as $triOption )
      $return .= '<option value="'.$triOption.'" '.$select[1][$triOption].'>'.$triOption.'</option>';
    $return .= '</select>
              </td>
              <td>
                <label for="tri2">Deuxième clé :</label>
                <select id="tri2" name="tri2" >
                  <option value="" '.$select[2]['...'].'>...</option>';
    foreach ( $this->triOptions as $triOption )
      $return .= '<option value="'.$triOption.'" '.$select[2][$triOption].'>'.$triOption.'</option>';
    $return .= '</select>
              </td>
            <tr>
          </table>
        </fieldset>';
    // FILTRES
    $return .= '
        <fieldset><legend>Filtres</legend>
          <table>
            <tr>
              <td></td>
              <td><button onclick="mw.mgwFiltresShow()">voir</button></td>
              <td><button onclick="mw.mgwFiltresHide()">masquer</button></td>
              <td>seul</td>
            </tr>';
    $writeTitle = false;
    foreach ( $this->filtreOptions as $filtreOption ) {
      foreach ( $this->filtreOptionsTitles as $title => $titleOptions ) {
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
        </fieldset>';

		if ( isset( $this->headActions ) ) {
			foreach ( $this->headActions as $action => $label ) {
				$return .= $this->actionButton( $action, $label );
			}
		}

		if ( isset( $this->headHiddenFields ) ) {
			foreach ( $this->headHiddenFields as $field => $value ) {
				$return .= '<input id="mgw-hiddenfield-' . $field . '" type="hidden"
											name="' . $field . '" value="' . $value . '" />';
			}
		}

		$return .= '
				<input id="mgw-hidden-field-add" type="hidden" name="add" value="" />
				<input id="mgw-hidden-field-del" type="hidden" name="del" value="" />

				<input id="mgw-action-submit" type="submit" name="action" value="" hidden >
      </form>';
    return $return;
  }

	/**
	 * @param array $showArray
 	 * @param array $actionInfo
	 * @return void
	 */
	private function sortShowArray( &$showArray, $actionInfo = [] ) {
		if ( isset( $showArray ) ) {
			// tri d'un tableau à 2 dimentions
			uasort ( $showArray , function ($a, $b) {
				global $tri;
				if ( $a[$tri[1]] == $b[$tri[1]] ) {
					if ( $tri[2] != '' ) {
						if ( $a[$tri[2]] == $b[$tri[2]] ) return 0;
						return ( $a[$tri[2]] < $b[$tri[2]] ) ? -1 : 1;
					}
					return 0;
				}
				return ( $a[$tri[1]] < $b[$tri[1]] ) ? -1 : 1;
			});
		}
		else $showArray = [];

		if ( count( $actionInfo ) > 0 ) {
			$showArray = array_merge( $actionInfo, $showArray );
		}
	}

	/**
	 * @param array $get associative array of GET request parameters
	 * [key => value]
	 */
	private function selfURL( array $get = [] ) {
		return SpecialPage::getTitleFor( 'specialadmingrouptypes' )->getLinkURL( $get );
	}

	protected function getGroupName() {
		return 'mgwiki';
	}
}
