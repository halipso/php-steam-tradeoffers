<?php
require_once 'Unirest.php';
require_once 'simple_html_dom.php';
/**
* Steam Trade PHP Class
* Based on node.js version by Alex7Kom https://github.com/Alex7Kom/node-steam-tradeoffers
*
*
*
* https://github.com/halipso/php-steam-tradeoffers
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
			$key = explode(' ',$parse->find('#bodyContents_ex',0)->find('p',0)->plaintext)[1];
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

	private function toAccountID($id) {
	    if (preg_match('/^STEAM_/', $id)) {
	        $split = explode(':', $id);
	        return $split[2] * 2 + $split[1];
	    } elseif (preg_match('/^765/', $id) && strlen($id) > 15) {
	        return bcsub($id, '76561197960265728');
	    } else {
	        return $id;
	    }
	}

	private function toSteamID($id) {
	    if (preg_match('/^STEAM_/', $id)) {
	        $parts = explode(':', $id);
	        return bcadd(bcadd(bcmul($parts[2], '2'), '76561197960265728'), $parts[1]);
	    } elseif (is_numeric($id) && strlen($id) < 16) {
	        return bcadd($id, '76561197960265728');
	    } else {
	        return $id;
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
		      'referer' => 'https://steamcommunity.com/tradeoffer/'.$offer.'/?partner='.$this->toAccountID($options['partnerSteamId'])
		    ) , $options['contextId'], null));	
	}

	public function makeOffer($options) {

		$tradeoffer = array(
		    'newversion' => TRUE,
		    'version' => 2,
		    'me' => array('assets' => $options['itemsFromMe'], 'currency' => array(), 'ready' => FALSE ),
		    'them' => array('assets' => $options['itemsFromThem'], 'currency' => array(), 'ready' => FALSE )
	  	);

	  	$formFields = array(
		    'serverid' => 1,
		    'sessionid' => $this->sessionId,
		    'partner' => $options['partnerSteamId'] ? $options['partnerSteamId'] : $this->toSteamID($options['partnerAccountId']),
		    'tradeoffermessage' => $options['message'] ? $options['message'] : '',
		    'json_tradeoffer' => json_encode($tradeoffer)
	  	);	

	  	$query = array(
		    'partner' => $options['partnerAccountId'] ? $options['partnerAccountId'] : $this->toAccountID($options['partnerSteamId'])
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

	  	$headers = array('Cookie' => $this->webCookies,'Timeout'=> Unirest\Request::timeout(5));
	  	$headers['referer'] = $referer;
	  	$response = Unirest\Request::post('https://steamcommunity.com/tradeoffer/new/send', $headers, $formFields);
	  	
	  	if($response->code != 200) {
	  		die('Error making offer! Server response code: '.$response->code);
	  	}

	  	$body = $response->body;

	  	if($body && $body->strError) {
	  		die('Error making offer: '.$body->strError);
	  	}

	  	return $body;
	}

	public function getOffers($options) {
		$offers = $this->doAPICall(
			array(
				'method' => 'GetTradeOffers/v1',
				'params' => $options
			)
		);

		$offers = json_decode(mb_convert_encoding($offers, 'UTF-8', 'UTF-8'),1);

		if($offers['response']['trade_offers_received']) {
			foreach ($offers['response']['trade_offers_received'] as $key => $value) {
				$offers['response']['trade_offers_received'][$key]['steamid_other'] = $this->toSteamID($value['accountid_other']);
			}
		}

		if($offers['response']['trade_offers_sent']) {
			foreach ($offers['response']['trade_offers_sent'] as $key => $value) {
				$offers['response']['trade_offers_sent'][$key]['steamid_other'] = $this->toSteamID($value['accountid_other']);
			}
		}

		return $offers;
	}

	public function getOffer($options) {
		$offer = $this->doAPICall(
			array(
				'method' => 'GetTradeOffer/v1',
				'params' => $options
			)
		);

		$offer = json_decode(mb_convert_encoding($offer, 'UTF-8', 'UTF-8'),1);

		if ($offer['response']['offer']) {
	    	$offer['response']['offer']['steamid_other'] = $this->toSteamId($offer['response']['offer']['accountid_other']);
	    }

		return $offer;
	} 

	private function doAPICall($options) {
		$uri = 'https://api.steampowered.com/IEconService/'.$options['method'].'/?key='.$this->apiKey.($options['post'] ? '' : ('&'.http_build_query($options['params'])));

		$body = null;
		if($options['post']) {
			$body = $options['params'];
		}

		$response = ($options['post'] ? Unirest\Request::post($uri, null, $body) : Unirest\Request::get($uri));
		
		if($response->code != 200) {
			die('Error doing API call. Server response code: '.$response->code);
		}

		if(!$response->raw_body) {
			die('Error doing API call. Invalid response.');
		}

		return $response->raw_body;
	}

	public function declineOffer($options) {
		return $this->doAPICall(
			array(
				'method' => 'DeclineTradeOffer/v1',
				'params' => array('tradeofferid' => $options['tradeOfferId']),
				'post' => 1
			)
		);
	}

	public function cancelOffer($options) {
		return $this->doAPICall(
			array(
				'method' => 'CancelTradeOffer/v1',
				'params' => array('tradeofferid' => $options['tradeOfferId']),
				'post' => 1
			)
		);
	}

	public function acceptOffer($options) {

	  	if(!$options['tradeOfferId']) {
	  		die('No options');
	  	}

	  	$form = array(
	  		'sessionid' => $this->sessionId,
	  		'serverid' => 1,
	  		'tradeofferid' => $options['tradeOfferId']
	  		);

	  	$referer = 'https://steamcommunity.com/tradeoffer/'.$options['tradeOfferId'].'/';

	  	$headers = array('Cookie' => $this->webCookies,'Timeout'=> Unirest\Request::timeout(5));
	  	$headers['referer'] = $referer;
	  	$response = Unirest\Request::post('https://steamcommunity.com/tradeoffer/'.$options['tradeOfferId'].'/accept', $headers, $form);

	  	if($response->code != 200) {
	  		die('Error accepting offer. Server response code: '.$response->code);
	  	}

	  	$body = $response->body;

	  	if($body && $body->strError) {
	  		die('Error accepting offer: '.$body->strError);
	  	}

	  	return $body;
	}

	public function getOfferToken() {

		$headers = array('Cookie' => $this->webCookies,'Timeout'=> Unirest\Request::timeout(5));
		$response = Unirest\Request::get('https://steamcommunity.com/my/tradeoffers/privacy', $headers);

		if($response->code != 200) {
			die('Error retrieving offer token. Server response code: '.$response->code);
		}

		$body = str_get_html($response->body);

		if(!$body) {
			die('Error retrieving offer token. Invalid response.');
		}

    	$offerUrl = $body->find('#trade_offer_access_url',0)->value;
    	return explode('=',$offerUrl)[2];
	}

	public function getItems($options) {
		$headers = array('Cookie' => $this->webCookies,'Timeout'=> Unirest\Request::timeout(5));
		$response = Unirest\Request::get('https://steamcommunity.com/trade/'.$options['tradeId'].'/receipt/', $headers);

		if($response->code != 200) {
			die('Error get items. Server response code: '.$response->code);
		}

		$body = $response->body;

		preg_match('/(var oItem;[\s\S]*)<\/script>/', $body, $matches);

		if(!$matches) {
			die('Error get items: no session');
		}

		$temp = str_replace(array("\r", "\n"), "", $matches[1]);

		$items = array();

		preg_match_all('/oItem = {(.*?)};/', $temp, $matches);
		foreach ($matches[0] as $key => $value) {
			$value = rtrim(str_replace('oItem = ', '', $value),';');
			$items[] = json_decode($value,1);
		}

		return $items;
	}

}
?>