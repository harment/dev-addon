<?php

namespace Harment/XBTTracker;

use XF\AddOn\AbstractAddOn;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\AddOn\StepRunnerUninstallTrait;

/**
 * Class XBTTracker
 * 
 * الفئة الرئيسية لإضافة XBT Tracker لمنصة XenForo
 * تمثل هذه الفئة نقطة الدخول الرئيسية للإضافة وتحتوي على وظائف التهيئة الأساسية
 *
 * @package XBTTracker
 */
class XBTTracker extends AbstractAddOn
{
    /**
     * تهيئة الإضافة عند تحميلها
     * يتم استدعاء هذه الدالة عند تحميل الإضافة في كل طلب
     */
    public function setupApp(\XF\App $app)
    {
        // إضافة مسار بحث قوالب إضافية
        $app->templater()->addTemplateDirectory('XBTTracker', 'public');
        
        // إضافة مسار بحث قوالب المشرف
        $app->templater()->addTemplateDirectory('XBTTracker', 'admin');
        
        // إضافة مسار بحث CSS
        $app->templater()->addDefaultParam('xbtTrackerCssPath', $this->getStyleRelativePath());
        
        // إضافة مسارات إضافية للموجه (router)
        $this->setupRoutes($app);
    }
    
    /**
     * إعداد المسارات الإضافية للإضافة
     * يضيف مسارات مخصصة للتعامل مع طلبات التورنت
     *
     * @param \XF\App $app
     */
    protected function setupRoutes(\XF\App $app)
    {
        // التحقق إذا كان الموجه متاح (قد لا يكون متاحًا في بعض السياقات مثل CLI)
        $router = $app->router('public');
        
        if ($router)
        {
            // إضافة مسار /torrents ليتم معالجته بواسطة متحكم XBTTracker:Torrent
            $router->setPrefixHandlers('torrents', [
                'controller' => 'XBTTracker:Torrent',
                'context' => 'torrents'
            ]);
            
            // إضافة مسار /announce للتعامل مع طلبات التراكر
            $router->addRoute(
                'announce',
                'announce',
                'XBTTracker:Tracker',
                'announce'
            );
            
            // إضافة مسار /scrape للتعامل مع طلبات scrape
            $router->addRoute(
                'scrape',
                'scrape',
                'XBTTracker:Tracker',
                'scrape'
            );
        }
        
        // إضافة مسارات لوحة التحكم
        $adminRouter = $app->router('admin');
        
        if ($adminRouter)
        {
            $adminRouter->setPrefixHandlers('torrents', [
                'controller' => 'XBTTracker:Admin',
                'context' => 'torrents'
            ]);
        }
    }
    
    /**
     * الحصول على مسار أنماط CSS النسبي
     * يستخدم لتحميل ملفات CSS الخاصة بالإضافة
     *
     * @return string
     */
    protected function getStyleRelativePath()
    {
        $addOnId = $this->getAddOnId();
        return "styles/{$addOnId}";
    }
    
