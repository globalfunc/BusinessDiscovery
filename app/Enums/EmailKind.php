<?php

namespace App\Enums;

/**
 * The three §6.5 on-click email generators. Values are stored on
 * email_drafts.kind and passed as the email.generate stage param (§7.2).
 */
enum EmailKind: string
{
    case WarmTease = 'warm_tease';
    case FollowUp = 'follow_up';
    case ProposalCover = 'proposal_cover';
}
