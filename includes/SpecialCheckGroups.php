<?php

namespace MediaWiki\Extension\MGWikiDev;

use SpecialPage;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;
use WikitextContent;

use MediaWiki\Extension\MGWikiDev\Utilities\GetMessage as Msg;
use MediaWiki\Extension\MGWikiDev\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWikiDev\Utilities\UsersFunctions as UserF;

/**
 * Page spéciale demande de création de compte
 */
class SpecialCheckGroups extends SpecialPage {

	/**
	 * @var array
	 */
	private $triOptions, $filtreOptions, $filtreOptionsTitles;

	/**
	 * Array of sort values
	 *
	 * @var array
	 */
	private $select = [];

	/**
	 * Array of filter values
	 *
	 * @var array
	 */
	private $check = [];

	/**
	 * Initialize the special page.
	 */
	public function __construct()
  {
		parent::__construct( 'SpecialCheckGroups', 'editinterface' ); // restrict to sysops

    # définition des options
    $this->triOptions = ['page_id', 'page_title', 'referent', 'year'];
    $this->filtreOptions = [
      'archive',
      'nogrouptemplate',
      'redirect'
		];
    $this->filtreOptionsTitles = [
    ];
  }

	/**
	 * @param string $sub The subpage string argument (if any).
	 */

	public function execute( $sub )
  {
    global $tri;
    $postData = $this->getRequest()->getPostValues();

    // définition de $select[] et $check[]
    $this->setOptions( $postData );

    // affichage du formulaire d'entête
    $this->setHeaders();
		$out = $this->getOutput();
		$out->addModules('ext.mgwiki-specialcheckgroups');
		$out->setPageTitle( Msg::get('specialcheckgroups-title') );
    $out->addHTML( $this->makeHeadForm() );

    // ACTIONS

      # affichage brut
    if ( isset( $postData['afficher'] ) ) {
      $groups = $this->getGroups( false );
    }

      # pages groupe cochés uniquement
    if ( isset( $postData['select'] ) ) {
      $groups = self::getGroups( false, 'page_id IN(' . $postData['addgroups'] . ')' );
    }

      # affichage des groupes valides uniquement
    if ( isset( $postData['validgroups'] ) ) {
      $groups = self::getGroups( true );
    }

      # mise à jour des tables mgw_group et mgw_membres
    if ( isset( $postData['populate'] ) ) {
      $groups = self::getGroups( true, 'user_id IN(' . $postData['addgroups'] . ')' );
      foreach ( $groups as $validGroup ) {
        /*
				$r = UserF::setMGWUser( $validUser['id'], $validUser['nom'], $validUser['prenom'] );
        $mess = ( $r['done'] ) ? 'SUCCES : ' : 'ECHEC : ';
        $validUser['message'] = $mess . $r['message'];
        $actionInfo[] = $validUser;
        */
      }
    }

    // TRI
    if ( isset($groups) ) {
      // tri d'un tableau à 2 dimentions
      uasort ( $groups , function ($a, $b) {
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
    else $groups = [];
    if ( isset( $actionInfo ) ) {
      $groups = array_merge( $actionInfo, $groups );
    }
    if ( sizeof( $groups ) < 1 ) {
      $groups[] = array(
        'page_id' => '0',
        'page_title' => '',
        'page_url' => '',
        'message' => 'Aucun utilisateur ne correspond à la requête.'
      );
    }

    // AFFICHAGE DES GROUPES
    $out->addHTML('<div class="mgw-displaygroups" >');
    $select = &$this->select;
    $check = &$this->check;
		foreach( $groups as $row ) {
      /*
      filtres:
        'archive',
        'nogrouptemplate',
        'redirect'
      champs:
        'archive'
        'template'
        'page_id'
        'page_title'
        'page_url'
        'type'
        'institution'
        'referent'
        'membres'
        'year'
        'redirect'
        'redirect_url'
      */
      $test = true;
      //'nouserpage', 'nousertemplate', 'sysop', 'bureaucrat', 'U2', 'nouseremail', 'emailduplicate'
      if ( ( $check['archive']['hide'] == 'checked' &&  $row['archive'] == 'Oui' ) ||
           ( $check['archive']['only'] == 'checked' &&  $row['archive'] != 'Oui' )
      ) $test = false;
      if ( ( $check['nogrouptemplate']['hide'] == 'checked' &&  !$row['template'] ) ||
           ( $check['nogrouptemplate']['only'] == 'checked' &&  $row['template'] )
      ) $test = false;
      if ( ( $check['redirect']['hide'] == 'checked' &&  !is_null($row['redirect']) ) ||
           ( $check['redirect']['only'] == 'checked' &&  is_null($row['redirect']) )
      ) $test = false;

      if ( isset( $row['message'] ) ) $test = true;

      if ( $test ) {
        $out->addHTML( $this->displayGroup( $row ) );
      }
		}
    $out->addHTML('</div>');
    $out->addHTML( $this->displayActions() );
  }


  /**
   * @param array $postData
   */
  private function setOptions( &$postData )
  {
    global $tri;
    $select = &$this->select;
    $check = &$this->check;

    $tri[1] = ( isset( $postData['tri1'] ) ) ? $postData['tri1'] : 'id';
    $tri[2] = ( isset( $postData['tri2'] ) ) ? $postData['tri2'] : '';

    foreach ( $this->triOptions as $value ) {
      if ( isset($postData['tri1'] ) && $postData['tri1'] == $value ) $select[1][$value] = 'selected';
      else $select[1][$value] = '';
      if ( isset($postData['tri2'] ) && $postData['tri2'] == $value ) $select[2][$value] = 'selected';
      else $select[2][$value] = '';
      if ( !in_array( 'selected', $select[2] ) ) $select[2]['...'] = 'selected';
      else $select[2]['...'] = '';
    }

    foreach ( $this->filtreOptions as $value ) {
      $check[$value]['view'] = ( isset( $postData[$value] ) && $postData[$value] == 'view' ) ? 'checked' : '';
      $check[$value]['hide'] = ( isset( $postData[$value] ) && $postData[$value] == 'hide' ) ? 'checked' : '';
      $check[$value]['only'] = ( isset( $postData[$value] ) && $postData[$value] == 'only' ) ? 'checked' : '';
      if ( !in_array('checked', [ $check[$value]['view'], $check[$value]['hide'], $check[$value]['only'] ] ) )
        $check[$value]['view'] = 'checked';
    }
  }


	/**
	 * @param string $option { 'tri' | 'filtres' }
	 * @return mixed (string ou false)
	 */
  private function makeHeadForm ( )
  {
    global $_SERVER;
    $select = &$this->select;
    $check = &$this->check;

    $return = '<form action="' . $_SERVER['PHP_SELF'] . '" method="post" >';
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
              <td><button onclick="mw.mgwFiltresShow()">afficher</button></td>
              <td><button onclick="mw.mgwFiltresHide()">masquer</button></td>
              <td>seul</td>
            </tr>';
    $writeTitle = false;
    foreach ( $this->filtreOptions as $filtreOption ) {
      foreach ( $this->filtreOptionsTitles as $title => $titleOptions ) {
        if ( in_array( $filtreOption, $titleOptions ) && !$writeTitle ){
          $return .= '<tr><td>'.Msg::get( 'specialcheckgroups-'.$title ).'</td><td></td><td></td><td></td></tr>';
          $writeTitle = true;
        }
        elseif ( !in_array( $filtreOption, $titleOptions ) )
          $writeTitle = false;
      }
      $return .= '
            <tr>
              <td>'.Msg::get( 'specialcheckgroups-'.$filtreOption ).'</td>
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
        <input type="submit" name="afficher" value="afficher tous" class="mgw-select-submit" >
      </form>';
    return $return;
  }


	/**
	 * @param array $row
	 * @return mixed (string HTML ou false)
	 */
  private function displayGroup ( &$row ) {
    // si l'entrée correspond à un d'erreur on l'affiche...
    if ( isset( $row['message'] ) ) {
      return '<br>&nbsp&nbsp;&nbsp;<small>'.$row['page_id'].'</small> <a href="'.$row['page_url'].'">' . $row['page_title'] . '</a> :
              <i>&nbsp&nbsp;&nbsp;' . $row['message'] . '</i><br>';
    }
    // ... sinon on affiche l'utilisateur
    $return = '<table  class="mgw-specialcheckgroups-group" >
                <tr>
                  <td><small>'.$row['page_id'].'</small></td>
                  <td></td>
                  <td class="mgw-grouptitle" pageid="'.$row['page_id'].'" >' . $row['page_title'] . '</td>
                  <td></td>
                </tr>';
    if ( $row['template'] ) {
      $return .= '<tr>
                    <td><input type="checkbox" class="mgw-group-add" name="addgroup" value="'.$row['page_id'].'"></td>
                    <td>archive</td>
                    <td>' . $row['archive'] . '</td>
                    <td><input type="checkbox" class="mgw-group-delete" name="deletegroup" value="'.$row['page_id'].'"></td>
                  </tr>';
      $return .= '<tr>
                    <td></td>
                    <td>institution</td>
                    <td>' . $row['institution'] . '</td>
                    <td></td>
                  </tr>';
      $return .= '<tr>
                    <td></td>
                    <td>type</td>
                    <td>' . $row['type'] . '</td>
                    <td></td>
                  </tr>';
      $return .= '<tr>
                    <td></td>
                    <td>referent</td>
                    <td>' . $row['referent'] . '</td>
                    <td></td>
                  </tr>';
      $return .= '<tr>
                    <td></td>
                    <td>membres</td>
                    <td>' . $row['membres'] . '</td>
                    <td></td>
                  </tr>';
    }
    else {
      $return .= '
                <tr>
                  <td></td>
                  <td></td>
                  <td><i>modèle {{Groupe}} inexistant</i></td>
                  <td></td>
                </tr>';
    }
    if ( !is_null($row['redirect'] ) ) {
      $return .= '
                <tr>
                  <td></td>
                  <td></td>
                  <td>#redirection: <a href="' . $row['redirect_url'] . '" >' . $row['redirect'] . '</a></td>
                  <td></td>
                </tr>';
    }
    $return .= '</table>';
    return $return;
  }

  /**
   * @return string HTML
   */
  private function displayActions() {
    global $_SERVER;
    return '<table  class="mgw-specialcheckgroups-user" >
              <tr>
                <td><input id="mgw-groups-addall" type="checkbox" onclick="mw.mgwAddAll()" ></td>
                <td style="font-size:small;">in</td>
                <td style="text-align:right; font-size:small;">out</td>
                <td><input id="mgw-groups-deleteall" type="checkbox" onclick="mw.mgwDeleteAll()" ></td>
              </tr>
              <tr>
                <td></td>
                <td><button id="mgw-action-select-submit" onclick="mw.mgwSelectGroups()" >SHOW</button></td>
                <td></td>
                <td></td>
              </tr>
              <tr style="height:10px;"><td></td><td></td><td></td><td></td></tr>
              <tr>
                <td></td>
                <td><button id="mgw-action-populate-submit" onclick="mw.mgwPopulate()" >UPDATE</button></td>
                <td><- - - <button id="mgw-action-validgroups-submit" onclick="mw.mgwValidGroups()" >SHOW ALL VALID GROUPS</button></td>
                <td></td>
              </tr>
            </table>

            <form id="mgw-actions-form" action="' . $_SERVER['PHP_SELF'] . '" method="post">
              <input id="mgw-select-addgroups" type="hidden" name="addgroups" value="test"/>
              <input id="mgw-select-deletegroups" type="hidden" name="deletegroups" />
              <input id="mgw-action-select"   type="submit" value="1" name="select"   class="mgw-groupactions" hidden >
              <input id="mgw-action-validgroups" type="submit" value="1" name="validgroups" class="mgw-groupactions" hidden >
              <input id="mgw-action-populate" type="submit" value="1" name="populate" class="mgw-groupactions" hidden >
            </form>';
  }

	/**
	 * Requête la base et les pages groupe
   *
   * @param bool $isValid
	 * @param mixed $where string ou null
	 * @param mixed $options array ou false (ex. : array( 'ORDER BY' => 'cat_title ASC' ))
	 * @return array|null
	 */
	private function getGroups ( $isValid, $where = null, $options = false ) {

		$return = [];

		/* Construction de la liste des groupes depuis les pages */
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
    $colnames = array( 'page_id', 'page_title' );
    $cond = 'page_namespace = 730';
    if ( !is_null( $where ) ) $cond .= ' AND ' . $where;

		if ( !$options ) {
      $res = $dbr->select( 'page', $colnames, $cond );
    }
    elseif ( is_array($options) ) {
      $res = $dbr->select( 'page', $colnames, $cond, __METHOD__, $options );
    }
    else throw new \Exception("Erreur SpecialCheckGroups::getGroups() : arguments invalides", 1);

    ///////// PATCH TEMPORAIRE /////////////
		foreach( $res as $row ) {
      $group = [
        'page_id'     => $row->page_id,
        'page_title'  => $row->page_title,
        'page_url'    => null,
        'template'    => false,
        'archive'     => null,
        'type'        => null,
        'institution' => null,
        'referent'    => null,
        'membres'     => null,
        'year'        => null,
        'redirect'    => null,
        'redirect_url' => null,
      ];
      $valid = false;

      $page = PageF::getPageFromId( $row->page_id );
			if ( !is_null( $page ) ) {
				$group['page_url'] = $page->getTitle()->getFullURL();
				$infos = PageF::getPageTemplateInfos(
          $page,
          'Groupe',
          [
            'Archivé',
            'Type de groupe',
            'Institution de rattachement',
            'Tuteur ou modérateur',
            'Membres',
            'Année'
            ]
          );
				if ( !is_null( $infos ) ) {
          $group['archive'] = $infos['Archivé'];
          $group['type'] = $infos['Type de groupe'];
          $group['institution'] = $infos['Institution de rattachement'];
          $group['referent'] = $infos['Tuteur ou modérateur'];
          $group['membres'] = $infos['Membres'];
          $group['year'] = $infos['Année'];
          $group['template'] = true;
          $valid = true;
				}
				else {
          $group['redirect'] = PageF::getPageRedirect( $page );
          if ( !is_null( $group['redirect'] ) ) {
            // vérification de la validité de la redirection
            $screen = preg_match( '/^Groupe:(.*)$/', $group['redirect'], $matches );
            if ( $screen > 0 ) {
              $title = PageF::getTitleFromText( $matches[1], NS_GROUP, true );
              if ( !is_null( $title ) ) $group['redirect_url'] = $title->getFullURL();
            }
          }
				}
			}
      if ( !$isValid ) $valid = true;
      /////////////////////////////////////////////
      if ( $valid ) {
        $return[] = $group;
      }
		}
    return $return;
	}

	protected function getGroupName() {
		return 'mgwiki';
	}
}
