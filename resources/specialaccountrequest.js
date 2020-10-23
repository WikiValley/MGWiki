

( function ( mw, $ ) {
  mw.accountRequest = function(){
    if ($('input[type=radio][name=institution]:checked').attr('value') == 'lyon1') {
      $('.mgw-hidden').show();
      $('span[id=intro-lyon]').show();
      $('span[id=intro-adepul]').hide();
      $('span[id=intro-autre]').hide();
      $('td[class=mgw-tr-formateur]').text('Nom de votre tuteur');
      $('td[class=mgw-tr-year]').text('Année de promotion');
      $('.mgw-tr-formateur').show();
      $('.mgw-tr-formateur').attr("required","true")
      $('.mgw-tr-year').show();
      $('.mgw-tr-year').attr("required","true")
      $('#mgw-textarea-comment').removeAttr("required");
    }
    if ($('input[type=radio][name=institution]:checked').attr('value') == 'adepul') {
      $('.mgw-hidden').show();
      $('span[id=intro-lyon]').hide();
      $('span[id=intro-adepul]').show();
      $('span[id=intro-autre]').hide();
      $('td[class=mgw-tr-formateur]').text('Nom de votre formation');
      $('.mgw-tr-formateur').show();
      $('.mgw-tr-formateur').attr("required","true");
      $('.mgw-tr-year').hide();
      $('.mgw-tr-year').removeAttr("required");
      $('#mgw-p-comment-intro a').hide();
      $('#mgw-textarea-comment').attr("required");
    }
    if ($('input[type=radio][name=institution]:checked').attr('value') == 'autre') {
      $('.mgw-hidden').show();
      $('span[id=intro-lyon]').hide();
      $('span[id=intro-adepul]').hide();
      $('span[id=intro-autre]').show();
      $('.mgw-tr-formateur').hide();
      $('.mgw-tr-formateur').removeAttr("required");
      $('.mgw-tr-year').hide();
      $('.mgw-tr-year').removeAttr("required");
      $('#mgw-p-comment-intro a').show();
      $('#mgw-textarea-comment').attr("required", true);
    }
  }

  mw.mgwHome = function(){
    $(location).attr("href", "https://mgwiki.univ-lyon1.fr");
  }

  $( function () {
    // code à faire d'emblée (...)
  });
}( mediaWiki, jQuery ) );
