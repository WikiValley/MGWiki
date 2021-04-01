<?php

/**
 * Fonctions appelées par MgwUpdater::do_backup
 */

trait MgwBackup {

  private function backup_db_save( $sql_file, $directory ) {
    global $wgDBuser, $wgDBpassword, $wgDBname;

    # on vérifie le fichier de destination
    if ( $this->check_backup_sql( $sql_file, $directory, true ) )
     return "annulation";

    // sauvegarde
    # usage de "MYSQL_PWD=" pour sécuriser l'historique des commandes
    $cmd = "mysqldump -u {$wgDBuser} {$wgDBname} > {$sql_file}";
    $shell_out = '';
    $shell = $this->shell( "cd {$directory} && MYSQL_PWD=\"{$wgDBpassword}\" {$cmd}", $shell_out );
    echo ( $shell_out );

    if ( $shell != 0 ) {
      return "la commande :\n   {$cmd} \nn'a pas fonctionné. Veuillez effectuer la sauvegarde manuellement.\n";
    }

    return "La base de données a été dupliquée dans le fichier:\n".
        "  {$directory}/{$sql_file}\n\n";
  }

  private function backup_files_save( $copy_dir, $directory ) {
    global $IP;

    # vérification du répertoire de destination
    if ( !$this->check_backup_files( $copy_dir, $directory, true ) )
      return "annulation";

    # sauvegarde
    $shell_out = '';
    $shell_cmd = "cp {$IP}/LocalSettings.php {$directory}/{$copy_dir}/LocalSettings.php ".
                "&& cp -r {$IP}/images {$directory}/{$copy_dir}/images " .
                "&& mkdir {$directory}/{$copy_dir}/skins" .
                "&& cp -r {$IP}/skins/MGWiki {$directory}/{$copy_dir}/skins/MGWiki";
    $shell = $this->shell( $shell_cmd, $shell_out );
    if ( $shell != 0 ) {
      return $shell_out . "\n";
    }

    return "La copie des fichiers a été effectuée dans le répertoire: \n" .
        "  {$directory}/{$copy_dir}\n\n";
  }

  private function backup_db_restore( $sql_file, $directory ) {

    global $wgDBuser, $wgDBpassword, $wgDBname;

    # on vérifie le fichier de destination
    if ( ! $this->check_backup_sql( $sql_file, $directory, false ) ) {
      return 'Aucun fichier .sql retrouvé.';
    }

    # 1: suppression
    $shell_cmd = "mysqladmin --force -u {$wgDBuser} drop {$wgDBname}";
    $shell_out = '';
     // usage de "MYSQL_PWD=" pour sécuriser l'historique des commandes
    $shell = $this->shell( "MYSQL_PWD=\"{$wgDBpassword}\" {$shell_cmd}", $shell_out );
    echo $shell_out . "\n\n";
    if ( $shell != 0 ) {
      echo "échec de la commande '$shell_cmd' :\n";
      return "Veuillez effectuer la suppression manuellement.\n";
    }

    # 2: re-création de la base
    $shell_cmd = "mysqladmin -u {$wgDBuser} create {$wgDBname}";
    $shell_out = '';
    $shell = $this->shell( "MYSQL_PWD=\"{$wgDBpassword}\" {$shell_cmd}", $shell_out );
    echo $shell_out . "\n\n";
    if ( $shell != 0 ) {
      echo "échec de la commande '$shell_cmd' :\n";
      return "Veuillez effectuer la re-création de la base manuellement.\n";
    }

    # 3: ré-implémentation de la sauvegarde
    $shell_cmd = "mysql -u {$wgDBuser} {$wgDBname} < {$directory}/{$sql_file}";
    $shell = $this->shell( "MYSQL_PWD=\"{$wgDBpassword}\" {$shell_cmd}", $shell_out );
    echo $shell_out . "\n\n";
    if ( $shell != 0 ) {
      echo "échec de la commande '$shell_cmd' :\n";
      return "Veuillez effectuer la ré-implémentation de la sauvegarde dans la base manuellement.\n";
    }
    return "Sauvegarde de la DB restaurée avec succès";
  }

