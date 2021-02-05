( function ( mw, $ ) {

	// Display on articles when viewing the current revision
	if( mw.config.get( 'wgIsArticle' ) && mw.config.get( 'wgAction' ) === 'view' && mw.config.get( 'wgRevisionId' ) === mw.config.get( 'wgCurRevisionId' ) && mw.config.get( 'wgDiffOldId' ) === null ) {

		$( function () {

			// Add the click action to the link
			$( '#p-tb li.mgwiki-send-notification a, .mgwiki-send-notification-button' ).click( function( e ) {

				// API to query recipients
				// TODO: interface avec choix parmi les destinataires proposés
				let recipients;
				new mw.Api().postWithToken( 'csrf', {
					action: 'mgwiki-send-notification',
					title: mw.config.get( 'wgPageName' ),
					module: 'get',
					errorformat: 'plaintext'
				} )
				.then( function ( ret ) {
						i = 0;
			      for (x in ret) {
							if (i > 0) { recipients += ', '; }
							recipients += ret[x].user_name;
							i++;
		        }
					// It failed: display the error to the user
					}, function( code, result ) {
						let error = result && result.errors && result.errors[0] && result.errors[0].code && result.errors[0]['*'] ? result.errors[0] : '';
					if( error && error.code === 'mgwiki-error-when-sending-email' ) {
						mw.notify( result.errors[0]['*'], { title: mw.message( 'mgwiki-notification-title' ), type: 'warn', autoHide: false } );
					} else if( error ) {
						mw.notify( result.errors[0]['*'], { title: mw.message( 'mgwiki-notification-title' ), type: 'error', autoHide: false } );
					} else {
						mw.notify( mw.message( 'mgwiki-notification-unknown-error' ), { title: mw.message( 'mgwiki-notification-title' ), type: 'error', autoHide: false } );
					}
					// on arrête le script
					return;
				} );

				var confirmationDialog = new OO.ui.MessageDialog();

				// Create and append a window manager.
				var windowManager = new OO.ui.WindowManager();
				$( 'body' ).append( windowManager.$element );

				// Add the dialog to the window manager.
				windowManager.addWindows( [ confirmationDialog ] );

				// Configure the message dialog when it is opened with the window manager's openWindow() method.
				windowManager.openWindow( confirmationDialog, {
					title: mw.message( 'mgwiki-send-notification-confirm-title' ).text(),
					message: mw.message( 'mgwiki-send-notification-confirm-top', recipients ).text()
				} ).closed.then( function ( data ) {
					if( !data || data.action !== 'accept' ) {
						return;
					}

					// Trigger the API action
					new mw.Api().postWithToken( 'csrf', {
						action: 'mgwiki-send-notification',
						title: mw.config.get( 'wgPageName' ),
						errorformat: 'plaintext'
					} )

					// It worked: acknowledge to the user
					.then( function() {
						mw.notify( mw.message( 'mgwiki-notification-success' ), { title: mw.message( 'mgwiki-notification-title' ), type: 'success' } );

						// It failed: display the error to the user
						}, function( code, result ) {
							let error = result && result.errors && result.errors[0] && result.errors[0].code && result.errors[0]['*'] ? result.errors[0] : '';
						if( error && error.code === 'mgwiki-error-when-sending-email' ) {
							mw.notify( result.errors[0]['*'], { title: mw.message( 'mgwiki-notification-title' ), type: 'warn', autoHide: false } );
						} else if( error ) {
							mw.notify( result.errors[0]['*'], { title: mw.message( 'mgwiki-notification-title' ), type: 'error', autoHide: false } );
						} else {
							mw.notify( mw.message( 'mgwiki-notification-unknown-error' ), { title: mw.message( 'mgwiki-notification-title' ), type: 'error', autoHide: false } );
						}
					} );
				} );

				e.preventDefault();
				e.stopPropagation();

				return false;
			} );
		} );
	}

}( mediaWiki, jQuery ) );
