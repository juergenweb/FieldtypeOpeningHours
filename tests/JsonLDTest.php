<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

/**
 * Tests for getjsonLDTimes()/renderjsonLDTimes() (the regular weekly schedule, in
 * schema.org's plain "openingHours" string format) and getJsonLDSpecialOpeningHours()/
 * renderJsonLDSpecialOpeningHours() (exceptions, in the structured
 * "specialOpeningHoursSpecification" format).
 */
final class JsonLDTest extends OpeningHoursTestCase {

    public function testGetJsonLDTimesCombinesDaysWithIdenticalHours(): void {
        $openingHours = $this->makeOpeningHours(['times' => [
            'mo' => [self::timePair('08:00', '12:00')],
            'tu' => [self::timePair('08:00', '12:00')],
            'we' => [self::timePair('08:00', '12:00'), self::timePair('13:00', '18:00')],
            'th' => [self::timePair()],
            'fr' => [self::timePair()],
            'sa' => [self::timePair()],
            'su' => [self::timePair()],
            'ho' => [self::timePair()],
        ]]);

        $times = $openingHours->getjsonLDTimes();

        $this->assertContains('Mo,Tu,We 08:00-12:00', $times);
        $this->assertContains('We 13:00-18:00', $times);
        $this->assertCount(2, $times);
    }

    public function testRenderJsonLDTimesQuotesAndJoinsEachEntry(): void {
        $openingHours = $this->makeOpeningHours(['times' => [
            'mo' => [self::timePair('08:00', '12:00')],
            'tu' => [self::timePair()],
            'we' => [self::timePair()],
            'th' => [self::timePair()],
            'fr' => [self::timePair()],
            'sa' => [self::timePair()],
            'su' => [self::timePair()],
            'ho' => [self::timePair()],
        ]]);

        $this->assertSame('"Mo 08:00-12:00"', $openingHours->renderjsonLDTimes());
    }

    public function testGetJsonLDSpecialOpeningHoursRepresentsAClosedExceptionAsMidnightToMidnight(): void {
        $openingHours = $this->makeOpeningHours(['exceptions' => [
            [
                'label' => 'Christmas',
                'startDate' => '2026-12-24',
                'endDate' => '2026-12-26',
                'recurring' => false,
                'closed' => true,
                'times' => [],
            ],
        ]]);

        $referenceDate = new \DateTime('2026-09-01');
        $specs = $openingHours->getJsonLDSpecialOpeningHours($referenceDate);

        $this->assertSame([[
            '@type' => 'OpeningHoursSpecification',
            'opens' => '00:00',
            'closes' => '00:00',
            'validFrom' => '2026-12-24',
            'validThrough' => '2026-12-26',
        ]], $specs);
    }

    public function testGetJsonLDSpecialOpeningHoursAddsOneEntryPerTimePair(): void {
        $openingHours = $this->makeOpeningHours(['exceptions' => [
            [
                'label' => 'Christmas Eve, reduced hours',
                'startDate' => '2026-12-24',
                'endDate' => '2026-12-24',
                'recurring' => false,
                'closed' => false,
                'times' => [
                    self::timePair('09:00', '12:00'),
                    self::timePair('13:00', '15:00'),
                ],
            ],
        ]]);

        $specs = $openingHours->getJsonLDSpecialOpeningHours(new \DateTime('2026-09-01'));

        $this->assertCount(2, $specs);
        $this->assertSame('09:00', $specs[0]['opens']);
        $this->assertSame('12:00', $specs[0]['closes']);
        $this->assertSame('13:00', $specs[1]['opens']);
        $this->assertSame('15:00', $specs[1]['closes']);
        foreach ($specs as $spec) {
            $this->assertSame('2026-12-24', $spec['validFrom']);
            $this->assertSame('2026-12-24', $spec['validThrough']);
        }
    }

    public function testGetJsonLDSpecialOpeningHoursExcludesExpiredNonRecurringExceptions(): void {
        $openingHours = $this->makeOpeningHours(['exceptions' => [
            [
                'label' => 'Last year\'s inventory closure',
                'startDate' => '2025-01-02',
                'endDate' => '2025-01-03',
                'recurring' => false,
                'closed' => true,
                'times' => [],
            ],
        ]]);

        $specs = $openingHours->getJsonLDSpecialOpeningHours(new \DateTime('2026-09-01'));
        $this->assertSame([], $specs);
    }

    public function testGetJsonLDSpecialOpeningHoursProjectsARecurringExceptionOntoTheCurrentYear(): void {
        // stored year (2020) is irrelevant for a recurring exception - only month/day matter
        $openingHours = $this->makeOpeningHours(['exceptions' => [
            [
                'label' => 'Christmas',
                'startDate' => '2020-12-24',
                'endDate' => '2020-12-26',
                'recurring' => true,
                'closed' => true,
                'times' => [],
            ],
        ]]);

        $specs = $openingHours->getJsonLDSpecialOpeningHours(new \DateTime('2026-09-01'));

        $this->assertSame('2026-12-24', $specs[0]['validFrom']);
        $this->assertSame('2026-12-26', $specs[0]['validThrough']);
    }

    public function testGetJsonLDSpecialOpeningHoursRollsARecurringExceptionToNextYearOnceThisYearsOccurrenceIsOver(): void {
        $openingHours = $this->makeOpeningHours(['exceptions' => [
            [
                'label' => 'Christmas',
                'startDate' => '2020-12-24',
                'endDate' => '2020-12-26',
                'recurring' => true,
                'closed' => true,
                'times' => [],
            ],
        ]]);

        // today is already past this year's December 26th occurrence
        $specs = $openingHours->getJsonLDSpecialOpeningHours(new \DateTime('2026-12-30'));

        $this->assertSame('2027-12-24', $specs[0]['validFrom']);
        $this->assertSame('2027-12-26', $specs[0]['validThrough']);
    }

    public function testGetJsonLDSpecialOpeningHoursHandlesARecurringRangeWrappingAcrossNewYear(): void {
        $openingHours = $this->makeOpeningHours(['exceptions' => [
            [
                'label' => 'Company vacation',
                'startDate' => '2020-12-28',
                'endDate' => '2020-01-03', // month/day only: 12-28 through 01-03
                'recurring' => true,
                'closed' => true,
                'times' => [],
            ],
        ]]);

        $specs = $openingHours->getJsonLDSpecialOpeningHours(new \DateTime('2026-06-15'));

        $this->assertSame('2026-12-28', $specs[0]['validFrom']);
        $this->assertSame('2027-01-03', $specs[0]['validThrough']);
    }

    public function testRenderJsonLDSpecialOpeningHoursJsonEncodesEachEntry(): void {
        $openingHours = $this->makeOpeningHours(['exceptions' => [
            [
                'label' => 'Christmas',
                'startDate' => '2026-12-24',
                'endDate' => '2026-12-24',
                'recurring' => false,
                'closed' => true,
                'times' => [],
            ],
        ]]);

        $rendered = $openingHours->renderJsonLDSpecialOpeningHours(new \DateTime('2026-09-01'));

        $this->assertJson('[' . $rendered . ']');
        $decoded = json_decode('[' . $rendered . ']', true);
        $this->assertSame('OpeningHoursSpecification', $decoded[0]['@type']);
        $this->assertSame('2026-12-24', $decoded[0]['validFrom']);
    }
}
