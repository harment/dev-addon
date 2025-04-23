<?php
// src/addons/Harment/XBTTracker/Service/Tmdb.php
namespace Harment\XBTTracker\Service;

use XF\Service\AbstractService;

/**
 * Legacy service class for backward compatibility
 * This forwards requests to the new Tmdb\Client and Tmdb\Data services
 * 
 * @package Harment\XBTTracker\Service
 */
class Tmdb extends AbstractService
{
    /**
     * @var \Harment\XBTTracker\Service\Tmdb\Client
     */
    protected $client;
    
    /**
     * @var \Harment\XBTTracker\Service\Tmdb\Data
     */
    protected $data;
    
    /**
     * Constructor
     * 
     * @param \XF\App $app Application instance
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        
        $this->client = $this->service('Harment\XBTTracker:Tmdb\Client');
        $this->data = $this->service('Harment\XBTTracker:Tmdb\Data');
    }
    
    /**
     * Search TMDB for movies or TV shows
     *
     * @param string $query Search query
     * @param string $type Type (movie or tv)
     * @return array Results from TMDB search
     */
    public function search($query, $type = 'movie')
    {
        try {
            $results = $this->client->search($query, $type);
            return $results ?: [];
        } catch (\Exception $e) {
            \XF::logException($e);
            return [];
        }
    }
    
    /**
     * Get TMDB info for a specific ID
     *
     * @param int $tmdbId TMDB ID
     * @param string $type Type (movie or tv)
     * @return \Harment\XBTTracker\Entity\TmdbData|null The TMDB data entity or null if not found
     */
    public function getInfo($tmdbId, $type = 'movie')
    {
        try {
            return $this->data->getInfo($tmdbId, $type);
        } catch (\Exception $e) {
            \XF::logException($e);
            return null;
        }
    }
    
    /**
     * Get or fetch movie details from TMDB
     * 
     * @param int $tmdbId TMDB ID
     * @return array|null Movie details or null on failure
     */
    public function getMovieDetails($tmdbId)
    {
        try {
            return $this->client->getDetails($tmdbId, 'movie');
        } catch (\Exception $e) {
            \XF::logException($e);
            return null;
        }
    }
    
    /**
     * Get or fetch TV show details from TMDB
     * 
     * @param int $tmdbId TMDB ID
     * @return array|null TV show details or null on failure
     */
    public function getTvDetails($tmdbId)
    {
        try {
            return $this->client->getDetails($tmdbId, 'tv');
        } catch (\Exception $e) {
            \XF::logException($e);
            return null;
        }
    }
}