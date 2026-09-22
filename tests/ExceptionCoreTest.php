<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use DateTime;
use ProcessWire\OpeningHours;
use ProcessWire\TestLanguageStub;
use ProcessWire\TestWireContainer;

/**
 * Tests for the core exception building blocks: normalizeExceptionTimes(),
 * filterActiveExceptions(), exceptionMatchesDate(), resolveLocalizedLabel(),
 * getExceptionForDate(), exceptionAppliesInYear(), getExceptionsForYear() and
 * resolveExceptionValidityRange().
 */
final class ExceptionCoreTest extends OpeningHoursTestCase {

    public function testNormalizeExceptionTimesReturnsTheTimesListWhenPresent(): void {
        $exception = ['times' => [self::timePair('08:00', '12:00'), self::timePair('13:00', '18:00')]];
        $this->assertSame($exception['times'], self::callMethod(OpeningHours::class, 'normalizeExceptionTimes', [$exception]));
    }

    public function testNormalizeExceptionTimesFallsBackToLegacyTopLevelStartFinish(): void {
        // entries saved before multiple time pairs were supported
        $exception = ['start' => '08:00', 'finish' => '12:00'];
        $this->assertSame(
            [self::timePair('08:00', '12:00')],
            self::callMethod(OpeningHours::class, 'normalizeExceptionTimes', [$exception])
        );
    }

    public function testNormalizeExceptionTimesReturnsEmptyArrayWhenNeitherIsPresent(): void {
        $this->assertSame([], self::callMethod(OpeningHours::class, 'normalizeExceptionTimes', [['label' => 'x']]));
    }

    public function testFilterActiveExceptionsKeepsRecurringExceptionsRegardlessOfDate(): void {
        $exceptions = [
            ['recurring' => true, 'startDate' => '2000-01-01', 'endDate' => '2000-01-01'],
        ];
        $filtered = OpeningHours::filterActiveExceptions($exceptions, new DateTime('2026-09-21'));
        $this->assertSame($exceptions, $filtered);
    }

    public function testFilterActiveExceptionsDropsExpiredNonRecurringExceptions(): void {
        $past = ['recurring' => false, 'startDate' => '2026-01-01', 'endDate' => '2026-01-02'];
        $future = ['recurring' => false, 'startDate' => '2026-12-01', 'endDate' => '2026-12-02'];
        $today = ['recurring' => false, 'startDate' => '2026-09-20', 'endDate' => '2026-09-21'];

        $filtered = OpeningHours::filterActiveExceptions([$past, $future, $today], new DateTime('2026-09-21'));

        $this->assertSame([$future, $today], $filtered);
    }

    public function testFilterActiveExceptionsDropsMalformedEntriesWithNoDate(): void {
        $malformed = ['recurring' => false];
        $this->assertSame([], OpeningHours::filterActiveExceptions([$malformed], new DateTime('2026-09-21')));
    }

    public function testExceptionMatchesDateForANonRecurringRange(): void {
        $exception = ['startDate' => '2026-12-24', 'endDate' => '2026-12-26', 'recurring' => false];

        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-23')]));
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-24')]));
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-25')]));
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-26')]));
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-27')]));
        // a non-recurring exception ignores the year only insofar as its stored dates say so -
        // the same month/day in a different year does NOT match
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2027-12-25')]));
    }

    public function testExceptionMatchesDateForARecurringExceptionIgnoresTheStoredYear(): void {
        $exception = ['startDate' => '2019-12-24', 'endDate' => '2019-12-26', 'recurring' => true];

        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-25')]));
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2099-12-25')]));
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-27')]));
    }

