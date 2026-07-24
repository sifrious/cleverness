<?php

declare(strict_types=1);

namespace Clever\Clever\Tests\Feature;

use Clever\Clever\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;

final class CustomRoutePathTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('clever.route.path', 'dev/clever');
    }

    public function test_it_registers_the_panel_at_the_configured_path(): void
    {
        $this->assertStringEndsWith('/dev/clever', route('clever.panel'));

        $this->get('/dev/clever')->assertOk()->assertSee('No report yet');
    }
}

final class MistypedRouteConfigTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        // Common mistakes: a bare string middleware and a null path. Neither
        // should crash boot — CleverConfig narrows them to safe defaults.
        $app['config']->set('clever.route.middleware', 'web');
        $app['config']->set('clever.route.path', null);
    }

    public function test_it_falls_back_to_safe_route_defaults_instead_of_crashing(): void
    {
        $this->assertTrue(Route::has('clever.panel'));

        $this->get('/clever')->assertOk()->assertSee('No report yet');
    }
}
