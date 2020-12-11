<?php

namespace MediaWiki\Extension\MGWikiDev;

/* mediawiki */
use SpecialPage;
use WikitextContent;
use MediaWiki\MediaWikiServices;
use Wikimedia\Rdbms\Database;
/* foreign */
use MediaWiki\Extension\MGWikiDev\Foreign\MGWUserMerge;
use MediaWiki\Extension\MGWikiDev\Foreign\MGWReplaceText;
/* MGWiki */
use MediaWiki\Extension\MGWikiDev\Utilities\GetMessage as Msg;
use MediaWiki\Extension\MGWikiDev\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWikiDev\Utilities\UsersFunctions as UserF;
use MediaWiki\Extension\MGWikiDev\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWikiDev\Classes\MGWUser;

/**
 * Page spéciale maintenance des utilisateurs
 */
class SpecialCheckAccounts extends SpecialPage {

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
	private $triOptions, $filtreOptions, $filtreOptionsTitles;


	/**
	 * Initialize the special page.
	 */
	public function __construct()
  {
		parent::__construct( 'SpecialCheckAccounts', 'editinterface' ); // restrict to sysops

    # définition des options
    $this->triOptions = ['user_id', 'user_name', 'page_template_nom', 'page_template_prenom', 'user_email'];
    $this->filtreOptions = [
      'page',
      'page_redirect',
      'page_template',
      'same_names',
      'user_email',
      'same_email',
      'user_real_name',
			'same_real_name',
			'mgw_exists',
      'sysop',
      'bureaucrat',
			'U3',
      'U2',
			'U1',
			'U0'
		];
    $this->filtreOptionsTitles = [
      'user_groups' => [ 'sysop', 'bureaucrat', 'U3', 'U2', 'U1', 'U0' ]
    ];
  }

