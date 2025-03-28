<?php
namespace TorrentTracker\Inc;
use GuzzleHttp\Exception\GuzzleException;

class Tracker
{
	const NUMBER_OF_TRIES = 2;
	
	public static function updateTracker(array $params, &$logError)
	{
		$config = self::getTrackerRepo()->getConfig();
		$tracker = \XF::options()->xenTorrentTracker;

		$host = $tracker['host'];
		if (empty($host))
		{
			return \XF::phrase('torrent_tracker_host_is_not_set');
		}

		if (!isset($config['torrent_pass_private_key']))
		{
			return \XF::phrase('private_key_not_set');
		}

		$host = rtrim($host, '/');
		$port = $config['listen_port'];
		$key = $config['torrent_pass_private_key'];
		$action = $params['action'];

		if(\XF::options()->xfdevTrackerWithoutPort){
            if(\XF::options()->xenTorrentHttpsTracker)
            {
                $url = "https://$host/update?key=$key&action=$action";
            }else{
                $url = "http://$host/update?key=$key&action=$action";
            }
        }else{
            if(\XF::options()->xenTorrentHttpsTracker)
            {
                $url = "https://$host:$port/update?key=$key&action=$action";
            }else{
                $url = "http://$host:$port/update?key=$key&action=$action";
            }
        }

		$numberOfTries = self::NUMBER_OF_TRIES;
		$message = '';

		while ($numberOfTries)
		{
				$client = \XF::app()->http()->client();

                try {
                    $response = $client->request('GET', $url, [
                        'timeout' => 15,
                        'exceptions' => true,
                        'keepalive' => true
                    ]);

                    $body = $response->getBody();
                    $content = $body ? $body->getContents() : '';
                    $code = $response->getStatusCode();

                    if ($content)
                    {
                        $logError = false;
                        return $content;
                    }
                } catch (GuzzleException $e) {
                    $logError = $e;
                }

            $numberOfTries--;
		}

		return $message;
	}

	public static function send(array $params = array(), $log = true)
	{
		if (!isset($params['action']))
		{
			return;
		}

		$error = false;
		$start = microtime(true); 

		$message = self::updateTracker($params, $error); 
		if (!$log)
		{
			return self::_returnResponse($message);
		}

		if ($error) 
		{
			$message = $error->getMessage();
			$response = false;
		}
		else
		{
			$response = self::_returnResponse($message);
		}

		$logMessage = $message . self::_getTime($start, microtime(true));
		$db = \XF::db();

		try 
		{
			$db->insert('xftt_log', array(
				'log_date' => \XF::$time,
				'message' => $logMessage,
				'params'  => serialize($params),
				'action'  => $params['action'],
				'is_error' => empty($error) ? 0 : 1
			));

			if (!empty($error))
			{
				$file = $error->getFile();
				$messagePrefix = 'XenTT Error: ';

				$db->insert('xf_error_log', array(
					'exception_date' => \XF::$time,
					'user_id' => null,
					'ip_address' => '',
					'exception_type' => get_class($error),
					'message' => $messagePrefix . $error->getMessage(),
					'filename' => $file,
					'line' => $error->getLine(),
					'trace_string' => $error->getTraceAsString(),
					'request_state' => serialize($params)
				));
			}
		} 
		catch (\Exception $e) {}

		return $response;
	}

	protected static function _returnResponse($response)
	{
		$response = str_replace(array("\n\r", "\n", "\r"), '', trim($response));

		try
		{
			$response = json_decode($response, true);
		}
		catch(\Exception $e)
		{
			$response = array();
		}

		return $response;
	}

	protected static function _getTime($start, $end)
	{
		$time = ($end - $start) * 1000;
		if ($time >= 1000)
		{
			return ' (' . number_format(($time / 1000), 2) .' seconds)';
		}
		else
		{
			return ' (' . number_format($time, 2) .' ms)';
		}
	}
	protected static function getTrackerRepo()
	{
		return \XF::repository('TorrentTracker:Tracker');
	}  
}