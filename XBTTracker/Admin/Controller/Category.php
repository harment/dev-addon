<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Category extends AbstractController
{
    /**
     * Display categories list
     */
    public function actionList()
    {
        $this->assertAdminPermission('xbtTracker');
        
        $categories = $this->getCategoryRepo()->findCategoriesForList()->fetch();
        
        $viewParams = [
            'categories' => $categories
        ];
        
        return $this->view('XBTTracker:Admin\Category\List', 'xbt_admin_categories', $viewParams);
    }
    
    /**
     * Add category form
     */
    public function actionAdd()
    {
        $this->assertAdminPermission('xbtTracker');
        
        // Get parent categories for select
        $parentCategories = $this->getCategoryRepo()->findCategoriesForList()
            ->where('parent_id', '=', 0)
            ->fetch();
        
        $viewParams = [
            'category' => $this->em()->create('XBTTracker:Category'),
            'parentCategories' => $parentCategories
        ];
        
        return $this->view('XBTTracker:Admin\Category\Add', 'xbt_admin_category_add', $viewParams);
    }
    
    /**
     * Handle category addition
     */
    public function actionAddSave()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('xbtTracker');
        
        $input = $this->filter([
            'title' => 'str',
            'description' => 'str',
            'parent_id' => 'uint',
            'display_order' => 'uint'
        ]);
        
        /** @var \XBTTracker\Entity\Category $category */
        $category = $this->em()->create('XBTTracker:Category');
        $category->bulkSet($input);
        
        if (!$category->save($errors)) {
            return $this->error($errors);
        }
        
        return $this->redirect($this->buildLink('torrents/categories'));
    }
    
    /**
     * Edit category form
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertAdminPermission('xbtTracker');
        
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
        
        return $this->view('XBTTracker:Admin\Category\Edit', 'xbt_admin_category_edit', $viewParams);
    }
    
    /**
     * Handle category edit
     */
    public function actionEditSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('xbtTracker');
        
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
        
        $category->bulkSet($input);
        
        if (!$category->save($errors)) {
            return $this->error($errors);
        }
        
        return $this->redirect($this->buildLink('torrents/categories'));
    }
    
    /**
     * Delete category confirmation
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertAdminPermission('xbtTracker');
        
        $categoryId = $params->get('category_id');
        $category = $this->assertCategoryExists($categoryId);
        
        // Check if category has torrents
        $torrentCount = $this->finder('XBTTracker:Torrent')
            ->where('category_id', $category->category_id)
            ->total();
        
        // Check if category has sub-categories
        $childCount = $this->finder('XBTTracker:Category')
            ->where('parent_id', $category->category_id)
            ->total();
        
        $viewParams = [
            'category' => $category,
            'torrentCount' => $torrentCount,
            'childCount' => $childCount
        ];
        
        return $this->view('XBTTracker:Admin\Category\Delete', 'xbt_admin_category_delete', $viewParams);
    }
    
    /**
     * Handle category deletion
     */
    public function actionDeleteConfirm(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('xbtTracker');
        
        $categoryId = $params->get('category_id');
        $category = $this->assertCategoryExists($categoryId);
        
        // Get replacement category if specified
        $replacementId = $this->filter('replacement_id', 'uint');
        $moveTorrents = $this->filter('move_torrents', 'bool');
        $moveChildren = $this->filter('move_children', 'bool');
        
        if ($moveTorrents && $replacementId) {
            $replacementCategory = $this->em()->find('XBTTracker:Category', $replacementId);
            if ($replacementCategory) {
                // Move torrents to replacement category
                $this->db()->update(
                    'xf_xbt_torrents',
                    ['category_id' => $replacementId],
                    'category_id = ?',
                    $categoryId
                );
            }
        }
        
        if ($moveChildren && $replacementId) {
            $replacementCategory = $this->em()->find('XBTTracker:Category', $replacementId);
            if ($replacementCategory) {
                // Move children to replacement category
                $this->db()->update(
                    'xf_xbt_categories',
                    ['parent_id' => $replacementId],
                    'parent_id = ?',
                    $categoryId
                );
            }
        }
        
        // Delete the category
        $category->delete();
        
        return $this->redirect($this->buildLink('torrents/categories'));
    }
    
    /**
     * Assert category exists
     */
    protected function assertCategoryExists($categoryId)
    {
        $category = $this->em()->find('XBTTracker:Category', $categoryId);
        if (!$category) {
            throw $this->exception($this->notFound());
        }
        
        return $category;
    }
    
    /**
     * Get category repository
     */
    protected function getCategoryRepo()
    {
        return $this->repository('XBTTracker:Category');
    }
}