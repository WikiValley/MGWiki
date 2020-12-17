<?php

namespace MediaWiki\Extension\MGWikiDev;

use SpecialPage;
use HTMLForm;
use MediaWiki\MediaWikiServices;

use MediaWiki\Extension\MGWikiDev\Utilities\GetMessage as Msg;
use MediaWiki\Extension\MGWikiDev\Utilities\MgwDataFunctions as DbF;
use MediaWiki\Extension\MGWikiDev\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWikiDev\Utilities\HtmlFunctions as HtmlF;
use MediaWiki\Extension\MGWikiDev\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWikiDev\Classes\MGWStatus as Status;

 // https://www.mediawiki.org/wiki/HTMLForm

class SpecialAdminGroupTypes extends SpecialPage {

	private $id, $nom, $admin_level, $user_level, $duration;

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
	private $tri, $headInfos;

	public function __construct() {
		parent::__construct( 'specialadmingrouptypes', 'editinterface' ); // restrict to sysops

    # définition des options
		$this->headInfos['triOptions'] = ['nom', 'admin_level', 'user_level', 'default_duration', 'update_time', 'updater_id'];
    $this->headInfos['filtreOptions'] = [ 'archive' ];
    $this->headInfos['filtreOptionsTitles'] = [];
		$this->headInfos['headActions'] = [ 'refresh' => 'rafraîchir' ];
    $this->headInfos['headHiddenFields'] = [];
	}

	public function execute( $sub ) {

		// récupération et contrôle des entrées
		$reqData = $this->set_reqData();
    HtmlF::set_select( $reqData, $this->headInfos['triOptions'], $this->tri, $this->select );
		HtmlF::set_check( $reqData, $this->headInfos['filtreOptions'], $this->check );

		// préparation de l'affichage
    $this->setHeaders();
    $out = $this->getOutput();
		$out->addModules('ext.mgwiki-specialadmin');
		$out->addModules('ext.mgwiki-specialadmingrouptypes');
		$out->enableOOUI();

		// sélection du mode d'affichage
		$edit = ( $reqData['action'] == 'edit' );
		$history = ( $reqData['action'] == 'history' && !is_null( $reqData['id'] ));
		$show = ( ! $edit && ! $history );

		// SUBMIT
		if ( isset( $reqData['submit'] ) ) {
			$status = $this->formCallback( $reqData, $edit, $show );
			$done = ( $status->done() ) ? 'true' : 'false';
			$out->addHTML( HtmlF::alertMessage( $status ) );
		}

		// ACTIONS
		# undo
		if ( $reqData['action'] == 'undo' && isset( $reqData['id'] ) && isset( $reqData['archive'] ) ) {
			$undo = $this->undo( $reqData['id'], $reqData['archive'] );
			$out->addHTML( HtmlF::alertMessage( $undo ) );
			$history = true;
			$show = false;
		}

		# delete
		if ( $reqData['action'] == 'delete' && isset( $reqData['id'] ) ) {
			$delete = $this->delete( $reqData['id'], 'Suppression de type de groupe.' );
			$out->addHTML( HtmlF::alertMessage( $delete ) );
			$history = false;
			$show = true;
		}

		# undelete
		if ( $reqData['action'] == 'undelete' && isset( $reqData['archive'] ) ) {
			$undelete = $this->undelete( $reqData['archive'] );
			$out->addHTML( HtmlF::alertMessage( $undelete ) );
			$history = true;
			$show = false;
		}

		// VUES
		# history
		if ( $history ) {

			$show_array = $this->getHistoryShowArray( $reqData['id'] );

			$out->setPageTitle( Msg::get('specialadmingrouptypes-title-history', [ $show_array[0]['nom'] ] ) );
			$out->addHTML( $this->makeView( $show_array ) );
			$out->addHTML( '<br>' .
				(string)new \OOUI\ButtonWidget( [	'href' => $this->selfURL(), 'label' => 'retour' ] )
				);
		}

		# show
    if ( $show ) {

			$show_array = $this->getShowArray( '', [ 'ORDER BY' => 'nom' ] );

			if ( count( $show_array ) > 0 ) {
				HtmlF::sort_show_array( $show_array, $this->tri );
			}

			$out->setPageTitle( Msg::get( 'specialadmingrouptypes-title' ) );
	    $out->addHTML( HtmlF::makeHeadForm( $this->selfURL(), $this->select, $this->check, $this->tri, $this->headInfos, 'specialadmingrouptypes' ) );
			$out->addHTML( $this->makeView( $show_array ) );
			$out->addHTML( '<br>' .
				(string)new \OOUI\ButtonWidget( [
					'href' => $this->selfURL( [	'action' => 'edit' ] ),
					'label' => 'nouveau',
					'flags' => [
						'primary',
						'progressive'
					]
				] )
			);
    }

    # edit
    if ( $edit ) {
			$this->setData( $reqData );
			if ( !empty( $this->nom ) ) {
				$out->setPageTitle( Msg::get('specialadmingrouptypes-title-edit', [ $this->nom ] ) );
			}
			else {
				$out->setPageTitle( Msg::get('specialadmingrouptypes-title-edit-new' ) );
			}
			$out->addHTML( $this->makeOouiForm( $reqData ) );
    }
  }

