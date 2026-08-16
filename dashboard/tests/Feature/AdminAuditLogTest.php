<?php

namespace Tests\Feature;

use App\Models\FinancialAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_page_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.audit-log'))
            ->assertForbidden();
    }

    public function test_admin_can_view_audit_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['balance' => 5]);

        FinancialAuditEvent::create([
            'actor_id' => $admin->id,
            'action' => 'event_topup_settings',
            'metadata' => ['enabled' => '1', 'type' => 'tier'],
            'ip' => '127.0.0.1',
        ]);
        FinancialAuditEvent::create([
            'target_user_id' => $user->id,
            'action' => 'topup_bonus',
            'amount' => '0.923077',
            'balance_before' => '5.000000',
            'balance_after' => '5.923077',
            'metadata' => ['amount_idr' => 100000, 'bonus_idr' => 15000],
        ]);
        FinancialAuditEvent::create([
            'target_user_id' => $user->id,
            'action' => 'referral_reward',
            'amount' => '0.500000',
            'metadata' => ['referral_code' => 'ABC123'],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->assertSee('Audit Log')
            ->assertSee('event_topup_settings')
            ->assertSee('topup_bonus')
            ->assertSee('referral_reward')
            ->assertSee($admin->email)
            ->assertSee($user->email)
            ->assertSee('0.923077');
    }

    public function test_audit_log_can_be_filtered_by_action(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        FinancialAuditEvent::create(['actor_id' => $admin->id, 'action' => 'event_topup_settings', 'metadata' => ['filter_marker' => 'TOPUP-ONLY']]);
        FinancialAuditEvent::create(['actor_id' => $admin->id, 'action' => 'event_referral_settings', 'metadata' => ['filter_marker' => 'REFERRAL-ONLY']]);

        $this->actingAs($admin)
            ->get(route('admin.audit-log', ['action' => 'event_topup_settings']))
            ->assertOk()
            ->assertSee('TOPUP-ONLY')
            ->assertDontSee('REFERRAL-ONLY');
    }

    public function test_stats_show_event_related_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        FinancialAuditEvent::create(['actor_id' => $admin->id, 'action' => 'event_topup_settings', 'metadata' => []]);
        FinancialAuditEvent::create(['actor_id' => $admin->id, 'action' => 'event_referral_settings', 'metadata' => []]);
        FinancialAuditEvent::create(['action' => 'topup_bonus', 'amount' => '1.000000', 'metadata' => []]);
        FinancialAuditEvent::create(['action' => 'referral_reward', 'amount' => '0.500000', 'metadata' => []]);

        $this->actingAs($admin)
            ->get(route('admin.audit-log'))
            ->assertOk()
            ->assertSee('4')
            ->assertSee('1')
            ->assertSee('2');
    }
}
