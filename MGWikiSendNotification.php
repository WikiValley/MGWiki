<?php
/**
 * MGWiki - add a button to request a review of the current page to the referrer of the student.
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWiki
 */

declare(strict_types=1);

class MGWikiSendNotification {

	/**
	 * Include the module ext.mgwiki.send-notification.
	 *
	 * Only on articles when viewing the current revision
	 */
	public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
		$context = $out->getContext();
		$action = \Action::getActionName( $context );
		if( $out->isArticle() && $action === 'view' && $out->isRevisionCurrent() && $context->getRequest()->getVal( 'oldid' ) === null ) {
			$out->addModules( 'ext.mgwiki.send-notification' );
		}
	}

	/**
	 * Add the link in the toolbox to send a notification to the referrer.
	 *
	 * Only on articles when viewing the current revision
	 */
	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ) {
		$out = $skin->getOutput();
		$context = $out->getContext();
		$action = \Action::getActionName( $context );
		if( $out->isArticle() && $action === 'view' && $out->isRevisionCurrent() && $context->getRequest()->getVal( 'oldid' ) === null ) {
			$sidebar['TOOLBOX'][] = [
				'msg' => 'mgwiki-toolbox-link',
				'href' => '#',
				'class' => 'mgwiki-send-notification',
			];
		}
	}
}
