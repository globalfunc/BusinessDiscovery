<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VendorBlocklistTerm;
use App\Services\Ai\VendorFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin CRUD for the §6.6 vendor blocklist editor, plus the cache-invalidation
 * contract the runtime filter relies on.
 */
class VendorBlocklistAdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_index_lists_terms(): void
    {
        VendorBlocklistTerm::factory()->create(['term' => 'Calendly']);

        $this->actingAs($this->admin())
            ->get(route('admin.vendor-blocklist.index'))
            ->assertOk();
    }

    public function test_store_creates_a_term(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.vendor-blocklist.store'), [
                'term' => 'Mailchimp',
                'category' => 'email_crm',
                'replacement' => 'an email tool',
                'is_regex' => false,
                'active' => true,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('vendor_blocklist_terms', ['term' => 'Mailchimp', 'category' => 'email_crm']);
    }

    public function test_store_rejects_an_invalid_regex(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.vendor-blocklist.store'), [
                'term' => 'Google(Ads',   // unbalanced group
                'is_regex' => true,
            ])
            ->assertSessionHasErrors('term');

        $this->assertDatabaseCount('vendor_blocklist_terms', 0);
    }

    public function test_update_changes_a_term(): void
    {
        $term = VendorBlocklistTerm::factory()->create(['term' => 'Wix', 'active' => true]);

        $this->actingAs($this->admin())
            ->patch(route('admin.vendor-blocklist.update', $term), [
                'term' => 'Wix',
                'active' => false,
                'is_regex' => false,
            ])
            ->assertRedirect();

        $this->assertFalse($term->fresh()->active);
    }

    public function test_destroy_removes_a_term(): void
    {
        $term = VendorBlocklistTerm::factory()->create();

        $this->actingAs($this->admin())
            ->delete(route('admin.vendor-blocklist.destroy', $term))
            ->assertRedirect();

        $this->assertDatabaseMissing('vendor_blocklist_terms', ['id' => $term->id]);
    }

    public function test_guest_cannot_manage_the_blocklist(): void
    {
        $this->post(route('admin.vendor-blocklist.store'), ['term' => 'Stripe'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('vendor_blocklist_terms', 0);
    }

    public function test_writes_invalidate_the_filter_cache(): void
    {
        $filter = app(VendorFilter::class);
        $this->assertSame([], $filter->scan('Use Klaviyo for email.'));

        // A fresh admin write must make the new term visible to a fresh filter.
        VendorBlocklistTerm::factory()->create(['term' => 'Klaviyo']);

        $this->assertNotSame([], app(VendorFilter::class)->scan('Use Klaviyo for email.'));
    }
}
