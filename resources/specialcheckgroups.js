( function ( mw, $ ) {

  mw.mgwFiltresShow = function() {
    $('.mgw-view').attr('checked', true );
  }

  mw.mgwFiltresHide = function() {
    $('.mgw-hide').attr('checked', true );
  }

  mw.mgwAddAll = function() {
    let inputs = document.getElementById('mgw-users-addall');
    if ( inputs.checked ) {
      $('.mgw-group-add').each( function(){
        let id = $(this).val();
        if ( ! $('.mgw-group-delete[value='+id+']').is(':checked') ) {
          $(this).attr('checked', true );
        }
      });
    }
    if ( ! inputs.checked ) {
      $('.mgw-group-add').attr('checked', false );
    }
  }

  mw.mgwDeleteAll = function() {
    let inputs = document.getElementById('mgw-groups-deleteall');
    if ( inputs.checked ) {
      $('.mgw-group-delete').each( function(){
        let id = $(this).val();
        if ( ! $('.mgw-group-add[value='+id+']').is(':checked') ) {
          $(this).attr('checked', true );
        }
      });
    }
    if ( ! inputs.checked ) {
      $('.mgw-group-delete').attr('checked', false );
    }
  }

  mw.mgwSelectGroups = function(){
    mw.mgwChecksConcat();
    $('#mgw-action-select').click();
  }

  mw.mgwPopulate = function(){
    mw.mgwChecksConcat();
    $('#mgw-action-populate').click();
  }

  mw.mgwValidGroups = function(){
    $('#mgw-action-validgroups').click();
  }

// cumuler toutes les id cochée en 1 valeur "multiple" puis submit
  mw.mgwChecksConcat = function(){
      var arr = [];
      $('.mgw-group-add:checked').each( function(){ arr.push( $(this).val() ); });
      $('#mgw-select-addgroups').val( arr.join(',') );
      arr=[];
      $('.mgw-group-delete:checked').each( function(){ arr.push( $(this).val() ); });
      $('#mgw-select-deletegroups').val( arr.join(',') );
  }

  $( function () {

    $('.mgw-group-add').click( function(){
      let id = $(this).val();
      if( $(this).is(':checked') ){
        if ( $('.mgw-group-delete[value='+id+']').is(':checked') ) {
          $('.mgw-group-delete[value='+id+']').removeAttr('checked');
        }
      }
    });

    $('.mgw-group-delete').click( function(){
      let id = $(this).val();
      if ( $(this).is(':checked') ){
        if ( $('.mgw-group-add[value='+id+']').is(':checked') ){
          $('.mgw-group-add[value='+id+']').removeAttr('checked');
        }
      }
    });
  });

}( mediaWiki, jQuery ) );
