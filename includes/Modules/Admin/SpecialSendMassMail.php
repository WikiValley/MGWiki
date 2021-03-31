<?php
/**
 * Page spéciale pour envoyer un Email à tout un groupe d'utilisateurs
 */

namespace MediaWiki\Extension\MGWiki\Modules\Admin;

use MediaWiki\Extension\MGWiki\Utilities\DataFunctions as DbF;
use MediaWiki\Extension\MGWiki\Utilities\MailFunctions as MailF;
use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWiki\Utilities\HtmlFunctions as HtmlF;

class SpecialSendMassMail extends \SpecialPage {

	private $special, $body_page, $footer_page, $groups;

	public function __construct() {

		global $wgUser;
		$this->special = 'MgwSendMassMail';

		$body = wfMgwConfig( 'sendmassmail', 'body-page' );
		$this->body_page = $body['pre'] . $wgUser->getName() . '/' . $body['string'];

		$this->footer_page = wfMgwConfig( 'sendmassmail', 'footer-page' );

		$this->pre_URL = wfMgwConfig( 'sendmassmail', 'url-pre' );

		parent::__construct( $this->special, 'editinterface' );
	}

	public function execute( $par ) {

		global $wgUser;
		$user = $this->getUser();
		$request = $this->getRequest();
		$post = ( $request->wasPosted() ) ? $request->getPostValues() : [ 'submit' => '' ];
		$this->post_sanitize( $post );
		$this->groups = ( $post['mw_groups'] ) ? explode( ',', $post['mw_groups'] ) : [];

		$this->setHeaders();
		$out = $this->getOutput();
		$out->enableOOUI();
		$out->addModules('ext.mgwiki.mass-email');
			# on ajoute directement les styles au header pour diminuer le délai de mise en forme
		$out->addHeadItems( HtmlF::include_resource_file ( 'mass-email.css', 'style' ) );

		// SUBMIT
		if ( $post['submit'] == 'submit' ) {

			# constitution de la liste des destinataires
			$users = $this->db_query( 'users', $this->groups );
			$this->showConfirm( $out, $users, $post );
			return;
		}

		// SEND
		if ( $post['submit'] == 'send'	) {

			# préparation d'une TASK
			$users = explode( ',', $post['users'] );
			$data = [
				"recipients" => $users,
				"subject" => $post['subject'],
				'groups' => $this->groups,
				"total" => count( $users ),
				"count" => 0,
				"done" => [],
				'failed' => []
			];
			$extra = $this->getHtmlBody();

			$status = DbF::insert(
				'task',
				[ 'label' => 'mass-email', 'data' => json_encode( $data, JSON_UNESCAPED_UNICODE ), 'extra' => $extra ],
				$wgUser->getId(),
				true // retourne l'id du nouvel enregistrement
			);

			# préparation des données AJAX
			if ( $status->done() ) {
				$ajax = [
					'status' => 'done',
					'task_id' => $status->extra()
				];
			}
			else {
				$ajax = [
					'status' => 'failed',
					'message' => MailF::bug( '', $status->mess() )
				];
			}
			$this->showSend( $out, $ajax, $post );
			return;
		}

		// RECALL
		if ( $post['submit'] == 'recall' ) {
			$ajax = [
				'status' => 'done',
				'task_id' => $post['task_id']
			];
			$this->showSend( $out, $ajax, $post );
			return;
		}

		// DELETE
		if ( $post['submit'] == 'delete' ) {
			$ajax = [
				'status' => 'done',
				'task_id' => $post['task_id']
			];
			$this->showSend( $out, $ajax, $post, 'delete' );
			return;
		}

		// UNDELETE
		if ( $post['submit'] == 'undelete' ) {
			$this->db_query('undelete', $post['task_id'] );
			$ajax = [
				'status' => 'done',
				'task_id' => $post['task_id']
			];
			$this->showSend( $out, $ajax, $post, 'run' );
			return;
		}

		// HISTORY
		if ( $post['submit'] == 'history'	) {
			$pending = $this->db_query('pending_tasks');
			$archived = $this->db_query('archived_tasks');
			$this->showHistory( $out, $pending, $archived );
			return;
		}

		// DETAILS
		if ( $post['submit'] == 'details'	) {
			$task = DbF::select( 'task', false, ['*'], [ 'id' => ['=', $post['task_id'] ] ] );
			$this->showDetails( $out, $task[0] );
			return;
		}

		// DEFAULT
		$this->showDefault( $out, $post );
	}

