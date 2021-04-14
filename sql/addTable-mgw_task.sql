CREATE TABLE IF NOT EXISTS /*_*/mgw_task (  task_id int unsigned not null auto_increment,  task_updater_id int not null,  task_update_time varbinary(14) not null,  task_label varchar(32) not null,  task_data mediumblob,  task_extra mediumblob,  task_archive tinyint default 0,	PRIMARY KEY (task_id)) /*$wgDBTableOptions*/;