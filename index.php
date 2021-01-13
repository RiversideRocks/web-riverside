<?php
/*
   +----------------------------------------------------------------------+
   | Copyright (c) 2020 Riverside Rocks authors                           |
   +----------------------------------------------------------------------+
   | This source file is subject to the Apache 2.0 Lisence.               |
   |                                                                      |
   | If you did not receive a copy of the license and are unable to       |
   | obtain it through the world-wide-web, please send a email to         |
   | support@riverside.rocks so we can mail you a copy immediately.       |
   +----------------------------------------------------------------------+
   | Authors: Trent "Riverside Rocks" Wiles <trent@riverside.rocks>       |
   +----------------------------------------------------------------------+
*/

//die("Offline for matinence, sorry. Contact trent@riverside.rocks for assistance.");

session_start();
header("X-Powered-By: Riverside Rocks");
header("X-Server: kestral (v2.2)");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protections: 1; mode=block");
header("X-Frame-Options: SAMEORIGIN");



require __DIR__ . '/vendor/autoload.php';


/*

Require all controllers

*/

require 'authapi.php';



require 'functions.php';
require 'security.php';

use RiversideRocks\services as Rocks;
use RiversideRocks\security as Secure;
use IPTools\Network;
use Smalot\Cups\Builder\Builder;
use Smalot\Cups\Manager\JobManager;
use Smalot\Cups\Manager\PrinterManager;
use Smalot\Cups\Model\Job;
use Smalot\Cups\Transport\Client;
use Smalot\Cups\Transport\ResponseParser;

$exploits = Secure::returnExploits();

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$envRequiredFields = [
    "MYSQL_SERVER", "MYSQL_USERNAME", "MYSQL_PASSWORD", "MYSQL_DATABASE", "YOUTUBE", "ABUSE_IP_DB", "UPLOAD"
];

foreach ($envRequiredFields as $field) {
    if (!isset($_ENV[$field])) {
        die("\$_ENV doesn't have a required field ${field}");
    }
}

if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) $_SERVER['REMOTE_ADDR'] = $_SERVER['HTTP_CF_CONNECTING_IP'];

$ip = $_SERVER['REMOTE_ADDR'];
$router = new \Bramus\Router\Router();
$pug = new Pug();

/*===============================================

EMERGENCY SHUTOFF SWITCH

Upon activation, this will disable global access
to the whole website.

If there is ever a crisis, this can be activitated
internally. While this is "visble" in the source
code, the way to activate the kill switch

================================================*/

if(isset($_ENV["w"]))
{
    die(Phug::displayFile('views/kill-switch-result.pug'));
}

/*===============================================

END EMERGENCY SHUTOFF SWITCH

================================================*/
     

$servername = $_ENV['MYSQL_SERVER'];
$username = $_ENV["MYSQL_USERNAME"];
$password = $_ENV["MYSQL_PASSWORD"];
$dbname = $_ENV["MYSQL_DATABASE"];

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT * FROM blocklist WHERE ip='${ip}'";
$result = $conn->query($sql);

$blocks = 0;
if (!empty($result) && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $blocks = $blocks + 1;
        $reason = $row["reason"];
    }
}

if($blocks !== 0)
{
    header("HTTP/1.1 403 Forbidden");
    echo "<html><head><title>403 Forbidden</title></head><body><center><h1>403 Forbidden</h1></center><hr><center>358 Engine</center></body></html>";
    die();
}

$epoch = time();

$stmt = $conn->prepare("INSERT INTO logs (epoch, country) VALUES (?, ?)");
$stmt->bind_param("is", $epoch1, $country1);

$epoch1 = $epoch;
$country1 = $_SERVER["HTTP_CF_IPCOUNTRY"];
$stmt->execute();
$stmt->close();

$times = 0;

$sql = "SELECT * FROM logs";
    $result = $conn->query($sql);
    $times = 0;
    if (!empty($result) && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $times = $times + 1;
        }
    }
    define("times", $times);

$sql = "SELECT * FROM msg";
    $result = $conn->query($sql);
    $times = 0;
    if (!empty($result) && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $times = $times + 1;
        }
    }

    define("mess", $times);


    $sql = "SELECT country, count(*) as SameValue from logs GROUP BY country ORDER BY SameValue DESC";
    $result = $conn->query($sql);
    $countries = array();
    if (!empty($result) && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $con = htmlspecialchars($row["country"]);
            $val = htmlspecialchars($row["SameValue"]);
            array_push($countries, $con, $val);
        }
    }



$router->get('/api/visits', function() {
    echo json_encode(times, true);
});


