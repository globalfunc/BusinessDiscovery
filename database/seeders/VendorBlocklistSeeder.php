<?php

namespace Database\Seeders;

use App\Models\VendorBlocklistTerm;
use Illuminate\Database\Seeder;

/**
 * Seeds the §7.6.2 vendor blocklist with common booking / e-commerce /
 * email-CRM / social / website / payment brand names the AI might otherwise
 * reach for, plus a couple of regex examples. Admin-editable afterwards via the
 * blocklist editor (§6.6). Idempotent — keyed on `term` via updateOrCreate.
 */
class VendorBlocklistSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->terms() as [$term, $replacement, $category, $isRegex]) {
            VendorBlocklistTerm::query()->updateOrCreate(
                ['term' => $term],
                [
                    'replacement' => $replacement,
                    'category' => $category,
                    'is_regex' => $isRegex,
                    'active' => true,
                ],
            );
        }
    }

    /**
     * @return array<int, array{0: string, 1: string, 2: string, 3: bool}>
     *                                                                     term, replacement, category, is_regex
     */
    private function terms(): array
    {
        $booking = 'an online booking system';
        $store = 'an online store platform';
        $email = 'an email & marketing tool';
        $social = 'a social platform';
        $site = 'a website builder';
        $pay = 'a payment provider';
        $ads = 'a search & advertising platform';

        return [
            // Booking / scheduling
            ['Calendly', $booking, 'booking', false],
            ['Booksy', $booking, 'booking', false],
            ['Fresha', $booking, 'booking', false],
            ['SimplyBook', $booking, 'booking', false],
            ['Acuity', $booking, 'booking', false],
            ['Setmore', $booking, 'booking', false],

            // E-commerce
            ['Shopify', $store, 'ecommerce', false],
            ['WooCommerce', $store, 'ecommerce', false],
            ['BigCommerce', $store, 'ecommerce', false],
            ['Magento', $store, 'ecommerce', false],
            ['Etsy', $store, 'ecommerce', false],

            // Email / CRM / marketing
            ['Mailchimp', $email, 'email_crm', false],
            ['HubSpot', $email, 'email_crm', false],
            ['Klaviyo', $email, 'email_crm', false],
            ['Salesforce', $email, 'email_crm', false],
            ['Constant Contact', $email, 'email_crm', false],
            ['SendGrid', $email, 'email_crm', false],
            ['ActiveCampaign', $email, 'email_crm', false],

            // Social / content
            ['Instagram', $social, 'social', false],
            ['Facebook', $social, 'social', false],
            ['TikTok', $social, 'social', false],
            ['LinkedIn', $social, 'social', false],
            ['YouTube', $social, 'social', false],
            ['Hootsuite', $social, 'social', false],
            ['Buffer', $social, 'social', false],
            ['Canva', 'a design tool', 'content', false],

            // Website / hosting builders
            ['Wix', $site, 'website', false],
            ['Squarespace', $site, 'website', false],
            ['WordPress', $site, 'website', false],
            ['Webflow', $site, 'website', false],
            ['GoDaddy', $site, 'website', false],

            // Payments
            ['Stripe', $pay, 'payments', false],
            ['PayPal', $pay, 'payments', false],
            ['Square', $pay, 'payments', false],

            // Regex examples — brand families with sub-products.
            ['Google\s+(?:Ads|Analytics|My\s+Business|Business\s+Profile)', $ads, 'ads', true],
            ['Meta\s+(?:Ads|Business\s+Suite)', $ads, 'ads', true],
        ];
    }
}
