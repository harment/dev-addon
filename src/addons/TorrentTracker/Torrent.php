<?php
namespace TorrentTracker;


use TorrentTracker\Helper\Bencode\Bencode;

class Torrent
{
	//public $announce = 'http://example.com/announce';
	public $announce = null;
	// Torrent Comment
	public $comment = null;
	// Created by Program
	public $created_by = null;

	private $data;
	
	function __construct() 
	{
		// Here you can load default announce URL, comment and created_by from your configuration file.
	}

    public static function createFromTorrentFile($path)
    {
        if (!is_file($path))
        {
            throw new \Exception('invalid_torrent_file');
        }

        if (!is_readable($path)) 
        { 
            throw new \Exception('Torrent does not exist or can not be read.');
        }

        // Create a new torrent
        $torrent = new static();
        $torrent->bdecode_file($path);

        return $torrent;
    }

    public function save($filename)
    {
    	if (!$filename || !is_writable($filename) || !is_writable(dirname($filename))) 
    	{
            throw new \Exception('Could not open file "' . $filename . '" for writing.');
        }

    	// Write the encoded data to the file
        file_put_contents($filename, Bencode::encode($this->data));
    }

	/**
	 * Data Setter
	 * @param array $data [array of public variables]
	 * eg:
	 *  $bcoder = new \Bhutanio\BEncode;
	 * 	$bcoder->set([
	 *		'announce'=>'http://www.example.com',
	 *		'comment'=>'Downloaded from example.com',
	 *		'created_by'=>'TorrentSite v1.0'
	 *	]);
	 */
	public function set($data=array())
	{
		if ( is_array($data) ) 
		{
			if ( isset($data['announce']) ) {
				$this->announce = $data['announce'];
			}
			if ( isset($data['comment']) ) {
				$this->comment = $data['comment'];
			}
			if ( isset($data['created_by']) ) {
				$this->created_by = $data['created_by'];
			}
		}
	}

	/**
	 * Decode a torrent file into Bencoded data
	 * @param  string $filename 	[File Path]
	 * @return array/null 			[Array of Bencoded data]
	 */
	public function bdecode_file($filename)
	{
		if ( is_file($filename) ) 
        {
			$f = file_get_contents($filename, FILE_BINARY);
			$this->data = Bencode::decodeTorrent($f);
		}

		return null;
	}

    /**
     * Generate list of files in a torrent
     * @param array $data [array data of a decoded torrent file]
     * @return array        [list of files in an array]
     * @throws \Exception
     */
	public function getFileList($precision = false)
	{
		$info = $this->getInfoPart();

        if (isset($info['length'])) 
        {
            if ($precision) 
            {
                return array($info['name'] => round($info['length'], $precision));
            }

            return $info['name'];
        }

        if ($precision) 
        {
            $files = array();
            foreach ($info['files'] as $file) 
            {
                $files[implode(DIRECTORY_SEPARATOR, $file['path'])] = round($file['length'], $precision);
            }

            return $files;
        }

        return $info['files'];
	}

   	public function getHash($raw = false) 
    {
        $info = $this->getInfoPart();
        return sha1(Bencode::encode($info), $raw);
    }

    public function getEncodedHash() 
    {
        return urlencode($this->getHash(true));
    }

    public function getInfo() 
    {
        if (isset($this->data['info']))
        {
        	return $this->data['info'];
        }

        return null;
    }

    public function setAnnounceWithAdditional($announceUrl,$additionalAnnounceUrl) 
    {
        $this->announce = array($announceUrl);
        $this->data['announce'] = $announceUrl;
        array_push($this->announce, $additionalAnnounceUrl);
    }

    public function setAnnounce($announceUrl) 
    {
        $this->announce = $announceUrl;
        $this->data['announce'] = $announceUrl;
    }

    public function getAnnounce()
    {
    	if (isset($this->data['announce']))
    	{
    		return $this->data['announce'];
    	}

    	return null;
    }

    public function setComment($comment) 
    {
        $this->comment = $comment;
        $this->data['comment'] = $comment;
    }

    public function getComment()
    {
    	if (isset($this->data['comment']))
    	{
    		return $this->data['comment'];
    	}

    	return null;
    }

    public function getCreatedBy()
    {
    	if (isset($this->data['created by']))
    	{
    		return $this->data['created by'];
    	}

    	return null;
    }	

    public function getSize()
    {
    	$info = $this->getInfoPart();

        // If the length element is set, return that one. If not, loop through the files and generate the total
        if (isset($info['length'])) 
        {
            return $info['length'];
        }

        $files = $this->getFileList();
        $size  = 0;

        foreach ($files as $file) 
        {
            $size = $this->add($size, $file['length']);
        }

        return $size;
    }

    public function getName() 
    {
        $info = $this->getInfoPart();

        return isset($info['name']) ? $info['name'] : '';
    }

	/**
	 * Replace array data on Decoded torrent data so that it can be bencoded into a private torrent file.
	 * Provide the custom data using $this->set();
	 * @param  array $data 	[array data of a decoded torrent file]
	 * @return array 		[array data for torrent file]
	 */
	public function setPrivate()
	{
		// Remove announce
		// announce-list is an unofficial extension to the protocol that allows for multiple trackers per torrent
		unset($this->data['announce']);
		unset($this->data['announce-list']);
		// Bitcomet & Azureus cache peers in here
		unset($this->data['nodes']);
		// Azureus stores the dht_backup_enable flag here
		unset($this->data['azureus_properties']);
		// Remove web-seeds
		unset($this->data['url-list']);
		// Remove libtorrent resume info
		unset($this->data['libtorrent_resume']);
		// Remove profiles / Media Infos
		unset($this->data['info']['profiles']);
		unset($this->data['info']['file-duration']);
		unset($this->data['info']['file-media']);

		// Add Announce URL
		if (is_array($this->announce) ) 
		{
			$this->data['announce'] = reset($this->announce);
			$this->data["announce-list"] = array();
			// $announce_list = array();
			foreach ($this->announce as $announceUri) 
			{
				$announce_list[] = array($announceUri);
			}

			$this->data["announce-list"] = $announce_list;
		} 
		else if(!empty($this->announce)) 
		{
			$this->data['announce'] = $this->announce;
		}

		// Add Comment
		if (!empty($this->comment)) 
		{
			$this->data['comment'] = $this->comment;
		}

		// Created by and Created on
		if (!empty($this->created_by)) 
		{
			$this->data['created by'] = $this->created_by;
		}

		// Make Private
		$this->data['info']['private'] = 1;

		// Sort by key to respect spec
		ksort($this->data['info']);
		ksort($this->data);

		//return $this->data;
	}

    private function getInfoPart() 
    {
        $info = $this->getInfo();

        if ($info === null) 
        { 
             throw new \Exception('The info part of the torrent is not set.');
        }

        return $info;
    }

    /**
     * Add method that should work on both 32 and 64-bit platforms
     *
     * @param int $a
     * @param int $b
     * @return int|string
     */
    private function add($a, $b) 
    {
        if (PHP_INT_SIZE === 4) 
        {
            return bcadd($a, $b);
        }

        return $a + $b;
    }
}