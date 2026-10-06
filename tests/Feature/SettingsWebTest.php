<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Secret;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SettingsWebTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]));
    }

    public function test_edit_shows_current_values(): void
    {
        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('9Router')
            ->assertSee('combo-hemat');
    }

    public function test_update_saves_settings_and_encrypted_key(): void
    {
        $this->put(route('settings.update'), [
            'nine_router_base_url' => 'https://nine.example/v1',
            'nine_router_default_combo' => 'combo-coding',
            'nine_router_timeout' => 60,
            'api_key' => 'super-rahasia-123',
            'task_token_budget' => 150000,
            'delegation_max_depth' => 3,
            'delegation_max_rounds' => 4,
            'projects_root' => '/var/www/folder-proyek',
        ])->assertRedirect(route('settings.edit'));

        $settings = app(SettingsService::class);
        $this->assertSame('https://nine.example/v1', $settings->get('nine_router.base_url'));
        $this->assertSame('super-rahasia-123', $settings->getApiKey());

        $raw = Secret::where('key_name', 'ninerouter_api_key')->firstOrFail()->getRawOriginal('value');
        $this->assertStringNotContainsString('super-rahasia-123', $raw);
    }

    public function test_update_without_key_keeps_old_key(): void
    {
        app(SettingsService::class)->setApiKey('lama-123456');

        $this->put(route('settings.update'), [
            'nine_router_base_url' => 'https://nine.example/v1',
            'nine_router_default_combo' => 'combo-hemat',
            'nine_router_timeout' => 120,
            'api_key' => '',
            'task_token_budget' => 200000,
            'delegation_max_depth' => 4,
            'delegation_max_rounds' => 5,
            'projects_root' => '',
        ])->assertRedirect(route('settings.edit'));

        $this->assertSame('lama-123456', app(SettingsService::class)->getApiKey());
    }

    public function test_connection_test_reports_result(): void
    {
        Http::fake(['*' => Http::response([
            'model' => 'combo-hemat',
            'choices' => [['message' => ['content' => 'ok']]],
            'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1],
        ])]);

        app(SettingsService::class)->setApiKey('kunci-123456');

        $this->post(route('settings.test'))
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHas('status');
    }

    public function test_connection_test_without_key_shows_error(): void
    {
        $this->post(route('settings.test'))
            ->assertRedirect(route('settings.edit'))
            ->assertSessionHasErrors('connection');
    }
}
