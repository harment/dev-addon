<?php

namespace TorrentTracker\Pub\Controller;

use XF\App;
use XF\Entity\User;
use XF\Http\Request;
use XF\Mvc\ParameterBag;
use XF\Pub\Controller\AbstractController;

class Torrent extends AbstractController
{
    public function __construct(App $app, Request $request, User $user = null)
    {
        parent::__construct($app, $request);
    }

    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertRegistrationRequired();
        if (!$this->getTorrentRepo()->canViewTorrents()) {
            throw $this->exception($this->noPermission());
        }
    }

    public function actionIndex(ParameterBag $params)
    {
        $defaultOrder = 'ctime';
        $orderDirection = 'DESC';
        $perPage = 20;
        $perPageOptions = \XF::options()->xenTTPerPage;
        if ($perPage) {
            $perPage = $perPageOptions;
        }
        $page = $this->filterPage();


        $linkFilters = [];

        //Node Tree
        $nodesRepo = $this->repository('XF:Node');
        $nodeList = $nodesRepo->getNodesListForTorrent();
        $nodeTree = $nodesRepo->createNodeTree($nodeList);
        $nodeExtras = $nodesRepo->getNodeListExtras($nodeTree);

        /* @var \TorrentTracker\Repository\Torrent $torrentRepo */
        $torrentRepo = $this->getTorrentRepo();

        if($this->options()->xenTorrentSeedingIndicator){ //Check if seeding indicator is enabled
            $seedingTorrents = $torrentRepo->getSeeedingTorrentsList();
        }else{
            $seedingTorrents = null;
        }

        $torrentFinder = $torrentRepo->findTorrentForList()->limitByPage($page, $perPage)->where('sticky', 0);
        $stickyTorrentFinder = $torrentRepo->findTorrentForList()->limitByPage($page, $perPage)->where('sticky', 1);

        if ($username = $this->filter('username', 'str')) {
            $user = $this->finder('XF:User')->where('username', $username)->fetchOne();
            if ($user) {
                $torrentFinder->where('user_id', $user->user_id);
                $linkFilters['username'] = $user->username;
            }
        }

        if ($start = $this->filter('start', 'datetime')) {
            $torrentFinder->where('ctime', '>', $start);
            $linkFilters['start'] = $start;
        }

        if ($end = $this->filter('end', 'datetime')) {
            $torrentFinder->where('ctime', '<', $end);
            $linkFilters['end'] = $end;
        }

        if ($filename = $this->filter('filename', 'str')) {
            $torrentFinder->where('Thread.title', 'LIKE', $torrentFinder->escapeLike($filename, '%?%'));
            $linkFilters['filename'] = $filename;
        }

        //Implementing Tag System
        if (\XF::options()->xenTorrentTagSystem) {
            if ($this->filter('tag', 'str')) {

                $tag = str_replace(" ","-",$this->filter('tag', 'str'));

                $tagData = $this->finder('XF:Tag')->where('tag_url', $tag)->fetchOne();
                $threadIds = null;
                if ($tagData) {
                    $tagContent = $this->finder('XF:TagContent')->where('tag_id', $tagData->tag_id)->fetch();
                    $threadIds = $tagContent->pluckNamed('content_id');
                }
                $torrentFinder->where('thread_id', $threadIds);
                $linkFilters['tag'] = $tag;
            }
        }

        if ($order = $this->filter('order', 'str')) {
            $defaultOrder = $order;
        }

        if ($direction = $this->filter('direction', 'str')) {
            $orderDirection = $direction;
        }

        if ($freeleech = $this->filter('freeleech', 'int')) {
            $linkFilters['freeleech'] = $freeleech;
            $torrentFinder->where('freeleech', 1);
            $stickyTorrentFinder->where('freeleech', 1);
        }

        if ($this->options()->xenTorrentPrefixSystem) {
            if ($prefix = $this->filter('prefix_id', 'int')) {
                $linkFilters['prefix_id'] = $prefix;
                $torrentFinder->where('Thread.prefix_id', '=', $prefix);
            }
        }


        $selectedCategoryId = $this->filter('category_id', 'int');

        $active = null;
        if ($nodeTree->getData($selectedCategoryId) !== null) {
            $active = $nodeTree->getData($selectedCategoryId);
        }

        $fields = array(
            'Thread.reply_count',
            'size',
            'completed',
            'seeders',
            'leechers',
            'ctime',
        );
        $iCon = [
            'Thread.reply_count' => '',
            'size' => '',
            'completed' => '',
            'seeders' => '',
            'leechers' => '',
            'ctime' => '',
        ];

        foreach ($fields as $field) {
            if ($order == $field) {
                $linkFilters['direction'] = ($orderDirection == 'desc' ? 'asc' : 'desc');
                $iCon[$order] = ($orderDirection == 'desc' ? '↓' : '↑');
            }

        }


        if ($selectedCategoryId) {
            $torrentFinder->where('Thread.node_id', $selectedCategoryId);
            $linkFilters['category_id'] = $selectedCategoryId;
        }

        $torrentFinder->order($defaultOrder, $orderDirection);
        if ($linkFilters && $this->isPost()) {
            return $this->redirect($this->buildLink('torrents', null, $linkFilters), '');
        }
        $total = $torrentFinder->total();
        $this->assertValidPage($page, $perPage, $total, 'torrent');

        $cache = $this->app->simpleCache()->TorrentTracker->statisticsCache;

        $pageNavParams = [
            'direction' => $orderDirection,
            'order' => $order
        ];

        $viewParams = array(
            'torrents' => $torrentFinder->fetch()->filterViewable(),
            'stickyTorrents' => $stickyTorrentFinder->fetch()->filterViewable(),
            'seedingTorrents' => $seedingTorrents,
            'page' => $page,
            'active' => $active,
            'nodeTree' => $nodeTree,
            'nodeExtras' => $nodeExtras,
            'perPage' => $perPage,
            'total' => $total,
            'linkFilters' => $linkFilters,
            'Statistics' => $cache,
            'pageNavParams' => array_merge($linkFilters, $pageNavParams),
            'iCon' => $iCon
        );
        return $this->view('', 'torrent_list_index', $viewParams);
    }

    public function actionTop()
    {
        $statsRepo = $this->getStatsRepo();

        $defaultType = 'user';
        if ($this->filter('type', 'str')) {
            $defaultType = $this->filter('type', 'str');
        }
        $active = null;
        $userType = [
            'uploaders' => \XF::phrase('top_uploaders'),
            'downloaders' => \XF::phrase('top_downloaders'),
            'snatchers' => \XF::phrase('top_snatchers'),
            'bsharers' => \XF::phrase('top_bsharers'),
            'wsharers' => \XF::phrase('top_wsharers'),
        ];
        $torrentType = [
            'active' => \XF::phrase('top_active'),
            'downloaders' => \XF::phrase('top_downloaders'),
            'completed' => \XF::phrase('top_completed'),
            'seeded' => \XF::phrase('top_seeded'),
        ];
        $user = [];
        $thread = [];
        if ($defaultType == 'user') {

            $defaultSubType = 'uploaders';
            if ($this->filter('subtype', 'str')) {
                $defaultSubType = $this->filter('subtype', 'str');
            }

            list($title, $description, $items) = $statsRepo->getTopUsers($defaultSubType);

            foreach ($items as $key => $item) {
                $user[$key] = $this->em()->find('XF:User', $item['user_id']);
            }
        } else {
            $defaultSubType = 'active';
            if ($this->filter('subtype', 'str')) {
                $defaultSubType = $this->filter('subtype', 'str');
            }
            list($title, $description, $items) = $statsRepo->getTopTorrents($defaultSubType);
            foreach ($items as $key => $item) {
                $thread[$key] = $this->em()->find('XF:Thread', $item['thread_id']);
            }
        }

        if ($defaultType == 'user' & isset($defaultSubType)) {
            $active = $defaultSubType;
        } elseif ($defaultType == 'torrent' & isset($defaultSubType)) {
            $active = $defaultSubType;
        }

        $viewParams = array(
            'type' => $defaultType,
            'subtype' => $defaultSubType,
            'active' => $active,
            'items' => $items,
            'user' => $user,
            'thread' => $thread,
            'torrentType' => $torrentType,
            'userType' => $userType,
            'title' => $title,
            'description' => $description
        );
        return $this->view('', 'xentorrent_top', $viewParams);
    }

    public function actionRequestReseed(ParameterBag $params, &$error = null)
    {
        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $visitor = \XF::visitor();
        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();

        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }

        $attachment = $torrent->Attachment;
        if (!$attachment->canView($error)) {
            return $this->noPermission($error);
        }

        $reseedInterval = \XF::options()->xenTorrentReseedInterval;
        if (!($torrent['seeders'] == 0 && \XF::$time > ($torrent['ctime'] + $reseedInterval))) {
            return $this->error(\XF::phrase('request_failed'));
        }

        if (($torrent['last_reseed_request'] + $reseedInterval) > \XF::$time) {
            return $this->error(\XF::phrase('there_was_already_reseed_request_for_this_torrent'));
        }

        $maxReseedRequest = $visitor->hasPermission('xenTorrentTracker', 'maxReseedRequest');
        if (!$maxReseedRequest) {
            return $this->noPermission();
        }

        if ($maxReseedRequest > 0) {
            $requests = $torrentRepo->getReseedRequestByUserId($visitor['user_id']);
            if ($requests >= $maxReseedRequest) {
                return $this->error(\XF::phrase('you_have_already_sent_max_number_of_reseed_request_for_the_day'));
            }
        }

        $finder = $this->finder('TorrentTracker:Snatched')->with('User', true);
        $user = $finder->where('torrent_id', $torrentId)->fetch(50);


        //Removes the Requesters ID
        if (isset($user[$visitor['user_id']])) {
            unset($user[$visitor['user_id']]);
        }

        /** @var \XF\Repository\UserAlert $alertRepo */
        $alertRepo = $this->repository('XF:UserAlert');

        $sender = \XF::visitor();

        //Send Alert to Snatchers
        if (!empty($user)) {
            foreach ($user as $u) {
                $extra = [];
                $extra = array_merge([
                    'action' => 'completed',
                    'time' => $u['mtime'],
                ], $extra);
                $alertRepo->alertFromUser($u->User, $sender, 'thread', $torrent['thread_id'], 'reseed', $extra);
                // Create Template for Alert like alert_.$contentType._.$action
            }

        }

        // Send Alert to torrent/thread creator also

        if (!empty($torrent['user_id'])) {
            $finder2 = $this->finder('TorrentTracker:Torrent');
            $tuser = $finder2->where('torrent_id', $torrentId)->fetchOne();

            $extra2 = [];
            $extra2 = array_merge([
                'action' => 'uploaded',
                'time' => $tuser['ctime'],
            ], $extra2);
            $alertRepo->alertFromUser($tuser->User, $sender, 'thread', $torrent['thread_id'], 'reseed', $extra2);
        }

        $torrentRepo->insertReseedRequest($visitor['user_id'], $torrentId);
        $torrent->last_reseed_request = \XF::$time;
        $torrent->save();

        return $this->message(\XF::phrase('request_sent'));
    }


    public function actionRequestFreeleech(ParameterBag $params)
    {

        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $visitor = \XF::visitor();

        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();

        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }

        if (!$visitor->canMakeFreeleechRequest()) {
            return $this->noPermission();
        }

        if ($this->isPost()) {

            $requestRepo = $this->getrequestRepo();

            if (!$requestRepo->getRequestByTorrentId($torrentId)) {

                $requester = $visitor;
                $userid = $visitor->user_id;

                $requestRepo->insertRequest($torrentId, $userid);

                /***
                 **  Alert to Staff on Freeleech Request
                 ***/

                $finder = \XF::finder('XF:User');
                $staffs = $finder->where('is_staff', 1)->fetch();

                /** @var \XF\Repository\UserAlert $alertRepo */
                $alertRepo = $this->repository('XF:UserAlert');

                if (!empty($staffs)) {
                    foreach ($staffs as $s) {
                        $extra = [];

                        $extra = array_merge([
                            'action' => 'freeleech',
                            'time' => \XF::$time,
                        ], $extra);

                        $alertRepo->alertFromUser($s, $requester, 'thread', $torrent['thread_id'], 'freeleech', $extra);
                        // Create Template for Alert like alert_.$contentType._.$action
                    }
                }


            }

            $redirect = $this->message(\XF::phrase('make_freeleech_success'));
            return $redirect;
        } else {
            $viewParams = [
                'torrent' => $torrent
            ];
            return $this->view('', 'sent_freeleech_request', $viewParams);

        }
    }

    public function actionCheckStaff()
    {
        $finder = \XF::finder('XF:User');
        $staffs = $finder->where('is_staff', 1)->fetch();

        foreach ($staffs as $s) {
            \XF::dump($s);
        }

        $finder = $this->finder('TorrentTracker:Snatched')->with('User', true);
        $user = $finder->where('torrent_id', 1)->fetch(50);

        foreach ($user as $u) {
            \XF::dump($u->User);
        }

    }

    public function actionMakeSticky(ParameterBag $params)
    {
        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $countSticky = $torrentRepo->countStickyTorrents();
        $visitor = \XF::visitor();

        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();

        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }

        if (!$visitor->canStickUnstickTorrent()) {
            return $this->noPermission();
        }

        if ($this->isPost()) {

            if ($torrent['sticky'] == 0) {
                $torrent->sticky = 1;
                $torrent->save();
                $newState = '1';
            } else {
                $torrent->sticky = 0;
                $torrent->save();
                $newState = '0';
            }
            $redirect = $this->message(\XF::phrase('make_sticky_torrent_success'));
            if ($newState == 0) {
                $redirect = $this->message(\XF::phrase('remove_sticky_torrent_success'));
            }

            $redirect->setJsonParam('switchKey', $newState == 0 ? 'make' : 'remove');
            return $redirect;
        } else {
            $viewParams = [
                'torrent' => $torrent,
                'stickyTorrents' => $countSticky
            ];
            return $this->view('', 'make_sticky_torrent', $viewParams);

        }

    }


    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Message|\XF\Mvc\Reply\View
     * @throws \XF\PrintableException
     */
    public function actionMakeFreeleech(ParameterBag $params)
    {

        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $visitor = \XF::visitor();


        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();

        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }

        if (!$visitor->canAcceptFreeleechRequest()) {
            return $this->noPermission();
        }

        /*
         * Fetches $requestId from url eg:- index.php?torrents/6/make-freeleech/&request_id=5
         */
        $requestId = $this->filter('request_id', 'int');

        if ($requestId) {
            $request = $this->em()->Find('TorrentTracker:Request', $requestId);
            if ($request) {
                $request->action = 'accept';
                $request->open = '0';
                $request->save();
                $torrent->freeleech = 1;
                $torrent->save();

                /** @var \XF\Repository\UserAlert $alertRepo */
                $alertRepo = $this->repository('XF:UserAlert');

                //Delete Freeleech Request Alert
                $alertRepo->fastDeleteAlertsFromUser($request->user_id, 'thread', $request->torrent_id, 'freeleech');


                $redirect = $this->message(\XF::phrase('make_freeleech_success'));
                return $redirect;
            }
        }


        if ($this->isPost()) {

            if ($torrent['freeleech'] == 0) {
                $torrent->freeleech = 1;
                $torrent->save();
                $newState = '1';
            } else {
                $torrent->freeleech = 0;
                $torrent->save();
                $newState = '0';
            }
            $redirect = $this->message(\XF::phrase('make_freeleech_success'));
            if ($newState == 0) {
                $redirect = $this->message(\XF::phrase('remove_freeleech_success'));
            }

            $redirect->setJsonParam('switchKey', $newState == 0 ? 'make' : 'remove');
            return $redirect;
        } else {
            $viewParams = [
                'torrent' => $torrent
            ];
            return $this->view('', 'make_free_leech', $viewParams);

        }

    }

    public function actionRemoveFreeleech(ParameterBag $params)
    {
        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $visitor = \XF::visitor();
        $requestId = $this->filter('request_id', 'int');

        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();
        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }

        if (!$visitor->canAcceptFreeleechRequest()) {
            return $this->noPermission();
        }

        if ($torrent['freeleech'] == 1) {
            $torrent->freeleech = 0;
            $torrent->save();
        }

        if ($requestId) {
            $request = $this->em()->Find('TorrentTracker:Request', $requestId);
            if ($request) {
                $request->action = 'reject';
                $request->open = '0';
                $request->save();

                /** @var \XF\Repository\UserAlert $alertRepo */
                $alertRepo = $this->repository('XF:UserAlert');

                //Delete Freeleech Request Alert
                $alertRepo->fastDeleteAlertsFromUser($request->user_id, 'thread', $request->torrent_id, 'freeleech');
            }
        }

        $redirect = $this->message(\XF::phrase('remove_freeleech_success'));
        return $redirect;
    }

    public function actionFiles(ParameterBag $params, &$error = null)
    {
        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $visitor = \XF::visitor();

        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();
        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }
        $attachment = $torrent->Attachment;
        if (!$attachment) {
            return $this->notFound($error);
        }

        if (!$attachment->canView($error)) {
            return $this->noPermission($error);
        }

        $viewParams = array(
            'files' => $torrent->Info->file_details,
        );

        return $this->view('', 'xentorrent_post_torrent_file_list', $viewParams);
    }

    public function actionPeerList(ParameterBag $params, &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewPeerList()) {
            return $this->noPermission();
        }

        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();
        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }
        $attachment = $torrent->Attachment;

        if (!$attachment->canView($error)) {
            return $this->noPermission($error);
        }

        $perPage = 20;
        $page = $this->filterPage();
        // $page = $params->page;
        $peers = $torrentRepo->getPeerList($torrentId)->limitByPage($page, $perPage)->fetch();
        $total = $torrentRepo->getPeerList($torrentId)->total();

        $viewParams = array(
            'torrent' => $torrent,
            'peers' => $peers,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,

        );

        return $this->view('', 'xentorrent_post_torrent_peer_list', $viewParams);
    }

    public function actionSnatchers(ParameterBag $params, &$error = null)
    {
        $visitor = \XF::visitor();

        if (!$visitor->canViewSnatchList()) {
            return $this->noPermission();
        }

        $torrentId = $params->torrent_id;
        $torrentRepo = $this->getTorrentRepo();
        $torrent = $torrentRepo->findTorrentInfo($torrentId)->fetchOne();
        if (!$torrent) {
            return $this->error(\XF::phrase('requested_torrent_not_found'));
        }
        $attachment = $torrent->Attachment;
        if (!$attachment->canView($error)) {
            return $this->noPermission($error);
        }

        $perPage = 20;
        $page = $this->filterPage();

        $snatchers = $torrentRepo->getSnapList($torrentId)->limitByPage($page, $perPage)->fetch();
        $total = $torrentRepo->getSnapList($torrentId)->total();

        $viewParams = array(
            'torrent' => $torrent,
            'snatchers' => $snatchers,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,

        );

        return $this->view('', 'xentorrent_post_torrent_snatchers', $viewParams);
    }


    public function actionUpload()
    {
        $visitor = \XF::visitor();
        if (!$visitor->canUploadTorrent()) {
            throw $this->exception($this->noPermission());
        }

        if ($this->isPost()) {
            $nodeId = $this->filter('node_id', 'int');
            $forum = $this->em()->Find('XF:Forum', $nodeId);
            return $this->redirectPermanently($this->buildLink('forums/post-thread', $forum));
        }
        $torrentRepo = $this->getTorrentRepo();

        //Node Tree
        $nodesRepo = $this->repository('XF:Node');
        $nodeList = $nodesRepo->getNodesListForTorrent();
        $nodeTree = $nodesRepo->createNodeTree($nodeList);
        $nodeTree = $nodeTree->filter(null, function ($id, \XF\Entity\Node $node, $depth, $children, \XF\Tree $tree) {
            if ($children) {
                return true;
            }
            if (
                $node->node_type_id == 'Forum'
                && (
                    $node->Data->canCreateThread()
                    || $node->Data->canCreateThreadPreReg()
                )
            ) {
                return true;
            }
            return false;
        });

        $nodeExtras = $nodesRepo->getNodeListExtras($nodeTree);

        $viewParams = array(
            'categoryTree' => $nodeTree,
            'categoryExtras' => $nodeExtras
        );

        return $this->view('', 'xentorrent_choose_category', $viewParams);
    }


    public function actionFreeleechRequests()
    {

        $visitor = \XF::visitor();

        if (!$visitor->canAcceptFreeleechRequest()) {
            return $this->noPermission();
        }

        if (!$visitor->canViewTorrents()) {
            throw $this->exception($this->noPermission());
        }
        $perPage = 20;

        $open = $this->filter('open', 'int');
        if (!$open) {
            $open = 1;
        }
        $perPageOptions = \XF::options()->xenTTPerPage;
        if ($perPage) {
            $perPage = $perPageOptions;
        }
        $page = $this->filterPage();

        $torrentRepo = $this->getTorrentRepo();
        $torrentFinder = $torrentRepo->findTorrentFreeleechRequesetForList($open)->limitByPage($page, $perPage);
        $requests = $torrentFinder->fetch();
        $total = $torrentFinder->total();

        $viewParams = array(
            'open' => $open,
            'requests' => $requests,
            'perPage' => $perPage,
            'page' => $page,
            'total' => $total,
        );
        return $this->view('', 'xentorrent_freeleech_request_list', $viewParams);
    }

    /**
     * @return \XF\Mvc\Entity\Repository
     */
    protected function getTorrentRepo()
    {
        return $this->repository('TorrentTracker:Torrent');
    }

    protected function getStatsRepo()
    {
        return $this->repository('TorrentTracker:Stats');
    }

    protected function getrequestRepo()
    {
        return $this->repository('TorrentTracker:FreeleechRequest');
    }

    public static function getActivityDetails(array $activities)
    {
        return \XF::phrase('xftt_torrent_activity');
    }


}
