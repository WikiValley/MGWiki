<?php
namespace MediaWiki\Extension\MGWiki\Modules\Json;

use MediaWiki\Extension\MGWiki\Modules\Json\JsonToForm;

/**
 * Page spéciale demande de création de compte
 * Accessible du public (whitelisted)
 */
class SpecialAccountRequest extends \SpecialPage {

	private $JsonForm;    // obj
	private $cookieData;  // array
	private $isPosted;    // bool

	/**
	 * Initialize the special page.
	 */
	public function __construct() {
		parent::__construct( 'SpecialAccountRequest' );

		# gestion du formulaire
		$postData = $this->getRequest()->getPostValues(); // récupération des variables POST si elles existent
		$this->isPosted = ( sizeof($postData) > 0 );
		$this->JsonForm = new JsonToForm(
			'specialaccountrequest',
			 $postData
		);

		# récupération des cookies
		self::getCookies();
	}

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub ) {

		$messHTML = "";
		$done = false;

		/* Traitement de la réponse au captcha */
		if ( $this->JsonForm->isCaptchaPosted() )
		{
			# demande déjà finalisée
	    if ( $this->cookieData['done'] )
			{
				$messHTML = $this->messageHTML( 'done', wfMessage('specialaccountrequest-mess-alreadydone')->plain() );
				$done = true;
			}
			# envoi du mail si réponse valide
			elseif ( $this->JsonForm->isCaptchaValid() )
			{
				$this->JsonForm->sendEmail();
					//Todo: use WebResponse::setCookie() when upgrade Mediawiki > 1.35
				setcookie( $this->cookieData['label'], "Account request sent.", time()+3600 );
				$messHTML = $this->messageHTML( 'done', wfMessage('specialaccountrequest-mess-confirm')->plain() );
				$done = true;
			}

			# captcha invalide, nombre d'essais dépassés
			elseif ( $this->cookieData['attempt'] > 4 ){
				$messHTML = $this->messageHTML( 'done', wfMessage('specialaccountrequest-mess-attemptslimit')->plain() );
				$done = true;
			}

			# captcha invalide, nouvel essai
			else
			{
				$this->cookieData['attempt'] += 1;
				$messHTML = $this->messageHTML( 'captchaError', wfMessage('specialaccountrequest-mess-captchaerror')->plain() );
				setcookie('mgw_accountRequestAttempt', $this->cookieData['attempt'], time()+60*60);
			}
		}

		/* Construction de la page */
		$out = $this->getOutput();
		$out->addModules('ext.mgwiki.jsonform');
		$out->addModules('ext.mgwiki-specialaccountrequest');
		$out->setPageTitle( wfMessage('specialaccountrequest-title')->plain() );

		if (!$done){
			$form = $this->JsonForm->makeForm( $messHTML );
			foreach ( $form as $outKey => $outValue ) {
				switch ( $outValue['type'] ) {
					case 'html':
						$out->addHTML( $outValue['content'] );
						break;
					case 'wiki':
						$out->addWikiTextAsContent( $outValue['content'] );
						break;
				}
			}
    }
		else {
			$out->addHTML($messHTML);
		}
  }

	protected function messageHTML($option, $mess)
	{
		switch ($option) {

			case 'done':
				return '<p id="mgw-specialaccountrequest-ok" >' . $mess . '</p>
								<button type="button" onclick="mw.mgwHome()" >' . wfMessage('specialaccountrequest-mess-returnbutton')->plain() . '</button>';
				break;

			case 'captchaError':
				return '<br><i style="color:red;">' . $mess . '</i>';
				break;

			default:
				throw new \Exception("Error: use of SpecialAccountRequest::messageHTML with invalid option argument", 1);
		}
	}

	/**
	 * récupération des cookies s'ils existent
	 */
	private function getCookies ()
	{
		global $_COOKIE; // NB: l'appel de $this->getRequest()->getCookie() provoque une erreur: Warning: require(): Could not call the sapi_header_callback in /var/www/html/wiki/extensions/MGWikiDev/includes/SpecialAccountRequest.php
		if ( $this->isPosted ){
			$this->cookieData['label'] = str_replace(array('=', ',' ,';' ,'\t' ,'\r' ,'\n' ,'\013' ,'\014'), '', "mgw_" . $this->JsonForm->getHash() );
			$this->cookieData['done'] = isset($_COOKIE[$this->cookieData['label']]);
			$this->cookieData['attempt'] = isset($_COOKIE['mgw_accountRequestAttempt']) ? $_COOKIE['mgw_accountRequestAttempt'] : 0;
			if ( isset( $this->cookieData[ 'attempt'] ) && !is_int( $this->cookieData[ 'attempt' ] ) ) { $this->cookieData[ 'attempt' ] = 5; }
		}
	}

	/**
	 * groupe auquel se rapporte cette page spéciale
	 */
	protected function getGroupName() {
		return 'mgwiki';
	}
}
