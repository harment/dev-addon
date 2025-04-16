<?php
// src/addons/XBTTracker/Service/Tmdb/Data.php
namespace Harment\XBTTracker\Service\Tmdb;

use XF\Service\AbstractService;

class Data extends AbstractService
{
    /**
     * @var Client
     */
    protected $client;
    
    /**
     * Constructor
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        $this->client = $this->service('XBTTracker:Tmdb\Client');
    }
    
    /**
     * Get or create TMDB info for a given ID
     *
     * @param int $tmdbId TMDB ID
     * @param string $type Type (movie or tv)
     * @param bool $forceRefresh Force refresh data even if recent
     * @return \XBTTracker\Entity\TmdbData|null
     */
    public function getInfo($tmdbId, $type = 'movie', $forceRefresh = false)
    {
        // Check if we already have this info in database
        $tmdbInfo = $this->finder('XBTTracker:TmdbData')
            ->where('tmdb_id', $tmdbId)
            ->where('type', $type)
            ->fetchOne();
            
        // If we have recent data, return it
        if (!$forceRefresh && $tmdbInfo && $tmdbInfo->fetch_date > (\XF::$time - 86400 * 7)) {
            return $tmdbInfo;
        }
        
        // Otherwise fetch new data
        $data = $this->client->getDetails($tmdbId, $type);
        
        if (!$data || !isset($data['id'])) {
            return $tmdbInfo; // Return existing data or null
        }
        
        // Get translations for Arabic
        $titleAr = '';
        $overviewAr = '';
        $translation = $this->client->getTranslations($tmdbId, $type, 'ar');
        
        if ($translation && isset($translation['data'])) {
            $titleAr = $translation['data']['title'] ?? ($translation['data']['name'] ?? '');
            $overviewAr = $translation['data']['overview'] ?? '';
        }
        
        // Prepare credits data
        $cast = [];
        $crew = [];
        
        if (isset($data['credits']['cast']) && is_array($data['credits']['cast'])) {
            $cast = array_slice($data['credits']['cast'], 0, 15);
        }
        
        if (isset($data['credits']['crew']) && is_array($data['credits']['crew'])) {
            $crew = array_slice($data['credits']['crew'], 0, 15);
        }
        
        // Create or update TMDB data
        if (!$tmdbInfo) {
            $tmdbInfo = $this->em()->create('XBTTracker:TmdbData');
            $tmdbInfo->tmdb_id = $tmdbId;
            $tmdbInfo->type = $type;
        }
        
        $tmdbInfo->title = $data['title'] ?? ($data['name'] ?? '');
        $tmdbInfo->title_ar = $titleAr;
        $tmdbInfo->overview = $data['overview'] ?? '';
        $tmdbInfo->overview_ar = $overviewAr;
        $tmdbInfo->poster_path = $data['poster_path'] ?? '';
        $tmdbInfo->backdrop_path = $data['backdrop_path'] ?? '';
        $tmdbInfo->release_date = $data['release_date'] ?? ($data['first_air_date'] ?? '');
        $tmdbInfo->vote_average = $data['vote_average'] ?? 0;
        $tmdbInfo->cast = json_encode($cast);
        $tmdbInfo->crew = json_encode($crew);
        $tmdbInfo->fetch_date = \XF::$time;
        
        $tmdbInfo->save();
        
        return $tmdbInfo;
    }
    
    /**
     * Search for movies/TV shows and create basic data entries for results
     *
     * @param string $query Search query
     * @param string $type Type (movie or tv)
     * @return array
     */
    public function search($query, $type = 'movie')
    {
        $results = $this->client->search($query, $type);
        
        if (!$results) {
            return [];
        }
        
        // Process results to ensure we have database entries for them
        foreach ($results as $key => $result) {
            if (isset($result['id'])) {
                $tmdbId = $result['id'];
                
                // Check if we already have this info in database
                $tmdbInfo = $this->finder('XBTTracker:TmdbData')
                    ->where('tmdb_id', $tmdbId)
                    ->where('type', $type)
                    ->fetchOne();
                
                if (!$tmdbInfo) {
                    // Create basic entry that can be expanded later
                    $tmdbInfo = $this->em()->create('XBTTracker:TmdbData');
                    $tmdbInfo->tmdb_id = $tmdbId;
                    $tmdbInfo->type = $type;
                    $tmdbInfo->title = $result['title'] ?? ($result['name'] ?? '');
                    $tmdbInfo->overview = $result['overview'] ?? '';
                    $tmdbInfo->poster_path = $result['poster_path'] ?? '';
                    $tmdbInfo->backdrop_path = $result['backdrop_path'] ?? '';
                    $tmdbInfo->release_date = $result['release_date'] ?? ($result['first_air_date'] ?? '');
                    $tmdbInfo->vote_average = $result['vote_average'] ?? 0;
                    $tmdbInfo->fetch_date = \XF::$time;
                    $tmdbInfo->save();
                }
                
                // Add database entity reference to results
                $results[$key]['entity'] = $tmdbInfo;
            }
        }
        
        return $results;
    }
}