    /**
     * تسجيل الدوال التي ستتم استدعاؤها عند تحميل بعض الكيانات
     * يتيح هذا إضافة سلوك مخصص للكيانات الأساسية في XenForo
     */
    public function loadEntityStructure()
    {
        $structure = [
            'XF:User' => function(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
{
    $structure->relations['XBTStats'] = [
        'entity' => 'XBTTracker:UserStats',
        'type' => \XF\Mvc\Entity\Entity::TO_ONE,
        'conditions' => [
            ['user_id', '=', '$user_id']
        ],
        'primary' => true
    ];
},
            
            'XF:Forum' => function(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
            {
                // إضافة علاقة بين المنتدى وفئة التورنت
                $structure->relations['XbtCategory'] = [
                    'entity' => 'XBTTracker:Category',
                    'type' => 'one-to-one',
                    'conditions' => [
                        ['node_id', '=', '$node_id']
                    ],
                    'primary' => true
                ];
            }
        ];
        
        return $structure;
    }
    
    /**
     * تسجيل وظائف إضافية في مكتبة التوابع (utils)
     * يمكن استخدامها في مختلف أجزاء الإضافة
     */
    public function loadUtils()
    {
        $utils = [
            'bencode' => function()
            {
                return new Util\Bencode();
            },
        ];
        
        return $utils;
    }
    
    /**
     * تسجيل الدوال التي ستتم استدعاؤها بعد حفظ أو حذف كيانات معينة
     * يتيح هذا إضافة سلوك مخصص عند حدوث تغييرات في الكيانات
     */
    public function loadEntityPostActions()
    {
        $actions = [
            'XF:User' => [
                'delete' => function(\XF\Entity\User $user)
                {
                    // عند حذف مستخدم، قم بحذف بيانات التورنت المرتبطة به
                    $this->deleteUserTorrentData($user);
                }
            ],
            
            'XF:Node' => [
                'delete' => function(\XF\Entity\Node $node)
                {
                    // عند حذف عقدة منتدى، تحقق إذا كانت مرتبطة بفئة تورنت
                    if ($node->node_type_id == 'Forum')
                    {
                        $this->unlinkNodeCategory($node->node_id);
                    }
                }
            ]
        ];
        
        return $actions;
    }
    
    /**
     * حذف بيانات التورنت المرتبطة بمستخدم تم حذفه
     *
     * @param \XF\Entity\User $user
     */
    protected function deleteUserTorrentData(\XF\Entity\User $user)
    {
        $db = \XF::db();
        
        // حذف إحصائيات المستخدم
        $db->delete('xf_xbt_user_stats', 'user_id = ?', $user->user_id);
        
        // حذف سجل المكافآت
        $db->delete('xf_xbt_user_bonus_history', 'user_id = ?', $user->user_id);
        
        // حذف سجل الإكمال
        $db->delete('xf_xbt_user_completed', 'user_id = ?', $user->user_id);
        
        // حذف سجل الأقران
        $db->delete('xf_xbt_peers', 'user_id = ?', $user->user_id);
        
        // جعل التورنت المرفوعة من قبل المستخدم بدون مالك
        // يمكن اختيار حذفها بدلاً من ذلك إذا كانت هذه هي السياسة المطلوبة
        $db->update('xf_xbt_torrents', ['user_id' => 0], 'user_id = ?', $user->user_id);
    }
    
    /**
     * إلغاء ارتباط عقدة منتدى بفئة تورنت عند حذف العقدة
     *
     * @param int $nodeId
     */
    protected function unlinkNodeCategory($nodeId)
    {
        $db = \XF::db();
        
        // تحديث فئات التورنت المرتبطة لإزالة الارتباط
        $db->update('xf_xbt_categories', ['node_id' => 0], 'node_id = ?', $nodeId);
    }
    
    /**
     * إضافة إحصائيات إضافية إلى إحصائيات النظام
     * تستخدم في لوحة إحصائيات المشرف
     *
     * @param array $stats
     * @return array
     */
    public function addStatsToAdminHomepage(array $stats)
    {
        $xbtStats = \XF::repository('XBTTracker:Torrent')->getTorrentStats();
        
        if ($xbtStats)
        {
            $stats['xbtTorrents'] = [
                'title' => \XF::phrase('xbt_torrents'),
                'count' => $xbtStats['total_torrents'],
                'icon' => 'fa-magnet'
            ];
            
            $stats['xbtPeers'] = [
                'title' => \XF::phrase('xbt_peers'),
                'count' => $xbtStats['total_peers'],
                'icon' => 'fa-users'
            ];
        }
        
        return $stats;
    }
    
    /**
     * تكامل مع نظام اكتشاف الوحدات البرمجية لـ XenForo
     * يسجل الفئات المخصصة ليتمكن نظام XenForo من العثور عليها
     */
    public function postInstall()
    {
        // الإجراءات التي يجب تنفيذها بعد تثبيت الإضافة
        // مثل تسجيل الوحدات المخصصة أو تهيئة البيانات الأولية
    }
    
    /**
     * إضافة عناصر قائمة في قائمة المستخدم
     * تضيف روابط إلى إحصائيات التورنت وصفحات أخرى
     *
     * @param array $items
     * @return array
     */
    public function addUserNavigationItems(array $items)
    {
        $visitor = \XF::visitor();
        
        if ($visitor->user_id && $visitor->hasPermission('xbtTracker', 'view'))
        {
            $items['xbtTorrents'] = [
                'title' => \XF::phrase('xbt_torrents'),
                'href' => \XF::app()->router()->buildLink('torrents'),
                'icon' => 'fa-magnet'
            ];
            
            $items['xbtStats'] = [
                'title' => \XF::phrase('xbt_user_stats'),
                'href' => \XF::app()->router()->buildLink('account/xbt-stats'),
                'icon' => 'fa-chart-line'
            ];
        }
        
        return $items;
    }
    
    /**
     * تهيئة أي مكونات إضافية عند بدء التطبيق
     * يتم استدعاء هذه الدالة مرة واحدة عند بدء تشغيل التطبيق
     */
    public function initializeSystem()
    {
        // أي إجراءات تهيئة إضافية يمكن تنفيذها هنا
    }
}