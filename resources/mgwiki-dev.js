
/**
 * MGW customization
 */

 mw.mgwImgTooltip();
 $("#mgw-toggle-createUserSubPage-icon").html(' ▼ ');
 $("#mgw-toggle-createUserSubPage").attr("onclick","mw.mgwToggleCreateUserSubPage()");
 
( function ( mw, $ ) {
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
}( mediaWiki, jQuery ) );
