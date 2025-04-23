<?php
// src/addons/XBTTracker/Service/Tmdb/Data.php
namespace Harment\XBTTracker\Service\Tmdb;

use XF\Service\AbstractService;
use Harment\XBTTracker\Entity\TmdbData as TmdbDataEntity;

/**
 * TMDB Data Service
 * Manages storage and retrieval of TMDb data in the database
 * 
 * خدمة بيانات TMDB
 * تدير تخزين واسترجاع بيانات TMDb في قاعدة البيانات
 */
class Data extends AbstractService
{
    /**
     * TMDB API Client
     * عميل واجهة برمجة تطبيقات TMDB
     * 
     * @var Client
     */
    protected $client;
    
    /**
     * Constructor
     * 
     * @param \XF\App $app
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        $this->client = $this->service('Harment\XBTTracker:Tmdb\Client');
    }
    
    /**
     * Get or create TMDB info for a given ID
     * الحصول على أو إنشاء معلومات TMDB لمعرف معين
     *
     * @param int $tmdbId TMDB ID
     * @param string $type Type (movie or tv)
     * @param bool $forceRefresh Force refresh data even if recent
     * @return TmdbDataEntity|null
     */
    public function getInfo($tmdbId, $type = 'movie', $forceRefresh = false)
    {
        if (!$tmdbId) {
            return null;
        }
        
        // Check if we already have this info in database
        /** @var TmdbDataEntity|null $tmdbInfo */
        $tmdbInfo = $this->finder('Harment\XBTTracker:TmdbData')
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
            /** @var TmdbDataEntity $tmdbInfo */
            $tmdbInfo = $this->em()->create('Harment\XBTTracker:TmdbData');
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
        $tmdbInfo->cast = $cast;
        $tmdbInfo->crew = $crew;
        $tmdbInfo->fetch_date = \XF::$time;
        
        $tmdbInfo->save();
        
        return $tmdbInfo;
    }
    
    /**
     * Search for movies/TV shows and create basic data entries for results
     * البحث عن الأفلام/المسلسلات وإنشاء إدخالات بيانات أساسية للنتائج
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
                /** @var TmdbDataEntity|null $tmdbInfo */
                $tmdbInfo = $this->finder('Harment\XBTTracker:TmdbData')
                    ->where('tmdb_id', $tmdbId)
                    ->where('type', $type)
                    ->fetchOne();
                
                if (!$tmdbInfo) {
                    // Create basic entry that can be expanded later
                    /** @var TmdbDataEntity $tmdbInfo */
                    $tmdbInfo = $this->em()->create('Harment\XBTTracker:TmdbData');
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
    
    /**
     * Get info for multiple TMDB IDs (batch processing)
     * الحصول على معلومات لعدة معرفات TMDB (معالجة دفعية)
     * 
     * @param array $tmdbIds Array of TMDB IDs
     * @param string $type Type (movie or tv)
     * @param bool $forceRefresh Force refresh for all
     * @return array Array of TmdbData entities indexed by TMDB ID
     */
    public function getMultipleInfo(array $tmdbIds, $type = 'movie', $forceRefresh = false)
    {
        if (empty($tmdbIds)) {
            return [];
        }
        
        // Filter out duplicates and empty values
        $tmdbIds = array_filter(array_unique($tmdbIds));
        
        // Get existing records
        $existingRecords = $this->finder('Harment\XBTTracker:TmdbData')
            ->where('tmdb_id', $tmdbIds)
            ->where('type', $type)
            ->fetch()
            ->toArray();
            
        $result = [];
        $refreshThreshold = \XF::$time - 86400 * 7; // 7 days
        
        // Process existing records and determine which need refresh
        foreach ($existingRecords as $record) {
            $needsRefresh = $forceRefresh || $record->fetch_date < $refreshThreshold;
            
            if ($needsRefresh) {
                $refreshed = $this->getInfo($record->tmdb_id, $type, true);
                if ($refreshed) {
                    $result[$record->tmdb_id] = $refreshed;
                }
            } else {
                $result[$record->tmdb_id] = $record;
            }
        }
        
        // Find IDs that need to be fetched
        $toFetch = array_diff($tmdbIds, array_keys($result));
        
        // Fetch missing data
        foreach ($toFetch as $tmdbId) {
            $data = $this->getInfo($tmdbId, $type, true);
            if ($data) {
                $result[$tmdbId] = $data;
            }
        }
        
        return $result;
    }
    
    /**
     * Update all TMDb data that is older than specified threshold
     * تحديث جميع بيانات TMDb الأقدم من الحد المحدد
     * 
     * @param int $ageInDays Age threshold in days
     * @param int $limit Maximum number of records to update
     * @return int Number of records updated
     */
    public function updateStaleData($ageInDays = 30, $limit = 50)
    {
        $staleThreshold = \XF::$time - 86400 * $ageInDays;
        
        $staleTmdbData = $this->finder('Harment\XBTTracker:TmdbData')
            ->where('fetch_date', '<', $staleThreshold)
            ->order('fetch_date')
            ->limit($limit)
            ->fetch();
        
        $updatedCount = 0;
        
        foreach ($staleTmdbData as $tmdbData) {
            $updated = $this->getInfo($tmdbData->tmdb_id, $tmdbData->type, true);
            if ($updated) {
                $updatedCount++;
            }
        }
        
        return $updatedCount;
    }
    
    /**
     * Get or fetch popular movies
     * الحصول على أو جلب الأفلام الشائعة
     * 
     * @param int $limit Number of movies to return
     * @param bool $forceRefresh Force refresh from API
     * @return array
     */
    public function getPopularMovies($limit = 10, $forceRefresh = false)
    {
        // Check for cached results
        $cacheKey = 'xbtTracker_popularMovies';
        $cache = $this->app->cache();
        
        if (!$forceRefresh && $cache) {
            $cached = $cache->get($cacheKey);
            if ($cached && is_array($cached)) {
                return array_slice($cached, 0, $limit);
            }
        }
        
        // Fetch from API
        $results = $this->client->getPopularMovies();
        if (!$results) {
            return [];
        }
        
        // Store in cache (12 hours)
        if ($cache) {
            $cache->set($cacheKey, $results, 43200);
        }
        
        // Process and store entities
        $processed = [];
        foreach ($results as $result) {
            if (isset($result['id'])) {
                $tmdbInfo = $this->getInfo($result['id'], 'movie', $forceRefresh);
                if ($tmdbInfo) {
                    $processed[] = [
                        'data' => $result,
                        'entity' => $tmdbInfo
                    ];
                }
            }
        }
        
        return array_slice($processed, 0, $limit);
    }
}