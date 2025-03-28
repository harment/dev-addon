<?php

namespace TorrentTracker\Alert;

use XF\Mvc\Entity\Entity;

class Reseed extends \XF\Alert\AbstractHandler
{
     public function canViewContent(Entity $entity, &$error = null)
	{
          return true;
     }
}
