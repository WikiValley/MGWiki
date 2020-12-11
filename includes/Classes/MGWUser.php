<?php

namespace MediaWiki\Extension\MGWikiDev\Classes;

/* MGWiki */
use MediaWiki\Extension\MGWikiDev\Utilities\UsersFunctions;

use MediaWiki\Extension\MGWikiDev\Classes\MGWStatus as Status;
use MediaWiki\Extension\MGWikiDev\Utilities\GetMessage as Msg;
use MediaWiki\Extension\MGWikiDev\Utilities\PagesFunctions as PageF;
use MediaWiki\Extension\MGWikiDev\Utilities\MgwDataFunctions as DbF;
/* Foreign */
use MediaWiki\Extension\MGWikiDev\Foreign\MGWRenameuser;
use MediaWiki\Extension\MGWikiDev\Foreign\MGWReplaceText;
/* MediaWiki */
use MediaWiki\MediaWikiServices;
use WikitextContent;

/**
 * Classe pour manipuler les utilisateurs MGWiki
 */
class MGWUser extends UsersFunctions {

  /**
   * @var int
   */
	private $user_id, $level;

  /**
   * @var string
	 * variables destinées à être manipulées par les utilisateurs
   */
	private $nom, $prenom, $email;

  /**
   * @var array
   */
  private $data;

  /**
   * @var bool
   */
  private $mw_exists, $mgw_exists, $page_exists;

	/**
   * (ne devrait pas être appelé directement)
	 */
	public function __construct( ) {}

	/**
   * @param int $user_id (mediawiki user_id)
   * @return MGWUser|null
	 */
  public static function newFromUserId( $user_id ) {
    $MGWuser = new MGWUser;
		$MGWuser->set_user_id( $user_id );
		$r = $MGWuser->userExists( $user_id );
		if ( !$r )
			return null;

		// données de la table user
		$MGWuser->set_mw_exists( true );
    $MGWuser->retrieveBaseData();

		// données de la table mgw_utilisateurs
		$r = $MGWuser->mgwUserExists( 'user_id', $user_id );
		if ( !$r ) $MGWuser->set_mgw_exists( false );
		else {
			$MGWuser->set_mgw_exists( true );
	    $MGWuser->retrieveMGWData();
		}

		// données de la page utilisateur
		$r = PageF::getPageFromTitleText( $MGWuser->data['user_name'], NS_USER, true );
		if ( is_null( $r ) ) $MGWuser->set_page_exists( false );
		else {
			$MGWuser->set_page_exists( true );
			$MGWuser->retrievePageData( $r );
		}

		// définition des variables principales
		$MGWuser->set_nom();
		$MGWuser->set_prenom();
		$MGWuser->set_email();

    return $MGWuser;
  }

	#########################
	## Fonctions publiques ##
	#########################

	// Setters
	public function set_user_id( $user_id ) {
		$this->user_id = $user_id;
	}

	public function set_level( $level ) {
		$this->level = $level;
	}

	public function set_nom( $nom = null ) {

		if ( is_null( $nom ) && isset( $this->data['utilisateur_nom'] ) )
			$this->nom = $this->data['utilisateur_nom'];

		elseif ( is_null( $nom ) && isset( $this->data['page_template_nom'] ) )
			$this->nom = $this->data['page_template_nom'];

		elseif ( !is_null( $nom ) )
			$this->nom = $nom;

		else $this->nom = null;
	}

	public function set_prenom( $prenom = null ) {

		if ( is_null( $prenom ) && isset( $this->data['utilisateur_prenom'] ) )
			$this->prenom = $this->data['utilisateur_prenom'];

		elseif ( is_null( $prenom ) && isset( $this->data['page_template_prenom'] ) )
			$this->prenom = $this->data['page_template_prenom'];

		elseif ( !is_null( $prenom ) )
			$this->prenom = $prenom;

		else $this->prenom = null;
	}

	public function set_email( $email = null ) {

		if ( is_null( $email ) && isset( $this->data['user_email'] ) )
			$this->email = $this->data['user_email'];

		elseif ( is_null( $email ) && isset( $this->data['page_template_email'] ) )
			$this->email = $this->data['page_template_email'];

		elseif ( !is_null( $email ) )
			$this->email = $email;

		else $this->email = null;
	}

	public function set_mw_exists( $mw_exists ) {
		$this->mw_exists = $mw_exists;
	}

