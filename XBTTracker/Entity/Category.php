<?php
// src/addons/XBTTracker/Entity/Category.php


namespace XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;



class Category extends \XF\Mvc\Entity\Entity
{
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
            'url' => true,
            'breadcrumb' => true
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
                'conditions' => 'node_id',
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
     * Get URL parameters
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
     * Get breadcrumb for category
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
}
