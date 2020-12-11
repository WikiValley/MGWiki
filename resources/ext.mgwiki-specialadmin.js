( function ( mw, $ ) {

  mw.mgwFiltresShow = function() {
    $('.mgw-view').attr('checked', true );
  }

  mw.mgwFiltresHide = function() {
    $('.mgw-hide').attr('checked', true );
  }

  mw.mgwAddAll = function() {
    let inputs = document.getElementById('mgw-check-add-all');
    if ( inputs.checked ) {
      $('.mgw-check-add').each( function(){
        let id = $(this).val();
        if ( ! $('.mgw-check-del[value='+id+']').is(':checked') ) {
          $(this).attr('checked', true );
        }
      });
    }
    if ( ! inputs.checked ) {
      $('.mgw-check-add').attr('checked', false );
    }
  }

  mw.mgwDelAll = function() {
    let inputs = document.getElementById('mgw-check-del-all');
    if ( inputs.checked ) {
      $('.mgw-check-del').each( function(){
        let id = $(this).val();
        if ( ! $('.mgw-check-add[value='+id+']').is(':checked') ) {
          $(this).attr('checked', true );
        }
      });
    }
    if ( ! inputs.checked ) {
      $('.mgw-check-del').attr('checked', false );
    }
  }

  mw.mgwCheckAddConcat = function(){
      var arr = [];
      $('.mgw-check-add:checked').each( function(){ arr.push( $(this).val() ); });
      $('#mgw-hidden-field-add').val( arr.join(',') );
  }

  mw.mgwCheckDelConcat = function(){
      var arr = [];
      $('.mgw-check-del:checked').each( function(){ arr.push( $(this).val() ); });
      $('#mgw-hidden-field-del').val( arr.join(',') );
  }

  $( function () {

    // valider le formulaire en définissant l'action
    $('.mgw-action-button').click( function(){
      let action = $(this).attr('action');
      if ( $(this).attr('checkadd') == 'true' ) {
        mw.mgwCheckAddConcat();
      }
      if ( $(this).attr('checkdel') == 'true' ) {
        mw.mgwCheckDelConcat();
      }
      let hidden = JSON.parse( $(this).attr('hidden') );
      Object.entries( hidden ).forEach(entry => {
        const [ field, value ] = entry;
        $('input[name='+field+']').val( value );
      });
      if ( $(this).attr('control') != '' ) {
        $(this).attr('control');
      }
      $('input[name=action]').val( action )
    });

    // si check-add décocher check-del
    $('.mgw-check-add').click( function(){
      let id = $(this).val();
      if( $(this).is(':checked') ){
        if ( $('.mgw-check-del[value='+id+']').is(':checked') ) {
          $('.mgw-check-del[value='+id+']').removeAttr('checked');
        }
      }
    });

    $('.mgw-check-del').click( function(){
      let id = $(this).val();
      if ( $(this).is(':checked') ){
        if ( $('.mgw-check-add[value='+id+']').is(':checked') ){
          $('.mgw-check-add[value='+id+']').removeAttr('checked');
        }
      }
    });
  });

}( mediaWiki, jQuery ) );