  private function backup_files_restore( $copy_dir, $directory, $sub = '' ) {
    global $IP;

    // vérification du répertoire de sauvegarde
    if ( !$this->check_backup_files( $copy_dir, $directory, false ) )
      return "annulation";
    $directory = $directory . '/' . $copy_dir;

    // restauration des fichiers
    if ( ( !$sub || $sub = 'LocalSettings' ) && file_exists( $directory . '/LocalSettings.php' ) ) {
      $shell_cmd = "rm {$IP}/LocalSettings.php && cp {$directory}/LocalSettings.php {$IP}/LocalSettings.php";
      $shell_out = '';
      $shell = $this->shell( $shell_cmd, $shell_out );
      echo $shell_out . "\n";
      if ( $shell != 0 ) {
        echo "copie de LocalSettings.php: echec\n";
      }
      else echo "copie de LocalSettings.php: OK\n";
    }
    elseif ( !file_exists( $directory . '/LocalSettings.php' ) ) 
      echo "aucun fichier LocalSettings.php dans la sauvegarde: le fichier actuel est inchangé.\n";

    if ( !$sub && file_exists( $directory . '/images' ) ) {
      $shell_cmd = "rm -rf {$IP}/images && cp -r {$directory}/images {$IP}/images " .
        "&& chown -R www-data:www-data {$IP}/images";
      $shell_out = '';
      $shell = $this->shell( $shell_cmd, $shell_out );
      echo $shell_out . "\n";
      if ( $shell != 0 ) {
        echo "copie de images/ : échec\n";
      }
      else echo "copie de images/ : OK\n";
    }
    elseif ( !file_exists( $directory . '/images' ) )
      echo "aucun dossier images/ dans la sauvegarde: le dossier actuel est inchangé.\n";

    if ( !$sub && file_exists( $directory . '/skins/MGWiki' ) ) {
      $shell_cmd = "rm -rf {$IP}/skins/MGWiki && cp -r {$directory}/skins/MGWiki {$IP}/skins/MGWiki ";
      $shell_out = '';
      $shell = $this->shell( $shell_cmd, $shell_out );
      echo $shell_out . "\n";
      if ( $shell != 0 ) {
        echo "copie de skins/MGWiki/ : échec\n";
      }
      else echo "copie de skins/MGWiki/ : OK\n";
    }
    elseif ( !file_exists( $directory . '/skins/MGWiki' ) )
      echo "aucun dossier skins/MGWiki/ dans la sauvegarde: le dossier actuel est inchangé.\n";

    return '';
  }

  private function make_backup_name( &$backup ) {
    global $wgVersion;
    $mgw_config = file_get_contents ( '../extension.json' );
    $mgw_config = json_decode( $mgw_config, true );
    $time = wfTimestamp( TS_MW );
    $time = substr($time,0,8) . '_' . substr($time,8,6);
    $backup = $time . '_MW-' . $wgVersion . '_MGW-' . $mgw_config['version'];
  }

  private function check_backup_dir( $backup, $dir, $create ) {

    $shell_out = '';
    $shell = $this->shell( "cd {$dir} && ls", $shell_out );
    $ls = explode( "\n", $shell_out );
    $dir_exists = in_array( $backup, $ls );

    if ( $dir_exists && !$create ) {
      return true;
    }
    if ( !$dir_exists && !$create ) {
      echo 'Le répertoire de sauvegarde demandé n\'existe pas.';
      return false;
    }
    if ( $dir_exists && $create ) {
      echo 'Un répertoire de sauvegarde déjà présent porte le même nom. Voulez-vous le réécrire (o/n) ?';
      if ( $this->readconsole('> ') != 'o' ) {
        return false;
      }
      return true;
    }
    if ( !$dir_exists && $create ) {
      $cmd = "cd {$dir} && mkdir {$backup}";
      $shell_out = '';
      $shell = $this->shell( $cmd, $shell_out );
      if ( $shell != 0 ) {
        echo $shell_out . "\n";
        return false;
      }
      return true;
    }
  }

  private function check_backup_sql( $filename, $dir, $create ) {
    $shell_out = '';
    $shell = $this->shell( "cd {$dir} && ls", $shell_out );
    $ls = explode( "\n", $shell_out );
    $file_exists = in_array( $filename, $ls );

    if ( $file_exists && $create ) {
      echo 'Un fichier .sql déjà présent porte le même nom. Voulez-vous le remplacer (o/n) ?';
      if ( $this->readconsole('> ') != 'o' ) {
        return true;
      }
      $this->shell( "cd {$dir} && rm {$filename}", $shell_out );
      return false;
    }

    return $file_exists;
  }

  private function check_backup_files( $copy_dir, $directory, $create ) {

    if ( $create ) {
      $shell_out = '';
      $shell = $this->shell( "cd {$directory} && ls", $shell_out );
      $ls = explode( "\n", $shell_out );
      $dir_files_exists = in_array( $copy_dir, $ls ) && is_dir( $dir . '/' . $copy_dir );

      if ( !$dir_files_exists ) {
        $shell_out = '';
        $shell = $this->shell( "cd {$directory} && mkdir {$copy_dir}", $shell_out );
        if ( $shell != 0 ) {
          echo $shell_out . "\n";
          return false;
        }
        return true;
      }

      if ( $dir_files_exists ) {
        $shell_out = '';
        $shell = $this->shell( "cd {$directory}/{$copy_dir} && ls", $shell_out );
        if ( !$shell_out ) {
          return true;
        }
        else {
          echo "le répertoire de destination n'est pas vide. Voulez-vous remplacer son contenu ? (o/n)";
          if ( $this->readconsole('> ') != 'o' ) {
            return false;
          }
          $shell_out = '';
          $shell = $this->shell( "cd {$directory} && rm -rf {$copy_dir} && mkdir {$copy_dir}", $shell_out );
          if ( $shell != 0 ) {
            echo $shell_out;
            return false;
          }
          return true;
        }
      }
    }
    if ( !$create ) {
      $shell_out = '';
      $shell = $this->shell( "cd {$directory}/{$copy_dir} && ls", $shell_out );
      $ls = explode( "\n", $shell_out );
      if ( $shell != 0 ) {
        echo $shell_out;
        return false;
      }
      if ( !$shell_out ) {
        echo "le répertoire de sauvegarde des fichiers est vide.\n";
        return false;
      }
      if ( !in_array( 'LocalSettings.php', $ls) ) {
        echo "le fichier LocalSettings.php est absent de la sauvegarde. Souhaitez-vous poursuivre ? (o/n)\n";
        if ( $this->readconsole('> ') != 'o' ) return false;
      }
      if ( !in_array( 'images', $ls) ) {
        echo "le dossier images est absent de la sauvegarde. Souhaitez-vous poursuivre ? (o/n)\n";
        if ( $this->readconsole('> ') != 'o' ) return false;
      }
      return true;
    }
  }