	/**
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub )
  {
		global $wgUser;
		global $tri;

    $postData = $this->getRequest()->getPostValues();

    // définition de $tri et $select[]
		$tri = $this->set_select( $postData );

		// définition de $check[]
    $this->set_check( $postData );

    // affichage du formulaire d'entête
    $this->setHeaders();
		$out = $this->getOutput();
		$out->addModules('ext.mgwiki-specialcheckaccounts');
		$out->setPageTitle( Msg::get('specialcheckaccounts-title') );
    $out->addHTML( $this->makeHeadForm() );

    // ACTIONS
    $empty = [
      'user_id' => '',
      'user_name' => '',
      'page_url' => ''
    ];

    # affichage brut
    if ( isset( $postData['action'] ) && $postData['action'] == 'show' ) {
      $users = $this->getUsers( );
    }

    # emails en doublon uniquement
    if ( isset( $postData['action'] ) && $postData['action'] == 'emailduplicates' ) {

      $user_ids = $this->getEmailDuplicates();
      $users = $this->getUsers( implode( ',', $user_ids ) );

      if ( count( $users ) == 0 ) {
        $empty['user_id'] = '0';
        $empty['message'] = 'Aucun mail en doublon n\'a été trouvé.';
        $actionInfo[] = $empty;
      }
    }

    # utilisateurs cochés uniquement
    if ( isset( $postData['action'] ) && $postData['action'] == 'select' ) {
      $users = $this->getUsers( $postData['addusers'] );
    }

    # harmonisation de la casse 'Prénom NOM' sur les pages utilisateurs
    if ( isset( $postData['action'] ) && $postData['action'] == 'sanitize' ) {

			$actionInfo = [];
			$list = explode( ',', $postData['addusers'] );

			foreach ( $list as $user_id ) {

				$MGWuser = MGWUser::newFromUserId( (int)$user_id );
				if ( $MGWuser->sanitize_nom() && $MGWuser->sanitize_prenom() ) {
					$status = $MGWuser->update_page_template_names(
						Msg::get('specialcheckaccount-sanitize-summary')
					);
				}
				else
					$status = Status::newFailed( 'Nom ou Prénom non défini' );

				$mess = ( $status->done() ) ? 'SUCCES : ' : 'ECHEC : ';
				$mess .= $status->mess();

        $return = array(
  				'user_id' => $user_id,
  				'user_name' => $MGWuser->get_data()['user_name'],
          'page_url' => $MGWuser->get_data()['page_url'],
					'message' => $mess
				);

				$actionInfo[] = $return;
			}
      $users = $this->getUsers( $postData['addusers'] );
    }

    # harmoniser les comptes utilisateurs sur la base du formulaire Personne
    if ( isset( $postData['action'] ) && $postData['action'] == 'harmonize' ) {
			if ( $postData['harmonize-replace'] == 'oui' ) {
				$replace = true;
			} else {
				$replace = false;
			}
			$list = explode( ',', $postData['addusers'] );
			$actionInfo = [];
			foreach ( $list as $user_id ) {
				$MGWuser = MGWUser::newFromUserId( $user_id );
				if ( !$MGWuser->same_names() ){
					$status = $MGWuser->rename(
						Msg::get('specialcheckaccount-rename-summary'),
						$replace,
						$nsAll = true,
						$ns = null,
						$wgUser
					);
					$details = '';
					if ( is_array( $status->extra() ) && count( $status->extra() ) > 0 ) {
						foreach ( $status->extra() as $detail ) {
							$details .= ' / ' . $detail['message'];
						}
					}
					$mess = ( $status->done() ) ? 'SUCCES : ' : 'ECHEC : ';
					$mess .= $status->mess() . $details ;
				} else {
					$mess = '"user_name" est à jour.';
				}
				if ( !$MGWuser->same_real_name() ) {
					$MGWuser->update_real_name();
					$mess .= '<br>"user_real_name" a été mis à jour';
				}
				if ( !$MGWuser->same_email() ) {
					$realname = $MGWuser->update_email();
					$mess .= '<br>"user_email" a été mis à jour';
				}
        $return = array(
  				'user_id' => $user_id,
  				'user_name' => $MGWuser->get_data()['user_name'],
          'page_url' => $MGWuser->get_data()['page_url'],
					'message' => $mess
				);
				$actionInfo[] = $return;
			}
      $users = $this->getUsers( $postData['addusers'] );
    }

		# vider le compte utilisateur de ses infos (user_email, user_real_name et groupes )
		if ( isset( $postData['action'] ) && $postData['action'] == 'empty' ) {
			$list = explode( ',', $postData['deleteusers'] );
			$actionInfo = [];
			foreach ( $list as $user_id ) {
				$MGWuser = MGWUser::newFromUserId( $user_id );
				$info = $MGWuser->delete_all_groups();
				if ( !is_null( $MGWuser->get_email() ) ) {
					$MGWuser->delete_email();
					$info .= '<br>E-mail effacé.';
				}
				if ( is_string( $MGWuser->get_data()['user_real_name'] ) ) {
					$MGWuser->delete_real_name();
					$info .= '<br>real_name effacé.';
				}
				$page_url = ( is_string( $MGWuser->get_data()['page_url'] ) ) ? $MGWuser->get_data()['page_url'] : null;
        $return = array(
  				'user_id' => $user_id,
  				'user_name' => $MGWuser->get_data()['user_name'],
          'page_url' => $page_url,
					'message' => $info
				);
				$actionInfo[] = $return;
			}
			$users = $this->getUsers( $postData['deleteusers'] );
		}

    # effacer les utilisateurs
    ## contrôle des entrées
		$deleteAsked = isset( $postData['action'] ) && $postData['action'] == 'delete';
		$checkDelete = true;
    if ( $deleteAsked && count( explode( ',', $postData['addusers'] ) ) > 1 ) {
      $empty['message'] = '"in" :: un seul utilisateur de destination doit être coché.';
      $actionInfo[] = $empty;
      if ( isset( $postData['deleteusers'] ) && $postData['deleteusers'] != '' ) {
        $postData['addusers'] .= ',' . $postData['deleteusers'];
			}
      $users = $this->getUsers( $postData['addusers'] );
      $checkDelete = false;
    }
    if ( $deleteAsked && ( !isset( $postData['addusers'] ) || $postData['addusers'] == '' ) ) {
      $empty['message'] = '"in" :: un utilisateur de destination doit être coché.';
      $actionInfo[] = $empty;
      if ( isset( $postData['deleteusers'] ) && $postData['deleteusers'] != '' ) {
        $users = $this->getUsers( $postData['deleteusers'] );
      }
      $checkDelete = false;
    }
    if ( $deleteAsked && ( !isset( $postData['deleteusers'] ) || $postData['deleteusers'] == '' ) ) {
      $empty['message'] = 'Au moins un utilisateur à supprimer doit être coché :: "out"';
      $actionInfo[] = $empty;
      if ( isset( $postData['deleteusers'] ) && $postData['deleteusers'] != '' ) {
        $users = $this->getUsers( $postData['deleteusers'] );
      }
      $checkDelete = false;
    }
    ## délétion en tant que telle
    if ( $deleteAsked && $checkDelete ) {
      $targetUser = $this->getUsers( $postData['addusers'] );
      $deleteUsers = $this->getUsers( $postData['deleteusers'] );
      foreach ( $deleteUsers as $key => $deleteUser ) {
				// merge en effançant l'ancien utilisateur
				// implique suppression de compte et de page
        $merge = MGWUserMerge::execute(
					$deleteUser['user_id'],
					$targetUser[0]['user_id'],
					true,
					[ $this, 'msg' ]
				);
				if ( $merge->done() && $postData['delete-replace'] == 'oui' ) {
					// on remplace l'ancien nom par le nouveau dans le contenu de toutes les pages
	 			 $replace = new MGWReplaceText( [
	 				 "target" 	=> $deleteUser['user_name'],
	 				 "replace" 	=> $targetUser[0]['user_name'],
	 				 "regex" 		=> false,
	 				 "nsall" 		=> true,
	 				 "ns"  			=> null,
	 				 "summary" 	=> 'Tache automatisée lors de la fusion des utilisateurs.',
	 				 "user" 		=> $wgUser->getName()
	 			 ] );
				 $replace = $replace->execute();
			 }
			 if ( !$merge->done() ) {
				 $message = 'ECHEC :' . $deleteUser['user_name'] .
				 	' n\'a pas pu être supprimé (' . $merge->mess() . ')';
			 }
 			 elseif ( $merge->done() || !$replace->done() ) {
				 $details = '';
				 if ( is_array( $replace->extra() ) && count( $replace->extra() ) > 0 ) {
					 foreach ( $replace->extra() as $detail ) {
						 $details .= ' / ' . $detail['message'];
					 }
				 }
 				 $message = 'SUCCES PARTIEL :<br><ul>' .
				 	'<li>' . $deleteUser['user_name'] . ' a bien été supprimé (' . $merge->mess() . ')</li>' .
					'<li>Son nom n\'a pas été supprimé dans les pages (' . $replace->mess() . ')' . $details . '</li>';
 			 }
 			 else {
 				 $message = 'SUCCES :<br><ul>' .
				 	'<li>' . $deleteUser['user_name'] . 'a bien été supprimé (' . $merge->mess() . ')</li>';
					if ( isset( $replace ) ) {
		 				 $details = '';
		 				 foreach ( $replace->extra() as $detail ) {
		 					 $details .= ' / ' . $detail['message'];
		 				 }
						$message .= '<li>Son nom a bien été supprimé dans les pages (' . $replace->mess() . ')' . $details . '</li>';
					}
					$message .= '</ul>';
 			 }
       $deleteUser['message'] = $message;
       $actionInfo[] = $deleteUser;
      }
      $users = $this->getUsers( $postData['addusers'] );
    }

    # mise à jour de la table mgw_utilisateurs
		if ( isset( $postData['action'] ) && $postData['action'] == 'db_update' ) {
			$list = explode( ',', $postData['addusers'] );
			$actionInfo = [];
			foreach ( $list as $user_id ) {
				$MGWuser = MGWUser::newFromUserId( (int)$user_id );
				$status = $MGWuser->db_update();
				if ( $status->done() ) {
					$info = 'SUCCES: ' . $status->mess();
				} else {
					$info = 'ECHEC: ' . $status->mess();
				}
				$actionInfo[] = [
  				'user_id' => $user_id,
  				'user_name' => $MGWuser->get_data()['user_name'],
          'page_url' => $MGWuser->get_data()['page_url'],
					'message' => $info
				];
			}
      $users = $this->getUsers( $postData['addusers'] );
    }

    // TRI
    if ( isset( $users ) ) {
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
        'user_id' => '0',
        'user_name' => '',
        'page_url' => '',
        'message' => 'Aucun utilisateur ne correspond à la requête.'
      );
    }

    // AFFICHAGE DES UTILISATEURS
    $out->addHTML('<div class="mgw-displayusers" >');
		foreach( $users as $row ) {
      $out->addHTML( $this->displayUser( $row ) );
		}
    $out->addHTML('</div>');
    $out->addHTML( $this->displayActions() );
  }


  /**
   * @param array $postData
   */
  private function set_select( &$postData )
  {
		$select = &$this->select;

    $tri[1] = ( isset( $postData['tri1'] ) )
			? $postData['tri1'] : 'user_id';

    $tri[2] = ( isset( $postData['tri2'] ) )
			? $postData['tri2'] : '';

    foreach ( $this->triOptions as $value ) {

      if ( isset($postData['tri1'] ) && $postData['tri1'] == $value ) {
				$select[1][$value] = 'selected';
			} else {
				$select[1][$value] = '';
			}

      if ( isset($postData['tri2'] ) && $postData['tri2'] == $value ) {
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
   * @param array $postData
   */
  private function set_check( &$postData )
  {
    $check = &$this->check;

    foreach ( $this->filtreOptions as $value ) {

      $check[$value]['view'] =
				( isset( $postData[$value] ) && $postData[$value] == 'view' )
				? 'checked' : '';

      $check[$value]['hide'] =
				( isset( $postData[$value] ) && $postData[$value] == 'hide' )
				? 'checked' : '';

      $check[$value]['only'] =
				( isset( $postData[$value] ) && $postData[$value] == 'only' )
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
	 * @param string $option { 'tri' | 'filtres' }
	 * @return mixed (string ou false)
	 */
  private function makeHeadForm ( )
  {
    global $_SERVER;
    $select = &$this->select;
    $check = &$this->check;

    $return = '
			<form action="' . $_SERVER['PHP_SELF'] . '" method="post" >';

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
          $return .= '<tr><td>'.Msg::get( 'specialcheckaccounts-'.$title ).'</td><td></td><td></td><td></td></tr>';
          $writeTitle = true;
        }
        elseif ( !in_array( $filtreOption, $titleOptions ) )
          $writeTitle = false;
      }
      $return .= '
            <tr>
              <td>'.Msg::get( 'specialcheckaccounts-'.$filtreOption ).'</td>
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

				<input id="mgw-select-addusers" 				 type="hidden" name="addusers" 					value="all" />
				<input id="mgw-select-deleteusers" 			 type="hidden" name="deleteusers" 			value="none" />
				<input id="mgw-select-harmonize-replace" type="hidden" name="harmonize-replace" value="non" />
				<input id="mgw-select-delete-replace" 	 type="hidden" name="delete-replace" 		value="non" />
        <input id="mgw-select-action" 					 type="submit" name="action" 						value="show" hidden >

				<button id="mgw-action-do-submit" class="mgw-select-submit"
								onclick="mw.mgwShowUsers()" >afficher</button>
				<button id="mgw-action-select-submit" class="mgw-select-submit"
								onclick="mw.mgwSelectUsers()" >afficher la sélection</button>
				<button id="mgw-action-emailduplicates-submit" class="mgw-select-submit"
								onclick="mw.mgwEmailduplicates()" >afficher mails en doublons</button>
      </form>
			<table  class="mgw-specialcheckaccount-user" >
        <tr>
          <td class="mgw-td-first"><input id="mgw-users-addall" class="mgw-td-first" type="checkbox" onclick="mw.mgwAddAll()" ></td>
          <td class="mgw-td-label" style="font-size:small;"><label for="mgw-users-addall">add</label></td>
					<td class="mgw-td-1stlabel-top"><strong>USER</strong></td>
					<td><strong>PAGE</strong></td>
          <td style="text-align:right; font-size:small;"><label for="mgw-users-addall">delete</label></td>
          <td class="mgw-td-last"><input id="mgw-users-deleteall"
								type="checkbox" onclick="mw.mgwDeleteAll()" ></td>
        </tr>
      </table>';
    return $return;
  }


	/**
	 * @param array $row
	 * @return mixed (string HTML ou false)
	 */
  private function displayUser ( &$row ) {
    // si l'entrée correspond à un message on l'affiche...
    if ( isset( $row['message'] ) ) {
			$userName = ( !isset( $row['page_url'] ) || is_null(  $row['page_url'] ) )
				? $row['user_name'] : '<a href="'.$row['page_url'].'">' . $row['user_name'] . '</a>';
      return '<table>
								<tr>
									<td style="width=100px;"><small>'.$row['user_id'].'</small><br> ' . $userName . '</td>
									<td style="padding-left:15px;"><i>' . $row['message'] . '</i></td>
								</tr>
							</table>';
    }

    // sinon on affiche l'utilisateur:
		$class = ( $row['mgw_exists'] ) ? 'mgw-specialcheckaccount-mgw-in' : 'mgw-specialcheckaccount-mgw-out';
		$userName = $row['user_name'];
		$pageInfo = 'page utilisateur inexistante';

		if ( $row['page'] ) {
			$userName = '<a href="'.$row['page_url'].'" >' . $row['user_name'] . '</a>';

			if ( $row['page_redirect'] ) {
        $pageInfo = '#redirection: <a href="' . $row['page_redirect_url'] . '" >'
					. $row['page_redirect_title'] . '</a>';
			}
      elseif ( !$row['page_template'] ) {
        $pageInfo = '! {{Personne}}';
			}
			else {
				$pageInfo = '';
			}
		}

    $return = '<table  class="mgw-specialcheckaccount-user ' . $class . '" >
                <tr>
                  <td class="mgw-td-first">
										<input type="checkbox" class="mgw-user-add" name="adduser" value="'.$row['user_id'].'"></td>
                  <td class="mgw-td-label"><small><strong>'.$row['user_id'].'</strong></small></td>
                  <td class="mgw-username" userid="'.$row['user_id'].'" >' . $userName . '</td>
                  <td class="mgw-td-page"><i>' . $pageInfo . '</i></td>
                  <td class="mgw-td-last">
										<input type="checkbox" class="mgw-user-delete" name="deleteuser" value="'.$row['user_id'].'"></td>
                </tr>';
    $return .= '<tr>
                  <td></td>
                  <td>real_name</td>
                  <td>' . $row['user_real_name'] . '</td>
									<td>' . $row['page_template_prenom'] . ' ' . $row['page_template_nom'] . '</td>
                  <td></td>
                </tr>';
    $return .= '<tr>
                  <td></td>
                  <td>email</td>
                  <td>' . $row['user_email'] . '</td>
									<td>' . $row['page_template_email'] . '</td>
                  <td></td>
                </tr>';
    $return .= '<tr>
                  <td></td>
                  <td>groups</td>
                  <td rowspan="2" >' . implode( ', ', $row['user_groups'] ) . '</td>
                  <td></td>
                </tr>';
    $return .= '</table>';
    return $return;
  }

  /**
   * @return string HTML
   */
  private function displayActions() {
    global $_SERVER;
    return '<table>
              <tr>
                <td class="mgw-td-first">add</td>
                <td class="mgw-td-button">
									<button id="mgw-action-sanitize-submit" onclick="mw.mgwSanitize()" >SANITIZE</button></td>
								<td class="mgw-td-first"></td>
                <td class="mgw-td-legend">corrige la casse "Prénom NOM" dans le modèle {{Personne}}</td>
              </tr>
              <tr>
                <td class="mgw-td-first">add</td>
                <td class="mgw-td-button">
									<button id="mgw-action-harmonize-submit" onclick="mw.mgwHarmonize()" >HARMONIZE</button><br>
									<input type="checkbox" id="mgw-action-harmonize-replace" onclick="mw.mgwHarmonizeReplace()" >
									<label for="mgw-action-harmonize-replace">ReplaceText</label></td>
								<td></td>
								<td class="mgw-td-legend">modifie user_name, user_email et user_real_name selon les données du modèle {{Personne}}</td>
              </tr>
              <tr>
                <td></td>
                <td class="mgw-td-button">
									<button id="mgw-action-empty-submit" onclick="mw.mgwEmpty()" >EMPTY</button></td>
                <td class="mgw-td-first">del</td>
								<td class="mgw-td-legend">vide les champs real_name, email et groups du compte utilisateur</td>
              </tr>
              <tr>
                <td class="mgw-td-first">add</td>
                <td class="mgw-td-button">
									<button id="mgw-action-delete-submit" onclick="mw.mgwDelete()" >DELETE</button><br>
									<input type="checkbox" id="mgw-action-delete-replace" onclick="mw.mgwDeleteReplace()" >
									<label for="mgw-action-delete-replace">ReplaceText</label></td>
	              <td class="mgw-td-first">del</td>
								<td class="mgw-td-legend">supprime les comptes utilisateur "delete" en les fusionnant avec "add"</td>
              </tr>
              <tr>
                <td class="mgw-td-first">add</td>
                <td class="mgw-td-button">
									<button id="mgw-action-db_update-submit" onclick="mw.mgwDBupdate()" >DATABASE UPDATE</button></td>
                <td></td>
                <td></td>
              </tr>
            </table>';
  }


	/**
	 * Requête la base et les pages utilisateur
   *
   * @param string $list user_id values in comma-separated string
   * @param string $filter ( 'valid'|'page_template'|'page'|'page_redirect' )
	 * @param mixed $where string ou false (ex. : 'cat_pages > 0')
	 * @param mixed $options array ou false (ex. : array( 'ORDER BY' => 'cat_title ASC' ))
	 * @return array|null
	 */
	private function getUsers ( $list = '', $where = '', $options = false ) {

		// Construction de la liste des utilisateurs
		if ( empty( $list ) ) {

			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbr = $lb->getConnectionRef( DB_REPLICA );
	    $colnames = array( 'user_id' );

			if ( !$options ) {
	      $res = $dbr->select( 'user', $colnames, $where );
	    }
	    elseif ( is_array($options) ) {
	      $res = $dbr->select( 'user', $colnames, $where, __METHOD__, $options );
	    }
	    else throw new \Exception("Erreur SpecialCheckAccounts::getUsers() : arguments invalides", 1);

	    foreach( $res as $row ) {
				if ( empty( $list ) ) {
					$list = $row->user_id;
				}
				else {
					$list .= ',' . $row->user_id;
				}
			}
		}

		// construction des données
		$list = explode( ',', $list );
		$return = [];
    foreach( $list as $user_id ) {
			$user_id = (int)$user_id;

			$MGWuser = MGWUser::newFromUserId( $user_id );
			$MWuser = UserF::getUserFromId( $user_id );

			$data = $MGWuser->get_data();
			PhpF::false( $data['user_real_name'] );
			PhpF::false( $data['user_email'] );
									 $data['user_groups'] = $MWuser->getGroups();
									 $data['user_registration'] = $MWuser->getRegistration();
			PhpF::empty( $data['user_real_name'] );
			PhpF::empty( $data['user_email'] );
									 $data['mgw_exists'] = $MGWuser->get_mgw_exists();
									 $data['page'] = $MGWuser->get_page_exists();
			PhpF::false( $data['page_redirect'] );
			PhpF::false( $data['page_template'] );
			PhpF::empty( $data['user_'] );
			PhpF::empty( $data['page_template_prenom'] );
			PhpF::empty( $data['page_template_nom'] );
			PhpF::empty( $data['page_template_email'] );
									 $data['same_email'] = $MGWuser->same_email();
									 $data['same_names'] = $MGWuser->same_names();

			foreach ( $data['user_groups'] as $group ) {
									 $data[ $group ] = true;
			}
			foreach ( $this->filtreOptionsTitles['user_groups'] as $group ) {
				PhpF::false( $data[ $group ] );
			}

			// contrôle de la sortie selon les filtres
			$display = true;
			foreach ( $this->check as $item => $control ) {

				if ( array_key_exists( 'hide', $control )
						 && $control[ 'hide' ] == 'checked'
					 	 && ( !is_bool( $data[ $item ] ) || $data[ $item ] )
				) {
					$display = false;
				}

				if ( array_key_exists( 'only', $control )
						 && $control[ 'only' ] == 'checked'
					 	 && !$data[ $item ]
				) {
					$display = false;
				}
			}

			// sortie
			if ( $display ) {
  			$return[] = $data;
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
		$mails = [];

		// Requête sur les mails
		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
		$dbr = $lb->getConnectionRef( DB_REPLICA );
    $colnames = array( 'user_email' );
    $where = 'user_email IS NOT NULL';
    $options = array(
      "GROUP BY" => "user_email",
      "HAVING" => "COUNT(user_email) > 1"
    );
    $res = $dbr->select( 'user', $colnames, $where, __METHOD__, $options );

		foreach( $res as $row ) {
      $mails[] = $row->user_email;
		}

		// on récupère les utilisateurs correspondants
		$user_ids = [];
		foreach ( $mails as $mail ) {
	    $colnames = array( 'user_id' );
	    $where = 'user_email = \'' . $mail . '\'';
	    $res = $dbr->select( 'user', $colnames, $where );
			foreach( $res as $row ) {
	      $user_ids[] = $row->user_id;
			}
		}
    return $user_ids;
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
				$edit_summary = Msg::get('specialcheckaccount-sanitize-summary');
				$newcontent = new WikitextContent( $newtext );
				$userpage->doEditContent( $newcontent, $edit_summary, EDIT_AUTOSUMMARY );
      }
      else {
        $return[] = array(
  				'user_id' => $id,
  				'user_name' => $userpage->getTitle()->getFullText(),
          'page_url' => $userpage->getTitle()->getFullURL(),
          'message' => 'Aucune modification n\'a été faite.'
  			);
      }
    }
    return $return;
  }

	/**
   * @param string $oldName
   * @param string $newName
   * @return true|string $e
	 */
  private function userPageRedirect( $oldName, $newName ) {

    $oldUserPage = PageF::getPageFromTitleText( $oldName, NS_USER );

		if ( is_null( $oldUserPage ) ) {
			return Status::newFailed( "Redirection impossible: 'Utilisateur:$oldName' doit être une page existante." );
		}

    $newtext = '#REDIRECTION [[Utilisateur:' . $newName . ']]';
    $edit_summary = Msg::get('specialcheckaccount-merge-summary', [ $newName ] );
    $flags = EDIT_AUTOSUMMARY;
    $newcontent = new WikitextContent( $newtext );

    $oldUserPage->doEditContent( $newcontent, $edit_summary, $flags );

		return Status::newDone( "La page 'Utilisateur:$oldName' a bien été redirigée vers 'Utilisateur:$newName'.");
  }

	private function userEmptyAccount( $userName ) {

		$oldUser = UserF::getUserFromNames( $oldName, null, true );

		if ( is_null($oldUser) ) {
			return Status::newFailed( 'Nettoyage du compte utilisateur: $oldUser doit être un utilisateur valide.' );
		}

		$oldUser->setEmail('');
		foreach ( $oldUser->getGroups() as $group ) {
			$oldUser->removeGroup( $group );
		}
		$oldUser->saveSettings();

		return Status::newDone( "L'e-mail et les groupes de l'utilisateur $userName ont été effacés.");
	}

	protected function getGroupName() {
		return 'mgwiki';
	}
}
