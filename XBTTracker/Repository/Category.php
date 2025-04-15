<?php
// src/addons/XBTTracker/Repository/Category.php
namespace XBTTracker\Repository;

use XF\Mvc\Entity\Repository;

/**
 * مستودع فئات التورنت
 * يوفر طرق للوصول إلى وإدارة فئات التورنت
 */
class Category extends Repository
{
    /**
     * الحصول على finder للفئات
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getCategoryFinder()
    {
        return $this->finder('XBTTracker:Category');
    }
    
    /**
     * العثور على الفئات للقائمة
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function findCategoriesForList()
    {
        return $this->getCategoryFinder()
            ->order(['parent_id', 'display_order']);
    }
    
    /**
     * الحصول على الفئات الرئيسية (بدون أب)
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getRootCategories()
    {
        return $this->getCategoryFinder()
            ->where('parent_id', 0)
            ->order('display_order');
    }
    
    /**
     * الحصول على الفئات الفرعية لفئة أب
     *
     * @param int $parentId معرف الفئة الأب
     * @return \XF\Mvc\Entity\Finder
     */
    public function getChildCategories($parentId)
    {
        return $this->getCategoryFinder()
            ->where('parent_id', $parentId)
            ->order('display_order');
    }
    
    /**
     * الحصول على شجرة الفئات
     *
     * @return array
     */
    public function getCategoryTree()
    {
        $categories = $this->findCategoriesForList()->fetch();
        
        $tree = [];
        $descendantsMap = [];
        
        // بناء خريطة الفئات حسب الأب
        foreach ($categories as $category) {
            $parentId = $category->parent_id;
            
            if (!isset($descendantsMap[$parentId])) {
                $descendantsMap[$parentId] = [];
            }
            
            $descendantsMap[$parentId][$category->category_id] = $category;
        }
        
        // دالة تكرارية لبناء الشجرة
        $populateTree = function($parentId) use (&$populateTree, $descendantsMap) {
            $output = [];
            
            if (isset($descendantsMap[$parentId])) {
                foreach ($descendantsMap[$parentId] as $childId => $child) {
                    $childTree = [
                        'record' => $child,
                        'children' => $populateTree($childId)
                    ];
                    
                    $output[$childId] = $childTree;
                }
            }
            
            return $output;
        };
        
        $tree = $populateTree(0);
        
        return $tree;
    }
    
    /**
     * الحصول على شجرة فئات مسطحة للاستخدام في خيارات القائمة المنسدلة
     *
     * @return array
     */
    public function getFlattenedCategoryTree()
    {
        $tree = $this->getCategoryTree();
        $flattened = [];
        
        $flatten = function($tree, $depth = 0) use (&$flatten, &$flattened) {
            foreach ($tree as $categoryId => $data) {
                $category = $data['record'];
                
                $flattened[$categoryId] = [
                    'record' => $category,
                    'depth' => $depth
                ];
                
                if ($data['children']) {
                    $flatten($data['children'], $depth + 1);
                }
            }
        };
        
        $flatten($tree);
        
        return $flattened;
    }
    
    /**
     * الحصول على خيارات الفئات للقائمة المنسدلة
     *
     * @return array
     */
    public function getCategoryOptions()
    {
        $categories = $this->findCategoriesForList()->fetch();
        $options = [];
        $categoryTree = [];
        
        // بناء شجرة متداخلة
        foreach ($categories as $category) {
            $categoryTree[$category->parent_id][$category->category_id] = $category;
        }
        
        // بناء الخيارات مع المسافات البادئة
        $this->buildCategoryOptionsRecursive($categoryTree, 0, $options);
        
        return $options;
    }
    
    /**
     * بناء مصفوفة خيارات الفئات بشكل متكرر
     *
     * @param array $categoryTree
     * @param int $parentId
     * @param array &$options
     * @param int $depth
     */
    protected function buildCategoryOptionsRecursive(array $categoryTree, $parentId, array &$options, $depth = 0)
    {
        if (!isset($categoryTree[$parentId])) {
            return;
        }
        
        foreach ($categoryTree[$parentId] as $category) {
            $options[$category->category_id] = str_repeat('-- ', $depth) . $category->title;
            $this->buildCategoryOptionsRecursive($categoryTree, $category->category_id, $options, $depth + 1);
        }
    }
    
    /**
     * الحصول على فتات الخبز للفئة
     *
     * @param \XBTTracker\Entity\Category $category
     * @return array
     */
    public function getCategoryBreadcrumb(\XBTTracker\Entity\Category $category)
    {
        $breadcrumb = [];
        
        while ($category) {
            array_unshift($breadcrumb, [
                'value' => $category->category_id,
                'title' => $category->title,
                'href' => \XF::app()->router()->buildLink('torrents', [
                    'category_id' => $category->category_id
                ])
            ]);
            
            if ($category->parent_id) {
                $category = $this->em()->find('XBTTracker:Category', $category->parent_id);
            } else {
                $category = null;
            }
        }
        
        return $breadcrumb;
    }
    
    /**
     * احتساب عدد التورنتات في الفئة
     *
     * @param int $categoryId
     * @return int
     */
    public function countTorrentsInCategory($categoryId)
    {
        return $this->finder('XBTTracker:Torrent')
            ->where('category_id', $categoryId)
            ->total();
    }
}