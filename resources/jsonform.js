( function ( mw, $ ) {

  mw.changeform = function( jsonForm ){
    var api = new mw.Api();
    api.get( {
        action: 'getjson',
        service: jsonForm,
    } ).done( function ( data ) {

      // affichage .mgw-hidden ssi un champs coché
      if ( $('input[type=radio]:checked').attr('value') ) {
        $('.mgw-hidden').show()
      } else { $('.mgw-hidden').hide() }

      // construction du controlleur
      let reader = {};
      Object.keys(data['controllers']['onclick']['changeform']).forEach( controlfield => {
        let checkfield = data['controllers']['onclick']['changeform'][controlfield]['onfield'];
        let checkvalues = data['controllers']['onclick']['changeform'][controlfield]['onvalues'];
        if ( $('input[name=' + controlfield + ']:checked').attr('value') &&
            ( checkfield == "any" || checkvalues.indexOf( $('input[name=' + checkfield + ']:checked').attr('value') ) >= 0 ) )
        {
          reader[controlfield] = $('input[name=' + controlfield + ']:checked').attr('value');
        }
      });

      // construction de la liste des champs
      let fields = {};
      Object.keys(reader).forEach( controlfield => {
        fields = Object.assign(fields, data[ 'changefields' ][ '$' + controlfield ][ reader[controlfield] ] );
      });

      // affichage des champs
      $('.mgw-specialaccountrequest-field-hide').each( function () {
        let name = $(this).attr('fieldname');
        if ( fields[name] ) {
          $(this).show();
          $( '#mgw-' + jsonForm + '-' + name + '-label' ).html( fields[name]['label'] ) ;
          if (fields[name]['required'] == "true" ){
            $( '#mgw-' + jsonForm + '-' + name + '-field' ).attr("required", true);
          } else {
            $( '#mgw-' + jsonForm + '-' + name + '-field' ).attr("required", false);
          }
          if (fields[name]['value']) {
            $( '#mgw-' + jsonForm + '-' + name + '-field').val(fields[name]['value'])
          }
        }
        else {
          $( '#mgw-' + jsonForm + '-' + name + '-field' ).attr("required", false);
          $(this).hide()
        }
      });

      // affichage des boutons radio
      $('.mgw-radio-hide').each( function () {
        let fieldkey = $(this).attr('fieldkey');
        let radiokey = $(this).attr('radiokey');
        if ( fields[fieldkey] !== undefined && fields[fieldkey]['showvalues'].indexOf(radiokey) >= 0 ) {
          $(this).show();
        }
        else {
          $(this).hide();
        }
      });

      // affichage des templates
      $('.mgw-specialaccountrequest-template').each( function () {
        if ( $(this).attr('showonfield') ){
          let showonfield = $(this).attr('showonfield');
          let showonvalue = $(this).attr('showonvalue');
          if ( reader[showonfield] !== undefined && reader[showonfield] == showonvalue ) {
            $(this).show();
          }
          else {
            $(this).hide();
          }
        }
      });
    }); // fin de api.get().done()

  }
}( mediaWiki, jQuery ) );
