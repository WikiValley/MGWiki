( function ( mw, $ ) {

	// Display on articles when viewing the current revision
	if( mw.config.get( 'wgIsArticle' ) && mw.config.get( 'wgAction' ) === 'view' && mw.config.get( 'wgRevisionId' ) === mw.config.get( 'wgCurRevisionId' ) && mw.config.get( 'wgDiffOldId' ) === null ) {

		$( function () {
	
			// Add the click action to the link - it could be added elsewhere easily also
			$( '#p-tb li.mgwiki-send-notification a' ).click( function( e ) {

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

				e.preventDefault();
				e.stopPropagation();

				return false;
			} );
		} );
	}

}( mediaWiki, jQuery ) );
