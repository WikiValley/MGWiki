( function ( mw, $ ) {

	/**
	 * programme pour envoi des mails UN A UN
	 * via l'api 'mgw-send-mail'
	 */

	 // déclaration des fonctions

	 /* SEND */
	 mw.mgw_api_send = function() {

	  $('#mgw-massmail-stop').hide();
 		$('#mgw-massmail-restart').hide();
 		$('#mgw-massmail-delete').hide();
		$('#mgw-massmail-go-back').hide();

		let status = $('input[name=send_status]').val();

		if ( status == 'stop' ) {
 			$('#mgw-massmail-send-info').html( 'L\'envoi a été interrompu.' );
 			$('#mgw-massmail-restart').show();
 			$('#mgw-massmail-delete').show();
			$('#mgw-massmail-go-back').show();
			return;
		}

		else if ( status == 'delete' ) {
			 new mw.Api().postWithToken( 'csrf', {
				 action: 'mgw-send-mail',
				 type: 'task',
				 task_id: mw.mgw_reqData['task_id'],
				 task_do: 'delete'
			 } ).then( function( ret ) {
		  		$('#mgw-massmail-send-info').html( 'Le message a été archivé.' );
		  		$('#mgw-massmail-go-back').show();
			 });
			return;
 		}

		else if ( status == 'error' ) {
			// envoi d'un mail d'erreur au webmaster
			let d = new Date();
			let user = mw.config.get( 'wgUserName' );
			new mw.Api().postWithToken( 'csrf', {
			  action: 'mgw-send-mail',
			  type: 'message',
			  main: 'mgw-bug-email',
				recipient_id: 1,
				sender: 'default',
			  body_params: d + '|' + user + '|' + $('input[name=send_error]').val()
			} );

 			$('#mgw-massmail-send-info').html( 'Une erreur est survenue. Un administrateur en est informé.' );
			$('#mgw-massmail-go-back').show();
			return;
 		}

		else if ( status == "end" ) {
			$('#mgw-massmail-send-info').html( 'L\'envoi est terminé.' );
 			$('#mgw-massmail-go-back').show();
 			return;
		}

		else if ( status = "run" ) {

			$('#mgw-massmail-stop').show(); // possibilité de stopper les envois

			// envois en tant que tel
			var req = {
				action: 'mgw-send-mail',
				type: 'task',
				task_id: mw.mgw_reqData['task_id'],
				task_do: 'send'
			};

			new mw.Api().postWithToken( 'csrf', req ).then( function( ret ) {

				// gestion des erreurs
				if ( typeof ret.erreur !== 'undefined' || typeof ret.errorclass !== 'undefined' ) {
					let erreur = ( ret.erreur.length > 0 ) ? ret.erreur : ret.info + ret['*'];
					report = 'REQUETE : ' + JSON.stringify(req) + '<br><br>';
					report += 'ERREUR : ' + erreur;
					$('input[name=send_error]').val(report);
					$('input[name=send_status]').val('error');
					$('input[name=send_status]').trigger('change');
					return;
				}
				// résultat ok:
				else {
					// on affiche le résultat
					$('#mgw-massmail-send-info').html( 'Envoi du message ' + ret.count + '/' + ret.total + ' : ' + ret.message );

					// liste des mails envoyés
					let display_done = '';
					if ( ret['done'].length > 0 ) {
						display_done = '<strong>Envoyés: '+ret['done'].length+'</strong><br>';
						ret['done'].forEach((item) => {
							display_done += item['name'] + '<br>';
						});
					}
					$('#mgw-massmail-done').html( display_done );

					let display_failed = '';
					if ( ret['failed'].length > 0 ) {
						display_failed = '<strong>Echecs: '+ret['failed'].length+'</strong><br>';
						ret['failed'].forEach((item) => {
							display_failed += item['name'] + ' (' +item['mess']+ ')<br>';
						});
					}
					$('#mgw-massmail-failed').html( display_failed );

					// vérification de fin de boucle
					if ( ret['total'] == ret['count'] ) {
						$('input[name=send_status]').val('end');
					}

					// dans tous les cas on relance la fonction
					$('input[name=send_status]').trigger('change');
				}
			} );
		}
	 }

	// initialisation des triggers
	$('#mgw-massmail-restart-btn').click( function ( event ){
		event.preventDefault();
		$('input[name=send_status]').val('run');
		mw.mgw_api_send();
	});

	$('#mgw-massmail-delete-btn').click( function ( event ){
	  event.preventDefault();
		$('input[name=send_status]').val('delete');
	  mw.mgw_api_send();
	});

	$('#mgw-massmail-history-btn').click( function () {
		$('input[name=subject]').removeAttr('required');
	});

	$('.mgw-massmail-history-details').click( function ( e ) {
		e.preventDefault();
		$('input[name=task_id]').val( $(this).attr('task_id') );
		$('input[name=submit]').val('details');
		$('input[name=submit]').click();
	});

	$('.mgw-massmail-history-recall').click( function ( e ) {
		e.preventDefault();
		$('input[name=task_id]').val( $(this).attr('task_id') );
		$('input[name=submit]').val('recall');
		$('input[name=submit]').click();
	});

	$('.mgw-massmail-history-delete').click( function ( e ) {
		e.preventDefault();
		$('input[name=task_id]').val( $(this).attr('task_id') );
		$('input[name=submit]').val('delete');
		$('input[name=submit]').click();
	});

	$('#mgw-massmail-stop-btn').click( function ( event ){
		event.preventDefault();
		$('input[name=send_status]').val('stop');
		$('input[name=send_status]').trigger('change');
	});

	$('#mgw-massmail-go-back-btn').click( function ( event ){
		event.preventDefault();
		document.location.href="/wiki/index.php/Spécial:MgwSendMassMail";
	});

	$('input[name=send_status]').change( function() {
		mw.mgw_api_send();
	});

	// au chargement de la page: récupération des données pour la requête
	if ( $('#mgw-data-transfer').length > 0 ) {

	 	mw.mgw_reqData = JSON.parse( $('#mgw-data-transfer').html() );

	 	// erreur à la préparation de l'envoi
	 	if ( mw.mgw_reqData['status'] == 'failed' ) {
	 		$('#mgw-massmail-send-info').html( mw.mgw_reqData['message'] );
			$('#mgw-massmail-send-info').css({"background-color":"#fbe9e7", "padding":"10px"});
	 		$('#mgw-massmail-go-back').show();
	 		return;
	 	}

		// lancement de la boucle d'envois
		if ( mw.mgw_reqData['status'] == 'done' ) {
			mw.mgw_api_send();
		}
 	}

}( mediaWiki, jQuery ) );
