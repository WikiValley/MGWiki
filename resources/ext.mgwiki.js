( function ( mw, $ ) {

	/**
	 * PATCH TEMPORAIRE MGW 1.0
	 * adaptation des valeurs par défaut du formulaire "modifier le groupe"
	 * pour ne pas surcharger le nombre de formulaires spécifiques à chaque
	 * type de groupe... ( GEP/GAPP/Stage praticien/DPC)
	 */
	 var titleRegex = new RegExp('AjouterDonnées\/Modifier le groupe\/');
	 if( titleRegex.test( mw.config.get('wgTitle') ) ) {
		 $inputs = $('input');
		 var groupe_type = '';
		 $inputs.each( function(){
			 if ( $( this ).attr('name') == 'Groupe[Type de groupe]' )
			 	groupe_type = $( this ).val();
		 });
		 if ( groupe_type == 'GEP' || groupe_type == 'Stage praticien' ) {
			 mw.mgw_participant = 0;
			 $('input.multipleTemplateAdder').click( function( ){
				 mw.mgw_participant++;
				 // #input_x_12 = id du bouton radio 'interne'
				 setTimeout(() => {  $('#input_'+mw.mgw_participant+'_12').click() }, 500 );
			 });
		 }
	 }
 	 /**
 	  * PATCH TEMPORAIRE MGW 1.0
 	  * définition des valeurs cachées du formulaire "Personne"
		* objet: déclencher la màj même en l'absence de modifs
 	  */
 	 if ( mw.config.get('wgNamespaceNumber') === 2
	 			&& mw.config.get('wgAction') === 'formedit' ) {
 		 $('input').each( function(){
 			 if ( $( this ).attr('name') == 'Personne2[updatetime]' )
 			 	$(this).val( Math.floor(Date.now() / 1000) );

			 if ( $( this ).attr('name') == 'Personne2[updateuser]' )
			 	$(this).val( mw.config.get('wgUserName') );
 		 });

		 // lisibilité: on cache l'option "Titre" aux non-médecins
		 $('th').each( function(){
			 if ( $(this).html().match(/Titre\:/) ) {
				 $(this).addClass('mgw-titre-field');
				 $(this).next('td').addClass('mgw-titre-field');
			 }
		 });

		 mw.mgw_hide_titre = function (){
			 if ( $('#input_10').is(':checked') ) {
				 $('.mgw-titre-field').show();
			 }
			 else {
				 $('.mgw-titre-field').hide();
			 }
		 }
		 $('#input_9').click( mw.mgw_hide_titre );
		 $('#input_10').click( mw.mgw_hide_titre );
		 $('#input_11').click( mw.mgw_hide_titre );
		 $('#input_12').click( mw.mgw_hide_titre );

		 mw.mgw_hide_titre();

		 // on cache l'onglet 'modifier le wikitexte'
		 if ( mw.config.get( 'wgUserGroups').indexOf('sysop') == -1 ) {
				$('#ca-edit').hide();
		 }
 	 }

	/**
	 * Onglets d'action: correction de l'affichage si "ca-edit" au lieu de "ca-ve-edit"
	 * pour les ns MAIN et RECIT
	 */
	 if ( ( mw.config.get('wgNamespaceNumber') === 0
	 				|| mw.config.get('wgNamespaceNumber') === 724 )
 				&& mw.config.get( 'wgAction' ) === 'view' ) {
			str = $('#ca-edit a').attr('href');
			//str = str.replace('&action', '&veaction');
			//$('#ca-edit a').attr('href',str);
			$('#ca-edit a').html('Modifier');
			$('#ca-edit').attr('id','ca-ve-edit');
		}

	/**
	 * Modify in background the competency on the current page.
	 *
	 * The edition is done by the current user without edit summary.
	 * The competence can be any string followed by '=Oui' or '=Non'
	 * (boolean values in a template). If no specific value is given
	 * in the second argument, the boolean value is toggled.
	 *
	 * @param {string} Competence name
	 * @param {string|boolean} [Value]
	 * @return void
	 */
	mw.mgwikiModifyCompetence = function ( name, value ) {

		new mw.Api()
			.mgwikiEdit( mw.config.get( 'wgPageName' ), function ( revision ) {
				if ( !revision.content.match( new RegExp( '\\| *' + name + ' *= *([Oo]ui|[Nn]on)' ) ) ) {
					return revision.content;
				}
				if ( value === undefined ) {
					value = 'Oui';
					if ( revision.content.match( new RegExp( '\\| *' + name + ' *= *[Oo]ui' ) ) ) {
						value = 'Non';
					}
				} else if ( value == 'Non' || value == 'non' || !value ) {
					value = 'Non';
				} else {
					value = 'Oui';
				}
				return revision.content.replace(
					new RegExp( '\\| *' + name + ' *= *([Oo]ui|[Nn]on)' ),
					'|' + name + '=' + value );
			} );
	}

	/**
	 * On some images, add clickable areas editing the page.
	 *
	 * More specifically, the image must be included in a <div class="mgwiki-marguerite">,
	 * and this div must also contain a description of the clickable areas redacted in
	 * HTML with <map name="somename"><area ...></map> (only <map> and <area> are whitelisted
	 * HTML tags); the <area> must have a dedicated attribute data-mgwiki-competence with
	 * "Compétence" or "Compétence=Oui" or "Compétence=Non" inside. When the use clicks on
	 * some area, s/he edits the page in background, replacing the competence on the page
	 * (specifically, "|" + name + "=Oui" (or "=Non") according to the data-mgwiki-competence
	 * attribute ("Compétence" alone toggles the boolean value).
	 */
	$( function () {

		if( !mw.config.get( 'wgIsArticle' ) || mw.config.get( 'wgAction' ) != 'view' || $( '#mw-content-text table.diff' ).length > 0 ) {
			return;
		}

		$( '.mgwiki-marguerite' ).each( function () {

			var map = $( '.map', $(this) ),
			    maphtml = map.text() ?
			    	map.text() :
			    	map.html()
			    		.replace( new RegExp( '</?pre>' ), '' )
			    		.replace( new RegExp( '&lt;' ), '<' )
			    		.replace( new RegExp( '&gt;' ), '>' ),
			    mapname = maphtml.match( new RegExp( '<map .*name="[a-zA-Z0-9]+".*>' ) ) ?
			    	maphtml.match( new RegExp( '<map .*name="([a-zA-Z0-9]+)".*>' ) )[1] :
			    	null;

			// Verify all is in order and protect against unauthorised HTML, only <map> and <area> are whitelisted
			if ( !maphtml || !mapname || maphtml.replace( new RegExp( '</?(map|area)[ >]', 'g' ), '' ).match( new RegExp( '</?[a-zA-Z]+[ >]' ) ) ) {
				return;
			}

			// Add the <map> definition and activate it on the first image
			$(this).append( maphtml );
			$( 'img', $(this) ).first().attr( 'usemap', '#' + mapname );

			// Add click events on <area>
			$( 'area', $(this) ).css( 'cursor', 'pointer' ).click( function() {
				var competencedata = $(this).attr( 'data-mgwiki-competence' ),
				    competence = competencedata ?
				    	competencedata.match( new RegExp( '^([a-zA-ZéèêëáöôíîïÉÈÊËÁÖÔÍÎÏ0-9]+)(=([Oo]ui|[Nn]on))?$' ) ) :
				    	null;
				if( !competence ) {
					return false;
				}
				if( competence[3] ) {
					mw.mgwikiModifyCompetence( competence[1], competence[3] );
				} else {
					mw.mgwikiModifyCompetence( competence[1] );
				}
				return false;
			} );
		} );
	} );

		/**
		 * FONCTIONS D'AFFICHAGE DIVERSES
		 * TODO: Ménage ... !
		 */
		$('.mgw-dropdown').click( function (){
			label = $(this).html();
			$items = $('.mgw-dropdown-' + $(this).attr('data'));
			if ( $(this).attr('state') == 'hidden' ) {
				$(this).html( label.replace('►','▼') );
				$items.show();
				$(this).attr('state','displayed');
			}
			else {
				$(this).html( label.replace('▼','►') );
				$items.hide();
				$(this).attr('state','hidden');
			}
		});

		//liens donnés par le modèle {{mgw-link}}
		mw.mgwLink = function () {
			$(".mgw-link-self").each(function(){
				let url = $(this).children('.mgw-link-url').text();
				$(this).attr("onClick", "location.href='" + url + "'");
				$(this).find('a').attr('href', url);
			});
			$(".mgw-link-blank").each(function(){
				let url = $(this).children('.mgw-link-url').text();
				$(this).attr("onClick", "parent.open('" + url + "')");
				$childlink = $(this).children('a');
				$childlink.replaceWith($('<p>' + $childlink.html() + '</p>'));
			});
		}

		mw.mgwRedirectPost = function (url, data, target) {
	    var form = document.createElement('form');
	    document.body.appendChild(form);
	    form.method = 'post';
	    form.action = url;
			if ( target == "blank" ) {
				form.setAttribute( "target", "_blank");
			}
	    for (var name in data) {
	        var input = document.createElement('input');
	        input.type = 'hidden';
	        input.name = name;
	        input.value = data[name];
	        form.appendChild(input);
	    }
	    form.submit();
		}

		//change menu border color
		mw.mgwBorderColor = function() {
			if ( $(".mgw-border-color").attr('style') !== undefined ) {
				let color = $(".mgw-border-color").attr('style').match(/(#[a-z0-9]{6});/)[1];
				let col = color.substring(1, 7);
				$('#content').css('border', '1px solid' + color);
				$('.vectorTabs,.vectorTabs span,.vectorTabs ul').css('background-image',
					'url(http://localhost/wiki/extensions/MGWikiDev/images/php/MenuListBorder.php?color='+col+')');
				$('.vectorTabs li:not(.selected)').css('background-image',
					'url(http://localhost/wiki/extensions/MGWikiDev/images/php/MenuListBackground.php?color='+col+')');
			}
		}

		//logo aide:
		//override image link tooltip
		mw.mgwImgTooltip = function () {
			$(".mgw-help-img").each(function () {
				title = $( this ).attr('title');
				$( this ).children("a").attr('title',title);
			});
		}

	  mw.mgwToggleCreateUserSubPage = function () {
	    $icon = $("#mgw-toggle-createUserSubPage-icon");
	    $div = $("#mgw-toggle-createUserSubPage");
	    if ($icon.attr("class") == "mgw-show"){
	      $div.after('\
	        <div class="mgw-toggle-content">\
	          <form method="get" action="/wiki/index.php" name="uluser" id="mw-userrights-form1">\
	            <tr><td><input name="user" size="30" value="" id="username" class="mw-autocomplete-user" ></td>\
	                <td><input type="submit" value="Afficher les groupes d’utilisateurs"></td></tr>\
	          </form>\
	        </div>');
	      $icon.html(' ▲ ');
	      $icon.attr('class','mgw-hide');
	      $div.attr('class','mgw-toggle-hide');
	    }
	    else {
	      $icon.html(' ▼ ');
	      $('.mgw-toggle-content').remove();
	      $icon.attr('class','mgw-show');
	      $div.attr('class','mgw-toggle-show');
	    }
	  }

		// faire disparare les messages de succès après 10 secondes
		mw.mgwAlertMessage = function () {
			setTimeout(() => {  $('.mgw-alert[status=done]').hide(); }, 2000);
		}

	  $( function () {
		 mw.mgwAlertMessage();
		 mw.mgwBorderColor();
	   mw.mgwImgTooltip();
		 mw.mgwLink();
		 $('.mgw-tooltiptext').css('display','');
	   $("#mgw-toggle-createUserSubPage-icon").html(' ▼ ');
	   $("#mgw-toggle-createUserSubPage").attr("onclick","mw.mgwToggleCreateUserSubPage()");
		 $("#wpName1, #wpPassword1").attr("placeholder","");
		 $( ".mgw-delete-link" ).click( function() {
			 let link = $(this).attr('href');
			 let elmt = $(this).attr('elmt');
			 if ( confirm( 'Vous êtes sur le point de supprimer: ' + elmt +
			 		'\nConfirmez-vous ?' ) ) {
						document.location.href= link;
					}
			});

			$( ".mgw-post-link" ).each( function(){
				 let data = JSON.parse( $(this).find(".mgw-post-link-data").html() );
				 if ( data.data ) {
				  var $link = $("<p></p>");
					$link.attr( 'class', 'mgw-post-submit' );
					$link.attr( 'data', data.data );
					$link.attr( 'target', data.target );
				 }
				 else {
				  var $link = $("<a></a>");
					if ( data.target == "blank" ) {
						$link.attr( "target", "_blank");
					}
				 }
				 $link.attr( 'href', data.url );
				 $link.attr( 'title', data.tooltip );

				 let $a = $(this).find(".mgw-post-link-inner a");
				 let $b = $(this).find(".mgw-post-link-inner");
				 if ( $a.length != 0 ) {
					 $link.html( $a.html() );
				 }
				 else {
				  $link.html( $b.html() );
				 }
				 $(this).after( $link );
			})

	 	 $( ".mgw-post-submit" ).click( function() {
	 		 let url = $(this).attr('href');
	 		 let data = $(this).attr('data');
			 let target = $(this).attr('target');
			 var rData = new Object();
			 data = data.split(",");
			 data.forEach( function( item ){
			  item = item.split("=");
				rData[item[0]] = item[1];
			 });
			 mw.mgwRedirectPost( url, rData, target );
	 		});
	  });

}( mediaWiki, jQuery ) );
