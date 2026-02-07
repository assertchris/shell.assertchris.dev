<?php

namespace App\Enums;

enum DashboardMessagePriority: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
