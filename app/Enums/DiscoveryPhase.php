<?php

namespace App\Enums;

enum DiscoveryPhase: string
{
    case Phase0 = 'phase_0';
    case Phase1 = 'phase_1';
    case Phase2 = 'phase_2';
    case Phase3 = 'phase_3';
    case Phase4 = 'phase_4';
    case Phase5 = 'phase_5';
    case Phase6 = 'phase_6';
    case Review = 'review';

    /**
     * Ordered phase sequence for the phase machine (Greeting happens on the
     * referral landing page and isn't part of this server-side sequence).
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [
            self::Phase0,
            self::Phase1,
            self::Phase2,
            self::Phase3,
            self::Phase4,
            self::Phase5,
            self::Phase6,
            self::Review,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Phase0 => 'Intake',
            self::Phase1 => 'Business Profile',
            self::Phase2 => 'Services',
            self::Phase3 => 'Branding',
            self::Phase4 => 'Content & Social',
            self::Phase5 => 'Growth & Operations',
            self::Phase6 => 'Budget & Timeline',
            self::Review => 'Review',
        };
    }

    public function next(): ?self
    {
        $ordered = self::ordered();
        $index = array_search($this, $ordered, true);

        return $ordered[$index + 1] ?? null;
    }

    public function previous(): ?self
    {
        $ordered = self::ordered();
        $index = array_search($this, $ordered, true);

        return $index > 0 ? $ordered[$index - 1] : null;
    }
}
