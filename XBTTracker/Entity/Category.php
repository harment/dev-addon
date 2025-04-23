<?php
// src/addons/XBTTracker/Entity/Category.php
namespace Harment\XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
 * Torrent Category Entity
 * Used to organize torrents into categories and subcategories
 * 
 * كيان فئة التورنت
 * يستخدم لتنظيم التورنتات في فئات وفئات فرعية
 *
 * @property int $category_id
 * @property string $title
 * @property string $description
 * @property int $parent_id
 * @property int $display_order
 * @property int $node_id
 *
 * @property-read array $breadcrumb
 * @property-read string $url
 *
 * @property-read \Harment\XBTTracker\Entity\Category|null $Parent
 * @property-read \XF\Mvc\Entity\ArrayCollection|\Harment\XBTTracker\Entity\Category[] $Children
 * @property-read \XF\Entity\Node|null $Node
 * @property-read \XF\Mvc\Entity\ArrayCollection|\Harment\XBTTracker\Entity\Torrent[] $Torrents
 */
class Category extends Entity
{
    /**
     * Define the entity structure
     * تعريف هيكل الكيان
     *
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_xbt_categories';
        $structure->shortName = 'XBTTracker:Category';
        $structure->primaryKey = 'category_id';
        
        $structure->columns = [
            'category_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
            'description' => ['type' => self::STR, 'default' => ''],
            'parent_id' => ['type' => self::UINT, 'default' => 0],
            'display_order' => ['type' => self::UINT, 'default' => 1],
            'node_id' => ['type' => self::UINT, 'default' => 0]
        ];
        
        $structure->getters = [
            'breadcrumb' => true,
            'url' => true,
            'breadcrumbs' => true,
            'category_url' => true,
            'torrent_count' => true
        ];
        
        $structure->relations = [
            'Parent' => [
                'entity' => 'XBTTracker:Category',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['category_id', '=', '$parent_id']
                ],
                'primary' => true
            ],
            'Children' => [
                'entity' => 'XBTTracker:Category',
                'type' => self::TO_MANY,
                'conditions' => [
                    ['parent_id', '=', '$category_id']
                ],
                'order' => 'display_order'
            ],
            'Node' => [
                'entity' => 'XF:Node',
                'type' => self::TO_ONE,
                'conditions' => [
                    ['node_id', '=', '$node_id']
                ],
                'primary' => true
            ],
            'Torrents' => [
                'entity' => 'XBTTracker:Torrent',
                'type' => self::TO_MANY,
                'conditions' => 'category_id',
                'key' => 'torrent_id'
            ]
        ];
        
        return $structure;
    }
    
    /**
     * Get URL parameters for the category
     * الحصول على معلمات URL للفئة
     *
     * @return array
     */
    public function getUrlParams()
    {
        return [
            'category_id' => $this->category_id,
            'title' => \XF::app()->stringFormatter->wholeWordTrim($this->title, 30, 0, '')
        ];
    }
    
    /**
     * Get the navigation path (breadcrumb) for the category
     * Includes all parent categories up to the root
     * 
     * الحصول على مسار التنقل (الفتات) للفئة
     * يتضمن جميع الفئات الأم حتى الجذر
     *
     * @return array
     */
    public function getBreadcrumb()
    {
        $breadcrumb = [];
        
        $category = $this;
        while ($category)
        {
            array_unshift($breadcrumb, [
                'value' => $category->category_id,
                'title' => $category->title,
                'href' => \XF::app()->router()->buildLink('torrents', [
                    'category_id' => $category->category_id
                ])
            ]);
            
            if ($category->parent_id)
            {
                $category = $category->Parent;
            }
            else
            {
                $category = null;
            }
        }
        
        return $breadcrumb;
    }
    
    /**
     * Legacy function for backward compatibility
     * دالة بديلة للتوافق مع الكود القديم
     *
     * @return array
     */
    public function getBreadcrumbs()
    {
        $output = [];
        $breadcrumb = $this->getBreadcrumb();
        
        foreach ($breadcrumb as $item) {
            $output[] = [
                'value' => $item['value'],
                'label' => $item['title']
            ];
        }
        
        return $output;
    }
    
    /**
     * Get the category URL
     * الحصول على URL الفئة
     *
     * @return string
     */
    public function getUrl()
    {
        return \XF::app()->router()->buildLink('torrents', [
            'category_id' => $this->category_id
        ]);
    }
    
    /**
     * Legacy function for backward compatibility
     * دالة بديلة للتوافق مع الكود القديم
     *
     * @return string
     */
    public function getCategoryUrl()
    {
        return $this->getUrl();
    }
    
