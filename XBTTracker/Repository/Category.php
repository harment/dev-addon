<?php
// src/addons/XBTTracker/Repository/Category.php
namespace Harment\XBTTracker\Repository;

use XF\Mvc\Entity\Repository;
use Harment\XBTTracker\Entity\Category as CategoryEntity;

/**
 * Torrent Category Repository
 * Provides methods for accessing and managing torrent categories
 * 
 * مستودع فئات التورنت
 * يوفر طرق للوصول إلى وإدارة فئات التورنت
 */
class Category extends Repository
{
    /**
     * Get category finder
     * الحصول على finder للفئات
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getCategoryFinder()
    {
        return $this->finder('Harment\XBTTracker:Category');
    }
    
    /**
     * Find categories for list
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
     * Get root categories (without parent)
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
     * Get child categories for a parent category
     * الحصول على الفئات الفرعية لفئة أب
     *
     * @param int $parentId Parent category ID
     * @return \XF\Mvc\Entity\Finder
     */
    public function getChildCategories($parentId)
    {
        return $this->getCategoryFinder()
            ->where('parent_id', $parentId)
            ->order('display_order');
    }
    
    /**
     * Get the category tree
     * الحصول على شجرة الفئات
     *
     * @return array
     */
    public function getCategoryTree()
    {
        $categories = $this->findCategoriesForList()->fetch();
        
        $tree = [];
        $descendantsMap = [];
        
        // Build category map by parent
        // بناء خريطة الفئات حسب الأب
        foreach ($categories as $category) {
            $parentId = $category->parent_id;
            
            if (!isset($descendantsMap[$parentId])) {
                $descendantsMap[$parentId] = [];
            }
            
            $descendantsMap[$parentId][$category->category_id] = $category;
        }
        
        // Recursive function to build the tree
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
     * Get flattened category tree for dropdown options
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
     * Get category options for dropdown
     * الحصول على خيارات الفئات للقائمة المنسدلة
     *
     * @return array
     */
    public function getCategoryOptions()
    {
        $categories = $this->findCategoriesForList()->fetch();
        $options = [];
        $categoryTree = [];
        
        // Build nested tree
        // بناء شجرة متداخلة
        foreach ($categories as $category) {
            $categoryTree[$category->parent_id][$category->category_id] = $category;
        }
        
        // Build options with indentation
        // بناء الخيارات مع المسافات البادئة
        $this->buildCategoryOptionsRecursive($categoryTree, 0, $options);
        
        return $options;
    }
    
    /**
     * Build category options recursively
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
     * Get category breadcrumb
     * الحصول على فتات الخبز للفئة
     *
     * @param CategoryEntity $category
     * @return array
     */
    public function getCategoryBreadcrumb(CategoryEntity $category)
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
                $category = $this->em()->find('Harment\XBTTracker:Category', $category->parent_id);
            } else {
                $category = null;
            }
        }
        
        return $breadcrumb;
    }
    
    /**
     * Count torrents in category
     * احتساب عدد التورنتات في الفئة
     *
     * @param int $categoryId
     * @return int
     */
    public function countTorrentsInCategory($categoryId)
    {
        return $this->finder('Harment\XBTTracker:Torrent')
            ->where('category_id', $categoryId)
            ->total();
    }
    
    /**
     * Count torrents in category recursively (including all subcategories)
     * احتساب عدد التورنتات في الفئة بشكل تراكمي (متضمنًا الفئات الفرعية)
     *
     * @param int $categoryId
     * @return int
     */
    public function countTorrentsInCategoryRecursive($categoryId)
    {
        $count = $this->countTorrentsInCategory($categoryId);
        
        // Get all subcategories
        $childCategories = $this->getCategoryFinder()
            ->where('parent_id', $categoryId)
            ->fetch();
            
        // Count torrents in each subcategory
        foreach ($childCategories as $childCategory) {
            $count += $this->countTorrentsInCategoryRecursive($childCategory->category_id);
        }
        
        return $count;
    }
    
    /**
     * Get popular categories (based on torrent count)
     * الحصول على الفئات الشائعة (بناءً على عدد التورنتات)
     * 
     * @param int $limit
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getPopularCategories($limit = 5)
    {
        $categories = $this->findCategoriesForList()->fetch();
        $categoryCounts = [];
        
        foreach ($categories as $categoryId => $category) {
            $categoryCounts[$categoryId] = [
                'category' => $category,
                'count' => $this->countTorrentsInCategory($category->category_id)
            ];
        }
        
        // Sort by count in descending order
        uasort($categoryCounts, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        
        // Limit the results
        $categoryCounts = array_slice($categoryCounts, 0, $limit, true);
        
        $result = [];
        foreach ($categoryCounts as $data) {
            $result[$data['category']->category_id] = $data['category'];
        }
        
        return $this->em()->getBasicCollection($result);
    }
}