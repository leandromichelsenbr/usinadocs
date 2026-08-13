<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

\UsinaDocs\Core\AppFactory::create(dirname(__DIR__))->run();
