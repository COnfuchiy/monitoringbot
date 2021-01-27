<?php
require (__DIR__.'/sites.php');
require (__DIR__.'/models/Bot.php');


$settings = array(
    /**
     * @var string timezone
     * @see https://www.php.net/manual/en/timezones.php
     */
    "timezone"=>"Europe/Moscow",

    /**
     * @var string telegram bot token
     */
    "token"=>"",

    /**
     * @var string ID of the channel or group where the bot will send messages
     */
    "chat_id"=>"",

    /**
     * @var array sites to check
     * default declare in /sites.php
     * @example [
     *              [
     *                  "domain" => "http://example.com",
     *                  "pages" => [
     *                              "/about"
     *                             ]
     *              ],
     *          ]
     */
    "sites"=>$sites,

    /**
     * @var bool TRUE for print messages in browser
     */
    "debug"=>false,

    /**
     * @var bool TRUE to forbid notify about unavailable sites every time
     */
    "disable_milty_notify"=>true,

    /**
     * @var array with the time intervals (based on 24 hours) when the bot can send messages
     * @example [
     *          "7-12",
     *          "13-15",
     *          "16-18"
     *          ]
     * use only integer number
     */
    "hours"=>["0-24"],

);

$bot = new AdminBot($settings, __DIR__.'/');
$bot->check_sites_stability();

/**
 * INFORMATION
 * @author SemD semm0202@yandex.ru
 * @version 1.2 27.01.2021
 */
