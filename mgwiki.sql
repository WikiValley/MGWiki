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

CREATE TABLE IF NOT EXISTS /*_*/mgw_utilisateur_archive (
  utilisateur_archive_id int unsigned not null auto_increment,
	utilisateur_id int unsigned not null,
  utilisateur_user_id int not null,
	utilisateur_nom varchar(64) not null,
	utilisateur_prenom varchar(64) not null,
  utilisateur_level smallint not null,
  utilisateur_update_time varbinary(14) not null,
  utilisateur_updater_id int not null,
  utilisateur_drop_time varbinary(14) default null,
  utilisateur_drop_updater_id int default null,
	PRIMARY KEY (utilisateur_archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_utilisateur_archive_lookup ON /*_*/mgw_utilisateur_archive (utilisateur_user_id, utilisateur_nom, utilisateur_prenom, utilisateur_level);

CREATE TABLE IF NOT EXISTS /*_*/mgw_institution (
	institution_id int unsigned auto_increment not null,
  institution_page_id int not null,
	institution_nom varchar(64) not null,
  institution_update_time varbinary(14) not null,
  institution_updater_id int not null,
  PRIMARY KEY (institution_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mgw_institution_archive (
  institution_archive_id int unsigned not null auto_increment,
	institution_id int unsigned not null,
  institution_page_id int not null,
	institution_nom varchar(64) not null,
  institution_update_time varbinary(14) not null,
  institution_updater_id int not null,
  institution_drop_time varbinary(14) default null,
  institution_drop_updater_id int default null,
  PRIMARY KEY (institution_archive_id)
) /*$wgDBTableOptions*/;

CREATE TABLE IF NOT EXISTS /*_*/mgw_groupe (
	groupe_id int unsigned auto_increment not null,
  groupe_institution_id int unsigned not null,
  groupe_page_id int,
  groupe_frame_id int unsigned,
  groupe_start_time varbinary(14),
  groupe_end_time varbinary(14),
  groupe_actif smallint default 1,
  groupe_updater_id int not null,
  groupe_update_time varbinary(14) not null,
  PRIMARY KEY (groupe_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_groupe_lookup ON /*_*/mgw_groupe (groupe_institution_id, groupe_page_id, groupe_frame_id, groupe_actif);

 -- pas de cas d'usage drop pour les groupes
CREATE TABLE IF NOT EXISTS /*_*/mgw_groupe_archive (
  groupe_archive_id int unsigned not null auto_increment,
	groupe_id int unsigned not null,
  groupe_institution_id int unsigned not null,
  groupe_page_id int,
  groupe_frame_id int unsigned,
  groupe_start_time varbinary(14),
  groupe_end_time varbinary(14),
  groupe_actif smallint,
  groupe_update_time varbinary(14) not null,
  groupe_updater_id int not null,
  PRIMARY KEY (groupe_archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_groupe_archive_lookup ON /*_*/mgw_groupe_archive (groupe_institution_id, groupe_page_id, groupe_frame_id, groupe_actif);

CREATE TABLE IF NOT EXISTS /*_*/mgw_membre (
	membre_id int unsigned auto_increment not null,
  membre_groupe_id int unsigned not null,
  membre_user_id int not null,
  membre_isadmin smallint default 0,
  membre_update_time varbinary(14) not null,
  membre_updater_id int not null,
  PRIMARY KEY (membre_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_membre_lookup ON /*_*/mgw_membre (membre_groupe_id, membre_user_id, membre_isadmin);

CREATE TABLE IF NOT EXISTS /*_*/mgw_membre_archive (
  membre_archive_id int unsigned not null auto_increment,
	membre_id int unsigned not null,
  membre_groupe_id int unsigned not null,
  membre_user_id int not null,
  membre_isadmin smallint,
  membre_update_time varbinary(14) not null,
  membre_updater_id int not null,
  membre_drop_time varbinary(14) default null,
  membre_drop_updater_id int default null,
  PRIMARY KEY (membre_archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_membre_archive_lookup ON /*_*/mgw_membre_archive (membre_groupe_id, membre_user_id, membre_isadmin);

CREATE TABLE IF NOT EXISTS /*_*/mgw_frame (
	frame_id int unsigned auto_increment not null,
  frame_nom varchar(64) not null,
  frame_page_id int,
  frame_admin_level smallint not null,
  frame_user_level smallint not null,
  frame_default_duration int default null,
  frame_update_time varbinary(14) not null,
  frame_updater_id int not null,
  PRIMARY KEY (frame_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_frame_lookup ON /*_*/mgw_frame (frame_nom, frame_page_id, frame_user_level, frame_admin_level);

CREATE TABLE IF NOT EXISTS /*_*/mgw_frame_archive (
  frame_archive_id int unsigned not null auto_increment,
	frame_id int unsigned not null,
  frame_nom varchar(64) not null,
  frame_page_id int,
  frame_admin_level smallint not null,
  frame_user_level smallint not null,
  frame_default_duration int,
  frame_update_time varbinary(14) not null,
  frame_updater_id int not null,
  frame_drop_time varbinary(14) default null,
  frame_drop_updater_id int default null,
  PRIMARY KEY (frame_archive_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_frame_archive_lookup ON /*_*/mgw_frame_archive (frame_nom, frame_page_id, frame_user_level, frame_admin_level);

-- table ne nécessitant pas d'archive
CREATE TABLE IF NOT EXISTS /*_*/mgw_instit_allow (
  instit_allow_id int unsigned not null auto_increment,
  instit_allow_institution_id int unsigned not null,
  instit_allow_frame_id int unsigned not null,
  PRIMARY KEY (institution_groupe_id)
) /*$wgDBTableOptions*/;
