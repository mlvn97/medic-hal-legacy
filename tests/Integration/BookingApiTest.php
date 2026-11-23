<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Bootstrap;
use App\Infrastructure\Http\Request;
use App\Infrastructure\Http\Response;
use App\Infrastructure\Http\Router;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class BookingApiTest extends TestCase
{
    private Router $router;
    private string $date;
    private string|false $previousCalendars;
    private ?string $calendarFile = null;

    protected function setUp(): void
    {
        $this->previousCalendars = getenv('PRACTITIONER_ICAL_CALENDARS');
        putenv('PRACTITIONER_ICAL_CALENDARS');
        $this->router = Bootstrap::createContainer(':memory:')->get(Router::class);
        $this->date = (new DateTimeImmutable('next Monday'))->format('Y-m-d');
    }

    protected function tearDown(): void
    {
        putenv($this->previousCalendars === false
            ? 'PRACTITIONER_ICAL_CALENDARS'
            : 'PRACTITIONER_ICAL_CALENDARS=' . $this->previousCalendars);
        if ($this->calendarFile !== null) {
            unlink($this->calendarFile);
        }
    }

    public function testPersonalCalendarBlocksRecurringBusyTimeAndAllowsAdjacentBooking(): void
    {
        $practitioner = $this->createPractitioner('one');
        $patient = $this->createPatient('one');
        $previousMonday = (new DateTimeImmutable($this->date))->modify('-7 days')->format('Ymd');
        $day = str_replace('-', '', $this->date);
        $this->configureCalendar($practitioner, [
            'BEGIN:VEVENT',
            'UID:recurring@example.test',
            'DTSTAMP:20260101T000000Z',
            'DTSTART;TZID=Europe/Paris:' . $previousMonday . 'T090000',
            'DTEND;TZID=Europe/Paris:' . $previousMonday . 'T093000',
            'RRULE:FREQ=WEEKLY;COUNT=2',
            'END:VEVENT',
            'BEGIN:VEVENT',
            'UID:transparent@example.test',
            'DTSTAMP:20260101T000000Z',
            'DTSTART;TZID=Europe/Paris:' . $day . 'T093000',
            'DTEND;TZID=Europe/Paris:' . $day . 'T100000',
            'TRANSP:TRANSPARENT',
            'END:VEVENT',
        ]);

        self::assertSame(409, $this->book($practitioner, $patient, '09:00')->getStatusCode());
        $booked = $this->data($this->book($practitioner, $patient, '09:30'), 201);
        $path = '/api/appointments/' . $booked['id'];
        self::assertSame(409, $this->request('PATCH', $path . '/reschedule', $this->slot('09:00'))->getStatusCode());
        self::assertSame($booked, $this->data($this->request('GET', $path)));
    }

    public function testAllDayCalendarEventBlocksOnlyConfiguredPractitioner(): void
    {
        $practitioner = $this->createPractitioner('one');
        $otherPractitioner = $this->createPractitioner('two');
        $patient = $this->createPatient('one');
        $this->configureCalendar($practitioner, [
            'BEGIN:VEVENT',
            'UID:day-off@example.test',
            'DTSTAMP:20260101T000000Z',
            'DTSTART;VALUE=DATE:' . str_replace('-', '', $this->date),
            'END:VEVENT',
        ]);

        self::assertSame(409, $this->book($practitioner, $patient, '09:00')->getStatusCode());
        $this->data($this->book($otherPractitioner, $patient, '09:00'), 201);
    }

    public function testInvalidCalendarPreventsBookingAndReschedule(): void
    {
        $practitioner = $this->createPractitioner('one');
        $patient = $this->createPatient('one');
        $original = $this->data($this->book($practitioner, $patient, '09:00'), 201);
        $this->configureCalendar($practitioner, []);
        file_put_contents($this->calendarFile, 'Invalid calendar');

        self::assertSame(503, $this->book($practitioner, $patient, '09:30')->getStatusCode());
        $path = '/api/appointments/' . $original['id'];
        self::assertSame(503, $this->request('PATCH', $path . '/reschedule', $this->slot('09:30'))->getStatusCode());
        self::assertSame($original, $this->data($this->request('GET', $path)));
    }

    public function testBookingAndReschedulePersistTheSameAppointment(): void
    {
        $practitioner = $this->createPractitioner('one');
        $patient = $this->createPatient('one');
        $booked = $this->data($this->book($practitioner, $patient, '09:00'), 201);
        self::assertSame('CONFIRMED', $booked['status']);
        self::assertSame($patient, $booked['patient_id']);
        self::assertSame($practitioner, $booked['practitioner_id']);
        self::assertStringContainsString('T09:00:', $booked['start_time']);

        $path = '/api/appointments/' . $booked['id'];
        $updated = $this->data($this->request('PATCH', $path . '/reschedule', $this->slot('09:30')));
        self::assertSame($booked['id'], $updated['id']);
        self::assertStringContainsString('T09:30:', $updated['start_time']);
        self::assertSame($updated, $this->data($this->request('GET', $path)));

        $completed = $this->data($this->request('PATCH', $path . '/complete'));
        self::assertSame('COMPLETED', $completed['status']);
        self::assertSame($completed, $this->data($this->request('GET', $path)));
    }

    public function testBookingRejectsPractitionerAndPatientConflicts(): void
    {
        $practitioner = $this->createPractitioner('one');
        $otherPractitioner = $this->createPractitioner('two');
        $patient = $this->createPatient('one');
        $otherPatient = $this->createPatient('two');
        $booked = $this->data($this->book($practitioner, $patient, '09:00'), 201);

        self::assertSame(409, $this->book($practitioner, $otherPatient, '09:00')->getStatusCode());
        self::assertSame(409, $this->book($otherPractitioner, $patient, '09:00')->getStatusCode());
        self::assertSame(400, $this->book($practitioner, $patient, '13:00')->getStatusCode());

        $history = $this->data($this->request('GET', "/api/patients/$patient/appointments"));
        self::assertSame([$booked['id']], array_column($history, 'id'));
        $this->data($this->book($practitioner, $patient, '09:30'), 201);
    }

    public function testRejectedReschedulePreservesTheOriginalAppointment(): void
    {
        $practitioner = $this->createPractitioner('one');
        $patient = $this->createPatient('one');
        $otherPatient = $this->createPatient('two');
        $original = $this->data($this->book($practitioner, $patient, '09:00'), 201);
        $this->data($this->book($practitioner, $otherPatient, '09:30'), 201);

        $path = '/api/appointments/' . $original['id'];
        $result = $this->request('PATCH', $path . '/reschedule', $this->slot('09:30'));
        self::assertSame(409, $result->getStatusCode());
        self::assertSame($original, $this->data($this->request('GET', $path)));
    }

    public function testAvailabilityAndRecordDeletionKeepHealthCountsConsistent(): void
    {
        $practitioner = $this->createPractitioner('one');
        $patient = $this->createPatient('one');
        $health = $this->data($this->request('GET', '/api/health'));
        self::assertSame('healthy', $health['status']);
        self::assertSame(['practitioners' => 1, 'patients' => 1], $health['statistics']);

        $availabilityPath = "/api/practitioners/$practitioner/availabilities";
        $this->data($this->request('POST', $availabilityPath, ['items' => [[
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '11:00',
            'is_recurring' => true,
        ]]]));
        $availabilities = $this->data($this->request('GET', $availabilityPath));
        self::assertSame(['10:00'], array_column($availabilities, 'start_time'));
        self::assertSame(400, $this->book($practitioner, $patient, '09:00')->getStatusCode());

        self::assertSame(204, $this->request('DELETE', "/api/patients/$patient")->getStatusCode());
        self::assertSame(204, $this->request('DELETE', "/api/practitioners/$practitioner")->getStatusCode());
        self::assertSame(404, $this->request('GET', "/api/patients/$patient")->getStatusCode());
        self::assertSame(404, $this->request('GET', "/api/practitioners/$practitioner")->getStatusCode());
        $health = $this->data($this->request('GET', '/api/health'));
        self::assertSame(['practitioners' => 0, 'patients' => 0], $health['statistics']);
    }

    private function createPractitioner(string $suffix): string
    {
        return $this->data($this->request('POST', '/api/practitioners', [
            'first_name' => 'Camille',
            'last_name' => 'Martin',
            'email' => "practitioner-$suffix@example.test",
            'phone' => '0102030405',
            'speciality' => 'Médecin généraliste',
        ]), 201)['id'];
    }

    private function createPatient(string $suffix): string
    {
        return $this->data($this->request('POST', '/api/patients', [
            'first_name' => 'Dominique',
            'last_name' => 'Dupont',
            'email' => "patient-$suffix@example.test",
            'phone' => '0601020304',
        ]), 201)['id'];
    }

    private function book(string $practitioner, string $patient, string $time): Response
    {
        return $this->request('POST', '/api/appointments', $this->slot($time) + [
            'practitioner_id' => $practitioner,
            'patient_id' => $patient,
            'reason' => 'Consultation',
        ]);
    }

    private function slot(string $time): array
    {
        $start = new DateTimeImmutable($this->date . 'T' . $time . ':00', new \DateTimeZone('Europe/Paris'));

        return ['start_time' => $start->format(DATE_ATOM), 'duration_minutes' => 30];
    }

    private function configureCalendar(string $practitioner, array $events): void
    {
        $file = tempnam(sys_get_temp_dir(), 'medic-ical-');
        self::assertNotFalse($file);
        $this->calendarFile = $file;
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Medic HAL//Tests//EN', ...$events, 'END:VCALENDAR'];
        file_put_contents($file, implode("\r\n", $lines) . "\r\n");
        putenv('PRACTITIONER_ICAL_CALENDARS=' . json_encode([
            $practitioner => ['source' => $file, 'timezone' => 'Europe/Paris'],
        ], JSON_THROW_ON_ERROR));
    }

    private function request(string $method, string $path, array $body = []): Response
    {
        return $this->router->dispatch(new Request($method, $path, bodyParams: $body));
    }

    private function data(Response $response, int $status = 200): array
    {
        $payload = json_decode(json_encode($response->getData(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($status, $response->getStatusCode(), $payload['error']['message'] ?? '');
        self::assertTrue($payload['success']);

        return $payload['data'];
    }
}
