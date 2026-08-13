<?php

namespace Classes;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Exception;

class Weather
{
    private Client $client;
    private float $latitude;
    private float $longitude;

    /**
     * @param float $latitude Default is Ravensburg, Germany
     * @param float $longitude Default is Ravensburg, Germany
     */
    public function __construct(float $latitude = 47.7833, float $longitude = 9.6167)
    {
        $this->client = new Client();
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * Fetches current weather data.
     *
     * @return array
     * @throws Exception
     */
    public function getCurrentWeather(): array
    {
        $url = "https://api.open-meteo.com/v1/forecast?latitude={$this->latitude}&longitude={$this->longitude}&current=temperature_2m,wind_speed_10m,weather_code";

        try {
            $response = $this->client->request('GET', $url);
            $data = json_decode($response->getBody()->getContents(), true);

            if (!isset($data['current'])) {
                throw new Exception("Invalid API response.");
            }

            return [
                'temperature' => $data['current']['temperature_2m'],
                'unit' => $data['current_units']['temperature_2m'],
                'wind_speed' => $data['current']['wind_speed_10m'],
                'wind_unit' => $data['current_units']['wind_speed_10m'],
                'time' => $data['current']['time'],
            ];

        } catch (GuzzleException $e) {
            throw new Exception("Network error while fetching weather: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error processing weather data: " . $e->getMessage());
        }
    }
}
