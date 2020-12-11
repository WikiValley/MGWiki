-- Database schema for MGWiki (only for MYSQL database)
--
-- en cas de modif: màj des fichiers sql/*.sql avec la commande 'php UpdateSQLfiles.php'
-- ! pas de commentaires dans les lignes de commande
-- booléens traités en int: 1|0

CREATE TABLE IF NOT EXISTS /*_*/mgw_utilisateur (
  utilisateur_id int unsigned not null auto_increment,
  utilisateur_user_id int not null,
	utilisateur_nom varchar(64) not null,
	utilisateur_prenom varchar(64) not null,
  utilisateur_level smallint default 0,
  utilisateur_update_time varbinary(14) not null,
  utilisateur_updater_id int not null,
	PRIMARY KEY (utilisateur_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_utilisateur_lookup ON /*_*/mgw_utilisateur (utilisateur_user_id, utilisateur_nom, utilisateur_prenom, utilisateur_level);

CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_utilisateur (
  archive_id int unsigned not null auto_increment,
	utilisateur_id int unsigned not null,
  utilisateur_user_id int not null,
	utilisateur_nom varchar(64) not null,
	utilisateur_prenom varchar(64) not null,
  utilisateur_level smallint not null,
  utilisateur_update_time varbinary(14) not null,
  utilisateur_updater_id int not null,
  utilisateur_drop_time varbinary(14) default null,
  utilisateur_drop_updater_id int default null,
	PRIMARY KEY (archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_archive_utilisateur_lookup ON /*_*/mgw_archive_utilisateur (utilisateur_user_id, utilisateur_nom, utilisateur_prenom, utilisateur_level);

CREATE TABLE IF NOT EXISTS /*_*/mgw_institution (
	institution_id int unsigned auto_increment not null,
  institution_page_id int not null,
	institution_nom varchar(64) not null,
  institution_update_time varbinary(14) not null,
  institution_updater_id int not null,
  PRIMARY KEY (institution_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_institution (
  archive_id int unsigned not null auto_increment,
	institution_id int unsigned not null,
  institution_page_id int not null,
	institution_nom varchar(64) not null,
  institution_update_time varbinary(14) not null,
  institution_updater_id int not null,
  institution_drop_time varbinary(14) default null,
  institution_drop_updater_id int default null,
  PRIMARY KEY (archive_id)
) /*$wgDBTableOptions*/;

-- table ne nécessitant pas d'archive
CREATE TABLE IF NOT EXISTS /*_*/mgw_institution_groupe (
  institution_groupe_id int unsigned not null auto_increment,
  institution_groupe_type_id int unsigned not null,
  institution_groupe_institution_id int unsigned not null,
  PRIMARY KEY (institution_groupe_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mgw_groupe (
	groupe_id int unsigned auto_increment not null,
  groupe_institution_id int unsigned not null,
  groupe_page_id int,
  groupe_type_id int unsigned,
  groupe_start_time varbinary(14),
  groupe_end_time varbinary(14),
  groupe_actif smallint default 1,
  groupe_updater_id int not null,
  groupe_update_time varbinary(14) not null,
  PRIMARY KEY (groupe_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_groupe_lookup ON /*_*/mgw_groupe (groupe_institution_id, groupe_page_id, groupe_type_id, groupe_actif);

 -- pas de cas d'usage drop pour les groupes
CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_groupe (
  archive_id int unsigned not null auto_increment,
	groupe_id int unsigned not null,
  groupe_institution_id int unsigned not null,
  groupe_page_id int,
  groupe_type_id int unsigned,
  groupe_start_time varbinary(14),
  groupe_end_time varbinary(14),
  groupe_actif smallint,
  groupe_update_time varbinary(14) not null,
  groupe_updater_id int not null,
  PRIMARY KEY (archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_archive_groupe_lookup ON /*_*/mgw_archive_groupe (groupe_institution_id, groupe_page_id, groupe_type_id, groupe_actif);

CREATE TABLE IF NOT EXISTS /*_*/mgw_groupe_membre (
	groupe_membre_id int unsigned auto_increment not null,
  groupe_membre_groupe_id int unsigned not null,
  groupe_membre_user_id int not null,
  groupe_membre_isadmin smallint default 0,
  groupe_membre_update_time varbinary(14) not null,
  groupe_membre_updater_id int not null,
  PRIMARY KEY (groupe_membre_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_groupe_membre_lookup ON /*_*/mgw_groupe_membre (groupe_membre_groupe_id, groupe_membre_user_id, groupe_membre_isadmin);

CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_groupe_membre (
  archive_id int unsigned not null auto_increment,
	groupe_membre_id int unsigned not null,
  groupe_membre_groupe_id int unsigned not null,
  groupe_membre_user_id int not null,
  groupe_membre_isadmin smallint,
  groupe_membre_update_time varbinary(14) not null,
  groupe_membre_updater_id int not null,
  groupe_membre_drop_time varbinary(14) default null,
  groupe_membre_drop_updater_id int default null,
  PRIMARY KEY (archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_archive_groupe_membre_lookup ON /*_*/mgw_archive_groupe_membre (groupe_membre_groupe_id, groupe_membre_user_id, groupe_membre_isadmin);

CREATE TABLE IF NOT EXISTS /*_*/mgw_groupe_type (
	groupe_type_id int unsigned auto_increment not null,
  groupe_type_nom varchar(64) not null,
  groupe_type_page_id int,
  groupe_type_admin_level smallint not null,
  groupe_type_user_level smallint not null,
  groupe_type_default_duration int default null,
  groupe_type_update_time varbinary(14) not null,
  groupe_type_updater_id int not null,
  PRIMARY KEY (groupe_type_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_groupe_type_lookup ON /*_*/mgw_groupe_type (groupe_type_nom, groupe_type_page_id, groupe_type_user_level, groupe_type_admin_level);

CREATE TABLE IF NOT EXISTS /*_*/mgw_archive_groupe_type (
  archive_id int unsigned not null auto_increment,
	groupe_type_id int unsigned not null,
  groupe_type_nom varchar(64) not null,
  groupe_type_page_id int,
  groupe_type_admin_level smallint not null,
  groupe_type_user_level smallint not null,
  groupe_type_default_duration int,
  groupe_type_update_time varbinary(14) not null,
  groupe_type_updater_id int not null,
  groupe_type_drop_time varbinary(14) default null,
  groupe_type_drop_updater_id int default null,
  PRIMARY KEY (archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_archive_groupe_type_lookup ON /*_*/mgw_archive_groupe_type (groupe_type_nom, groupe_type_page_id, groupe_type_user_level, groupe_type_admin_level);
