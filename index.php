<?php
error_reporting(E_ALL);
require_once('classes/steam.class.php');
$steam = new SteamTrade();
$steam->setup(
	'dcbf1f030a7517b532dd2a20',
	'__utma=268881843.1968640959.1431894014.1435827871.1435834368.187;__utmb=268881843.0.10.1435834368;__utmc=268881843;__utmz=268881843.1435764757.179.84.utmcsr=google|utmccn=(organic)|utmcmd=organic|utmctr=(not%20provided);mediumappid=753;mediumname=368020-Mysterious Card 3 (Foil);recentlyVisitedAppHubs=319630%2C261570%2C252490%2C730;sessionid=dcbf1f030a7517b532dd2a20;steamCountry=RU%7Cb74c0a38caa0109ecfed3984c3f00a4b;steamLogin=76561198043696486%7C%7C3253143FA887C5B862476A11AC02134E759F8AD9;steamLoginSecure=76561198043696486%7C%7C97F8F8DC7F20EB48D95ADCDC210025CBC92831B6;steamMachineAuth76561198043696486=6C090F7DF6FE5728FF2CF360EF83AE623D3C3767;steamMachineAuth76561198091831085=6F4D0B2EAED38A7B776EE85BFA3AA1DB38211C41;strInventoryLastContext=730_2;timezoneOffset=10800,0;webTradeEligibility=%7B%22allowed%22%3A1%2C%22allowed_at_time%22%3A0%2C%22steamguard_required_days%22%3A15%2C%22sales_this_year%22%3A296%2C%22max_sales_per_year%22%3A-1%2C%22forms_requested%22%3A0%2C%22new_device_cooldown_days%22%3A7%7D;'
	);
//$my = $steam->loadMyInventory(array('appId'=>730,'contextId'=>2));

$partner = $steam->loadPartnerInventory(array('partnerSteamId'=>76561198053328708,'appId'=>730,'contextId'=>2));
print_r($partner);
?>