<?php

/**
 * Fonctions appelées par MgwUpdater::do_install
 */

trait MgwInstall {

  private function install_version( $version ) {

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
    $MGWpath = "/var/www/html/$version/extensions/MGWiki";
    if ( !file_exists( $MGWpath ) ) {
      $cmd = "cd /var/www/html/$version/extensions && git clone https://github.com/WikiValley/MGWiki.git";
      if ( $this->shell_dry( $cmd ) ) {
        echo "échec au téléchargement de l'extension MGWiki. Veuillez le faire manuellement avant de continuer.\n\n";
        return "abandon\n";
      }
    }

    # 3. extensions
    $ext_config = json_decode( file_get_contents( "$MGWpath/config/extensions.versions.json" ), true );
    if ( !isset( $ext_config[$version] ) ) {
      echo "La liste des extensions n'est pas configurée pour cette version de mediawiki\n.";
      return "Abandon\n";
    }
      # composer
    $composer = [ "require" => $ext_config[$version]['composer'] ];
    file_put_contents( "/var/www/html/$version/composer.local.json", json_encode( $composer ) );
    $cmd = "cd $MGWpath && php composer.phar update --no-dev";
    if ( $this->shell_dry( $cmd ) ) {
      echo "échec à la mise à jour des extensions via composer ($cmd).\n Veuillez le faire manuellement.\n\n";
    }
      # git
    foreach ( $ext_config[$version]['git'] as $url => $branch ) {
      $cmd = "cd $MGWpath/extensions && git clone -b $branch $url";
      if ( $this->shell_dry( $cmd ) ) {
        echo "$url : échec.\n Veuillez le faire manuellement.\n\n";
      }
    }
    return "\n...done\n";
  }
}
