Extension MGWiki - module de développement
==========================================

Cette extension permet de personnaliser le site [MGWiki](https://mgwiki.univ-lyon1.fr), wiki privé dédié au partage d’expérience entre internes en médecine et médecins.

Modules inclus:
* SpecialAccountRequest (MàJ 23/10/2020)

Todo:
* includes/SpecialAccount.php: use WebResponse::setCookie() instead of setcookie() when upgrading Mediawiki >= 1.35

Core changes:
*  /includes/api/ApiMain.php l.1420 :
    .protected function checkExecutePermissions( $module ) {
    .  $user = $this->getUser();
    .  
    .  if ( $module->isReadMode() && !User::isEveryoneAllowed( 'read' ) &&
    .    !$user->isAllowed( 'read' )
    ++   && !Hooks::run('ApiAllow', [ $module, $user ] )
