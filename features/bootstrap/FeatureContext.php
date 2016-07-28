<?php

use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Context\Context;
use Behat\Behat\Context\SnippetAcceptingContext;
use Behat\Gherkin\Node\PyStringNode;
use Behat\Gherkin\Node\TableNode;

/**
 * Defines application features from the specific context.
 */
class FeatureContext implements Context, SnippetAcceptingContext
{
    /**
     * Initializes context.
     *
     * Every scenario gets its own context instance.
     * You can also pass arbitrary arguments to the
     * context constructor through behat.yml.
     */
    public function __construct()
    {
    }

    /**
     * @Given Martine une utilisatrice de niveau :arg1
     */
    public function martineUneUtilisatriceDeNiveau($arg1)
    {
        throw new PendingException();
    }

    /**
     * @When elle édite sa page utilisateur
     */
    public function elleEditeSaPageUtilisateur()
    {
        throw new PendingException();
    }

    /**
     * @When inscrit test@example.org dans le champ « E-mail »
     */
    public function inscritTestExampleOrgDansLeChampEMail()
    {
        throw new PendingException();
    }

    /**
     * @When enregistre la page
     */
    public function enregistreLaPage()
    {
        throw new PendingException();
    }

    /**
     * @Then l’adresse de courriel enregistrée dans ses préférences est test@example.org
     */
    public function lAdresseDeCourrielEnregistreeDansSesPreferencesEstTestExampleOrg()
    {
        throw new PendingException();
    }

    /**
     * @When clique sur le statut « médecin »
     */
    public function cliqueSurLeStatutMedecin()
    {
        throw new PendingException();
    }

    /**
     * @Then sa page indique le statut « médecin »
     */
    public function saPageIndiqueLeStatutMedecin()
    {
        throw new PendingException();
    }

    /**
     * @Then elle appartient au groupe utilisateur « médecin »
     */
    public function elleAppartientAuGroupeUtilisateurMedecin()
    {
        throw new PendingException();
    }

    /**
     * @When elle édite la page utilisateur de Alexandre
     */
    public function elleEditeLaPageUtilisateurDeAlexandre()
    {
        throw new PendingException();
    }

    /**
     * @Then l’accès en édition lui est refusé
     */
    public function lAccesEnEditionLuiEstRefuse()
    {
        throw new PendingException();
    }

    /**
     * @When elle clique sur le statut additionnel « tuteur »
     */
    public function elleCliqueSurLeStatutAdditionnelTuteur()
    {
        throw new PendingException();
    }

    /**
     * @Then le statut additionnel ne change pas
     */
    public function leStatutAdditionnelNeChangePas()
    {
        throw new PendingException();
    }

    /**
     * @Given Alexandre un utilisateur de niveau :arg1
     */
    public function alexandreUnUtilisateurDeNiveau($arg1)
    {
        throw new PendingException();
    }

    /**
     * @When il édite la page utilisateur de Martine
     */
    public function ilEditeLaPageUtilisateurDeMartine()
    {
        throw new PendingException();
    }

    /**
     * @Then l’adresse de courriel enregistrée dans les préférences de Martine est test@example.org
     */
    public function lAdresseDeCourrielEnregistreeDansLesPreferencesDeMartineEstTestExampleOrg()
    {
        throw new PendingException();
    }

    /**
     * @Then la page de Martine indique le statut « médecin »
     */
    public function laPageDeMartineIndiqueLeStatutMedecin()
    {
        throw new PendingException();
    }

    /**
     * @When clique sur le statut additionnel « tuteur »
     */
    public function cliqueSurLeStatutAdditionnelTuteur()
    {
        throw new PendingException();
    }

    /**
     * @Then la page de Martine indique le statut additionnel « tuteur »
     */
    public function laPageDeMartineIndiqueLeStatutAdditionnelTuteur()
    {
        throw new PendingException();
    }

    /**
     * @Then elle appartient au groupe utilisateur « tuteur »
     */
    public function elleAppartientAuGroupeUtilisateurTuteur()
    {
        throw new PendingException();
    }

    /**
     * @When clique sur le statut additionnel « aucun »
     */
    public function cliqueSurLeStatutAdditionnelAucun()
    {
        throw new PendingException();
    }

    /**
     * @Then la page de Martine n’indique aucun statut additionnel
     */
    public function laPageDeMartineNIndiqueAucunStatutAdditionnel()
    {
        throw new PendingException();
    }

    /**
     * @Then elle n’appartient pas aux groupes utilisateur « tuteur » ou « modérateur »
     */
    public function elleNAppartientPasAuxGroupesUtilisateurTuteurOuModerateur()
    {
        throw new PendingException();
    }
}
