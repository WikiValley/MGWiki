<?php
/**
 * Fichier de maintenance pour vérifier les Hooks utilisés par MGWiki lors des mises à jour de MediaWiki.
 * Les Hooks spécifiques à MGWiki sont automatiquement insérés (si l'accroche du code n'a pas changé).
 * (doit être exécuté avec les droits d'écriture)
 *
 * Fonctions appelées par MgwUpdater::do_check_hooks
 */

trait MgwCheckHooks {

  private function checkHooks() {
    global $IP;
    global $MGW_IP;
    $info = json_decode( file_get_contents( $MGW_IP . '/extension.json' ), true );
    $customHooks = $this->config('custom-hooks');

    $report = fopen('maintenance-report.txt', 'a');
    fputs( $report, "\n
--------------------------------
CheckHooks - " . date('Y-m-d H:i:s') . "
--------------------------------\n" );

    foreach ( $info['Hooks'] as $key => $value ) {
      $shell =  ' && stdbuf -oL grep -r -n -E --exclude-dir=MGWiki "Hooks::run(WithoutAbort)?\( \'' . $key . '\'" | head -n1';
      $grep = 2;

      # on vérifie la présence des Hooks dans les fichiers du programme
      foreach ( $this->config('screen-hooks') as $dir ) {
        $grep = shell_exec( 'cd ' . $IP . '/' .$dir . $shell );
        if ( $grep ) break;
      }
      # si absent: on recherche l'existence d'un customHook, on l'insère si nécessaire
      if ( !$grep ) {
         if ( array_key_exists( $key, $customHooks ) ) {
           $add = $this->addHook( $customHooks[$key] );
           fputs($report, $key . $add . "\n");
           echo $key . $add . "\n";
         }
         else {
           fputs($report, $key . " : ECHEC\n");
           echo $key . " : ECHEC\n";
         }
      }
      else {
        fputs($report, $key . " : OK\n");
        echo $key . " : OK\n" ;
      }
    }
return '';

    fclose( $report );
    return "
==================================
=> voir: maintenance-report.txt <=
==================================\n";
  }

  # insère le hook customisé dans MediaWiki-core
  # @param array $hook
  # @return bool
  private function addHook( $hook ) {
    global $IP;
    $grep = shell_exec( 'cd ' . $IP . ' && grep -r -n -E "' . $hook[ 'fileIdentifier' ] . '"' );
    if ( is_null( $grep ) ) {
      $mess = ' : ECHEC A L\'INSERTION (hook customisé)  : occurence "' . $hook[ 'fileIdentifier' ] . '" introuvable dans les fichiers';
    }
    else {
      $grep = explode( ':', $grep );
      $file = $IP . '/' . $grep[0];
      $code = file_get_contents( $file );
      $ret = substr_count( $code, $hook['stringIdentifier'] );
      switch ( $ret ) {

        case 0:
          $mess = ' : ECHEC A L\'INSERTION (hook customisé) : '. $file .' : "' . $hook[ 'stringIdentifier' ] . '" introuvable';
          break;

        case 1:  // ajout du code
          $code = str_replace( $hook['stringIdentifier'], $hook['stringIdentifier'] . $hook['customCode'], $code );
          file_put_contents( $file, $code );
          $mess = ' : OK (customized)';
          break;

        default:
          $mess = ' : ECHEC A L\'INSERTION (hook customisé) : '. $file .' : "' . $hook[ 'stringIdentifier' ] . '" se trouve plusieurs fois.
    "' . $hook['customCode'] . '" doit être ajouté manuellement.';
          break;
      }
    }
    return $mess;
  }
}
