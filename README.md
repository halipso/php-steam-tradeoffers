# php-steam-tradeoffers
Steam Trade Offers library for PHP (based on node.js library by Alex7Kom)

All the same functions as in [Alex7Kom library](https://github.com/Alex7Kom/node-steam-tradeoffers)


__Note__: By using this library you automatically agree to [Steam API Terms of Use](https://steamcommunity.com/dev/apiterms)

# Installation

```
require_once 'classes/steam.class.php'
```

# Usage
Instantiate a steam object...

```php
$steam = new SteamTrade();
```

...then setup session:

```php
$steam->setup('sessionID', 'cookies');
```

This setup will automatically register and retrieve Steam API key for you.

# Methods

The first param for all methods (except setup) is an associative array of options.

## setup(sessionId, cookies)

As noted above, this method is used to setup a web session. It also tries to retrieve Web API key.

Options:

* `sessionID` is a valid web session ID.
* `webCookie` is an array of cookies.

If failed to retrieve Web API key due to [limited account](https://support.steampowered.com/kb_article.php?ref=3330-IAGK-7663), `setup` will throw the error.

## loadMyInventory(options)

Loads your inventory for the given app and context. For example, use 440 and 2 for TF2 and 570 and 2 for Dota 2.

Options:

* `appId` is the Steam AppID
* `contextId` is the inventory context Id
* `language` (optional) is the language for item descriptions
* `tradableOnly` (optional) is a boolean flag that defaults to `true` to return tradable items only

## loadPartnerInventory(options)

Loads your partner inventory for the given app and context.

Options:

* `partnerSteamId` is the SteamID of the trade partner
* `appId` is the Steam AppID
* `contextId` is the inventory context Id
* `tradeOfferId` (optional) is needed to load private inventory of the trade partner for received trade offer
* `language` (optional) is the language for item descriptions

## makeOffer(options)

Makes a trade offer to the partner.

Options:

* `partnerAccountId` or `partnerSteamId`, you need only one of those.
* `accessToken` (optional) is a token from the public Trade URL of the partner.
* `itemsFromMe` are the items you will lose in the trade.
* `itemsFromThem` are the items you will receive in the trade.
* `counteredTradeOffer` (optional) is the ID to a trade offer you are countering.
* `message` (optional) is a message to include in the offer.

`itemsFromMe` and `itemsFromThem` both are arrays of item objects that look like this:

```php
array(
    "appid" => 440,
    "contextid" => 2,
    "amount" => 1,
    "assetid" => "1627590398"
)
```

If success it will return an object with `tradeofferid` of the newly created trade offer.

## getOffers(options)
## getOffer(options)

The first method loads a list of trade offers, and the second loads just a single offer.

Options:

* See [Steam Web API/IEconService](https://developer.valvesoftware.com/wiki/Steam_Web_API/IEconService).

 In return you will get an object that Steam Web API returns. The only thing to note is that the wrapper adds a property `steamid_other` with the SteamID of the trade partner to each `CEcon_TradeOffer` object in received trades.

## declineOffer(options)
## acceptOffer(options)
## cancelOffer(options)

`declineOffer` or `acceptOffer` that was sent to you. `cancelOffer` that you sent.

Options:

* `tradeOfferId` is a trade offer Id

In return you will get an object with response from Steam, but don't expect anything meaningful in it.

## getOfferToken()

In return you will get the offer token of the bot, extracted from its trade offer URL.

## getItems(options)

Options:

* `tradeId` is the ID of the completed trade you want to get items for, available as a `tradeid` property on offers from `getOffers` or `getOffer`

In return you will get an array of items acquired in a completed trade.
