<?php
/**
 * API pour envoyer un e-mail selon les paramètres définis dans la requête
 * Paramètres:
 ** type ('message'|'page'|'task')
 ** task_id (int) - obligatoire pour 'task'
 ** sender (string) 'user'|'default' - obligatoire pour 'message'|'page'
 ** recipient_id (int) - obligatoire pour 'message'|'page'
 ** main (string) le nom de la page ou du message à envoyer (sans '-body' ni '-subject')  - obligatoire pour 'message'|'page'
 */
namespace MediaWiki\Extension\MGWiki\Api;
use ApiBase;
use MediaWiki\Extension\MGWiki\Utilities\DataFunctions as DbF;
use MediaWiki\Extension\MGWiki\Utilities\MailFunctions as MailF;

class ApiMgwSendMail extends ApiBase {

	public function execute( ) {
    global $wgUser;
    global $wgEmergencyContact;

    $params = $this->extractRequestParams();
		$r = $this->getResult();

		try {
      # contrôle des paramètres
			if ( !isset( $params['type'] ) ) {
				throw new \Exception("Erreur : le paramètre 'type' doit être défini.");
			}
      if ( $params['type'] == 'task' &&  !isset( $params['task_id'] ) ) {
        throw new \Exception("Erreur : le paramètre 'task_id' doit être défini.");
      }
      if ( $params['type'] == 'task' &&  !isset( $params['task_do'] ) ) {
        throw new \Exception("Erreur : le paramètre 'task_do' doit être défini.");
      }
			if ( $params['type'] != 'task' && !isset( $params['recipient_id'] ) ) {
				throw new \Exception("Erreur : le paramètre 'recipient_id' doit être défini.");
			}
			if ( $params['type'] != 'task' && !isset( $params['sender'] ) ) {
				throw new \Exception("Erreur : le paramètre 'sender' doit être défini.");
			}
			if ( $params['type'] != 'task' && !isset( $params['main'] ) ) {
				throw new \Exception("Erreur : le paramètre 'main' doit être défini.");
			}

			// gestion de l'envoi depuis une task enregistrée dans la table
			$over = false;
			 # suppression de la tâche
			if ( $params['type'] == 'task' && $params['task_do'] == 'delete' ) {
				global $wgDBprefix;
				$sql = "UPDATE {$wgDBprefix}mgw_task SET task_archive = 1 WHERE task_id = " . $params['task_id'];
        DbF::mysqli_query($sql);
				$over = true;
			}
			 # envoi du mail à la première personne de la file active
      elseif ( $params['type'] == 'task' && $params['task_do'] == 'send' ) {
        $res = DbF::select( 'task', false, ['*'], ['id' => ['=', $params['task_id'] ] ] );
        if ( $res ) {
					if ( $res[0]['archive'] == '1' ) {
						# tâche archivée = envois terminés
						$over = true;
					}
					else {
						# tâche en cours
	          $data = json_decode( $res[0]['data'], true, 512, JSON_UNESCAPED_UNICODE );

	          $recipients_update = $data['recipients'];
						foreach ( $recipients_update as $k => $recip ) {
							$key = $k;
							break;
						}
	          $recipient = \User::newFromId( $recipients_update[$key] );
	          unset( $recipients_update[$key] );

						if ( $recipient ) {
		          $to = $recipient->getEmail();
							$recipients_names = $recipient->getName();
						}
						else {
							$to = null;
							$recipients_names = 'inconnu';
						}

	          $sender = \User::newFromId( $res[0]['updater_id'] );
	          $from = ( $sender ) ? $sender->getEmail() : null;

	          $subject = $data['subject'];
	          $body = $res[0]['extra'];
					}
        }
      }

      // envoi selon les paramètres de la requête
      else {
				if ( $params['recipient_id'] == 0 ) {
					# mail aux responsables techniques
					global $wgMgwBugMailto;
					$to = [];
					$recipients_names = [];
					foreach ( $wgMgwBugMailto as $userName ) {
						$user = \User::newFromName( $userName );
						$to[] = $user->getEmail();
						$recipients_names[] = $user->getName();
					}
					$recipients_names = implode( ', ', $recipients_names );
				}
				else {
					$recipient = \User::newFromId( $params['recipient_id'] );
					if ( $recipient ) {
						$to = $recipient->getEmail();
						$recipients_names = $recipient->getName();
					}
					else {
						$to = null;
						$recipients_names = 'inconnu';
					}
				}

        $from = ( $params['sender'] == 'user' ) ? $wgUser->getEmail() : $wgEmergencyContact;

        $subject_params = ( isset( $params['subject_params'] ) )
          ? explode( '|', $params['subject_params'] )
          : [];
        $subject = wfMessage( $params['main'] . '-subject', $subject_params )->text();

        $body_params = ( isset( $params['body_params'] ) )
          ? explode( '|', $params['body_params'] )
          : [];
        $body = ( $params['type'] == 'page' )
          ? $this->getPageHtmlBody( $params['main'] )
          : wfMessage( $params['main'] . '-body', $body_params )->parse();
      }

      # envoi du mail
			if ( $to && $from && !$over ) {
        $status = MailF::send( $from, $to, $subject, $body );
        if ( $status->isOK() ) {
          $out_status = 'done';
          $out_message = wfMessage( 'mgw-sendmail-success', $recipients_names )->text();
          $out_message_short = wfMessage( 'mgw-sendmail-success-short' )->text();
        }
        else {
          $out_status = 'failed';
          $out_message = $status->getWikiText();
          $out_message_short = wfMessage( 'mgw-sendmail-error-short' )->text();
        }
			}
			elseif ( $over ) {
				$out_status = 'done';
				$out_message = wfMessage( 'mgw-sendmail-task-archived' )->text();
				$out_message_short = wfMessage( 'mgw-sendmail-task-archived-short' )->text();
			}
      else {
        $out_status = 'failed';
        $out_message = ( $from )
          ? wfMessage( 'mgw-sendmail-recipient-missing', $recipients_names )->text()
          : wfMessage( 'mgw-sendmail-sender-missing' )->text();
				$out_message_short = ( $from )
          ? wfMessage( 'mgw-sendmail-recipient-missing-short' )->text()
          : wfMessage( 'mgw-sendmail-sender-missing-short' )->text();
      }

      # feedback
      $r->addValue( null, 'status', $out_status );
      $r->addValue( null, 'message', $out_message );
      $r->addValue( null, 'message-short', $out_message_short );

      # màj task
      if ( $params['type'] == 'task' && $params['task_do'] == 'send' ) {

        $data['recipients'] = $recipients_update;
				$data['count'] = $data['total'] - count( $data['recipients'] );

				if ( $out_status == 'failed' ) {
					$data[ $out_status ][] = [
						'id' => $recipient->getId(),
						'name' => $recipient->getName(),
						'mess' => $out_message_short
					];
				}
				else {
					$data[ $out_status ][] = [
						'id' => $recipient->getId(),
						'name' => $recipient->getName()
					];
				}

				$data_update = [ 'data' => json_encode( $data, JSON_UNESCAPED_UNICODE ) ];
				if ( $data['count'] == $data['total'] ) {
					$data_update['archive'] = 1;
				}

        DbF::update(
          'task',
          [ 'id' => ['=', $params['task_id']] ],
          $data_update,
          $res[0]['updater_id'],
          true,
          true
        );

				// retour des valeurs en cours
        $r->addValue( null, 'total', $data['total'] );
        $r->addValue( null, 'count', $data['count'] );
				$r->addValue( null, 'done', $data['done'] );
				$r->addValue( null, 'failed', $data['failed'] );
      }
		} catch (\Exception $e) {
			$r->addValue( null, "erreur", $e );
		}
  }

	private function getPageHtmlBody( $title_text ) {
		$title = \Title::newFromText($this->body_page);
		$page = \WikiPage::factory($title);
		$body = $page->getContent()->getParserOutput($title)->getRawText();
		$body = str_replace('="/wiki/', '="'.$this->pre_URL, $body );
		return $body;
	}

  protected function getAllowedParams() {
		return [
      'type' => [
        ApiBase::PARAM_TYPE => ['page', 'message', 'task']
      ],
      'task_id' => [
        ApiBase::PARAM_TYPE => 'integer'
      ],
      'task_do' => [
        ApiBase::PARAM_TYPE => ['send','delete']
      ],
			'recipient_id' => [
				ApiBase::PARAM_TYPE => 'integer'
			],
			'sender' => [
				ApiBase::PARAM_TYPE => ['user', 'default']
			],
			'main' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'subject_params' => [
				ApiBase::PARAM_TYPE => 'string'
			],
			'body_params' => [
				ApiBase::PARAM_TYPE => 'string'
			]
		];
	}

	public function mustBePosted() {
		return true;
	}

	public function needsToken() {
		return 'csrf';
	}
}
