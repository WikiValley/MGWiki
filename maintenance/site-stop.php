<?php
  /**
   * Automatisation de la redirection de toutes les requêtes vers /var/www/html/travaux/index.html
   *
   * Usage:
   *  sudo php site-stop.php --stop (mise en pause du site le temps des travaux de maintenance)
   *  sudo php site-stop.php --run (remise en production)
   */

  $options = ['run','stop','help'];
  if ( !isset($getOpt) ) {
    $getOpt = getOpt( 'rsh', $options );
  }

  if ( array_key_exists( 'r', $getOpt ) || array_key_exists( 'run', $getOpt ) ) {

    # /var/www/html/index.php
    $index = file_get_contents ( '/var/www/html/index.php' );
    $index = str_replace( 'travaux/index.html', 'wiki/index.php', $index );
    file_put_contents( '/var/www/html/index.php', $index );

    # /var/www/html/travaux/index.html
    $info = file_get_contents ( '/var/www/html/travaux/index.html' );
    $info = explode( "\n", $info );
    $needle = preg_quote('<!--<meta http-equiv="refresh" content="0; URL=./wiki/index.php">-->');
    foreach ( $info as $key => $line ) {
      $info[$key] = str_replace(
        '<!--<meta http-equiv="refresh" content="0; URL=../index.php">-->',
        '<meta http-equiv="refresh" content="0; URL=../index.php">',
        $line );
    }
    $info = implode("\n", $info );
    file_put_contents ( '/var/www/html/travaux/index.html', $info );

    # /var/www/html/wiki/LocalSettings.php
    $settings = file_get_contents ( '/var/www/html/wiki/LocalSettings.php' );
    $settings = explode( "\n", $settings );
    foreach ( $settings as $key => $line ) {
      /* NON FONCTIONNEL (empêche la maintenance sur la BDD...)
      if ( preg_match( '/^(\$wgReadOnly)/', $line ) ) {
        $settings[$key] = preg_replace( '/^(\$wgReadOnly)/', '#$1', $line );
      }
      */
      if ( preg_match( '/^(header\("Location\:)/', $line ) ) {
        $settings[$key] = preg_replace( '/^(header\("Location\:)/', '#$1', $line );
      }
    }
    $settings = implode("\n", $settings );
    file_put_contents ( '/var/www/html/wiki/LocalSettings.php', $settings );

    echo "MGWiki est en production\n";
  }
  elseif ( array_key_exists( 's', $getOpt ) || array_key_exists( 'stop', $getOpt ) ) {

    # /var/www/html/index.php
    $index = file_get_contents ( '/var/www/html/index.php' );
    $index = str_replace( 'wiki/index.php', 'travaux/index.html', $index );
    file_put_contents( '/var/www/html/index.php', $index );

    # /var/www/html/travaux/index.html
    $info = file_get_contents ( '/var/www/html/travaux/index.html' );
    $info = explode( "\n", $info );
    foreach ( $info as $key => $line ) {
      $info[$key] = str_replace(
        '<meta http-equiv="refresh" content="0; URL=../index.php">',
        '<!--<meta http-equiv="refresh" content="0; URL=../index.php">-->',
        $line );
    }
    $info = implode("\n", $info );
    file_put_contents ( '/var/www/html/travaux/index.html', $info );

    # /var/www/html/wiki/LocalSettings.php
    $settings = file_get_contents ( '/var/www/html/wiki/LocalSettings.php' );
    $settings = explode( "\n", $settings );
    //$readOnly = false;
    $headerLocation = false;
    foreach ( $settings as $key => $line ) {
      /* NON FONCTIONNEL (empêche la maintenance sur la BDD...)
      if ( preg_match( '/^(.*)(\$wgReadOnly)/', $line ) ) {
        $settings[$key] = preg_replace( '/^(.*)(\$wgReadOnly)/', '$2', $line );
        $readOnly = true;
      }
      */
      if ( preg_match( '/^(.*)(header\("Location\:)/', $line ) ) {
        $settings[$key] = preg_replace( '/^(.*)(header\("Location\:)/', '$2', $line );
        $headerLocation = true;
      }
    }
    //if ( !$readOnly ) $settings[] = '$wgReadOnly = "MGWiki est en cours de maintenance. L\'accès sera rétabli rapidement";'
    if ( !$headerLocation ) $settings[] = 'header("Location: $wgServer");';
    $settings = implode("\n", $settings );
    file_put_contents ( '/var/www/html/wiki/LocalSettings.php', $settings );

    echo "MGWiki est arrêté pour maintenance\n";
  }
  else
    echo "\nCommande de mise à l'arrêt / en production de MGWiki.\n\n"
    . "Usage:\n\n  php site-stop.php [opt]\n\n"
    . "Options:\n\n"
    . "  --run (-r):\n"
    . "    URL principale dirigée sur /wiki/index.php\n"
    . "    header() désactivé dans LocalSettings.php\n\n"
    . "  --stop (-s):\n"
    . "    URL principale dirigée sur /travaux/index.html\n"
    . "    header('Location: https://mgwiki.univ-lyon1.fr') activé\n\n";
?>
