<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use DateTime;
use ProcessWire\OpeningHours;

/**
 * Tests for the day-generator helpers (getDaysInWeek/Month/Year), the getExceptionsFor*()
 * lookups built on top of them, and renderExceptionsForWeek()/Month()/Year() - in particular
 * the grouping of consecutive matching days into "from - to" ranges, and the 'fulldayName'
 * option being honored for both single dates and ranges.
 */
final class ExceptionDaysAndRenderTest extends OpeningHoursTestCase {

    public function testGetDaysInWeekReturnsSevenDaysStartingOnMonday(): void {
        $openingHours = $this->makeOpeningHours();
        // 2026-01-14 is a Wednesday; its week runs Mon 2026-01-12 - Sun 2026-01-18
        $days = self::callMethod($openingHours, 'getDaysInWeek', [new DateTime('2026-01-14')]);

        $this->assertCount(7, $days);
        $this->assertSame('2026-01-12', $days[0]->format('Y-m-d'));
        $this->assertSame('Monday', $days[0]->format('l'));
        $this->assertSame('2026-01-18', $days[6]->format('Y-m-d'));
        $this->assertSame('Sunday', $days[6]->format('l'));
    }

    public function testGetDaysInMonthReturnsEveryDayOfTheGivenMonthInTheCurrentYear(): void {
        $openingHours = $this->makeOpeningHours();
        $currentYear = (int)(new DateTime())->format('Y');
        $expectedCount = (int)(new DateTime($currentYear . '-02-01'))->format('t');

        $days = self::callMethod($openingHours, 'getDaysInMonth', [2]);

        $this->assertCount($expectedCount, $days);
        $this->assertSame($currentYear . '-02-01', $days[0]->format('Y-m-d'));
        $this->assertSame($currentYear . '-02-' . str_pad((string)$expectedCount, 2, '0', STR_PAD_LEFT), $days[$expectedCount - 1]->format('Y-m-d'));
    }

    public function testGetDaysInMonthFallsBackToTheCurrentMonthForAnOutOfRangeArgument(): void {
        $openingHours = $this->makeOpeningHours();
        $currentMonth = (int)(new DateTime())->format('n');

        $days = self::callMethod($openingHours, 'getDaysInMonth', [13]);

        $this->assertSame($currentMonth, (int)$days[0]->format('n'));
    }

    public function testGetDaysInYearReturns366DaysForALeapYear(): void {
        $openingHours = $this->makeOpeningHours();
        $days = self::callMethod($openingHours, 'getDaysInYear', [2028]);

        $this->assertCount(366, $days);
        $this->assertSame('2028-01-01', $days[0]->format('Y-m-d'));
        $this->assertSame('2028-12-31', $days[365]->format('Y-m-d'));
    }

    public function testGetDaysInYearReturns365DaysForANonLeapYear(): void {
        $openingHours = $this->makeOpeningHours();
        $this->assertCount(365, self::callMethod($openingHours, 'getDaysInYear', [2026]));
    }

    public function testGetDaysInYearDefaultsToTheCurrentYear(): void {
        $openingHours = $this->makeOpeningHours();
        $currentYear = (int)(new DateTime())->format('Y');
        $days = self::callMethod($openingHours, 'getDaysInYear', [null]);

        $this->assertSame($currentYear . '-01-01', $days[0]->format('Y-m-d'));
    }

