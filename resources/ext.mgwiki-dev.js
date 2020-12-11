
/**
 * MGW customization
 */

( function ( mw, $ ) {
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
  });
}( mediaWiki, jQuery ) );
