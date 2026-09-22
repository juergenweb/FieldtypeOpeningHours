<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\OpeningHours;

/**
 * Tests for sanitizeValidateValues(), the per-weekday time-pair cleanup/validation run on the
 * regular schedule after form submission: dropping incomplete pairs, validating start/finish
 * order, enforcing the configured max pairs per day, de-duplicating, and sorting.
 */
final class InputfieldOpeningHoursSanitizeValidateValuesTest extends InputfieldOpeningHoursTestCase {

    /** Every weekday defaulting to one empty (closed) pair, with the given day(s) overridden. */
    private function fullWeekTimes(array $overrides = []): array {
        $times = [];
        foreach (OpeningHours::getWeekdays() as $abbr => $names) {
            $times[$abbr] = $overrides[$abbr] ?? [['start' => '', 'finish' => '']];
        }
        return $times;
    }

    public function testKeepsASingleCompleteTimePairAsIs(): void {
        $inputfield = $this->makeInputfield();
        $times = $this->fullWeekTimes(['mo' => [['start' => '08:00', 'finish' => '12:00']]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertSame([['start' => '08:00', 'finish' => '12:00']], $result['mo']);
        $this->assertSame([['start' => '', 'finish' => '']], $result['tu']);
    }

    public function testResetsAnIncompletePairToEmptyAndWarns(): void {
        $inputfield = $this->makeInputfield();
        $times = $this->fullWeekTimes(['mo' => [['start' => '08:00', 'finish' => '']]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertSame([['start' => '', 'finish' => '']], $result['mo']);
        $this->assertNotEmpty($inputfield->warnings);
    }

    public function testErrorsWhenStartAndFinishAreEqualButKeepsThePair(): void {
        $inputfield = $this->makeInputfield();
        $times = $this->fullWeekTimes(['mo' => [['start' => '08:00', 'finish' => '08:00']]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertSame([['start' => '08:00', 'finish' => '08:00']], $result['mo']);
        $this->assertNotEmpty($inputfield->errors);
    }

    public function testWarnsButAllowsAnOvernightPairWhereFinishIsBeforeStart(): void {
        $inputfield = $this->makeInputfield();
        $times = $this->fullWeekTimes(['mo' => [['start' => '22:00', 'finish' => '02:00']]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertSame([['start' => '22:00', 'finish' => '02:00']], $result['mo']);
        $this->assertNotEmpty($inputfield->warnings);
        $this->assertSame([], $inputfield->errors);
    }

    public function testLimitsToTheConfiguredNumberOfTimesPerDay(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 1]);
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '13:00', 'finish' => '18:00'],
        ]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertSame([['start' => '08:00', 'finish' => '12:00']], $result['mo']);
    }

    public function testRemovesDuplicateTimePairsOnTheSameDay(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 3]);
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '13:00', 'finish' => '18:00'],
        ]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertCount(2, $result['mo']);
    }

    public function testSortsMultipleTimePairsByStartTimeAscending(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 2]);
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '13:00', 'finish' => '18:00'],
            ['start' => '08:00', 'finish' => '12:00'],
        ]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertSame('08:00', $result['mo'][0]['start']);
        $this->assertSame('13:00', $result['mo'][1]['start']);
    }

    public function testRemovesALeadingEmptyPairWhenARealPairFollows(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 2]);
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '', 'finish' => ''],
            ['start' => '08:00', 'finish' => '12:00'],
        ]]);

        $result = $inputfield->sanitizeValidateValues($times);

        $this->assertSame([['start' => '08:00', 'finish' => '12:00']], array_values($result['mo']));
    }

    public function testWarnsWhenTwoTimePairsOnTheSameDayOverlapButKeepsBothPairs(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 3]);
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '10:00', 'finish' => '14:00'],
        ]]);

        $result = $inputfield->sanitizeValidateValues($times);

        // the overlap is only flagged, never auto-corrected or dropped
        $this->assertSame([
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '10:00', 'finish' => '14:00'],
        ], array_values($result['mo']));
        $this->assertNotEmpty($inputfield->warnings);
        $this->assertStringContainsString('overlap', $inputfield->warnings[0]);
    }

    public function testDoesNotWarnAboutTwoTimePairsOnTheSameDayThatDontOverlap(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 3]);
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '13:00', 'finish' => '18:00'],
        ]]);

        $inputfield->sanitizeValidateValues($times);

        $this->assertSame([], $inputfield->warnings);
    }

    public function testDoesNotWarnAboutOverlapWhenABackToBackPairStartsExactlyWhereThePreviousOneFinishes(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 3]);
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '12:00', 'finish' => '18:00'],
        ]]);

        $inputfield->sanitizeValidateValues($times);

        $this->assertSame([], $inputfield->warnings);
    }

    public function testDoesNotMistakeAnOvernightPairForAnOverlapWithItself(): void {
        // an overnight pair (finish < start) is intentionally excluded from the overlap check
        // (see warnOnOverlappingTimePairs()) - only the existing "start is after finish"
        // warning should fire for it, not an "overlap" warning
        $inputfield = $this->makeInputfield();
        $times = $this->fullWeekTimes(['mo' => [
            ['start' => '22:00', 'finish' => '02:00'],
        ]]);

        $inputfield->sanitizeValidateValues($times);

        foreach ($inputfield->warnings as $warning) {
            $this->assertStringNotContainsString('overlap', $warning);
        }
    }
}
