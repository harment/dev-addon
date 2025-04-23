<?php

namespace Harment\XBTTracker\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class Announcements extends AbstractController
{
    /**
     * List announcements
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionList()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $page = $this->filterPage();
        $perPage = 20;
        
        // Get announcements finder
        $announcementFinder = $this->finder('Harment\XBTTracker:Announcement')
            ->with('User')
            ->order('date', 'DESC');
        
        // Get total count for pagination
        $totalAnnouncements = $announcementFinder->total();
        
        // Apply pagination
        $announcementFinder->limitByPage($page, $perPage);
        
        // Get announcements
        $announcements = $announcementFinder->fetch();
        
        $viewParams = [
            'announcements' => $announcements,
            'totalAnnouncements' => $totalAnnouncements,
            'page' => $page,
            'perPage' => $perPage
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Announcements\List', 'harment_xbttracker_admin_announcements', $viewParams);
    }
    
    /**
     * Add announcement form
     *
     * @return \XF\Mvc\Reply\View
     */
    public function actionAdd()
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $viewParams = [
            'announcement' => $this->em()->create('Harment\XBTTracker:Announcement')
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Announcements\Add', 'harment_xbttracker_admin_announcement_add', $viewParams);
    }
    
    /**
     * Handle announcement addition
     *
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Error
     */
    public function actionAddSave()
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $input = $this->filter([
            'title' => 'str',
            'message' => 'str',
            'active' => 'bool',
            'display_order' => 'uint',
            'expiry_date' => 'datetime'
        ]);
        
        if (!$input['title']) {
            return $this->error(\XF::phrase('harment_xbttracker_please_enter_announcement_title'));
        }
        
        if (!$input['message']) {
            return $this->error(\XF::phrase('harment_xbttracker_please_enter_announcement_message'));
        }
        
        // Create announcement
        $announcement = $this->em()->create('Harment\XBTTracker:Announcement');
        $announcement->title = $input['title'];
        $announcement->message = $input['message'];
        $announcement->date = \XF::$time;
        $announcement->user_id = \XF::visitor()->user_id;
        $announcement->active = $input['active'];
        $announcement->display_order = $input['display_order'];
        $announcement->expiry_date = $input['expiry_date'] ?: null;
        
        if (!$announcement->save($errors)) {
            return $this->error($errors);
        }
        
        // Log the action
        $this->app()->logger()->info(
            'Tracker announcement added by ' . \XF::visitor()->username . ': ' . $announcement->title
        );
        
        return $this->redirect(
            $this->buildLink('tracker/announcements'),
            \XF::phrase('harment_xbttracker_announcement_added')
        );
    }
    
    /**
     * View announcement details
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionDetails(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $announcementId = $params->get('announcement_id');
        $announcement = $this->assertAnnouncementExists($announcementId);
        
        $viewParams = [
            'announcement' => $announcement
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Announcements\Details', 'harment_xbttracker_admin_announcement_details', $viewParams);
    }
    
    /**
     * Edit announcement form
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     */
    public function actionEdit(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $announcementId = $params->get('announcement_id');
        $announcement = $this->assertAnnouncementExists($announcementId);
        
        $viewParams = [
            'announcement' => $announcement
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Announcements\Edit', 'harment_xbttracker_admin_announcement_edit', $viewParams);
    }
    
    /**
     * Handle announcement edit
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\Error
     */
    public function actionEditSave(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $announcementId = $params->get('announcement_id');
        $announcement = $this->assertAnnouncementExists($announcementId);
        
        $input = $this->filter([
            'title' => 'str',
            'message' => 'str',
            'active' => 'bool',
            'display_order' => 'uint',
            'expiry_date' => 'datetime'
        ]);
        
        if (!$input['title']) {
            return $this->error(\XF::phrase('harment_xbttracker_please_enter_announcement_title'));
        }
        
        if (!$input['message']) {
            return $this->error(\XF::phrase('harment_xbttracker_please_enter_announcement_message'));
        }
        
        // Update announcement
        $announcement->title = $input['title'];
        $announcement->message = $input['message'];
        $announcement->active = $input['active'];
        $announcement->display_order = $input['display_order'];
        $announcement->expiry_date = $input['expiry_date'] ?: null;
        
        if (!$announcement->save($errors)) {
            return $this->error($errors);
        }
        
        // Log the action
        $this->app()->logger()->info(
            'Tracker announcement edited by ' . \XF::visitor()->username . ': ' . $announcement->title
        );
        
        return $this->redirect(
            $this->buildLink('tracker/announcements'),
            \XF::phrase('harment_xbttracker_announcement_updated')
        );
    }
    
    /**
     * Delete announcement
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View|\XF\Mvc\Reply\Redirect
     */
    public function actionDelete(ParameterBag $params)
    {
        $this->assertAdminPermission('harmentXbtTracker');
        
        $announcementId = $params->get('announcement_id');
        $announcement = $this->assertAnnouncementExists($announcementId);
        
        if ($this->isPost()) {
            // Store title for logging
            $title = $announcement->title;
            
            // Delete announcement
            $announcement->delete();
            
            // Log the action
            $this->app()->logger()->info(
                'Tracker announcement deleted by ' . \XF::visitor()->username . ': ' . $title
            );
            
            return $this->redirect(
                $this->buildLink('tracker/announcements'),
                \XF::phrase('harment_xbttracker_announcement_deleted')
            );
        }
        
        $viewParams = [
            'announcement' => $announcement
        ];
        
        return $this->view('Harment\XBTTracker:Admin\Announcements\Delete', 'harment_xbttracker_admin_announcement_delete', $viewParams);
    }
    
    /**
     * Toggle announcement active status
     *
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     */
    public function actionToggle(ParameterBag $params)
    {
        $this->assertPostOnly();
        $this->assertAdminPermission('harmentXbtTracker');
        
        $announcementId = $params->get('announcement_id');
        $announcement = $this->assertAnnouncementExists($announcementId);
        
        // Toggle active status
        $announcement->active = !$announcement->active;
        $announcement->save();
        
        // Log the action
        $status = $announcement->active ? 'activated' : 'deactivated';
        $this->app()->logger()->info(
            'Tracker announcement ' . $status . ' by ' . \XF::visitor()->username . ': ' . $announcement->title
        );
        
        return $this->redirect($this->buildLink('tracker/announcements'));
    }
    
    /**
     * Assert announcement exists
     *
     * @param int $announcementId
     * @return \Harment\XBTTracker\Entity\Announcement
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertAnnouncementExists($announcementId)
    {
        $announcement = $this->em()->find('Harment\XBTTracker:Announcement', $announcementId);
        if (!$announcement) {
            throw $this->exception($this->notFound(\XF::phrase('harment_xbttracker_announcement_not_found')));
        }
        
        return $announcement;
    }
}