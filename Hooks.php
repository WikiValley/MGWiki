<?php

use MediaWiki\Extension\MGWikiDev\Parsers;

/**
 * MGWiki - development version
 * General functions and hooks.
 *
 * @author Sébastien Beyou <seb35@seb35.fr>
 * @author Alexandre Brulet
 * @license GPL-3.0+
 * @package MediaWiki-extension-MGWikiDev
 */
class MGWikiDevHooks {

	/**
	 * Chargement du module MGWikiDev
	 */
  public static function onBeforePageDisplay( OutputPage $out, Skin $skin ) {
    //Modules pour toutes les pages
    $out->addModules('ext.mgwiki-dev');
  }

	/**
   * ! CUSTOM HOOK ! (cf readme -> ApiMain.php changes)
	 * Autoriser l'API getjson quelque soit l'utilisateur
	 */
  public static function onApiAllow( $module, $user )
  {
    global $wgRequest;
    if ( $wgRequest->getText( 'action' ) == 'getjson' )
    {
      return true;
    }
    return false;
  }

   // Register any render callbacks with the parser
   public static function onParserFirstCallInit( Parser $parser ) {

      // Create a function hook associating the "example" magic word with renderExample()
      $parser->setFunctionHook( 'mgw-onclick', [ Parsers::class, 'onclickSpan' ] );
   }

 	/**
 	 * Hook: LoadExtensionSchemaUpdates
   *
   * fonction exécutée par maintenance/Update.php
   *
 	 * @param DatabaseUpdater $updater
 	 */
 	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {

 		$dir = __DIR__ . '/sql';
    $tables = array(
      'utilisateurs',
      'archive_utilisateurs',
      'institutions',
      'archive_institutions',
      'groupes',
      'archive_groupes',
      'groupes_membres',
      'archive_groupes_membres'
    );

    foreach( $tables as $key => $table ) {
      $tableSQLFile = "$dir/addTable-" . $table . ".sql";
      $indexSQLfile = "$dir/addIndex-" . $table . "_lookup.sql";
      $updater->addExtensionTable( 'mgw_' . $table, $baseSQLFile );
      if ( file_exists($indexSQLfile) ) {
        $updater->addExtensionIndex( $table, $table.'_lookup', $indexSQLfile );
      }
    }

 		// $updater->addExtensionTable( 'mgw_utilisateurs', $baseSQLFile );
    // malgré le premier argument toutes les tables et les index sont insérés.

    /* fonctions disponibles:
    $updater->addExtensionField( 'flow_revision', 'rev_last_edit_id', "$dir/db_patches/patch-revision_last_editor.sql" );
    $updater->addExtensionIndex( 'flow_workflow', 'flow_workflow_lookup', "$dir/db_patches/patch-workflow_lookup_idx.sql" );

 		if ( $updater->getDB()->getType() === 'sqlite' ) {
 			$updater->modifyExtensionField( 'flow_summary_revision', 'summary_workflow_id',
 				"$dir/db_patches/patch-summary2header.sqlite.sql" );
 		} else {
 			// renames columns, alternate patch is above for sqlite
 			$updater->modifyExtensionField( 'flow_summary_revision', 'summary_workflow_id',
 				"$dir/db_patches/patch-summary2header.sql" );
      }

 		$updater->dropExtensionTable( 'flow_definition',
 			"$dir/db_patches/patch-drop_definition.sql" );
 		$updater->dropExtensionField( 'flow_workflow', 'workflow_user_ip',
 			"$dir/db_patches/patch-drop_workflow_user.sql" );
 		$updater->dropExtensionIndex( 'flow_ext_ref', 'flow_ext_ref_pk',
 			"$dir/db_patches/patch-remove_unique_ref_indices.sql" );

 		require_once __DIR__ . '/maintenance/FlowUpdateRecentChanges.php';
 		$updater->addPostDatabaseUpdateMaintenance( FlowUpdateRecentChanges::class );

 		if ( $updater->updateRowExists( 'FlowSetUserIp' ) ) {
 			$updater->dropExtensionField( 'flow_revision', 'rev_user_text',
 				"$dir/db_patches/patch-remove_usernames_2.sql" );
 		}
    */
 	}
}
