( function ( mw, $ ) {

  mw.mgwShowUsers = function() {
    $('#mgw-select-action').val('show');
    $('#mgw-select-action').click();
  }

  mw.mgwEmailduplicates = function() {
    $('#mgw-select-action').val('emailduplicates');
    $('#mgw-select-action').click();
  }

  mw.mgwSelectUsers = function(){
    mw.mgwChecksConcat();
    $('#mgw-select-action').val('select');
    $('#mgw-select-action').click();
  }

  mw.mgwSanitize = function(){
    mw.mgwChecksConcat();
    $('#mgw-select-action').val('sanitize');
    $('#mgw-select-action').click();
  }

  mw.mgwEmpty = function(){
    mw.mgwChecksConcat();
    let del = '';
    let m = 0;
    let add = '';
    let t = 0;
    $('.mgw-user-delete:checked').each( function(){
      m += 1;
      let id = $(this).val();
      del += $('.mgw-username[userid='+id+']').text() + ', ';
    });
    if ( m < 1 ) {
      alert( 'Vous devez sélectionner au moins un utilisateur à vider (cases "delete").');
      return;
    }
    $('.mgw-user-add:checked').each( function(){
      t += 1;
      let id = $(this).val();
      add += $('.mgw-username[userid='+id+']').text() + ' ';
    });
    if ( t > 0 ) {
      alert( 'Vous ne devez pas sélectionner d\'utilisateur dans les cases "add".');
      return;
    }
    let message = 'ATTENTION vous êtes sur le point de supprimer les variable \
      user_email, user_groups et user_real_name aux utilisateurs suivants :\n\n' + del;
    if ( confirm( message ) ) {
      $('#mgw-select-action').val('empty');
      $('#mgw-select-action').click();
    }
  }

  mw.mgwHarmonize = function(){
    mw.mgwChecksConcat();
    $('#mgw-select-action').val('harmonize');
    $('#mgw-select-action').click();
  }

  mw.mgwHarmonizeReplace = function(){
    if ( $('#mgw-action-harmonize-replace:checked') ) {
      $('#mgw-select-harmonize-replace').val('oui');
    } else {
      $('#mgw-select-harmonize-replace').val('non');
    }
  }

  mw.mgwDeleteReplace = function(){
    if ( $('#mgw-action-delete-replace:checked') ) {
      $('#mgw-select-delete-replace').val('oui');
    } else {
      $('#mgw-select-delete-replace').val('non');
    }
  }

  mw.mgwDelete = function( ){
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
      alert( 'Vous devez sélectionner au moins un utilisateur à supprimer (cases "out").');
      return;
    }
    $('.mgw-user-add:checked').each( function(){
      t += 1;
      let id = $(this).val();
      targeted += $('.mgw-username[userid='+id+']').text() + ' ';
    });
    if ( t != 1 ) {
      alert( 'Vous devez sélectionner un (et un seul) utilisateur à supprimer (cases "in").');
      return;
    }
    let message = 'ATTENTION vous êtes sur le point de supprimer :\n\n' + merged
      + '\n\n vers : \n\n' + targeted + '\n\nCette opération est irréversible.';
    if ( confirm( message ) ) {
      $('#mgw-select-action').val('delete');
      $('#mgw-select-action').click();
    }
  }

  mw.mgwDBupdate = function(){
    mw.mgwChecksConcat();
    $('#mgw-select-action').val('db_update');
    $('#mgw-select-action').click();
  }

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