  private function screen_backups( $directory, &$backup ) {
    $dirs = [];
    $files = [];
    $this->backup_ls( $directory, $dirs, $files );
    if ( !$dirs ) {
      echo "Le chemin spécifié ne contient aucun répertoire.";
      return false;
    }
    echo "Quelle sauvegarde voulez-vous restaurer ? " .
      "(ATTENTION cette commande est irréversible - 'q' pour quitter)\n\n";
    foreach ( $dirs as $key => $dir ) {
      echo "[ $key ]  $dir\n";
    }
    echo "\n";
    $n = $this->readconsole('> ');
    if ( !isset( $dirs[$n] ) ) {
      if ( $n != 'q' ) {
        echo "votre choix ne correspond à aucune proposition.\n";
      }
      return false;
    }
    $backup = $dirs[$n];
    return true;
  }

  private function backup_purge_all( $directory ) {
    echo "Souhaitez-vous remplacer les archives existantes par la nouvelle ? (o/n)\n";
    $r = $this->readconsole('> ');
    if ( $r == 'o' ) {
      $this->backup_purge( $directory, true );
    }
  }

  private function backup_purge( $directory, $all = false ) {

    $dirs = [];
    $files = [];
    $this->backup_ls( $directory, $dirs, $files );

    # répertoires
    if ( !$dirs ) {
      echo "Le chemin spécifié ne contient aucun répertoire.\n";
    }
    else {
      if ( !$all ) {
        echo "\nQuelles sauvegardes voulez-vous CONSERVER ? " .
          "(attention cette commande est irréversible)\n" .
          "  '1' / '0,1,4' / 'a' aucune / 'q' quitter\n\n";
        foreach ( $dirs as $key => $dir ) {
          echo "[ $key ]  $dir\n";
        }
        echo "\n";
        $n = $this->readconsole('> ');
        if ( $n == 'a' ) {
          echo "suppression de tous les répertoirs de sauvegarde ...\n";
        }
        elseif ( $n == 'q' ) {
          $dirs = [];
          echo "aucune sauvegarde ne sera supprimée\n";
        }
        else {
          $n = explode(',', $n);
          foreach( $n as $key ) {
            if ( !isset( $dirs[$key] ) ) echo "'{$key}' ne correspond à aucun choix\n";
            else {
              unset( $dirs[$key] );
            }
          }
        }
      }
      foreach ( $dirs as $dir ) {
        echo "suppression de {$dir}/\n";
     		$shell_cmd = "cd {$directory} && rm -rf {$dir}";
     		$shell_out = '';
     		$shell = $this->shell( $shell_cmd, $shell_out );
     		if ( $shell != 0 ) echo $shell_out . "\n";
      }
    }

    # fichiers
    if ( $files ) {
      if ( !$all ) {
        echo "\nQuels fichiers voulez-vous CONSERVER ? " .
          "(attention cette commande est irréversible)\n" .
          "  '1' / '0,1,4' / 'a' aucun / 'q' quitter\n\n";
        foreach ( $files as $key => $file ) {
          echo "[ $key ]  $file\n";
        }
        echo "\n";
        $n = $this->readconsole('> ');
        if ( $n == 'a' ) {
          echo "suppression de tous les fichiers ...\n";
        }
        elseif ( $n == 'q' ) {
          $files = [];
          echo "aucun fichier ne sera supprimé\n";
        }
        else {
          $n = explode(',', $n);
          foreach( $n as $key ) {
            if ( !isset( $files[$key] ) ) echo "'{$key}' ne correspond à aucun choix\n";
            else {
              echo "suppression de {$files[$key]}\n";
              unset( $files[$key] );
            }
          }
        }
      }
      foreach ( $files as $file ) {
        echo "suppression de {$file}\n";
        unlink( $directory . '/' . $file );
      }
    }
  }

  private function backup_ls( $directory, &$dirs, &$files ) {
    $shell_out = '';
    $shell = $this->shell( "cd {$directory} && ls", $shell_out );
    $ls = explode( "\n", $shell_out );
    foreach ( $ls as $string ) {
      if ( is_dir( $directory.'/'.$string ) ) {
        $dirs[] = $string;
      }
      else $files[] = $string;
    }
    asort($dirs);
    asort($files);
  }
}
