<?php
// src/addons/XBTTracker/Repository/Category.php
namespace XBTTracker\Repository;

use XF\Mvc\Entity\Repository;

class Category extends Repository
{
    /**
     * Get root categories (no parent)
     *
     * @return \XF\Mvc\Entity\Finder
     */
    public function getRootCategories()
    {
        return $this->finder('XBTTracker:Category')
            ->where('parent_id', 0)
            ->order('display_order');
    }
    
    /**
     * Get child categories for a parent
     *
     * @param int $parentId
     * @return \XF\Mvc\Entity\Finder
     */
    public function getChildCategories($parentId)
    {
        return $this->finder('XBTTracker:Category')
            ->where('parent_id', $parentId)
            ->order('display_order');
    }
    
    /**
     * Get category tree
     *
     * @return array
     */
    public function getCategoryTree()
    {
        $rootCategories = $this->getRootCategories()->fetch();
        $tree = [];
        
        foreach ($rootCategories as $rootCategory) {
            $tree[$rootCategory->category_id] = [
                'category' => $rootCategory,
                'children' => $this->getChildCategoriesTree($rootCategory->category_id)
            ];
        }
        
        return $tree;
    }
    
    /**
     * Get child categories tree
     *
     * @param int $parentId
     * @return array
     */
    protected function getChildCategoriesTree($parentId)
    {
        $childCategories = $this->getChildCategories($parentId)->fetch();
        $tree = [];
        
        foreach ($childCategories as $childCategory) {
            $tree[$childCategory->category_id] = [
                'category' => $childCategory,
                'children' => $this->getChildCategoriesTree($childCategory->category_id)
            ];
        }
        
        return $tree;
    }
    
    /**
     * Get categories for select options
     *
     * @return array
     */
    public function getCategoryOptions()
    {
        $categories = $this->finder('XBTTracker:Category')
            ->order(['parent_id', 'display_order'])
            ->fetch();
            
        $options = [];
        $categoryTree = [];
        
        // Build a nested tree
        foreach ($categories as $category) {
            $categoryTree[$category->parent_id][$category->category_id] = $category;
        }
        
        // Build options with indent
        $this->buildCategoryOptionsRecursive($categoryTree, 0, $options);
        
        return $options;
    }
    
    /**
     * Build category options array recursively
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
}