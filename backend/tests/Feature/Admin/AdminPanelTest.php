<?php

namespace Tests\Feature\Admin;

use App\Enums\AdminRole;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Routes\Pages\ManageRoutes;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\City;
use App\Models\TravelRoute;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_app_users_cannot_reach_the_panel(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_super_admin_can_open_every_page(): void
    {
        User::factory()->count(3)->create();
        $this->actingAs(Admin::factory()->create(), 'admin');

        foreach (['/admin', '/admin/settings', '/admin/routes', '/admin/cities', '/admin/states', '/admin/users', '/admin/admins', '/admin/audit-logs'] as $url) {
            $this->get($url)->assertOk();
        }

        $this->get('/admin/users/'.User::first()->id)->assertOk();
    }

    public function test_inactive_admin_is_denied(): void
    {
        $this->actingAs(Admin::factory()->create(['is_active' => false]), 'admin');
        $this->get('/admin')->assertForbidden();
    }

    public function test_reviewer_sees_users_but_not_settings_or_routes(): void
    {
        $this->actingAs(Admin::factory()->role(AdminRole::Reviewer)->create(), 'admin');

        $this->get('/admin/users')->assertOk();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/routes')->assertForbidden();
        $this->get('/admin/admins')->assertForbidden();
        $this->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_settings_page_saves_values_and_audits(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ManageSettings::class)
            ->assertSchemaStateSet(['booking__response_timeout_hours' => 12])
            ->fillForm([
                'booking__response_timeout_hours' => 8,
                'fees__model' => 'fixed_per_seat',
                'verification__allow_first_booking_while_pending' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(8, settings()->int('booking.response_timeout_hours'));
        $this->assertSame('fixed_per_seat', settings('fees.model'));
        $this->assertFalse(settings()->bool('verification.allow_first_booking_while_pending'));
        $this->assertSame(3, AuditLog::where('subject_type', 'App\Models\Setting')->count());
    }

    public function test_settings_page_validates_minimums(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ManageSettings::class)
            ->fillForm(['booking__response_timeout_hours' => 0])
            ->call('save')
            ->assertHasFormErrors(['booking__response_timeout_hours']);

        $this->assertSame(12, settings()->int('booking.response_timeout_hours'));
    }

    public function test_settings_list_field_round_trips(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(ManageSettings::class)
            ->fillForm(['content__prohibited_items' => ['أسلحة', 'ذهب']])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['أسلحة', 'ذهب'], settings()->list('content.prohibited_items'));
    }

    public function test_route_can_be_created_and_duplicate_is_rejected(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $kosti = City::where('name_en', 'Kosti')->value('id');
        $obeid = City::where('name_en', 'El Obeid')->value('id');

        Livewire::test(ManageRoutes::class)
            ->callAction('create', ['origin_city_id' => $kosti, 'destination_city_id' => $obeid, 'enabled' => true, 'sort' => 0])
            ->assertHasNoActionErrors();
        $this->assertTrue(TravelRoute::between($kosti, $obeid)->exists());

        Livewire::test(ManageRoutes::class)
            ->callAction('create', ['origin_city_id' => $kosti, 'destination_city_id' => $obeid, 'enabled' => true, 'sort' => 0])
            ->assertHasActionErrors(['origin_city_id' => 'unique']);

        $this->assertSame(1, AuditLog::where('subject_type', 'App\Models\TravelRoute')->where('action', 'created')->count());
    }

    public function test_admin_can_ban_and_reactivate_a_user(): void
    {
        $admin = Admin::factory()->role(AdminRole::Support)->create();
        $this->actingAs($admin, 'admin');
        $user = User::factory()->create();
        $user->createToken('t');

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('ban', ['reason' => 'احتيال'])
            ->assertHasNoActionErrors();

        $this->assertTrue($user->fresh()->isBanned());
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.banned', 'admin_id' => $admin->id, 'subject_id' => $user->id]);

        Livewire::test(ViewUser::class, ['record' => $user->id])
            ->callAction('reactivate', ['reason' => 'تم التحقق'])
            ->assertHasNoActionErrors();
        $this->assertSame('active', $user->fresh()->status->value);
    }
}
