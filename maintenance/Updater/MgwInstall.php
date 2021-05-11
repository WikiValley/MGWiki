<?php

/**
 * Fonctions appelées par MgwUpdater::do_install
 */

trait MgwInstall {

  private function install_mediawiki( $version ) {

    global $IP;
    $rel = 'REL' . str_replace('.','_',$version);
    $MWpath = '/var/www/html/' . $version;

    //on remplace le fichier de config par celui de la version demandée
    global $wgMgwGitRawUrl;
    // temporaire...
    if ( !isset($wgMgwGitRawUrl) ) $wgMgwGitRawUrl = 'https://raw.githubusercontent.com/WikiValley/MGWiki';
    $this->config = json_decode( wget("$wgMgwGitRawUrl/$rel/config/maintenance.json"), true );

    # 1. mediawiki
    echo "\nMEDIAWIKI...\n\n";
    if ( !file_exists( $MWpath ) ) {
      $tar_url = $this->config['mediawiki'];
      $temp = explode('/', $tar_url );
      $dir = str_replace( '.tar.gz', '', $temp[array_key_last($temp)] );
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
    if ( !isset( $this->config ) ) {
      echo "La liste des extensions n'est pas configurée pour cette version de mediawiki\n.";
      return "Abandon\n";
    }

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

    # 4. Hooks
    $this->do_check_hooks( $MWpath );

    # 5. scripts
    echo "\nSCRIPTS... \n\n";
    foreach ( $this->config['maintenance-scripts'] as $num => $script ){
      $cmd = "cd $MWpath/{$script['dir']} && {$script['cmd']}";
        echo $cmd . "... \n";
      if ( $this->shell_dry( $cmd ) ) {
        echo "... ECHEC. Veuillez le faire manuellement.\n\n";
      }
      else {
        echo "... OK\n\n";
      }

    }

    return "\n\n...fin de l'installation. \nMediawiki $version est installé dans le répertoire $MWpath\n";
  }
}
