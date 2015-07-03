# php-steam-tradeoffers
Steam Trade Offers library for PHP (based on node.js library by Alex7Kom)

All the same functions as in [Alex7Kom library](https://github.com/Alex7Kom/node-steam-tradeoffers).


__Note__: By using this library you automatically agree to [Steam API Terms of Use](https://steamcommunity.com/dev/apiterms)

# Installation

```
require_once 'steam.class.php
```

# Usage
Instantiate a SteamTradeOffers object...

```php
$SteamTradeOffers = new SteamTrade();
```

...then setup session:

```php
$steam->setup('sessionID', 'cookies');
```

This setup will automatically register and retrieve Steam API key for you.
