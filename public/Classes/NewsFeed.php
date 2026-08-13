<?php

namespace Classes;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use SimpleXMLElement;
use Exception;

class NewsFeed
{
    private string $url;
    private Client $client;

    public function __construct(string $url = 'http://newsrss.bbc.co.uk/rss/newsonline_uk_edition/front_page/rss.xml')
    {
        $this->url = $url;
        $this->client = new Client();
    }

    /**
     * Fetches and parses the RSS feed.
     *
     * @return array
     * @throws Exception
     */
    public function getArticles(): array
    {
        try {
            $response = $this->client->request('GET', $this->url);
            $xmlContent = $response->getBody()->getContents();

            if (empty($xmlContent)) {
                throw new Exception("Received empty response from the feed.");
            }

            $xml = simplexml_load_string($xmlContent);

            if ($xml === false) {
                throw new Exception("Failed to parse XML content.");
            }

            $articles = [];
            foreach ($xml->channel->item as $item) {
                $articles[] = [
                    'title' => (string)$item->title,
                    'description' => (string)$item->description,
                    'link' => (string)$item->link,
                ];
            }

            return $articles;

        } catch (GuzzleException $e) {
            throw new Exception("Network error while fetching the feed: " . $e->getMessage());
        } catch (Exception $e) {
            throw new Exception("Error processing news feed: " . $e->getMessage());
        }
    }

    /**
     * @deprecated Use getArticles() and handle display in the view layer.
     */
    public function fetch()
    {
        try {
            $articles = $this->getArticles();
            foreach ($articles as $article) {
                echo '<h2>' . htmlspecialchars($article['title']) . '</h2>';
                echo '<p>' . htmlspecialchars($article['description']) . '</p>';
                echo '<a href="' . htmlspecialchars($article['link']) . '">Read more</a>';
            }
        } catch (Exception $e) {
            echo '<p style="color: red;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
