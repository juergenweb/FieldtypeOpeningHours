<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\OpeningHours;
use ProcessWire\TestLanguageStub;
use ProcessWire\TestWireContainer;

/**
 * Tests for ___render()'s "tableheader" column, which behaves differently on a single- vs.
 * multi-language site: on a single-language site there's no per-language override to look up
 * at all, while on a multi-language site exactly one header column is shown, driven entirely
 * by the CURRENT user's language, with a three-way precedence (per-language override > the
 * field's general "tableheader" setting > the translated default "Opening hours").
 *
 * getExceptionLanguagesJson(), renderExceptionLabelInputs() and the exceptions-label
 * single-/multi-language handling already have their own single-vs-multi-language coverage in
 * InputfieldOpeningHoursRenderTest.php and InputfieldOpeningHoursExceptionsSanitizeTest.php.
 */
final class InputfieldOpeningHoursLanguageTest extends InputfieldOpeningHoursTestCase {

    private function fullWeekValue(): OpeningHours {
        $times = [];
        foreach (OpeningHours::getWeekdays() as $abbr => $names) {
            $times[$abbr] = [['start' => '', 'finish' => '']];
        }
        return $this->makeValue(['times' => $times, 'exceptions' => []]);
    }

    private function renderThead(\ProcessWire\InputfieldOpeningHours $inputfield): string {
        $html = $inputfield->___render();
        preg_match('/<thead>.*?<\/thead>/s', $html, $matches);
        return $matches[0] ?? '';
    }

    public function testRendersNoTableheaderColumnAtAllOnASingleLanguageSite(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->fullWeekValue());

        $thead = $this->renderThead($inputfield);

        $this->assertStringNotContainsString('colspan', $thead);
        $this->assertSame('<thead><tr><th>Day of the week</th><th>Status</th><th></th></tr></thead>', $thead);
    }

    public function testUsesTheFieldsDefaultTableheaderTextOnAMultiLanguageSiteWhenNothingIsOverridden(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->fullWeekValue());
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);
        TestWireContainer::setUserLanguage(new TestLanguageStub('1', true));

        $thead = $this->renderThead($inputfield);

        $this->assertStringContainsString('<th colspan="2">Opening hours</th>', $thead);
        // exactly one header column is rendered - not one per configured language
        $this->assertSame(1, substr_count($thead, 'colspan="2"'));
    }

    public function testFallsBackToTheGeneralTableheaderSettingWhenNoPerLanguageOverrideExists(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['tableheader' => 'General Header']);
        $inputfield->attr('value', $this->fullWeekValue());
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);
        TestWireContainer::setUserLanguage(new TestLanguageStub('5'));

        $thead = $this->renderThead($inputfield);

        $this->assertStringContainsString('<th colspan="2">General Header</th>', $thead);
    }

    public function testPrefersTheCurrentUsersPerLanguageTableheaderOverrideOverTheGeneralSetting(): void {
        $inputfield = $this->makeInputfield('opening_hours', [
            'tableheader' => 'General Header',
            'tableheader5' => 'Öffnungszeiten',
        ]);
        $inputfield->attr('value', $this->fullWeekValue());
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);
        TestWireContainer::setUserLanguage(new TestLanguageStub('5'));

        $thead = $this->renderThead($inputfield);

        $this->assertStringContainsString('<th colspan="2">Öffnungszeiten</th>', $thead);
        $this->assertStringNotContainsString('General Header', $thead);
    }

    public function testNeverShowsAnotherLanguagesOverrideWhenTheCurrentUserIsOnTheDefaultLanguage(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['tableheader5' => 'Öffnungszeiten']);
        $inputfield->attr('value', $this->fullWeekValue());
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);
        TestWireContainer::setUserLanguage(new TestLanguageStub('1', true));

        $thead = $this->renderThead($inputfield);

        // the current (default-language) user must see the translated default text, not the
        // German override meant for language "5"
        $this->assertStringContainsString('<th colspan="2">Opening hours</th>', $thead);
        $this->assertStringNotContainsString('Öffnungszeiten', $thead);
        $this->assertSame(1, substr_count($thead, 'colspan="2"'));
    }
}
