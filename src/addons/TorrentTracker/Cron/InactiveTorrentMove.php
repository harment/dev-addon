<?php

namespace TorrentTracker\Cron;

class InactiveTorrentMove
{
    public static function runInactiveTorrentMove()
    {
        $options = \XF::options();

        // No. of Days after which Inactive torrents should auto move.
        $days = $options->xfttInactiveTorrentsMoveDays;

        // convert to Unix timesatmp
		$timestamp = time() - (86400 * $days);

        // Get Destination Forum where Torrents Needs to be moved.
        $destinationForum = $options->xfttInactiveTorrentMoveDestinationForum;
        
        //Show error if no destination forum is selected.
        if(empty($destinationForum))
        {
            \XF::app()->error()->logError("Torrents can't move automatically as no destination forum is specified.");
			return;
        }

        // Show error if none is selected in destination forum.
		if ($destinationForum == 0)
		{
			\XF::app()->error()->logError("The Destination forum cannot have (None) as one of the selected forums.");
			return;
        }

        // Find Inactive Torrents
        $torrentFinder = \XF::finder('TorrentTracker:Torrent');
        $torrents = $torrentFinder
            ->where('mtime', '<', $timestamp)
            ->fetch();

        //Move Dead Torrents to the specified Destination.

        foreach ($torrents as $k => $v) {
            $targetNodeId = $destinationForum;
			$targetForum = \XF::app()->em()->find('XF:Forum', $targetNodeId);

            $threadFinder  = \XF::finder('XF:Thread');
            $thread = $threadFinder
                        ->where('thread_id', '=', $v['thread_id'])
                        ->fetchOne();

            //Run Mover Only if any thread exists for that torrent
            if($thread)
            {
	            // Moving Thread
				$mover = \XF::app()->service('XF:Thread\Mover', $thread);
				$mover->move($targetForum);
			}
        }
        
    }
}