<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\TaxonomyNiche;
use Illuminate\Database\Seeder;

class ServiceCatalogSeeder extends Seeder
{
    /**
     * Service catalog seed data per Technical_Specification.md §5.1.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function services(): array
    {
        return [
            [
                'key' => 'online-booking-system',
                'name' => ['en' => 'Online Booking System', 'bg' => 'Система за онлайн резервации'],
                'one_liner' => ['en' => 'Let clients book and manage appointments online, 24/7.', 'bg' => 'Позволете на клиентите да резервират и управляват часове онлайн, 24/7.'],
                'base_features' => ['Calendar & slots', 'Service/staff selection', 'Automatic reminders (email/SMS)', 'Deposits or full prepayment (optional)', 'Waitlist & cancellation auto-fill', 'Recurring appointments', 'No-show tracking'],
                'saas_eligible' => true,
                'tags' => ['booking', 'scheduling'],
                'price_min' => 300, 'price_max' => 900,
            ],
            [
                'key' => 'business-website',
                'name' => ['en' => 'Business Website', 'bg' => 'Уебсайт за бизнеса'],
                'one_liner' => ['en' => 'A custom, mobile-first website that presents your business professionally.', 'bg' => 'Персонализиран, mobile-first уебсайт, който представя бизнеса ви професионално.'],
                'base_features' => ['Custom design', 'Mobile-first', 'Services/pricing pages', 'Gallery', 'Contact & map', 'Inquiry forms', 'Multilingual (BG/EN)', 'Basic SEO structure'],
                'saas_eligible' => false,
                'tags' => ['website'],
                'price_min' => 500, 'price_max' => 2000,
            ],
            [
                'key' => 'online-store',
                'name' => ['en' => 'Online Store', 'bg' => 'Онлайн магазин'],
                'one_liner' => ['en' => 'Sell products online with a full cart-to-checkout experience.', 'bg' => 'Продавайте продукти онлайн с пълно преживяване от количка до плащане.'],
                'base_features' => ['Product catalog', 'Cart & checkout', 'Delivery options', 'Stock management', 'Order notifications', 'Discount codes'],
                'saas_eligible' => true,
                'tags' => ['ecommerce'],
                'price_min' => 800, 'price_max' => 2500,
            ],
            [
                'key' => 'digital-menu-price-list-qr',
                'name' => ['en' => 'Digital Menu / Price List (QR)', 'bg' => 'Дигитално меню / ценоразпис (QR)'],
                'one_liner' => ['en' => 'A QR-accessible menu or price list you can update instantly.', 'bg' => 'Меню или ценоразпис с достъп чрез QR код, който обновявате мигновено.'],
                'base_features' => ['QR access', 'Instant updates', 'Photos', 'Multilingual', 'Optional table ordering'],
                'saas_eligible' => true,
                'tags' => ['menu', 'qr'],
                'price_min' => 150, 'price_max' => 500,
            ],
            [
                'key' => 'gift-cards-vouchers',
                'name' => ['en' => 'Gift Cards & Vouchers', 'bg' => 'Подаръчни карти и ваучери'],
                'one_liner' => ['en' => 'Sell digital gift vouchers and track redemptions.', 'bg' => 'Продавайте дигитални подаръчни ваучери и следете тяхното използване.'],
                'base_features' => ['Digital voucher sales', 'Unique codes', 'Redemption tracking'],
                'saas_eligible' => true,
                'tags' => ['gift-cards'],
                'price_min' => 200, 'price_max' => 600,
            ],
            [
                'key' => 'loyalty-rewards',
                'name' => ['en' => 'Loyalty & Rewards', 'bg' => 'Лоялност и награди'],
                'one_liner' => ['en' => 'Keep clients coming back with points, tiers and reward notifications.', 'bg' => 'Задръжте клиентите си с точки, нива и автоматични известия за награди.'],
                'base_features' => ['Points/stamps', 'Tiers', 'Automated reward notifications'],
                'saas_eligible' => true,
                'tags' => ['loyalty'],
                'price_min' => 250, 'price_max' => 700,
            ],
            [
                'key' => 'reviews-reputation',
                'name' => ['en' => 'Reviews & Reputation', 'bg' => 'Отзиви и репутация'],
                'one_liner' => ['en' => 'Turn happy visits into public reviews and manage your reputation.', 'bg' => 'Превърнете доволните посещения в публични отзиви и управлявайте репутацията си.'],
                'base_features' => ['Post-visit review requests', 'Review monitoring dashboard', 'Response templates', 'Testimonial widget for the site'],
                'saas_eligible' => true,
                'tags' => ['reviews'],
                'price_min' => 200, 'price_max' => 550,
            ],
            [
                'key' => 'local-visibility-package',
                'name' => ['en' => 'Local Visibility Package', 'bg' => 'Пакет за локална видимост'],
                'one_liner' => ['en' => 'Be found by nearby customers searching for your business.', 'bg' => 'Бъдете открити от клиенти наблизо, търсещи вашия бизнес.'],
                'base_features' => ['Local search & map presence optimization', 'Business profile content', 'Photos & posts cadence', 'Category & keyword tuning'],
                'saas_eligible' => false,
                'tags' => ['local-seo'],
                'price_min' => 250, 'price_max' => 800,
            ],
            [
                'key' => 'email-marketing-newsletters',
                'name' => ['en' => 'Email Marketing & Newsletters', 'bg' => 'Имейл маркетинг и бюлетини'],
                'one_liner' => ['en' => 'Reach your audience with branded campaigns and automated sequences.', 'bg' => 'Достигнете до аудиторията си с брандирани кампании и автоматизирани поредици.'],
                'base_features' => ['Audience lists', 'Branded templates', 'Campaign scheduling', 'Automated sequences (welcome, win-back)', 'Performance stats'],
                'saas_eligible' => true,
                'tags' => ['email'],
                'price_min' => 200, 'price_max' => 650,
            ],
            [
                'key' => 'sms-messaging-campaigns',
                'name' => ['en' => 'SMS / Messaging Campaigns', 'bg' => 'SMS / съобщения кампании'],
                'one_liner' => ['en' => 'Short, direct promos and reminders sent straight to the phone.', 'bg' => 'Кратки, директни промоции и напомняния директно на телефона.'],
                'base_features' => ['Short promo blasts', 'Reminder flows', 'Opt-in management'],
                'saas_eligible' => true,
                'tags' => ['sms'],
                'price_min' => 150, 'price_max' => 450,
            ],
            [
                'key' => 'social-media-content-pack',
                'name' => ['en' => 'Social Media Content Pack', 'bg' => 'Пакет съдържание за социални мрежи'],
                'one_liner' => ['en' => 'Monthly branded content so your social presence stays active.', 'bg' => 'Месечно брандирано съдържание, за да остане активно вашето присъствие в социалните мрежи.'],
                'base_features' => ['Monthly branded posts', 'Stories/short-video scripts', 'Content calendar', 'Caption writing', 'Hashtag/local tagging strategy'],
                'saas_eligible' => true,
                'tags' => ['social-media', 'retainer'],
                'price_min' => 300, 'price_max' => 900,
            ],
            [
                'key' => 'customer-database-crm-lite',
                'name' => ['en' => 'Customer Database (CRM-lite)', 'bg' => 'База данни клиенти (лек CRM)'],
                'one_liner' => ['en' => 'Keep client history and notes organized in one place.', 'bg' => 'Поддържайте историята и бележките за клиентите организирани на едно място.'],
                'base_features' => ['Client profiles', 'Visit/purchase history', 'Notes', 'Segments', 'Birthday/anniversary automations'],
                'saas_eligible' => true,
                'tags' => ['crm'],
                'price_min' => 300, 'price_max' => 900,
            ],
            [
                'key' => 'invoicing-quotes',
                'name' => ['en' => 'Invoicing & Quotes', 'bg' => 'Фактуриране и оферти'],
                'one_liner' => ['en' => 'Send branded quotes and invoices, and track their status.', 'bg' => 'Изпращайте брандирани оферти и фактури и следете статуса им.'],
                'base_features' => ['Branded quotes/invoices', 'Statuses', 'Reminders', 'Export for accounting'],
                'saas_eligible' => false,
                'tags' => ['invoicing'],
                'price_min' => 250, 'price_max' => 700,
            ],
            [
                'key' => 'staff-scheduling',
                'name' => ['en' => 'Staff Scheduling', 'bg' => 'График на персонала'],
                'one_liner' => ['en' => 'Plan shifts and see staff availability at a glance.', 'bg' => 'Планирайте смени и вижте наличността на персонала с един поглед.'],
                'base_features' => ['Shift planning', 'Staff calendars', 'Availability', 'Workload view'],
                'saas_eligible' => true,
                'tags' => ['staff'],
                'price_min' => 250, 'price_max' => 700,
            ],
            [
                'key' => 'ai-customer-assistant-chatbot',
                'name' => ['en' => 'AI Customer Assistant (Chatbot)', 'bg' => 'AI асистент за клиенти (чатбот)'],
                'one_liner' => ['en' => 'Answer client questions instantly, any time, in any language.', 'bg' => 'Отговаряйте на въпросите на клиентите мигновено, по всяко време, на всеки език.'],
                'base_features' => ['24/7 answers from the business\'s own info', 'Booking hand-off', 'Multilingual', 'Escalation to owner'],
                'saas_eligible' => true,
                'tags' => ['ai', 'chatbot'],
                'price_min' => 400, 'price_max' => 1200,
            ],
            [
                'key' => 'ai-knowledge-document-assistant-rag',
                'name' => ['en' => 'AI Knowledge & Document Assistant (RAG)', 'bg' => 'AI асистент за документи и знание'],
                'one_liner' => ['en' => 'Turn your documents into an instant, searchable knowledge base.', 'bg' => 'Превърнете документите си в мигновена, търсеща се база от знания.'],
                'base_features' => ['Scan/ingest documents (price lists, policies, manuals)', 'Instant Q&A', 'Internal knowledge base', 'Insights'],
                'saas_eligible' => true,
                'tags' => ['ai', 'rag'],
                'price_min' => 500, 'price_max' => 1500,
            ],
            [
                'key' => 'onboarding-intake-assistant',
                'name' => ['en' => 'Onboarding / Intake Assistant', 'bg' => 'Асистент за прием на клиенти'],
                'one_liner' => ['en' => 'Guided digital intake forms that collect what you need upfront.', 'bg' => 'Дигитални формуляри за прием, които събират необходимата информация предварително.'],
                'base_features' => ['Guided digital intake forms (legal/medical/fitness questionnaires)', 'Document collection'],
                'saas_eligible' => true,
                'tags' => ['onboarding'],
                'price_min' => 300, 'price_max' => 900,
            ],
            [
                'key' => 'event-ticketing-registrations',
                'name' => ['en' => 'Event Ticketing & Registrations', 'bg' => 'Билети и регистрации за събития'],
                'one_liner' => ['en' => 'Sell tickets and manage RSVPs for your events.', 'bg' => 'Продавайте билети и управлявайте регистрации за вашите събития.'],
                'base_features' => ['Event pages', 'Ticket sales/RSVPs', 'Capacity', 'Check-in list'],
                'saas_eligible' => true,
                'tags' => ['events', 'ticketing'],
                'price_min' => 300, 'price_max' => 900,
            ],
            [
                'key' => 'membership-subscriptions-portal',
                'name' => ['en' => 'Membership & Subscriptions Portal', 'bg' => 'Портал за членства и абонаменти'],
                'one_liner' => ['en' => 'Manage recurring plans and give members exclusive access.', 'bg' => 'Управлявайте абонаментни планове и дайте на членовете ексклузивен достъп.'],
                'base_features' => ['Recurring plans', 'Member content/perks', 'Access management'],
                'saas_eligible' => true,
                'tags' => ['membership'],
                'price_min' => 400, 'price_max' => 1200,
            ],
            [
                'key' => 'waitlist-queue-management',
                'name' => ['en' => 'Waitlist & Queue Management', 'bg' => 'Управление на опашки и чакащи'],
                'one_liner' => ['en' => 'Keep walk-ins organized with a live, text-notified queue.', 'bg' => 'Организирайте клиентите без резервация с жива опашка и SMS известия.'],
                'base_features' => ['Live queue', 'SMS "you\'re next"', 'Walk-in handling'],
                'saas_eligible' => true,
                'tags' => ['queue'],
                'price_min' => 200, 'price_max' => 600,
            ],
            [
                'key' => 'lead-capture-follow-up',
                'name' => ['en' => 'Lead Capture & Follow-up', 'bg' => 'Събиране на запитвания и последващи действия'],
                'one_liner' => ['en' => 'Capture interested visitors and follow up with them automatically.', 'bg' => 'Улавяйте заинтересовани посетители и следвайте автоматично с тях.'],
                'base_features' => ['Landing pages', 'Forms', 'Instant follow-up sequences', 'Lead inbox'],
                'saas_eligible' => true,
                'tags' => ['leads'],
                'price_min' => 250, 'price_max' => 750,
            ],
            [
                'key' => 'analytics-insights-dashboard',
                'name' => ['en' => 'Analytics & Insights Dashboard', 'bg' => 'Табло за анализи'],
                'one_liner' => ['en' => 'See visits, bookings and campaign results in one simple view.', 'bg' => 'Вижте посещения, резервации и резултати от кампании на едно място.'],
                'base_features' => ['Visits', 'Bookings/sales', 'Campaign results', 'Simple monthly report'],
                'saas_eligible' => true,
                'tags' => ['analytics'],
                'price_min' => 250, 'price_max' => 700,
            ],
            [
                'key' => 'content-digitization',
                'name' => ['en' => 'Content Digitization', 'bg' => 'Дигитализация на съдържание'],
                'one_liner' => ['en' => 'Turn menus, price lists and catalogs into structured web content.', 'bg' => 'Превърнете менюта, ценоразписи и каталози в структурирано уеб съдържание.'],
                'base_features' => ['Menus', 'Price lists', 'Catalogs', 'PDFs → structured web content'],
                'saas_eligible' => false,
                'tags' => ['content'],
                'price_min' => 150, 'price_max' => 500,
            ],
            [
                'key' => 'photography-media-brief',
                'name' => ['en' => 'Photography / Media Brief', 'bg' => 'Бриф за фотография/видео'],
                'one_liner' => ['en' => 'Capture the requirements for a photo/video shoot done offline.', 'bg' => 'Съберете изискванията за фото/видео заснемане, изпълнявано офлайн.'],
                'base_features' => ['Requirements capture for photo/video shoot (executed offline)'],
                'saas_eligible' => false,
                'tags' => ['media'],
                'price_min' => 100, 'price_max' => 400,
            ],
        ];
    }

    /**
     * Per-niche recommended mapping excerpt per Technical_Specification.md §5.2.
     *
     * @return array<int, array{niches: array<int, string>, services: array<int, string>}>
     */
    public static function recommendedMappings(): array
    {
        return [
            [
                'niches' => ['Barbershop', 'Hair salon', 'Beauty studio / nails / lashes'],
                'services' => ['online-booking-system', 'reviews-reputation', 'loyalty-rewards', 'sms-messaging-campaigns', 'social-media-content-pack', 'gift-cards-vouchers', 'business-website'],
            ],
            [
                'niches' => ['Restaurant', 'Café / bakery'],
                'services' => ['digital-menu-price-list-qr', 'business-website', 'reviews-reputation', 'local-visibility-package', 'social-media-content-pack', 'event-ticketing-registrations', 'gift-cards-vouchers'],
            ],
            [
                'niches' => ['Car wash', 'Car repair / service'],
                'services' => ['online-booking-system', 'sms-messaging-campaigns', 'reviews-reputation', 'business-website', 'customer-database-crm-lite', 'invoicing-quotes'],
            ],
            [
                'niches' => ['Lawyer', 'Accountant', 'Consultant'],
                'services' => ['business-website', 'onboarding-intake-assistant', 'ai-knowledge-document-assistant-rag', 'invoicing-quotes', 'online-booking-system', 'email-marketing-newsletters'],
            ],
            [
                'niches' => ['Gym / fitness', 'Yoga / pilates studio'],
                'services' => ['online-booking-system', 'membership-subscriptions-portal', 'loyalty-rewards', 'social-media-content-pack', 'waitlist-queue-management'],
            ],
            [
                'niches' => ['Organized travel vendor', 'Outdoor activities / tours'],
                'services' => ['event-ticketing-registrations', 'business-website', 'online-store', 'social-media-content-pack', 'reviews-reputation', 'lead-capture-follow-up'],
            ],
            [
                'niches' => ['Local shop / boutique', 'Artist / artisan', 'Handmade goods / crafts'],
                'services' => ['online-store', 'social-media-content-pack', 'loyalty-rewards', 'email-marketing-newsletters', 'local-visibility-package'],
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::services() as $sort => $service) {
            Service::query()->updateOrCreate(
                ['key' => $service['key']],
                [
                    'name' => $service['name'],
                    'one_liner' => $service['one_liner'],
                    'base_features' => $service['base_features'],
                    'saas_eligible' => $service['saas_eligible'],
                    'tags' => $service['tags'],
                    'price_min' => $service['price_min'],
                    'price_max' => $service['price_max'],
                ],
            );
        }

        foreach (self::recommendedMappings() as $mapping) {
            $niches = TaxonomyNiche::query()->whereIn('name->en', $mapping['niches'])->get();
            $services = Service::query()->whereIn('key', $mapping['services'])->get();

            foreach ($niches as $niche) {
                foreach ($services as $service) {
                    $niche->services()->syncWithoutDetaching([$service->id => ['recommended' => true]]);
                }
            }
        }
    }
}
