// استكمال لملف src/addons/XBTTracker/Listener.php
            if (!$torrents->count())
            {
                return;
            }
            
            $viewParams = [
                'forum' => $forum,
                'category' => $category,
                'torrents' => $torrents
            ];
            
            $output = $templater->renderTemplate('public:xbt_forum_torrents', $viewParams);
            $contents .= $output;
        }
    }
}