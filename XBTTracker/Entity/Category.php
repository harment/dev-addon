<?php
// src/addons/XBTTracker/Entity/Category.php
namespace XBTTracker\Entity;

use XF\Mvc\Entity\Structure;
use XF\Mvc\Entity\Entity;

/**
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
 * @property-read Category|null $Parent
 * @property-read \XF\Mvc\Entity\ArrayCollection|Category[] $Children
 * @property-read \XF\Entity\Node|null $Node
 * @property-read \XF\Mvc\Entity\ArrayCollection|Torrent[] $Torrents
 */
class Category extends Entity
{
    /**
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
            'url' => true
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
     * دالة بديلة للتوافق مع الكود القديم
     *
     * @return string
     */
    public function getCategoryUrl()
    {
        return $this->getUrl();
    }
    
    /**
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
}