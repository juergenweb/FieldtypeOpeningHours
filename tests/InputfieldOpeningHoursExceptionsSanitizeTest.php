<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\TestLanguageStub;
use ProcessWire\TestWireContainer;

/**
 * Tests for sanitizeExceptionsInput() and sanitizeExceptionDate() - cleanup/validation of the
 * submitted exceptions (holidays/company vacation) JSON before it is stored.
 */
final class InputfieldOpeningHoursExceptionsSanitizeTest extends InputfieldOpeningHoursTestCase {

    private function sanitize(\ProcessWire\InputfieldOpeningHours $inputfield, array $exceptions): array {
        return self::callMethod($inputfield, 'sanitizeExceptionsInput', [json_encode($exceptions)]);
    }

    public function testReturnsAnEmptyArrayForANullOrEmptyRawValue(): void {
        $inputfield = $this->makeInputfield();

        $this->assertSame([], self::callMethod($inputfield, 'sanitizeExceptionsInput', [null]));
        $this->assertSame([], self::callMethod($inputfield, 'sanitizeExceptionsInput', ['']));
    }

    public function testReturnsAnEmptyArrayWhenTheRawValueIsNotValidJsonOrNotAnArray(): void {
        $inputfield = $this->makeInputfield();

        $this->assertSame([], self::callMethod($inputfield, 'sanitizeExceptionsInput', ['not json']));
        $this->assertSame([], self::callMethod($inputfield, 'sanitizeExceptionsInput', ['"a string"']));
    }

