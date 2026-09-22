<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use DateTime;

/**
 * Tests for getStatusForDate(), which combines the regular weekly schedule with any
 * applicable exception into a single "effective status" for a given date.
 */
final class StatusForDateTest extends OpeningHoursTestCase {

    public function testFallsBackToTheRegularWeeklyScheduleWhenNoExceptionApplies(): void {
        $openingHours = $this->makeOpeningHours(['times' => [
            'mo' => [self::timePair('08:00', '12:00')],
        ]]);

        // 2026-01-12 is a Monday
        $status = $openingHours->getStatusForDate(new DateTime('2026-01-12'));

        $this->assertFalse($status['closed']);
        $this->assertFalse($status['exception']);
        $this->assertNull($status['label']);
        $this->assertSame('08:00', $status['start']);
        $this->assertSame('12:00', $status['finish']);
    }

    public function testRegularScheduleTreatsAnEmptyDayAsClosed(): void {
        $openingHours = $this->makeOpeningHours(['times' => [
            'tu' => [self::timePair()],
        ]]);

        // 2026-01-13 is a Tuesday
        $status = $openingHours->getStatusForDate(new DateTime('2026-01-13'));

        $this->assertTrue($status['closed']);
        $this->assertFalse($status['exception']);
    }

    public function testAClosedExceptionOverridesTheRegularSchedule(): void {
        $openingHours = $this->makeOpeningHours([
            'times' => ['th' => [self::timePair('08:00', '12:00')]],
            'exceptions' => [[
                'label' => 'Christmas',
                'startDate' => '2026-12-24',
                'endDate' => '2026-12-24',
                'recurring' => false,
                'closed' => true,
                'times' => [],
            ]],
        ]);

        // 2026-12-24 is a Thursday, normally open 08:00-12:00
        $status = $openingHours->getStatusForDate(new DateTime('2026-12-24'));

        $this->assertTrue($status['closed']);
        $this->assertTrue($status['exception']);
        $this->assertSame('Christmas', $status['label']);
        $this->assertSame([], $status['times']);
    }

    public function testAnOpenExceptionWithItsOwnHoursOverridesTheRegularSchedule(): void {
        $openingHours = $this->makeOpeningHours([
            'times' => ['fr' => [self::timePair('08:00', '18:00')]],
            'exceptions' => [[
                'label' => 'Black Friday',
                'startDate' => '2026-11-27',
                'endDate' => '2026-11-27',
                'recurring' => false,
                'closed' => false,
                'times' => [self::timePair('06:00', '22:00')],
            ]],
        ]);

        $status = $openingHours->getStatusForDate(new DateTime('2026-11-27'));

        $this->assertFalse($status['closed']);
        $this->assertTrue($status['exception']);
        $this->assertSame('06:00', $status['start']);
        $this->assertSame('22:00', $status['finish']);
    }
}
