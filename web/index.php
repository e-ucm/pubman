<?php

require_once __DIR__.'/../bootstrap.php';

//
// Silex Dependency Injection Container (DCI) initialiation
//

$app = new Silex\Application();

$twigSettings = array();
if (PUBMAN_DEBUG) {
	$twigSettings = array(
	'twig.path' => __DIR__.'/../views',
	'twig.options' => array(
		'auto_reload' => true,
		'debug' => true
	));
} else {
	$twigSettings = array(
	'twig.path' => __DIR__.'/../views',
	'twig.options' => array(
		'cache' => __DIR__. '/../cache',
		'auto_reload' => false, // set to false on production
		'debug' => false // set to false on production
	));
}
// Initilize TWIG templating system
$app->register(new Silex\Provider\TwigServiceProvider(), $twigSettings);

// Iinitialize URL generation service
$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

// Initialize Silex Forms+Validation service
$app->register(new Silex\Provider\FormServiceProvider());
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

$app->get('/', function() use($app) {
	return $app['twig']->render('main.twig', array(
		'navbar' => 'home-navbar.twig',
		'content' => 'home-content.twig',
	));
})->bind('homepage');


$app->get('/publications', 'es\\eucm\\PublicationsService::listPublications')->bind('publications');
$app->get('/publications/all', 'es\\eucm\\PublicationsService::listAllPublications');

$app->get('/publications/add', 'es\\eucm\\PublicationsService::addPublication')->bind('addPublication');
$app->post('/publications', 'es\\eucm\\PublicationsService::addPublication');


$app->get('/publications/{publicationId}/edit', 'es\\eucm\\PublicationsService::editPublication')->bind('editPublicationView');
$app->put('/publications/{publicationId}', 'es\\eucm\\PublicationsService::editPublication')->bind('editPublication');
$app->get('/publications/{publicationId}/delete', 'es\\eucm\\PublicationsService::deletePublication')->bind('delPublicationView');
$app->delete('/publications/{publicationId}', 'es\\eucm\\PublicationsService::deletePublication')->bind('delPublication');

$app->get('/authors', 'es\\eucm\\AuthorsService::listAuthors')->bind('authors');
$app->get('/authors/all', 'es\\eucm\\AuthorsService::listAllAuthors');

$app->get('/authors/add', 'es\\eucm\\AuthorsService::addAuthor')->bind('addAuthor');
$app->post('/authors', 'es\\eucm\\AuthorsService::addAuthor');

$app->get('/authors/{authorId}/edit', 'es\\eucm\\AuthorsService::editAuthor')->bind('editAuthorView');
$app->put('/authors/{authorId}', 'es\\eucm\\AuthorsService::editAuthor')->bind('editAuthor');
$app->get('/authors/{authorId}/delete', 'es\\eucm\\AuthorsService::deleteAuthor')->bind('delAuthorView');
$app->delete('/authors/{authorId}', 'es\\eucm\\AuthorsService::deleteAuthor')->bind('delAuthor');


// Enable the use for _method FORM param to override HTTP method
use Symfony\Component\HttpFoundation\Request;
Request::enableHttpMethodParameterOverride();

$app->run();
