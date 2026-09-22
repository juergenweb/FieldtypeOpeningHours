<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\OpeningHours;

/**
 * Tests for the array helper methods: getWeekdays(), getDayAbbreviations(),
 * createMultidimArray(), flattenArray() and the protected isMultidimArray().
 */
final class ArrayHelpersTest extends OpeningHoursTestCase {

    public function testGetWeekdaysReturnsAllEightKeysWithAbbreviationAndFullName(): void {
        $weekdays = OpeningHours::getWeekdays();

        $this->assertSame(['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su', 'ho'], array_keys($weekdays));
        $this->assertSame(['Mo', 'Monday'], $weekdays['mo']);
        $this->assertSame(['Ho', 'Holiday'], $weekdays['ho']);
    }

    public function testGetDayAbbreviationsReturnsOneEntryPerWeekday(): void {
        // Note: despite the method's name, it returns the same [abbr, fullname] pairs as
        // getWeekdays() (just re-indexed numerically), not a flat list of abbreviations -
        // this test documents that actual behavior.
        $days = OpeningHours::getDayAbbreviations();

        $this->assertCount(8, $days);
        $this->assertSame(['Mo', 'Monday'], $days[0]);
    }

    public function testIsMultidimArrayDetectsNestedArrays(): void {
        $this->assertTrue(self::callMethod(OpeningHours::class, 'isMultidimArray', [['mo' => [['start' => '', 'finish' => '']]]]));
        $this->assertFalse(self::callMethod(OpeningHours::class, 'isMultidimArray', [['08:00', '12:00']]));
    }

    public function testCreateMultidimArrayGroupsFlatPostValuesIntoStartFinishPairs(): void {
        // Simulates the flat $_POST-style array for field "opening_hours", day "mo", with
        // two time pairs (fe from the "Add time" button being used once).
        $flat = [
            'opening_hours-mo-0-start' => '08:00',
            'opening_hours-mo-0-finish' => '12:00',
            'opening_hours-mo-1-start' => '13:00',
            'opening_hours-mo-1-finish' => '18:00',
        ];

        $result = OpeningHours::createMultidimArray($flat);

        $this->assertSame([
            'mo' => [
                ['start' => '08:00', 'finish' => '12:00'],
                ['start' => '13:00', 'finish' => '18:00'],
            ],
        ], $result);
    }

    public function testCreateMultidimArrayReturnsAlreadyMultidimArrayUnchanged(): void {
        $already = ['mo' => [['start' => '08:00', 'finish' => '12:00']]];
        $this->assertSame($already, OpeningHours::createMultidimArray($already));
    }

    public function testFlattenArrayIsTheInverseOfCreateMultidimArray(): void {
        $multidim = ['mo' => [
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '13:00', 'finish' => '18:00'],
        ]];

        $flat = OpeningHours::flattenArray($multidim, 'opening_hours');

        $this->assertSame([
            'opening_hours-mo-0-start' => '08:00',
            'opening_hours-mo-0-finish' => '12:00',
            'opening_hours-mo-1-start' => '13:00',
            'opening_hours-mo-1-finish' => '18:00',
        ], $flat);

        // round-tripping flattenArray() -> createMultidimArray() reproduces the original
        $this->assertSame($multidim, OpeningHours::createMultidimArray($flat));
    }
}
