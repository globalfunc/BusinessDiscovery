<?php

namespace Tests\Feature;

use App\Enums\AiCallStatus;
use App\Enums\DiscoveryPhase;
use App\Models\AiCall;
use App\Models\BusinessOwner;
use App\Models\DiscoverySession;
use App\Models\ReferralToken;
use App\Models\Service;
use App\Models\SuggestionPreset;
use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use App\Services\Ai\AiCallRequest;
use App\Services\Ai\AiCallResult;
use App\Services\Ai\AiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionGenerationTest extends TestCase
{
    use RefreshDatabase;

    private BusinessOwner $businessOwner;

    private DiscoverySession $session;

    private TaxonomyNiche $niche;

    protected function setUp(): void
    {
        parent::setUp();

        $this->businessOwner = BusinessOwner::factory()->create(['admin_context' => 'Barber referred by a friend.']);
        $this->session = DiscoverySession::factory()->create([
            'business_owner_id' => $this->businessOwner->id,
            'current_phase' => DiscoveryPhase::Phase2,
        ]);

        $category = TaxonomyCategory::factory()->create();
        $this->niche = TaxonomyNiche::factory()->create(['taxonomy_category_id' => $category->id]);

        // Confirmed niche drives the catalog excerpt + preset lookup.
        $this->session->answers()->create([
            'phase' => DiscoveryPhase::Phase1->value,
            'field_key' => 'niche_id',
            'value' => $this->niche->id,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    private function validServicesPayload(array $cards = []): array
    {
        return ['suggestions' => $cards !== [] ? $cards : [
            $this->card(['related_catalog_key' => 'online_booking']),
            $this->card(['title' => 'Reviews & Reputation', 'related_catalog_key' => null]),
            $this->card(['title' => 'Loyalty & Rewards', 'related_catalog_key' => null]),
        ]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function card(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Smart Booking',
            'summary' => 'Let clients book 24/7.',
            'features' => ['SMS reminders', 'Deposit on booking', 'Waitlist auto-fill'],
            'rationale' => 'You mentioned losing time to no-shows.',
            'tags' => ['retention', 'time_saving'],
            'saas_eligible' => true,
            'related_catalog_key' => null,
        ], $overrides);
    }

    private function fakeAiClient(bool $successful, ?string $text, AiCallStatus $status = AiCallStatus::Success): void
    {
        $businessOwner = $this->businessOwner;

        $this->mock(AiClient::class, function ($mock) use ($successful, $text, $status, $businessOwner) {
            $mock->shouldReceive('call')
                ->andReturnUsing(fn (AiCallRequest $request) => new AiCallResult(
                    successful: $successful,
                    text: $text,
                    aiCall: AiCall::factory()->create([
                        'business_owner_id' => $businessOwner->id,
                        'tool' => $request->tool,
                        'status' => $status,
                    ]),
                ));
        });
    }

    private function postSuggest(string $tool)
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->postJson(route("discovery.suggest.{$tool}"));
    }

    public function test_valid_service_cards_are_returned(): void
    {
        $this->fakeAiClient(successful: true, text: json_encode($this->validServicesPayload()));

        $response = $this->postSuggest('services');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(3, 'suggestions');
        $response->assertJsonPath('suggestions.0.title', 'Smart Booking');
    }

    public function test_markdown_fenced_json_is_accepted(): void
    {
        $this->fakeAiClient(successful: true, text: "```json\n".json_encode($this->validServicesPayload())."\n```");

        $this->postSuggest('services')->assertJsonPath('status', 'ok');
    }

    public function test_hallucinated_catalog_key_is_nulled_so_accept_falls_back_to_custom(): void
    {
        // 'online_booking' matches no real Service here → must be nulled.
        $this->fakeAiClient(successful: true, text: json_encode($this->validServicesPayload()));

        $response = $this->postSuggest('services');

        $response->assertJsonPath('suggestions.0.related_catalog_key', null);
    }

    public function test_real_catalog_key_survives_sanitization(): void
    {
        Service::factory()->create(['key' => 'online_booking']);

        $this->fakeAiClient(successful: true, text: json_encode($this->validServicesPayload()));

        $this->postSuggest('services')->assertJsonPath('suggestions.0.related_catalog_key', 'online_booking');
    }

    public function test_accepting_a_suggestion_with_matching_key_links_the_catalog_service(): void
    {
        $service = Service::factory()->create(['key' => 'online_booking']);

        $response = $this->postSelectedService([
            'origin' => 'ai_suggestion',
            'related_catalog_key' => 'online_booking',
            'name' => 'Smart Booking',
            'description' => 'Let clients book 24/7.',
            'features' => ['SMS reminders', 'Deposit on booking', 'Waitlist auto-fill'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('selected_services', [
            'discovery_session_id' => $this->session->id,
            'service_id' => $service->id,
            'origin' => 'ai_suggestion',
            'custom' => false,
        ]);
    }

    public function test_accepting_a_suggestion_without_a_match_creates_a_custom_service(): void
    {
        $response = $this->postSelectedService([
            'origin' => 'ai_suggestion',
            'related_catalog_key' => null,
            'name' => 'Loyalty & Rewards',
            'description' => 'Keep regulars coming back.',
            'features' => ['Points', 'Tiers', 'Reward notifications'],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('selected_services', [
            'discovery_session_id' => $this->session->id,
            'service_id' => null,
            'origin' => 'ai_suggestion',
            'custom' => true,
            'name' => 'Loyalty & Rewards',
        ]);
    }

    public function test_failed_call_falls_back_to_presets_without_blocking(): void
    {
        SuggestionPreset::factory()->create([
            'taxonomy_niche_id' => $this->niche->id,
            'phase' => DiscoveryPhase::Phase2->value,
            'cards' => [$this->card(['title' => 'Preset Booking'])],
        ]);

        $this->fakeAiClient(successful: false, text: null, status: AiCallStatus::Failed);

        $response = $this->postSuggest('services');

        $response->assertOk();
        $response->assertJsonPath('status', 'unavailable');
        $response->assertJsonPath('suggestions.0.title', 'Preset Booking');
    }

    public function test_invalid_schema_falls_back_to_presets(): void
    {
        SuggestionPreset::factory()->create([
            'taxonomy_niche_id' => $this->niche->id,
            'phase' => DiscoveryPhase::Phase2->value,
            'cards' => [$this->card(['title' => 'Preset Booking'])],
        ]);

        // Only 2 cards → violates the 3–5 rule.
        $this->fakeAiClient(successful: true, text: json_encode(['suggestions' => [$this->card(), $this->card()]]));

        $response = $this->postSuggest('services');

        $response->assertJsonPath('status', 'unavailable');
        $response->assertJsonPath('suggestions.0.title', 'Preset Booking');
    }

    public function test_unavailable_with_no_presets_returns_empty_but_does_not_block(): void
    {
        $this->fakeAiClient(successful: false, text: null, status: AiCallStatus::Failed);

        $response = $this->postSuggest('services');

        $response->assertOk();
        $response->assertJsonPath('status', 'unavailable');
        $response->assertJsonCount(0, 'suggestions');
    }

    public function test_branding_endpoint_returns_cards_and_ignores_missing_catalog_key(): void
    {
        $cards = [
            $this->brandingCard(['title' => 'Warm Neighborhood']),
            $this->brandingCard(['title' => 'Modern Minimal']),
            $this->brandingCard(['title' => 'Bold & Vibrant']),
        ];

        $this->fakeAiClient(successful: true, text: json_encode(['suggestions' => $cards]));

        $response = $this->postSuggest('branding');

        $response->assertOk();
        $response->assertJsonPath('status', 'ok');
        $response->assertJsonCount(3, 'suggestions');
    }

    public function test_branding_failure_falls_back_to_branding_presets(): void
    {
        SuggestionPreset::factory()->create([
            'taxonomy_niche_id' => $this->niche->id,
            'phase' => DiscoveryPhase::Phase3->value,
            'cards' => [$this->brandingCard(['title' => 'Preset Direction'])],
        ]);

        $this->fakeAiClient(successful: false, text: null, status: AiCallStatus::Failed);

        $response = $this->postSuggest('branding');

        $response->assertJsonPath('status', 'unavailable');
        $response->assertJsonPath('suggestions.0.title', 'Preset Direction');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function brandingCard(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Warm Neighborhood',
            'summary' => 'Cozy, welcoming, community-first.',
            'features' => ['Dark wood & brass palette', 'Bold display type', 'Photo-forward gallery'],
            'rationale' => 'Your prime location and loyal regulars suit a warm, local feel.',
            'tags' => ['warm', 'premium'],
            'saas_eligible' => false,
            'related_catalog_key' => null,
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function postSelectedService(array $payload)
    {
        $referralToken = ReferralToken::factory()->create(['business_owner_id' => $this->businessOwner->id]);

        return $this->withSession([
            'referral_token_id' => $referralToken->id,
            'business_owner_id' => $this->businessOwner->id,
            'referral_confirmed_'.$referralToken->id => true,
        ])->postJson(route('discovery.services.store'), $payload);
    }
}
