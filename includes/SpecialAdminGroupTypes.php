<?php

namespace MediaWiki\Extension\MGWikiDev;

use SpecialPage;
use HTMLForm;
use MediaWiki\MediaWikiServices;

use MediaWiki\Extension\MGWikiDev\Utilities\GetMessage as Msg;
use MediaWiki\Extension\MGWikiDev\Utilities\MgwDataFunctions as DbF;
use MediaWiki\Extension\MGWikiDev\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWikiDev\Utilities\HtmlFunctions as HtmlF;
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
	private $triOptions, $filtreOptions, $filtreOptionsTitles;

	public function __construct() {
		parent::__construct( 'specialadmingrouptypes', 'editinterface' ); // restrict to sysops

    # définition des options
    $this->triOptions = ['nom', 'admin_level', 'user_level', 'default_duration', 'update_time', 'updater_id'];
    $this->filtreOptions = [
      'show_archive',
      'sysop',
			'U3',
      'U2',
			'U1',
			'U0'
		];
    $this->filtreOptionsTitles = [
      'admin_level' => [ 'sysop', 'U3', 'U2', 'U1', 'U0' ],
      'user_level' => [ 'sysop', 'U3', 'U2', 'U1', 'U0' ]
    ];
	}

	public function execute( $sub ) {

		// récupération et contrôle des entrées
    $reqData = $this->getRequest()->getValues();
		PhpF::empty( $reqData['action'] );
		PhpF::empty( $reqData['id'] );
		PhpF::int( $reqData['id'] );

    $this->setHeaders();
    $out = $this->getOutput();
		$out->addModules('ext.mgwiki-specialadmingrouptypes');
		$out->enableOOUI();

		// mode d'affichage
		$edit = ( $reqData['action'] == 'edit' );

		$history = ( $reqData['action'] == 'history' && !is_null( $reqData['id'] ));

		$show = ( $reqData['action'] != 'edit' && $reqData['action'] != 'history' );

		if ( isset( $reqData['submit'] ) ) {
			$status = $this->formCallback( $reqData, $edit, $show );
			$done = ( $status->done() ) ? 'true' : 'false';
			$out->addHTML( HtmlF::alertMessage( $status ) );
		}

		// undo
		if ( $reqData['action'] == 'undo' && isset( $reqData['id'] ) && isset( $reqData['archive'] ) ) {
			$undo = $this->undo( $reqData['id'], $reqData['archive'] );
			$out->addHTML( HtmlF::alertMessage( $undo ) );
			$history = true;
			$show = false;
		}

		// delete
		if ( $reqData['action'] == 'delete' && isset( $reqData['id'] ) ) {
			$delete = $this->delete( $reqData['id'] );
			$out->addHTML( HtmlF::alertMessage( $delete ) );
			$history = false;
			$show = true;
		}

		// historique
		if ( $history ) {
			$groupe_types = $this->getGroupTypeHistory( $reqData['id'] );
			$out->setPageTitle( Msg::get('specialadmingrouptypes-title-history', [ $groupe_types[0]['nom'] ] ) );
			$out->addHTML( $this->makeView( $groupe_types ) );
			$out->addHTML( '<br>' .
				(string)new \OOUI\ButtonWidget( [	'href' => $this->selfURL(), 'label' => 'retour' ] )
				);
		}

		// affichage général
    if ( $show ) {

			$out->setPageTitle( Msg::get('specialadmingrouptypes-title') );

			$groupe_types = $this->getGroupTypes( '', [ 'ORDER BY' => 'nom' ] );
			$out->addHTML( $this->makeView( $groupe_types ) );
			$out->addHTML( '<br>' .
				(string)new \OOUI\ButtonWidget( [
					'href' => $this->selfURL( [	'action' => 'edit' ] ),
					'label' => 'nouveau'
				] )
			);
    }

    // formulaire d'édition
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

  public static function formCallback( &$reqData, &$edit, &$show ) {

		global $wgUser;

		PhpF::html( $reqData['nom'] );
		PhpF::int( $reqData['admin_level'] );
		PhpF::int( $reqData['user_level'] );
		PhpF::int( $reqData['duration'] );

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

		if ( is_null( $reqData['id'] ) ) {
			$select = [ 'nom' => $reqData['nom'] ];
			$data = [
				'admin_level' => $reqData['admin_level'],
				'user_level' => $reqData['user_level'],
				'default_duration' => $reqData['duration']
			];
		}
		else {
			$select = [ 'id' => $reqData['id'] ];
			$data = [
				'nom' => $reqData['nom'],
				'admin_level' => $reqData['admin_level'],
				'user_level' => $reqData['user_level'],
				'default_duration' => $reqData['duration']
			];
		}

		$write = DbF::update_or_insert( 'groupe_type', $select, $data, $wgUser->getId() );

		if ( $write->done() ) {
			$edit = false;
			return Status::newDone( 'Les modifications de <strong>' . $reqData['nom'] . '</strong> ont été enregistrées.' );
		}
		else {
			$edit = true;
			return $write;
		}
  }

	private function undo( $id, $archive_id ) {
		global $wgUser;
		$old_type = DbF::select_clean(
			'archive_groupe_type',
			[ 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration' ],
			'archive_id = ' . $archive_id
		)[0];
		$actual_types = DbF::select_clean(
			'groupe_type',
			[ 'id', 'nom' ]
		);
		foreach ( $actual_types as $type ) {
			if ( strtolower( $type['nom'] ) == strtolower( $old_type['nom'] ) ) {
				return Status::newFailed( 'Le nom <i>'.$old_type['nom'] .
					'</i> correspond à un autre type de groupe en cours d\'utilisation.'
					.' Veuillez rétablir les modifications manuellement avec un autre nom.' );
			}
		}
		$status = DbF::update( 'groupe_type', [ 'id' => $id ], $old_type, $wgUser->getId() );
		if ( $status->done() ) {
			return Status::newDone( 'L\'ancienne version de <i>'.$old_type['nom'].'</i> a été rétablie.');
		}
		else {
			return $status;
		}
	}

	private function delete( $id ) {
		global $wgUser;
		$status = DbF::delete( 'groupe_type', [ 'id' => $id ], $wgUser->getId() );
		if ( $status->done() ) {
			return Status::newDone( '<i>'.$status->extra()['groupe_type_nom'].'</i> a été supprimé.');
		}
		else {
			return $status;
		}
	}

	private function getGroupTypes( $select = '', $opts = [] ) {
		return DbF::select_clean(
			'groupe_type',
			[ 'id', 'nom', 'page_id', 'admin_level', 'user_level', 'default_duration', 'update_time', 'updater_id' ],
			$select,
			$opts
		);
	}

	private function getGroupTypeHistory( $id ) {

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
		if ( !is_null($past) ) {
			$actu = array_merge( $actu, $past );
		}
		return $actu;
	}

	////////////////////////////////////
	// FONCTIONS RELATIVES A L'AFFICHAGE

	private function makeView( $groupe_types ) {
		global $wgMgwLevels;
		$print = '<div class="mgw-display-group-types" >';
		# préparation des variables
		foreach ( $groupe_types as $type ) {
			switch ( $type['default_duration'] ) {
				case 0:
					$duration = 'indéfini';
					break;
				case 1:
					$duration = '6 mois';
					break;
				case 2:
					$duration = '1 an';
					break;
				default:
					$duration = ( $type['default_duration'] / 2 ) . ' ans';
					break;
			}

			$buttons = ( isset( $type['archive_id'] ) )
				? HtmlF::onclickButton(
						'rétablir',
						$this->selfURL( [ 'action'=>'undo', 'id'=>$type['id'], 'archive'=>$type['archive_id'] ] ) )
				: HtmlF::onclickButton(
						'modifier',
						$this->selfURL( [ 'action'=>'edit', 'id'=>$type['id'] ] ) ) . '
					<a href="' . $this->selfURL( [ 'action'=>'history', 'id'=>$type['id'] ] ) .
						'" class="mgw-history-link">historique</a>
					<span href="' . $this->selfURL( [ 'action'=>'delete', 'id'=>$type['id'] ] ) .
						'" class="mgw-delete-link" elmt="' . $type['nom'] . '">supprimer</span>' ;

			$class = ( isset( $type['archive_id'] ) )
				? 'mgw-archive'
				: 'mgw-actif';

			if ( isset( $type['drop_time'] ) && $type['drop_time'] != 0 ) {
				$update_time = wfTimestamp( TS_DB, $type['drop_time'] );
				$updater = \User::newFromId ( $type['drop_updater_id'] );
				$updater_link = '<a href="'.$updater->getUserPage()->getFullUrl().'">' . $updater->getName() . '</a>';
				$maj = 'Supprimé le ' . $update_time . ' par ' . $updater_link;
			}
			else {
				$update_time = wfTimestamp( TS_DB, $type['update_time'] );
				$updater = \User::newFromId ( $type['updater_id'] );
				$updater_link = '<a href="'.$updater->getUserPage()->getFullUrl().'">' . $updater->getName() . '</a>';
				$maj = 'Màj le ' . $update_time .	' par ' . $updater_link ;
			}

			#intégration
			$print .= '
				<table  class="mgw-admin-group-types ' . $class . '" >
          <tr>
            <td class="mgw-title" colspan="2" ><strong> ' . $type['nom'] . '</strong></td>
						<td>Page: ' . $type['page_id'] . '</td>
						<td class="mgw-edit-button" rowspan="2">' . $buttons . ' </td>
					</tr>
					<tr>
						<td class="mgw-level" >Admin : ' . $wgMgwLevels[ $type['admin_level'] ] . '</td>
						<td class="mgw-level" >User : ' . $wgMgwLevels[ $type['user_level'] ] . '</td>
						<td>Durée : ' . $duration . '</td>
					</tr>
					<tr>
						<td colspan="2" ></td>
						<td class="mgw-maj" colspan="2" >' . $maj . '</td>
					</tr>
				</table>';
		}
		$print .= '</div>';
		return $print;
	}

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
			$row = $this->getGroupTypes(
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
								'required' => true
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
								'type' => 'submit'
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
