CREATE TABLE /*_*/mgw_archive_utilisateurs (
	utilisateur_id int not null,
	utilisateur_nom varchar(64) not null,
	utilisateur_prenom varchar(64) not null,
  utilisateur_level smallint not null,
  utilisateur_update_utilisateur_id int not null,
  utilisateur_update_time varbinary(14) not null,
	PRIMARY KEY (utilisateur_id, utilisateur_update_time)
) /*$wgDBTableOptions*/;
