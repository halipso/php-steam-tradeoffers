<?php
error_reporting(E_ALL ^ E_NOTICE);
require_once 'Unirest.php';
require_once 'simple_html_dom.php';
/**
* Steam Trade PHP Class
*/
class SteamTrade
{
	private $webCookies = ''; 
	private $sessionId = '';
	private $apiKey = '';
	
	function __construct()
	{
		# code...
	}

	public function setup($sessionId, $webCookies) {
		$this->webCookies = $webCookies;
		$this->sessionId = $sessionId;
		$this->getApiKey();
	}

	public function getApiKey() {
		if($this->apiKey) {
			return;
		}
		$headers = array('Cookie' => $this->webCookies,'Timeout'=> Unirest\Request::timeout(5));
		try {
			$response = Unirest\Request::get('https://steamcommunity.com/dev/apikey',$headers);
		} catch (Exception $e) {
			echo 'Error: '.$e->getMessage(); #TODO: show url in error
			return;
		}
		
		if($response->code != 200) {
			die("Error getting apiKey. Code:".$response->code);
		}

		$parse = str_get_html($response->body);

		if($parse->find('#mainContents',0)->find('h2',0)->plaintext == 'Access Denied') {
			die('Error: Access Denied!');
		}

		if($parse->find('#bodyContents_ex',0)->find('h2',0)->plaintext == 'Your Steam Web API Key') {
			$key = explode(' ',$parse->find('#bodyContents_ex',0)->find('p',0))[1];
			$this->apiKey = $key;
			return;
		}

		$headers = array('Cookie' => $this->webCookies);
		$body = array('domain' => 'localhost', 'agreeToTerms' => 'agreed', 'sessionid' => $this->sessionId, 'submit' => 'Register');
		$response = Unirest\Request::post('https://steamcommunity.com/dev/registerkey', $headers, $body);
		$this->getApiKey();
	}

	public function loadMyInventory($options) {
		$query = array();

		if($options['language']) {
			$query['l'] = $options['language'];
		}

		if($options['tradableOnly']) {
			$query['trading'] = 1;
		}

		$uri = 'https://steamcommunity.com/my/inventory/json/'.$options['appId'].'/' .$options['contextId'].'/?'.http_build_query($query);
		return $this->_loadInventory(array(), $uri, array('json' => TRUE), $options['contextId'], null);
	}

	private function _loadInventory($inventory, $uri, $options, $contextid, $start) {
		$options['uri'] = $uri;

		if($start) {
			$options['uri'] = $options['uri'] + '&' + http_build_query(array('start'=>'start'));
		}

		$headers = array('Cookie' => $this->webCookies,'Timeout'=> Unirest\Request::timeout(5));

		try {
			$response = Unirest\Request::get($uri,$headers);
		} catch (Exception $e) {
			echo 'Error: '.$e->getMessage(); #TODO: show url in error
			return;
		}

		if($response->code != 200) {
			die("Error getting apiKey. Code:".$response->code);
		}

		$response = $response->body;

		if(!$response || !$response->rgInventory || !$response->rgDescriptions ) { #TODO: Check rgCurrency
			die('Invalid Response');
		}
		
		$inventory = array_merge($inventory,array_merge($this->mergeWithDescriptions($response->rgInventory, $response->rgDescriptions, $contextid),$this->mergeWithDescriptions($response->rgCurrency, $response->rgDescriptions, $contextid)));
		if($response->more) {
			return $this->_loadInventory($inventory, $uri, $options, $contextid, $response->more_start);
		} else {
			return $inventory;
		}
	}

	/*function mergeWithDescriptions(items, descriptions, contextid) {
	  return Object.keys(items).map(function(id) {
	    var item = items[id];
	    var description = descriptions[item.classid + '_' + (item.instanceid || '0')];
	    for (var key in description) {
	      item[key] = description[key];
	    }
	    // add contextid because Steam is retarded
	    item.contextid = contextid;
	    return item;
	  });
	}*/

	private function mergeWithDescriptions($items, $descriptions, $contextid) {
		$descriptions = (array) $descriptions;
		$n_items = array();
		foreach ($items as $key => $item) {
			$description = (array) $descriptions[$item->classid.'_'.($item->instanceid ? $item->instanceid : 0)];
			$item = (array) $item;
			foreach ($description as $k => $v) {
				$item[$k] = $description[$k];
			}
			// add contextid because Steam is retarded
			$item['contextid'] = $contextid;
			$n_items[] = $item;
		}
		return $n_items;
	}
}
?>