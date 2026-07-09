<?php

namespace App\Services\Ai;

/**
 * The 8 ordered context blocks from Technical_Specification.md §7.3. Every
 * AiClient call is assembled from a subset of these, always in this order.
 * SpecDocument and Assessment are S4.5 additions for the admin-side
 * generators (§6.4/§6.5), which ground on compiled documents rather than raw
 * answers — slotted after StructuredAnswers in the order.
 */
enum ContextBlockType: string
{
    case SystemPolicy = 'system_policy';
    case AdminContext = 'admin_context';
    case Dcp = 'dcp';
    case TaxonomyCatalog = 'taxonomy_catalog';
    case StructuredAnswers = 'structured_answers';
    case SpecDocument = 'spec_document';
    case Assessment = 'assessment';
    case PhaseNotes = 'phase_notes';
    case SuggestionPresets = 'suggestion_presets';
    case BriefExemplars = 'brief_exemplars';
    case TaskInstruction = 'task_instruction';

    public function label(): string
    {
        return match ($this) {
            self::SystemPolicy => 'System policy',
            self::AdminContext => 'Admin-provided business context',
            self::Dcp => 'Discovery Context Profile',
            self::TaxonomyCatalog => 'Taxonomy & service catalog',
            self::StructuredAnswers => "BO's accumulated answers",
            self::SpecDocument => 'Compiled Business Specification (latest version)',
            self::Assessment => 'Internal technical assessment (admin-reviewed)',
            self::PhaseNotes => 'Phase-specific free-text notes',
            self::SuggestionPresets => 'Suggestion presets (inspiration)',
            self::BriefExemplars => 'Advisory brief exemplars (gold standard)',
            self::TaskInstruction => 'Task instruction & output schema',
        };
    }
}
