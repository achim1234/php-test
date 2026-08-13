<?php

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Classes\User;
use Classes\Counter;
use Classes\FileHandler;
use Classes\ApiHandler;
use Classes\FormHandler;
use Classes\NewsFeed;
use Classes\Math;
use Classes\Weather;

$math = new Math();
$testNumbers = [4, 7, 10, 15, 22, -2, -3, 0];

echo "<h1>Math Class Test</h1>";
foreach ($testNumbers as $number) {
    echo "Number $number is " . ($math->isEven($number) ? 'Even' : 'Odd') . "<br>";
}

echo "<h2>Pi Calculation</h2>";
echo "Pi with 100 decimal places: " . $math->getPi() . "<br>";

echo "<h2>Rectangle Area Calculation</h2>";
$rectangles = [
    ['a' => 5.5, 'b' => 10],
    ['a' => 3, 'b' => 4],
    ['a' => 10.2, 'b' => 2.5]
];
foreach ($rectangles as $rect) {
    $a = $rect['a'];
    $b = $rect['b'];
    echo "Area of rectangle with a=$a and b=$b is: " . $math->calculateRectangleArea($a, $b) . "<br>";
}

echo "<h2>Weather in Ravensburg</h2>";
$weather = new Weather();
try {
    $currentWeather = $weather->getCurrentWeather();
    echo "Current Temperature: " . $currentWeather['temperature'] . " " . $currentWeather['unit'] . "<br>";
    echo "Wind Speed: " . $currentWeather['wind_speed'] . " " . $currentWeather['wind_unit'] . "<br>";
    echo "Time: " . $currentWeather['time'] . "<br>";
} catch (Exception $e) {
    echo "Could not fetch weather data: " . $e->getMessage();
}

$newsFeed = new NewsFeed();
$newsFeed->fetch();
//$newsFeed->getArticles();













//die(__DIR__);
//
//
//$apiHandler = new ApiHandler('https://api.open-meteo.com/v1/forecast?latitude=48.13&longitude=11.58&current=temperature_2m,wind_speed_10m');
//
//$formHandler = new FormHandler();
//
//echo($formHandler->createFrom());
//
//
//
//if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//    $handler = new FormHandler();
//    $handler->handle();
//}



//$user = new User();
//$counter = new Counter();
//
//echo($user->hello());
//echo($counter->count(1));
//
//
//echo('<br>');
//echo('<br>');
//echo('<br>');
//
//
//$fileHandler = new FileHandler();
//#$filename = 'files/testäääääää.txt';
//$filename = 'filesddd/test.txt';
//$fileHandler->createNewFile($filename);
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');
//$fileHandler->addTextToFile($filename, 'This is a test');




