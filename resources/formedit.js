( function ( mw, $ ) {
  
  /* CONTROLE DES FORMULAIRES DE CREATION DE GROUPES & D'UTILISATEURS */

  // définition des variables
  // TODO: appel à fichier de config...
  modifGrp = new RegExp('AjouterDonnées\/Modifier le groupe');
  newGrp = new RegExp('AjouterDonnées\/(GEP|GAPP|Stage praticien)');
  newDPC = new RegExp('AjouterDonnées\/DPC');
  newUtilisateur = new RegExp('AjouterDonnées\/Nouveaux utilisateurs');
  var addToExisting = true;

  if ( modifGrp.test( mw.config.get('wgTitle') ) ) {
    var input_trigger_name = 'Rajouter un(e) participant(e)';
    var nom_n = 7, prenom_n = 8, email_n = 9;
    var autogen_id = '#s2id_autogen1';
    var trigger_tag_input = true;
    var email_duplicates = {}, name_duplicates = {};
  }
  else if ( newGrp.test( mw.config.get('wgTitle') ) ) {
    var input_trigger_name = 'Rajouter un(e) participant(e)';
    var nom_n = 8, prenom_n = 9, email_n = 10;
    var autogen_id = '#s2id_autogen2';
    var trigger_tag_input = true;
    var email_duplicates = {}, name_duplicates = {};
  }
  else if ( newDPC.test( mw.config.get('wgTitle') ) ) {
    var input_trigger_name = 'Rajouter un(e) participant(e)';
    var nom_n = 9, prenom_n = 10, email_n = 11;
    var autogen_id = '#s2id_autogen2';
    var trigger_tag_input = true;
    var email_duplicates = {}, name_duplicates = {};
  }
  else if ( newUtilisateur.test( mw.config.get('wgTitle') ) ) {
    var input_trigger_name = 'Rajouter un compte';
    var nom_n = 3, prenom_n = 4, email_n = 5;
    var autogen_id = '';
    var trigger_tag_input = true;
    var email_duplicates = {}, name_duplicates = {};
    addToExisting = false;
  }
  else {
    var trigger_tag_input = false;
  }

  var waitForEl = function(selector, callback) {
    if ( $(selector).length ) {
      callback();
    } else {
      setTimeout(function() {
        waitForEl(selector, callback);
      }, 100);
    }
  };

  mw.mgw_nouveau_compte = 0;

  mw.mgw_tag_input_nouveau = function() {
    $inputs = $('input');
    $inputs.each( function(){
      if ( $(this).val() == input_trigger_name ) {
        $(this).click( function(){
          mw.mgw_nouveau_compte++;
          let id_nom = 'input_' + mw.mgw_nouveau_compte + '_' + nom_n;
          let id_prenom = 'input_' + mw.mgw_nouveau_compte + '_' + prenom_n;
          let id_email = 'input_' + mw.mgw_nouveau_compte + '_' + email_n;
          // délai nécessaire à l'inclusion du nouveau formulaire dans le DOM
          waitForEl('#'+id_nom, function() {
            $( 'input#'+id_nom ).on( 'input', {
                id: id_nom,
                field:'nom',
                num: mw.mgw_nouveau_compte
              }, mw.mgw_input_change );
            $( 'input#'+id_prenom ).on( 'input', {
                id: id_prenom,
                field:'prenom',
                num: mw.mgw_nouveau_compte
              }, mw.mgw_input_change );
            $( 'input#'+id_email ).on( 'input', {
                id: id_email,
                field:'email',
                num: mw.mgw_nouveau_compte
              }, mw.mgw_input_change );
            $parent = $('input#'+id_nom).parents('div.multipleTemplateInstance');
            $parent.attr('id', 'mgw-multiple-user-'+mw.mgw_nouveau_compte);
            $parent.prepend('<div id="mgw-user-message-'+mw.mgw_nouveau_compte+'" class="mgw-user-message"></div>');
            $parent.find('a.removeButton').on('click', {num: mw.mgw_nouveau_compte}, mw.mgw_delete_new );
            $parent.find('.addAboveButton').remove();
          });
        });
      }
    });
  }

  mw.mgw_delete_new = function(e) {
    if ( email_duplicates[e.data.num] !== 'undefined' ) {
      delete email_duplicates[e.data.num];
    }
    if ( name_duplicates[e.data.num] !== 'undefined' ) {
      delete name_duplicates[e.data.num];
    }
  }

  mw.mgw_input_change = function (e){

    if ( e.data.field == 'nom' ) {
      $('input#'+e.data.id).val( $('input#'+e.data.id).val().toUpperCase() );
    }

    if ( e.data.field == 'prenom' ) {
      $('input#'+e.data.id).val( mw.mgw_sanitize_prenom( $('input#'+e.data.id).val() ) );
    }

    var new_name = $('input#input_'+e.data.num+'_'+prenom_n).val() + ' ' + $('input#input_'+e.data.num+'_'+nom_n).val();
    var new_email = $('input#input_'+e.data.num+'_'+email_n).val()
    var num = e.data.num;

    query = {
      action: 'mgw-query',
      query: 'check_user_creation',
      user_name: new_name,
      user_email: new_email
    }
    api = new mw.Api()
    api.post( query ).done( function ( ret ) {
      // feedback dans le formulaire
      if ( ret.user_exists == 'true' && ret.email_exists == 'false' ) {
        $('#mgw-user-message-'+num).show();
        $('#mgw-user-message-'+num).html('<a href="/wiki/index.php/Utilisateur:'+new_name+'" target="_blank">'
          +'<strong>'+new_name+'</strong></a> existe déjà avec le courriel <u>' + ret.user_email + '</u>' );
        $ul = $('<ul style="list-style: none;"></ul>');
        if ( addToExisting ) {
          $add_user = $('<span class="mgw-add-existing-user">ajouter</span>');
          $add_user.on('click', {
            user_name: new_name,
            num: num
          }, mw.mgw_add_existing_user );
          $add_user_text = $('<span> '+new_name+' aux membres déjà inscrits</span>');
          $li = $('<li></li>');
          $li.append($add_user);
          $li.append($add_user_text);
          $ul.append($li);
        }
        $ul.append('<li>s\'il ne s\'agit pas de la même personne, vous pouvez ajouter un deuxième nom ou prénom pour différencier les utilisateurs</li>');
        $('#mgw-user-message-'+num).append($ul);
      }
      else if ( ret.user_exists == 'true' && ret.email_exists == 'true' ) {
        $('#mgw-user-message-'+num).show();
        $('#mgw-user-message-'+num).html('<a href="/wiki/index.php/Utilisateur:'+new_name+'" target="_blank">'
          + '<strong>'+new_name+'</strong></a> existe déjà avec le même courriel.' );
        $ul = $('<ul style="list-style: none;"></ul>');
        if ( addToExisting ) {
          $add_user = $('<span class="mgw-add-existing-user">ajouter</span>');
          $add_user.on('click', {
            user_name: new_name,
            num: num
          }, mw.mgw_add_existing_user );
          $add_user_text = $('<span> '+new_name+'aux membres déjà inscrits</span>');
          $li = $('<li></li>');
          $li.append($add_user);
          $li.append($add_user_text);
          $ul.append($li);
        }
        $('#mgw-user-message-'+num).append($ul);
      }
      else if ( ret.user_exists == 'false' && ret.email_exists == 'true' ) {
        $('#mgw-user-message-'+num).show();
        $('#mgw-user-message-'+num).html('Le compte <a href="/wiki/index.php/Utilisateur:'+ret.user_name+'" '
        + ' target="_blank">' + ret.user_name + '</a> utilise le même courriel');
        $ul = $('<ul style="list-style: none;"></ul>');
        if ( addToExisting ) {
          $add_user = $('<span class="mgw-add-existing-user">ajouter</span>');
          $add_user.on('click', {
            user_name: ret.user_name,
            num: num
          }, mw.mgw_add_existing_user );
          $add_user_text = $('<span> '+ret.user_name+' aux membres déjà inscrits</span>');
          $li = $('<li></li>');
          $li.append($add_user);
          $li.append($add_user_text);
          $ul.append($li);
        }
        $('#mgw-user-message-'+num).append($ul);
      }
      else {
        $('#mgw-user-message-'+num).hide();
        $('#mgw-user-message-'+num).html('');
      }
      // submit callback
      if ( ret.email_exists == 'true' ) {
        email_duplicates[num] = {name: new_name};
      }
      else if ( email_duplicates[num] !== 'undefined' ) {
        delete email_duplicates[num];
      }

      if ( ret.user_exists == 'true' ) {
        name_duplicates[num] = {name: new_name};
      }
      else if ( name_duplicates[num] !== 'undefined' ) {
        delete name_duplicates[num];
      }
    });
  }

  mw.mgw_add_existing_user = function(e) {
    var enter = jQuery.Event("keydown");
    enter.which = 13;
    enter.keyCode = 13;
    $(autogen_id).focus();
    $(autogen_id).val( e.data.user_name );
    $(autogen_id).click();
    waitForEl('li.select2-result', function() {
      $(autogen_id).trigger(enter);
    });
    $('#mgw-multiple-user-'+e.data.num+' .removeButton').click();
    if ( email_duplicates[e.data.num] !== 'undefined' ) {
      delete email_duplicates[e.data.num];
    }
    if ( name_duplicates[e.data.num] !== 'undefined' ) {
      delete name_duplicates[e.data.num];
    }
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

  mw.mgw_submit_control = function(e){
    if ( Object.keys(email_duplicates).length ) {
      e.preventDefault();
      list = [];
      Object.keys(email_duplicates).forEach((item) => {
        list.push(email_duplicates[item].name);
      });
      users_list = list.join(', ');
      str1 = ( Object.keys(email_duplicates).length > 1 ) ? 'de nouveaux comptes' : 'un nouveau compte';
      str2 = ( Object.keys(email_duplicates).length > 1 ) ? 'les modifier ou les ' : 'le modifier ou l\'';
      alert ('Erreur : ' + users_list + '\n'
        + 'Vous ne pouvez pas créer ' + str1 + ' avec un courriel déjà utilisé.\n'
        + 'Veuillez '+str2+'ajouter aux membres déjà inscrits.');
    }
    else if ( Object.keys(name_duplicates).length ) {
      list = [];
      Object.keys(name_duplicates).forEach((item) => {
        list.push(name_duplicates[item].name);
      });
      users_list = list.join(', ');
      mess = 'Vous êtes sur le point de créer de nouveau(x) compte(s) avec des noms déjà existants:\n'
        + users_list + '\n\nSi vous confirmez, un nombre sera automatiquement ajouté à la suite du nom pour différencier les utilisateurs.'
      if ( !confirm( mess ) ) {
        e.preventDefault();
      }
    }
  }

  // initialisation des triggers
  if ( trigger_tag_input ) {
    mw.mgw_tag_input_nouveau();
    $('input#wpSave').click( mw.mgw_submit_control );
  }

}( mediaWiki, jQuery ) );