// Note that this endpoint will not work at the moment, this repo is private!
$router->get('/ip', function() {
   header("Location: https://raw.githubusercontent.com/RiversideRocks/BlockSec/main/ips.txt");
   die();
});

$router->get('/v1/research', function() {
    header("HTTP/1.1 405 Method Not Allowed");
    header("Content-type: application/json");
    die(json_encode(array("success" => "false", "message" => "This endpoint does not accept GET requests"), true));
});

$router->post('/v1/research', function() {
    header("Content-type: application/json");
    $cli_agent = htmlspecialchars($_POST["agent"]);
    $cli_locale = htmlspecialchars($_POST["locale"]);
    $cli_ref = htmlspecialchars($_POST["referrer"]);
    $cli_time = htmlspecialchars($_POST["time"]);

    $servername = $_ENV['MYSQL_SERVER'];
    $username = $_ENV["MYSQL_USERNAME"];
    $password = $_ENV["MYSQL_PASSWORD"];
    $dbname = $_ENV["MYSQL_DATABASE"];

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $stmt = $conn->prepare("INSERT INTO analytics (`country`, `ref`, `agent`, `epoch`) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $cli_locale, $cli_ref, $cli_agent, $cli_time);
    $stmt->execute();

    
    die(json_encode(array("success" => "true", "message" => "OK"), true));
});


$router->post('/v1/boost', function() {
    header("Content-type: application/json");
    $cli_agent = htmlspecialchars($_POST["agent"]);
    $cli_locale = htmlspecialchars($_POST["locale"]);
    $cli_ref = htmlspecialchars($_POST["referrer"]);
    $cli_time = htmlspecialchars($_POST["time"]);

    $servername = $_ENV['MYSQL_SERVER'];
    $username = $_ENV["MYSQL_USERNAME"];
    $password = $_ENV["MYSQL_PASSWORD"];
    $dbname = $_ENV["MYSQL_DATABASE"];

    if(!isset($_POST["base"]))
    {
        header("HTTP/1.1 400 Bad Request");
        die(json_encode(array("success" => "false", "message" => "Bad Request"), true));
    }

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    if(!$_SESSION["username"])
    {
        header("HTTP/1.1 401 Unauthorized");
        die(json_encode(array("success" => "false", "message" => "Not logged in"), true));
    }

    $r_username = htmlspecialchars($_SESSION["username"]);
    $r_time = 10;
    $r_epoch = time();


    $stmt = $conn->prepare("INSERT INTO read_time (`username`, `time`, `epoch`) VALUES (?, ?, ?)");
    $stmt->bind_param("sii", $r_username, $r_time, $r_epoch);
    $stmt->execute();

    
    die(json_encode(array("success" => "true", "message" => "OK"), true));
});

$router->get('/about/feed', function() {
    Phug::displayFile('views/ip.pug');
});

$router->post('/twitter', function() {
    $dms = json_decode($_POST["content"], true);
    foreach($dms as $dm)
    {
        echo $dm["content"];
    }
});



$router->get('/twitter/approve', function() {
    $client = new Client();
    $builder = new Builder();
    $responseParser = new ResponseParser();

    $printerManager = new PrinterManager($builder, $client, $responseParser);
    $printer = $printerManager->findByUri('ipp://192.168.1.11:631');

    $jobManager = new JobManager($builder, $client, $responseParser);

    $job = new Job();
    $job->setName('job create file');
    $job->setUsername('demo');
    $job->setCopies(1);
    $job->setPageRanges('1');
    $job->addFile('./helloworld.pdf');
    $job->addAttribute('media', 'A4');
    $job->addAttribute('fit-to-page', true);
    $result = $jobManager->send($printer, $job);

});

$router->get('/api/bycountry', function() {
    print_r($countries);
});

$router->get('/api/cidr', function() {
    header("Content-type: application/json");
    $hosts = Network::parse($_GET["ip"])->hosts;
    $sendl = array();
    foreach($hosts as $ipas) {
        array_push($sendl, (string)$ipas);
    }
    $echo = json_encode($sendl, true);
    echo $echo;
});


/*===========================
/\/\/\/\/\/\/\/\/\/\/\/\/\/\
END EXPERIMENTAL API ENDPOINTS
/\/\/\/\/\/\/\/\/\/\/\/\/\/\
===========================*/


$router->get('/', function() {
    header('HTTP/1.1 200 OK');

    $pug = new Pug();
    /*
    $servername = $_ENV['MYSQL_SERVER'];
    $username = $_ENV["MYSQL_USERNAME"];
    $password = $_ENV["MYSQL_PASSWORD"];
    $dbname = $_ENV["MYSQL_DATABASE"];
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $channel_id = "UCoHNPdbSrE2c_g95JgGiBkw";
    $api_key = $_ENV["YOUTUBE"];
    $api_response = file_get_contents('https://www.googleapis.com/youtube/v3/channels?part=statistics&id='.$channel_id.'&fields=items/statistics/subscriberCount&key='.$api_key);
    $api_response_decoded = json_decode($api_response, true);
    $subs = $api_response_decoded['items'][0]['statistics']['subscriberCount'];
    $api_response2 = file_get_contents('https://www.googleapis.com/youtube/v3/channels?part=statistics&id='.$channel_id.'&fields=items/statistics/viewCount&key='.$api_key);
    $api_response_decoded2 = json_decode($api_response2, true);
    $views = $api_response_decoded2['items'][0]['statistics']['viewCount'];
    */
    if($_SESSION["username"] !== "")
    {
        $authuser = htmlspecialchars($_SESSION["username"]);
        $profile = "https://riverside.rocks/users/" . $authuser;
    }
    /*
    $latest = Rocks::githubEvent("RiversideRocks", 0);
    $github = "Latest from GitHub: " . $latest["event"] . " (Repo " . $latest["repo"] . ")";
    */
    $output = $pug->render('views/index.pug', array(
        'user' => $authuser,
        'profile' => $profile,
    ));
    echo $output;
});

$router->get('/globe', function() {
    Phug::displayFile('views/globe.pug');
});

$router->get('/about', function() {
    header('HTTP/1.1 200 OK');

    $pug = new Pug();
    $ipdb = file_get_contents("https://riverside.rocks/crawl.php");
    $output = $pug->render('views/about.pug', array(
        'ipdb' => $ipdb,
    ));
    Phug::displayFile('views/about.pug');
});

$router->get('/about/legal', function() {
    header('HTTP/1.1 200 OK');

    Phug::displayFile('views/legal.pug');
});

$router->get('/about/hacking', function() {
    header('HTTP/1.1 200 OK');

    Phug::displayFile('views/hacking.pug');
});

$stat = Rocks::statcord("764485265775263784", "logan");

$router->get('/about/stats', function() {
    header('HTTP/1.1 200 OK');

    $pug = new Pug();
    $stat = Rocks::statcord("764485265775263784", "logan");
    $output = $pug->renderFile('views/count.pug', array(
        'bot_users' => $stat[0],
        'bot_servers' => $stat[1],
        'bot_commands' => $stat[2],
        'requests' => times
    ));
    echo $output;
});

$router->get('/projects', function() {
    header('HTTP/1.1 200 OK');

    Phug::displayFile('views/projects.pug');
});


$router->get('/contact', function() {
   header("Location: /about/contact");
});

$router->get('/about/contact', function() {
    header('HTTP/1.1 200 OK');

    Phug::displayFile('views/contact.pug'); // might make this dynamic later, might not
});

$router->post('/about/contact', function() {
    header('HTTP/1.1 200 OK');

   $name = $_POST["name"];
   $email = $_POST["email"];
   $type = $_POST["type"];
   $comment = $_POST["description"];
   // No need to worry about XSS or SQL injections, thats now Discord's problem hehe
   $final = "From ${name} <${email}> regarding ${type}: **${comment}**";
   $data = array(
    'secret' => $_ENV["CAPTCHA"],
    'response' => $_POST['h-captcha-response']
    );
    $verify = curl_init();
    curl_setopt($verify, CURLOPT_URL, "https://hcaptcha.com/siteverify");
    curl_setopt($verify, CURLOPT_POST, true);
    curl_setopt($verify, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($verify, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($verify);
    $responseData = json_decode($response, true);
    if($responseData["success"]) {
        Rocks::newDiscordContact($final);
        Phug::displayFile('views/thanks.pug');
    } 
    else {
        echo "You did not pass the captcha. Please try again.";
        Rocks::newDiscordContactSpam($final);
    }
   
});
$router->get('/videos', function() {
    header('HTTP/1.1 200 OK');

    $pug = new Pug();
    die(header("Location: https://www.youtube.com/RiversideRocks"));

    // This is all ignored for now...
    $ids = array("1", "2", "3");
    $output = $pug->render('views/watch.pug', array(
        'videos' => $ids
    ));
    echo $output;
});

$router->get('/index.php', function() {
    header("Location: https://www.youtube.com/watch?v=E2Q52cVx7Bo");
    die();
});

$router->get('/discord', function() {
    header("Location: https://discord.gg/Pa7S4Hm");
});

/*================================

     UPLOAD CONTROLLER CODE

===============================*/

$router->get('/admin/upload', function() {
    header('HTTP/1.1 200 OK');

    Phug::displayFile('views/upload.pug');
});

/*
$router->get('/v1/analytics', function() {
    file_get_contents("https://riverside.rocks/analyics.php");
});

*/


$router->post('/admin/upload', function() {
    header('HTTP/1.1 200 OK');

    $pug = new Pug();
    if($_POST["key"] !== $_ENV["UPLOAD"]){
        $output = $pug->renderFile('views/upload-fail.pug', array(
            'errors' => '400: Bad Request. You are missing a valid upload key.'
        ));
        die($output);
    }
    if($_POST["one"] == "public"){
        $storage = new \Upload\Storage\FileSystem('a');
        $dir = 'a';
    }else{
        $storage = new \Upload\Storage\FileSystem('assets/serve/production/app');
        $dir = 'assets/serve/production/app';
    }
    $file = new \Upload\File('foo', $storage);

    $new_filename = uniqid();
    $file->setName($new_filename);

$data = array(
    'name'       => $file->getNameWithExtension(),
    'extension'  => $file->getExtension(),
    'mime'       => $file->getMimetype(),
    'size'       => $file->getSize(),
    'md5'        => $file->getMd5(),
    'dimensions' => $file->getDimensions()
);

// Try to upload file
try {
    // Success!
    $file->upload();
    $path = "https://riverside.rocks/${dir}/" . $data["name"];
    

    $servername = $_ENV['MYSQL_SERVER'];
    $username = $_ENV["MYSQL_USERNAME"];
    $password = $_ENV["MYSQL_PASSWORD"];
    $dbname = $_ENV["MYSQL_DATABASE"];

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    $epoch = time();

    $stmt = $conn->prepare("INSERT INTO uploads (`url`, epoch) VALUES (?, ?)");
    $stmt->bind_param("si", $path0, $epoch0);

    $path0 = $path;
    $epoch0 = $epoch;
    $stmt->execute();
    $stmt->close();
    
    if($_GET["api"] == "true")
    {
        echo $path;
        die();
    }
    $output = $pug->renderFile('views/uploaded.pug', array(
        'url' => $path
    ));
    echo $output;
} catch (\Exception $e) {
    // Fail!
    $errors = $file->getErrors();
    if($_GET["api"] == "true")
    {
        echo $errors;
        die();
    }
    $output = $pug->renderFile('views/upload-fail.pug', array(
        'errors' => $errors
    ));
    echo $output;
}
});

$router->get('/community', function() {
    header('HTTP/1.1 200 OK');

    Phug::displayFile('views/community-temp.pug');
});


$router->get('/admin', function() {
    header("Location: /admin/upload/");
    die();
});

$router->get('/legal', function() {
    header("Location: /about/legal/");
    die();
});

$router->get('/start', function() {
    header("Location: /app/");
    die();
});

$router->get('/why', function() {
    header("Location: /about/");
    die();
});

$router->get('/wp-login.php', function() {
    header('HTTP/1.1 200 OK');

   $pug = new Pug();
            $ip = $_SERVER['REMOTE_ADDR'];
            $to_discord = "${ip} - ${mes}";
            Rocks::newDiscord($to_discord, "Hacker Feed");
            $client = new GuzzleHttp\Client([
                'base_uri' => 'https://api.abuseipdb.com/api/v2/'
              ]);
              
              $response = $client->request('POST', 'report', [
                  'query' => [
                      'ip' => "${ip}",
                      'categories' => '15',
                      'comment' => "Unauthorized connection attempt detected from IP address " . $ip . " to port 80"
                  ],
                  'headers' => [
                      'Accept' => 'application/json',
                      'Key' => $_ENV["ABUSE_IP_DB"]
                ],
              ]);
              
              $output = $response->getBody();
              // Store response as a PHP object.
              $ipDetails = json_decode($output, true);
   $output = $pug->renderFile('views/wp-login.pug', array());
    echo $output;
});

$router->post('/wp-login.php', function() {
    header('HTTP/1.1 200 OK');
   $pass = $_POST["pwd"];
   switch(strpos($pass, "@everyone"))
   {
       case true:
        Rocks::newDiscord("Would you look at that, someone tried to ping the whole server.", "Idiot");
        die();
   }
   switch(strpos($pass, "@here"))
   {
       case true:
        Rocks::newDiscord("Would you look at that, someone tried to ping the whole server.", "Idiot");
        die();
   }
   $log_m = 
   "
   Attempted to hack into wordpress admin:
   
   **Password:** ${pass}
   ";
   Rocks::newDiscord($log_m, "Wordpress Hacker");
   header("Location: /wp-login.php");
   die();
});

$router->get('/wp-admin/', function() {
   header("Location: /wp-login.php");
   die();
});
$router->get('/account/dashboard', function() {
    header('HTTP/1.1 200 OK');
    $pug = new Pug();
    if(!isset($_SESSION["username"]))
    {
       die(header("Location: /account/login/"));
    }
    $output = $pug->renderFile('views/dashboard.pug', array(
        'username' => $_SESSION["username"],
        'icon' => "https://avatars0.githubusercontent.com/u/" . $_SESSION["id"],
        'url' => "/users" . "/" . $_SESSION["username"]
    ));
    echo $output;
    // Note to self, work on this!
    // As of 11/13 this endpoint is pretty bare it could use quite a bit of work (~riversiderocks)
});


$router->set404(function() {
    header('HTTP/1.1 404 Not Found');
    $hacks = Secure::returnExploits();
    
    $url = $_SERVER["REQUEST_URI"];
    
    $user_agent_blacklist = array(
        "Go-http-client/1.1",
        "Mozilla/5.0 zgrab/0.x",
        "python-requests/2.24.0"
    );
    
    $ua = $_SERVER['HTTP_USER_AGENT'];
    
    if(isset($hacks[$url]) || isset($baduseragent[$ua])){
        $mes = "AUTOMATED REPORT: " . $hacks[$url];
    }
    
    if(isset($baduseragent[$ua])){
        $mes = "AUTOMATED REPORT: Port Scanning: " . $url;
    }
        
    if(isset($mes)){
        $ip = $_SERVER['REMOTE_ADDR'];
        $to_discord = "${ip} - ${mes}";
        Rocks::newDiscord($to_discord, "Hacker Feed");
            $client = new GuzzleHttp\Client([
                'base_uri' => 'https://api.abuseipdb.com/api/v2/'
              ]);
              
              $response = $client->request('POST', 'report', [
                  'query' => [
                      'ip' => "${ip}",
                      'categories' => '15',
                      'comment' => "Unauthorized connection attempt detected from IP address " . $ip . " to port 80"
                  ],
                  'headers' => [
                      'Accept' => 'application/json',
                      'Key' => $_ENV["ABUSE_IP_DB"]
                ],
              ]);
        }
          //$output = $response->getBody();
          // Store response as a PHP object.
          //$ipDetails = json_decode($output, true);
          $servername = $_ENV['MYSQL_SERVER'];
          $username = $_ENV["MYSQL_USERNAME"];
          $password = $_ENV["MYSQL_PASSWORD"];
          $dbname = $_ENV["MYSQL_DATABASE"];

          $conn = new mysqli($servername, $username, $password, $dbname);
          if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
          }
          //$sql = "INSERT INTO `blocklist` (`ip`, `reason`) VALUES ('${ip}','Hacking attempt (HTTP)')";
          //$result = $conn->query($sql);
        //$data['http'] = $ip;
       //$pusher = new Pusher\Pusher(
        //$_ENV["PUSHER_APP_KEY"],
        //$_ENV["PUSHER_APP_SECRET"],
        //$_ENV["PUSHER_APP_ID"],
        //$options
    //);
            //$pusher->trigger('abuseipdb', 'http', $data);
        
   
    // If a hacking attempt is detected, we show the 403 page
    if(in_array($_SERVER["REQUEST_URI"], $hacks))
    {
        Phug::displayFile('views/403.pug');
    }
    else
    {
        Phug::displayFile('views/404.pug');
    }
    $list = Secure::userAgents();
    if(in_array($ua, $list))
    {
        $mes = $list[$ua];
        if(isset($mes)){
            $ip = $_SERVER['REMOTE_ADDR'];
            $to_discord = "${ip} - ${mes}";
            Rocks::newDiscord($to_discord, "Hacker Feed");
            $client = new GuzzleHttp\Client([
                'base_uri' => 'https://api.abuseipdb.com/api/v2/'
              ]);
              
              $response = $client->request('POST', 'report', [
                  'query' => [
                      'ip' => "${ip}",
                      'categories' => '15',
                      'comment' => "${mes}"
                  ],
                  'headers' => [
                      'Accept' => 'application/json',
                      'Key' => $_ENV["ABUSE_IP_DB"]
                ],
              ]);
              
              $output = $response->getBody();
              // Store response as a PHP object.
              $ipDetails = json_decode($output, true);
            }
    }
   header("HTTP/1.1 404 Not Found");
});



$router->run();
