<?php

namespace Harment/XBTTracker;

use XF\App;
use XF\Container;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;

class Listener
{
    /**
     * Called during app setup
     * Register services and extend functionality
     */
    public static function appSetup(App $app)
    {
        // Register service
        $container = $app->container();
        $container['xbt.tracker.service'] = function (Container $c) {
            return new Service\Tracker($c['db'], $c['options']);
        };
        
        // Register bbcode for torrent tag
        $bbCode = $app->bbCode();
        $formatter = $bbCode->formatter();
        
        $formatter->addTag('torrent', [
            'replace' => function($tag, array $config, $name, $option, $content, array $options, \XF\BbCode\Renderer\AbstractRenderer $renderer) {
                $torrentId = intval($option);
                if (!$torrentId) {
                    return $content;
                }
                
                $router = \XF::app()->router('public');
                $url = $router->buildLink('torrents/view', ['torrent_id' => $torrentId]);
                
                return '<a href="' . htmlspecialchars($url) . '" class="internalLink">' . $content . '</a>';
            }
        ]);
    }
    
    /**
     * Add additional fields to user and forum entities
     */
    public static function entityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure, $entity)
    {
        if ($entity === 'XF:User') {
            // Add torrent stats to user entity
            $structure->getters['xbt_stats'] = true;
            $structure->getters['xbt_ratio'] = true;
            $structure->getters['xbt_uploaded_formatted'] = true;
            $structure->getters['xbt_downloaded_formatted'] = true;
            
            $structure->relations['XbtStats'] = [
                'entity' => 'XBTTracker:UserStats',
                'type' => \XF\Mvc\Entity\Structure::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true
            ];
        }
    }
    
    /**
     * Add torrent tracker to navigation tabs
     */
    public static function navigationTabs($selected, &$tabsToKeep, $handlerInfo)
    
	{
        if (!\XF::visitor()->hasPermission('xbtTracker', 'view')) {
            return;
        }
        
        $router = $app->router('public');
        $customTabs['torrents'] = [
            'title' => \XF::phrase('xbt_torrent_list'),
            'href' => $router->buildLink('torrents'),
            'position' => 'middle'
        ];
    }
    
    /**
     * Add content to template hooks
     */
    public static function templateHook($hookName, &$contents, array $hookParams, \XF\Template\Templater $templater)
    {
        if ($hookName == 'forum_view_threads_before') {
            // Add torrents tab to forum
            $forum = $hookParams['forum'];
            if (!$forum || !\XF::visitor()->hasPermission('xbtTracker', 'view')) {
                return;
            }
            
            $templater->includeTemplate('xbt_forum_tab', [
                'forum' => $forum
            ]);
        } elseif ($hookName == 'account_wrapper_sidebar') {
            // Add user stats to user sidebar
            $user = \XF::visitor();
            if (!$user->user_id || !$user->hasPermission('xbtTracker', 'view')) {
                return;
            }
            
            $templater->includeTemplate('xbt_widget_user_stats', [
                'user' => $user
            ]);
        }
    }
}