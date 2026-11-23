<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Model\TimeSlot;

interface PersonalCalendar
{
    public function isBusy(string $practitionerId, TimeSlot $slot): bool;
}
