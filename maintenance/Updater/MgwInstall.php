<?php

/**
 * Fonctions appelées par MgwUpdater::do_install
 */

trait MgwInstall {

  private function install_mediawiki() {
    global $IP;
    $version = $this->mw_version;

    # 1. mediawiki
    $rel = 'REL' . str_replace('.','_',$version);
    if ( !file_exists('/var/www/html/' . $version ) ) {
      $tar_url = $this->config[$version]['mediawiki'];
      $tar = end( explode('/', $tar_url ) );
      $dir = str_replace( '.tar.gz', '', $tar );
      $cmd = "cd /var/www/html && wget $tar_url && tar -xzf $tar && rm $tar && mv $dir $version";
      if ( $this->shell_dry( $cmd ) ) {
        echo "échec au téléchargement de mediawiki. Veuillez le faire manuellement avant de continuer.\n\n";
        return "abandon\n";
      }
    }
    if ( isset( $this->config[$version]['skin'] ) ) {
      // installation de skin
      if ( file_exists( "/var/www/html/$version/skins/".$this->config[$version]['skin']['name'] ) ) {
        $skin = $this->config[$version]['skin']['name'];
        $tar_url = $this->config[$version]['skin']['url'];
        $tar = end( explode('/', $tar_url ) );
        $cmd = "cd /var/www/html/$version/skins && rm -rf $skin" .
          " && wget $tar_url && tar -xzf $tar && rm $tar";
        if ( $this->shell_dry( $cmd ) ) {
          echo "échec à l'installation de $skin ($cmd). Veuillez le faire manuellement avant de continuer.\n\n";
          return "abandon\n";
        }
      }
    }

    # 2. MGWiki
    $MWpath = "/var/www/html/$version";
    $MGWpath = "/var/www/html/$version/extensions/MGWiki";
    if ( !file_exists( $MGWpath ) ) {
      $cmd = "cd /var/www/html/$version/extensions && git clone https://github.com/WikiValley/MGWiki.git";
      if ( $this->shell_dry( $cmd ) ) {
        echo "échec au téléchargement de l'extension MGWiki. Veuillez le faire manuellement avant de continuer.\n\n";
        return "abandon\n";
      }
    }

    # 3. extensions
    if ( !isset( $this->config[$version] ) ) {
      echo "La liste des extensions n'est pas configurée pour cette version de mediawiki\n.";
      return "Abandon\n";
    }

      ## composer
    $composer = [ "require" => $this->config[$version]['composer'] ];
    file_put_contents( "$MWpath/composer.local.json", json_encode( $composer ) );

    $cmd = "cd $MWpath && composer update --no-dev";
    if ( $this->shell_dry( $cmd ) ) {
      echo "échec à la mise à jour des extensions via composer ($cmd).\n Veuillez le faire manuellement.\n\n";
    }

      ## git
    foreach ( $this->config[$version]['git'] as $ext => $info ) {
      echo "$ext ... \n";
      if ( file_exists( "$MWpath/extensions/$ext" ) && count( glob("$MWpath/extensions/$ext/*") ) === 0 ) {
        $this->shell_dry( "cd $MWpath/extensions && rm -rf $ext/" );
      }
      if ( ! file_exists( "$MWpath/extensions/$ext" ) ) {
        $cmd = "cd $MWpath/extensions && git clone -b {$info['branch']} {$info['url']}";
        if ( $this->shell_dry( $cmd ) ) {
          echo "$url : échec.\n Veuillez faire l'installation manuellement.\n\n";
        }
        else echo "\n...ok\n\n";
      }
      else echo "\n...ok\n\n";
    }

    # 4. restauration des données + correctifs de version
    echo "\nRéimplémentation des données depuis la sauvegarde... \n\n";
    echo $this->do_backup( 'restore', $version );
    echo "\nMise à jour de LocalSettings.php ... \n\n";
    if ( isset( $this->config[$version]['localsettings'] ) ) {
      $ls_content = file_get_contents( "$MWpath/LocalSettings.php" );
      foreach ( $this->config[$version]['localsettings'] as $str => $rpl ) {
        $ls_content = str_replace( $str, $rpl, $ls_content );
      }
      file_put_contents( "$MWpath/LocalSettings.php", $ls_content );
    }
    echo "... OK\n\n";

    # 5. droits d'écriture
    echo "\nParamétrage des droits d'écriture ... \n\n";
    $cmd = "chown -R www-data:www-data $MWpath/cache";
    if ( $this->shell_dry( $cmd ) ) {
      echo "échec à l'ouverture des droits d'écriture du cache ($cmd).\n Veuillez le faire manuellement.\n\n";
    }
    else {
      echo "... OK\n\n";
    }

    # 6. checkHooks
    $this->do_check_hooks( $MWpath );


    return "\n\n...fin de l'installation. Veuillez poursuivre en exéctant php update.php\n";
  }
}