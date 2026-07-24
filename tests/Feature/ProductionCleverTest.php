<?php

declare(strict_types=1);

namespace Clever\Clever\Tests\Feature;

use Clever\Clever\Clever;
use Clever\Clever\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

final class ProductionCleverTest extends TestCase
{
    /**
     * Force-enable on purpose: these tests prove production always wins.
     *
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['env'] = 'production';
        $app['config']->set('app.env', 'production');
        $app['config']->set('clever.enabled', true);
    }

    public function test_it_is_never_enabled_in_production_even_when_forced_on(): void
    {
        $this->assertTrue($this->app->make('config')->get('clever.enabled'));
        $this->assertFalse($this->app->make(Clever::class)->enabled());
    }

    public function test_it_registers_no_clever_commands_in_production(): void
    {
        $clever = array_filter(
            array_keys(Artisan::all()),
            fn (string $name): bool => str_starts_with($name, 'clever:'),
        );

        $this->assertSame([], $clever);
    }

    public function test_it_registers_no_panel_route_in_production(): void
    {
        $this->assertFalse(Route::has('clever.panel'));

        $this->get('/clever')->assertNotFound();
    }
}
