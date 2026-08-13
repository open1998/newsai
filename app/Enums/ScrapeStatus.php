<?php

namespace App\Enums;

enum ScrapeStatus: string
{
    case Pending = 'pending';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
