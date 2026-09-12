<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Integration\ItemsController;

Route::get('/probe', ItemsController::class);
