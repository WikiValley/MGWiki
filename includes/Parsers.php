<?php

namespace MediaWiki\Extension\MGWikiDev;

use MediaWiki\Extension\MGWikiDev\Utilities\HtmlFunctions as HtmlF;
use MediaWiki\Extension\MGWikiDev\Utilities\MgwDataFunctions as DbF;

class Parsers {

   // Render the output of {{#mgw-onclick:target|tag|include|blank=false}}.
   public static function onclickSpan( $parser, $target = '', $tag = 'span', $include = '', $blank = false ) {

      // The input parameters are wikitext with templates expanded.
      // The output should be wikitext too.
      if (!$blank) {
        $target = 'window.location.href=\'' . $target . '\'';
      }
      else {
        $target = 'window.open(\'' . $target . '\')';
      }

      $output = '<' . $tag . ' style="cursor: pointer;" onclick="'. $target .'">' . $include . '</' . $tag . '>';

      if ($target == ''){
        $output = $include;
      }

      return [ $output, 'isHTML' => true ];//, 'noparse' => true,
   }

   // retrieve data from mgw_groupe_type table
   // must be called from a' MGWiki:Types_de_groupes/<Sub>' subpage
   public static function mgw_groupe_type( $parser, $field = '' ) {

     $title = $parser->getTitle();
     $fields = [ 'admin_level', 'user_level', 'default_duration' ];

     if ( $title->getNamespace() != NS_PROJECT ||
          preg_match( '/Types de groupes\/(.*)$/', $title->getText() ) < 1 )
     {
        $output = HtmlF::parseError('La balise {{#mgw-groupe-type:[arg]}} doit être employée sur une sous-page '.
          '"MGWiki:Types_de_groupes/..."' );
        return [ $output, 'noparse' => true, 'isHTML' => true ];
     }

     if ( empty( $field ) ) {
        $output = HtmlF::parseError('La balise {{#mgw-groupe-type:[arg]}} doit comporter un argument' );
        return [ $output, 'noparse' => true, 'isHTML' => true ];
     }

     if ( !in_array( $field, $fields ) ) {
        $output = HtmlF::parseError('{{#mgw-groupe-type:[arg]}} : mauvais argument => "admin_level" | "user_level" | "default_duration"' );
        return [ $output, 'noparse' => true, 'isHTML' => true ];
     }

     $screen = DbF::select_clean( 'groupe_type', ['page_id', $field ], 'page_id = ' . $title->getArticleID() );
     if ( is_null( $screen ) ) {
        $output = HtmlF::parseError('{{#mgw-groupe-type:' . $field .
          '}} : cette page n\'est pas connue dans la table mgw_groupe_type.' );
        return [ $output, 'noparse' => true, 'isHTML' => true ];
     }
     else {
       return $screen[0][$field];
     }
   }

    // retrieve data from mgw_groupe_type table
    // must be called from a' MGWiki:Types_de_groupes/<Sub>' subpage
    public static function mgw_display( $parser, $value = '', $field = '' ) {

      $fields = [ 'level', 'duration' ];

      if ( $value === '' ) {
         $output = HtmlF::parseError('{{#mgw-display:[value]|[field]}} : [value] absent.' );
         return [ $output, 'noparse' => true, 'isHTML' => true ];
      }

      if ( !in_array( $field, $fields ) ) {
         $output = HtmlF::parseError('{{#mgw-display:[value]|[field]}} : Mauvais argument '.
          '[field] => "'. implode('" | "', $fields) .'"' );
         return [ $output, 'noparse' => true, 'isHTML' => true ];
      }

      switch ( $field ) {
        case 'level':
          global $wgMgwLevels;
          return $wgMgwLevels[ (int)$value ];
          break;
        case 'duration':
          return wfMgwDuration( (int)$value );
          break;
      }
    }
}
