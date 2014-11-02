<?php
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

$app = new Silex\Application();

/* START CONFIGURATION */
$app['debug'] = true;
$app['config_path'] = __DIR__.'/../config/config.json';
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

$app['config'] = $app->share(function ($app) {
    $config = array();
    try {
        if (!file_exists($app['config_path'])) {
            throw new FileNotFoundException('Could not find file'.$app['config_path']);
        }
        $json = file_get_contents($app['config_path']);
        $config = json_decode($json);
    } catch (Exception $e) {
        $app['monolog']->addError($e->getMessage());
        exit(1);
    }

    return $config;
});

/**
 * Creates the Google Analytics service
 */
$app['google_api_service'] = $app->share(function ($app) {
    try {
        $c = $app['config']->google_api;
        $client = new Google_Client();
        $client->setApplicationName($c->app_name);
        $service = new Google_Service_Analytics($client);

        if (null !== $gapi = $app['session']->get('gapi')) {
            $client->setAccessToken($gapi['token']);
        }

        $key = file_get_contents(__DIR__.$c->key_path);
        $cred = new Google_Auth_AssertionCredentials(
            $c->username,
            array(Google_Service_Analytics::ANALYTICS_READONLY),
            $key,
            $c->key_secret
        );

        $client->setAssertionCredentials($cred);
        if ($client->getAuth()->isAccessTokenExpired()) {
            $client->getAuth()->refreshTokenWithAssertion($cred);
        }

        $app['session']->set('gapi', array('token' => $client->getAccessToken()));
    } catch (Google_Exception $e) {
        $app['monolog']->addError($e->getMessage());
        exit(1);
    }

    return $service;
});

/**
 * Obtains the profiles for the tracking codes
 */
$app['websites'] = $app->share(function ($app) {
    /* @var Google_Service_Analytics $service */
    $c = $app['config'];
    $service = $app['google_api_service'];
    $websites = array();

    $tracking_codes = $c->websites;
    try {
        $summaries = $service->management_accountSummaries->listManagementAccountSummaries();
        foreach ($summaries->getItems() as $account) {
            $web_properties = $service->management_webproperties->listManagementWebproperties($account->getId());
            foreach ($tracking_codes as $code) {
                foreach ($web_properties as $property) {
                    if ($property->getId() !== $code->tracking_id) {
                        continue;
                    }
                    $websites[$code->name] = 'ga:'.$property->getDefaultProfileId();
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
$app->get('/api/getuserslastday.csv', function (Silex\Application $app) {
    /* @var Google_Service_Analytics $service */
    $service = $app['google_api_service'];
    $websites = $app['websites'];
    $data = array();

    $params = array(
        'dimensions' => 'ga:dateHour',
        'sort' => '-ga:dateHour',
        'max-results' => '25',
    );
    foreach ($websites as $name => $code) {
        $visitors = null;
        try {
            $visitors = $service->data_ga->get($code, 'yesterday', 'today', 'ga:users', $params);

            $results = array();
            foreach ($visitors->getRows() as $row) {
                if (count($row) !== 2) {
                    continue;
                }
                $results[$row[0]] = $row[1];
            }

            // Backfill the data
            $current_datetime = new \DateTime('24 hours ago');
            $end_date = new \DateTime('now');

            while ($current_datetime < $end_date) {
                $formated_date = $current_datetime->format('YmdH');
                $users = (array_key_exists($formated_date, $results)) ? $results[$formated_date] : 0;
                $data[] = array(
                    'website' => $name,
                    'dateHour' => $formated_date,
                    'users' => $users,
                );
                $current_datetime->add(new DateInterval('PT1H'));
            }
        } catch (Google_Exception $e) {
            $app['monolog']->addError($e->getMessage());
            continue;
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
$app->get('/api/getactiveusers.json', function (Silex\Application $app) {
    /* @var Google_Service_Analytics $service */
    $service = $app['google_api_service'];
    $websites = $app['websites'];
    $data = array();

    foreach ($websites as $name => $code) {
        try {
            $visitors = $service->data_realtime->get($code, 'rt:activeUsers');
            $data[$name] = ($visitors->getTotalResults() > 0) ? $visitors->getRows()[0][0] : 0;
        } catch (Google_Exception $e) {
            $app['monolog']->addError($e->getMessage());
        }
    }

    $content = $app['twig']->render('api/getactiveusers.json.twig', array(
        'data' => $data,
    ));

    $headers = array('Content-Type' => 'application/json');
    $response = new Response($content, 200, $headers);

    return $response;
});

$app->get('/', function (Silex\Application $app) {
    $c = $app['config']->display;

    return $app['twig']->render('default/index.html.twig', array(
        'horizontal_tiles' => $c->horizontal_tiles,
        'vertical_tiles' => $c->vertical_tiles,
    ));
});

$app->run();
