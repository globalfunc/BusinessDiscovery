<?php

namespace Tests\Feature\Admin;

use App\Enums\DiscoveryPhase;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\DcpProfile;
use App\Models\DiscoveryAnswer;
use App\Models\DiscoverySession;
use App\Models\SpecDocument;
use App\Models\Upload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * S4.2 — BO detail page: per-phase discovery progress, structured answers,
 * uploaded assets, DCP view, spec version list, and AI usage/cost scoped
 * to a single BO (must not leak other BOs' ai_calls into the aggregate).
 */
class BusinessOwnerShowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_discovery_progress_marks_completed_current_and_upcoming_phases(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        DiscoverySession::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'current_phase' => DiscoveryPhase::Phase3,
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $response->assertOk();
        $phases = collect($response->viewData('page')['props']['discovery']['phases']);

        $this->assertSame('completed', $phases->firstWhere('value', 'phase_0')['status']);
        $this->assertSame('completed', $phases->firstWhere('value', 'phase_2')['status']);
        $this->assertSame('current', $phases->firstWhere('value', 'phase_3')['status']);
        $this->assertSame('upcoming', $phases->firstWhere('value', 'phase_4')['status']);
        $this->assertSame('upcoming', $phases->firstWhere('value', 'review')['status']);
    }

    public function test_submitted_session_marks_every_phase_completed(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        DiscoverySession::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'current_phase' => DiscoveryPhase::Review,
            'status' => 'submitted',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $phases = collect($response->viewData('page')['props']['discovery']['phases']);
        $this->assertTrue($phases->every(fn ($phase) => $phase['status'] === 'completed'));
    }

    public function test_structured_answers_are_grouped_by_phase_in_phase_order(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $session = DiscoverySession::factory()->create(['business_owner_id' => $businessOwner->id]);

        DiscoveryAnswer::create([
            'discovery_session_id' => $session->id,
            'phase' => 'phase_2',
            'field_key' => 'services_offered',
            'value' => ['haircut', 'shave'],
        ]);
        DiscoveryAnswer::create([
            'discovery_session_id' => $session->id,
            'phase' => 'phase_0',
            'field_key' => 'business_name',
            'value' => 'Test Barbershop',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $answers = $response->viewData('page')['props']['answers'];

        $this->assertSame('phase_0', $answers[0]['phase']);
        $this->assertSame('phase_2', $answers[1]['phase']);
        $this->assertSame('business_name', $answers[0]['answers'][0]['field_key']);
        $this->assertSame('Test Barbershop', $answers[0]['answers'][0]['value']);
    }

    public function test_uploaded_assets_render_with_download_urls(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $session = DiscoverySession::factory()->create(['business_owner_id' => $businessOwner->id]);

        Upload::create([
            'business_owner_id' => $businessOwner->id,
            'discovery_session_id' => $session->id,
            'phase' => 'phase_3',
            'path' => 'uploads/1/logo.png',
            'thumb_path' => 'uploads/1/logo-thumb.png',
            'original_name' => 'logo.png',
            'mime' => 'image/png',
            'size' => 12345,
            'kind' => 'image',
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $uploads = $response->viewData('page')['props']['uploads'];

        $this->assertCount(1, $uploads);
        $this->assertSame('logo.png', $uploads[0]['original_name']);
        $this->assertNotNull($uploads[0]['url']);
        $this->assertNotNull($uploads[0]['thumbnail_url']);
    }

    public function test_dcp_view_renders_the_latest_version(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $session = DiscoverySession::factory()->create(['business_owner_id' => $businessOwner->id]);

        DcpProfile::factory()->create(['discovery_session_id' => $session->id, 'version' => 1]);
        DcpProfile::factory()->create([
            'discovery_session_id' => $session->id,
            'version' => 2,
            'payload' => ['summary' => 'Latest version summary'],
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $dcp = $response->viewData('page')['props']['dcpProfile'];

        $this->assertSame(2, $dcp['version']);
        $this->assertSame('Latest version summary', $dcp['payload']['summary']);
    }

    public function test_spec_versions_are_listed_newest_first(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $session = DiscoverySession::factory()->create(['business_owner_id' => $businessOwner->id]);

        SpecDocument::factory()->create(['discovery_session_id' => $session->id, 'version' => 1]);
        SpecDocument::factory()->create(['discovery_session_id' => $session->id, 'version' => 2, 'change_summary' => 'Amended budget section']);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $specVersions = $response->viewData('page')['props']['specVersions'];

        $this->assertSame(2, $specVersions[0]['version']);
        $this->assertSame('Amended budget section', $specVersions[0]['change_summary']);
        $this->assertSame(1, $specVersions[1]['version']);
    }

    public function test_ai_usage_is_scoped_to_this_bo_and_does_not_leak_other_bos_calls(): void
    {
        $businessOwner = BusinessOwner::factory()->create();
        $otherBusinessOwner = BusinessOwner::factory()->create();

        AiCall::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'tool' => 'dcp.generate',
            'input_tokens' => 1000,
            'output_tokens' => 500,
            'cost_estimate' => 0.05,
        ]);
        AiCall::factory()->create([
            'business_owner_id' => $businessOwner->id,
            'tool' => 'dcp.generate',
            'input_tokens' => 200,
            'output_tokens' => 100,
            'cost_estimate' => 0.01,
        ]);
        AiCall::factory()->create([
            'business_owner_id' => $otherBusinessOwner->id,
            'tool' => 'spec.compile',
            'input_tokens' => 9000,
            'output_tokens' => 9000,
            'cost_estimate' => 5.0,
        ]);

        $response = $this->actingAs($this->admin())->get(route('admin.business-owners.show', $businessOwner));

        $aiUsage = $response->viewData('page')['props']['aiUsage'];

        $this->assertSame(1800, $aiUsage['total']['tokens']);
        $this->assertSame(2, $aiUsage['total']['calls']);
        $this->assertEqualsWithDelta(0.06, $aiUsage['total']['cost'], 0.0001);
        $this->assertCount(1, $aiUsage['by_tool']);
        $this->assertSame('dcp.generate', $aiUsage['by_tool'][0]['tool']);
        $this->assertSame(1800, $aiUsage['by_tool'][0]['tokens']);
    }

    public function test_token_budget_override_persists_and_is_read_by_the_budget_gate(): void
    {
        $businessOwner = BusinessOwner::factory()->create(['ai_token_cap' => null]);

        $response = $this->actingAs($this->admin())->put(route('admin.business-owners.update', $businessOwner), [
            'name' => $businessOwner->name,
            'company' => $businessOwner->company,
            'status' => $businessOwner->status->value,
            'ai_token_cap' => 5000,
        ]);

        $response->assertRedirect();
        $this->assertSame(5000, $businessOwner->fresh()->ai_token_cap);
    }
}
