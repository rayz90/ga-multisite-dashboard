<?php
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$app = new Silex\Application();

/* START CONFIGURATION */
$app['debug'] = true;

$app['google_api_app_name'] = 'xxxx';
$app['google_api_username'] = 'xxxx@developer.gserviceaccount.com';
$app['google_api_key_path'] = __DIR__.'/../config/key.p12';
$app['google_api_key_secret'] = 'notasecret';

$app['websites_path'] = '../config/websites.json';
/* END CONFIGURATION */

/*
 * Register Providers
 */
$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../log/development.log',
));

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app->register(new Silex\Provider\SessionServiceProvider());

/**
 * Creates the Google Analytics service
 */
$app['google_api_service'] = $app->share(function ($app) {
    try {
        $client = new Google_Client();
        $client->setApplicationName($app['google_api_app_name']);
        $service = new Google_Service_Analytics($client);

        if (null !== $gapi = $app['session']->get('gapi')) {
            $client->setAccessToken($gapi['token']);
        }

        $key = file_get_contents($app['google_api_key_path']);
        $cred = new Google_Auth_AssertionCredentials(
            $app['google_api_username'],
            array(Google_Service_Analytics::ANALYTICS_READONLY),
            $key,
            $app['google_api_key_secret']
        );

        $client->setAssertionCredentials($cred);
        if ($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion($cred);
        }

        $app['session']->set('gapi', array('token' => $client->getAccessToken()));
    } catch (Google_Exception $e) {
        $app['monolog']->addError($e->getMessage());
        die();
    }

    return $service;
});

/**
 * Obtains the profiles for the tracking codes
 */
$app['websites'] = $app->share(function ($app) {
    /* @var Google_Service_Analytics $service */
    $service = $app['google_api_service'];
    $websites = array();

    $json = file_get_contents($app['websites_path']);
    $tracking_codes = json_decode($json, true);
    $tracking_codes = $tracking_codes['websites'];

    try {
        $summaries = $service->management_accountSummaries->listManagementAccountSummaries();
        foreach ($summaries->getItems() as $account) {
            $web_properties = $service->management_webproperties->listManagementWebproperties($account->getId());
            foreach ($tracking_codes as $code) {
                foreach ($web_properties as $property) {
                    if ($property->getId() !== $code['tracking_id']) {
                        continue;
                    }
                    $websites[$code['name']] = 'ga:'.$property->getDefaultProfileId();
                    break;
                }
            }
        }
    } catch (Google_Exception $e) {
        $app['monolog']->addError($e->getMessage());
    }

    return $websites;
});

/**
 * Returns a CSV with the vistors for each website in the last 24 hours
 * Format: website;dateHour;users
 */
$app->get('/api/getuserslastday', function (Silex\Application $app) {
    /* @var Google_Service_Analytics $service */
    $service = $app['google_api_service'];
    $websites = $app['websites'];
    $data = array();

    $params = array(
        'dimensions' => 'ga:dateHour',
        'sort' => '-ga:dateHour',
        'max-results' => '24',
    );
    foreach ($websites as $name => $code) {
        $vistors = null;
        try {
            $vistors = $service->data_ga->get($code, 'yesterday', 'today', 'ga:users', $params);
        } catch (Google_Exception $e) {
            $app['monolog']->addError($e->getMessage());
        }

        foreach ($vistors->getRows() as $row) {
            $data[] = array(
                'website' => $name,
                'dateHour' => $row[0],
                'users' => $row[1],
            );
        }
    }

    $content = $app['twig']->render('api/getuserslastday.csv.twig', array(
        'data' => $data,
    ));

    $headers = array('Content-Type' => 'text/csv');
    $response = new Response($content, 200, $headers);

    return $response;
});

/**
 * Returns a CSV with the active vistors for each website
 * Format: website;users
 */
$app->get('/api/getactiveusers', function (Silex\Application $app) {
    /* @var Google_Service_Analytics $service */
    $service = $app['google_api_service'];
    $websites = $app['websites'];
    $data = array();

    foreach ($websites as $name => $code) {
        $vistors = null;
        try {
            $vistors = $service->data_realtime->get($code, 'rt:activeUsers');
        } catch (Google_Exception $e) {
            $app['monolog']->addError($e->getMessage());
        }

        foreach ($vistors->getRows() as $row) {
            $data[] = array(
                'website' => $name,
                'users' => $row[0],
            );
        }
    }

    $content = $app['twig']->render('api/getactiveusers.csv.twig', array(
        'data' => $data,
    ));

    $headers = array('Content-Type' => 'text/csv');
    $response = new Response($content, 200, $headers);

    return $response;
});

$app->get('/', function (Silex\Application $app) {
    return $app['twig']->render('default/index.html.twig', array());
});

$app->run();
