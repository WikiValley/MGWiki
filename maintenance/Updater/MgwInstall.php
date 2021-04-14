<?php

/**
 * Fonctions appelées par MgwUpdater::do_install
 */

trait MgwInstall {

  private function install_version( $version ) {
    global $IP;

    # 1. mediawiki
    $rel = 'REL' . str_replace('.','_',$version);
    if ( !file_exists('/var/www/html/' . $version ) ) {
      $cmd = "cd /var/www/html && git clone https://gerrit.wikimedia.org/r/mediawiki/core.git -b $rel mediawiki";
      if ( $this->shell_dry( $cmd ) ) {
        echo "échec au téléchargement de mediawiki. Veuillez le faire manuellement avant de continuer.\n\n";
        return "abandon\n";
      }
      $cmd = "cd /var/www/html && mv mediawiki/ $version/";
      if ( $this->shell_dry( $cmd ) ) {
        echo "Mediawiki téléchargé mais échec au renommage du dossier en /var/www/html/$version\n"
        . " Veuillez le faire manuellement avant de continuer.\n\n";
        return "abandon\n";
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
    $ext_config = json_decode( file_get_contents( "$IP/extensions/MGWiki/config/extensions.versions.json" ), true );
    if ( !isset( $ext_config[$version] ) ) {
      echo "La liste des extensions n'est pas configurée pour cette version de mediawiki\n.";
      return "Abandon\n";
    }

      ## composer
    $composer = [ "require" => $ext_config[$version]['composer'] ];
    file_put_contents( "$MWpath/composer.local.json", json_encode( $composer ) );

    $cmd = "cd $MWpath && composer update --no-dev";
    if ( $this->shell_dry( $cmd ) ) {
      echo "échec à la mise à jour des extensions via composer ($cmd).\n Veuillez le faire manuellement.\n\n";
    }

      ## git
    foreach ( $ext_config[$version]['git'] as $ext => $info ) {
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

    # 4. restauration des données
    echo "\nRéimplémentation des données depuis la sauvegarde... \n\n";
    echo $this->do_backup( 'restore', $version );

    # 5. droits d'écriture
    $cmd = "chown -R www-data:www-data $MWpath/cache";
    if ( $this->shell_dry( $cmd ) ) {
      echo "échec à l'ouverture des droits d'écriture du cache ($cmd).\n Veuillez le faire manuellement.\n\n";
    }

    return "\n\n...fin de l'installation. Veuillez poursuivre en exéctant php update.php\n";
  }
}
