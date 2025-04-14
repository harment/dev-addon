<?php
// src/addons/XBTTracker/Widget/LatestTorrents.php
namespace XBTTracker\Widget;

use XF\Widget\AbstractWidget;

/**
 * Widget to display the latest torrents
 */
class LatestTorrents extends AbstractWidget
{
    /**
     * Get default options for this widget
     *
     * @return array
     */
    protected function getDefaultOptions()
    {
        return [
            'limit' => 10,
            'category_id' => 0
        ];
    }
    
    /**
     * Render the widget
     *
     * @return string|array
     */
    public function render()
    {
        /** @var \XBTTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = $this->repository('XBTTracker:Torrent');
        
        $limit = $this->options['limit'];
        $categoryId = $this->options['category_id'];
        
        $finder = $torrentRepo->findLatestTorrents($limit);
        
        if ($categoryId) {
            $finder->where('category_id', $categoryId);
        }
        
        $torrents = $finder->fetch();
        
        $viewParams = [
            'torrents' => $torrents,
            'style' => $this->style,
            'categoryId' => $categoryId
        ];
        
        return $this->renderer('xbt_widget_latest_torrents', $viewParams);
    }
    
    /**
     * Get the admin edit form
     *
     * @param \XF\Http\Request $request
     * @return \XF\Admin\View\Widget\Edit
     */
    public function getOptionsTemplate()
    {
        /** @var \XBTTracker\Repository\Category $categoryRepo */
        $categoryRepo = $this->repository('XBTTracker:Category');
        $categoryOptions = $categoryRepo->getCategoryOptions();
        
        return $this->renderer('admin:xbt_widget_latest_torrents_options', [
            'options' => $this->options,
            'categoryOptions' => $categoryOptions
        ]);
    }
}
