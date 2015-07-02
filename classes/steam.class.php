<?php
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
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

	private function _loadInventory($inventory, $uri, $options, $contextid, $start = null) {
		$options['uri'] = $uri;

		if($start) {
			$options['uri'] = $options['uri'] + '&' + http_build_query(array('start'=>'start'));
		}

		$headers = array('Cookie' => $this->webCookies,'Timeout'=> Unirest\Request::timeout(5));
		if($options['headers']) {
			foreach ($options['headers'] as $key => $value) {
				$headers[$key] = $value;
			}
		}

		try {
			$response = Unirest\Request::get($uri,$headers);
		} catch (Exception $e) {
			echo 'Error: '.$e->getMessage(); #TODO: show url in error
			return;
		}

		if($response->code != 200) {
			die("Error loading inventory. Code:".$response->code);
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

	private function toSteamID($id) {
	    if (preg_match('/^STEAM_/', $id)) {
	        $split = explode(':', $id);
	        return $split[2] * 2 + $split[1];
	    } elseif (preg_match('/^765/', $id) && strlen($id) > 15) {
	        return bcsub($id, '76561197960265728');
	    } else {
	        return $id; // We have no idea what this is, so just return it.
	    }
	}

	private function toAccountID($id) {
	    if (preg_match('/^STEAM_/', $id)) {
	        $parts = explode(':', $id);
	        return bcadd(bcadd(bcmul($parts[2], '2'), '76561197960265728'), $parts[1]);
	    } elseif (is_numeric($id) && strlen($id) < 16) {
	        return bcadd($id, '76561197960265728');
	    } else {
	        return $id; // We have no idea what this is, so just return it.
	    }
	}

	public function loadPartnerInventory($options) {
		
		$form = array(
		    'sessionid' => $this->sessionId,
		    'partner' => $options['partnerSteamId'],
		    'appid' => $options['appId'],
		    'contextid' => $options['contextId']
	 	);

	 	if($options['language']) {
			$form['l'] = $options['language'];
		}

		$offer = 'new';
		if($options['tradeOfferId']) {
			$offer = $options->tradeOfferId;
		}

		$uri = 'https://steamcommunity.com/tradeoffer/'.$offer.'/partnerinventory/?'.http_build_query($form);	

		return $this->_loadInventory(array(), $uri, array(
		    'json' => TRUE,
		    'headers' => array(
		      'referer' => 'https://steamcommunity.com/tradeoffer/'.$offer.'/?partner='.$this->toSteamID($options['partnerSteamId'])
		    ) , $options['contextId'], null));	
	}

	public function makeOffer($options) {
		$tradeoffer = array(
		    'newversion' => TRUE,
		    'version' => 2,
		    'me' => array('assets' => $options['itemsFromMe'], 'currency' => array(), 'ready' => FALSE ),
		    'them' => array('assets' => $options['itemsFromThem'], 'currency': array(), 'ready' => FALSE )
	  	);

	  	$formFields = array(
		    'serverid' => 1,
		    'sessionid' => $this->sessionID,
		    'partner' => $options['partnerSteamId'] ? $options['partnerSteamId'] : $this->toSteamID($options['partnerAccountId']),
		    'tradeoffermessage' => $options['message'] ? $options['message'] : '',
		    'json_tradeoffer' => http_build_query($tradeoffer);
	  	);	

	  	$query = array(
		    'partner' => $options['partnerAccountId'] ? $options['partnerAccountId'] : $this->toAccountID($options['partnerSteamId']);
		);

	  	if($options['accessToken']) {
	  		$formFields['trade_offer_create_params'] = http_build_query(array('trade_offer_access_token'=>$options['accessToken']));
	  		$query['token'] = $options['accessToken'];
	  	}

	  	$referer = '';
	  	if($options['counteredTradeOffer']) {
	  		$formFields['tradeofferid_countered'] = $options['counteredTradeOffer'];
	  		$referer = 'https://steamcommunity.com/tradeoffer/'.$options['counteredTradeOffer'].'/';
	  	} else {
	  		$referer = 'https://steamcommunity.com/tradeoffer/new/?'.http_build_query($query);
	  	}

	  	$headers = array('referer'=>$referer);
	  	$response = Unirest\Request::post('https://steamcommunity.com/tradeoffer/new/send', $headers, $formFields);
	  	print_r($response);

	}

}
?>