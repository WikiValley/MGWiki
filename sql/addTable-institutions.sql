CREATE TABLE IF NOT EXISTS /*_*/mgw_institutions (
	institution_id int unsigned auto_increment not null,
  institution_page_id int not null,
	institution_nom varchar(64) not null,
  institution_update_utilisateur_id int not null,
  institution_update_time varbinary(14) not null,
  PRIMARY KEY (institution_id)
) /*$wgDBTableOptions*/;
