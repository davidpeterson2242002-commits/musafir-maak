<?php

namespace Tests;

use App\Contracts\WhatsAppGateway;
use App\Gateways\FakeWhatsAppGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = TestSeeder::class;

    protected function whatsapp(): FakeWhatsAppGateway
    {
        return app(WhatsAppGateway::class);
    }
}
