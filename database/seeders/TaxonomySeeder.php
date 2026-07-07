<?php

namespace Database\Seeders;

use App\Models\TaxonomyCategory;
use App\Models\TaxonomyNiche;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    /**
     * Business taxonomy seed data per Technical_Specification.md §4.
     *
     * @return array<int, array{en: string, bg: string, niches: array<int, array{en: string, bg: string}>}>
     */
    public static function data(): array
    {
        return [
            [
                'en' => 'Personal Care & Wellness',
                'bg' => 'Грижа за тялото и уелнес',
                'niches' => [
                    ['en' => 'Barbershop', 'bg' => 'Бръснарница'],
                    ['en' => 'Hair salon', 'bg' => 'Фризьорски салон'],
                    ['en' => 'Beauty studio / nails / lashes', 'bg' => 'Козметично студио / нокти / мигли'],
                    ['en' => 'Spa & massage', 'bg' => 'СПА и масажи'],
                    ['en' => 'Yoga / pilates studio', 'bg' => 'Йога / пилатес студио'],
                    ['en' => 'Tattoo studio', 'bg' => 'Тату студио'],
                ],
            ],
            [
                'en' => 'Fitness & Sport',
                'bg' => 'Фитнес и спорт',
                'niches' => [
                    ['en' => 'Gym / fitness', 'bg' => 'Фитнес зала'],
                    ['en' => 'Personal trainer', 'bg' => 'Личен треньор'],
                    ['en' => 'Martial arts / dance school', 'bg' => 'Бойни изкуства / школа по танци'],
                    ['en' => 'Sports club', 'bg' => 'Спортен клуб'],
                ],
            ],
            [
                'en' => 'Food & Hospitality',
                'bg' => 'Храна и хотелиерство',
                'niches' => [
                    ['en' => 'Restaurant', 'bg' => 'Ресторант'],
                    ['en' => 'Café / bakery', 'bg' => 'Кафене / пекарна'],
                    ['en' => 'Fast food / food chain', 'bg' => 'Бързо хранене / верига'],
                    ['en' => 'Catering', 'bg' => 'Кетъринг'],
                    ['en' => 'Bar', 'bg' => 'Бар'],
                ],
            ],
            [
                'en' => 'Auto & Home Services',
                'bg' => 'Авто и домашни услуги',
                'niches' => [
                    ['en' => 'Car wash', 'bg' => 'Автомивка'],
                    ['en' => 'Car repair / service', 'bg' => 'Авторемонт / сервиз'],
                    ['en' => 'Detailing', 'bg' => 'Детайлинг'],
                    ['en' => 'Home repair / handyman', 'bg' => 'Домашни ремонти / майстор'],
                    ['en' => 'Cleaning services', 'bg' => 'Почистващи услуги'],
                    ['en' => 'HVAC / electrical / plumbing', 'bg' => 'Климатизация / електро / ВиК'],
                ],
            ],
            [
                'en' => 'Retail & E-commerce',
                'bg' => 'Търговия и е-търговия',
                'niches' => [
                    ['en' => 'Local shop / boutique', 'bg' => 'Местен магазин / бутик'],
                    ['en' => 'Specialty food store', 'bg' => 'Специализиран хранителен магазин'],
                    ['en' => 'Online store (existing/new)', 'bg' => 'Онлайн магазин (съществуващ/нов)'],
                    ['en' => 'Florist', 'bg' => 'Цветарски магазин'],
                ],
            ],
            [
                'en' => 'Professional Services',
                'bg' => 'Професионални услуги',
                'niches' => [
                    ['en' => 'Lawyer', 'bg' => 'Адвокат'],
                    ['en' => 'Accountant', 'bg' => 'Счетоводител'],
                    ['en' => 'Consultant', 'bg' => 'Консултант'],
                    ['en' => 'Real estate agent', 'bg' => 'Брокер на недвижими имоти'],
                    ['en' => 'Insurance broker', 'bg' => 'Застрахователен брокер'],
                    ['en' => 'Notary', 'bg' => 'Нотариус'],
                ],
            ],
            [
                'en' => 'Creative & Artisan',
                'bg' => 'Творчество и занаяти',
                'niches' => [
                    ['en' => 'Artist / artisan', 'bg' => 'Художник / занаятчия'],
                    ['en' => 'Photographer / videographer', 'bg' => 'Фотограф / видеограф'],
                    ['en' => 'Designer', 'bg' => 'Дизайнер'],
                    ['en' => 'Handmade goods / crafts', 'bg' => 'Ръчно изработени изделия'],
                ],
            ],
            [
                'en' => 'Travel & Experiences',
                'bg' => 'Пътувания и преживявания',
                'niches' => [
                    ['en' => 'Organized travel vendor', 'bg' => 'Организирани пътувания'],
                    ['en' => 'Outdoor activities / tours', 'bg' => 'Активности на открито / турове'],
                    ['en' => 'Guesthouse / small hotel', 'bg' => 'Къща за гости / малък хотел'],
                    ['en' => 'Event organizer', 'bg' => 'Организатор на събития'],
                ],
            ],
            [
                'en' => 'Education & Coaching',
                'bg' => 'Образование и коучинг',
                'niches' => [
                    ['en' => 'Language school', 'bg' => 'Езикова школа'],
                    ['en' => 'Tutoring', 'bg' => 'Частни уроци'],
                    ['en' => 'Courses & workshops', 'bg' => 'Курсове и уъркшопи'],
                    ['en' => 'Business/life coach', 'bg' => 'Бизнес/лайф коуч'],
                ],
            ],
            [
                'en' => 'Health Practices',
                'bg' => 'Здравни практики',
                'niches' => [
                    ['en' => 'Dentist', 'bg' => 'Зъболекар'],
                    ['en' => 'Physiotherapist', 'bg' => 'Физиотерапевт'],
                    ['en' => 'Psychologist / therapist', 'bg' => 'Психолог / терапевт'],
                    ['en' => 'Veterinary', 'bg' => 'Ветеринар'],
                    ['en' => 'Medical practice', 'bg' => 'Медицинска практика'],
                ],
            ],
            [
                'en' => 'Solo Professionals / Freelancers',
                'bg' => 'Самостоятелни професионалисти / фрийлансъри',
                'niches' => [
                    [
                        'en' => 'Generic solo professional (cross-cutting; niche described in free text)',
                        'bg' => 'Универсален самостоятелен професионалист (описва се в свободен текст)',
                    ],
                ],
            ],
        ];
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (self::data() as $categorySort => $category) {
            $categoryModel = TaxonomyCategory::query()->updateOrCreate(
                ['name->en' => $category['en']],
                ['name' => ['en' => $category['en'], 'bg' => $category['bg']], 'sort' => $categorySort],
            );

            foreach ($category['niches'] as $nicheSort => $niche) {
                TaxonomyNiche::query()->updateOrCreate(
                    ['taxonomy_category_id' => $categoryModel->id, 'name->en' => $niche['en']],
                    [
                        'taxonomy_category_id' => $categoryModel->id,
                        'name' => ['en' => $niche['en'], 'bg' => $niche['bg']],
                        'sort' => $nicheSort,
                    ],
                );
            }
        }
    }
}
