<?php
/**
 */

namespace MediaWiki\Extension\MGWikiDev;

use SpecialPage;
use HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\Extension\MGWikiDev\Classes\MGWUser;
use MediaWiki\Extension\MGWikiDev\Utilities\UsersFunctions as UserF;
use MediaWiki\Extension\MGWikiDev\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWikiDev\Foreign\MGWReplaceText;
use MediaWiki\Extension\MGWikiDev\Utilities\MgwDataFunctions as DbF;

 // https://www.mediawiki.org/wiki/HTMLForm

class SpecialMgwikiTest extends SpecialPage {

	public function __construct() {
		parent::__construct( 'specialmgwikitest' );
	}

	public function execute( $sub ) {
		global $_SERVER;
    $this->setHeaders();
    $postData = $this->getRequest()->getPostValues();
				$select = '';
				$opts = [
					'ORDER BY' => 'nom',
				];

$rep = DbF::select( 'groupe_type', ['id', 'nom', 'admin_level' ] );


var_dump($rep);

/*
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addHTML( new \OOUI\FormLayout( [
			'method' => 'POST',
			'action' => $_SERVER['PHP_SELF'],
			'items' => [
				new \OOUI\FieldsetLayout( [
					'label' => 'Form layout',
					'items' => [
						new \OOUI\FieldLayout(
							new \OOUI\DropdownInputWidget(
								[
									'name' => 'choix',
									'value' => 2,
									'options' => [
										[ 'data' => 1, 'label' => 'choix1' ],
										[ 'data' => 2, 'label' => 'choix2' ],
										[ 'data' => 3, 'label' => 'choix3' ],
										[ 'data' => 4, 'label' => 'choix4' ],
									],
								] ),
								[
									'label' => 'Choisissez',
									'align' => 'top',
								]
							),
							new \OOUI\FieldLayout(
								new \OOUI\TextInputWidget( [
									'name' => 'password',
									'type' => 'text',
									'value' => 'blablabla'
								] ),
								[
									'label' => 'Password',
									'align' => 'top',
								]
							),
							new \OOUI\FieldLayout(
								new \OOUI\CheckboxInputWidget( [
									'name' => 'rememberme',
									'selected' => true,
								] ),
								[
									'label' => 'Remember me',
									'align' => 'inline',
								]
							),
							new \OOUI\FieldLayout(
								new \OOUI\ButtonInputWidget( [
									'name' => 'login',
									'label' => 'Log in',
									'type' => 'submit',
									'flags' => [ 'primary', 'progressive' ],
									'icon' => 'check',
								] ),
								[
									'label' => null,
									'align' => 'top',
								]
							),
						]
					] )
				]
			] ) );
			*/
/*
    // formDescriptor Array to tell HTMLForm what to build
    $formDescriptor = [
			'user_id' => [
				'type' => 'int',
        'label' => 'user_id',
				'required' => true,
				//'filter-callback' => function ( $val, $array ) { return 'hahahaha'; },
			],
			'nom' => [
				'type' => 'text',
        'label' => 'nom',
				'required' => true,
				//'filter-callback' => function ( $val, $array ) { return 'hahahaha'; },
			],
			'prenom' => [
				'type' => 'text',
        'label' => 'prenom',
				'required' => true,
				//'filter-callback' => function ( $val, $array ) { return 'hahahaha'; },
			],
    ];

    // Build the HTMLForm object
    $htmlForm = HTMLForm::factory( 'vform', $formDescriptor, $this->getContext() );
    $htmlForm->setSubmitText( 'Soumettre' );
    $htmlForm->setSubmitCallback( [ $this, 'processInput' ] );
    $htmlForm->show(); // Display the form
*/

	/*
    if ( isset($postData['wpkey'] ) ){

			///////// TIMESTAMP & DB:

			$user = UserF::getUserFromId((int)$postData['wpkey']);
      var_dump( wfTimestamp( TS_DB, $user->getRegistration() ) );
      var_dump( wfTimestamp(TS_MW) );

			$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
			$dbw = $lb->getConnectionRef( DB_MASTER );
			var_dump( $dbw->timestamp( ) ); //

			var_dump( 'MGW_DB_ROW_DROPED : ' . MGW_DB_DROPED );

			///////// MGWuser :
			$r = MGWUser::newFromUserId( (int)$postData['wpkey'] );
			var_dump($r); */
		/*
			$replace = new MGWReplaceText( [
				"target" => "interne TEST2",
				"replace" => "anonyme",
				"regex" => false,
				"nsall" => true,
				"summary" => "MGW replacetext test.",
				"user" => "Webmaster"
			] );
	 		$status = $replace->execute();
			var_dump($status);
			*/
  }

  // Callback function
  // OnSubmit Callback, here we do all the logic we want to do…
  public static function processInput( $formData ) {

		global $wgUser;
		$table = 'utilisateur';
		$select = [ 'user_id' => $formData['user_id'] ];
		$data = [
			'nom' => $formData['nom'],
			'prenom' => $formData['prenom']
		];

		$updater_id = $wgUser->getId();

		$dbUpdate = DbF::update_or_insert( $table, $select, $data, $updater_id );

		return $dbUpdate->mess();
  }

	protected function getGroupName() {
		return 'mgwiki';
	}
}
