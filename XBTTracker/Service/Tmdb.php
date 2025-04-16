<?php
// src/addons/XBTTracker/Service/Tmdb.php
namespace Harment\XBTTracker\Service;

use XF\Service\AbstractService;

/**
 * Legacy service class for backward compatibility
 * This forwards requests to the new Tmdb\Client and Tmdb\Data services
 */
class Tmdb extends AbstractService
{
    /**
     * @var \XBTTracker\Service\Tmdb\Client
     */
    protected $client;
    
    /**
     * @var \XBTTracker\Service\Tmdb\Data
     */
    protected $data;
    
    /**
     * Constructor
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        
        $this->client = $this->service('XBTTracker:Tmdb\Client');
        $this->data = $this->service('XBTTracker:Tmdb\Data');
    }
    
    /**
     * Search TMDB
     *
     * @param string $query Search query
     * @param string $type Type (movie or tv)
     * @return array
     */
    public function search($query, $type = 'movie')
    {
        $results = $this->client->search($query, $type);
        return $results ?: [];
    }
    
    /**
     * Get TMDB info
     *
     * @param int $tmdbId TMDB ID
     * @param string $type Type (movie or tv)
     * @return \XBTTracker\Entity\TmdbData|null
     */
    public function getInfo($tmdbId, $type = 'movie')
    {
        return $this->data->getInfo($tmdbId, $type);
    }
}