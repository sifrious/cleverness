<?php

declare(strict_types=1);

namespace Clever\Clever\Tests\Feature;

use Clever\Clever\Clever;
use Clever\Clever\Tests\TestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

final class DisabledCleverTest extends TestCase
{
    /**
     * @param  Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $app['config']->set('clever.enabled', false);
    }

    public function test_it_reports_disabled_when_config_turns_it_off(): void
    {
        $this->assertFalse($this->app->make(Clever::class)->enabled());
    }

    public function test_it_registers_no_clever_commands_when_disabled(): void
    {
        $clever = array_filter(
            array_keys(Artisan::all()),
            fn (string $name): bool => str_starts_with($name, 'clever:'),
        );

        $this->assertSame([], $clever);
    }

    public function test_it_registers_no_panel_route_when_disabled(): void
    {
        $this->assertFalse(Route::has('clever.panel'));

        $this->get('/clever')->assertNotFound();
    }
}
