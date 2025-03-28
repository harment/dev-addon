<?php

namespace TorrentTracker;

use XF\Mvc\Entity\Entity;


class Listener
{

    public static function criteriaUser($rule, array $data, \XF\Entity\User $user, &$returnValue)
    {
        switch ($rule) {
            case 'xentt_uploaded':
                if (isset($user['uploaded']) && ($user['uploaded'] >= ($data['uploaded'] * 1048576))) {
                    $returnValue = true;
                }
                break;

            case 'xentt_downloaded':
                if (isset($user['downloaded']) && ($user['downloaded'] >= ($data['downloaded'] * 1048576))) {
                    $returnValue = true;
                }
                break;

            case 'xentt_ratio_less':
                if (isset($user['ratio']) && ($user['ratio']) < ($data['ratio_less'])) {
                    $returnValue = true;
                }
                break;
            case 'xentt_ratio_greater':
                if (isset($user['ratio']) && ($user['ratio']) > ($data['ratio_greater'])) {
                    $returnValue = true;
                }
                break;

            case 'xentt_uploaded_torrent':
                if (isset($user['torrentsCount']) && ($user['torrentsCount'] >= ($data['uploaded_torrent']))) {
                    $returnValue = true;
                }
                break;
        }
    }

    public static function forumEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['torrent_category_image'] = ['type' => Entity::STR, 'default' => ''];
    }

    public static function nodeEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['is_torrent_category'] = ['type' => Entity::UINT, 'default' => 0];
        $structure->columns['upload_multiplier'] = ['type' => Entity::UINT, 'default' => 1];
        $structure->columns['download_multiplier'] = ['type' => Entity::UINT, 'default' => 1];
    }

    public static function userEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $option = \XF::options()->xenTTNewUser;
        $structure->columns['torrent_pass_version'] = ['type' => Entity::UINT, 'default' => 0];
        $structure->columns['downloaded'] = ['type' => Entity::STR, 'default' => 0];
        $structure->columns['uploaded'] = ['type' => Entity::STR, 'default' => 0];
        $structure->columns['can_leech'] = ['type' => Entity::UINT, 'default' => !empty($option['can_leech']) ? 1 : 0];
        $structure->columns['wait_time'] = ['type' => Entity::UINT, 'default' => !empty($option['wait_time']) ? $option['wait_time'] : 0];
        $structure->columns['peers_limit'] = ['type' => Entity::UINT, 'default' => !empty($option['peers_limit']) ? $option['peers_limit'] : 0];
        $structure->columns['torrent_pass'] = ['type' => Entity::STR, 'default' => ''];
        $structure->columns['seedbonus'] = ['type' => Entity::STR, 'default' => 0];
        $structure->columns['freeleech'] = ['type' => Entity::UINT, 'default' => 0];
        $structure->columns['upload_multiplier'] = ['type' => Entity::UINT, 'default' => 1];
        $structure->columns['download_multiplier'] = ['type' => Entity::UINT, 'default' => 1];
        $structure->columns['torrent_upload_count'] = ['type' => Entity::UINT, 'default' => 0];
    }

    /**
     * @param \XF\Mvc\Entity\Manager $em
     * @param \XF\Mvc\Entity\Structure $structure
     */
    public static function userUpgradeEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {
        $structure->columns['xentt_options'] = ['type' => Entity::JSON_ARRAY, 'default' => []];
    }

    public static function threadEntityStructure(\XF\Mvc\Entity\Manager $em, \XF\Mvc\Entity\Structure &$structure)
    {

        $structure->relations['Torrent'] = [
            'entity' => 'TorrentTracker:Torrent',
            'type' => Entity::TO_ONE,
            'conditions' => 'thread_id',
            'with' => ['Request', 'Info']
        ];
    }

    public static function templaterMacroPreRender(\XF\Template\Templater $templater, &$type, &$template, &$name, array &$arguments, array &$globalVars)
    {
        if ($arguments['group']->group_id == 'xenTorrentTrackerOptions') {
        // Override template name
            $template = 'torrent_tracker_option_macros';

        // Or use 'option_form_block_tabs' for tabs
            $name = 'option_form_block_tabs';

        // Your block header configurations
            $arguments['headers'] = [
                'generalOptions' => [
                    'label' => \XF::phrase('xfdev_main_tracker_settings'),
                    'minDisplayOrder' => 0,
                    'maxDisplayOrder' => 199,
                    'active' => true // Only used for tabs, indicates default active tab
                ],
                'newUserSettings' => [
                    'label' => \XF::phrase('xfdev_new_user_tracker_settings'),
                    'minDisplayOrder' => 199,
                    'maxDisplayOrder' => 300,
                ],
                'basicTorrentSettings' => [
                    'label' => \XF::phrase('xfdev_basic_tracker_settings'),
                    'minDisplayOrder' => 300,
                    'maxDisplayOrder' => 445,
                ],
                'additionalSettings' => [
                    'label' => \XF::phrase('xfdev_additional_settings'),
                    'minDisplayOrder' => 445,
                    'maxDisplayOrder' => 650
                ],
                'xbtConfiguration' => [
                    'label' => \XF::phrase('xfdev_xbt_configuration'),
                    'minDisplayOrder' => 650,
                    'maxDisplayOrder' => -1 // This allows for any higher display order value
                ],
            ];
        }
    }

}