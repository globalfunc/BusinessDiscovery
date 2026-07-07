<?php

namespace App\Enums;

enum PipelineStage: string
{
    case Prospect = 'prospect';
    case ReferralSent = 'referral_sent';
    case LinkVisited = 'link_visited';
    case DiscoveryInProgress = 'discovery_in_progress';
    case DiscoveryComplete = 'discovery_complete';
    case ProposalSent = 'proposal_sent';
    case Negotiation = 'negotiation';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::Prospect => 'Prospect',
            self::ReferralSent => 'Referral sent',
            self::LinkVisited => 'Link visited',
            self::DiscoveryInProgress => 'Discovery in progress',
            self::DiscoveryComplete => 'Discovery complete',
            self::ProposalSent => 'Proposal sent',
            self::Negotiation => 'Negotiation',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }
}
