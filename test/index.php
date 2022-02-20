<?php

require '../src/PHP_HttpClient.php';
use ACME\PHP_HttpClient as PHP_HttpClient;

$app = new PHP_HttpClient('https://corednacom.corewebdna.com');
$app->options('assessment-endpoint.php'); // get token
$token = '';

if($app->fetchData() && $app->fetchStatusCode() == 200 && $app->fetchErr() == ''){
    $token = $app->fetchData();
}

if($token){  
    $data = ["name"=>"Aman Goel","email"=>"aman.goel008@gmail.com","url"=>"https://github.com/amangoel08/http-php"];
    //post request
    $app->post('assessment-endpoint.php',$data,['Authorization'=>'Bearer '.$token] );
    echo $app->fetchStatusCode();
}