    public function testGetExceptionsForWeekReturnsOnlyExceptionsFallingInThatWeek(): void {
        $inWeek = ['startDate' => '2026-01-14', 'endDate' => '2026-01-14', 'recurring' => false];
        $outsideWeek = ['startDate' => '2026-02-01', 'endDate' => '2026-02-01', 'recurring' => false];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$inWeek, $outsideWeek]]);

        $this->assertSame([$inWeek], $openingHours->getExceptionsForWeek(new DateTime('2026-01-14')));
    }

    public function testGetExceptionsForMonthReturnsOnlyExceptionsFallingInThatMonthOfTheCurrentYear(): void {
        $currentYear = (int)(new DateTime())->format('Y');
        $inJune = ['startDate' => $currentYear . '-06-10', 'endDate' => $currentYear . '-06-10', 'recurring' => false];
        $inJuly = ['startDate' => $currentYear . '-07-10', 'endDate' => $currentYear . '-07-10', 'recurring' => false];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$inJune, $inJuly]]);

        $this->assertSame([$inJune], $openingHours->getExceptionsForMonth(6));
    }

    public function testRenderExceptionsForWeekShowsASingleMatchingDayWithItsTimes(): void {
        $exception = [
            'label' => 'Holiday sale',
            'startDate' => '2026-01-16',
            'endDate' => '2026-01-16',
            'recurring' => false,
            'closed' => false,
            'times' => [self::timePair('10:00', '14:00')],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $out = $openingHours->renderExceptionsForWeek(new DateTime('2026-01-14'));

        $this->assertSame('<ul><li class="oh-exception-week-item">Fr 16.01.2026: 10:00 - 14:00 (Holiday sale)</li></ul>', $out);
    }

    public function testRenderExceptionsForWeekGroupsConsecutiveMatchingDaysIntoARange(): void {
        // Monday through Wednesday of the week containing 2026-01-14
        $exception = [
            'label' => 'Company vacation',
            'startDate' => '2026-01-12',
            'endDate' => '2026-01-14',
            'recurring' => false,
            'closed' => true,
            'times' => [],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $out = $openingHours->renderExceptionsForWeek(new DateTime('2026-01-14'));

        $this->assertSame(
            '<ul><li class="oh-exception-week-item">Mo 12.01.2026 - We 14.01.2026: closed (Company vacation)</li></ul>',
            $out
        );
    }

    public function testRenderExceptionsForWeekHonorsFulldayNameForARangeToo(): void {
        $exception = [
            'label' => 'Company vacation',
            'startDate' => '2026-01-12',
            'endDate' => '2026-01-14',
            'recurring' => false,
            'closed' => true,
            'times' => [],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $out = $openingHours->renderExceptionsForWeek(new DateTime('2026-01-14'), ['fulldayName' => true]);

        $this->assertSame(
            '<ul><li class="oh-exception-week-item">Monday 12.01.2026 - Wednesday 14.01.2026: closed (Company vacation)</li></ul>',
            $out
        );
    }

    public function testRenderExceptionsForWeekReturnsAnEmptyWrapperWhenNothingMatches(): void {
        $openingHours = $this->makeOpeningHours(['exceptions' => []]);
        $this->assertSame('<ul></ul>', $openingHours->renderExceptionsForWeek(new DateTime('2026-01-14')));
    }

    public function testRenderExceptionsForMonthGroupsAMultiDayExceptionWithinTheMonth(): void {
        $currentYear = (int)(new DateTime())->format('Y');
        $exception = [
            'label' => 'Company vacation',
            'startDate' => $currentYear . '-06-10',
            'endDate' => $currentYear . '-06-15',
            'recurring' => false,
            'closed' => true,
            'times' => [],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $out = $openingHours->renderExceptionsForMonth(6);

        $this->assertStringContainsString('10.06.' . $currentYear, $out);
        $this->assertStringContainsString(' - ', $out);
        $this->assertStringContainsString('15.06.' . $currentYear . ': closed (Company vacation)', $out);
        $this->assertStringContainsString('oh-exception-month-item', $out);
    }

    public function testRenderExceptionsForYearGroupsAMultiDayExceptionWithinTheGivenYear(): void {
        $exception = [
            'label' => 'Company vacation',
            'startDate' => '2026-12-12',
            'endDate' => '2026-12-31',
            'recurring' => false,
            'closed' => true,
            'times' => [],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $out = $openingHours->renderExceptionsForYear(2026);

        $this->assertStringContainsString('12.12.2026', $out);
        $this->assertStringContainsString(' - ', $out);
        $this->assertStringContainsString('31.12.2026: closed (Company vacation)', $out);
        $this->assertStringContainsString('oh-exception-year-item', $out);
    }

    public function testRenderExceptionsForYearDefaultsToTheCurrentYear(): void {
        $currentYear = (int)(new DateTime())->format('Y');
        $exception = [
            'label' => 'Test',
            'startDate' => $currentYear . '-05-01',
            'endDate' => $currentYear . '-05-01',
            'recurring' => false,
            'closed' => true,
            'times' => [],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $this->assertStringContainsString('01.05.' . $currentYear, $openingHours->renderExceptionsForYear());
    }

    public function testRenderExceptionsForYearOnlyShowsTheRangeWithinTheRequestedYearForARecurringWrap(): void {
        // recurring 28.12. - 03.01.: within the days list for a single requested year, the
        // run is cut off at that year's boundary (Jan 1-3 of the following year are outside
        // getDaysInYear(2026)), so this documents that current, year-scoped behavior.
        $exception = [
            'label' => 'Company vacation',
            'startDate' => '2020-12-28',
            'endDate' => '2020-01-03',
            'recurring' => true,
            'closed' => true,
            'times' => [],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $out = $openingHours->renderExceptionsForYear(2026);

        $this->assertStringContainsString('Mo 28.12.2026 - Th 31.12.2026', $out);
        $this->assertStringNotContainsString('01.2027', $out);
    }

    public function testRenderExceptionsForMonthUsesDefaultOptionsWhenNoneGiven(): void {
        $currentYear = (int)(new DateTime())->format('Y');
        $exception = [
            'label' => 'Test',
            'startDate' => $currentYear . '-06-01',
            'endDate' => $currentYear . '-06-01',
            'recurring' => false,
            'closed' => true,
            'times' => [],
        ];
        $openingHours = $this->makeOpeningHours(['exceptions' => [$exception]]);

        $this->assertStringStartsWith('<ul>', $openingHours->renderExceptionsForMonth(6));
    }
}
