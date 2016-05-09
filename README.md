mysql-migrate
======================

Version control your database with tiny one migration script.

Handles migrating your MySQL database schema upwards. Easy to use in different environments: multiple developers, staging, production. Just run migrate and it will move the database schema to the latest version. Will detect conflicts and print out errors.

How to use
======================

For installation inside your project directory:

    git clone https://github.com/idnan/mysql-migrate

To add a new migration:

    php mysql-migrate/migrate.php make [name-without-spaces]

To migrate to the latest version:

    php mysql-migrate/migrate.php run

The migrate script will create a ".version" file in the directory from which it is run. For this reason, I recommend running the migration script from one level up. Do not checkin the version file since it needs to be local!

When you add a new migration, a new script file will be created under the "migrations/" folder and this folder should be checked into the code repository. This way other environments can migrate the database using them.

More info
======================

To setup your database information, make sure to run:

    cp config.php.sample config.php
    vim config.php

The database version is tracked locally using file ".version".

License
======================
MIT &copy; [Adnan Ahmed](https://github.com/idnan)