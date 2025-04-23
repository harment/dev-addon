<?php

namespace Harment\XBTTracker\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

/**
 * Controller for TMDB (The Movie Database) related actions
 */
class Tmdb extends AbstractController
{
    /**
     * Search TMDB for movies and TV shows
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionSearch()
    {
        $this->assertCanView();
        
        $query = $this->filter('query', 'str');
        
        if (!$query) {
            return $this->view('Harment\XBTTracker:Tmdb\Search', 'xbt_tmdb_search_results', [
                'results' => [],
                'query' => ''
            ]);
        }
        
        // Search TMDB
        /** @var \Harment\XBTTracker\Service\Tmdb\Client $tmdbService */
        $tmdbService = $this->service('Harment\XBTTracker:Tmdb\Client');
        $results = $tmdbService->search($query);
        
        $viewParams = [
            'results' => $results,
            'query' => $query
        ];
        
        return $this->view('Harment\XBTTracker:Tmdb\Search', 'xbt_tmdb_search_results', $viewParams);
    }
    
    /**
     * Get detailed information about a specific TMDB item
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Error
     */
    public function actionInfo(ParameterBag $params)
    {
        $this->assertCanView();
        
        $tmdbId = $params->get('tmdb_id');
        if (!$tmdbId) {
            return $this->error(\XF::phrase('xbt_tmdb_id_required'));
        }
        
        // Get TMDB info
        /** @var \Harment\XBTTracker\Service\Tmdb\Client $tmdbService */
        $tmdbService = $this->service('Harment\XBTTracker:Tmdb\Client');
        $info = $tmdbService->getInfo($tmdbId);
        
        if (!$info) {
            return $this->error(\XF::phrase('xbt_tmdb_not_found'));
        }
        
        $viewParams = [
            'info' => $info,
            'tmdbId' => $tmdbId
        ];
        
        return $this->view('Harment\XBTTracker:Tmdb\Info', 'xbt_tmdb_info', $viewParams);
    }
    
    /**
     * Assert that the current user can view torrents
     *
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertCanView()
    {
        $visitor = \XF::visitor();
        if (!$visitor->hasPermission('xbtTracker', 'view')) {
            throw $this->exception($this->noPermission());
        }
    }
}