    public function testSkipsEntriesThatAreNotArraysOrThatHaveNoStartDate(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            'not an array',
            ['label' => 'No start date', 'closed' => true],
            ['label' => '', 'startDate' => '', 'closed' => true],
        ]);

        $this->assertSame([], $result);
    }

    public function testSkipsAnEntryWithAnInvalidStartDate(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => 'Bad date', 'startDate' => '2026-13-40', 'closed' => true],
        ]);

        $this->assertSame([], $result);
    }

    public function testKeepsAClosedAllDayExceptionWithAValidSingleDate(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => 'Christmas', 'startDate' => '2026-12-24', 'closed' => true, 'recurring' => true],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame('2026-12-24', $result[0]['startDate']);
        // no endDate given - defaults to the same as startDate
        $this->assertSame('2026-12-24', $result[0]['endDate']);
        $this->assertTrue($result[0]['closed']);
        $this->assertTrue($result[0]['recurring']);
        $this->assertSame('Christmas', $result[0]['label']);
        $this->assertSame([], $inputfield->errors);
    }

    public function testResetsAnEndDateBeforeTheStartDateAndErrors(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => 'X', 'startDate' => '2026-06-10', 'endDate' => '2026-06-01', 'closed' => true],
        ]);

        $this->assertSame('2026-06-10', $result[0]['endDate']);
        $this->assertNotEmpty($inputfield->errors);
    }

    public function testFallsBackToTheStartDateWhenTheGivenEndDateIsInvalid(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => 'X', 'startDate' => '2026-06-10', 'endDate' => 'not a date', 'closed' => true],
        ]);

        $this->assertSame('2026-06-10', $result[0]['endDate']);
    }

    public function testSanitizesASingleLanguageLabelAsPlainText(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => '<b>Summer</b> break', 'startDate' => '2026-08-01', 'closed' => true],
        ]);

        $this->assertSame('Summer break', $result[0]['label']);
    }

    public function testSanitizesAMultiLanguageLabelPerLanguage(): void {
        $inputfield = $this->makeInputfield();
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);

        $result = $this->sanitize($inputfield, [
            ['label' => ['default' => '<i>Holiday</i>', '5' => '<i>Feiertag</i>'], 'startDate' => '2026-08-01', 'closed' => true],
        ]);

        $this->assertSame(['default' => 'Holiday', '5' => 'Feiertag'], $result[0]['label']);
    }

    public function testDropsMultiLanguageLabelKeysThatAreNotAnActuallyConfiguredLanguage(): void {
        // covers both a plain unrecognized key and a JS-prototype-pollution-style key
        // ("__proto__") that must never be passed through to stored/emitted data - see
        // getAllowedExceptionLabelLanguageKeys()
        $inputfield = $this->makeInputfield();
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);

        $result = $this->sanitize($inputfield, [
            ['label' => ['default' => 'Holiday', '5' => 'Feiertag', '99' => 'Unknown', '__proto__' => 'Evil'], 'startDate' => '2026-08-01', 'closed' => true],
        ]);

        $this->assertSame(['default' => 'Holiday', '5' => 'Feiertag'], $result[0]['label']);
    }

    public function testDropsEveryMultiLanguageLabelKeyOnASingleLanguageSiteExceptDefault(): void {
        $inputfield = $this->makeInputfield();
        // no TestWireContainer::setLanguages() call - single-language site

        $result = $this->sanitize($inputfield, [
            ['label' => ['default' => 'Holiday', '5' => 'Feiertag'], 'startDate' => '2026-08-01', 'closed' => true],
        ]);

        $this->assertSame(['default' => 'Holiday'], $result[0]['label']);
    }

    public function testKeepsAValidCompleteTimePairOnAnOpenException(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => '', 'startDate' => '2026-08-01', 'closed' => false, 'times' => [
                ['start' => '08:00', 'finish' => '12:00'],
            ]],
        ]);

        $this->assertSame([['start' => '08:00', 'finish' => '12:00']], $result[0]['times']);
        $this->assertSame([], $inputfield->errors);
    }

    public function testDropsATimePairWhoseFinishIsBeforeItsStartAndErrors(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => '', 'startDate' => '2026-08-01', 'closed' => false, 'times' => [
                ['start' => '12:00', 'finish' => '08:00'],
            ]],
        ]);

        $this->assertSame([], $result[0]['times']);
        $this->assertNotEmpty($inputfield->errors);
    }

    public function testDropsAOneSidedIncompleteTimePairAndErrors(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => '', 'startDate' => '2026-08-01', 'closed' => false, 'times' => [
                ['start' => '08:00', 'finish' => ''],
            ]],
        ]);

        $this->assertSame([], $result[0]['times']);
        $this->assertNotEmpty($inputfield->errors);
    }

    public function testDropsAnInvalidTimeStringWithinAPairTreatingItAsMissing(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => '', 'startDate' => '2026-08-01', 'closed' => false, 'times' => [
                ['start' => 'not a time', 'finish' => '12:00'],
            ]],
        ]);

        // the invalid start becomes '', leaving only 'finish' set - one-sided, so dropped+error
        $this->assertSame([], $result[0]['times']);
        $this->assertNotEmpty($inputfield->errors);
    }

    public function testErrorsWhenAnOpenExceptionHasNoValidTimeRangeAtAll(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => '', 'startDate' => '2026-08-01', 'closed' => false, 'times' => []],
        ]);

        $this->assertSame([], $result[0]['times']);
        $this->assertNotEmpty($inputfield->errors);
    }

    public function testDoesNotRequireHoursWhenTheExceptionIsClosedAllDay(): void {
        $inputfield = $this->makeInputfield();
        $result = $this->sanitize($inputfield, [
            ['label' => '', 'startDate' => '2026-08-01', 'closed' => true, 'times' => []],
        ]);

        $this->assertSame([], $result[0]['times']);
        $this->assertSame([], $inputfield->errors);
    }

    public function testLimitsTimesToTheConfiguredNumberOfTimesPerException(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 1]);
        $result = $this->sanitize($inputfield, [
            ['label' => '', 'startDate' => '2026-08-01', 'closed' => false, 'times' => [
                ['start' => '08:00', 'finish' => '12:00'],
                ['start' => '13:00', 'finish' => '18:00'],
            ]],
        ]);

        $this->assertSame([['start' => '08:00', 'finish' => '12:00']], $result[0]['times']);
    }

    public function testSanitizeExceptionDateAcceptsAWellFormedDate(): void {
        $inputfield = $this->makeInputfield();
        $this->assertSame('2026-08-01', self::callMethod($inputfield, 'sanitizeExceptionDate', ['2026-08-01']));
    }

    public function testSanitizeExceptionDateRejectsAnOverflowingCalendarDate(): void {
        // DateTime::createFromFormat('Y-m-d', ...) silently rolls 2026-02-30 over into
        // 2026-03-02, so comparing the reformatted string back against the input is what
        // catches it - guard this behaviour with a regression test.
        $inputfield = $this->makeInputfield();
        $this->assertSame('', self::callMethod($inputfield, 'sanitizeExceptionDate', ['2026-02-30']));
    }

    public function testSanitizeExceptionDateRejectsAMalformedString(): void {
        $inputfield = $this->makeInputfield();
        $this->assertSame('', self::callMethod($inputfield, 'sanitizeExceptionDate', ['not a date']));
        $this->assertSame('', self::callMethod($inputfield, 'sanitizeExceptionDate', ['']));
    }

    // ---- warnOnOverlappingExceptionDateRanges() / exceptionDateRangesOverlap() ----
    // getExceptionForDate() only ever returns the FIRST matching exception for a date, so
    // two overlapping exceptions silently mean the second one is never applied on the
    // shared dates - these tests only check that the warning fires (or doesn't), not that
    // anything gets dropped or reordered (nothing does).

    public function testWarnsWhenTwoNonRecurringExceptionsOverlap(): void {
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'Vacation', 'startDate' => '2026-08-01', 'endDate' => '2026-08-10', 'closed' => true],
            ['label' => 'Renovation', 'startDate' => '2026-08-05', 'endDate' => '2026-08-15', 'closed' => true],
        ]);

        $this->assertNotEmpty($inputfield->warnings);
        $this->assertStringContainsString('overlap', $inputfield->warnings[0]);
    }

    public function testDoesNotWarnWhenTwoNonRecurringExceptionsDontOverlap(): void {
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'Vacation', 'startDate' => '2026-08-01', 'endDate' => '2026-08-10', 'closed' => true],
            ['label' => 'Renovation', 'startDate' => '2026-08-11', 'endDate' => '2026-08-15', 'closed' => true],
        ]);

        $this->assertSame([], $inputfield->warnings);
    }

    public function testDoesNotWarnWhenTwoOneOffExceptionsShareTheSameDateInDifferentYears(): void {
        // same month/day, but different (real, non-recurring) years - never actually
        // coincide, so this must NOT be flagged
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'Christmas 2026', 'startDate' => '2026-12-24', 'endDate' => '2026-12-24', 'closed' => true],
            ['label' => 'Christmas 2027', 'startDate' => '2027-12-24', 'endDate' => '2027-12-24', 'closed' => true],
        ]);

        $this->assertSame([], $inputfield->warnings);
    }

    public function testWarnsWhenTwoRecurringExceptionsOverlapOnMonthAndDay(): void {
        // different (arbitrary) years in startDate/endDate, but recurring - only the
        // month/day portion matters and it overlaps every year
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'Christmas', 'startDate' => '2020-12-24', 'endDate' => '2020-12-26', 'closed' => true, 'recurring' => true],
            ['label' => 'Company vacation', 'startDate' => '2026-12-25', 'endDate' => '2027-01-02', 'closed' => true, 'recurring' => true],
        ]);

        $this->assertNotEmpty($inputfield->warnings);
        $this->assertStringContainsString('overlap', $inputfield->warnings[0]);
    }

    public function testWarnsWhenARecurringExceptionsWraparoundRangeOverlapsAnother(): void {
        // recurring range wraps across new year's eve (28.12.-03.01., stored using
        // successive years so "endDate" still sorts after "startDate" as required by
        // sanitizeExceptionsInput()) - must still be detected as overlapping a plain
        // "01.01.-01.01." recurring exception
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'Winter break', 'startDate' => '2026-12-28', 'endDate' => '2027-01-03', 'closed' => true, 'recurring' => true],
            ['label' => 'New Year', 'startDate' => '2026-01-01', 'endDate' => '2026-01-01', 'closed' => true, 'recurring' => true],
        ]);

        $this->assertNotEmpty($inputfield->warnings);
    }

    public function testDoesNotWarnWhenTwoRecurringExceptionsDontOverlap(): void {
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'Christmas', 'startDate' => '2020-12-24', 'endDate' => '2020-12-26', 'closed' => true, 'recurring' => true],
            ['label' => 'New Year', 'startDate' => '2026-01-01', 'endDate' => '2026-01-01', 'closed' => true, 'recurring' => true],
        ]);

        $this->assertSame([], $inputfield->warnings);
    }

    public function testWarnsWhenAOneOffExceptionFallsInsideARecurringExceptionsRange(): void {
        // a mixed comparison: the recurring range genuinely covers this one-off date in
        // its particular year too, so this is a real conflict, not a false positive
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'Christmas', 'startDate' => '2020-12-24', 'endDate' => '2020-12-26', 'closed' => true, 'recurring' => true],
            ['label' => 'Special closing', 'startDate' => '2026-12-25', 'endDate' => '2026-12-25', 'closed' => true],
        ]);

        $this->assertNotEmpty($inputfield->warnings);
    }

    public function testWarnsAboutEveryOverlappingPairNotJustAdjacentOnesInSubmissionOrder(): void {
        // three exceptions where #1 and #3 overlap but neither is adjacent in the
        // submitted order - a naive "only check neighbours" approach would miss this
        $inputfield = $this->makeInputfield();
        $this->sanitize($inputfield, [
            ['label' => 'A', 'startDate' => '2026-08-01', 'endDate' => '2026-08-10', 'closed' => true],
            ['label' => 'B', 'startDate' => '2026-09-01', 'endDate' => '2026-09-10', 'closed' => true],
            ['label' => 'C', 'startDate' => '2026-08-05', 'endDate' => '2026-08-06', 'closed' => true],
        ]);

        $this->assertCount(1, $inputfield->warnings);
        $this->assertStringContainsString('#1', $inputfield->warnings[0]);
        $this->assertStringContainsString('#3', $inputfield->warnings[0]);
    }
}
