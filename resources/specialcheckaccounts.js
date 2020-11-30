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
      $('.mgw-user-add').each( function(){
        let id = $(this).val();
        if ( ! $('.mgw-user-delete[value='+id+']').is(':checked') ) {
          $(this).attr('checked', true );
        }
      });
    }
    if ( ! inputs.checked ) {
      $('.mgw-user-add').attr('checked', false );
    }
  }

  mw.mgwDeleteAll = function() {
    let inputs = document.getElementById('mgw-users-deleteall');
    if ( inputs.checked ) {
      $('.mgw-user-delete').each( function(){
        let id = $(this).val();
        if ( ! $('.mgw-user-add[value='+id+']').is(':checked') ) {
          $(this).attr('checked', true );
        }
      });
    }
    if ( ! inputs.checked ) {
      $('.mgw-user-delete').attr('checked', false );
    }
  }

  mw.mgwSelectUsers = function(){
    mw.mgwChecksConcat();
    $('#mgw-action-select').click();
  }

  mw.mgwSanitize = function(){
    mw.mgwChecksConcat();
    $('#mgw-action-sanitize').click();
  }

  mw.mgwRename = function(){
    mw.mgwChecksConcat();
    $('#mgw-action-rename').click();
  }

  mw.mgwUserMerge = function( opt ){
    mw.mgwChecksConcat();
    let merged = '';
    let m = 0;
    let targeted = '';
    let t = 0;
    $('.mgw-user-delete:checked').each( function(){
      m += 1;
      let id = $(this).val();
      merged += $('.mgw-username[userid='+id+']').text() + ', ';
    });
    if ( m < 1 ) {
      alert( 'Vous devez sélectionner au moins un utilisateur à '+opt+' (cases "out").');
      return;
    }
    $('.mgw-user-add:checked').each( function(){
      t += 1;
      let id = $(this).val();
      targeted += $('.mgw-username[userid='+id+']').text() + ' ';
    });
    if ( t != 1 ) {
      alert( 'Vous devez sélectionner un (et un seul) utilisateur à '+opt+' (cases "in").');
      return;
    }
    let message = 'ATTENTION vous êtes sur le point de '+ opt +' :\n\n' + merged
      + '\n\n vers : \n\n' + targeted + '\n\nCette opération est irréversible.';
    if ( confirm( message ) ) {
      if ( opt == 'supprimer') {
        $('#mgw-action-merge').val('delete');
      }
      $('#mgw-action-merge').click();
    }
  }

  mw.mgwMerge = function(){
    mw.mgwUserMerge('fusionner')
  }

  mw.mgwDelete = function(){
    mw.mgwUserMerge('supprimer')
  }

  mw.mgwPopulate = function(){
    mw.mgwChecksConcat();
    $('#mgw-action-populate').click();
  }

  mw.mgwValidUsers = function(){
    $('#mgw-action-validusers').click();
  }

// cumuler toutes les id cochée en 1 valeur "multiple" puis submit
  mw.mgwChecksConcat = function(){
      var arr = [];
      $('.mgw-user-add:checked').each( function(){ arr.push( $(this).val() ); });
      $('#mgw-select-addusers').val( arr.join(',') );
      arr=[];
      $('.mgw-user-delete:checked').each( function(){ arr.push( $(this).val() ); });
      $('#mgw-select-deleteusers').val( arr.join(',') );
  }

  $( function () {

    $('.mgw-user-add').click( function(){
      let id = $(this).val();
      if( $(this).is(':checked') ){
        if ( $('.mgw-user-delete[value='+id+']').is(':checked') ) {
          $('.mgw-user-delete[value='+id+']').removeAttr('checked');
        }
      }
    });

    $('.mgw-user-delete').click( function(){
      let id = $(this).val();
      if ( $(this).is(':checked') ){
        if ( $('.mgw-user-add[value='+id+']').is(':checked') ){
          $('.mgw-user-add[value='+id+']').removeAttr('checked');
        }
      }
    });
  });

}( mediaWiki, jQuery ) );
