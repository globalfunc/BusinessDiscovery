<?php

namespace App\Enums;

enum ReferralTokenState: string
{
    case Created = 'created';
    case Sent = 'sent';
    case Visited = 'visited';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