    public function testExceptionMatchesDateForARecurringRangeWrappingAcrossNewYear(): void {
        // company vacation 28.12. - 03.01., every year
        $exception = ['startDate' => '2020-12-28', 'endDate' => '2020-01-03', 'recurring' => true];

        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-12-30')]));
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2027-01-01')]));
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2027-01-03')]));
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2027-01-04')]));
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [$exception, new DateTime('2026-07-01')]));
    }

    public function testExceptionMatchesDateReturnsFalseForAMalformedException(): void {
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionMatchesDate', [[], new DateTime()]));
    }

    public function testResolveLocalizedLabelReturnsPlainStringsAsIs(): void {
        $this->assertSame('Christmas', self::callMethod(OpeningHours::class, 'resolveLocalizedLabel', ['Christmas']));
    }

    public function testResolveLocalizedLabelPrefersTheCurrentUsersLanguage(): void {
        TestWireContainer::setUserLanguage(new TestLanguageStub('5'));
        $label = ['default' => 'Christmas', '5' => 'Weihnachten'];

        $this->assertSame('Weihnachten', self::callMethod(OpeningHours::class, 'resolveLocalizedLabel', [$label]));
    }

    public function testResolveLocalizedLabelFallsBackToDefaultWhenUsersLanguageIsMissing(): void {
        TestWireContainer::setUserLanguage(new TestLanguageStub('999'));
        $label = ['default' => 'Christmas', '5' => 'Weihnachten'];

        $this->assertSame('Christmas', self::callMethod(OpeningHours::class, 'resolveLocalizedLabel', [$label]));
    }

    public function testResolveLocalizedLabelFallsBackToTheFirstEntryWhenThereIsNoDefault(): void {
        $label = ['5' => 'Weihnachten'];
        $this->assertSame('Weihnachten', self::callMethod(OpeningHours::class, 'resolveLocalizedLabel', [$label]));
    }

    public function testResolveLocalizedLabelReturnsEmptyStringForAnEmptyArray(): void {
        $this->assertSame('', self::callMethod(OpeningHours::class, 'resolveLocalizedLabel', [[]]));
    }

    public function testGetExceptionForDateReturnsTheMatchingException(): void {
        $christmas = ['label' => 'Christmas', 'startDate' => '2026-12-24', 'endDate' => '2026-12-26', 'recurring' => false, 'closed' => true];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$christmas]]);

        $this->assertSame($christmas, $openingHours->getExceptionForDate(new DateTime('2026-12-25')));
        $this->assertNull($openingHours->getExceptionForDate(new DateTime('2026-12-27')));
    }

    public function testExceptionAppliesInYearForRecurringExceptionsIsAlwaysTrue(): void {
        $exception = ['recurring' => true, 'startDate' => '2000-01-01', 'endDate' => '2000-01-01'];
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionAppliesInYear', [$exception, 2030]));
    }

    public function testExceptionAppliesInYearForNonRecurringExceptions(): void {
        $exception = ['recurring' => false, 'startDate' => '2026-06-01', 'endDate' => '2026-06-05'];
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionAppliesInYear', [$exception, 2026]));
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionAppliesInYear', [$exception, 2027]));
    }

    public function testExceptionAppliesInYearForANonRecurringRangeSpanningTwoYears(): void {
        $exception = ['recurring' => false, 'startDate' => '2025-12-28', 'endDate' => '2026-01-03'];
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionAppliesInYear', [$exception, 2025]));
        $this->assertTrue(self::callMethod(OpeningHours::class, 'exceptionAppliesInYear', [$exception, 2026]));
        $this->assertFalse(self::callMethod(OpeningHours::class, 'exceptionAppliesInYear', [$exception, 2027]));
    }

    public function testGetExceptionsForYearFiltersByYearAndDefaultsToTheCurrentYear(): void {
        $recurring = ['recurring' => true, 'startDate' => '2000-12-24', 'endDate' => '2000-12-24'];
        $only2026 = ['recurring' => false, 'startDate' => '2026-06-01', 'endDate' => '2026-06-05'];
        $only2019 = ['recurring' => false, 'startDate' => '2019-06-01', 'endDate' => '2019-06-05'];

        $openingHours = $this->makeOpeningHours(['exceptions' => [$recurring, $only2026, $only2019]]);

        $this->assertSame([$recurring, $only2026], $openingHours->getExceptionsForYear(2026));
    }

    public function testResolveExceptionValidityRangeReturnsStoredDatesForNonRecurringExceptions(): void {
        $exception = ['startDate' => '2026-03-01', 'endDate' => '2026-03-05', 'recurring' => false];
        $range = self::callMethod(OpeningHours::class, 'resolveExceptionValidityRange', [$exception, new DateTime('2026-01-01')]);

        $this->assertSame('2026-03-01', $range['start']->format('Y-m-d'));
        $this->assertSame('2026-03-05', $range['end']->format('Y-m-d'));
    }

    public function testResolveExceptionValidityRangeReturnsNullForAMalformedException(): void {
        $this->assertNull(self::callMethod(OpeningHours::class, 'resolveExceptionValidityRange', [[], new DateTime()]));
    }

    public function testResolveExceptionValidityRangeProjectsARecurringExceptionOntoTheReferenceYear(): void {
        $exception = ['startDate' => '2020-12-24', 'endDate' => '2020-12-26', 'recurring' => true];
        $range = self::callMethod(OpeningHours::class, 'resolveExceptionValidityRange', [$exception, new DateTime('2026-09-01')]);

        $this->assertSame('2026-12-24', $range['start']->format('Y-m-d'));
        $this->assertSame('2026-12-26', $range['end']->format('Y-m-d'));
    }

    public function testResolveExceptionValidityRangeRollsToNextYearOnceThisYearsOccurrenceIsOver(): void {
        $exception = ['startDate' => '2020-12-24', 'endDate' => '2020-12-26', 'recurring' => true];
        $range = self::callMethod(OpeningHours::class, 'resolveExceptionValidityRange', [$exception, new DateTime('2026-12-30')]);

        $this->assertSame('2027-12-24', $range['start']->format('Y-m-d'));
        $this->assertSame('2027-12-26', $range['end']->format('Y-m-d'));
    }

    public function testResolveExceptionValidityRangePushesTheEndDateIntoTheFollowingYearForAWrappingRange(): void {
        $exception = ['startDate' => '2020-12-28', 'endDate' => '2020-01-03', 'recurring' => true];
        $range = self::callMethod(OpeningHours::class, 'resolveExceptionValidityRange', [$exception, new DateTime('2026-06-15')]);

        $this->assertSame('2026-12-28', $range['start']->format('Y-m-d'));
        $this->assertSame('2027-01-03', $range['end']->format('Y-m-d'));
    }
}
