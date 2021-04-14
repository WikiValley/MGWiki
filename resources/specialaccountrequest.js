( function ( mw, $ ) {

  mw.specialaccountrequest = function( ){
    mw.changeform( 'specialaccountrequest' );
  }

  mw.mgwHome = function(){
    $( location ).attr( "href", "https://mgwiki.univ-lyon1.fr" );
  }

  $( function () {
    mw.specialaccountrequest();
  });
}( mediaWiki, jQuery ) );
