<?php
/**
 * Page spéciale pour envoyer un Email à tout un groupe d'utilisateurs
 */

namespace MediaWiki\Extension\MGWiki;


class AdminSendMassEmail extends \SpecialPage {

	private $special, $body_page;

	public function __construct() {
		$this->special = 'MgwAdminSendMassEmail';
		$this->body_page = 'MediaWiki:SendMassEmail';
		$this->pre_URL = 'http://localhost/wiki/';
		parent::__construct( $this->special, 'editinterface' );

	}

	public function execute( $par ) {
		$user = $this->getUser();
		$request = $this->getRequest();

		//$out->addModules( [ 'ext.mgw-send-mass-email' ] );
		$this->setHeaders();

		// callBacks
		if (
			$request->wasPosted() &&
			$request->getBool('submit')
		) {
			$post = $request->getPostValues();

			# constitution de la liste des destinataires
			$groups = [];
			if ( $post['main_select'] == 2 ) {
				foreach ( $post as $key => $val ) {
					if ( preg_match( '/^group_/', $key ) > 0 ) {
						$groups[] = $val;
					}
				}
			}
			$users = $this->query( 'users', $groups );
			$this->showConfirm( $users, htmlspecialchars($post['subject']) );
			return;
		}

		if (
			$request->wasPosted() &&
			$request->getBool( 'confirmed' )
		) {
			$post = $request->getPostValues();
			$users = explode(',', $post['users']);
			# envoi du mail
			$sent = [];
			$fails = [];
			foreach ( $users as $user_id ) {
				$user = \User::newFromId( $user_id );
				if ( $user->getEmail() ) {
			    global $wgEmergencyContact;
		      $mail_to[] = new \MailAddress( $user->getEmail() );
			    $mailer = new \UserMailer();
			    $mail_from = new \MailAddress( $wgEmergencyContact );
			    $mailer->send(
			      $mail_to,
			      $mail_from,
			      $post['subject'],
			      $this->getHtmlBody(),
			      array( 'contentType' => 'text/html; charset=UTF-8' )
			    );
					$sent[] = $user->getName();
				}
				else $fails[] = $user->getName();
			}
			$this->showEnd( $sent, $fails );
			return;
		}

		$this->showForm();
	}

