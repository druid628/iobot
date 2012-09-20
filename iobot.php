<?php
/**
 * The officially-unofficial iostudio IRC bot.
 *
 * @author Bill Israel <bill.israel@gmail.com>
 */
require __DIR__ . '/vendor/autoload.php';

use Philip\Philip;
use Philip\IRC\Response;
use Symfony\Component\Process\Process;

$config = array(
    "hostname"   => "irc.freenode.net",
    "servername" => "iostudio.com",
    "port"       => 6667,
    "username"   => "sismo",
    "realname"   => "iostudio Sismo IRC Bot",
    "nick"       => "io-sismo",
    "channels"   => array( '#iostudio-dev', '#iostudio-vip' ),
    "admins"     => array( 'druid628' ),
    "debug"      => false,
    "log"        => __DIR__ . '/iobot.log',
    "sismo_dir"  => "/sismo"
);

// Create the bot, passing in configuration options
$bot = new Philip($config);

// Load my plugins
$bot->loadPlugins(array('Admin', 'SwearJar', 'ImageMe', 'CannedResponse'));


// Say hi back to the nice people
$hi_re = "/^(hi|hello|hey|yo|was+up|waz+up|werd|hai|lo) {$config['nick']}$/";
$bot->onChannel($hi_re, function($request, $matches) {
    return Response::msg($request->getSource(), 'Hello, ' . $request->getSendingUser() . '!');
});


// Spit out a random meme (thanks @inky!!)
$bot->onChannel("/^!meme$/", function($request, $matches) {
    $meme = file_get_contents('http://api.automeme.net/text?lines=1');
    return Response::msg($request->getSource(), $meme);
});


// Gives high-fives
$bot->onChannel("/^!hf (\w+)$/", function($request, $matches) use ($config) {
    $who = $matches[0];

    // Better way of having the bot high-five itself.
    if ($who === $config['nick']) {
        $who = 'itself';
    }

    return Response::action($request->getSource(), "gives $who a high-five!");
});


// You can't have a bot without the ability to fire people...
$fired = array();
$bot->onChannel("/^!fire( \w+)?$/", function($request, $matches) use (&$fired, $config) {
    $who = empty($matches) ? 'Jarvis' : trim($matches[0]);
    $normal = strtolower($who);

    // The bot shouldn't fire itself, that's just silly
    if ($who === $config['nick']) {
        return Response::msg($request->getSource(), "I'm sorry {$request->getSendingUser()}, I can't let you do that.");
    }

    if (!in_array($normal, array_keys($fired))) {
        $fired[$normal] = 0;
    }

    $count = ++$fired[$normal];
    $times = ($count === 1) ? 'time' : 'times';
    return Response::msg($request->getSource(), "$who, you're fired! (that's $count $times so far)");
});


// Look for URLs, shame people who repost them.
$urls  = array();
$url_re = '/(((http|https):?\/\/)?(([\d\w]|%[a-fA-f\d]{2,2})+(:([\d\w]|%[a-fA-f\d]{2,2})+)?@)?([\d\w][-\d\w]{0,253}[\d\w]\.)+[\w]{2,4}(:[\d]+)?(\/([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)*(\?(&?([-+_~.\d\w]|%[a-fA-f\d]{2,2})?=?)*)?(#([-+_~.\d\w]|%[a-fA-f\d]{2,2})*)?)/';
$bot->onChannel($url_re, function($request, $matches) use (&$urls) {
        $url = $matches[0];
        $normal = rtrim(preg_replace("/https?:\/\/(www\.)?/", '', $url), '/');
        $source = $request->getSource();

        if (isset($urls[$source]) && in_array($normal, array_keys($urls[$source]))) {
            $who = $urls[$source][$normal];
            return Response::msg($source, "REPOST!! ($who already posted that)");
        } else {
            $urls[$source][$normal] = $request->getSendingUser();
        }
});


// Stock prices
$bot->onChannel('/^\$(\w+)$/', function($request, $matches) {
    $stock = strtoupper($matches[0]);
    $price = trim(file_get_contents("http://download.finance.yahoo.com/d/quotes.csv?s=${stock}&f=b2"));
    return Response::msg($request->getSource(), "Current $stock price: $price -- http://google.com/finance?q=$stock");
});


// Sismo
$sismoCommandsArray = array( 'build' => "Building", 'status' => "Status of" );
$bot->onChannel("/^!(build|status) \b([\w-_]+)\b/", function($request, $matches) use ($config, $sismoCommandsArray) {
    // execute Sismo stuff
    $sismo_func = ($matches[0] === "status") ? "output" : $matches[0];
    $sismo_cmd = sprintf("php %s/sismo %s %s",  $config['sismo_dir'], $sismo_func, $matches[1]); 
    $process = new Process($sismo_cmd);
    $process->setTimeout(3600);
    $process->run();

    $output = "SISMO: " . $sismoCommandsArray[$matches[0]] . " $matches[1]";
    $output .= ": " . $process->getOutput();

    return Response::msg($request->getSource(), $output);
});

// Ready, set, go.
$bot->run();

