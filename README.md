Bot for monitoring your sites and domains. Use the task scheduler for index.php at a given interval. Use the settings in the index.php:
1. timezone - your timezone. Default "Europe/Moscow"
2. token - telegram bot token
3. chat_id - ID of the channel or group where the bot will send messages
4. sites - array sites to check. Default declare in /sites.php. Example:
```php
$sites = [
    [
        "domain" => "http://example.com",
        "pages" => [
            "/about"
        ]
    ],
];
```
5. debug - TRUE for print messages in browser. Default FALSE
6. disable_milty_notify - TRUE to forbid notify about unavailable sites every time. Default TRUE
7. hours - array with the time intervals (based on 24 hours) when the bot can send messages. Example:
```php
["7-12", "13-15", "16-18"]
```
