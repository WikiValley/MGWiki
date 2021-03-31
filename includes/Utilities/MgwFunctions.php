<?php
namespace MediaWiki\Extension\MGWiki\Utilities;
use MediaWiki\Extension\MGWiki\Foreign\MGWSemanticMediaWiki as SmwF;
use MediaWiki\Extension\MGWiki\Utilities\MGWStatus as Status;
use MediaWiki\Extension\MGWiki\Utilities\PhpFunctions as PhpF;
use MediaWiki\Extension\MGWiki\Utilities\PagesFunctions as PageF;

/**
 * Fonctions statiques génériques au fonctionnement de MGWiki
 */
class MgwFunctions {

  /**
   * est-ce que l'utilisateur est référent de $targetUser ?
   * @param User $actionUser
   * @param User $targetUser
   * @return bool
   */
  public static function is_user_referent( $actionUser, $targetUser ){
    $field = wfMgwConfig('smw-properties', 'user' )['referent'];
    $data = PageF::getPageTemplateInfos ( $targetUser->getUserPage(), 'Personne', [ 'referent' => $field ] );
    //$complete = false;
    //$data = SmwF::collectSemanticData( [ $field ], $targetUser->getUserPage(), $complete ); // -> RESULTATS INCONSTANTS !!!??
    return ( $data && isset( $data[0]['referent'] ) && $data[0]['referent'] == $actionUser->getName() );
  }

  /**
   * est-ce que l'utilisateur est responsable de l'institution de rattachement de $targetUser ?
   * @param User $actionUser
   * @param User $targetUser
   * @return bool
   */
  public static function is_user_instit_referent( $actionUser, $targetUser ){
    $field = wfMgwConfig('smw-properties', 'user' )['institution'];
    $data = PageF::getPageTemplateInfos ( $targetUser->getUserPage(), 'Personne', [ 'institution' => $field ] );
    return ( $data && isset( $data[0]['institution'] ) && self::is_instit_referent( $actionUser, $data[0]['institution'] ) );
  }

  /**
   * est-ce que l'utilisateur est responsable de l'institution $targetInstit ?
   * @param User $actionUser
   * @param User $targetUser
   * @return bool
   */
  public static function is_instit_referent( $actionUser, $targetInstit ){
    $field = wfMgwConfig('smw-properties', 'institution' )['referent'];
    $title = \Title::newFromText( $targetInstit, NS_PROJECT );
    $data = PageF::getPageTemplateInfos ( $title, 'Institution', [ 'referent' => $field ] );
    return ( $data && isset( $data[0]['referent'] ) && $data[0]['referent'] == $actionUser->getName() );
  }

  /**
   * est-ce que l'utilisateur est responsable du groupe ?
   * @param User $actionUser
   * @param string|Title $target
   * @return bool
   */
  public static function is_groupe_referent( $actionUser, $targetGroupe ) {
    if ( !( $targetGroupe instanceof \Title ) )
      $targetGroupe = \Title::newFromText( $targetGroupe, NS_GROUP );
    $field = wfMgwConfig('smw-properties', 'groupe' )['referent'];
    $data = PageF::getPageTemplateInfos ( $targetGroupe, 'Groupe', [ 'referent' => $field ] );
    //$complete = false;
    //$data = SmwF::collectSemanticData( [ $field ], $target, $complete ); // -> RESULTATS INCONSTANTS !!!??
    return ( $data && isset( $data[0]['referent'] ) && $data[0]['referent'] == $actionUser->getName() );
  }

  /**
   * @param string $groupe_name
   * @param bool $archive
   * @return MGWStatus
   */
  public static function archive_groupe( $groupe_name, $archive ) {
    global $wgUser;
    $page = \Wikipage::factory(\Title::newFromText( $groupe_name, NS_GROUP ) );
    if ( $page->getId() < 1 ) {
      return Status::newFailed( wfMessage('mgw-groupe-unknown', $groupe_name )->text() );
    }

    $value = ( $archive ) ? 'Oui':'Non';
    $content = PageF::updatePageTemplateInfos ( $page, 'Groupe', [ 'Archivé' => $value ], false, true, true );

    if (!$content && $archive ) {
      return Status::newDone( wfMessage('mgw-groupe-do-archive-already', $groupe_name )->text() );
    }
    if ( !$content && !$archive ) {
      return Status::newDone( wfMessage('mgw-groupe-do-unarchive-already', $groupe_name )->text() );
    }

    $summary = ( $archive ) ? 'mgw-groupe-do-archive-summary' : 'mgw-groupe-do-archive-summary';
    return PageF::edit(
      $page,
      wfMessage( $summary )->text(),
      $wgUser,
      $content
    );
  }

	public static function sanitize_prenom( $prenom ) {
		$prenom = explode( '-', $prenom );
		foreach ( $prenom as $key => $sub ) {
			$prenom[$key] = ucfirst( strtolower($sub) );
		}
		$prenom = implode( '-', $prenom );

    $prenom = explode( ' ', $prenom );
    foreach ( $prenom as $key => $sub ) {
      $prenom[$key] = ucfirst( $sub );
    }
    $prenom = implode( ' ', $prenom );

    return $prenom;
	}

  // duration
  public static function duration_read( int $int, bool $tmstp = false ) {
    if ( !$tmstp ) {
			switch ( $int ) {
				case 0:
					return 'indéfini';
					break;
				case 1:
					return '6 mois';
					break;
				case 2:
					return '1 an';
					break;
				default:
					return ( $int / 2 ) . ' ans';
					break;
			}
    }
    if ($int == 0) return null;
    if ($int == 1) return '00000100000000';
    return '000'.( $int / 2 ).'0000000000';
  }

  // correction du timestamp si celui du système est décalé
  public static function timestampAdjust( $timestamp ) {
    return $timestamp - 10000;
  }

  /**
   * Affichage d'information avant redirection
   * après traitement d'un 'submit'
   * @param string $text     message d'information
   * @param string $type     'html'|'wikitext'
   * @param string $label    bouton de validation
   * @param string $href     redirection customisée (optionnel)
   * @return void
   */
  public static function afterSubmitInfo( $text, $type, $label, $href = '' ) {

    $context = \RequestContext::getMain();
    $out = $context->getOutput();
    $out->enableOOUI();
    $out->setPageTitle('Information');

    # on récupère la redirection prévue / on ré-oriente
    if ( !$href ) $href = $out->getRedirect();

    # on annule la redirection prévue
    $out->redirect('');

    # on ajoute le message d'info
    if ( $type = 'wikitext' ) {
      $out->addWikiText( $text );
    }
    else {
      $out->addHTML( $text );
    }

    $out->addHTML( '<br>' . new \OOUI\ButtonWidget( [
        'href' => $href,
        'label' => $label,
        'flags' => ['progressive']
      ] ) );
  }
}
