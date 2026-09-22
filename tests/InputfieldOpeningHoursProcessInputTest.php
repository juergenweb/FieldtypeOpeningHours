<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\WireInputData;

/**
 * End-to-end tests for ___processInput(): pulling this field's own inputs out of the full
 * WireInputData, sanitizing/validating the weekday times, and sanitizing the exceptions JSON,
 * then populating both back onto $this->value.
 */
final class InputfieldOpeningHoursProcessInputTest extends InputfieldOpeningHoursTestCase {

    private function process(\ProcessWire\InputfieldOpeningHours $inputfield, array $data): void {
        $inputfield->___processInput(new WireInputData($data));
    }

    public function testPopulatesTimesForEveryWeekdayEvenWhenOnlyOneWasSubmitted(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->makeValue());

        $this->process($inputfield, [
            'opening_hours-mo-0-start' => '08:00',
            'opening_hours-mo-0-finish' => '12:00',
        ]);

        $times = $inputfield->value->times;
        $this->assertSame('08:00', $times['opening_hours-mo-0-start']);
        $this->assertSame('12:00', $times['opening_hours-mo-0-finish']);
        // every other weekday not present in the submission is filled in blank, not omitted
        $this->assertSame('', $times['opening_hours-tu-0-start']);
        $this->assertSame('', $times['opening_hours-ho-0-finish']);
    }

    public function testIgnoresInputsBelongingToOtherFieldsOnThePage(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->makeValue());

        $this->process($inputfield, [
            'opening_hours-mo-0-start' => '08:00',
            'opening_hours-mo-0-finish' => '12:00',
            'some_other_field-mo-0-start' => '99:99',
        ]);

        $this->assertArrayNotHasKey('some_other_field-mo-0-start', $inputfield->value->times);
    }

    public function testReplacesAnInvalidTimeStringWithAnEmptyValueAndErrors(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->makeValue());

        $this->process($inputfield, [
            'opening_hours-mo-0-start' => 'not a time',
            'opening_hours-mo-0-finish' => '12:00',
        ]);

        $this->assertSame('', $inputfield->value->times['opening_hours-mo-0-start']);
        $this->assertNotEmpty($inputfield->errors);
    }

    public function testSanitizesTheHiddenExceptionsInputAndExcludesItFromTheDayTimesLoop(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->makeValue());

        $this->process($inputfield, [
            'opening_hours-mo-0-start' => '08:00',
            'opening_hours-mo-0-finish' => '12:00',
            'opening_hours-exceptions' => json_encode([
                ['label' => 'Christmas', 'startDate' => '2026-12-24', 'closed' => true],
            ]),
        ]);

        $this->assertCount(1, $inputfield->value->exceptions);
        $this->assertSame('Christmas', $inputfield->value->exceptions[0]['label']);
        // the "-exceptions" input must never be treated as a day/time key
        foreach (array_keys($inputfield->value->times) as $key) {
            $this->assertStringNotContainsString('exceptions', $key);
        }
    }

    public function testLeavesExceptionsEmptyWhenNoExceptionsInputWasSubmitted(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->makeValue());

        $this->process($inputfield, [
            'opening_hours-mo-0-start' => '08:00',
            'opening_hours-mo-0-finish' => '12:00',
        ]);

        $this->assertSame([], $inputfield->value->exceptions);
    }

    public function testReturnsTheInputfieldItselfForChaining(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->makeValue());

        $result = $inputfield->___processInput(new WireInputData([
            'opening_hours-mo-0-start' => '08:00',
            'opening_hours-mo-0-finish' => '12:00',
        ]));

        $this->assertSame($inputfield, $result);
    }
}
