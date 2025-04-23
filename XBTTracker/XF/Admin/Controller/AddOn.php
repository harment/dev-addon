<?php

namespace Harment\XBTTracker\XF\Admin\Controller;

/**
 * Class AddOnController
 * @package Harment\XBTTracker\XF\Admin\Controller
 */
class AddOnController extends XFCP_AddOnController
{
    /**
     * Override the original actionControls method to handle array options
     * 
     * @param \XF\Mvc\ParameterBag $params
     * @return \XF\Mvc\Reply\AbstractReply
     */
    public function actionControls(\XF\Mvc\ParameterBag $params)
    {
        $addOn = $this->assertAddOnAvailable($params->addon_id_url);

        $json = $addOn->getJson();
        if (!isset($json['options']) || $json['options'] === null)
        {
            /** @var \XF\Repository\Option $optionRepo */
            $optionRepo = $this->repository('XF:Option');

            /** @var \XF\Mvc\Entity\ArrayCollection $options */
            [$null, $options] = $optionRepo->getGroupsAndOptionsForAddOn($addOn->addon_id);

            $hasOptions = ($options->count() > 0);
        }
        else if (isset($json['options']) && !empty($json['options']))
        {
            // هنا التعديل: التحقق مما إذا كان options مصفوفة أو نص
            $hasOptions = is_string($json['options']) ? strlen($json['options']) : true;
        }
        else
        {
            $hasOptions = false;
        }

        $templates = $this->finder('XF:Template')
            ->where('addon_id', $addOn->addon_id)
            ->fetch()
            ->groupBy('type');

        $hasPublicTemplates = isset($templates['public']);
        $hasEmailTemplates = isset($templates['email']);

        if (\XF::$developmentMode)
        {
            $hasAdminTemplates = isset($templates['admin']);
        }
        else
        {
            $hasAdminTemplates = false;
        }

        $phraseFinder = $this->finder('XF:Phrase');
        $phrases = $phraseFinder->where('addon_id', $addOn->addon_id)->fetch();
        $hasPhrases = ($phrases->count() > 0);

        $viewParams = [
            'addOn' => $addOn,

            'hasOptions' => $hasOptions,
            'hasPublicTemplates' => $hasPublicTemplates,
            'hasEmailTemplates' => $hasEmailTemplates,
            'hasAdminTemplates' => $hasAdminTemplates,
            'hasPhrases' => $hasPhrases,

            'style' => $this->plugin('XF:Style')->getActiveEditStyle(),
            'masterStyle' => $this->repository('XF:Style')->getMasterStyle(),
            'language' => $this->plugin('XF:Language')->getActiveEditLanguage(),
        ];
        return $this->view('XF:AddOn\Controls', 'addon_controls', $viewParams);
    }
}