<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Classes\Weather;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class WeatherTest extends TestCase
{
    public function testGetCurrentWeatherSuccess()
    {
        // Mock API response
        $mockData = [
            'current' => [
                'temperature_2m' => 25.5,
                'wind_speed_10m' => 12.0,
                'time' => '2026-08-13T15:00',
            ],
            'current_units' => [
                'temperature_2m' => '°C',
                'wind_speed_10m' => 'km/h',
            ]
        ];

        $mock = new MockHandler([
            new Response(200, [], json_encode($mockData))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $weather = new Weather(47.7833, 9.6167, $client);
        $result = $weather->getCurrentWeather();

        $this->assertEquals(25.5, $result['temperature']);
        $this->assertEquals('°C', $result['unit']);
        $this->assertEquals(12.0, $result['wind_speed']);
        $this->assertEquals('km/h', $result['wind_unit']);
        $this->assertEquals('2026-08-13T15:00', $result['time']);
    }

    public function testGetCurrentWeatherInvalidResponse()
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode(['foo' => 'bar']))
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $weather = new Weather(47.7833, 9.6167, $client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Invalid API response.");
        $weather->getCurrentWeather();
    }

    public function testGetCurrentWeatherNetworkError()
    {
        $mock = new MockHandler([
            new Response(500, [], 'Internal Server Error')
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $weather = new Weather(47.7833, 9.6167, $client);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Network error while fetching weather");
        $weather->getCurrentWeather();
    }
}
