<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\OpeningHours;

/**
 * Tests for isTimeValid(), formatTimestring() and formatDate().
 */
final class FormatAndValidationTest extends OpeningHoursTestCase {

    public function testIsTimeValidAcceptsWellFormedTimes(): void {
        $this->assertTrue(OpeningHours::isTimeValid('16:00'));
        $this->assertTrue(OpeningHours::isTimeValid('00:00'));
        $this->assertTrue(OpeningHours::isTimeValid('23:59'));
    }

    public function testIsTimeValidRejectsOutOfRangeOrMalformedTimes(): void {
        $this->assertFalse(OpeningHours::isTimeValid('25:00'));
        $this->assertFalse(OpeningHours::isTimeValid('not-a-time'));
        // no leading zero: round-tripping through the H:i format won't match the input
        $this->assertFalse(OpeningHours::isTimeValid('9:00'));
    }

    public function testFormatTimestringConvertsToRequestedFormat(): void {
        $this->assertSame('16:00', OpeningHours::formatTimestring('16:00', 'H:i'));
        $this->assertSame('04:30', OpeningHours::formatTimestring('04:30', 'H:i'));
    }

    public function testFormatTimestringUsesDefaultFormatWhenNoneGiven(): void {
        // OpeningHours::DEFAULTTIMEFORMAT is '%R', which the test's WireDateTime stub maps
        // to 'H:i', same as the module's own real-world default rendering.
        $this->assertSame('16:00', OpeningHours::formatTimestring('16:00'));
    }

    public function testFormatTimestringReturnsEmptyStringForEmptyInput(): void {
        $this->assertSame('', OpeningHours::formatTimestring(''));
    }

    public function testFormatTimestringNormalizesNullInputToAnEmptyString(): void {
        // $time is nullable (?string), but the declared return type is the non-nullable
        // "string" - formatTimestring() normalizes a null input to '' so it never has to
        // return null itself (and so every caller, which already treats '' as "no time set",
        // can keep working the same way regardless of which falsy value came in).
        $this->assertSame('', OpeningHours::formatTimestring(null));
    }

    public function testFormatDateFormatsAGivenTimestamp(): void {
        $timestamp = mktime(16, 30, 0, 6, 15, 2026);
        $this->assertSame('2026-06-15', OpeningHours::formatDate($timestamp, 'Y-m-d'));
        $this->assertSame('16:30', OpeningHours::formatDate($timestamp, 'H:i'));
    }
}