	private function showConfirm( &$out, $users, $post ) {

		$out->setPageTitle( wfMessage('mgwsendmassmail')->text() . ' confirmation' );

		$out->addHTML( '
			<div class="mgw-massmail-head mgw-massmail-head-waiting-task">
				<div class="mgw-massmail-head-info mgw-massmail-head-info-waiting-task">
					'.wfMessage('mgw-massmail-confirm', count($users) )->text() . '
				</div>
			</div>' );
		$out->addHTML( '<strong>Destinataires:</strong><br>');
		$groupes = ( $this->groups ) ? implode( ', ', $this->groups ) : '(tous les utilisateurs)';
		$out->addHTML( '<i>groupes MediaWiki:</i> <strong>' . $groupes . '</strong></div><br>');
		$out->addHTML( '<strong>Sujet:</strong><br>');
		$out->addHTML( '<div class="mgw-massmail-details-body">' . $post['subject'] . '</div><br>');
		$out->addHTML( '<strong>Corps:</strong><br>');
		$out->addHTML( '<div class="mgw-massmail-details-body">' . $this->getHtmlBody( false ) . '</div>');

		$hiddenFields = [
			//"main_select" => $post['main_select'],
			"subject" => $post['subject'],
			"mw_groups" => $post['mw_groups'],
			"users" => implode(',',$users)
		];

		$out->addHTML( HtmlF::form( 'open', $hiddenFields ) );
		$out->addHTML( '<br>' . new \OOUI\ButtonInputWidget( [
			'name' => 'submit',
			'label' => 'envoyer',
			'value' => 'send',
			'type' => 'submit',
			'flags' => [ 'primary', 'progressive' ],
			'icon' => 'check',
			] ) . new \OOUI\ButtonInputWidget( [
			'name' => 'submit',
			'label' => 'modifier',
			'value' => '',
			'type' => 'submit',
			'flags' => [ 'progressive' ]
			] )
		);
		$out->addHTML( HtmlF::form( 'close' ) );
		$out->addHTML( '<br>'.(string)new \OOUI\ButtonWidget( [
				'href' => $this->selfURL(),
				'label' => 'annuler'
			] ) );
	}

	private function showSend( &$out, $ajax, $post, $action = 'run' ) {
		$titleInfo = ( $action == 'run' ) ? ' envoi' : ' suppression';
		$out->setPageTitle( wfMessage('mgwsendmassmail')->text() . $titleInfo );

		// données pour javascript
		$out->addHTML( '<div id="mgw-data-transfer" style="display:none">'.json_encode($ajax).'</div>');
		$out->addHTML( '<input name="send_status" value="' . $action . '" hidden >' ); // tant que sur la valeur "run" les requêtes d'envoi continuent
		$out->addHTML( '<input name="send_error" value="" hidden >' );

		// canevas d'affichage

		# boutons
		$out->addHTML( '<div id="mgw-massmail-buttons">');


		$out->addHTML( '<div id="mgw-massmail-go-back" class="mgw-massmail-send-btn" style="display:none">' );
		$out->addHTML( new \OOUI\ButtonWidget( [
				'id' => 'mgw-massmail-go-back-btn',
				'label' => 'retour',
				'flags' => []
			] ) );
		$out->addHTML( '</div>' );

		$out->addHTML( '<div id="mgw-massmail-stop" class="mgw-massmail-send-btn" style="display:none">' );
		$out->addHTML( new \OOUI\ButtonWidget( [
				'id' => 'mgw-massmail-stop-btn',
				'label' => 'interrompre',
				'flags' => ['destructive']
			] ) );
		$out->addHTML( '</div>' );

		$out->addHTML( '<div id="mgw-massmail-restart" class="mgw-massmail-send-btn" style="display:none">' );
		$out->addHTML( new \OOUI\ButtonWidget( [
				'id' => 'mgw-massmail-restart-btn',
				'label' => 'reprendre',
				'flags' => ['primary','progressive']
			] ) );
		$out->addHTML( '</div>' );

		$out->addHTML( '<div id="mgw-massmail-delete" class="mgw-massmail-send-btn" style="display:none">' );
		$out->addHTML( new \OOUI\ButtonWidget( [
				'id' => 'mgw-massmail-delete-btn',
				'label' => 'supprimer',
				'flags' => ['primary','destructive']
			] ) );
		$out->addHTML( '</div>' );

		$out->addHTML( '</div>' );

		# information
		$out->addHTML( '<div id="mgw-massmail-send-info"></div>');

		# feedback
		$out->addHTML( '<div id="mgw-massmail-feedback">
											<div id="mgw-massmail-done"></div>
											<div id="mgw-massmail-failed"></div>
										</div>');
	}

	private function showHistory( &$out, $pending, $archived ) {

		$out->setPageTitle( 'E-mail à tous les utilisateurs ... historique' );

		$out->addHTML( HtmlF::form( 'open', [	'task_id' => '', 'submit' => '' ] ) );
		$out->addHTML( HtmlF::form( 'close' ) );

		$pending_list = '';
		foreach ( $pending as $task ) {
			$pending_list .= $this->history_row( $task, true );
		}
		$out->addHTML( '<fieldset><legend>Envois interrompus</legend>');
		$out->addHTML( '<div class="mgw-massmail-fieldset"><ul>' . $pending_list . '</ul></div></fieldset>' );

		$archived_list = '';
		foreach ( $archived as $task ) {
			$archived_list .= $this->history_row( $task, false );
		}
		$out->addHTML( '<fieldset><legend>Envois archivés</legend>');
		$out->addHTML( '<div class="mgw-massmail-fieldset"><ul>' . $archived_list . '</ul></div></fieldset>' );

		$out->addHTML( '<br>'.(string)new \OOUI\ButtonWidget( [
				'href' => $this->selfURL(),
				'label' => 'retour'
			] ) );
	}

	private function history_row( $task, $pending = true ) {
			$data = json_decode( $task['task_data'], true );
			$groupes = ( isset( $data['groups'] ) && $data['groups'] )
				? implode( ', ', $data['groups'] )
				: 'tous les utilisateurs';
			$buttons = ( !$pending ) ? ''
				: '<button class="mgw-massmail-history-recall" task_id="'.$task['task_id'].'">reprendre</button>';
			$buttons .=
				'<span class="mgw-massmail-history-details" task_id="'.$task['task_id'].'">voir</span>';
			$buttons .= ( !$pending ) ? ''
				: '<span class="mgw-massmail-history-delete" task_id="'.$task['task_id'].'">supprimer</span>';
			return '<li>' .
				date( 'd-m-Y H:i:s', wfTimestamp( TS_UNIX, $task['task_update_time'] ) ) . ' - ' . $buttons .
				'<br><span class="mgw-massmail-history-details-row">' .
				'<i>Sujet: </i><strong>' . $data['subject'] . '</strong> - <i>Destinataires: </i><strong>' . $groupes .
				'</strong> - <i>Envois: </i><strong>' . $data['count'] . ' / ' . $data['total'] . '</strong></span></li>';
	}

	private function showDetails( &$out, $task ) {

		$type = ( $task['archive'] == 0 ) ? 'interrompu' : 'archivé';
		$out->setPageTitle( wfMessage('mgwsendmassmail')->text() . ' envoi ' . $type );
		$out->addScript('<script>document.getElementById("content").style.background = "#f4f4f4";</script>');

		$data = json_decode( $task['data'], true, 512, JSON_UNESCAPED_UNICODE );

		$out->addHTML( HtmlF::form( 'open', [	'task_id' => $task['id'] ]));
		$out->addHTML('<div id="mgw-massmail-details-head">');

		$head_btn = '<div class="mgw-massmail-head-btn-quiet-task">' . new \OOUI\ButtonInputWidget( [
				'name' => 'submit',
				'label' => 'historique',
				'value' => 'history',
				'type' => 'submit'
			] ) . new \OOUI\ButtonInputWidget( [
				'name' => 'submit',
				'label' => 'retour',
				'value' => '',
				'type' => 'submit'
			] ) . '</div>';

		if ( $data['total'] > $data['count'] ) {
			$count = '('.$data['count'].' mails envoyés / '.$data['total'].')';
			$head_btn = new \OOUI\ButtonInputWidget( [
				'name' => 'submit',
				'label' => 'reprendre l\'envoi',
				'value' => 'undelete',
				'type' => 'submit',
				'flags' => ['progressive']
			] ) . $head_btn;
		}
		else {
			$count = '('.$data['count'].' mails envoyés)';
		}

		$date = date( 'd-m-Y H:i:s', $task['update_time'] );
		$head = '<strong><big>Envoi ' . $type . ' du ' . $date . ' ' . $count . '</big></strong><br><br>';

		$out->addHTML('<div id="mgw-massmail-details-info">' . $head . '</div>' . $head_btn . '<br>');
		$out->addHTML( '</div><br>' );
		$out->addHTML( HtmlF::form( 'close' ) );

		$out->addHTML( '<strong>Sujet:</strong><br>');
		$out->addHTML( '<div class="mgw-massmail-details-body-details">' . $data['subject'] . '</div>');
		$out->addHTML( '<strong>Message:</strong><br>');
		$out->addHTML( '<div class="mgw-massmail-details-body-details">' . $task['extra'] . '</div><br>');

		$todo = '';
		if ( $data['recipients'] ) {
			$todo .= '<div id="mgw-massmail-todo">
				<strong>Non envoyés: ' . count($data['recipients']) . '</strong><br>';
			foreach ( $data['recipients'] as $recipient ) {
				$user = \User::newFromId( $recipient );
				$todo .= $user->getName() . '<br>';
			}
			$todo .= '</div>';
		}
		$done = '';
		if ( $data['done'] ) {
			$done .= '<div id="mgw-massmail-done">
				<strong>Envoyés: ' . count($data['done']) . '</strong><br>';
			foreach ( $data['done'] as $row ) {
				$done .= $row['name'] . '<br>';
			}
			$done .= '</div>';
		}
		$failed = '';
		if ( $data['failed'] ) {
			$failed .= '<div id="mgw-massmail-failed">
				<strong>Echecs: ' . count($data['failed']) . '</strong><br>';
			foreach ( $data['failed'] as $row ) {
				$failed .= $row['name'] . ' ('.$row['mess'].') <br>';
			}
			$failed .= '</div>';
		}
		$out->addHTML( '<div id="mgw-massmail-feedback">' . $todo . $done . $failed . '</div>');
	}

	private function showDefault( &$out, $post ) {

		$out->setPageTitle( wfMessage('mgwsendmassmail')->text() . ' créer' );
		$out->addHeadItems( HtmlF::include_resource_file ( 'ooui-search.css', 'style' ) );

		// HEAD
		$waiting_task = $this->db_query('waiting_task');
		if ( $waiting_task ) {
			$text = wfMessage('mgw-massmail-task-waiting')->text();
			$label = 'afficher';
			$headclass = 'mgw-massmail-head-waiting-task';
			$infoclass = 'mgw-massmail-head-info-waiting-task';
			$btnclass = 'mgw-massmail-head-btn-waiting-task';
		}
		else {
			$text = '';
			$label = 'historique des envois';
			$headclass = 'mgw-massmail-head-quiet-task';
			$infoclass = 'mgw-massmail-head-info-quiet-task';
			$btnclass = 'mgw-massmail-head-btn-quiet-task';
		}

		$out->addHTML( HtmlF::form( 'open' ) );
		$out->addHTML('
			<div class="mgw-massmail-head '.$headclass.'">
				<div class="mgw-massmail-head-info '.$infoclass.'">' . $text . '</div>
				<div class="mgw-massmail-head-btn '.$btnclass.'">' .
				new \OOUI\ButtonInputWidget( [
					'name' => 'submit',
					'id' => 'mgw-massmail-history-btn',
					'label' => $label,
					'value' => 'history',
					'type' => 'submit'
				] ) . '</div>
			</div>' );

		// FORM
			# construction des données à destination de ext.mgw-ooui-search
		$hiddenFields = [
			"mw_groups" => [
				"data" => $this->groups,
				"label" => $this->groups,
				"multiple" => true,
				"required" => false,
				"empty_text" => '(tous les utilisateurs)',
				"placeholder" => '(choix multiple)',
			]
		];
		$out->addHTML( '<div id="mgw-ooui-data-transfer" hidden>' . json_encode($hiddenFields). '</div>' );

		$out->addHTML( '<div>' );
		$out->addHTML( '<strong>Destinataires :</strong><br>' );
		$out->addHTML( 'groupes MediaWiki:<br>' );
		$out->addHTML( '<input name="mw_groups" value="'.$post['mw_groups'].'" hidden><br>');

		$out->addHTML( '<div>' );
		$out->addHTML( '<strong>Sujet :</strong>' );

		$out->addHTML( new \OOUI\TextInputWidget( [
				'name' => 'subject',
				'type' => 'text',
				'value' => ( isset( $post['subject'] ) ) ? $post['subject'] : '',
				'required' => true
			] ) . '</div><br>'
		);

		$out->addHTML( '<div>' .
			'<strong>Message : </strong>' .
			new \OOUI\ButtonWidget( [
				"framed" => false,
				"flags" => [
					'progressive'
				],
				"label" => 'editer',
				"href" => '/wiki/index.php?title=' . $this->body_page . '&action=edit',
				"target" => '_blank',
				"rel" => [
					'noreferrer',
					'noopener'
				]
			] ) . ' / ' . new \OOUI\ButtonInputWidget( [
				'name' => 'submit',
				'label' => 'rafraîchir',
				'value' => '',
				'type' => 'submit',
				"framed" => false,
				"flags" => [
					'progressive'
				]
			] ) . '<div id="mgw-massmail-edit-body">' . $this->getHtmlBody( false ) . '</div></div>'
		);
		$out->addHTML(
			new \OOUI\FieldLayout(
				new \OOUI\ButtonInputWidget( [
					'name' => 'submit',
					'label' => 'soumettre',
					'value' => 'submit',
					'type' => 'submit',
					'flags' => [ 'primary', 'progressive' ],
					'icon' => 'check'
				] ),
				[
					'label' => null,
					'align' => 'top',
				]
			)
		);
		$out->addHTML( HtmlF::form( 'close' ) );
	}

	/**
	 * @param string $element = 'groups'|'users'
	 * @param mixed $data
	 * @return array|null
	 */
	private function db_query( $element, $data = null ) {
		global $wgDBprefix;
		global $wgUser;
		$user_id = $wgUser->getId();

		switch ( $element ) {

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
				$res = DbF::mysqli_query($sql.$where);
				foreach ( $res as $row ) {
					$return[] = $row['user_id'];
				}
				return $return;
				break;

			case 'waiting_task':
				$sql = "SELECT task_id FROM {$wgDBprefix}mgw_task ".
						"WHERE task_archive = 0 ".
						"AND task_label = 'mass-email' " .
						"AND task_updater_id = {$user_id} " .
						"ORDER BY task_update_time DESC";
				$res = DbF::mysqli_query( $sql );
				if ( $res ) {
					return $res[0]['task_id'];
				}
				return null;
				break;

			case 'pending_tasks':
				$sql = "SELECT * FROM {$wgDBprefix}mgw_task ".
						"WHERE task_archive = 0 " .
						"AND task_label = 'mass-email' " .
						"AND task_updater_id = {$user_id} " .
						"ORDER BY task_update_time DESC";
				$res = DbF::mysqli_query( $sql, false );
				return $res;
				break;

			case 'archived_tasks':
				$sql = "SELECT * FROM {$wgDBprefix}mgw_task ".
						"WHERE task_archive = 1 ".
						"AND task_label = 'mass-email' " .
						"AND task_updater_id = {$user_id} " .
						"ORDER BY task_update_time DESC";
				$res = DbF::mysqli_query( $sql, false );
				return $res;
				break;

			case 'undelete':
				$sql = "UPDATE {$wgDBprefix}mgw_task SET task_archive = 0 WHERE task_id = " . $data;
				$res = DbF::mysqli_query( $sql, false, true );
				return;
				break;
		}
	}

	private function getHtmlBody( $links_replace = true ) {

		# body
		$title = \Title::newFromText($this->body_page);
		$page = \WikiPage::factory($title);
		$body = ( $title->getArticleID() ) ? $page->getContent()->getParserOutput($title)->getRawText() : '';

		# footer
		$title = \Title::newFromText($this->footer_page);
		$page = \WikiPage::factory($title);
		$body .= ( $title->getArticleID() ) ? $page->getContent()->getParserOutput($title)->getRawText() : '';

		if ( $links_replace ) {
			$body = str_replace('="/wiki/', '="'.$this->pre_URL, $body );
		}
		return $body;
	}

	private function post_sanitize( &$post ) {
		if ( isset( $post['subject'] ) ) {
			$post['subject'] = htmlspecialchars( $post['subject'] );
		}
		if ( !isset( $post['mw_groups'] ) ) {
			$post['mw_groups'] = '';
		}
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
