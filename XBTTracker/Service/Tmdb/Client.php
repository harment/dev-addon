<?php
// src/addons/XBTTracker/Service/Tmdb/Client.php
namespace Harment\XBTTracker\Service\Tmdb;

use XF\Service\AbstractService;
use GuzzleHttp\Exception\RequestException;

/**
 * TMDB API Client Service
 * Handles communication with The Movie Database API
 * 
 * خدمة عميل TMDB API
 * تتعامل مع واجهة برمجة تطبيقات قاعدة بيانات الأفلام
 */
class Client extends AbstractService
{
    /**
     * Base URL for TMDB API
     * العنوان الأساسي لواجهة برمجة تطبيقات TMDB
     * 
     * @var string
     */
    const API_URL = 'https://api.themoviedb.org/3';
    
    /**
     * TMDB API Key
     * مفتاح واجهة برمجة تطبيقات TMDB
     * 
     * @var string
     */
    protected $apiKey;
    
    /**
     * Constructor
     * 
     * @param \XF\App $app
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        $this->apiKey = $this->options()->xbtTrackerTmdbApiKey;
    }
    
    /**
     * Search for movies/TV shows in TMDB
     * البحث عن الأفلام/المسلسلات في TMDB
     *
     * @param string $query Search query
     * @param string $type Type (movie or tv)
     * @return array|null Results or null on failure
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
     * الحصول على تفاصيل فيلم/مسلسل من TMDB
     *
     * @param int $id TMDB ID
     * @param string $type Type (movie or tv)
     * @return array|null Details or null on failure
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
     * الحصول على ترجمات لفيلم/مسلسل
     *
     * @param int $id TMDB ID
     * @param string $type Type (movie or tv)
     * @param string $language ISO language code (default: 'ar')
     * @return array|null Translation or null on failure
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
     * الحصول على الطاقم (الممثلين وفريق العمل) لفيلم/مسلسل
     *
     * @param int $id TMDB ID
     * @param string $type Type (movie or tv)
     * @return array|null Credits or null on failure
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
     * Get translation and update TMDb data entity
     * الحصول على الترجمة وتحديث كيان بيانات TMDb
     * 
     * @param \Harment\XBTTracker\Entity\TmdbData $tmdbData
     * @param string $language ISO language code (default: 'ar')
     * @return bool Success or failure
     */
    public function getTranslation(\Harment\XBTTracker\Entity\TmdbData $tmdbData, $language = 'ar')
    {
        $translation = $this->getTranslations($tmdbData->tmdb_id, $tmdbData->type, $language);
        
        if ($translation && isset($translation['data'])) {
            if ($tmdbData->type == 'movie') {
                $tmdbData->title_ar = $translation['data']['title'] ?? '';
            } else {
                $tmdbData->title_ar = $translation['data']['name'] ?? '';
            }
            
            $tmdbData->overview_ar = $translation['data']['overview'] ?? '';
            return true;
        }
        
        return false;
    }
    
    /**
     * Make a request to the TMDB API
     * إجراء طلب إلى واجهة برمجة تطبيقات TMDB
     *
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @return array|null Response as array or null on failure
     */
    protected function makeRequest($endpoint, array $params = [])
    {
        try {
            $client = $this->app->http()->client();
            $response = $client->get($endpoint, [
                'query' => $params,
                'timeout' => 10, // 10 seconds timeout
                'connect_timeout' => 5 // 5 seconds connection timeout
            ]);
            
            $responseBody = (string)$response->getBody();
            $responseData = json_decode($responseBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                \XF::logError('TMDB API Error: Invalid JSON response');
                return null;
            }
            
            return $responseData;
        } catch (RequestException $e) {
            // Log error with more details
            \XF::logError('TMDB API Error: ' . $e->getMessage() . ' for endpoint: ' . $endpoint);
            return null;
        } catch (\Exception $e) {
            // Catch any other exceptions
            \XF::logError('TMDB API Unexpected Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if the API key is valid
     * التحقق من صحة مفتاح API
     * 
     * @return bool
     */
    public function isApiKeyValid()
    {
        if (!$this->apiKey) {
            return false;
        }
        
        // Try a simple configuration request to verify the API key
        $endpoint = self::API_URL . '/configuration';
        $params = [
            'api_key' => $this->apiKey
        ];
        
        $response = $this->makeRequest($endpoint, $params);
        
        return ($response && isset($response['images']));
    }
    
    /**
     * Get popular movies
     * الحصول على الأفلام الشائعة
     * 
     * @param int $page Page number
     * @return array|null
     */
    public function getPopularMovies($page = 1)
    {
        if (!$this->apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/movie/popular';
        $params = [
            'api_key' => $this->apiKey,
            'language' => 'en-US',
            'page' => $page
        ];
        
        $response = $this->makeRequest($endpoint, $params);
        
        if ($response && isset($response['results'])) {
            return $response['results'];
        }
        
        return null;
    }
    
    /**
     * Get popular TV shows
     * الحصول على المسلسلات الشائعة
     * 
     * @param int $page Page number
     * @return array|null
     */
    public function getPopularTvShows($page = 1)
    {
        if (!$this->apiKey) {
            return null;
        }
        
        $endpoint = self::API_URL . '/tv/popular';
        $params = [
            'api_key' => $this->apiKey,
            'language' => 'en-US',
            'page' => $page
        ];
        
        $response = $this->makeRequest($endpoint, $params);
        
        if ($response && isset($response['results'])) {
            return $response['results'];
        }
        
        return null;
    }
}