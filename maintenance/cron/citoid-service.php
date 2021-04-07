<?php

  /**
   * Les services citoid et zotero sont instables:
   * automatisation du redémarrage via une tâche cron quotidienne
   *
   * Usage:
   *  sudo php citoid-service.php --status (-l) (état des services citoid et zotero)
   *  sudo php citoid-service.php --run (-r) (démarre les services citoid et zotero)
   *  sudo php citoid-service.php --stop (-s) (arrêt des services citoid et zotero)
   *  sudo php citoid-service.php --help (-h) (arrêt des services citoid et zotero)
   *  option: --quiet (-q)
   *
   * Cron: tâche planifiée toutes les 6h
   * /etc/crontab (tâches exécutées par root)
   */

  $options = ['status', 'run', 'stop', 'quiet', 'help'];
  $getOpt = getOpt( 'lrsqh', $options );
  $services = [ "zotero", "citoid" ];
  $wgEmergencyContact = "mgwiki@localhost";

  $quiet = ( array_key_exists( 'q', $getOpt ) || array_key_exists( 'quiet', $getOpt ) );
  $mess = '';


	function shell( $cmd, &$console_out, $string = true ) {
		$console_out = [];
		$result_code = 2;
		exec( $cmd, $console_out, $result_code );
		if ( $string ) $console_out = implode( "\n", $console_out );
		return $result_code;
	}

  function is_active( $service ) {
    $cmd = "systemctl status $service.service";
    $console_out = '';
    $shell = shell($cmd, $console_out, false);
    foreach ( $console_out as $line ) {
      $line = trim( $line );
      if ( preg_match('/^(Active: )([^\s]+)/', $line, $matches )
          && $matches[2] == 'active' ) {
        return true;
      }
    }
    return false;
  }

  function start_service( $service ) {
    $cmd = "systemctl start $service.service";
    $console_out = '';
    $shell = shell( $cmd, $console_out );
    return ( $shell ) ? false : true;
  }

  function stop_service( $service ) {
    $cmd = "systemctl stop $service.service";
    $console_out = '';
    $shell = shell( $cmd, $console_out, false );
    return ( $shell ) ? false : true;
  }

  /**
   * Actions ...
   */
  if ( array_key_exists( 'l', $getOpt ) || array_key_exists( 'status', $getOpt ) ) {
    foreach( $services as $service ) {
      if ( is_active( $service ) )
        $mess .= "$service: actif\n";
      else $mess .= "$service: inactif\n";
    }
  }

  elseif ( array_key_exists( 'r', $getOpt ) || array_key_exists( 'run', $getOpt ) ) {
    foreach( $services as $service ) {
      if ( ! is_active( $service ) ) {
        start_service( $service );
        if ( ! is_active( $service ) ) {
          $add = "'systemctl start $service.service' : echec\n";
          // debug mail !
          mail( $wgEmergencyContact, 'MGWiki CRON: citoïd-services erreur', $add );
          $mess .= $add;
        }
        else {
          $mess .= "$service : actif\n";
        }
      }
      else $mess .= "$service : actif\n";
    }
  }
  elseif ( array_key_exists( 's', $getOpt ) || array_key_exists( 'stop', $getOpt ) ) {
    foreach( $services as $service ) {
      if ( is_active( $service ) ) {
        stop_service( $service );
        $mess .= "$service : inactif\n";
      }
      else $mess .= "$service : inactif\n";
    }
  }
  else
    echo "\nUsage:\n" .
    "*  sudo php citoid-service.php --status (-l) (état des services citoid et zotero)\n" .
    "*  sudo php citoid-service.php --run (-r) (démarre les services citoid et zotero)\n" .
    "*  sudo php citoid-service.php --stop (-s) (arrêt des services citoid et zotero)\n" .
    "*  sudo php citoid-service.php --help (-h) (arrêt des services citoid et zotero)\n\n";
  if ( !$quiet ) {
    echo $mess;
  }
?>
