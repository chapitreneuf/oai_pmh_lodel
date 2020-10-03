<?php

#
# CLI script to create tables for lodel oai-php database
#
# Database must exists
# Warning: running it twice will reset your database
#

if (php_sapi_name() != "cli") {
    print "Run this from cli !";
    exit(0);
}

require_once('lodel_connect.php');
lodel_init();

global $database_prefix;
if (!connect_site('oai-pmh')) {
    $db_user = C::get('dbusername', 'cfg');
    print "\n";
    print "Database oai-pmp does not exists, you must create it. \n";
    print " Connect to MySQL and type: \n";
    print "\n";
    print "CREATE DATABASE `{$database_prefix}_oai-pmh`; \n";
    print "GRANT ALL PRIVILEGES ON `{$database_prefix}_oai-pmh` . * TO '$db_user'@'localhost'; \n";
    print "\n";
}

global $db;
$ok = $db->execute("DROP TABLE IF EXISTS `sets`;");
$ok = $db->execute("CREATE TABLE `sets` (
    `id` INT(10) unsigned NOT NULL AUTO_INCREMENT,
    `set` VARCHAR(64) NOT NULL,
    `oai_id` VARCHAR(64) NOT NULL,
    `name` VARCHAR(64) NOT NULL,
    `title` VARCHAR(64) NOT NULL,
    `upd` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`),
    UNIQUE KEY `sets` (`set`,`name`)
);");
_log_debug($ok);
