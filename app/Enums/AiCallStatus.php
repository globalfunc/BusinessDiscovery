<?php

namespace App\Enums;

enum AiCallStatus: string
{
    case Success = 'success';
    case Failed = 'failed';
    case BudgetExhausted = 'budget_exhausted';
    case RateLimited = 'rate_limited';
}
