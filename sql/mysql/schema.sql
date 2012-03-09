CREATE TABLE `ezdropbox` (
  `id` int(11) NOT NULL auto_increment,
  `parent_id` int(11) NOT NULL default '0',
  `is_dir` int(1) NOT NULL default '0',
  `hash` varchar(255) NOT NULL default '',
  `path` varchar(255) NOT NULL default '',  
  `modified` int(11) NOT NULL default '0',  
  `object_id` int(11) NOT NULL default '0',
  PRIMARY KEY  (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

