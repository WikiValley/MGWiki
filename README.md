[English below]

Personnalisations pour MGWiki
=============================

** version 0.1 **
Cette extension permet de personnaliser le site [MGWiki](https://mgwiki.univ-lyon1.fr), wiki privé dédié au partage d’expérience entre internes en médecine et médecins.

Les personnalisations concernent :

* la gestion des droits entre différents niveaux d’utilisateurs ;
* la création d’utilisateurs au moyen de formulaires Semantic MediaWiki ;
* l’envoi d’une invitation aux nouveaux inscrits, invitation qui les connecte directement et les redirigent vers le formulaire de leur profil utilisateur ;
* la synchronisation entre les formulaires des profils utilisateur et MediaWiki (groupes utilisateur et adresse de courriel).

Cette extension (et son versant Semantic MediaWiki) a été réalisée par l’entreprise [Wiki Valley](http://wiki-valley.com) pour MGWiki, et est publiée sous licence GPL-3.0+. Il est peu probable qu’elle serve directement à d’autres réutilisateurs, étant très spécialisée, mais elle est publiée dans l’espoir que certaines parties puissent aider d’autres développeurs ayant des besoins similaires.

** version 0.2: **

Projet de migration progressive vers une gestion complète des utilisateurs, des groupes et de leurs droits rattachés via des tables spécifiques MySQL.

Cette version comprend:
* un début de réorganisation de l'extension par Modules (includes/Modules) avec différentiation des fichiers de configuration (config)
* l'intégration des tables spécifiques mgw_task (gestion des tâches en cours de traitement) et mgw_stat (production de statistiques sur l'utilisation du site)
* la définition d'outils génériques à toute l'extension (includes/Utilities, includes/Classes, includes/Api)
* un système de notification de demande de relecture à son référent MGWiki (S Beyou)
    TODO: élargir la demande de relecture à plusieurs utilisateurs et/ou tout un groupe
* un module d'envoi mails de masse à tout un groupe qui fonctionne avec une API transversale dédiée à l'envoi de mail (A Brulet)
    TODO: installation de l'extension Echo et articulation envoi mail / envoi notification
    TODO: intégration des différents groupes au sens MGWiki dans les choix d'envoi groupé
* une page spéciale temporaire "MgwAdminUsers" pour permettre la gestion centralisée mails et groupes mediawiki le temps du déploiement de MGWiki V 2.0
-  

MGWiki customisations
=====================

This extension customise the website [MGWiki](https://mgwiki.univ-lyon1.fr), private wiki dedicated to experience sharing between medecine interns and physicians.

These customisations are:

* the rights management between different user levels;
* the creation of users by means of Semantic MediaWiki forms;
* the sending of an invitation to new users, which logs them directly in and redirects them to the user profile form;
* the synchronisation between the user profile forms and MediaWiki (user groups and email address).

This extension (and its counterpart Semantic MediaWiki) was realised by the company [Wiki Valley](http://wiki-valley.com) for MGWiki, and is published under the GPL-3.0+ licence. It is unlikely it will be directely reused by other people, being very specialised, but it is published in the hope some parts can help other developers with similar needs.
