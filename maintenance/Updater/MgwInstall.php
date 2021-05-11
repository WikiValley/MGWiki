<?php

/**
 * Fonctions appelées par MgwUpdater::do_install
 */

trait MgwInstall {

  /**
   * Installation de MediaWiki [$version]
   * dans le répertoire /var/www/html/[$version]
   *
   * A compléter par $this->install_scripts() pour terminer l'installation
   * APRES AVOIR RENOMME LE REPERTOIRE /var/www/html/wiki
   */
  private function install_mediawiki( $version ) {

    global $IP;
    global $MGW_IP;
    $rel = 'REL' . str_replace('.','_',$version);
    $MWpath = '/var/www/html/' . $version;

    // on remplace le fichier de config par celui de la version demandée
    global $wgMgwGitRawUrl;
    // temporaire...
    if ( !isset($wgMgwGitRawUrl) ) $wgMgwGitRawUrl = 'https://raw.githubusercontent.com/WikiValley/MGWiki';
    $this->config = json_decode( file_get_contents("$wgMgwGitRawUrl/$rel/config/maintenance.json"), true );
    if ( !$this->config ) {
      return "Echec au téléchargement du fichier de configuration pour la version $version";
    }

    // si le site est actif, on propose de le mettre en maintenance
    $localSettings = file_get_contents( $IP . '/LocalSettings.php' );
    $localSettings = explode( "\n", $localSettings );
    $running = true;
    foreach( $localSettings as $line ) {
      if ( preg_match('/^header\("Location: \$wgServer"\)/', $line ) ) {
        $running = false;
        break;
      }
    }
    if ( $running ) {
      echo "Ce script ne peut continuer car MGWiki est en cours de production.\n" .
        " Souhaitez-vous le mettre à l'arrêt [o/n] ?";
      if ( $this->readconsole('> ') != 'o' ) {
        return "\n...installation annulée.\n";
      }
      $getOpt = [ 's' => 'stop' ];
      include ( $MGW_IP . '/maintenance/site-stop.php');
      echo "\n";
    }

    // on propose de réaliser une sauvegarde:
    echo "Souhaitez-vous effectuer une sauvegarde de l'état actuel de " .
      "la bdd et des fichiers [o/n] ?\n".
      "NB: l'existence d'une sauvegarde est indispensable au bon déroulement de l'installation.\n";
    if ( $this->readconsole('> ') == 'o' ) {
      if ( $this->do_backup( "save" ) == 'annulation' ) {
        return "Echec de la sauvegarde, annulation.\n";
      }
    }

    # 1. mediawiki
    echo "\nMEDIAWIKI...\n\n";
    if ( !file_exists( $MWpath ) ) {
      $tar_url = $this->config['mediawiki'];
      $temp = explode('/', $tar_url );
      $key = count($temp) -1;
      $tar = $temp[$key];
      $dir = str_replace( '.tar.gz', '', $tar );
      $cmd = "cd /var/www/html && wget $tar_url && tar -xzf $tar && rm $tar && mv $dir $version";
      if ( $this->shell_dry( $cmd ) ) {
        echo "échec au téléchargement de mediawiki. Veuillez le faire manuellement avant de continuer.\n\n";
        return "abandon\n";
      }
    }
    echo "\nSKINS...\n\n";
    if ( isset( $this->config['skin'] ) ) {
      // installation de skin
      if ( file_exists( "$MWpath/skins/".$this->config['skin']['name'] ) ) {
        $skin = $this->config['skin'];
        $temp = explode('/', $skin['url']);
        $tar = $temp[array_key_last($temp)];
        $cmd = "cd $MWpath/skins && rm -rf {$skin['name']}" .
          " && wget {$skin['url']} && tar -xzf $tar && rm $tar";
        if ( $this->shell_dry( $cmd ) ) {
          echo "échec à l'installation de {$skin['name']} ($cmd). Veuillez le faire manuellement avant de continuer.\n\n";
          return "abandon\n";
        }
      }
    }

    # 2. extensions
    echo "\nEXTENSIONS...\n\n";

      ## composer
    $composer = [ "require" => $this->config['composer'] ];
    file_put_contents( "$MWpath/composer.local.json", json_encode( $composer ) );

    $cmd = "cd $MWpath && composer update --no-dev";
    if ( $this->shell_dry( $cmd ) ) {
      echo "échec à la mise à jour des extensions via composer ($cmd).\n Veuillez le faire manuellement.\n\n";
    }

      ## git
    echo "\nGit clone...\n\n";
    foreach ( $this->config['git'] as $ext => $info ) {
      echo "$ext ... \n";
      // on supprime les répertoires vides
      if ( file_exists( "$MWpath/extensions/$ext" ) && count( glob("$MWpath/extensions/$ext/*") ) === 0 ) {
        $this->shell_dry( "cd $MWpath/extensions && rm -rf $ext/" );
      }
      // git clone
      if ( ! file_exists( "$MWpath/extensions/$ext" ) ) {
        $cmd = "cd $MWpath/extensions && git clone -b {$info['branch']} {$info['url']}";
        if ( $this->shell_dry( $cmd ) ) {
          echo "$url : échec.\n Veuillez faire l'installation manuellement.\n\n";
        }
        else echo "\n...ok\n\n";
      }
      else echo "\n...ok\n\n";
    }

    # 3. restauration des données + correctifs de version
    echo "\nDONNEES... \n\n";
    echo $this->do_backup( 'restore', $version );
    echo "\nMise à jour de LocalSettings.php ... \n\n";
    if ( isset( $this->config['localsettings'] ) ) {
      $ls_content = file_get_contents( "$MWpath/LocalSettings.php" );
      foreach ( $this->config['localsettings'] as $str => $rpl ) {
        $ls_content = str_replace( $str, $rpl, $ls_content );
      }
      file_put_contents( "$MWpath/LocalSettings.php", $ls_content );
    }
    echo "... OK\n\n";

    return "\n\nMediawiki $version est installé dans le répertoire $MWpath\n" .
      "Veuillez terminer l'installation avec 'php mgw-updater.php install_scripts'\n" .
      "après avoir déplacé le répertoire vers '/var/www/html/wiki/'.";
  }


  /**
   * Scripts pour achever l'installation de mediawiki
   */
  private function install_scripts() {

    global $MGW_IP;

    # 1. Hooks
    $this->do_check_hooks();

    # 2. scripts
    echo "\nSCRIPTS... \n\n";
    foreach ( $this->config['maintenance-scripts'] as $num => $script ) {
      $cmd = "cd $MWpath/{$script['dir']} && {$script['cmd']}";
        echo $cmd . "... \n";
      if ( $this->shell_dry( $cmd ) ) {
        echo "... ECHEC. Veuillez le faire manuellement.\n\n";
      }
      else {
        echo "... OK\n\n";
      }
    }

    # 3. refreshPages
    if ( file_get_contents( $MGW_IP . "/maintenance/Refreshpages/.refreshpages.txt") ) {
      $this->refresh_pages();
    }
    if ( file_get_contents( $MGW_IP . "/maintenance/Refreshpages/.refreshpages.txt") ) {
      $this->rename_pages();
    }

    # 4. remise en production
    $getOpt = [ 'r' => 'run' ];
    include ( $MGW_IP . '/maintenance/site-stop.php');

    return "Installation de MediaWiki terminée.";
  }
}
