<?php

namespace Tests\Feature;

use App\Enums\AiCallStatus;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §6.7 usage explorer: filterable by period/BO/call-type, aggregating token
 * counts and cost estimates from the existing ai_calls log.
 */
class AiUsageExplorerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_index_renders_with_aggregates_for_the_current_month(): void
    {
        $bo = BusinessOwner::factory()->create();
        AiCall::factory()->create([
            'business_owner_id' => $bo->id,
            'tool' => 'suggest.services',
            'input_tokens' => 100,
            'output_tokens' => 50,
            'cost_estimate' => 0.01,
            'status' => AiCallStatus::Success,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.ai-usage.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.calls', 1)
                ->where('summary.input_tokens', 100)
                ->where('summary.output_tokens', 50)
            );
    }

    public function test_filtering_by_business_owner_excludes_other_bos(): void
    {
        $target = BusinessOwner::factory()->create();
        $other = BusinessOwner::factory()->create();

        AiCall::factory()->create(['business_owner_id' => $target->id, 'input_tokens' => 10, 'output_tokens' => 5]);
        AiCall::factory()->create(['business_owner_id' => $other->id, 'input_tokens' => 999, 'output_tokens' => 999]);

        $this->actingAs($this->admin())
            ->get(route('admin.ai-usage.index', ['business_owner_id' => $target->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.calls', 1)
                ->where('summary.input_tokens', 10)
            );
    }

    public function test_filtering_by_tool_excludes_other_call_types(): void
    {
        BusinessOwner::factory()->create();
        AiCall::factory()->create(['tool' => 'spec.compile', 'input_tokens' => 10, 'output_tokens' => 5]);
        AiCall::factory()->create(['tool' => 'dcp.generate', 'input_tokens' => 999, 'output_tokens' => 999]);

        $this->actingAs($this->admin())
            ->get(route('admin.ai-usage.index', ['tool' => 'spec.compile']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.calls', 1));
    }

    public function test_filtering_by_date_range_excludes_calls_outside_it(): void
    {
        $call = AiCall::factory()->create(['input_tokens' => 10, 'output_tokens' => 5]);
        $call->forceFill(['created_at' => now()->subMonths(3)])->save();

        $this->actingAs($this->admin())
            ->get(route('admin.ai-usage.index', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->endOfMonth()->toDateString(),
            ]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.calls', 0));
    }

    public function test_guest_cannot_view_usage(): void
    {
        $this->get(route('admin.ai-usage.index'))->assertRedirect(route('login'));
    }
}
