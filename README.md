# OAI-PHP server for lodel CMS

This is a server that respond to [OAI-PMH](http://www.openarchives.org/OAI/2.0/openarchivesprotocol.htm) protocol specification made to work with [lodel CMS](http://lodel.org/).
It uses a core OAI-PMH library and this middleware to fetch data from lodel database.

Available metadata formats are oai_dc, qdc and mets.

## INSTALL
Clone OAI-PHP server to a directory listed in your php import path. Use the lodel branch.

You **must** use lodel branch from oai_pmh library and master branch from oai_pmh_lodel or use the same tagged version from both repositories for them to be compatible.
```
cd /usr/share/php/
git clone https://github.com/edinum/oai_pmh.git
cd oai_pmh
git checkout lodel
```

Clone this repository inside your lodel installation, and configure it.
```
cd /var/www/lodel/
git clone https://github.com/edinum/oai_pmh_lodel.git oai
cd oai
cp config.php.txt config.php
nano config.php
```

Create the needed database, launch setup.php script and fill your database. You **must** use the same database prefix as your lodel installation. Database name is by default oai-pmh (prefixed by lodel_), but it can be changed using 'lodelOAIsite' configuration.
```
mysql
> CREATE DATABASE `lodel_oai-pmh`;
> GRANT ALL PRIVILEGES ON `lodel_oai-pmh` . * TO 'lodel'@'localhost';
php tools/setup.php
php tools/update_db.php
```

Add options to your lodel sites to configure them to export their documents. You should have extra.oai_id (name of the set) and extra.doi_prefixe configured. You can use [lodel-options-extra](https://github.com/edinum/lodel-options-extra.git) script to create those options for you.

Add a cronjob to update your database every hour
```
12 * * * * php /var/www/lodel/oai/tools/update_db.php
```

Your OAI-PHP server will be available at `http://your-lodel-instance/oai/?verb=ListSets`

If you want to change URL, just change the name of the directory.

## Upgrade
You must upgrade oai_pmh and oai_pmh_lodel at the same time and use the same **x.y**.z tagged version. `lodel` and `master` branch are compatible. Read CHANGELOG.md for detailed changes.
### 0.1.0 to 0.2.x
You have to reset your database to allow upgrade of the SQL schema using `php tools/setup.php` and then `php tools/update_db.php`

## How it works
This tool connects to your lodel installation and database using its lodelconfig.php configuration.

It uses its own database (lodel_oai-pmh) to save Sets (lodel sites) and Records (documents).
 
Update of the database is done by update_db.php script that should run in the background using crontab. It takes care of newly published sites or documents and take care of deletion (deleted status is not implemented in OAI-PMH).

## Thanks
Thanks to [Université Jean Moulin Lyon 3](https://www.univ-lyon3.fr/) who paid for developing this tool, and special thanks to Jean-Luc de Ochandiano and [Olivier Crouzet](https://github.com/oliviercrouzet).

Thanks to [Daniel Neis Araujo](https://github.com/danielneis) for his OAI-PMH librairie.

Thanks to [Thomas Brouard](https://github.com/brrd) for his so cool specification for this work.
