[English below]

Personnalisations pour MGWiki
=============================

Version 1.0 (2021)
-----------------

Projet de migration progressive vers une gestion complète des utilisateurs, des groupes et de leurs droits rattachés via des tables spécifiques MySQL.

Cette version comprend:
* réparation du système de création des groupes & utilisateurs dont la mécanique originale basée sur SMW ne marche plus (~ conflits de propriétés) => création de PagesFunctions::getTemplateInfos() et ::updateTemplateInfos() pour récupérer les variables directement depuis les modèles inclus dans les pages + optimisation des pages Utilisateur via javascript
* fusion des extensions MGWiki-dev et MGWiki
* réorganisation de l'extension par Modules (includes/Modules) avec fichiers de configuration différenciés (config/)
* définition d'outils génériques à toute l'extension (includes/Utilities, includes/Api) et la mise en évidence des outils pour l'interface d'autres extensions (includes/Foreign)
* création de deux nouvelles tables : mgw_task (gestion des tâches en cours de traitement) et mgw_stat (production de statistiques sur l'utilisation du site)
* module Notification: demande de relecture à son référent MGWiki (S Beyou)
* module Admin: MgwAdminUsers (temporaire) et MassMail (fonctionne avec une API transversale dédiée à l'envoi de mail - A Brulet)
* module Adepul: !! son intégration se fera directement dans MGWiki 2.0
* module Auth: ensemble des pages spéciales et classes dédiées à l'authentification
* module Json: ébauche de fonctionnalité permettant de reporter dans les pages du wiki les fichiers de configuration .json (facilitation des màj d'interface)
* un outil de maintenance: mgw-updater.php (automatisation des sauvegardes BDD et fichiers avant màj du logiciel, automatisation de l'intégration multi-articles modifiés et/ou renommés, automatisation des ajouts de Hooks customisés dans le corps de MediaWiki)

Version 0 (2016-2020)
---------------------
Cette extension permet de personnaliser le site [MGWiki](https://mgwiki.univ-lyon1.fr), wiki privé dédié au partage d’expérience entre internes en médecine et médecins.

Les personnalisations concernent :

* la gestion des droits entre différents niveaux d’utilisateurs ;
* la création d’utilisateurs au moyen de formulaires Semantic MediaWiki ;
* l’envoi d’une invitation aux nouveaux inscrits, invitation qui les connecte directement et les redirigent vers le formulaire de leur profil utilisateur ;
* la synchronisation entre les formulaires des profils utilisateur et MediaWiki (groupes utilisateur et adresse de courriel).

Cette extension (et son versant Semantic MediaWiki) a été réalisée par l’entreprise [Wiki Valley](http://wiki-valley.com) pour MGWiki, et est publiée sous licence GPL-3.0+. Il est peu probable qu’elle serve directement à d’autres réutilisateurs, étant très spécialisée, mais elle est publiée dans l’espoir que certaines parties puissent aider d’autres développeurs ayant des besoins similaires.

MGWiki customisations
=====================

This extension customise the website [MGWiki](https://mgwiki.univ-lyon1.fr), private wiki dedicated to experience sharing between medecine interns and physicians.

These customisations are:

* the rights management between different user levels;
* the creation of users by means of Semantic MediaWiki forms;
* the sending of an invitation to new users, which logs them directly in and redirects them to the user profile form;
* the synchronisation between the user profile forms and MediaWiki (user groups and email address).

This extension (and its counterpart Semantic MediaWiki) was realised by the company [Wiki Valley](http://wiki-valley.com) for MGWiki, and is published under the GPL-3.0+ licence. It is unlikely it will be directely reused by other people, being very specialised, but it is published in the hope some parts can help other developers with similar needs.
