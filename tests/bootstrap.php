<?php

// Try extension vendor first, then walk up to find project vendor
$autoload = dirname(__DIR__) . '/vendor/autoload.php';
for ($i = 2; $i <= 8 && !file_exists($autoload); $i++) {
    $autoload = dirname(__DIR__, $i) . '/vendor/autoload.php';
}

if (!file_exists($autoload)) {
    die('Could not find autoload.php. Run composer install first.');
}

require_once $autoload;

// Register test namespace and extension source (may not be in project's autoload)
$loader = new \Composer\Autoload\ClassLoader();
$loader->addPsr4('dmstr\\rest\\sdk\\tests\\', __DIR__);
$loader->addPsr4('dmstr\\rest\\sdk\\', dirname(__DIR__) . '/src');
$loader->register(true);

// Minimal Yii bootstrap without web application
defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'test');

require_once dirname($autoload) . '/yiisoft/yii2/Yii.php';
