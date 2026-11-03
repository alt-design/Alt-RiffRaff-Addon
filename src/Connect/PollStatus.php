<?php

declare(strict_types=1);

namespace AltDesign\RiffRaff\Connect;

enum PollStatus
{
    case Pending;
    case SlowDown;
    case Approved;
    case Denied;
    case Expired;
    case Unrecognised;
    case Unavailable;
    case Failed;
}
