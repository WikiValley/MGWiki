( function ( mw, $ ) {

  mw.mgw_input_changed = {
    "prenom": false,
    "nom": false,
    "email": false
  }

  mw.mgw_check_changes = function( field ) {
    origin = $('#mgw-field-'+field+' .mgw-hidden-data' ).html();
    if ( inputs[field].getValue() != origin ) {
      color = '#fffbd6';
      mw.mgw_input_changed[field] = true;
    }
    else {
      color = '#fff';
      mw.mgw_input_changed[field] = false;
    }
    $('#mgw-'+field+' input').css({'background-color': color});
    mw.mgw_restore_btn();
  }

  mw.mgw_sanitize_prenom = function( str ) {
    str = str.split('-');
    str_new = [];
    str.forEach((item, i) => {
      str_new.push( item.slice(0,1).toUpperCase() + item.slice(1).toLowerCase() );
    });
    str = str_new.join('-');

    str = str.split(' ');
    str_new = [];
    str.forEach((item, i) => {
      str_new.push( item.slice(0,1).toUpperCase() + item.slice(1) );
    });
    str = str_new.join(' ');

    return str;
  }

  mw.mgw_restore_btn = function(){
    if ( mw.mgw_input_changed['prenom'] || mw.mgw_input_changed['nom'] || mw.mgw_input_changed['email'] )
      $('#mgw-restore-btn').show();
    else $('#mgw-restore-btn').hide();
  }

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

  $('#mgw-restore-btn').click( function( event ){
    event.preventDefault();

    inputs.prenom.setValue( $( '#mgw-field-prenom .mgw-hidden-data' ).html() );
    inputs.nom.setValue( $( '#mgw-field-nom .mgw-hidden-data' ).html() );
    inputs.email.setValue( $( '#mgw-field-email .mgw-hidden-data' ).html() );
  } );

  var inputs = {};
  inputs.prenom = OO.ui.infuse( $( '#mgw-prenom' ) );
  inputs.nom = OO.ui.infuse( $( '#mgw-nom' ) );
  inputs.email = OO.ui.infuse( $( '#mgw-email' ) );

  inputs.prenom.on('change', function(){
    str = inputs.prenom.getValue();
    inputs.prenom.setValue( mw.mgw_sanitize_prenom( str ) );
    mw.mgw_check_changes('prenom');
  } );
  inputs.nom.on('change', function(){
    str = inputs.nom.getValue();
    inputs.nom.setValue( str.toUpperCase() );
    mw.mgw_check_changes('nom');
  } );
  inputs.email.on('change', function(){
    mw.mgw_check_changes('email');
  } );
  mw.mgw_check_changes('prenom');
  mw.mgw_check_changes('nom');
  mw.mgw_check_changes('email');


  //$('#mgw-field-prenom').on('key', console.log('coucou'));

}( mediaWiki, jQuery ) );
