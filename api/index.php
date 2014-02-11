<?php

require_once __DIR__.'/../bootstrap.php';

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

//
// Silex Dependency Injection Container (DCI) initialiation
//

$app = new Silex\Application();

// Iinitialize URL generation service
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Initialize Silex Validation service
$app->register(new Silex\Provider\ValidatorServiceProvider());
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'locale_fallbacks' => array('en'),
));

/* Registers $app['conn'] to access the database connection.
 * Initilize dabase connection provider (per user connection) service
 */
$app->register(new es\eucm\DatabaseServiceProvider(), array(
	'database.server' => PUBMAN_DB_SERVER,
	'database.user' => PUBMAN_DB_USER, 
	'database.password' => PUBMAN_DB_PASSWORD,
	'database.name' => PUBMAN_DB_DATABASE,
));
// Registers $app['sqltemplate'] as a PreparedStatementTemplate's factory
$app->register(new es\eucm\SQLTemplateProvider(), array(
	'sqltemplate.connection' => $app['conn'],
));

// Publications repository
$app['publications.repository'] = function ($app) {
	return new \es\eucm\PublicationsRepository($app['sqltemplate'], $app['sqltransaction']);
};

// Authors repository
$app['authors.repository'] = function ($app) {
	return new \es\eucm\AuthorsRepository($app['sqltemplate']);
};

//
// Application parameters configuration
//

$app['debug'] = PUBMAN_DEBUG;

// Catch PHP Errors and PHP Notices
use Symfony\Component\Debug\ErrorHandler;
ErrorHandler::register();

// Catch PHP Fatal errors
use Symfony\Component\Debug\ExceptionHandler;
// Recommended false in production
ExceptionHandler::register(PUBMAN_DEBUG);



//
// Application Routing definition
//


function pubman_calculate_signature ($method, $url, $params, $apiSecret) {
	ksort($params);
	$params_string='';
	$i=0;
	foreach ($params as $key => $value) {
		if ($i > 0) {
			$params_string .= '&';
		}
		$params_string .= rawurlencode($key). '='. rawurlencode($value);
		$i++;
	}

	$request = strtoupper($method) . '&' . rawurlencode($url) . '&' . $params_string;
	return base64_encode(hash_hmac('sha256', $request, $apiSecret));
}

$app->before(function (Request $request) use ($app) {
	$apiKey = $request->get('apikey');
	if ($apiKey !== PUBMAN_API_KEY) {
		return new Response('Unauthorized', 403);
	}
	$actualSignature = $request->get('signature');
	$params = $request->query->all();
	unset($params['signature']);
	$url = preg_replace('/\?.*/', '', $request->getUri());

	$expectedSignature = pubman_calculate_signature($request->getMethod(), $url, $params, PUBMAN_API_SECRET);
	if ($actualSignature !== $expectedSignature ) {
		return new Response('Unauthorized', 403);
	}
});

$app->get('/publications', function (Request $request) use ($app) {
	$publications = $app['publications.repository']->getPublications();
	return new Response(json_encode($publications), 200, array ('Content-type: application/json'));
});

$app->get('/publications/{publicationId}', function (Request $request, $publicationId) use ($app) {
	$publication = $app['publications.repository']->getPublication(intval($publicationId));
	if (!$publication) {
		return new Response(json_encode(array("error" => "Publication not found")), 404, array ('Content-type: application/json'));
	}
	return new Response(json_encode($publication), 200, array ('Content-type: application/json'));
});

$app->get('/authors/{authorId}', function (Request $request, $authorId) use ($app) {
	$author = $app['authors.repository']->getAuthor(intval($authorId));
	if (!$author) {
		return new Response(json_encode(array("error" => "Author not found")), 404, array ('Content-type: application/json'));
	}
	$publications = $app['publications.repository']->getAuthorPublications(intval($authorId));
	return new Response(json_encode($publications), 200, array ('Content-type: application/json'));
});

$app->run();
