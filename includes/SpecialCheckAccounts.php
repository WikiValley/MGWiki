<?php

namespace MediaWiki\Extension\MGWikiDev;

/**
 * Page spéciale demande de création de compte
 * Accessible du public (whitelisted)
 */
class SpecialCheckAccounts extends \SpecialPage {

	/**
	 * Initialize the special page.
	 */
	public function __construct()
  {
		parent::__construct( 'SpecialCheckAccounts' );

  }

	/**
	 * Shows the page to the user.
	 * @param string $sub The subpage string argument (if any).
	 */
	public function execute( $sub )
  {
		/* Construction de la page */
		$out = $this->getOutput();
		$out->addModules('ext.mgwiki-jsonform');
		$out->addModules('ext.mgwiki-specialaccountrequest');
		$out->setPageTitle( $this->getMsg('specialaccountrequest-title') );
  }

	/**
	 * groupe auquel se rapporte cette page spéciale
	 */
	protected function getGroupName() {
		return 'mgwiki';
	}
}
