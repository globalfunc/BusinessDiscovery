<?php

namespace Tests\Feature\Admin;

use App\Models\BusinessOwner;
use App\Models\DiscoveryAnswer;
use App\Models\DiscoverySession;
use App\Models\SelectedService;
use App\Models\Service;
use App\Models\SpecDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S4.4 — Spec review & decision-surface UI: the admin spec page must serve
 * every stored version with its markdown (unlike the Show page's metadata
 * -only list), and assemble the decision surface from selected_services and
 * phase_3/phase_6 discovery_answers rather than the compiled markdown.
 */
class SpecReviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_page_serves_every_spec_version_with_markdown(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $session = DiscoverySession::factory()->create(['business_owner_id' => $businessOwner->id]);
        SpecDocument::factory()->create(['discovery_session_id' => $session->id, 'version' => 1, 'markdown' => '## v1 content']);
        SpecDocument::factory()->create(['discovery_session_id' => $session->id, 'version' => 2, 'markdown' => '## v2 content']);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.spec', $businessOwner));

        $response->assertOk();
        $versions = collect($response->viewData('page')['props']['versions']);
        $this->assertCount(2, $versions);
        $this->assertSame(2, $versions->first()['version']);
        $this->assertSame('## v2 content', $versions->first()['markdown']);
        $this->assertSame('## v1 content', $versions->last()['markdown']);
    }

    public function test_decision_surface_summarizes_services_billing_and_branding(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $session = DiscoverySession::factory()->create(['business_owner_id' => $businessOwner->id]);
        $service = Service::factory()->create(['name' => ['en' => 'Online Booking', 'bg' => 'Онлайн резервации']]);

        SelectedService::create([
            'discovery_session_id' => $session->id,
            'service_id' => $service->id,
            'custom' => false,
            'features' => ['24/7 booking', 'Reminders'],
            'priority' => true,
            'origin' => 'catalog',
        ]);

        SelectedService::create([
            'discovery_session_id' => $session->id,
            'service_id' => null,
            'custom' => true,
            'name' => 'Loyalty punch cards',
            'description' => 'Digital punch card for repeat clients',
            'features' => ['Stamp on visit'],
            'priority' => false,
            'origin' => 'custom',
        ]);

        DiscoveryAnswer::create([
            'discovery_session_id' => $session->id,
            'phase' => 'phase_6',
            'field_key' => 'billing_model',
            'value' => 'build_support',
        ]);
        DiscoveryAnswer::create([
            'discovery_session_id' => $session->id,
            'phase' => 'phase_6',
            'field_key' => 'budget_min',
            'value' => 2000,
        ]);
        DiscoveryAnswer::create([
            'discovery_session_id' => $session->id,
            'phase' => 'phase_6',
            'field_key' => 'budget_max',
            'value' => 8000,
        ]);
        DiscoveryAnswer::create([
            'discovery_session_id' => $session->id,
            'phase' => 'phase_6',
            'field_key' => 'timeline_choice',
            'value' => 'asap',
        ]);
        DiscoveryAnswer::create([
            'discovery_session_id' => $session->id,
            'phase' => 'phase_3',
            'field_key' => 'style_chips',
            'value' => ['modern', 'bold'],
        ]);

        SpecDocument::factory()->create(['discovery_session_id' => $session->id, 'version' => 1]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.spec', $businessOwner));

        $surface = $response->viewData('page')['props']['decisionSurface'];

        $this->assertCount(2, $surface['services']);
        $byName = collect($surface['services'])->keyBy('name');
        $this->assertTrue($byName['Online Booking']['priority']);
        $this->assertFalse($byName['Loyalty punch cards']['priority']);

        $this->assertSame('build_support', $surface['billing']['billing_model']);
        $this->assertSame(2000, $surface['billing']['budget_min']);
        $this->assertSame(8000, $surface['billing']['budget_max']);
        $this->assertSame('asap', $surface['billing']['timeline_choice']);

        $this->assertSame(['modern', 'bold'], $surface['branding']['style_chips']);
    }

    public function test_page_handles_business_owner_with_no_discovery_session(): void
    {
        $businessOwner = BusinessOwner::factory()->create();

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.spec', $businessOwner));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $this->assertSame([], $props['versions']);
        $this->assertNull($props['decisionSurface']);
    }
}