	private function set_reqData() {
		$reqData = $this->getRequest()->getValues();
		PhpF::empty( $reqData['action'] );
		PhpF::empty( $reqData['id'] );
		PhpF::int( $reqData['id'] );
		PhpF::empty( $reqData['reverse-a'] );
		PhpF::empty( $reqData['reverse-b'] );
		return $reqData;
	}


	/**
	 * @param int $id
	 * @param int $archive_id
	 */
	private function undo( $id, $archive_id ) {

		global $wgUser;

		$old = DbF::select_clean(
			'archive_groupe_type',
			[ 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration' ],
			'archive_id = ' . $archive_id
		)[0];

		$actual = DbF::select_clean(
			'groupe_type',
			[ 'id', 'nom', 'page_id' ],
			'id = ' . $id
		)[0];

		$currentNames = DbF::select_clean(
			'groupe_type',
			[ 'id', 'nom' ]
		);

		// on interdit la création de doublon
		foreach ( $currentNames as $row ) {
			if ( $row['id'] != $old['id'] &&
				strtolower( $row['nom'] ) == strtolower( $old['nom'] ) )
			{
				return Status::newFailed( 'Le nom <strong>'.$old['nom'] .
					'</strong> correspond à un autre type de groupe en cours d\'utilisation.'
					.' Veuillez rétablir les modifications manuellement avec un autre nom.' );
			}
		}

		// on vérifie l'existence de la page
		$page_id = PageF::pageID( 'MGWiki:Types de groupes/' . $actual['nom'] );
		if ( $page_id < 1 ) {
			$actualPage = PageF::newPage(
				'Types de groupes/' . $actual['nom'],
				NS_PROJECT,
				'{{Modèle:Types de groupes}}',
				Msg::get( 'specialadmingrouptypes-newpage-summary' ),
				$wgUser
			);
			if( !$actualPage->done() ) {
				return Status::newFailed( 'Action annulée: la page <strong>MGWiki:Types de groupes/' .
					$actual['nom'] . '</strong> n\'a pas pu être créée. (' . $actualPage->mess() . ')'  );
			}
			$old['page_id'] = $actualPage->extra();
		}
		elseif ( $old['page_id'] != $page_id ) {
			$old['page_id'] != $page_id;
		}

		// rename si besoin
		if ( $old['nom'] != $actual['nom'] ) {
			$rename = PageF::renamePage(
				'Types de groupes/' . $actual['nom'],
				NS_PROJECT,
				'Types de groupes/' . $old['nom'],
				NS_PROJECT,
				'Ancienne version restaurée.',
				$wgUser
			);
			if( !$rename->done() ) {
				return Status::newFailed( 'Action annulée: la page <strong>MGWiki:Types de groupes/' .
					$actual['nom'] . '</strong> n\'a pas pu être renomée.' );
			}
			$old['page_id'] = $rename->extra();
		}

		// màj des tables
		$update = DbF::update( 'groupe_type', [ 'id' => $id ], $old, $wgUser->getId() );
		if ( ! $update->done() ) {
			return $update;
		}

		// refreshPage
		$refresh = PageF::refreshPage(
			$old['page_id'],
			Msg::get( 'specialadmingrouptypes-update-summary' ),
			$wgUser
		);
		if ( ! $refresh->done() ) {
			return Status::newFailed( 'Les modifications ont été enregistrées mais la page <strong>MGWiki:Types de groupes/'
				. $old['nom'] . '</strong> n\'a pas pu être réactualisée.' );
		}

		// succès
		return Status::newDone( 'L\'ancienne version de <i>'.$old['nom'].'</i> a été rétablie.');
	}

	/**
	 * @param int $id mgw_groupe_type_id
	 * @param string $summary
	 */
	private function delete( $id, $summary ) {
		global $wgUser;
		// suppression dans les tables
		$dbDelete = DbF::delete( 'groupe_type', [ 'id' => $id ], $wgUser->getId() );
		if ( !$dbDelete->done() ){
			return $dbDelete;
		}
		// suppression de la page
		$pageDelete = PageF::lightDelete( $dbDelete->extra()['groupe_type_page_id'], $summary );
		if ( ! $pageDelete->done() ) {
			return Status::newFailed( '<i>' . $dbDelete->extra()['groupe_type_nom'] . '</i> a été supprimé mais'.
				' la page "MGWiki:Types_de_groupes/' . $dbDelete->extra()['groupe_type_nom'] .
				'" existe toujours ( ' . $dbDelete->mess() . ' )' );
		}
		// succès
		return Status::newDone( '<i>'.$dbDelete->extra()['groupe_type_nom'].'</i> a été supprimé.');
	}


	/**
	 * @param int $archive_id
	 */
	private function undelete( $archive_id ) {
		global $wgUser;

		// requêtes
		$old = DbF::select_clean(
			'archive_groupe_type',
			[ 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration' ],
			'archive_id = ' . $archive_id
		)[0];

		$names = DbF::select_clean(
			'groupe_type',
			[ 'id', 'nom' ]
		);

		// on vérifie l'absence de doublons
		foreach ( $names as $row ) {
			if ( strtolower( $row['nom'] ) == strtolower( $old['nom'] ) ) {
				return Status::newFailed( 'Le nom <strong>'.$old['nom'] .
					'</strong> correspond à un autre type de groupe en cours d\'utilisation.'
					.' Veuillez rétablir le groupe supprimé manuellement avec un autre nom.' );
			}
		}

		// on re-crée la page
		if ( PageF::pageArchiveExists( 'MGWiki:Types de groupes/' . $old['nom'] ) ) {
			$undelete = PageF::undeletePage(
				'MGWiki:Types de groupes/' . $old['nom'],
				'Restauration du type de groupe.',
				$wgUser
			);
			if( !$undelete->done() ) {
				return Status::newFailed( 'Action annulée: la page <strong>MGWiki:Types de groupes/' .
					$old['nom'] . '</strong> n\'a pas pu être restaurée.'  );
			}
			$old['page_id'] = $undelete->extra();
		}
		else {
			$newPage = PageF::newPage(
				'Types de groupes/' . $old['nom'],
				NS_PROJECT,
				'{{Modèle:Types de groupes}}',
				Msg::get( 'specialadmingrouptypes-newpage-summary' ),
				$wgUser
			);
			if( !$newPage->done() ) {
				return Status::newFailed( 'Action annulée: la page <strong>MGWiki:Types de groupes/' .
					$old['nom'] . '</strong> n\'a pas pu être créée. (' . $newPage->mess() . ')'  );
			}
			$old['page_id'] = $newPage->extra();
		}

		// on rétablit l'ancienne version
		$status = DbF::insert( 'groupe_type', $old, $wgUser->getId() );
		if ( ! $status->done() ) {
			return $status;
		}

		$refresh = PageF::refreshPage(
			$old['page_id'],
			Msg::get( 'specialadmingrouptypes-update-summary' ),
			$wgUser
		);
		if ( ! $refresh->done() ) {
			return Status::newFailed( 'Les modifications ont été enregistrées mais la page <strong>MGWiki:Types de groupes/'
				. $old['nom'] . '</strong> n\'a pas pu être réactualisée.' );
		}

		// succès
		return Status::newDone( $old['nom'].'</i> a été rétabli.');
	}


	private function getShowArray( $select = '', $opts = [] ) {

		$groupTypes = [];
		if ( $this->check['archive']['only'] != 'checked' ) {
			$groupTypes = DbF::select_clean(
				'groupe_type',
				[ 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration', 'update_time', 'updater_id' ],
				$select,
				$opts
			);
		}

		if ( $this->check['archive']['view'] == 'checked' || $this->check['archive']['only'] == 'checked' ) {
			if ( !empty( $select ) ) {
				$select = implode( ' AND ', [ $select, 'drop_time IS NOT NULL'] );
			}
			else {
				$select = 'drop_time IS NOT NULL';
			}
			$droped = DbF::select_clean(
				'archive_groupe_type',
				[ 'archive_id', 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration', 'update_time', 'updater_id', 'drop_time', 'drop_updater_id' ],
				$select,
				$opts
			);
			$groupTypes = array_merge( $groupTypes, $droped );
		}

		foreach ( $groupTypes as $key => $row ) {
			PhpF::null( $groupTypes[$key]['drop_time'] );
			PhpF::null( $groupTypes[$key]['archive_id'] );
			$groupTypes[$key]['droped'] = !is_null( $groupTypes[$key]['drop_time'] );
			$groupTypes[$key]['undroped'] = false;
			if ( $groupTypes[$key]['droped'] ) {
				// on le retire s'il a été rétabli depuis
				$screen = DbF::select_clean(
					'groupe_type',
					[ 'id', 'update_time' ],
					'id = ' . $groupTypes[$key]['id'] . ' AND groupe_type_update_time > ' . $groupTypes[$key]['update_time']
				);
				if ( !is_null( $screen )	) {
					unset( $groupTypes[$key] );
				}
				// on n'affiche que la dernière suppression si plusieurs
				if ( isset( $groupTypes[$key] ) ) {
					$screen = DbF::select_clean(
						'archive_groupe_type',
						[ 'id', 'drop_time' ],
						'id = ' . $groupTypes[$key]['id'] . ' AND groupe_type_drop_time > ' . $groupTypes[$key]['drop_time']
					);
					if ( !is_null( $screen )	) {
						unset( $groupTypes[$key] );
					}
				}
			}
		}
		return $groupTypes;
	}

	private function getHistoryShowArray( $id ) {

		$actu = DbF::select_clean(
			'groupe_type',
			[ 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration', 'update_time', 'updater_id' ],
			'id = ' . $id
		);

		$past = DbF::select_clean(
			'archive_groupe_type',
			[ 'archive_id', 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration',
				'update_time', 'updater_id', 'drop_time', 'drop_updater_id' ],
			'id = ' . $id,
			[ 'ORDER BY' => 'update_time DESC' ]
		);

		if ( is_null( $actu ) ) {
			$actu = [];
		}
		if ( is_null( $past ) ) {
			$past = [];
		}

		$return = array_merge( $actu, $past );

		foreach ( $return as $key => $row ) {
			PhpF::null( $return[$key]['drop_time'] );
			PhpF::null( $return[$key]['archive_id'] );
			$return[$key]['droped'] = !is_null( $return[$key]['drop_time'] );
			$return[$key]['undroped'] = isset( $return[$key - 1] );
		}

		return $return;
	}

	private function makeView( $show_array ) {
		if ( $show_array == [] ) {
			return HtmlF::tagShowArray('<br>&nbsp;&nbsp;Aucun type de groupe ne correspond à la sélection');
		}
		global $wgMgwLevels;

		# préparation des variables
		foreach ( $show_array as $row ) {

			$duration = wfMgwDuration( $row['default_duration'] );

			if ( $row['droped'] && !$row['undroped'] ) {
				$buttons = HtmlF::onclickButton(
						'rétablir',
						$this->selfURL( [ 'action' => 'undelete', 'id' => $row['id'], 'archive' => $row['archive_id'] ] )
					) . '<a href="' . $this->selfURL( [ 'action'=>'history', 'id'=>$row['id'] ] ) .
						'" class="mgw-history-link">historique</a>' ;
			}
			elseif ( !is_null( $row['archive_id'] ) ) {
				$buttons = HtmlF::onclickButton(
					'rétablir',
					$this->selfURL( [ 'action'=>'undo', 'id'=>$row['id'], 'archive'=>$row['archive_id'] ] )
				);
			}
			else {
				$buttons = HtmlF::onclickButton(
						'modifier',
						$this->selfURL( [ 'action'=>'edit', 'id'=>$row['id'] ] )
					) . '
						<a href="' . $this->selfURL( [ 'action'=>'history', 'id'=>$row['id'] ] ) .
						'" class="mgw-history-link">historique</a>
						<span href="' . $this->selfURL( [ 'action'=>'delete', 'id'=>$row['id'] ] ) .
						'" class="mgw-delete-link" elmt="' . $row['nom'] . '">supprimer</span>' ;
			}

			if ( $row['droped'] ) {
				$class = 'deleted';
			}
			elseif ( !is_null( $row['archive_id'] ) ) {
				$class = 'inactive';
			}
			else {
				$class = 'active';
			}

			if ( $row['droped'] ) {
				$update_time = wfTimestamp( TS_DB, $row['drop_time'] );
				$updater = \User::newFromId ( $row['drop_updater_id'] );
				$updater_link = '<a href="'.$updater->getUserPage()->getFullUrl().'">' . $updater->getName() . '</a>';
				$maj = 'Supprimé le ' . $update_time . ' par ' . $updater_link;
			}
			else {
				$update_time = wfTimestamp( TS_DB, $row['update_time'] );
				$updater = \User::newFromId ( $row['updater_id'] );
				$updater_link = '<a href="'.$updater->getUserPage()->getFullUrl().'">' . $updater->getName() . '</a>';
				$maj = 'Màj le ' . $update_time .	' par ' . $updater_link ;
			}

			#intégration
			$rowTable = '
          <tr>
            <td class="mgw-title" colspan="2" ><strong> ' . HtmlF::linkPageId( $row['nom'], $row['page_id'] ) . '</strong></td>
						<td></td>
						<td class="mgw-edit-button" rowspan="2">' . $buttons . ' </td>
					</tr>
					<tr>
						<td class="mgw-level" >Admin : ' . $wgMgwLevels[ $row['admin_level'] ] . '</td>
						<td class="mgw-level" >User : ' . $wgMgwLevels[ $row['user_level'] ] . '</td>
						<td>Durée : ' . $duration . '</td>
					</tr>
					<tr>
						<td colspan="2" ></td>
						<td class="mgw-maj" colspan="2" >' . $maj . '</td>
					</tr>';

				$print[] = HtmlF::tagRowTable( $rowTable, $class );
		}
		$print = HtmlF::tagShowArray( implode( '', $print ) );
		return $print;
	}

	///////////////
	// FORMULAIRE

	private function setData( $reqData ){
		// valeurs par défaut
		if ( isset( $reqData['submit'] ) ) {
			$this->id = $reqData['id'];
			$this->nom = $reqData['nom'];
			$this->admin_level = $reqData['admin_level'];
			$this->user_level = $reqData['user_level'];
			$this->duration = $reqData['duration'];
		}
		elseif ( !empty( $reqData['id'] ) ) {
			$row = $this->getShowArray(
				'id = ' . $reqData['id'],
				[ 'ORDER BY' => 'nom' ]
			)[0];

			$this->id = $row['id'];
			$this->nom = $row['nom'];
			$this->admin_level = $row['admin_level'];
			$this->user_level =  $row['user_level'];
			$this->duration =  $row['default_duration'];
		}
		else {
			$this->id = '';
			$this->nom = '';
			$this->admin_level = 2;
			$this->user_level = 1;
			$this->duration = 0;
		}
	}

	private function makeOouiForm( $reqData ) {
		$html = new \OOUI\FormLayout( [
			'method' => 'POST',
			'action' => $this->selfURL(),
			'items' => [
				new \OOUI\FieldsetLayout( [
					'items' => [
						new \OOUI\FieldLayout(
							new \OOUI\HiddenInputWidget( [
								'name' => 'id',
								'value' => $this->id
							] )
						),
						new \OOUI\FieldLayout(
							new \OOUI\TextInputWidget( [
								'name' => 'nom',
								'type' => 'text',
								'value' => $this->nom,
								'required' => true,
								'autofocus' => true
							] ),
							[
								'label' => 'Nom du type de groupe',
								'align' => 'top',
							]
						),
						new \OOUI\FieldLayout(
							new \OOUI\DropdownInputWidget( [
								'name' => 'admin_level',
								'value' => $this->admin_level,
								'options' => [
									[ 'data' => 1, 'label' => 'U1' ],
									[ 'data' => 2, 'label' => 'U2' ],
									[ 'data' => 3, 'label' => 'U3' ],
									[ 'data' => 4, 'label' => 'sysop' ],
								]
							] ),
							[
								'label' => 'Niveau des responsables',
								'align' => 'top',
							]
						),
						new \OOUI\FieldLayout(
							new \OOUI\DropdownInputWidget( [
								'name' => 'user_level',
								'value' => $this->user_level,
								'options' => [
									[ 'data' => 1, 'label' => 'U1' ],
									[ 'data' => 2, 'label' => 'U2' ],
									[ 'data' => 3, 'label' => 'U3' ],
									[ 'data' => 4, 'label' => 'sysop' ],
								]
							] ),
							[
								'label' => 'Niveau des utilisateurs',
								'align' => 'top',
							]
						),
						new \OOUI\FieldLayout(
							new \OOUI\DropdownInputWidget( [
								'name' => 'duration',
								'value' => $this->duration,
								'options' => [
									[ 'data' => 0,  'label' => 'indéfini' ],
									[ 'data' => 1,  'label' => '6 mois' ],
									[ 'data' => 2,  'label' => '1 an' ],
									[ 'data' => 4,  'label' => '2 ans' ],
									[ 'data' => 6,  'label' => '3 ans' ],
									[ 'data' => 8,  'label' => '4 ans' ],
									[ 'data' => 10, 'label' => '5 ans' ]
								]
							] ),
							[
								'label' => 'Durée par défaut',
								'align' => 'top',
							]
						),
						new \OOUI\FieldLayout(
							new \OOUI\ButtonInputWidget( [
								'name' => 'submit',
								'label' => 'Valider',
								'type' => 'submit',
								'flags' => [
									'primary',
									'progressive'
								]
							] ),
							[
								'label' => null,
								'align' => 'top',
							]
						)
					] // items [...]
				] ) // FieldsetLayout( [...] )
			] // items [...]
		]	); // FormLayout( [...] )
		$html .= '<br>' . (string)new \OOUI\ButtonWidget( [
				'href' => $this->selfURL(),
				'label' => 'annuler'
			] );
		return $html;
	}

  public static function formCallback( &$reqData, &$edit, &$show ) {
		global $wgUser;
		PhpF::html( $reqData['nom'] );
		PhpF::int( $reqData['admin_level'] );
		PhpF::int( $reqData['user_level'] );
		PhpF::int( $reqData['duration'] );

		// on interdit les doublons sur le champs 'nom'
		$check = DbF::select_clean( 'groupe_type', [ 'id', 'nom' ] );
		foreach ( $check as $row ) {
			if ( strtolower( $row['nom'] ) == strtolower( $reqData['nom'] )
		 				&& $row['id'] != $reqData['id'] ) {
				$edit = true; // on ré-affiche le formulaire
				$show = false;
				return Status::newFailed( 'Le nom demandé existe déjà. Vous pouvez:
					<ul>
						<li>Editer le type de groupe déjà existant:
							<a href="Spécial:Specialadmingrouptypes?action=edit&id='.$row['id'].'">'
							. $row['nom'] . '</a></li>
						<li>Modifier le nom.</li>
					</ul>' );
			}
		}

		// préparation de la reqête
		if ( is_null( $reqData['id'] ) ) {
			$select = [ 'nom' => ucfirst( $reqData['nom'] ) ];
			$data = [
				'admin_level' => $reqData['admin_level'],
				'user_level' => $reqData['user_level'],
				'default_duration' => $reqData['duration']
			];
			$new = true;
		}
		else {
			$select = [ 'id' => $reqData['id'] ];
			$data = [
				'nom' => ucfirst( $reqData['nom'] ),
				'admin_level' => $reqData['admin_level'],
				'user_level' => $reqData['user_level'],
				'default_duration' => $reqData['duration']
			];
			$new = false;
		}

		// gestion des pages du wiki
		if ( $new ) {

			# on crée une nouvelle page
			$status = PageF::newPage(
				'Types de groupes/' . $select['nom'],
				NS_PROJECT,
				'{{Modèle:Types de groupes}}',
				Msg::get( 'specialadmingrouptypes-newpage-summary' ),
				$wgUser
			);
	 		if( !$status->done() ) {
	 			return Status::newFailed( 'Action annulée: la page <strong>MGWiki:Types de groupes/' .
					$reqData['nom'] . '</strong> n\'a pas pu être créée. (' . $status->mess() . ')'  );
	 		}
			$data['page_id'] = $status->extra();
		}
		else {
			$oldData = DbF::select_clean( 'groupe_type', ['id', 'nom', 'page_id' ], 'id = ' . $reqData['id'] )[0];

			# on vérifie l'existence de la page et l'actualité de la base
			$page_id = PageF::pageID( 'MGWiki:Types de groupes/' . $oldData['nom'] );
			if ( $page_id < 1 ) {
				$actualPage = PageF::newPage(
					'Types de groupes/' . $oldData['nom'],
					NS_PROJECT,
					'{{Modèle:Types de groupes}}',
					Msg::get( 'specialadmingrouptypes-newpage-summary' ),
					$wgUser
				);
				if ( !$actualPage->done() ) {
					return Status::newFailed( 'Action annulée: la page <strong>MGWiki:Types de groupes/' .
						$oldData['nom'] . '</strong> n\'a pas pu être créée. (' . $actualPage->mess() . ')'  );
				}
				$oldData['page_id'] = $actualPage->extra();
			}
			elseif ( $oldData['page_id'] != $page_id ) {
				$oldData['page_id'] = $page_id;
			}

			# on renomme la page si le nom est modifié
			if ( $oldData['nom'] != $data['nom'] ) {
				$status = PageF::renamePage(
					'Types de groupes/' . $oldData['nom'],
					NS_PROJECT,
					'Types de groupes/' . $data['nom'],
					NS_PROJECT,
					'Mise à jour automatisée suite à modification de nom',
					$wgUser
				);
		 		if( !$status->done() ) {
		 			return Status::newFailed( 'Action annulée: la page <strong>MGWiki:Types de groupes/' .
						$reqData['nom'] . '</strong> n\'a pas pu être renomée.' );
		 		}
				$data['page_id'] = $status->extra();
			}
			else {
				$data['page_id'] = $oldData['page_id'];
			}
		}

		// écriture des tables
		$write = DbF::update_or_insert( 'groupe_type', $select, $data, $wgUser->getId() );
		if ( ! $write->done() ) {
			$edit = true;
			return $write;
		}
		$edit = false;

		// réactualisation de la page
		$refresh = PageF::refreshPage(
			$data['page_id'],
			Msg::get( 'specialadmingrouptypes-update-summary' ),
			$wgUser
		);

		if ( ! $refresh->done() ) {
			return Status::newFailed( 'Les modifications ont été enregistrées mais la page <strong>MGWiki:Types de groupes/'
				. $reqData['nom'] . '</strong> n\'a pas pu être réactualisée.' );
		}

		return Status::newDone( 'Les modifications de <strong>' . $reqData['nom'] . '</strong> ont été enregistrées.' );
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
