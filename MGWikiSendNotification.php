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
		$title = $out->getTitle();
		$action = \Action::getActionName( $context );
		$revisionId = $out->getRevisionId();
		if( $out->isArticle() && $title->getNamespace() === 0 && $action === 'view' && ( $revisionId == 0 || $revisionId == $out->getTitle()->getLatestRevID() ) && $context->getRequest()->getVal( 'oldid' ) === null ) {
		#if( $out->isArticle() && $action === 'view' && $out->isRevisionCurrent() && $context->getRequest()->getVal( 'oldid' ) === null ) { // For 1.35+
			$out->addModules( 'ext.mgwiki.send-notification' );
		}
	}

	/**
	 * Add the link in the toolbox to send a notification to the referrer.
	 *
	 * Only on articles when viewing the current revision
	 *
	 * This hook is for 1.35+.
	 */
	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ) {
		$out = $skin->getOutput();
		$context = $out->getContext();
		$title = $out->getTitle();
		$action = \Action::getActionName( $context );
		if( $out->isArticle() && $title->getNamespace() === 0 && $action === 'view' && $out->isRevisionCurrent() && $context->getRequest()->getVal( 'oldid' ) === null ) {
			$sidebar['TOOLBOX'][] = [
				'msg' => 'mgwiki-toolbox-link',
				'href' => '#',
				'class' => 'mgwiki-send-notification',
			];
		}
	}

	/**
	 * Add the link in the toolbox to send a notification to the referrer.
	 *
	 * Only on articles when viewing the current revision
	 *
	 * This hook is for 1.34-.
	 */
	public static function onBaseTemplateToolbox( $template, &$sidebar ) {
		$skin = $template->getSkin();
		$out = $skin->getOutput();
		$context = $out->getContext();
		$action = \Action::getActionName( $context );
		$revisionId = $out->getRevisionId();
		if( $out->isArticle() && $action === 'view' && ( $revisionId == 0 || $revisionId == $out->getTitle()->getLatestRevID() ) && $context->getRequest()->getVal( 'oldid' ) === null ) {
			$sidebar['mgwiki-send-notification'] = [
				'msg' => 'mgwiki-toolbox-link',
				'href' => '#',
				'class' => 'mgwiki-send-notification',
			];
		}
	}
}
