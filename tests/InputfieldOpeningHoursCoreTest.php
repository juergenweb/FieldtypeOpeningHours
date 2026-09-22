<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\InputfieldOpeningHours;

/**
 * Tests for getModuleInfo(), getTranslateableTexts() and getConfigAllowContext() - the
 * small, self-contained metadata/lookup methods.
 */
final class InputfieldOpeningHoursCoreTest extends InputfieldOpeningHoursTestCase {

    public function testGetModuleInfoDeclaresTheExpectedMetadata(): void {
        $info = InputfieldOpeningHours::getModuleInfo();

        $this->assertSame('Inputfield Opening Hours', $info['title']);
        $this->assertContains('FieldtypeOpeningHours', $info['requires']);
    }

    public function testGetTranslateableTextsReturnsEveryKeyOpeninghoursJsNeeds(): void {
        $inputfield = $this->makeInputfield();
        $texts = self::callMethod($inputfield, 'getTranslateableTexts');

        $expectedKeys = [
            'add', 'remove', 'removeException', 'label', 'dates', 'from', 'to',
            'closedAllDay', 'repeatEveryYear', 'hours', 'labelPlaceholder',
        ];
        $this->assertSame($expectedKeys, array_keys($texts));
        $this->assertSame('Add', $texts['add']);
        $this->assertSame('Remove Exception', $texts['removeException']);
    }

    public function testGetConfigAllowContextListsTheOverridableProperties(): void {
        $inputfield = $this->makeInputfield();
        $this->assertSame(
            ['numberOftimes', 'timeformat', 'tableheader', 'hideholiday'],
            $inputfield->getConfigAllowContext($this->makeField())
        );
    }

    private function makeField(): \ProcessWire\Field {
        return new \ProcessWire\Field();
    }
}
