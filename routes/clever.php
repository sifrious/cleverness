<?php

declare(strict_types=1);

use Clever\Clever\Http\Controllers\PanelController;
use Clever\Clever\Support\CleverConfig;
use Illuminate\Support\Facades\Route;

// Read through CleverConfig so a wrong-typed route.path / route.middleware
// value falls back to a safe default instead of throwing during boot.
$config = app(CleverConfig::class);

Route::middleware($config->routeMiddleware())
    ->get($config->routePath(), PanelController::class)
    ->name('clever.panel');
