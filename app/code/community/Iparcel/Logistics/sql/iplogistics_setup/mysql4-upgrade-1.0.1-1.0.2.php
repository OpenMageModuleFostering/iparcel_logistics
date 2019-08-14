<?php

$installer = $this;

// Create a table to store tax & duty calculations

$installer->run("
DROP TABLE IF EXISTS {$this->getTable('iplogistics/api_quote')};
CREATE TABLE {$this->getTable('iplogistics/api_quote')} (
  `id` int(11) unsigned NOT NULL auto_increment,
  `quote_id` varchar(255) NOT NULL,
  `parcel_id` varchar(255) NOT NULL,
  `service_levels` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$installer->endSetup();
