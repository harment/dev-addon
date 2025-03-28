<?php

namespace TorrentTracker\XF\Admin\Controller;

class User extends XFCP_User
{ 
	protected function userSaveProcess(\XF\Entity\User $user)
	{
		$parent = parent::userSaveProcess($user);
		$input = $this->filter([
			'user' => [
				'downloaded' => 'uint',
				'uploaded' => 'uint',
				'wait_time' => 'uint',
				'peers_limit' => 'uint',
				'seedbonus' => 'uint',
				'freeleech' => 'bool',
				'can_leech' => 'bool',
				'upload_multiplier' => 'uint',
				'download_multiplier' => 'uint',
			]
		]);
		if ($this->filter('reset_passkey', 'bool'))
			{
				$input['user']['torrent_pass_version'] = mt_rand();
			}

        $user->bulkSet($input['user']);
		return $parent;
	}
 
}