	public function set_mgw_exists( $mgw_exists ) {
		$this->mgw_exists = $mgw_exists;
	}

	public function set_page_exists( $page_exists ) {
		$this->page_exists = $page_exists;
	}

	// getters
	public function get_user_id( ) {
		return $this->user_id;
	}

	public function get_level( ) {
		return $this->level;
	}

	public function get_nom( ) {
		return $this->nom;
	}

	public function get_prenom( ) {
		return $this->prenom;
	}

	public function get_email( ) {
		return $this->email;
	}

	public function get_mw_exists( ) {
		return $this->mw_exists;
	}

	public function get_mgw_exists( ) {
		return $this->mgw_exists;
	}

	public function get_page_exists( ) {
		return $this->page_exists;
	}

	public function get_data( ) {
		return $this->data;
	}

	/**
	 * @return string 'Prénom NOM'
	 * construit à partir de $this->prenom et $this->nom
	 */
	public function make_name() {
		if ( isset( $this->prenom ) && isset( $this->nom ) ) {
			return $this->prenom . ' ' . $this->nom;
		}
		return false;
	}

  public function same_names() {

		if ( !$this->mw_exists || !$this->page_exists )
			return false;

		if ( !$this->data['page_template'] ||
				is_null( $this->data['page_template_nom'] ) ||
				is_null( $this->data['page_template_prenom'] ) )
			return false;

		if ( $this->data['user_name'] != $this->data['page_template_prenom'] . ' ' . $this->data['page_template_nom'] )
    	return false;

		return true;
  }

  public function same_email() {

		if ( !$this->mw_exists || !$this->page_exists )
			return false;

		if ( !$this->data['page_template'] || is_null( $this->data['page_template_email'] ) )
			return false;

		if ( $this->data['user_email'] != $this->data['page_template_email'] )
    	return false;

		return true;
  }

	/**
	 * définit : $user_email = $page_template_email
	 * envoie un mail de confirmation
	 */
  public function update_email() {
		$user = $this->getUserFromId( $this->user_id );
		$user->setEmail( $this->data['page_template_email'] );
		$user->saveSettings();
		$user->sendConfirmationMail( 'changed' );
  }

	/**
	 * définit : $user_email = null
	 */
  public function delete_email() {
		$user = $this->getUserFromId( $this->user_id );
		$user->setEmail( null );
		$user->saveSettings();
  }


	/**
	 * définit : $user_groups = null
	 * @return string message de retour
	 */
  public function delete_all_groups() {
		$user = $this->getUserFromId( $this->user_id );
		$deleted = [];
		$kept = [];
		foreach ( $user->getGroups() as $group ) {
			if ( $group != 'sysop' ){
				$user->removeGroup( $group );
				$deleted[] = $group;
			}
			else {
				$kept[] = $group;
			}
		}
		$user->saveSettings();

		if ( count( $deleted ) > 0 ){
			$mess = 'Groupes supprimés: ' . implode( ',', $deleted );
		}
		else {
			$mess = 'Aucun groupe n\'a été supprimé';
		}

		if ( count( $kept ) > 0 ){
			$mess .= '<br>Groupes conservés: ' . implode( ',', $kept );
		}
		else {
			$mess .= '<br>Aucun groupe n\'a été conservé.';
		}

		return $mess;
  }

  public function same_real_name() {
		if ( $this->data['user_real_name'] != $this->data['user_name'] ) {
    	return false;
		}
		return true;
  }

	/**
	 * définit : $user_real_name = $user_name
	 */
  public function update_real_name() {
		$user = $this->getUserFromId( $this->user_id );
		$user->setRealName( $this->data['user_name'] );
		$user->saveSettings();
  }

	/**
	 * définit : $user_real_name = null
	 */
  public function delete_real_name() {
		$user = $this->getUserFromId( $this->user_id );
		$user->setRealName( null );
		$user->saveSettings();
  }

	public function sanitize_nom() {
		if ( !isset( $this->nom ) ) {
			return false;
		}
		$this->nom = strtoupper( $this->nom );
		return true;
	}

