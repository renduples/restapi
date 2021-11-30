<?php

// Set strict mode for type hinting / declarations in our methods
// See: https://www.php.net/manual/en/control-structures.declare.php

declare(strict_types=1);

// We avoid constants, function and class name collisions by using PHP NameSpaces
// See: https://www.php.net/manual/en/language.namespaces.rationale.php

use App\Application\Handlers\HttpErrorHandler;
use App\Application\Handlers\ShutdownHandler;
use App\Application\ResponseEmitter\ResponseEmitter;
use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Slim\Factory\AppFactory;
use Slim\Factory\ServerRequestCreatorFactory;

// Autoload 3rd party libraries our framework depends on
// https://getcomposer.org/doc/00-intro.md

require __DIR__ . '/../vendor/autoload.php';

// Instantiate PHP-DI ContainerBuilder
// Containers allow us to pass a dependency parameter in the class constructor instead of using the new operator
// We can also use type hinting to autowire dependencies
// See https://php-di.org/doc/getting-started.html

$containerBuilder = new ContainerBuilder();

// Compile the container for optimal performance using a proxy class
// In production you should clear that directory every time you deploy
// in development you should not compile the container
// See https://php-di.org/doc/lazy-injection.html

if (false) { // Should be set to true in production
	$containerBuilder->enableCompilation(__DIR__ . '/../var/cache');
}

// Load our App definitions in a Global Settings Object
// See https://php-di.org/doc/php-definitions.html

$settings = require __DIR__ . '/../app/settings.php';
$settings($containerBuilder);

// Load our App dependencies in a Global dependencies Object

$dependencies = require __DIR__ . '/../app/dependencies.php';
$dependencies($containerBuilder);

// Set up infrastructure persistance such as in memory repositories
// or the models for a database See: /src/Infrastructure/Persistence/Product/ProductDatabase.php

$repositories = require __DIR__ . '/../app/repositories.php';
$repositories($containerBuilder);

// Build PHP-DI Container instance
$container = $containerBuilder->build();

// Use a factory pattern to instantiate the application
// by having our factory class create and return an app object
// See: https://phptherightway.com/pages/Design-Patterns.html

AppFactory::setContainer($container);
$app = AppFactory::create();
$callableResolver = $app->getCallableResolver();

// Register our concentric middleware structure that expands outwardly
// with the last middleware layer added being the first to be executed
// See: https://www.slimframework.com/docs/v4/concepts/middleware.html

$middleware = require __DIR__ . '/../app/middleware.php';
$middleware($app);

// Register routes object that will correspond with our API endpoints
// See: https://www.slimframework.com/docs/v4/objects/request.html#route-object

$routes = require __DIR__ . '/../app/routes.php';
$routes($app);

// Group our settings classes with an Interface using the implements operator
// See https://www.php.net/manual/en/language.oop5.interfaces.php

/** @var SettingsInterface $settings */
$settings = $container->get(SettingsInterface::class);

// Get log levels for our App from /app/settings.php

$displayErrorDetails = $settings->get('displayErrorDetails');
$logError = $settings->get('logError');
$logErrorDetails = $settings->get('logErrorDetails');

// Create Request object from globals using the Interface design pattern

$serverRequestCreator = ServerRequestCreatorFactory::create();
$request = $serverRequestCreator->createServerRequestFromGlobals();

// Create Error Handler that receives all uncaught PHP exceptions
// See: https://www.slimframework.com/docs/v4/middleware/error-handling.html

$responseFactory = $app->getResponseFactory();
$errorHandler = new HttpErrorHandler($callableResolver, $responseFactory);

// Create an advanced Shutdown Handler
// See: https://www.slimframework.com/docs/v4/objects/application.html#advanced-shutdown-handler

$shutdownHandler = new ShutdownHandler($request, $errorHandler, $displayErrorDetails);
register_shutdown_function($shutdownHandler);

// Add Parsing of JSON or XML data for our POST endpoint
// using the body parsing middleware
// See https://www.slimframework.com/docs/v4/middleware/body-parsing.html

$app->addBodyParsingMiddleware();

// Add Routing Middleware
$app->addRoutingMiddleware();

// Add Error Middleware
$errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logError, $logErrorDetails);
$errorMiddleware->setDefaultErrorHandler($errorHandler);

// Finally Run the App and return the Response

$response = $app->handle($request);
$responseEmitter = new ResponseEmitter();
$responseEmitter->emit($response);
