( function ( mw, $ ) {
/*
TODO:
- réactualisation des champs selon le résultat des précédents
- champs inchangeables selon le contexte
- recherche spéciale "Nom / Prénom"
*/
  mw.mgwOouiSearch_makeList = function(e) {
    mw.mgwOouiSearch_closeAllLists();

    // réactualisation de la table si nécessaire
    //cur_str = $('input[name='+e.data.name+'_chooser]').val();
    //if ( cur_str.length <= window[e.data.name+'_over'] ) {
      mw.mgwOouiSearch_query(e.data.name);
    //}

    // préparation des variables
    window[e.data.name + '_cur'] = -1;
    inp = $(e.target);
    val = inp.val();
    // create a DIV element that will contain the items (values):
    a = $('<div></div>');
    a.addClass('mgw-ooui-search-selector-items');
    a.attr('id',  e.data.name + '-ooui-search-selector-items' );
    inp.parent().append(a);
    for (x in window[e.data.name]) {
      // check if the item starts with the same letters as the text field value:
      start = window[e.data.name][x].label.substr(0, val.length);
      end = window[e.data.name][x].label.substr(val.length);
      // check for item in current input list
      check = false;
      for ( y in window[e.data.name+'_input'] ) {
        if ( window[e.data.name+'_input'][y].data == window[e.data.name][x].data ) {
          check = true;
          break;
        }
      }
      //create a DIV element for each matching element:
      if (start.toUpperCase() == val.toUpperCase() && window[e.data.name][x].data && !check ) {
        b = $('<div></div>');
        b.html("<strong>" + start + "</strong>" + end );
        b.on("click", {
            name: e.data.name,
            label: window[e.data.name][x].label,
            data: window[e.data.name][x].data,
            add: true
          }, mw.mgwOouiSearch_dataUpdate
        );
        a.append(b);
      }
    }
  }

  mw.mgwOouiSearch_listKeyDown = function(e) {
    // fonctions
    function addActive(x) {
      /*a function to classify an item as "active":*/
      if (!x) return false;
      /*start by removing the "active" class on all items:*/
      removeActive(x);
      if (window[e.data.name + '_cur'] >= x.length) window[e.data.name + '_cur'] = 0;
      if (window[e.data.name + '_cur'] < 0) window[e.data.name + '_cur'] = (x.length - 1);
      /*add class "autocomplete-active":*/
      $(x[window[e.data.name + '_cur']]).addClass("mgw-ooui-search-selector-active");
    }
    function removeActive(x) {
      /*a function to remove the "active" class from all autocomplete items:*/
      for (var i = 0; i < x.length; i++) {
        $(x[i]).removeClass("mgw-ooui-search-selector-active");
      }
    }
    // controller
    var x = $('#' + e.data.name + '-ooui-search-selector-items' );
    if ( x.length == 0 ) {
      mw.mgwOouiSearch_makeList(e);
    }
    x = $('#' + e.data.name + '-ooui-search-selector-items' + ' div');
    if (e.keyCode == 40) {
      // If the arrow DOWN key is pressed, increase the currentFocus variable:
      window[e.data.name + '_cur']++;
      // and and make the current item more visible:
      addActive(x);
    } else if (e.keyCode == 38) { //up
      // If the arrow UP key is pressed, decrease the currentFocus variable:
      window[e.data.name + '_cur']--;
      // and and make the current item more visible:
      addActive(x);
    } else if (e.keyCode == 13) {
      // If the ENTER key is pressed, prevent the form from being submitted,
      e.preventDefault();
      if (window[e.data.name + '_cur'] > -1) {
        // and simulate a click on the "active" item:
        if (x) $(x[window[e.data.name + '_cur']]).click();
      } else if ( $('#'+e.data.name+'-ooui-search-selector-items div').length == 1 ) {
        // validate the single item left
        $('#'+e.data.name+'-ooui-search-selector-items div')[0].click();
      }
    }
  }

  mw.mgwOouiSearch_closeAllLists = function( id ) {
    if (id === undefined) {
      $(".mgw-ooui-search-selector-items").remove();
    }
    else {
      var screen = id ;
      $(".mgw-ooui-search-selector-items").each(function(){
        if ( $(this).attr('id') != screen ) {
          $(this).remove();
        }
      });
    }
  }

  mw.mgwOouiSearch_documentCloseLists = function(e) {
    var $target = $(e.target);
    id = '';
    e.data.names.forEach(function(item){
      if( $target.closest('input[name=' + item + '_chooser]').length ) {
        id = item + '-ooui-search-selector-items';
      }
    });
    mw.mgwOouiSearch_closeAllLists( id );
  }

  mw.mgwOouiSearch_dataUpdate = function(e) {
    // MAJ DES TABLES
    pair = { data: e.data.data, label: e.data.label };
    // ajout
    if (e.data.add){
      // champs à valeur unique: on vide la table
      if ( !window[e.data.name+'_multiple'] ) {
        window[e.data.name + '_input'] = [];
      }
      // on complète la table avec la nouvelle paire
      window[e.data.name + '_input'].push(pair);
    }
    // retrait
    else {
      let temp = [];
      for ( i in window[e.data.name + '_input'] ) {
        if ( window[e.data.name + '_input'][i].data != pair.data ) {
          temp.push(window[e.data.name + '_input'][i]);
          /*
          delete window[e.data.name + '_input'][i];
          break;
          */
        }
      }
      window[e.data.name + '_input'] = temp;
    }
    // MAJ DU DOM
    mw.mgwOouiSearch_domUpdate( e.data.name );
  }

  mw.mgwOouiSearch_domUpdate = function(name) {
    // on remet les données à 0
    let a = '';
    $('input[name='+name+'_chooser]').val('');
    $('#mgw-'+name+'-show div').remove();
    $('#mgw-search-empty-text-'+name).remove();
    j = 0;
    for ( i in window[name + '_input'] ) {
      // reconstruction du champs data
      if (j > 0) a += ',';
      a += window[name + '_input'][i].data;
      // reconstruction de l'affichage
      b = $('<div id="mgw-ooui-search-show-item-' + window[name + '_input'][i].data +
        '" class="mgw-ooui-search-show-item" data="' + window[name + '_input'][i].data + '">' +
        window[name + '_input'][i].label + '</div>');
      // bouton de suppression: champs multiple ou non obligatoire:
      if ( window[name+'_multiple'] || !window[name+'_required'] ) {
        c = $('<span>✖</span>');
        c.on("click", {
            name: name,
            label: window[name + '_input'][i].label,
            data: window[name + '_input'][i].data,
            add: false
          }, mw.mgwOouiSearch_dataUpdate
        );
        b.append(c);
      }
      $('#mgw-'+name+'-show').append(b);
      j++;
    }
    if ( a == '' && typeof window[name + '_empty_text'] !== 'undefined' ) {
      b = $('<div id="mgw-search-empty-text-'+name+'" class="mgw-search-empty-text"><i>'+window[name + '_empty_text']+'</i></div>');
      $('#mgw-'+name+'-show').append(b);
    }
    $('input[name='+name+']').val(a);
    $('input[name='+name+']').trigger("change");
  }

  mw.mgwOouiSearch_query = function(name){
    if ( $('input[name='+name+'_chooser]').length > 0 ) {
      string = $('input[name='+name+'_chooser]').val();
    } else {
      string = '';
    }

    var query = {
      action: 'mgw-query',
      query: name,
      limit: 1000
    }
    // on ajoute les données contextuelles
    window.mgw_context.forEach( function(item, index){
      query[item] = $('input[name='+item+']').val();
    });
    if ( string != '' ) {
      query.string = window[name+'_string'];
    }
    // récupération des données
    api = new mw.Api()
    api.post( query ).done( function ( ret ) {
      window[name] = [];
      for (x in ret) {
        window[name][x] = {
          data: ret[x].data,
          label: ret[x].label
        }
        if ( x > 498 ) {
          window[name+'_over'] = string.length;
        }
      }
    } );
    return true;
  }

  mw.mgwOouiSearch_setDefault = function(name, def) {
    for (i in def.data) {
      pair = { data: def.data[i], label: def.label[i] };
      window[name + '_input'].push( pair );
    }
    mw.mgwOouiSearch_domUpdate(name);
  }

  mw.mgwOouiSearch_makeInput = function( name, def ){
    // préparation des variables
    window[name] = [];
    window[name + '_input'] = [];
    window[name + '_multiple'] = def.multiple;
    window[name + '_required'] = def.required;
    if ( typeof def.empty_text !== 'undefined' ) {
      window[name + '_empty_text'] = def.empty_text;
    }
    if ( typeof def.placeholder !== 'undefined' ) {
      window[name + '_placeholder'] = def.placeholder;
    }
    window[name + '_over'] = -1;
    // requête de la liste
    mw.mgwOouiSearch_query(name);
    // construction des containers
    inp = $('input[name='+name+']');
    a = $("<div id='mgw-"+name+"' class='mgw-ooui-search-selector'></div>");
    b = $("<div id='mgw-"+name+"-show' class='mgw-ooui-search-selector-show'></div>");
    c = $("<div id='mgw-"+name+"-choose' class='mgw-ooui-search-selector-choose'></div>");
    widget = {
      name: name+'_chooser',
      value: '',
      icon: 'search'
    }
    if ( typeof window[name + '_placeholder'] !== 'undedined' ) {
      widget.placeholder = window[name + '_placeholder'];
    }
    else if ( def.multiple ) {
      widget.placeholder = '(choix multiple)';
    }
    d = new OO.ui.TextInputWidget( widget );
    c.append(d.$element);
    a.append(b);
    a.append(c);
    inp.after(a);
    if ( def.required ) {
      $('input[name='+name+'_chooser]').parent().append(
        '<div class="mgw-ooui-search-required">' +
        '<span class="oo-ui-indicatorElement-indicator oo-ui-indicator-required"></span>' +
        '</div>'
      );
    }
    // intégration des données existantes
    mw.mgwOouiSearch_setDefault(name, def);
    // instanciation
    chooser = $('input[name='+name+'_chooser]');
    chooser.on( 'focus', {name:name}, mw.mgwOouiSearch_makeList );
    chooser.on( "input", {name:name}, mw.mgwOouiSearch_makeList );
    chooser.on( "keydown", {name:name}, mw.mgwOouiSearch_listKeyDown );
  }

  $( function () {
    // on récupère les données cachées dans le DOM
    dataTransfer = JSON.parse($('#mgw-ooui-data-transfer').html());
    if ( $('#mgw-ooui-data-context').length > 0 ){
      window.mgw_context = JSON.parse($('#mgw-ooui-data-context').html());
    }
    else window.mgw_context = [];
    // pour chaque clé on construit le champs
    searchKeys = Object.keys(dataTransfer);
    for (let key of searchKeys) {
      mw.mgwOouiSearch_makeInput(
        key,
        dataTransfer[key]
      );
    }
    $(document).click( { names: searchKeys }, mw.mgwOouiSearch_documentCloseLists );
  });

}( mediaWiki, jQuery ) );
