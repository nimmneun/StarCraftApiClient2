# StarCraftApiClient2

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nimmneun/StarCraftApiClient2/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/nimmneun/StarCraftApiClient2/?branch=master) [![Build Status](https://scrutinizer-ci.com/g/nimmneun/StarCraftApiClient2/badges/build.png?b=master)](https://scrutinizer-ci.com/g/nimmneun/StarCraftApiClient2/build-status/master)

Just a "single file SC2 API client" to perform multiple request

```php
// Initialize client with ApiKey ... (optionally) set max concurrency and locale.
$client = new \StarCraftApiClient2\Client('...your apikey...', 25, 'en_GB');

// Add maaaany (100s ... 1000s...) requests ...
$client->addAllProfileRequests(123321, 'Bobaaa', 'eu');
$client->addAllProfileRequests(321123, 'CommanderZerg', 'us');
...

// Start pulling data from battle.net
$client->run();

// Get header, body, curl_info & key of a single response
// ([header,body,[info],key])
$response = $client->get('profile/123321/profile');
var_dump($response['body']);

// Iterate over each response and do ... whatever
foreach ($client->each() as $key => $response) {
    $header[] = json_decode($response['header']);
    $body = json_decode($response['body']);
}
```
