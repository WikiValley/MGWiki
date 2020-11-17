<?php

namespace MediaWiki\Extension\MGWikiDev;

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
}