	public function sanitize_prenom() {
		if ( !isset( $this->prenom ) ) {
			return false;
		}
		$space = false;
		$str = $this->prenom;
		if ( preg_match('/ /', $str ) > 0 ) {
			$str = str_replace(' ','-',$str);
			$space = true;
		}

		$str = explode( '-', $str );
		foreach ( $str as $key => $substr ) {
			$str[$key] = ucfirst( strtolower( $substr ) );
		}

		$this->prenom = ( $space ) ? implode( ' ', $str ) : implode( '-', $str );

		return true;
	}

	/**
	 * FONCTION TEMPORAIRE
	 * @param string $summary le résumé de modification de page
	 * @return MGWStatus
	 */
	public function update_page_template_names( $summary ) {

		$mess = '';

		if ( $this->nom != $this->data['page_template_nom'] ) {
			$status = $this->update_page_template_info( 'Nom', $this->nom, $summary );
			$mess .= $status->mess() . '<br>';
			$done = $status->done();
		}

		if ( $this->prenom != $this->data['page_template_prenom'] ) {
			$status = $this->update_page_template_info( 'Prénom', $this->prenom, Msg::get('mgwuser-sanitize-names') );
			$mess .= $status->mess() . '<br>';
			if ( isset( $done ) && $done ) $done = $status->done();
			elseif ( !isset( $done ) ) $done = $status->done();
		}

		return Status::new( $done, $mess );
	}

	/**
	 * Renomme l'utilisateur à partir de $this->prenom et $this->nom
	 * @param string $summary
	 * @param bool $contentsReplace
	 * @param bool $nsAll recherche et remplace le texte dans toutes les pages
	 * @param string $n liste numérique des NS séparés par des virgules
	 * @return MGWStatus
	 */
	 public function rename( $summary, $contentsReplace = false, $nsAll = false, $ns = null, $user = null ) {

 		 if ( is_null( $user ) ) {
	 		 global $wgUser;
 			 $user = $wgUser;
 		 }

		 if ( !$this->mw_exists ) {
			 return Status::newFailed( 'Utilisateur inexistant' );
		 }
		 if ( !isset( $this->nom ) ) {
			 return Status::newFailed( 'Variable non définie : "NOM"' );
		 }
		 if ( !isset( $this->prenom ) ) {
			 return Status::newFailed( 'Variable non définie : "Prénom"' );
		 }

		 $oldName = $this->data['user_name'];
		 $newName = $this->make_name();
		 if ( $oldName == $newName ) {
			 return Status::newFailed( 'Nom d\'utilisateur inchangé : valeurs identiques.' );
		 }

		 $status = Status::newDone( 'processing...' );

		 // on renomme l'utilisateur
		 $rename = MGWRenameuser::execute(
			 $oldName,            // user_name actuel
			 $newName,            // user_name de destination
			 true,                // renommer toutes les pages
			 false,               // supprimer les redirections
			 $summary,
			 $user
		 );
		 if ( !$rename->done() ) $status->set_done( false );
		 $status->extra[] = $rename;

		 if ( $contentsReplace ) {
			 // on remplace l'ancien nom par le nouveau dans le contenu de toutes les pages
			 $replace = new MGWReplaceText( [
				 "target" 	=> $oldName,
				 "replace" 	=> $newName,
				 "regex" 		=> false,
				 "nsall" 		=> $nsAll,
				 "ns"  			=> $ns,
				 "summary" 	=> $summary,
				 "user" 		=> $user->getName()
			 ] );
			 $replace = $replace->execute();

			 if ( !$replace->done() ) $status->set_done( false );
			 $status->extra = $replace;
	 	 }

		 if ( !$status->done() ) {
			 $status->set_message( 'Certaines étapes du renommage ont échoué (voir détails)' );
		 }
		 else $status->set_message( 'Renommage réussi (voir détails)' );

		 return $status;
	 }

	 /**
	  * Mise à jour de la table mgw_utilisateurs
		* insert ou update sur les données $this->user_id, $this->nom, $this->prenom
		* @return MGWStatus
		*/
	 public function db_update( $updater_id = null ) {

		 if ( !$this->mw_exists ) {
			 return Status::newFailed("Erreur: un utilisateur MGWiki ne peut être créé que s'il existe déjà ".
			 "en tant qu'utilisateur MediaWiki");
		 }
		 if ( is_null( $this->nom ) || is_null( $this->prenom ) ) {
			 return Status::newFailed("Erreur: un utilisateur MGWiki ne peut être créé que si nom et prénom ".
			 "sont définis (null renvoyé)");
		 }

     if ( is_null($updater_id) ) {
       global $wgUser;
       $updater_id = $wgUser->getId();
     }

		 return DbF::update_or_insert(
			 'utilisateur',
			 [ 'user_id' => $this->user_id ],
			 [ 'nom' => $this->nom, 'prenom' => $this->prenom ],
			 $updater_id
		 );
	 }

