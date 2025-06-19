<?php

namespace App\Enums;

enum PeriodStatusEnum: string
{
    case STATUS_DISPATCH = 'DISPATCH';
    case STATUS_INTERVIEW = 'INTERVIEW';
    case STATUS_PRESELECTION = 'PRESELECTION';
    case STATUS_SELECTION = 'SELECTION';
}
