HOW TO UPGRADE AMPLETRACKS
==========================

Each new release of Ampletracks is pushed to it's own Git branch e.g. Ampletracks-1-2 for version 1.2 .
New development takes place on the "main" branch so if you pull this you will get the very latest features but there may still be some bugs.

The upgrade process basically involves:
1. Getting hold of the desired version
2. Running the script: /scripts/install.php

N.B. between steps 1 and 2 the code and the database will be out of step. Depending on the nature of the changes in the version of the code you are upgrading to there may be database incompatibility that will not be resolved until the upgrade script is run, so anyone using your site at this intervening stage may experience errors, or unpredictable behaviour. This will not corrupt the data, but might upset users. On most installations we find this isn't an issue, but if you have a particularly large or high profile installation you may wish to tweak you web server configuration to serve up a holding page to users whilst you do the upgrade. Ampletracks does not currently have any built in "maintenance mode" mechanism to do this.

Getting The Latest Version
--------------------------

If your ampletracks installation was performed using the Ampletracks Ansible script, or it was originally obtained via a manual "git clone", then all you need to do is fetch and checkout the desired version as follows:
~~~
# Change to the base directory of your Ampletracks install
# If you used Ansible then this will be /var/www/<your Ampletracks domain>/
cd /your/ampletracks/installation/directory
git fetch
git checkout <desired branch name>
~~~

Alternatively, if you are not using Git you can download the zipped version of the codebase from Github using a URL like this:
~~~
https://github.com/Ampletracks/Ampletracks/archive/refs/heads/<desired branch name>.zip
~~~
You should then unpack this over your existing Ampletracks installation.

Running The Install Script
--------------------------

Once you have the desired version of the codebase installed, you need to run the install script. This will automatically upgrade the database to bring it into line with the new codebase. You can upgrade multiple version in one go - the install script will perform all intervening upgrades in sequence.

As long as you are moving from an older to a newer version the upgrade script will not delete any data. It is safe to run - and re-run the upgrade script. Re-running the install script on on the same version is also safe. The only thing that might result in data loss is running the upgrade script after downgrading to an earlier version - although this will only ever effect data relating to features added in the newer version you are reverting away from.

Having said all this, as a precaution it is advisable to backup the existing database as described below - especially if you don't have automated daily backups in place.

The following command will run the upgrade process

~~~
# This assumes you are currently in the base directory of your Ampletracks installation
# N.B. You must provide the ABSOLUTE PATH to your configuration file
# Run the following as root
php scripts/install.php /absolute/path/to/your/ampletracks/config_file.php
~~~

The install script will generate output showing the status of each database table and also any other version-specific upgrades that have taken place.

Here is an example of the output (in this case none of the database tables happened to be updated):
~~~
Loading config from: /var/www/mydomain.com/config/mydomain.com.php
Working in base directory: /var/www/mydomain.com
Creating temporary MySQL admin user
Database config written to /var/www/mydomain.com/config/Ampletracks.db.php
actionLog             : unchanged
cms                   : unchanged
cmsPage               : unchanged
cmsPageLabel          : unchanged
configuration         : unchanged
dataField             : unchanged
dataFieldDependency   : unchanged
dataFieldType         : unchanged
email                 : unchanged
emailTemplate         : unchanged
emailRecipient        : unchanged
emailAddress          : unchanged
failedLogin           : unchanged
impliedAction         : unchanged
impliedLevel          : unchanged
iodRequest            : unchanged
label                 : unchanged
number                : unchanged
passwordReset         : unchanged
relationship          : unchanged
relationshipPair      : unchanged
relationshipLink      : unchanged
site                  : unchanged
systemAlert           : unchanged
systemData            : unchanged
testLookup            : unchanged
project               : unchanged
record                : unchanged
recordData            : unchanged
recordDataChildLock   : unchanged
recordDataVersion     : unchanged
recordType            : unchanged
role                  : unchanged
rolePermission        : unchanged
userLibrary           : unchanged
user                  : unchanged
userDefaultAnswer     : unchanged
userDefaultAnswerCache: unchanged
userProject           : unchanged
userRecordType        : unchanged
userRecordAccess      : unchanged
userRole              : unchanged
word                  : unchanged
tag                   : unchanged
Running post-setup routine
Removing temporary MySQL admin user
All done
~~~

Backing Up Existing Database
----------------------------
If you have used the Ampletracks-provided Ansible scripts to install Ampletracks then these will have configured automatic login to MySQL and called the database "ampletracks". In this case the following bash command will create a backup of the existing database for you...
~~~
# Assuming default database name and automatic MySQL login
# run the following as root
mysqldump ampletracks > /path/to/backup/dir/ampletracks_backup_$(date +%Y%m%d).sql
~~~

If you need to enter a MySQL username and password, or the database is named differently then you will need to something like this...

~~~
# Alternate command when DB credentials are required
# This will prompt for the password interactively
mysqldump -u <your_username> -p <your_database_name> > /path/to/backup/dir/ampletracks_backup_$(date +%Y%m%d).sql
~~~
