<?php
// src/addons/XBTTracker/Service/Tmdb/Client.php
namespace XBTTracker\Service\Tmdb;

use XF\Service\AbstractService;
use GuzzleHttp\Exception\RequestException;

class Client extends AbstractService
{
    /**
     * Base URL for TMDB API
     */
    const API_URL = 'https://api.themoviedb.org/3';
    
    /**
     * @var string
     */
    protected $apiKey;
    
    /**
     * Constructor
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        $this->apiKey = $this->options()->xbtTrackerTmdbApiKey;
    }
    
    /**
     * Search for movies/TV shows in TMDB
     *
     * @param string $query Search query
     * @param string $type Type (movie or tv)
     * @return array|null
     */
    public function search($query, $type = 'movie')
    {
        if (!$this->apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/search/' . $type;
        $params = [
            'api_key' => $this->apiKey,
            'query' => $query,
            'include_adult' => 'false',
            'language' => 'en-US'
        ];
        
        $response = $this->makeRequest($endpoint, $params);
        
        if ($response && isset($response['results'])) {
            return $response['results'];
        }
        
        return null;
    }
    
    /**
     * Get details for a movie/TV show from TMDB
     *
     * @param int $id TMDB ID
     * @param string $type Type (movie or tv)
     * @return array|null
     */
    public function getDetails($id, $type = 'movie')
    {
        if (!$this->apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/' . $type . '/' . $id;
        $params = [
            'api_key' => $this->apiKey,
            'language' => 'en-US',
            'append_to_response' => 'credits'
        ];
        
        return $this->makeRequest($endpoint, $params);
    }
    
    /**
     * Get translations for a movie/TV show
     *
     * @param int $id TMDB ID
     * @param string $type Type (movie or tv)
     * @param string $language ISO language code (default: 'ar')
     * @return array|null
     */
    public function getTranslations($id, $type = 'movie', $language = 'ar')
    {
        if (!$this->apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/' . $type . '/' . $id . '/translations';
        $params = [
            'api_key' => $this->apiKey
        ];
        
        $response = $this->makeRequest($endpoint, $params);
        
        if ($response && isset($response['translations'])) {
            if ($language) {
                // Return specific language
                foreach ($response['translations'] as $translation) {
                    if ($translation['iso_639_1'] == $language) {
                        return $translation;
                    }
                }
                return null;
            }
            
            return $response['translations'];
        }
        
        return null;
    }
    
    /**
     * Get credits (cast and crew) for a movie/TV show
     *
     * @param int $id TMDB ID
     * @param string $type Type (movie or tv)
     * @return array|null
     */
    public function getCredits($id, $type = 'movie')
    {
        if (!$this->apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/' . $type . '/' . $id . '/credits';
        $params = [
            'api_key' => $this->apiKey,
            'language' => 'en-US'
        ];
        
        return $this->makeRequest($endpoint, $params);
    }
    
    /**
     * Make a request to the TMDB API
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|null
     */
    protected function makeRequest($endpoint, array $params = [])
    {
        try {
            $client = $this->app->http()->client();
            $response = $client->get($endpoint, [
                'query' => $params
            ]);
            
            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            // Log error
            \XF::logError('TMDB API Error: ' . $e->getMessage());
            return null;
        }
    }
}