-- Database schema for MGWiki (only for MYSQL database)
--
-- en cas de modif: màj des fichiers sql/*.sql avec la commande 'sql-update.php'
-- ! pas de commentaires à l'intérieur des blocs d'instruction ...

----------------------------------------------------------------------------------------
-- TASK: gestion des tâches en cours
-- (objectif: pouvoir reprendre une tâche arrêtée en cours d'exécution)
CREATE TABLE IF NOT EXISTS /*_*/mgw_task (
  task_id int unsigned not null auto_increment,
  task_updater_id int not null,
  task_update_time varbinary(14) not null,
  task_label varchar(32) not null,
  task_data mediumblob,
  task_extra mediumblob,
  task_archive tinyint default 0,
	PRIMARY KEY (task_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_task_lookup ON /*_*/mgw_task (task_id, task_updater_id, task_label, task_archive);
-----------------------------------------------------------------------------------------
-----------------------------------------------------------------------------------------
-- STAT: enregistrement des cas d'usage
-- objectif: monitoring de l'application
CREATE TABLE IF NOT EXISTS /*_*/mgw_stat (
  stat_id int unsigned not null auto_increment,
  stat_updater_id int not null,
  stat_update_time varbinary(14) not null,
  stat_label varchar(32) not null,
  stat_data blob,
	PRIMARY KEY (stat_id)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/mgw_stat_lookup ON /*_*/mgw_stat (stat_id, stat_updater_id, stat_label);
-----------------------------------------------------------------------------------------
