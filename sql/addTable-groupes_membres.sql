CREATE TABLE IF NOT EXISTS /*_*/mgw_groupes_membres (
	groupe_membre_id int unsigned auto_increment not null,
  groupe_membre_groupe_id int unsigned not null,
  groupe_membre_utilisateur_id int unsigned not null,
  groupe_membre_isadmin boolean default false,
  groupe_membre_update_time varbinary(14) not null,
  groupe_membre_update_utilisateur_id int not null,
  PRIMARY KEY (groupe_membre_id)
) /*$wgDBTableOptions*/;
