<?php
// src/addons/XBTTracker/Service/Tmdb/Client.php
namespace XBTTracker\Service\Tmdb;

use XF\Service\AbstractService;

class Client extends AbstractService
{
    /**
     * Base URL for TMDB API
     */
    const API_URL = 'https://api.themoviedb.org/3';
    
    /**
     * Search for movies/TV shows in TMDB
     *
     * @param string $query Search query
     * @param string $type Type (movie or tv)
     * @return array|null
     */
    public function search($query, $type = 'movie')
    {
        $apiKey = $this->app->options()->xbtTrackerTmdbApiKey;
        if (!$apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/search/' . $type;
        $params = [
            'api_key' => $apiKey,
            'query' => $query,
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
        $apiKey = $this->app->options()->xbtTrackerTmdbApiKey;
        if (!$apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/' . $type . '/' . $id;
        $params = [
            'api_key' => $apiKey,
            'language' => 'en-US',
            'append_to_response' => 'credits'
        ];
        
        return $this->makeRequest($endpoint, $params);
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
        $apiKey = $this->app->options()->xbtTrackerTmdbApiKey;
        if (!$apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/' . $type . '/' . $id . '/credits';
        $params = [
            'api_key' => $apiKey,
            'language' => 'en-US'
        ];
        
        return $this->makeRequest($endpoint, $params);
    }
    
    /**
     * Get translations for a TMDB item and update the entity
     *
     * @param \XBTTracker\Entity\TmdbData $tmdbData
     * @return bool
     */
    public function getTranslation(\XBTTracker\Entity\TmdbData $tmdbData)
    {
        $apiKey = $this->app->options()->xbtTrackerTmdbApiKey;
        if (!$apiKey) {
            return false;
        }
        
        $endpoint = self::API_URL . '/' . $tmdbData->type . '/' . $tmdbData->tmdb_id . '/translations';
        $params = [
            'api_key' => $apiKey
        ];
        
        $response = $this->makeRequest($endpoint, $params);
        
        if ($response && isset($response['translations'])) {
            // Look for Arabic translation
            foreach ($response['translations'] as $translation) {
                if ($translation['iso_639_1'] == 'ar') {
                    if (isset($translation['data']['title'])) {
                        $tmdbData->title_ar = $translation['data']['title'];
                    } else if (isset($translation['data']['name'])) {
                        $tmdbData->title_ar = $translation['data']['name'];
                    }
                    
                    if (isset($translation['data']['overview'])) {
                        $tmdbData->overview_ar = $translation['data']['overview'];
                    }
                    
                    return true;
                }
            }
        }
        
        return false;
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
        $url = $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            return json_decode($response, true);
        }
        
        return null;
    }
}
