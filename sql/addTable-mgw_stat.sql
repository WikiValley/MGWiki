CREATE TABLE IF NOT EXISTS /*_*/mgw_stat (  stat_id int unsigned not null auto_increment,  stat_updater_id int not null,  stat_update_time varbinary(14) not null,  stat_label varchar(32) not null,  stat_data blob,	PRIMARY KEY (stat_id)) /*$wgDBTableOptions*/;