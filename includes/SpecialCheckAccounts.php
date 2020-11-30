<?php

namespace MediaWiki\Extension\MGWikiDev;

use SpecialPage;
use MediaWiki\Extension\MGWikiDev\Utilities\GetJsonPage;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;
use MediaWiki\Extension\MGWikiDev\Foreign\MGWRenameuser;
use MediaWiki\Extension\MGWikiDev\Foreign\MGWUserMerge;
use MediaWiki\Extension\MGWikiDev\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWikiDev\Utilities\UsersFunctions as UserF;
use WikitextContent;

/**
 * Page spéciale demande de création de compte
 * Accessible du public (whitelisted)
 */
class SpecialCheckAccounts extends SpecialPage {

	/**
	 * Array of messages values
	 *
	 * @var array
	 */
	private $messages = [];

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
		parent::__construct( 'SpecialCheckAccounts', 'editinterface' ); // restrict to sysops

		# messages d'interface
		$this->messages = GetJsonPage::getData('messages');

    # définition des options
    $this->triOptions = ['id', 'username', 'nom', 'prenom', 'email'];
    $this->filtreOptions = [
      'nouserpage',
      'nousertemplate',
      'redirect',
      'nouseremail',
      'differentname-real',
      'differentname-page',
      'sysop',
      'bureaucrat',
      'U2'];
    $this->filtreOptionsTitles = [
      'usergroup' => [ 'sysop', 'bureaucrat', 'U2' ]
    ];
  }

	/**
	 * Shows the page to the user.
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
		$out->addModules('ext.mgwiki-specialcheckaccounts');
		$out->setPageTitle( $this->getMsg('specialcheckaccounts-title') );
    $out->addHTML( $this->makeHeadForm() );

    // ACTIONS
    $empty = [
      'id' => '',
      'username' => '',
      'userpagelink' => ''
    ];

      # affichage brut
    if ( isset( $postData['afficher'] ) ) {
      $users = self::getUsers( false );
    }

      # emails en doublon uniquement
    if ( isset( $postData['emailduplicates'] ) ) {
      $emailduplicates = self::getEmailDuplicates();
      $users = [];
      foreach ( $emailduplicates as $mail ) {
        if ( $mail != '' ) {
          $users = array_merge( $users, self::getUsers( false, 'user_email = \'' . $mail . '\'' ) );
        }
      }
      if ( sizeof( $users ) < 1 ) {
        $empty['id'] = '0';
        $empty['message'] = 'Aucun mail en doublon n\'a été trouvé.';
        $actionInfo[] = $empty;
      }
    }

      # utilisateurs cochés uniquement
    if ( isset( $postData['select'] ) ) {
      $users = self::getUsers( false, 'user_id IN(' . $postData['addusers'] . ')' );
    }

      # harmonisation de la casse 'Prénom NOM' sur les pages utilisateurs
    if ( isset( $postData['sanitize'] ) ) {
      $actionInfo = $this->sanitizeNomPrenom( explode( ',', $postData['addusers'] ) );
      $users = self::getUsers( false, 'user_id IN(' . $postData['addusers'] . ')' );
    }

      # renommer les utilisateurs sur la base du formulaire Personne (pages utilisateurs)
    if ( isset( $postData['rename'] ) ) {
      $reason = $this->getMsg('specialcheckaccount-rename-summary');
      $users = self::getUsers( false, 'user_id IN(' . $postData['addusers'] . ')' );
      foreach ( $users as $user ) {
        $renameInfo = MGWRenameuser::execute(
          $user['username'],                          // username actuel
          $user['prenom'] . ' ' . $user['nom'],       // username de destination
          true,                                       // renommer toutes les pages
          false,                                      // supprimer les redirections
          $reason
        );
        $message = ($renameInfo['done']) ? 'SUCCES' : 'ECHEC';
        $actionInfo[] = [
  				'id' => $user['id'],
  				'username' => $user['username'],
          'userpagelink' => $user['userpagelink'],
          'message' => $message . ' : ' . $renameInfo['message']
        ];
      }
    }

      # fusionner ou effacer les utilisateurs
        ## contrôle des entrées
    $checkMerge = true;
    $checkDelete = true;
    if ( isset( $postData['merge'] ) && count( explode( ',', $postData['addusers'] ) ) > 1 ) {
      $empty['message'] = '"in" :: un seul utilisateur de destination doit être coché.';
      $actionInfo[] = $empty;
      if ( isset( $postData['deleteusers'] ) && $postData['deleteusers'] != '' )
        $postData['addusers'] .= ',' . $postData['deleteusers'];
      $users = self::getUsers( false, 'user_id IN(' . $postData['addusers'] . ')' );
      $checkMerge = false;
    }
    if ( isset( $postData['merge'] ) && ( !isset( $postData['addusers'] ) || $postData['addusers'] == '' ) ) {
      $empty['message'] = '"in" :: un utilisateur de destination doit être coché.';
      $actionInfo[] = $empty;
      if ( isset( $postData['deleteusers'] )  && $postData['deleteusers'] != '' ) {
        $users = self::getUsers( false, 'user_id IN(' . $postData['deleteusers'] . ')' );
      }
      $checkMerge = false;
    }
    if ( ( isset( $postData['merge'] ) || isset( $postData['delete'] ) ) &&
      ( !isset( $postData['deleteusers'] ) || $postData['deleteusers'] == '' ) ) {
      $empty['message'] = 'Au moins un utilisateur doit être coché :: "out"';
      $actionInfo[] = $empty;
      if ( isset( $postData['deleteusers'] )  && $postData['deleteusers'] != '' ) {
        $users = self::getUsers( false, 'user_id IN(' . $postData['deleteusers'] . ')' );
      }
      $checkMerge = false;
      $checkDelete = false;
    }
        ## fusion ou délétion en tant que telle
    if ( isset( $postData['merge'] ) && $checkMerge ) {
      $delete = ( $postData['merge'] == 'delete' );
      $targetUser = self::getUsers( false, 'user_id IN(' . $postData['addusers'] . ')' );
      $usersToMerge = self::getUsers( false, 'user_id IN(' . $postData['deleteusers'] . ')' );
      foreach ( $usersToMerge as $key => $userToMerge ) {
        $r = MGWUserMerge::execute( $userToMerge['username'], $targetUser[0]['username'], $delete, [ $this, 'msg' ] );

        if ( !$delete && $r['done'] ) {
          $s = self::oldUserClean( $userToMerge['username'], $targetUser[0]['username'] );
        }
        else
          $s = true;

        if ( $r['done'] && $s )
          $m = 'SUCCES';
        elseif ( $r['done'] && !$s ) {
          $m = 'ECHEC';
          $r['message'] .= '<br> La fusion a réussi mais l\'ancien utilisateur n\'a pas été nettoyé.';
        }
        else
          $m = 'ECHEC';

        $userToMerge['message'] = $m . ' : ' . $r['message'];
        $actionInfo[] = $userToMerge;

        $uDisplay = $postData['addusers'];
        if ( !$delete ) $uDisplay .= ',' . $postData['deleteusers'];
          $users = self::getUsers( false, 'user_id IN(' . $uDisplay . ')' );
      }
    }

      # affichage des utilisateurs valides
    if ( isset( $postData['validusers'] ) ) {
      $users = self::getUsers( true );
    }

      # mise à jour de la table mgw_utilisateurs
    if ( isset( $postData['populate'] ) ) {
      $users = self::getUsers( true, 'user_id IN(' . $postData['addusers'] . ')' );
      foreach ( $users as $validUser ) {
        $r = self::updateUtilisateurs( $validUser );
        $mess = ( $r['done'] ) ? 'SUCCES : ' : 'ECHEC : ';
        $validUser['message'] = $mess . $r['message'];
        $actionInfo[] = $validUser;
      }
    }

    // TRI
    if ( isset($users) ) {
      // tri d'un tableau à 2 dimentions
      uasort ( $users , function ($a, $b) {
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
    else $users = [];
    if ( isset( $actionInfo ) ) {
      $users = array_merge( $actionInfo, $users );
    }
    if ( sizeof( $users ) < 1 ) {
      $users[] = array(
        'id' => '0',
        'username' => '',
        'userpagelink' => '',
        'message' => 'Aucun utilisateur ne correspond à la requête.'
      );
    }

    // AFFICHAGE DES UTILISATEURS
    $out->addHTML('<div class="mgw-displayusers" >');
    $select = &$this->select;
    $check = &$this->check;
		foreach( $users as $row ) {
      $test = true;
      //'nouserpage', 'nousertemplate', 'sysop', 'bureaucrat', 'U2', 'nouseremail', 'emailduplicate'
      if ( ( $check['nouserpage']['hide'] == 'checked' &&  is_null( $row['userpagelink']) ) ||
           ( $check['nouserpage']['only'] == 'checked' &&  !is_null( $row['userpagelink']) )
      ) $test = false;
      if ( ( $check['nousertemplate']['hide'] == 'checked' &&  is_null($row['nom']) ) ||
           ( $check['nousertemplate']['only'] == 'checked' &&  !is_null($row['nom']) )
      ) $test = false;
      if ( ( $check['redirect']['hide'] == 'checked' &&  !is_null($row['redirect']) ) ||
           ( $check['redirect']['only'] == 'checked' &&  is_null($row['redirect']) )
      ) $test = false;
      if ( ( $check['sysop']['hide'] == 'checked' && in_array('sysop', $row['groups']) ) ||
           ( $check['sysop']['only'] == 'checked' && !in_array('sysop', $row['groups']) )
      ) $test = false;
      if ( ( $check['bureaucrat']['hide'] == 'checked' && in_array('bureaucrat', $row['groups']) ) ||
           ( $check['bureaucrat']['only'] == 'checked' && !in_array('bureaucrat', $row['groups']) )
      ) $test = false;
      if ( ( $check['U2']['hide'] == 'checked' && in_array('U2', $row['groups']) ) ||
           ( $check['U2']['only'] == 'checked' && !in_array('U2', $row['groups']) )
      ) $test = false;
      if ( ( $check['nouseremail']['hide'] == 'checked' &&  $row['email'] == '' ) ||
           ( $check['nouseremail']['only'] == 'checked' &&  $row['email'] != '' )
      ) $test = false;
      if ( ( $check['differentname-real']['hide'] == 'checked' &&  $row['username'] != $row['realname'] ) ||
           ( $check['differentname-real']['only'] == 'checked' &&  $row['username'] == $row['realname'] )
      ) $test = false;
      if ( ( $check['differentname-page']['hide'] == 'checked' &&
              $row['username'] != $row['prenom'].' '.$row['nom'] && !is_null($row['nom']) ) ||
           ( $check['differentname-page']['only'] == 'checked' &&
              $row['username'] == $row['prenom'].' '.$row['nom'] && !is_null($row['nom']) )
      ) $test = false;
      if ( isset( $row['message'] ) ) $test = true;

      if ( $test ) {
        $out->addHTML( $this->displayUser( $row ) );
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
          $return .= '<tr><td>'.$this->getMsg( 'specialcheckaccounts-'.$title ).'</td><td></td><td></td><td></td></tr>';
          $writeTitle = true;
        }
        elseif ( !in_array( $filtreOption, $titleOptions ) )
          $writeTitle = false;
      }
      $return .= '
            <tr>
              <td>'.$this->getMsg( 'specialcheckaccounts-'.$filtreOption ).'</td>
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
        <input type="submit" name="emailduplicates" value="afficher mails en doublons" class="mgw-select-submit" >
      </form>';
    return $return;
  }


	/**
	 * @param array $row
	 * @return mixed (string HTML ou false)
	 */
  private function displayUser ( &$row ) {
    // si l'entrée correspond à un d'erreur on l'affiche...
    if ( isset( $row['message'] ) ) {
      return '<br>&nbsp&nbsp;&nbsp;<small>'.$row['id'].'</small> <a href="'.$row['userpagelink'].'">' . $row['username'] . '</a> :
              <i>&nbsp&nbsp;&nbsp;' . $row['message'] . '</i><br>';
    }
    // ... sinon on affiche l'utilisateur
    $return = '<table  class="mgw-specialcheckaccount-user" >
                <tr>
                  <td><small>'.$row['id'].'</small></td>
                  <td></td>
                  <td class="mgw-username" userid="'.$row['id'].'" >' . $row['username'] . '</td>
                  <td></td>
                </tr>';
    $return .= '<tr>
                  <td><input type="checkbox" class="mgw-user-add" name="adduser" value="'.$row['id'].'"></td>
                  <td>realname</td>
                  <td>' . $row['realname'] . '</td>
                  <td><input type="checkbox" class="mgw-user-delete" name="deleteuser" value="'.$row['id'].'"></td>
                </tr>';
    $return .= '<tr>
                  <td></td>
                  <td>email</td>
                  <td>' . $row['email'] . '</td>
                  <td></td>
                </tr>';
    $return .= '<tr>
                  <td></td>
                  <td>groups</td>
                  <td>' . implode( ', ', $row['groups'] ) . '</td>
                  <td></td>
                </tr>';
    if ( is_null( $row['userpagelink'] ) ) {
      $return .= '
                <tr>
                  <td></td>
                  <td class="mgw-td-userpage"><i>User page</i></td>
                  <td><i>page utilisateur inexistante</i></td>
                  <td></td>
                </tr>';
    }
    else {
      $return .= '
                <tr>
                  <td></td>
                  <td class="mgw-td-userpage"><a href="'.$row['userpagelink'].'" ><i>User page</i></a></td>';
      if ( !is_null($row['redirect'] ) )
        $return .= '
                  <td>#redirection: <a href="' . $row['redirectURL'] . '" >' . $row['redirect'] . '</a></td>
                  <td></td>
                </tr>';
      elseif ( is_null($row['nom'] ) )
        $return .= '
                  <td><i>Modèle {{Personne}} inexistant et redirection absence ou invalide</i></td>
                  <td></td>
                </tr>';
      else {
        $return .= '
                  <td>' . $row['prenom'] . ' ' . $row['nom'] . '</td>
                  <td></td>
                </tr>';
      }
    }
    $return .= '</table>';
    return $return;
  }

  /**
   * @return string HTML
   */
  private function displayActions() {
    global $_SERVER;
    return '<table  class="mgw-specialcheckaccount-user" >
              <tr>
                <td><input id="mgw-users-addall" type="checkbox" onclick="mw.mgwAddAll()" ></td>
                <td style="font-size:small;">in</td>
                <td style="text-align:right; font-size:small;">out</td>
                <td><input id="mgw-users-deleteall" type="checkbox" onclick="mw.mgwDeleteAll()" ></td>
              </tr>
              <tr>
                <td></td>
                <td><button id="mgw-action-select-submit" onclick="mw.mgwSelectUsers()" >SHOW</button></td>
                <td></td>
                <td><button id="mgw-action-merge-submit" onclick="mw.mgwMerge()" >MERGE</button></td>
              </tr>
              <tr>
                <td></td>
                <td><button id="mgw-action-sanitize-submit" onclick="mw.mgwSanitize()" >SANITIZE</button></td>
                <td>-> <button id="mgw-action-rename-submit" onclick="mw.mgwRename()" >RENAME</button></td>
                <td><button id="mgw-action-delete-submit" onclick="mw.mgwDelete()" >DELETE</button></td>
              </tr>
              <tr style="height:10px;"><td></td><td></td><td></td><td></td></tr>
              <tr>
                <td></td>
                <td><button id="mgw-action-populate-submit" onclick="mw.mgwPopulate()" >UPDATE</button></td>
                <td><- - - <button id="mgw-action-validusers-submit" onclick="mw.mgwValidUsers()" >SHOW ALL VALID USERS</button></td>
                <td></td>
              </tr>
            </table>

            <form id="mgw-actions-form" action="' . $_SERVER['PHP_SELF'] . '" method="post">
              <input id="mgw-select-addusers" type="hidden" name="addusers" value="test"/>
              <input id="mgw-select-deleteusers" type="hidden" name="deleteusers" />
              <input id="mgw-action-select"   type="submit" value="1" name="select"   class="mgw-useractions" hidden >
              <input id="mgw-action-sanitize" type="submit" value="1" name="sanitize" class="mgw-useractions" hidden >
              <input id="mgw-action-rename"   type="submit" value="1" name="rename"   class="mgw-useractions" hidden >
              <input id="mgw-action-merge"    type="submit" value="1" name="merge"    class="mgw-useractions" hidden >
              <input id="mgw-action-validusers" type="submit" value="1" name="validusers" class="mgw-useractions" hidden >
              <input id="mgw-action-populate" type="submit" value="1" name="populate" class="mgw-useractions" hidden >
            </form>';
  }


	/**
	 * Requête la base et les pages utilisateur
   *
   * @param bool $isValid
	 * @param mixed $where string ou false (ex. : 'cat_pages > 0')
	 * @param mixed $options array ou false (ex. : array( 'ORDER BY' => 'cat_title ASC' ))
	 * @return array|null
	 */
	private function getUsers ( $isValid, $where = false, $options = false ) {

		$return = [];

		/* Construction de la liste des utilisateurs depuis la db */
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
    $colnames = array(
      'user_id',
      'user_name',
      'user_real_name',
      'user_email');
    if ( !$where && !$options ) {
      $res = $dbr->select( 'user', $colnames );
    }
		elseif ( is_string($where) && !$options ) {
      $res = $dbr->select( 'user', $colnames, $where );
    }
    elseif ( is_string($where) && is_array($options) ) {
      $res = $dbr->select( 'user', $colnames, $where, __METHOD__, $options );
    }
    elseif ( !$where && is_array($options) ) {
      $res = $dbr->select( 'user', $colnames, '', __METHOD__, $options );
    }
    else throw new \Exception("Erreur SpecialCheckAccounts::getUsers() : arguments invalides", 1);

    ///////// PATCH TEMPORAIRE /////////////
		foreach( $res as $row ) {
			$user = UserF::getUserFromId( $row->user_id );
      $userpage = PageF::getPageFromTitleText( $user->getName(), NS_USER , true );
			if ( !is_null( $userpage ) ) {
				$userpagelink = $userpage->getTitle()->getFullURL();
				$infos = PageF::getPageTemplateInfos( $userpage, 'Personne', [ 'Nom', 'Prénom' ] );
				if ( !is_null( $infos ) ) {
					$nom = $infos['Nom'];
					$prenom = $infos['Prénom'];
          $redirect = null;
          $redirectURL = null;
          $valid = true;
				}
				else {
					$nom = null;
					$prenom = null;
          $valid = false;
          $redirect = PageF::getPageRedirect( $userpage );
          if ( !is_null( $redirect ) ) {
            // vérification de la validité de la redirection
            $screen = preg_match( '/^Utilisateur:(.*)$/', $redirect, $matches );
            if ( $screen < 1 ) {
              $redirect = null;
              $redirectURL = null;
            }
            else {
              $title = PageF::getTitleFromText( $matches[1], NS_USER, true );
              if ( is_null($title) ) $redirect = null;
              else $redirectURL = $title->getFullURL();
            }
          }
				}
			}
			else {
        $userpagelink = null;
        $redirect = null;
				$nom = null;
				$prenom = null;
        $valid = false;
			}

      if ( !isset( $redirectURL ) )
        $redirectURL = null;

      if ( !isset($row->user_email) || is_null($row->user_email) || $row->user_email == '' )
        $valid = false;

      if ( !$isValid )
        $valid = true;

      /////////////////////////////////////////////
      if ( $valid ) {
  			$return[] = array(
  				'id' => $row->user_id,
  				'username' => $row->user_name,
          'realname' => $row->user_real_name,
  				'email' => $row->user_email,
  				'groups' => $user->getGroups(),
          'registration' => $user->getRegistration(),
  				'nom' =>  $nom,
  				'prenom' => $prenom,
  				'userpagelink' => $userpagelink,
          'redirect' => $redirect,
          'redirectURL' => $redirectURL
  			);
      }
		}
    return $return;
	}

	/**
	 * Recherche les adresses mail en doublon
   *
	 * @return mixed (array ou null)
	 */
	private function getEmailDuplicates ( ) {
		$return = [];

		/* Requête */
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
    $colnames = array( 'user_email' );
    $where = '';
    $options = array(
      "GROUP BY" => "user_email",
      "HAVING" => "COUNT(user_email) > 1"
    );
    $res = $dbr->select( 'user', $colnames, $where, __METHOD__, $options );

		foreach( $res as $row ) {
      $return[] = $row->user_email;
		}
    return $return;
	}

	/**
   * @param array $idList : liste des id utilisateurs
   * @return array $return : utilisateurs et messages
	 */
  private function sanitizeNomPrenom ( $idList ) {
    $return = [];
    foreach ( $idList as $id ) {
			$user = UserF::getUserFromId( $id );
      $userpage = PageF::getPageFromTitleText( $user->getName(), NS_USER, true );
      $oldtext = $userpage->getContent()->getNativeData();

      // Prénom
      $newtext = preg_replace_callback(
        '/(\|Prénom=)([^\|]+)/',
        function ($text) {
          $space = false;
          $str = $text[2];
          if ( preg_match('/ /', $text[2]) > 0 ) {
            $str = str_replace(' ','-',$str);
            $space = true;
          }
          $str = explode( '-', $str);
          foreach ( $str as $key => $substr ) {
            $str[$key] = ucfirst( strtolower( $substr ) );
          }
          $text[2] = ( $space ) ? implode( ' ', $str ) : implode( '-', $str );
          return $text[1] . $text[2] ;
        },
        $oldtext );

      // Nom
      $newtext = preg_replace_callback(
        '/(\|Nom=)([^\|]+)/',
        function ($text) {
          return $text[1] . strtoupper($text[2]);
        },
        $newtext );

			// If there's at least one replacement, modify the page
			if ( $newtext != $oldtext ) {
				$edit_summary = $this->getMsg('specialcheckaccount-sanitize-summary');
				$newcontent = new WikitextContent( $newtext );
				$userpage->doEditContent( $newcontent, $edit_summary, EDIT_AUTOSUMMARY );
      }
      else {
        $return[] = array(
  				'id' => $id,
  				'username' => $userpage->getTitle()->getFullText(),
          'userpagelink' => $userpage->getTitle()->getFullURL(),
          'message' => 'Aucune modification n\'a été faite.'
  			);
      }
    }
    return $return;
  }

	/**
   * @param string $oldName
   * @param string $newName
   * @return array $return : utilisateurs et messages
	 */
  private function oldUserClean( $oldName, $newName ) {

    $oldUser = UserF::getUserFromNames( $oldName, null, true );
    if ( is_null($oldUser) ) {
      throw new \Exception('oldUserClean: $oldUser doit être un utilisateur valide.', 1);
      return false;
    }

    # nettoyage de l'utilisateur dans la base
    $oldUser->setEmail('');
    foreach ( $oldUser->getGroups() as $group ) {
      $oldUser->removeGroup( $group );
    }
    $oldUser->saveSettings();

    # nettoyage de la page utilisateur
    $oldUserPage = PageF::getPageFromTitleText( $oldName, NS_USER );
    $newtext = '#REDIRECTION [[Utilisateur:' . $newName . ']]';
    $edit_summary = $this->getMsg('specialcheckaccount-merge-summary', [ $newName ] );
    $flags = EDIT_AUTOSUMMARY;
    $newcontent = new WikitextContent( $newtext );
    $oldUserPage->doEditContent( $newcontent, $edit_summary, $flags );
    /* ne marche pas ... ??
    if ( PageF::writeContent( $oldUserPage, $newtext, $edit_summary, $flags ) )
      return true;
    else return false;
    */
  }

	/**
   * @param array $arrUser : une entrée du tableau retourné par $this->getUsers()
	 */
  public function updateUtilisateurs( $userArray ) {

    global $wgUser;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbw = $lb->getConnectionRef( DB_MASTER );

    $res = $dbw->select( 'mgw_utilisateurs', [ 'utilisateur_user_id', 'utilisateur_nom', 'utilisateur_prenom' ],
      'utilisateur_id = ' . $userArray['id'] );

    if ( sizeof( $res < 1 ) ) {
      $dbw->insert(
        'mgw_utilisateurs',
        [
          'utilisateur_user_id' => $userArray['id'],
          'utilisateur_nom' => $userArray['nom'],
          'utilisateur_prenom' => $userArray['prenom'],
          'utilisateur_updater_user_id' => $wgUser->getId(),
          'utilisateur_update_time' => date('Y-m-d H:i:s')
          ]
        );
      $done = true;
      $message = $userArray['prenom'] . ' ' . $userArray['nom'] . ' a été ajouté à la table mgw_utilisateurs.';
    }
    elseif ( sizeof( $res < 1 ) ) {
      $done = false;
      $message = 'Erreur : l\'utilisateur ' . $userArray['id'] . ' figure ' .
        sizeof( $res ) . ' fois dans la table mgw_utilisateurs';
    }
    elseif ( $res[0]->utilisateur_nom != $userArray['nom'] ) {
      $dbw->update(
        'mgw_utilisateurs', [
          'utilisateur_nom' => $userArray['nom'],
          'utilisateur_updater_user_id' => $wgUser->getId(),
          'utilisateur_update_time' => date('Y-m-d H:i:s')
        ],
        'utilisateur_id = ' . $userArray['id'] );
      $done = true;
      $message = $userArray['prenom'] . ' ' . $userArray['nom'] . ' a été mis à jour.';
    }
    elseif ( $res[0]->utilisateur_prenom != $userArray['prenom'] ) {
      $dbw->update(
        'mgw_utilisateurs',
        [
          'utilisateur_prenom' => $userArray['prenom'],
          'utilisateur_updater_user_id' => $wgUser->getId(),
          'utilisateur_update_time' => date('Y-m-d H:i:s')
        ],
        'utilisateur_id = ' . $userArray['id']
      );
      $done = true;
      $message = $userArray['prenom'] . ' ' . $userArray['nom'] . ' a été mis à jour.';
    }
    return [ 'done' => $done, 'message' => $message ];
  }

	/**
   * @param string $mess : clé du message
   * @param array $args : liste des arguments à passer dans le message ( $1, $2, etc. )
   * @return array $return : utilisateurs et messages
	 */
	private function getMsg ( $mess, $args = [] ) {
		if ( isset( $this->messages[ $mess ] ) ) {
      $out = $this->messages[ $mess ];
      if ( sizeof($args) > 0 ) {
        foreach ( $args as $key => $arg ) {
          $needle = '$' . ($key + 1);
          $out = str_replace( $needle, $arg, $out );
        }
      }
			return $out;
		}
		else {
      $link = GetJsonPage::getLink('messages');
			return '<a href="'.$link.'">&lt;' . $mess . '&gt;</a>';
		}
	}

	protected function getGroupName() {
		return 'mgwiki';
	}
}
