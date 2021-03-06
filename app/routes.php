<?php
// Auth routes
$app->group('', function () {
	$this->get('/home[/page/{page}]', \App\Controllers\DashboardController::class . ':home')->setName('home');

	$this->group('', function () {
		$this->get('/home/switchView', \App\Controllers\DashboardController::class . ':switchView')->setName('switchView');

		$this->get('/system/deleteOrphanFiles', \App\Controllers\AdminController::class . ':deleteOrphanFiles')->setName('system.deleteOrphanFiles');

		$this->get('/system/themes', \App\Controllers\ThemeController::class . ':getThemes')->setName('theme');
		$this->post('/system/theme/apply', \App\Controllers\ThemeController::class . ':applyTheme')->setName('theme.apply');

		$this->post('/system/upgrade', \App\Controllers\UpgradeController::class . ':upgrade')->setName('system.upgrade');
		$this->get('/system/checkForUpdates', \App\Controllers\UpgradeController::class . ':checkForUpdates')->setName('system.checkForUpdates');

		$this->get('/system', \App\Controllers\AdminController::class . ':system')->setName('system');

		$this->get('/users[/page/{page}]', \App\Controllers\UserController::class . ':index')->setName('user.index');
	})->add(\App\Middleware\AdminMiddleware::class);

	$this->group('/user', function () {

		$this->get('/create', \App\Controllers\UserController::class . ':create')->setName('user.create');
		$this->post('/create', \App\Controllers\UserController::class . ':store')->setName('user.store');
		$this->get('/{id}/edit', \App\Controllers\UserController::class . ':edit')->setName('user.edit');
		$this->post('/{id}', \App\Controllers\UserController::class . ':update')->setName('user.update');
		$this->get('/{id}/delete', \App\Controllers\UserController::class . ':delete')->setName('user.delete');
	})->add(\App\Middleware\AdminMiddleware::class);

	$this->get('/profile', \App\Controllers\UserController::class . ':profile')->setName('profile');
	$this->post('/profile/{id}', \App\Controllers\UserController::class . ':profileEdit')->setName('profile.update');
	$this->post('/user/{id}/refreshToken', \App\Controllers\UserController::class . ':refreshToken')->setName('refreshToken');
	$this->get('/user/{id}/config/sharex', \App\Controllers\UserController::class . ':getShareXconfigFile')->setName('config.sharex');
	$this->get('/user/{id}/config/script', \App\Controllers\UserController::class . ':getUploaderScriptFile')->setName('config.script');

	$this->post('/upload/{id}/publish', \App\Controllers\UploadController::class . ':togglePublish')->setName('upload.publish');
	$this->post('/upload/{id}/unpublish', \App\Controllers\UploadController::class . ':togglePublish')->setName('upload.unpublish');
	$this->get('/upload/{id}/raw', \App\Controllers\UploadController::class . ':getRawById')->add(\App\Middleware\AdminMiddleware::class)->setName('upload.raw');
	$this->post('/upload/{id}/delete', \App\Controllers\UploadController::class . ':delete')->setName('upload.delete');

})->add(App\Middleware\CheckForMaintenanceMiddleware::class)->add(\App\Middleware\AuthMiddleware::class);

$app->get('/', \App\Controllers\DashboardController::class . ':redirects')->setName('root');
$app->get('/login', \App\Controllers\LoginController::class . ':show')->setName('login.show');
$app->post('/login', \App\Controllers\LoginController::class . ':login')->setName('login');
$app->map(['GET', 'POST'], '/logout', \App\Controllers\LoginController::class . ':logout')->setName('logout');

$app->post('/upload', \App\Controllers\UploadController::class . ':upload')->setName('upload');

$app->get('/{userCode}/{mediaCode}', \App\Controllers\UploadController::class . ':show')->setName('public');
$app->get('/{userCode}/{mediaCode}/delete/{token}', \App\Controllers\UploadController::class . ':show')->setName('public.delete.show')->add(\App\Middleware\CheckForMaintenanceMiddleware::class);;
$app->post('/{userCode}/{mediaCode}/delete/{token}', \App\Controllers\UploadController::class . ':deleteByToken')->setName('public.delete')->add(\App\Middleware\CheckForMaintenanceMiddleware::class);;
$app->get('/{userCode}/{mediaCode}/raw', \App\Controllers\UploadController::class . ':showRaw')->setName('public.raw')->setOutputBuffering(false);
$app->get('/{userCode}/{mediaCode}/download', \App\Controllers\UploadController::class . ':download')->setName('public.download')->setOutputBuffering(false);