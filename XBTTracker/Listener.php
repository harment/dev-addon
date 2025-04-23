<?php

namespace Harment\XBTTracker;

use XF\App;
use XF\Container;
use XF\Entity\User;
use XF\Mvc\Entity\Entity;

class Listener
{
    /**
     * Called during app setup
     * Register services and extend functionality
     * 
     * @param App $app XenForo application instance
     */
    public static function appSetup(App $app)
    {
        // Register service
        $container = $app->container();
        $container['xbt.tracker.service'] = function (Container $c) {
            return new Service\Tracker($c['app']);
        };
        
        // في XenForo 2.3.6، تسجيل BBCode يتم من خلال تعديل setup.php أو التسجيل في addon.json
        // نترك هذا الجزء فارغاً ونعتمد على bb_codes.xml للتسجيل
    }
    
    /**
 * Add additional fields to user and forum entities
 * 
 * @param \XF\Mvc\Entity\Manager $em Entity manager
 * @param \XF\Mvc\Entity\Structure &$structure Entity structure
 */
public static function entityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
{
    $entity = $structure->contentType;
    
    if ($entity === 'XF:User') {
        // Add torrent stats to user entity
        $structure->getters['xbt_stats'] = true;
        $structure->getters['xbt_ratio'] = true;
        $structure->getters['xbt_uploaded_formatted'] = true;
        $structure->getters['xbt_downloaded_formatted'] = true;
        
        $structure->relations['XbtStats'] = [
            'entity' => 'Harment\XBTTracker:UserStats',
            'type' => \XF\Mvc\Entity\Structure::TO_ONE,
            'conditions' => 'user_id',
            'primary' => true
        ];
    }
}
    
    /**
     * Add torrent tracker to navigation tabs
     * 
     * @param string $selected Selected tab
     * @param array &$tabsToKeep Tabs to keep
     * @param array $handlerInfo Handler information
     */
    public static function navigationTabs($selected, &$tabsToKeep, $handlerInfo)
    {
        if (!\XF::visitor()->hasPermission('xbtTracker', 'view')) {
            return;
        }
        
        $router = \XF::app()->router('public');
        $customTabs['torrents'] = [
            'title' => \XF::phrase('xbt_torrent_list'),
            'href' => $router->buildLink('torrents'),
            'position' => 'middle'
        ];
        
        // Add the custom tabs to the tabs to keep
        if (isset($tabsToKeep)) {
            $tabsToKeep = array_merge($tabsToKeep, $customTabs);
        }
    }
    
    /**
     * Add content to template hooks
     * 
     * @param string $hookName Hook name
     * @param string &$contents Contents
     * @param array $hookParams Hook parameters
     * @param \XF\Template\Templater $templater Templater
     */
    public static function templateHook($hookName, &$contents, array $hookParams, \XF\Template\Templater $templater)
    {
        if ($hookName == 'forum_view_threads_before') {
            // Add torrents tab to forum
            $forum = $hookParams['forum'] ?? null;
            if (!$forum || !\XF::visitor()->hasPermission('xbtTracker', 'view')) {
                return;
            }
            
            $contents .= $templater->renderTemplate('public:xbt_forum_tab', [
                'forum' => $forum
            ]);
        } elseif ($hookName == 'account_wrapper_sidebar') {
            // Add user stats to user sidebar
            $user = \XF::visitor();
            if (!$user->user_id || !$user->hasPermission('xbtTracker', 'view')) {
                return;
            }
            
            $contents .= $templater->renderTemplate('public:xbt_widget_user_stats', [
                'user' => $user
            ]);
        }
    }

    /**
     * Listens to user entity post-save event
     * Creates user tracker options and stats on user creation
     *
     * @param Entity $entity
     */
    public static function userPostSave(Entity $entity)
    {
        if (!$entity instanceof \XF\Entity\User)
        {
            return;
        }

        $userId = $entity->user_id;
        
        // Get user options
        $options = \XF::em()->find('Harment\XBTTracker:UserOptions', $userId);
        
        // Create user options if they don't exist
        if (!$options)
        {
            $options = \XF::em()->create('Harment\XBTTracker:UserOptions');
            $options->user_id = $userId;
            
            // Generate passkey if user is being created
            if ($entity->isInsert())
            {
                $data = $userId . $entity->username . \XF::$time . \XF::generateRandomString(16);
                $options->passkey = md5($data);
            }
            
            $options->save();
        }
        
        // Get user stats
        $stats = \XF::em()->find('Harment\XBTTracker:UserStats', $userId);
        
        // Create user stats if they don't exist
        if (!$stats)
        {
            $stats = \XF::em()->create('Harment\XBTTracker:UserStats');
            $stats->user_id = $userId;
            $stats->save();
        }
    }

    /**
     * Listens to user delete event
     * Cleans up tracker-related user data
     *
     * @param \XF\Entity\User $user
     */
    public static function userDelete(\XF\Entity\User $user)
    {
        $userId = $user->user_id;
        
        // Delete user options
        $options = \XF::em()->find('Harment\XBTTracker:UserOptions', $userId);
        if ($options)
        {
            $options->delete();
        }
        
        // Delete user stats
        $stats = \XF::em()->find('Harment\XBTTracker:UserStats', $userId);
        if ($stats)
        {
            $stats->delete();
        }
        
        // Delete user torrents
        $torrents = \XF::em()->findByIds('Harment\XBTTracker:Torrent', $userId, 'user_id');
        foreach ($torrents as $torrent)
        {
            $torrent->delete();
        }
        
        // Delete user peers
        $peers = \XF::em()->findByIds('Harment\XBTTracker:Peer', $userId, 'user_id');
        foreach ($peers as $peer)
        {
            $peer->delete();
        }
    }

    /**
     * Handler for adding custom permissions
     *
     * @param \XF\Permission\Builder $builder
     */
    public static function permissionHandler(\XF\Permission\Builder $builder)
    {
        $builder->add('xbtTracker', 'view', 'boolean');
        $builder->add('xbtTracker', 'download', 'boolean');
        $builder->add('xbtTracker', 'upload', 'boolean');
        $builder->add('xbtTracker', 'comment', 'boolean');
    }
}