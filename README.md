## About
Espybot is a Discord bot written in PHP, using MySQL for chat logs and configuration storage.

## Requirements
- OS: Linux 64-bit (Windows is difficult to set up this bot on and voice support does not work.)
- PHP: Version 5.6 64-bit (32bit does not work due to IDs being 64bit integers.)
- PHP Modules: mysqlnd, PDO, pdo_mysql
- MySQL: MySQL 5.6+ (Tested with MariaDB 10)

## Installing
To install all dependencies, run the following commands:
```
cd /path/to/project/
./composer.phar install
```

To setup the database, run the following commands:
```
cd /path/to/project/
echo "create database espybot" | mysql -u root -p
mysql -u root -p espybot < espybot.sql
```

Copy the included config `config/config.ini.default` as `config/config.ini` and edit with your desired options. This assumes you have registered a BOT account with Discord and have the token already.

Now to start the bot:
```
cd /path/to/project/
php espybot.php
```

## Troubleshooting
If debug mode is enabled in `config.ini`, all websocket connection messages are shown as well as chat and what the bot is doing. This can be used to help diagnose why the bot isn't responding or not connecting.
