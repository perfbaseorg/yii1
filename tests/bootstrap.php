<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

$yiiBootstrap = dirname(__DIR__) . '/vendor/yiisoft/yii/framework/yii.php';
if (file_exists($yiiBootstrap)) {
    require_once $yiiBootstrap;
}
