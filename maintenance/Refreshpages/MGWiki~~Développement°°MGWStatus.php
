=== Description ===
Version ultra-simplifiée de [https://www.mediawiki.org/wiki/Manual:Status.php Status.php]. Enregistre l'état de retour ''$done'' (bool) et un message ''$mess'' pour une fonction appelée. <u>Possibilité de passer une variable ''$extra'' de n'importe-quel type.</u>

=== Classe ===
 [[MGWiki:Développement/Utilities|MediaWiki\Extension\MGWiki\Utilities]]\MGWStatus;

=== Usage ===
==== Constructeur ====
 $status = MGWStatus::new( ''bool'' $done, ''string'' $mess, ''mixed'' $extra = null );
 $status = MGWStatus::newDone( ''string'' $mess, ''mixed'' $extra = null );
 $status = MGWStatus::newFailed( ''string'' $mess, ''mixed'' $extra = null );

==== Fonctions publiques ====
 $status->done(); // @return bool|null
 $status->set_done( bool );

 $status->mess(); // @return string|null
 $status->set_mess( string );

 $status->extra(); // @return mixed|null
 $status->set_extra( mixed );

==== Variables publiques ====
 $status->extra;