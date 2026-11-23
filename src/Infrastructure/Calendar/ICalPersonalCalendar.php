<?php

declare(strict_types=1);

namespace App\Infrastructure\Calendar;

use App\Application\Service\PersonalCalendar;
use App\Domain\Exception\PersonalCalendarUnavailableException;
use App\Domain\Model\TimeSlot;
use DateTimeZone;
use RuntimeException;
use Sabre\VObject\Component\VCalendar;
use Sabre\VObject\FreeBusyGenerator;
use Sabre\VObject\Reader;
use Throwable;

final class ICalPersonalCalendar implements PersonalCalendar
{
    private const int MAX_BYTES = 2 * 1024 * 1024;

    public function __construct(private readonly ?string $configuration = null)
    {
    }

    public function isBusy(string $practitionerId, TimeSlot $slot): bool
    {
        try {
            $configuration = $this->configuration ?? getenv('PRACTITIONER_ICAL_CALENDARS');
            if ($configuration === false || $configuration === '') {
                return false;
            }

            $calendars = json_decode($configuration, false, 512, JSON_THROW_ON_ERROR);
            if (!$calendars instanceof \stdClass) {
                throw new RuntimeException('Expected a calendar configuration object.');
            }
            if (!property_exists($calendars, $practitionerId)) {
                return false;
            }

            $settings = $calendars->{$practitionerId};
            $calendar = Reader::read($this->read((string) ($settings->source ?? '')));
            if (!$calendar instanceof VCalendar || (string) $calendar->VERSION !== '2.0') {
                throw new RuntimeException('Expected an iCalendar document.');
            }

            $timezone = new DateTimeZone($settings->timezone ?? 'Europe/Paris');
            $generator = new FreeBusyGenerator($slot->startTime, $slot->endTime, $calendar, $timezone);
            foreach ($generator->getResult()->VFREEBUSY->select('FREEBUSY') as $period) {
                if (strtoupper((string) $period['FBTYPE']) !== 'FREE') {
                    return true;
                }
            }

            return false;
        } catch (Throwable) {
            throw new PersonalCalendarUnavailableException(
                'Le calendrier personnel du praticien est temporairement indisponible.'
            );
        }
    }

    private function read(string $source): string
    {
        $context = null;
        if (str_starts_with($source, 'https://')) {
            $context = stream_context_create(['http' => [
                'timeout' => 3,
                'follow_location' => 0,
            ]]);
        } elseif ($source === '' || !is_file($source)) {
            throw new RuntimeException('Expected an HTTPS feed or a local calendar file.');
        }

        $data = @file_get_contents($source, false, $context, 0, self::MAX_BYTES + 1);
        if ($data === false || strlen($data) > self::MAX_BYTES) {
            throw new RuntimeException('Calendar could not be read.');
        }

        return $data;
    }
}
