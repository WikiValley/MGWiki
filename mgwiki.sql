-- Database schema for MGWiki (only for MYSQL database)

CREATE TABLE /*_*/mgw_utilisateurs (
  utilisateur_id int not null auto_increment,
  utilisateur_user_id int not null,
	utilisateur_nom varchar(64) not null,
	utilisateur_prenom varchar(64) not null,
  utilisateur_level smallint,
  utilisateur_updater_user_id int not null,
  utilisateur_update_time datetime not null,
	PRIMARY KEY (utilisateur_id)
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/mgw_utilisateurs_lookup ON /*_*/mgw_utilisateurs (utilisateur_user_id, utilisateur_nom, utilisateur_prenom);

CREATE TABLE /*_*/mgw_archive_utilisateurs (
	utilisateur_id int not null,
  utilisateur_user_id int not null,
	utilisateur_nom varchar(64) not null,
	utilisateur_prenom varchar(64) not null,
  utilisateur_level smallint not null,
  utilisateur_updater_user_id int not null,
  utilisateur_update_time datetime not null,
	PRIMARY KEY (utilisateur_id, utilisateur_update_time)
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/mgw_archive_utilisateurs_lookup ON /*_*/mgw_archive_utilisateurs (utilisateur_user_id, utilisateur_nom, utilisateur_prenom);

-- Triggers
DELIMITER |
CREATE TRIGGER mgw_after_update_utilisateur AFTER UPDATE
  ON /*_*/mgw_utilisateurs FOR EACH ROW
  BEGIN
    INSERT INTO mgw_archive_utilisateurs (
    	utilisateur_id,
      utilisateur_user_id,
    	utilisateur_nom,
    	utilisateur_prenom,
      utilisateur_level,
      utilisateur_updater_user_id,
      utilisateur_update_time
    )
    VALUES (
    	OLD.utilisateur_id,
      OLD.utilisateur_user_id,
    	OLD.utilisateur_nom,
    	OLD.utilisateur_prenom,
      OLD.utilisateur_level,
      OLD.utilisateur_updater_user_id,
      OLD.utilisateur_update_time
    );
  END |
CREATE TRIGGER mgw_after_delete_utilisateur AFTER DELETE
  ON /*_*/mgw_utilisateurs FOR EACH ROW
  BEGIN
    INSERT INTO mgw_archive_utilisateurs (
    	utilisateur_id,
      utilisateur_user_id,
    	utilisateur_nom,
    	utilisateur_prenom,
      utilisateur_level,
      utilisateur_updater_user_id,
      utilisateur_update_time
    )
    VALUES (
    	OLD.utilisateur_id,
      OLD.utilisateur_user_id,
    	OLD.utilisateur_nom,
    	OLD.utilisateur_prenom,
      OLD.utilisateur_level,
      OLD.utilisateur_updater_user_id,
      OLD.utilisateur_update_time
    );
  END |
DELIMITER ;

CREATE TABLE IF NOT EXISTS /*_*/mgw_institutions (
	institution_id int unsigned auto_increment not null,
  institution_page_id int not null,
	institution_nom varchar(64) not null,
  institution_update_utilisateur_id int not null,
  institution_update_time datetime not null,
  PRIMARY KEY (institution_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_institutions (
	institution_id int unsigned not null,
  institution_page_id int not null,
	institution_nom varchar(64) not null,
  institution_update_utilisateur_id int not null,
  institution_update_time datetime not null,
  PRIMARY KEY (institution_id, institution_update_time)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mgw_groupes (
	groupe_id int unsigned auto_increment not null,
  groupe_level smallint not null,
  groupe_institution_id int not null,
  groupe_page_id int not null,
	groupe_nom varchar(64) not null,
  groupe_start_time datetime,
  groupe_end_time datetime,
  groupe_actif boolean default true,
  groupe_update_utilisateur_id int not null,
  groupe_update_time datetime not null,
  PRIMARY KEY (groupe_id)
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/mgw_groupes_lookup ON /*_*/mgw_groupes (groupe_page_id);

CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_groupes (
	groupe_id int not null,
  groupe_level smallint not null,
  groupe_institution_id int not null,
  groupe_page_id int not null,
	groupe_nom varchar(64) not null,
  groupe_start_time datetime,
  groupe_end_time datetime,
  groupe_actif boolean not null,
  groupe_update_time datetime not null,
  groupe_update_utilisateur_id int not null,
  PRIMARY KEY (groupe_id, groupe_update_time)
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/mgw_archive_groupes_lookup ON /*_*/mgw_archive_groupes (groupe_page_id);

CREATE TABLE IF NOT EXISTS /*_*/mgw_groupes_membres (
	groupe_membre_id int unsigned auto_increment not null,
  groupe_membre_groupe_id int unsigned not null,
  groupe_membre_utilisateur_id int unsigned not null,
  groupe_membre_isadmin boolean default false,
  groupe_membre_update_time datetime not null,
  groupe_membre_update_utilisateur_id int not null,
  PRIMARY KEY (groupe_membre_id)
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/mgw_groupes_membres_lookup ON /*_*/mgw_groupes_membres (groupe_membre_groupe_id, groupe_membre_utilisateur_id);

CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_groupes_membres (
	groupe_membre_id int unsigned not null,
  groupe_membre_groupe_id int unsigned not null,
  groupe_membre_utilisateur_id int unsigned not null,
  groupe_membre_isadmin boolean default false,
  groupe_membre_update_time datetime not null,
  groupe_membre_drop_time datetime, -- uniquement lors de la suppression
  groupe_membre_update_utilisateur_id int not null,
  PRIMARY KEY (groupe_membre_id, groupe_membre_update_time)
) /*$wgDBTableOptions*/;
CREATE INDEX /*i*/mgw_archive_groupes_membres_lookup ON /*_*/mgw_archive_groupes_membres (groupe_membre_groupe_id, groupe_membre_utilisateur_id);