	private function showForm(){
		$groups = $this->query('groups');
		$groups_input = [];
		foreach ( $groups as $group ) {
			$groups_input[] = new \OOUI\FieldLayout(
				new \OOUI\CheckboxInputWidget( [ "name" => 'group_'.$group, "value" => $group ] ),
				[ 'label' => $group, 'align' => 'right'	]
			);
		}
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addHTML( new \OOUI\FormLayout( [
			'method' => 'POST',
			'action' => $_SERVER['PHP_SELF'],
			'items' => [
				new \OOUI\FieldsetLayout( [
					'items' => [
							new \OOUI\FieldLayout(
								new \OOUI\RadioSelectInputWidget( [
									'name' => 'main_select',
									'value' => 1,
									'options' => [
										[
											'data' => 1,
											'label' => 'tous les utilisateurs'
										],
										[
											'data' => 2,
											'label' => 'utilisateurs parmi les groupes :'
										]
									]
								] ),
								[
									'label' => 'Destinataires :',
									'align' => 'top',
								]
							),
						]
					]
				),
				new \OOUI\FieldsetLayout( [	'items' => $groups_input ] ),
				new \OOUI\FieldsetLayout( [
					'items' => [
							new \OOUI\FieldLayout(
								new \OOUI\TextInputWidget( [
									'name' => 'subject',
									'type' => 'text',
									'required' => true
								] ),
								[
									'label' => 'Sujet',
									'align' => 'top',
								]
							),
							new \OOUI\ButtonWidget( [
								"framed" => false,
								"flags" => [
									'progressive'
								],
								"label" => 'Editer le corps du message',
								"href" => $this->body_page,
								"target" => '_blank',
								"rel" => [
									'noreferrer',
									'noopener'
								]
							] ),
							new \OOUI\FieldLayout(
								new \OOUI\ButtonInputWidget( [
									'name' => 'submit',
									'label' => 'soumettre',
									'value' => true,
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
	}

	private function showConfirm( $users, $subject ) {
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addHTML( 'Vous êtes sur le point d\'envoyer le mail suivant à ' . count($users) . ' destinataires:<br><br>' );
		$out->addHTML( '<strong>Sujet:</strong><br>');
		$out->addHTML( '<div style="border:solid 1px; width:90%; padding-left:10px;">' . $subject . '</div><br>');
		$out->addHTML( '<strong>Corps:</strong><br>');
		$out->addHTML( '<div style="border:solid 1px; width:90%; padding-left:10px;">' . $this->getHtmlBody() . '</div>');

		$out->addHTML( new \OOUI\FormLayout( [
			'method' => 'POST',
			'action' => $_SERVER['PHP_SELF'],
			'items' => [
				new \OOUI\FieldsetLayout( [
					'items' => [
							new \OOUI\FieldLayout(
								new \OOUI\HiddenInputWidget( [
									'name' => 'users',
									'value' => implode(',',$users)
								] ),
								[]
							),
							new \OOUI\FieldLayout(
								new \OOUI\HiddenInputWidget( [
									'name' => 'subject',
									'value' => $subject
								] ),
								[]
							),
							new \OOUI\FieldLayout(
								new \OOUI\ButtonInputWidget( [
									'name' => 'confirmed',
									'label' => 'envoyer',
									'value' => true,
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
			] )
		);
		$out->addHTML( '<br>'.(string)new \OOUI\ButtonWidget( [
				'href' => $this->selfURL(),
				'label' => 'annuler'
			] ) );
	}

	private function showEnd( $sent, $fails ){
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addHTML( 'Votre mail a été envoyé.<br>');
		$out->addHTML( '<br>'.(string)new \OOUI\ButtonWidget( [
				'href' => $this->selfURL(),
				'label' => 'retour'
			] ) );

		$out->addHTML( '<div style="display:flex">');
		$sent = implode( '<br>', $sent );
		$out->addHTML( '<br><div style="margin:20px;"> <strong>Envois:</strong><br>' . $sent . '</div><br>' );
		$fails = implode( '<br>', $fails );
		$out->addHTML( '<br><div style="margin:20px;"> <strong>E-mail absents:</strong><br>' . $fails . '</div><br>' );
		$out->addHTML( '</div>');
	}

	/**
	 * @param string $element = 'groups'|'users'
	 * @param mixed $data
	 * @return array|null
	 */
	private function query( $element, $data = null ) {
		global $wgDBprefix;
		switch ( $element ) {

			case 'groups':
				$return = [];
				$sql = "SELECT ug_group FROM {$wgDBprefix}user_groups GROUP BY ug_group ORDER BY ug_group";
				$res = $this->mysqli_query($sql);
				foreach ( $res as $row ) {
					$return[] = $row['ug_group'];
				}
				return $return;
				break;

			case 'users':
				$return = [];
				$sql = "SELECT user_id FROM {$wgDBprefix}user";
				$where = '';
				if ( $data ) {
					foreach ( $data as $key => $field ) {
						$data[$key] = "'".$field."'";
					}
					$data = implode(',', $data);
					$where = " WHERE user_id IN (" .
						"SELECT ug_user FROM {$wgDBprefix}user_groups WHERE ug_group IN ({$data}) " .
						 "GROUP BY ug_user)";
				}
				$res = $this->mysqli_query($sql.$where);
				foreach ( $res as $row ) {
					$return[] = $row['user_id'];
				}
				return $return;
				break;
		}
	}

  /**
   * Requête brute
   * @param string $sql
   * @return array|null
   */
  private function mysqli_query( $sql ) {
    global $wgDBserver, $wgDBname, $wgDBuser, $wgDBpassword;
    $res = mysqli_connect( $wgDBserver, $wgDBuser, $wgDBpassword, $wgDBname );
		$result = mysqli_query( $res, $sql ) or die ('Erreur avec la requête "' . $sql . '" : ' . $res->error );
		$total = mysqli_num_rows( $result );
		if ( $total ) {
      $r = [];
			while ( $row = mysqli_fetch_assoc( $result ) ) {
				$r[] = $row;
			}
      return $r;
		}
    return [];
  }

	private function getHtmlBody() {
		$title = \Title::newFromText($this->body_page);
		$page = \WikiPage::factory($title);
		$body = $page->getContent()->getParserOutput($title)->getRawText();
		$body = str_replace('="/wiki/', '="'.$this->pre_URL, $body );
		return $body;
	}

	/**
	 * @param array $get associative array of GET request parameters
	 * [key => value]
	 */
	private function selfURL( array $get = [] ) {
		return \SpecialPage::getTitleFor( $this->special )->getLinkURL( $get );
	}

	protected function getGroupName() {
		return 'mgwiki';
	}
}
