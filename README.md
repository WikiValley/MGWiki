Extension MGWiki - module de développement
==========================================

màj: 17/11/2020

Cette extension permet de personnaliser le site [MGWiki](https://mgwiki.univ-lyon1.fr), wiki privé dédié au partage d’expérience entre internes en médecine et médecins.

Modules inclus:
* Pages spéciales:
  - includes/SpecialAccountRequest.php : page spéciale formulaire de demande d'inscription accessible au publique
* Parsers:
  - {{#mgw-onclick:<url>|<div / span>|<contenu html & wiki text (modèles non affichés)>}}
* API:
  - includes/Api/ApiGetJson: API (?action=getjson) pour récupérer un contenu .json, accès publique autorisé
* Classes php:
  - includes/Utilities/GetJsonPage.php: classe pour l'obtention et la manipulation de données au format JSON depuis une page du wiki
   permet la gestion de données d'interface directement depuis le wiki:
    - MediaWiki:Specialaccountrequest.json : définition du formulaire Special:Account_Request
    - MediaWiki:MGWiki-messages.json : messages d'interface
  - includes/Utilities/JsonToForm : classe pour la création de formulaires depuis une description au format .json
* Autres:
  - maintenance/CheckHooks.php:
    - vérification de l'existence des Hooks utilisés par l'extension
    - insertion de Hooks customizés si nécessaire (onApiAllow)
  - skinning:
    - resources/ext.mgwiki-dev.js & css
    - images/php
  - login avec email proposé ( Hooks::onAuthChangeFormFields )

Todo:
* /var/www/html/wiki/includes/preferences/DefaultPreferencesFactory.php : modif automatique
* /var/www/html/wiki/includes/specials/SpecialChangeEmail.php : modif automatique
* includes/SpecialAccount.php: use WebResponse::setCookie() instead of setcookie() when upgrading Mediawiki >= 1.35

Core changes:
*  /includes/api/ApiMain.php l.1419 :
    ++   && !Hooks::run('ApiAllow', [ $module, $user ] )
