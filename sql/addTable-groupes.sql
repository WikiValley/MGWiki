CREATE TABLE IF NOT EXISTS /*_*/mgw_groupes (
	groupe_id int unsigned auto_increment not null,
  groupe_level smallint not null,
  groupe_institution_id int not null,
  groupe_page_id int not null,
	groupe_nom varchar(64) not null,
  groupe_start_time varbinary(14),
  groupe_end_time varbinary(14),
  groupe_actif boolean default true,
  groupe_update_utilisateur_id int not null,
  groupe_update_time varbinary(14) not null,
  PRIMARY KEY (groupe_id)
) /*$wgDBTableOptions*/;
