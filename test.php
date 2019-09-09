<?php
/**
 * Created by PhpStorm.
 * User: wangyibo
 * Date: 2019/9/2
 * Time: 17:04
 */
require_once "./vendor/autoload.php";
use Adocwang\PhpSteamAppTicket;

$ticket = '080110a9ad81ac0d180020462a70b5eec8dff76fb9e88151984723d8f2ec38e877fb1d9c46724dfced064063f72e95f620931f70f121702edacf2d04fd7ad356c6a7bf13fc4f617b5cf9c4e36fd6003fe668cd3feee1c8adc20776e987c79b17947381d8ebff941663dc9edf803972f04228a4a68c17f0d79dd67b744803';
$key = '1a4b8a2fe2f3bce4532a02653e7f68fd130b3d30ba28284b4263348f0b19cb42';
$result = PhpSteamAppTicket\SteamEncryptedAppTicket::parse($ticket, $key);
var_dump($result);