( function ( mw, $ ) {

  $('#mgw-confirm').html( new OO.ui.ButtonWidget( {
    label: 'Confirmer les modifications',
    flags: [
      'primary',
      'progressive'
    ]
  } ).$element );

  $('#mgw-confirm').click( function( event ){
    event.preventDefault();
    $('button[name=submit]').val('confirm');
    $('button[name=submit]').click();
  })

}( mediaWiki, jQuery ) );
