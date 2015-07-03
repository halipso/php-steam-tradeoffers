<?php
require_once('classes/steam.class.php');

$steam = new SteamTrade();
$steam->setup(
	'sessionID',
	'cookies'
	);
?>