	########################
	## Fonctions privées  ##
	########################
	private function retrieveBaseData() {

		if ( ! $this->mw_exists )
			return false;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $lb->getConnectionRef( DB_REPLICA );
    $res = $dbr->select(
			'user',
			[
				'user_id',
				'user_name',
				'user_real_name',
				'user_email'
			],
			'user_id = ' . $this->user_id
		);

    if ( $res->numRows() > 0 ) {
			$r = $res->fetchRow();
			foreach ( $r as $field => $value ) {
      	if ( is_string($field) ) $this->data[ $field ] = $value;
			}
			return true;
		}
		return false;
	}

	private function retrieveMGWData() {

		if ( ! $this->mgw_exists ) return false;

		$lb = MediaWikiServices::getInstance()->getDBLoadBalancer();
    $dbr = $lb->getConnectionRef( DB_REPLICA );
    $res = $dbr->select(
			'mgw_utilisateur',
			[
				'utilisateur_nom',
				'utilisateur_prenom',
				'utilisateur_level',
				'utilisateur_updater_id',
				'utilisateur_update_time'
			],
			'utilisateur_user_id = ' . $this->user_id
		);

    if ( $res->numRows() > 0 ) {
			$r = $res->fetchRow();
			foreach ( $r as $field => $value ) {
      	if ( is_string($field) ) $this->data[ $field ] = $value;
			}
			return true;
		}

		return false;
	}

	private function retrievePageData( $wikiPage ) {

		if ( ! $this->page_exists ) return false;

		$this->data['page_title'] = $wikiPage->getTitle()->getFullText();
		$this->data['page_url'] = $wikiPage->getTitle()->getFullURL();

		$redirect = PageF::getPageRedirect ( $wikiPage, 'title' );
		if ( !is_null( $redirect ) ) {
			$this->data['page_redirect'] = true;
			$this->data['page_redirect_title'] = $redirect->getFullText();
			$this->data['page_redirect_url'] = $redirect->getFullURL();
		} else {
			$this->data['page_redirect'] = false;
		}

		$template = PageF::getPageTemplateInfos( $wikiPage, 'Personne', [ 'Nom', 'Prénom', 'E-mail' ] );
		if ( !is_null( $template ) ) {
			$this->data['page_template'] = true;
			$this->data['page_template_nom'] = $template['Nom'];
			$this->data['page_template_prenom'] = $template['Prénom'];
			$this->data['page_template_email'] = $template['E-mail'];
		} else {
			$this->data['page_template'] = false;
		}

		return true;
	}

	/**
	 * @return MGWStatus
	 */
	private function update_page_template_info( $field, $value, $summary ) {

		if ( !$this->mw_exists ) {
			return Status::newFailed( 'Utilisateur inexistant' );
		}
		if ( !$this->page_exists ) {
			return Status::newFailed( 'Page utilisateur inexistante' );
		}
		if ( !$this->data['page_template'] ) {
			return Status::newFailed( 'Modèle {{Personne}} inexistant' );
		}

		$userpage = PageF::getPageFromTitleText( $this->data['user_name'], NS_USER, true );
		$oldtext = $userpage->getContent()->getNativeData();
		$regexp = '/(\|' . $field . '=)([^\|\n]+)/';
		if ( preg_match( $regexp, $oldtext ) < 1 ) {
			return Status::newFailed( 'Champs "' . $field . '" inexistant.' );
		}
		$newtext = preg_replace( $regexp, '$1' . $value , $oldtext );
		if ( $newtext != $oldtext ) {
			try {
				$edit_summary = $summary;
				$newcontent = new WikitextContent( $newtext );
				$userpage->doEditContent( $newcontent, $edit_summary, EDIT_AUTOSUMMARY );
				return Status::newDone( 'Champs "' . $field . '" mis à jour avec succès.' );
			}
			catch (\Exception $e) {
				return Status::newFailed( $e );
			}
		}
		return Status::newFailed( 'Champs "' . $field . '" inchangé.' );
	}
}
