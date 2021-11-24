<?php

#
# CLI script to create tables for lodel oai-php database
#
# This serves as definition of our database
#
# Database must exists
# Warning: running it twice will reset your database
#

if (php_sapi_name() != "cli") {
    print "Run this from cli !";
    exit(0);
}

require_once('inc/init.php');

global $database_prefix;

$lodel_oai_site = get_conf('lodelOAIsite');

if (!connect_site($lodel_oai_site)) {
    $db_user = C::get('dbusername', 'cfg');
    print "\n";
    print "Database oai-pmp does not exists, you must create it. \n";
    print " Connect to MySQL and type: \n";
    print "\n";
    print "CREATE DATABASE `{$database_prefix}_{$lodel_oai_site}`; \n";
    print "GRANT ALL PRIVILEGES ON `{$database_prefix}_{$lodel_oai_site}` . * TO '$db_user'@'localhost'; \n";
    print "\n";
}

sql_query("DROP TABLE IF EXISTS `sets`;");
sql_query("CREATE TABLE `sets` (
    `id` INT(10) unsigned NOT NULL AUTO_INCREMENT,
    `set` VARCHAR(64) NOT NULL,
    `oai_id` VARCHAR(64) NOT NULL,
    `name` VARCHAR(64) NOT NULL,
    `title` VARCHAR(64) NOT NULL,
    `description` text NOT NULL,
    `subject` text NOT NULL,
    `url` tinytext DEFAULT NULL,
    `droitsauteur` varchar(255) DEFAULT NULL,
    `editeur` varchar(255) DEFAULT NULL,
    `titresite` varchar(255) DEFAULT NULL,
    `issn` varchar(255) DEFAULT NULL,
    `issn_electronique` varchar(255) DEFAULT NULL,
    `langueprincipale` varchar(255) DEFAULT NULL,
    `doi_prefixe` varchar(255) DEFAULT NULL,
    `openaire_access_level` varchar(255) DEFAULT NULL,
    `upd` timestamp NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `name` (`name`),
    UNIQUE KEY `sets` (`set`,`oai_id`)
);");

sql_query("DROP TABLE IF EXISTS `records`;");
sql_query("CREATE TABLE `records` (
    `id` INT(10) unsigned NOT NULL AUTO_INCREMENT,
    `identity` INT(10) unsigned NOT NULL,
    `title` text,
    `date` timestamp NOT NULL,
    `set` VARCHAR(64) NOT NULL,
    `oai_id` VARCHAR(64) NOT NULL,
    `openaire` VARCHAR(64) NOT NULL,
    `site` VARCHAR(64) NOT NULL,
    `class` VARCHAR(64) NOT NULL,
    `type` VARCHAR(64) NOT NULL,
    `idparent` INT(10) unsigned NOT NULL DEFAULT 0,
    `rank` INT(10) unsigned NOT NULL DEFAULT 0,
    `upd` timestamp NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `ids` (`oai_id`,`identity`),
    INDEX (`set`),
    INDEX (`openaire`),
    INDEX (`set`, `oai_id`)
);");
