<?php

namespace Database\Seeders;

use App\Models\BriefExemplar;
use Illuminate\Database\Seeder;

/**
 * Hand-written gold pairs for the S5.6 advisory-brief exemplar library.
 * Each pair is a DCP-style context excerpt + the brief we would actually
 * want written for it: specific to the owner's situation, advisory (never
 * ready-to-publish copy or a step plan), and free of platitudes. Idempotent —
 * keyed on dcp_excerpt via updateOrCreate so re-seeding never duplicates.
 */
class BriefExemplarSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->exemplars() as $exemplar) {
            BriefExemplar::query()->updateOrCreate(
                ['dcp_excerpt' => $exemplar['dcp_excerpt']],
                $exemplar + ['active' => true, 'version' => 1],
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function exemplars(): array
    {
        return [
            [
                'context_tags' => ['barber', 'salon', 'hair', 'beauty', 'appointments', 'no_shows', 'фризьор', 'бръснар', 'салон'],
                'dcp_excerpt' => "Neighbourhood barber shop, one chair, loyal regulars. Digital maturity: low.\nPain points: no-shows eat 2-3 slots a week; new clients only come by word of mouth.\nGoals: fill quiet weekday mornings; look more professional online.\nPlatforms: personal Facebook profile only, posts rarely.",
                'exemplar_brief' => [
                    'paragraph' => 'Your regulars already do your marketing for you — the gap is that a new client who hears about you has nowhere to look you up. A simple, well-kept business page with your work, prices and hours would convert that word of mouth, and it matters more for you than posting often.',
                    'bullets' => [
                        'Before-and-after cuts of real clients will do more for a barber than any promotional graphic.',
                        'Your quiet weekday mornings are a targeting question, not a content one — think offers aimed at flexible-schedule locals.',
                        'No-shows drop when booking feels official; your online presence and reminders should reinforce each other.',
                    ],
                ],
                'quality_notes' => 'Ties every point to a stated pain (no-shows, word of mouth, quiet mornings). Advises on direction; never drafts a post or lists steps.',
            ],
            [
                'context_tags' => ['restaurant', 'cafe', 'food', 'menu', 'reservations', 'reviews', 'ресторант', 'кафе', 'храна'],
                'dcp_excerpt' => "Family restaurant, 40 seats, strong lunch trade, weak evenings. Digital maturity: medium.\nPain points: empty tables after 20:00; review pages unmanaged, one bad review sits on top.\nGoals: more dinner bookings; make the terrace season count.\nStrengths: recognisable house dishes, chef with a personal story.",
                'exemplar_brief' => [
                    'paragraph' => 'Your lunch crowd proves the food sells itself once people are inside — evenings are an awareness and occasion problem. Lean your presence on what only you have: the chef\'s story and the house dishes, framed around dinner occasions rather than generic food photography.',
                    'bullets' => [
                        'That unanswered bad review is costing you dinner bookings; a calm owner\'s reply is worth more than ten new posts.',
                        'Terrace season is a short window — treat it as a campaign with a start and end, not ongoing posting.',
                        'Dinner-specific content (evening light, plated mains, "book a table" framing) signals you\'re an evening venue too.',
                    ],
                ],
                'quality_notes' => 'Reframes the stated pain (weak evenings) as the content angle. Concrete to this restaurant; no captions, no schedule.',
            ],
            [
                'context_tags' => ['fitness', 'gym', 'studio', 'training', 'members', 'retention', 'фитнес', 'зала', 'тренировки'],
                'dcp_excerpt' => "Small group-training studio, 60 active members, high churn after month 3. Digital maturity: medium.\nPain points: members drop off once novelty fades; Instagram follows don't turn into trials.\nGoals: keep members past month 3; steady flow of trial sign-ups.\nPlatforms: active Instagram, dormant email list.",
                'exemplar_brief' => [
                    'paragraph' => 'Your follower-to-trial gap suggests your content shows the workouts but not the outcome — people follow for fitness inspiration without ever seeing a reason to walk in. Member progress and community proof are your strongest material, and they also speak to the month-3 drop-off, because members who feel seen stay.',
                    'bullets' => [
                        'Real member milestones (with permission) beat exercise demos for converting followers into trials.',
                        'Your dormant email list is a retention channel, not a marketing one — month-2 members are the audience to reach there.',
                        'A visible trial offer belongs wherever a new follower first lands, not buried in a highlight.',
                    ],
                ],
                'quality_notes' => 'Connects the two stated pains (churn, follows-not-trials) into one diagnosis. Advisory framing throughout.',
            ],
            [
                'context_tags' => ['shop', 'store', 'boutique', 'ecommerce', 'products', 'orders', 'магазин', 'бутик', 'продукти'],
                'dcp_excerpt' => "Handmade-goods boutique selling in person and via DMs. Digital maturity: low.\nPain points: taking orders over chat is chaotic; product photos undersell the craftsmanship.\nGoals: an orderly way to sell online; charge what the work is worth.\nStrengths: unique products, repeat buyers who gift to friends.",
                'exemplar_brief' => [
                    'paragraph' => 'You\'re underselling handmade work with the sales process of a lost-and-found — chat orders make your products feel improvised, and improvised feels cheap. The craftsmanship is your pricing power; your presence online should look as deliberate as the work itself before you worry about reach.',
                    'bullets' => [
                        'Photos that show process and detail (hands, materials, close-ups) justify handmade prices better than finished-product shots alone.',
                        'Your gifting buyers are a distinct audience — content framed around occasions reaches the friend who received one.',
                        'An orderly product catalogue, even a simple one, changes how buyers perceive your prices before you change the prices.',
                    ],
                ],
                'quality_notes' => 'Grounds pricing advice in the stated strength (craftsmanship) and pain (chat chaos). Direction only — no platform how-to, no step list.',
            ],
        ];
    }
}
