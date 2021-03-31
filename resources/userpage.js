( function ( mw, $ ) {

	if ( $('#mgw-hide-edit').attr('value') == "true" ) {
		$('#ca-edit').hide();
	}
	if ( $('#mgw-hide-formedit').attr('value') == "true" ) {
	 	$('#ca-formedit').hide();
	}

	/* GROUPES TUTORES: affichage détaillé */
	mw.mgw_display_groupes = function() {
		var check = false;
		 $('span').filter( function() {
			 return this.id.match(/Responsable_de/);
		 }).parent().next('ul').find('li').each( function(){

			 groupe_name = $(this).find('span span a').html().replace( /\s/g, '_' );

			 $(this).addClass('mgw-groupe');
			 $(this).attr('groupe_name',groupe_name);

			 $title = $(this).children('.smw-field').eq(0);
			 $title.addClass('mgw-groupe-title');

			 $membres_inline = $(this).children('.smw-field').eq(1);
			 $membres_inline.addClass('mgw-groupe-membres-inline');
			 $membres_inline.attr('groupe_name',groupe_name);
			 $membres_inline.hide();

			 $toggle = $('<span class="mgw-group-toggle" groupe_name="'+groupe_name+'" title="réduire">▼</span>');

			 $btn_modifier = $('<a class="mgw-groupe-modifier-btn" groupe_name="'+groupe_name+'" '
			 	 +'href="/wiki/index.php/Spécial:AjouterDonnées/Modifier_le_groupe/Groupe:'+groupe_name
				 +'" title="modifier le groupe '+groupe_name+'">modifier</a>');
			 $btn_archive = $('<span class="mgw-groupe-archive-btn" groupe_name="'+ groupe_name
			 	 +'" title="archiver le groupe '+groupe_name+'">archiver</span>');
			 $btn_archive.on("click", {
					 archive: 'do',
					 groupe: groupe_name
				 }, mw.mgw_archive_groupe
			 );

			 $membres_inline.before( $toggle );
			 $membres_inline.before( $btn_modifier );
			 $membres_inline.before( $btn_archive );
			 var $membres_list = $('<ul class="mgw-groupe-membres-ul" groupe_name="'+groupe_name+'"></ul>');

			 $membres_inline.children('.smw-value').each( function ( index, membre ){
			 	$inner = $(membre);
				html_new = '<li class="mgw-groupe-membre" groupe_membre="'
					+ $inner.children('a').html().replace(/\s/g,'_') + '"></li>';
				$membre_li = $(html_new);
				$membre_li.html( $inner.first('span').html() );
	 			$membres_list.append($membre_li);
			 });

			 $(this).append( $membres_list );
			 check = true;
		 });
		 return check;
	 }

	 /* GROUPES TUTORES: définition des actions et de l'affichage pour chaque membre */
	 mw.mgw_check_membre = function() {
		 $('.mgw-groupe-membre').each( function(){
			 var membre = $(this).attr('groupe_membre');
			 query = {
	       action: 'mgw-query',
	       query: 'membre_status',
				 user_name: membre
	     }
			 api = new mw.Api()
	     api.post( query ).done( function ( ret ) {
				 var $membre_li = $('.mgw-groupe-membre[groupe_membre='+membre+']');
				 var $icon;
				 var $info = $('<span class="mgw-user-info mgw-last-contrib" title="dernière activité"></span');
				 var $btn = $('<span class="mgw-changecredentials-link" '
					+ 'onclick="window.location.href = \'/wiki/index.php/Special:MgwChangeCredentials?user_name=' + membre +
					'&returnto=/wiki/index.php/' + mw.config.get('wgPageName')
					+ '\'" title="administrer le compte '+membre+' ( nom / prénom / e-mail, (ré-)inviter... )">gérer</span>');

				 if ( ret.user_id == 0 ) {
					 // UTILISATEUR INEXISTANT
					 $icon = $('<span class="mgw-user-icon mgw-user-wrong">!</span>');
					 $info = $('<span class="mgw-user-info mgw-user-wrong">utilisateur inexistant</span>');
					 $btn = false;
				 }
				 else if ( ret.user_status == 0 || ret.user_status == 1 ) {
					 if ( ret.user_status == 0 ) {
						 // COMPTE ACTIF, EMAIL CONFIRME
						 $icon = $('<span class="mgw-user-icon mgw-user-complete" title="compte actif">✓</span>');
	 					 $btn = false;
					 }
					 else {
						 // COMPTE ACTIF, EMAIL NON CONFIRME
						 $icon = $('<span class="mgw-user-icon mgw-user-incomplete" title="e-mail non confirmé">?</span>');
					 }
					 	 // on recherche la dernière contrib sur les ns 0 (main) et 724 (récit)
					 query = {
						 action: 'query',
						 list: 'usercontribs',
						 ucuser: membre,
						 uclimit: 1,
						 ucnamespace:'0|724'
					 }
					 api = new mw.Api();
					 api.post( query ).done( function ( req ) {
						 if ( typeof( req.query.usercontribs[0] ) != "undefined" ) {
							 last_date = req.query.usercontribs[0].timestamp.substring(8, 10) + '/';
							 last_date += req.query.usercontribs[0].timestamp.substring(5, 7) + '/';
							 last_date += req.query.usercontribs[0].timestamp.substring(0, 4);

							 $info.html( '( <a href="/wiki/index.php/' + req.query.usercontribs[0].title
							 	+'"><i>'+req.query.usercontribs[0].title + '</i></a> le ' + last_date +' )' );
						 }
						 else {
							 $info.html( '( Aucune contribution )' );
						 }
					 });
				 }
				 else if ( ret.user_status == -1 ) {
					 // COMPTE INACTIF, EMAIL NON CONFIRME
					 $icon = $('<span class="mgw-user-icon mgw-user-wrong" title="compte non confirmé">✖</span>');
					 $info = false;
				 }
				 else if ( ret.user_status == 2 ) {
					 // COMPTE INACTIF, EMAIL CONFIRME
					 $icon = $('<span class="mgw-user-icon mgw-user-incomplete" title="compte confirmé, utilisateur inactif">?</span>');
					 $info.html( '( Aucune contribution )' );
				 }

				 if ( !$membre_li.find('.mgw-user-icon').length ) {
					 $membre_li.append( $icon );
					 if ( $btn ) $membre_li.append( $btn );
					 if ( $info ) $membre_li.append( $info );
				 }
	     } );
		 });
	 }

	 /* fonction pour relancer une invitation mail (comptes non confirmés seulement) */
	 mw.mgw_invite_send = function(e){
		 var user = e.data.user_name.replace( /_/g, ' ');

		 if ( confirm( 'Une invitation a déjà été envoyée à ' + user + ' le ' + e.data.last_invite
	 		+ '.\n\nSouhaitez-vous renouveller cette invitation ? ') )
		 {
			 query = {
				 action: 'mgw-action',
				 query: 'invite',
				 user_name: e.data.user_name
			 }
			 api = new mw.Api();
			 api.post( query ).done( function ( ret ) {
				 user = user.replace(/_/g, ' ');
				 if ( ret.done.status == "done" ) {
					 alert ( 'Une nouvelle invitation a été envoyée à ' + user + '.\\n' +
				 			'Si cela n\'a pas fonctionné il se peut que son adresse courriel soit erronée.');
				 }
				 else {
					 alert ( ret.done.info );
				 }
			 });
		 }
	 }

	 /* bouton pour archiver les groupes (U2)*/
	 mw.mgw_archive_groupe = function(e) {
		 let act = ( e.data.archive == "do" ) ? 'archiver' : 'rétablir';
 		 if ( confirm( 'Etes-vous sûr de vouloir '+act+' le groupe ' + e.data.groupe + ' ? ') )
 		 {
 			 query = {
 				 action: 'mgw-action',
 				 query: 'groupe_archive',
 				 groupe_name: e.data.groupe,
				 archive: e.data.archive
 			 }
 			 api = new mw.Api();
 			 api.post( query ).done( function ( ret ) {
				 if ( ret.done.status != 'done' ){
 					 alert( ret.done.info );
					 return;
				 }
				 alert( ret.done.info );
				 window.location.reload();
				 return;
 			 });
 		 }
	 }

	 /* toggle pour réduire l'affichage (U2)*/
	 mw.mgw_toggle_groupes = function() {
	 $('.mgw-group-toggle').click( function(){
	  let groupe_name = $(this).attr('groupe_name');
	  $inline = $('.mgw-groupe-membres-inline[groupe_name='+groupe_name+']');
	  $inlist = $('.mgw-groupe-membres-ul[groupe_name='+groupe_name+']');
	  $arch_btn = $('.mgw-groupe-archive-btn[groupe_name='+groupe_name+']');
	  $modif_btn = $('.mgw-groupe-modifier-btn[groupe_name='+groupe_name+']');
	  if ( $inline.is(":hidden") ) {
	 	 $inline.show();
	 	 $inlist.hide();
	 	 $arch_btn.hide();
	 	 $modif_btn.hide();
	 	 $(this).attr('title', 'détails');
	 	 $(this).html('►');
	  }
	  else {
	 	 $inline.hide();
	 	 $inlist.show();
	 	 $arch_btn.show();
	 	 $modif_btn.show();
	 	 $(this).attr('title', 'réduire');
	 	 $(this).html('▼');
	  }
	 });
	 }

	 /* bouton 'rétablir' un groupe archivé (U2)*/
	 mw.mgw_parse_archives = function() {
		 var check = false;
		 $('span').filter( function() {
		 	 return this.id.match(/Groupes_archivés/);
		 }).parent().next('ul').find('li').each( function(){
		 	 groupe_name = $(this).find('span span a').html().replace( /\s/g, '_' );
		 	 $(this).addClass('mgw-archived-groupe');
		 	 $(this).attr('groupe_name',groupe_name);

		 	 $btn_archive = $('<span class="mgw-groupe-archive-btn" groupe_name="'+groupe_name+'" title="rétablir ce groupe">rétablir</span>');
		 	 $btn_archive.on("click", {
		 			 archive: 'undo',
		 			 groupe: groupe_name
		 		 }, mw.mgw_archive_groupe
		 	 );

		 	 $(this).append( $btn_archive );
		 });
	 }

	 /* bouton 'créer un groupe' (U2)*/
	 mw.create_groupe_link = function() {
		 $new_groupe_button = $('<span class="mgw-new-groupe-button">Créer un nouveau groupe</span>');
		 $new_groupe_button.on("click", function(){
			 document.location.href='/wiki/index.php/MGWiki:Administration/Créer_un_groupe';
		 });
		 $("#Groupes_de_travail").parent().after($new_groupe_button);
	 }

 	 /* boutons 'créer un récit clinique' et 'créer une page documentaire' */
 	 mw.create_pages_links = function() {
 		 $new_recit_button = $('<span class="mgw-new-recit-button">Créer un récit clinique</span>');
 		 $new_recit_button.on("click", function(){
 			 document.location.href='/wiki/index.php/Spécial:AjouterDonnées/Nouveau_récit_clinique';
 		 });
 		 $new_page_button = $('<span class="mgw-new-page-button">Créer une page documentaire</span>');
 		 $new_page_button.on("click", function(){
 			 document.location.href='/wiki/index.php/Spécial:AjouterDonnées/Nouvelle_page_documentaire';
 		 });
 		 $("#Contributions").parent().after($new_page_button);
 		 $("#Contributions").parent().after($new_recit_button);
 	 }

	 /* do stuf... */
	 var isself = ( mw.config.get('wgUserName') == mw.config.get('wgTitle') );
	 var isU2 = ( mw.config.get('wgUserGroups').indexOf("U2") != -1 );
	 var issysop = ( mw.config.get('wgUserGroups').indexOf("sysop") != -1 );

	 if ( isself && ( isU2 || issysop ) && mw.mgw_display_groupes() ) {
		 mw.mgw_toggle_groupes();
		 mw.mgw_check_membre();
	 }

 	 if ( isself && ( isU2 || issysop ) ) {
		 mw.create_groupe_link();
  	 mw.mgw_parse_archives();
	 }

	 if ( isself ) {
		  mw.create_pages_links();
	 }


}( mediaWiki, jQuery ) );
