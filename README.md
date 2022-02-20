# http-php

This a PHP HTTP Client Class which perform requests like OPTIONS, POST. 

# usage

$app = new PHP_HttpClient('https://www.mydomain.com'); <br>
$app->post('/p/2', [array of post data], [array of headers]); <br><br>

$app->fetchData() // fetch content from last request  <br>
$app->fetchStatusCode() // fetch http status code from last request <br>
$app->fetchErr() // fetch error from last request <br>