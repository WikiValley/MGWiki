( function ( mw, $ ) {

	/**
	 * Onglets d'action: correction de l'affichage si "ca-edit" au lieu de "ca-ve-edit"
	 * pour les ns MAIN et RECIT
	 */
	 var veAllowedNameSpaces = [0,724 ];
	 if ( ( mw.config.get('wgNamespaceNumber') === 0
	 				|| mw.config.get('wgNamespaceNumber') === 724 )
 				&& mw.config.get( 'wgAction' ) === 'view' ) {
			str = $('#ca-edit a').attr('href');
			str = str.replace('&action', '&veaction');
		console.log(str);
			$('#ca-edit a').attr('href',str);
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

}( mediaWiki, jQuery ) );
