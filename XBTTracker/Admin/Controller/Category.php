<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Category extends AbstractController
{
    /**
     * Display categories list
     */
    public function actionIndex()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
        
        $viewParams = [
            'categories' => $categories
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Category\List', 'harment_xbttracker_admin_categories', $viewParams);
    }
    
    /**
     * Add category form
     */
    public function actionAdd()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        // Get parent categories for select
        $parentCategories = $this->getCategoryRepo()->findCategoriesForList()
            ->where('parent_id', '=', 0)
            ->fetch();
        
        $viewParams = [
            'category' => $this->em()->create('Harment\XBTTracker:Category'),
            'parentCategories' => $parentCategories
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Category\Add', 'harment_xbttracker_admin_category_add', $viewParams);
    }
    
    /**
     * Handle category addition
     */
    public function actionSave()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'parent_id' => 'uint',
            'display_order' => 'uint'
        ]);
        
        /** @var \Harment\XBTTracker\Entity\Category $category */
        $category = $this->em()->create('Harment\XBTTracker:Category');
        $category->bulkSet($input);
        
        if (!$category->save($errors)) {
            return $this->error($errors);
        }
        
        return $this->redirect($this->buildLink('tracker/categories'));
    }
    
    /**
     * Edit category form
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $categoryId = $params->get('category_id');
        $category = $this->assertCategoryExists($categoryId);
        
        // Get parent categories for select
        $parentCategories = $this->getCategoryRepo()->findCategoriesForList()
            ->where('parent_id', '=', 0)
            ->where('category_id', '!=', $categoryId)
            ->fetch();
        
        $viewParams = [
            'category' => $category,
            'parentCategories' => $parentCategories
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Category\Edit', 'harment_xbttracker_admin_category_edit', $viewParams);
    }
    
    /**
     * Handle category edit
     */
    public function actionSaveEdit(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $categoryId = $params->get('category_id');
        $category = $this->assertCategoryExists($categoryId);
        
        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'parent_id' => 'uint',
            'display_order' => 'uint'
        ]);
        
        // Prevent category being its own parent
        if ($input['parent_id'] == $category->category_id) {
            $input['parent_id'] = 0;
        }
        
        // Also prevent circular references (A -> B -> A)
        if ($input['parent_id'] > 0) {
            $parentCategory = $this->em()->find('Harment\XBTTracker:Category', $input['parent_id']);
            if ($parentCategory && $parentCategory->parent_id == $category->category_id) {
                $input['parent_id'] = 0;
            }
        }
        
        $category->bulkSet($input);
        
        if (!$category->save($errors)) {
            return $this->error($errors);
        }
        
        return $this->redirect($this->buildLink('tracker/categories'));
    }
    
    /**
     * Delete category confirmation
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $categoryId = $params->get('category_id');
        $category = $this->assertCategoryExists($categoryId);
        
        // Check if category has torrents
        $torrentCount = $this->finder('Harment\XBTTracker:Torrent')
            ->where('category_id', $category->category_id)
            ->total();
        
        // Check if category has sub-categories
        $childCount = $this->finder('Harment\XBTTracker:Category')
            ->where('parent_id', $category->category_id)
            ->total();
        
        // Get list of possible replacement categories
        $replacementCategories = $this->finder('Harment\XBTTracker:Category')
            ->where('category_id', '!=', $category->category_id)
            ->orderBy('title')
            ->fetch();
        
        $viewParams = [
            'category' => $category,
            'torrentCount' => $torrentCount,
            'childCount' => $childCount,
            'replacementCategories' => $replacementCategories
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Category\Delete', 'harment_xbttracker_admin_category_delete', $viewParams);
    }
    
    /**
     * Handle category deletion
     */
    public function actionDeleteConfirm(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $categoryId = $params->get('category_id');
        $category = $this->assertCategoryExists($categoryId);
        
        // Get replacement category if specified
        $replacementId = $this->filter('replacement_id', 'uint');
        $moveTorrents = $this->filter('move_torrents', 'bool');
        $moveChildren = $this->filter('move_children', 'bool');
        
        if ($moveTorrents && $replacementId) {
            $replacementCategory = $this->em()->find('Harment\XBTTracker:Category', $replacementId);
            if ($replacementCategory) {
                // Move torrents to replacement category
                $this->db()->update(
                    'xf_xbt_torrents',
                    ['category_id' => $replacementId],
                    'category_id = ?',
                    $categoryId
                );
                
                // Log the action
                $this->app()->logger()->info(
                    sprintf('Moved %d torrents from category %s to category %s', 
                        $this->db()->affectedRows(),
                        $category->title,
                        $replacementCategory->title
                    )
                );
            }
        }
        
        if ($moveChildren && $replacementId) {
            $replacementCategory = $this->em()->find('Harment\XBTTracker:Category', $replacementId);
            if ($replacementCategory) {
                // Move children to replacement category
                $this->db()->update(
                    'xf_xbt_categories',
                    ['parent_id' => $replacementId],
                    'parent_id = ?',
                    $categoryId
                );
                
                // Log the action
                $this->app()->logger()->info(
                    sprintf('Moved child categories from category %s to category %s', 
                        $category->title,
                        $replacementCategory->title
                    )
                );
            }
        }
        
        // Delete the category
        $category->delete();
        
        return $this->redirect($this->buildLink('tracker/categories'));
    }
    
    /**
     * Sort categories
     */
    public function actionSort()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
        
        if ($this->isPost()) {
            $sortData = $this->filter('categories', 'json-array');
            
            if (!empty($sortData)) {
                $this->sortCategories($sortData);
            }
            
            return $this->redirect($this->buildLink('tracker/categories'));
        }
        
        $viewParams = [
            'categories' => $categories
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Category\Sort', 'harment_xbttracker_admin_category_sort', $viewParams);
    }
    
    /**
     * Process category sorting data
     */
    protected function sortCategories(array $sortData, $parentId = 0, $displayOrder = 10)
    {
        foreach ($sortData as $categoryData) {
            $categoryId = $categoryData['id'];
            
            $this->db()->update(
                'xf_xbt_categories',
                [
                    'parent_id' => $parentId,
                    'display_order' => $displayOrder
                ],
                'category_id = ?',
                $categoryId
            );
            
            $displayOrder += 10;
            
            if (!empty($categoryData['children'])) {
                $this->sortCategories($categoryData['children'], $categoryId, 10);
            }
        }
    }
    
    /**
     * Assert category exists
     */
    protected function assertCategoryExists($categoryId)
    {
        $category = $this->em()->find('Harment\XBTTracker:Category', $categoryId);
        if (!$category) {
            throw $this->exception($this->notFound(\XF::phrase('harment_xbttracker_requested_category_not_found')));
        }
        
        return $category;
    }
    
    /**
     * Get category repository
     */
    protected function getCategoryRepo()
    {
        return $this->repository('Harment\XBTTracker:Category');
    }
}