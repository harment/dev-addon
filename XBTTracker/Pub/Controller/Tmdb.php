<?php

namespace XBTTracker\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Tmdb extends AbstractController
{
    /**
     * Search TMDB
     */
    public function actionSearch()
    {
        $this->assertCanView();
        
        $query = $this->filter('query', 'str');
        
        if (!$query) {
            return $this->view('XBTTracker:Tmdb\Search', 'xbt_tmdb_search_results', [
                'results' => [],
                'query' => ''
            ]);
        }
        
        // Search TMDB
        $tmdbService = $this->service('XBTTracker:Tmdb');
        $results = $tmdbService->search($query);
        
        $viewParams = [
            'results' => $results,
            'query' => $query
        ];
        
        return $this->view('XBTTracker:Tmdb\Search', 'xbt_tmdb_search_results', $viewParams);
    }
    
    /**
     * Get TMDB info
     */
    public function actionInfo(ParameterBag $params)
    {
        $this->assertCanView();
        
        $tmdbId = $params->get('tmdb_id');
        if (!$tmdbId) {
            return $this->error(\XF::phrase('xbt_tmdb_id_required'));
        }
        
        // Get TMDB info
        $tmdbService = $this->service('XBTTracker:Tmdb');
        $info = $tmdbService->getInfo($tmdbId);
        
        if (!$info) {
            return $this->error(\XF::phrase('xbt_tmdb_not_found'));
        }
        
        $viewParams = [
            'info' => $info,
            'tmdbId' => $tmdbId
        ];
        
        return $this->view('XBTTracker:Tmdb\Info', 'xbt_tmdb_info', $viewParams);
    }
    
    /**
     * Assert can view torrents
     */
    protected function assertCanView()
    {
        if (!\XF::visitor()->hasPermission('xbtTracker', 'view')) {
            throw $this->exception($this->noPermission());
        }
    }
}