Still a work in progress.

To install all dependencies, run ./composer.phar install
You will need the PDO module enabled in PHP as well as mysqlnd. Tested with PHP version 5.6
Copy the included config `config/config.ini.default` as `config/config.ini` and edit with your desired options.

Run the bot like so:
```
php espybot.php
```

If in debug mode, all websocket connection messages are shown as well as chat and what the bot is doing.