    /**
     * Get the count of torrents in this category
     * الحصول على عدد التورنتات في هذه الفئة
     *
     * @return int
     */
    public function getTorrentCount()
    {
        return $this->db()->fetchOne('
            SELECT COUNT(*)
            FROM xf_xbt_torrents
            WHERE category_id = ?
        ', $this->category_id);
    }
    
    /**
     * Get recursive torrent count (including all subcategories)
     * الحصول على عدد التورنتات التراكمي (بما في ذلك جميع الفئات الفرعية)
     * 
     * @return int
     */
    public function getRecursiveTorrentCount()
    {
        $db = $this->db();
        $count = $this->getTorrentCount();
        
        // Get all child categories
        $childCategoryIds = $this->getAllChildCategoryIds();
        
        if (!empty($childCategoryIds)) {
            $childCount = $db->fetchOne('
                SELECT COUNT(*)
                FROM xf_xbt_torrents
                WHERE category_id IN (' . $db->quote($childCategoryIds) . ')
            ');
            
            $count += $childCount;
        }
        
        return $count;
    }
    
    /**
     * Get all child category IDs recursively
     * الحصول على معرفات جميع الفئات الفرعية بشكل متكرر
     * 
     * @return array
     */
    public function getAllChildCategoryIds()
    {
        $ids = [];
        $this->getChildCategoryIdsRecursive($ids, $this->category_id);
        return $ids;
    }
    
    /**
     * Helper function to get child category IDs recursively
     * دالة مساعدة للحصول على معرفات الفئات الفرعية بشكل متكرر
     * 
     * @param array $ids Reference to collected IDs
     * @param int $parentId Parent category ID
     */
    protected function getChildCategoryIdsRecursive(&$ids, $parentId)
    {
        $db = $this->db();
        $children = $db->fetchAll('
            SELECT category_id
            FROM xf_xbt_categories
            WHERE parent_id = ?
        ', $parentId);
        
        foreach ($children as $child) {
            $ids[] = $child['category_id'];
            $this->getChildCategoryIdsRecursive($ids, $child['category_id']);
        }
    }
    
    /**
     * Check if the category can be viewed by the current user
     * التحقق مما إذا كان يمكن عرض الفئة من قبل المستخدم الحالي
     * 
     * @return bool
     */
    public function canView()
    {
        $visitor = \XF::visitor();
        
        if (!$visitor->user_id) {
            // Guest permissions can be checked here
            return \XF::options()->xbtTrackerAllowGuestBrowse ?? false;
        }
        
        // Check if user has permission to view torrents
        return $visitor->hasPermission('xbtTracker', 'view');
    }
    
    /**
     * Actions before deletion
     * الإجراءات قبل الحذف
     * 
     * @return bool
     */
    protected function _preDelete()
    {
        // Check for child categories
        if ($this->Children->count()) {
            $this->error(\XF::phrase('xbt_cannot_delete_category_with_children'));
            return false;
        }
        
        // Check for torrents in the category
        if ($this->getTorrentCount() > 0) {
            $this->error(\XF::phrase('xbt_cannot_delete_category_with_torrents'));
            return false;
        }
        
        return parent::_preDelete();
    }
    
    /**
     * Validate the entity before saving
     * التحقق من صحة الكيان قبل الحفظ
     * 
     * @return bool
     */
    protected function _preSave()
    {
        // Check for circular parent references
        if ($this->parent_id) {
            $parent = $this->Parent;
            
            // Detect circular references
            if ($parent) {
                if ($parent->category_id == $this->category_id) {
                    $this->error(\XF::phrase('xbt_category_cannot_be_parent_of_itself'), 'parent_id');
                    return false;
                }
                
                // Check if this category is a parent of the selected parent
                if ($this->isParentOf($parent)) {
                    $this->error(\XF::phrase('xbt_cannot_select_child_as_parent'), 'parent_id');
                    return false;
                }
            }
        }
        
        return parent::_preSave();
    }
    
    /**
     * Check if this category is a parent of another category
     * التحقق مما إذا كانت هذه الفئة هي أصل فئة أخرى
     * 
     * @param Category $category
     * @return bool
     */
    public function isParentOf(Category $category)
    {
        if (!$category->parent_id) {
            return false;
        }
        
        if ($category->parent_id == $this->category_id) {
            return true;
        }
        
        $parent = $category->Parent;
        if ($parent) {
            return $this->isParentOf($parent);
        }
        
        return false;
    }
}