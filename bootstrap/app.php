<?php

use App\Database\DB;
use App\Web\Lang;
use App\Web\Session;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use Slim\App;
use Slim\Container;

if (!file_exists('config.php') && is_dir('install/')) {
	header('Location: ./install/');
	exit();
} else if (!file_exists('config.php') && !is_dir('install/')) {
	exit('Cannot find the config file.');
}

// Load the config
$config = array_replace_recursive([
	'app_name' => 'XBackBone',
	'base_url' => isset($_SERVER['HTTPS']) ? 'https://' . $_SERVER['HTTP_HOST'] : 'http://' . $_SERVER['HTTP_HOST'],
	'storage_dir' => 'storage',
	'displayErrorDetails' => false,
	'maintenance' => false,
	'db' => [
		'connection' => 'sqlite',
		'dsn' => BASE_DIR . 'resources/database/xbackbone.db',
		'username' => null,
		'password' => null,
	],
], require BASE_DIR . 'config.php');

if (!$config['displayErrorDetails']) {
	$config['routerCacheFile'] = BASE_DIR . 'resources/cache/routes.cache.php';
}

$container = new Container(['settings' => $config]);

$container['config'] = function ($container) use ($config) {
	return $config;
};

$container['logger'] = function ($container) {
	$logger = new Logger('app');

	$streamHandler = new RotatingFileHandler(BASE_DIR . 'logs/log.txt', 10, Logger::DEBUG);
	$streamHandler->setFormatter(new LineFormatter("[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n", "Y-m-d H:i:s", true));

	$logger->pushHandler($streamHandler);

	return $logger;
};

$container['session'] = function ($container) {
	return new Session('xbackbone_session', BASE_DIR . 'resources/sessions');
};

$container['database'] = function ($container) use (&$config) {
	$dsn = $config['db']['connection'] === 'sqlite' ? BASE_DIR . $config['db']['dsn'] : $config['db']['dsn'];
	return new DB($config['db']['connection'] . ':' . $dsn, $config['db']['username'], $config['db']['password']);
};

$container['lang'] = function ($container) {
	return Lang::build(Lang::recognize(), BASE_DIR . 'resources/lang/');
};

$container['view'] = function ($container) use (&$config) {
	$view = new \Slim\Views\Twig(BASE_DIR . 'resources/templates', [
		'cache' => BASE_DIR . 'resources/cache',
		'autoescape' => 'html',
		'debug' => $config['displayErrorDetails'],
		'auto_reload' => $config['displayErrorDetails'],
	]);

	// Instantiate and add Slim specific extension
	$router = $container->get('router');
	$uri = \Slim\Http\Uri::createFromEnvironment(new \Slim\Http\Environment($_SERVER));
	$view->addExtension(new Slim\Views\TwigExtension($router, $uri));

	$view->getEnvironment()->addGlobal('config', $config);
	$view->getEnvironment()->addGlobal('request', $container->get('request'));
	$view->getEnvironment()->addGlobal('alerts', $container->get('session')->getAlert());
	$view->getEnvironment()->addGlobal('session', $container->get('session')->all());
	$view->getEnvironment()->addGlobal('current_lang', $container->get('lang')->getLang());
	$view->getEnvironment()->addGlobal('PLATFORM_VERSION', PLATFORM_VERSION);

	$view->getEnvironment()->addFunction(new Twig_Function('route', 'route'));
	$view->getEnvironment()->addFunction(new Twig_Function('lang', 'lang'));
	$view->getEnvironment()->addFunction(new Twig_Function('urlFor', 'urlFor'));
	$view->getEnvironment()->addFunction(new Twig_Function('mime2font', 'mime2font'));
	$view->getEnvironment()->addFunction(new Twig_Function('queryParams', 'queryParams'));
	return $view;
};

$container['phpErrorHandler'] = function ($container) {
	return function (\Slim\Http\Request $request, \Slim\Http\Response $response, \Throwable $error) use (&$container) {
		$container->logger->critical('Fatal runtime error during app execution', [$error, $error->getTraceAsString()]);
		return $container->view->render($response->withStatus(500), 'errors/500.twig', ['exception' => $error]);
	};
};

$container['errorHandler'] = function ($container) {
	return function (\Slim\Http\Request $request, \Slim\Http\Response $response, \Exception $exception) use (&$container) {

		if ($exception instanceof \App\Exceptions\MaintenanceException) {
			return $container->view->render($response->withStatus(503), 'errors/maintenance.twig');
		}

		if ($exception instanceof \App\Exceptions\UnauthorizedException) {
			return $container->view->render($response->withStatus(403), 'errors/403.twig');
		}

		$container->logger->critical('Fatal exception during app execution', [$exception, $exception->getTraceAsString()]);
		return $container->view->render($response->withStatus(500), 'errors/500.twig', ['exception' => $exception]);
	};
};

$container['notAllowedHandler'] = function ($container) {
	return function (\Slim\Http\Request $request, \Slim\Http\Response $response, $methods) use (&$container) {
		return $container->view->render($response->withStatus(405)->withHeader('Allow', implode(', ', $methods)), 'errors/405.twig');
	};
};

$container['notFoundHandler'] = function ($container) {
	return function (\Slim\Http\Request $request, \Slim\Http\Response $response) use (&$container) {
		$response->withStatus(404)->withHeader('Content-Type', 'text/html');
		return $container->view->render($response, 'errors/404.twig');
	};
};

$app = new App($container);

// Permanently redirect paths with a trailing slash to their non-trailing counterpart
$app->add(function (\Slim\Http\Request $request, \Slim\Http\Response $response, callable $next) {
	$uri = $request->getUri();
	$path = $uri->getPath();

	if ($path !== '/' && substr($path, -1) === '/') {
		$uri = $uri->withPath(substr($path, 0, -1));

		if ($request->getMethod() === 'GET') {
			return $response->withRedirect((string)$uri, 301);
		} else {
			return $next($request->withUri($uri), $response);
		}
	}

	return $next($request, $response);
});

// Load the application routes
require BASE_DIR . 'app/routes.php';