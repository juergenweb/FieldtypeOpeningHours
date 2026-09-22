<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\TestLanguageStub;
use ProcessWire\TestWireContainer;

/**
 * Tests for the markup-producing methods: ___render() (the regular weekly schedule table),
 * renderExceptionsSection()/renderExceptionsConfigMarkup()/renderExceptionRow() (the
 * exceptions/holidays section), renderExceptionTimesInputs(), renderExceptionLabelInputs()
 * and getExceptionLanguagesJson().
 */
final class InputfieldOpeningHoursRenderTest extends InputfieldOpeningHoursTestCase {

    private function fullWeekValue(array $overrides = [], array $exceptions = []): \ProcessWire\OpeningHours {
        $times = [];
        foreach (\ProcessWire\OpeningHours::getWeekdays() as $abbr => $names) {
            $times[$abbr] = $overrides[$abbr] ?? [['start' => '', 'finish' => '']];
        }
        return $this->makeValue(['times' => $times, 'exceptions' => $exceptions]);
    }

    public function testRenderMarksADayWithATimeAsOpenAndAnEmptyDayAsClosed(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->fullWeekValue(['mo' => [['start' => '08:00', 'finish' => '12:00']]]));

        $html = $inputfield->___render();

        $this->assertStringContainsString('day-row day-mo open', $html);
        $this->assertStringContainsString('day-row day-tu closed', $html);
    }

    public function testRenderHidesTheHolidayRowWhenHideholidayIsSet(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['hideholiday' => 1]);
        $inputfield->attr('value', $this->fullWeekValue());

        $html = $inputfield->___render();

        $this->assertMatchesRegularExpression('/day-row day-ho closed"[^>]*style="display:none"/', $html);
    }

    public function testRenderShowsTheHolidayRowByDefault(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->fullWeekValue());

        $html = $inputfield->___render();

        $this->assertDoesNotMatchRegularExpression('/day-row day-ho closed"[^>]*style="display:none"/', $html);
    }

    public function testRenderIncludesACopyToButtonAndPopoverForEveryWeekday(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->fullWeekValue());

        $html = $inputfield->___render();

        // one button + one popover per weekday, including the holiday row (8 total)
        $this->assertSame(8, substr_count($html, 'oh-copy-day-btn'));
        $this->assertSame(8, substr_count($html, 'oh-copy-day-popover'));
    }

    public function testCopyDayPopoverListsEveryOtherDayButExcludesItself(): void {
        $inputfield = $this->makeInputfield();

        $popover = self::callMethod($inputfield, 'renderCopyDayPopover', ['mo']);

        $this->assertStringContainsString('value="tu"', $popover);
        $this->assertStringContainsString('value="ho"', $popover);
        $this->assertStringNotContainsString('value="mo"', $popover);
    }

    public function testCopyDayPopoverExcludesTheHolidayDayAsATargetWhenHideholidayIsSet(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['hideholiday' => 1]);

        $popover = self::callMethod($inputfield, 'renderCopyDayPopover', ['mo']);

        $this->assertStringNotContainsString('value="ho"', $popover);
        // the other, unrelated days are still offered
        $this->assertStringContainsString('value="tu"', $popover);
    }

    public function testCopyDayPopoverIsHiddenByDefault(): void {
        $inputfield = $this->makeInputfield();

        $popover = self::callMethod($inputfield, 'renderCopyDayPopover', ['mo']);

        $this->assertStringContainsString('style="display:none"', $popover);
    }

    public function testRenderAppendsTheExceptionsSectionBelowTheWeeklyTable(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->fullWeekValue([], [
            ['label' => 'Christmas', 'startDate' => '2026-12-24', 'endDate' => '2026-12-24', 'closed' => true, 'recurring' => true],
        ]));

        $html = $inputfield->___render();

        $this->assertStringContainsString('oh-exceptions-wrap', $html);
        $this->assertStringContainsString('oh-exception-row', $html);
    }

    public function testRenderExceptionsSectionEmbedsTheGivenExceptionsAsHiddenJson(): void {
        $inputfield = $this->makeInputfield();
        $exceptions = [
            ['label' => 'Christmas', 'startDate' => '2026-12-24', 'endDate' => '2026-12-24', 'closed' => true, 'recurring' => true],
        ];

        $html = self::callMethod($inputfield, 'renderExceptionsSection', ['opening_hours', $exceptions]);

        $this->assertStringContainsString('id="opening_hours-exceptions"', $html);
        $this->assertStringContainsString('Christmas', $html);
    }

    public function testRenderExceptionsSectionFiltersOutExpiredNonRecurringExceptions(): void {
        $inputfield = $this->makeInputfield();
        $expired = ['label' => 'Past', 'startDate' => '2000-01-01', 'endDate' => '2000-01-02', 'closed' => true, 'recurring' => false];

        $html = self::callMethod($inputfield, 'renderExceptionsSection', ['opening_hours', [$expired]]);

        $this->assertStringNotContainsString('Past', $html);
    }

    public function testGetExceptionLanguagesJsonFallsBackToASingleDefaultEntryOnASingleLanguageSite(): void {
        $inputfield = $this->makeInputfield();

        $json = self::callMethod($inputfield, 'getExceptionLanguagesJson');

        $this->assertSame([['id' => 'default', 'label' => 'default']], json_decode($json, true));
    }

    public function testGetExceptionLanguagesJsonListsEveryLanguageOnAMultiLanguageSite(): void {
        $inputfield = $this->makeInputfield();
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true, 'English'),
            new TestLanguageStub('5', false, 'Deutsch'),
        ]);

        $json = self::callMethod($inputfield, 'getExceptionLanguagesJson');

        // the default language's id is normalized to the literal string "default"
        $this->assertSame(
            [['id' => 'default', 'label' => 'English'], ['id' => '5', 'label' => 'Deutsch']],
            json_decode($json, true)
        );
    }

    public function testRenderExceptionLabelInputsRendersAPlainInputOnASingleLanguageSite(): void {
        $inputfield = $this->makeInputfield();

        $html = self::callMethod($inputfield, 'renderExceptionLabelInputs', ['My Label']);

        $this->assertStringContainsString('value="My Label"', $html);
        $this->assertStringNotContainsString('oh-lang-tabs', $html);
    }

    public function testRenderExceptionLabelInputsRendersATabPerLanguageOnAMultiLanguageSite(): void {
        $inputfield = $this->makeInputfield();
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true, 'English'),
            new TestLanguageStub('5', false, 'Deutsch'),
        ]);

        $html = self::callMethod($inputfield, 'renderExceptionLabelInputs', [['default' => 'Holiday', '5' => 'Feiertag']]);

        $this->assertStringContainsString('oh-lang-tabs', $html);
        $this->assertStringContainsString('value="Holiday"', $html);
        $this->assertStringContainsString('value="Feiertag"', $html);
        // only the first (default) language's input stays visible; the rest start hidden
        $this->assertStringContainsString('style="display:none"', $html);
    }

    public function testRenderExceptionTimesInputsNormalizesALegacySingleTimePair(): void {
        $inputfield = $this->makeInputfield();

        $html = self::callMethod($inputfield, 'renderExceptionTimesInputs', [['start' => '08:00', 'finish' => '12:00']]);

        $this->assertSame(1, substr_count($html, 'oh-exception-time-pair'));
        $this->assertStringContainsString('value="08:00"', $html);
    }

    public function testRenderExceptionTimesInputsRendersARemoveButtonForEveryPairAfterTheFirst(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 2]);

        $html = self::callMethod($inputfield, 'renderExceptionTimesInputs', [[
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '13:00', 'finish' => '18:00'],
        ]]);

        $this->assertSame(1, substr_count($html, 'oh-exception-time-add-btn'));
        $this->assertSame(1, substr_count($html, 'oh-exception-time-remove-btn'));
    }

    public function testRenderExceptionTimesInputsDisablesTheAddButtonAtTheConfiguredMax(): void {
        $inputfield = $this->makeInputfield('opening_hours', ['numberOftimes' => 2]);

        $html = self::callMethod($inputfield, 'renderExceptionTimesInputs', [[
            ['start' => '08:00', 'finish' => '12:00'],
            ['start' => '13:00', 'finish' => '18:00'],
        ]]);

        $this->assertStringContainsString('disabled="disabled"', $html);
    }

    public function testRenderExceptionRowEscapesTheDatesAndLabel(): void {
        $inputfield = $this->makeInputfield();

        $html = self::callMethod($inputfield, 'renderExceptionRow', [
            ['startDate' => '2026-08-01', 'endDate' => '2026-08-01', 'closed' => true, 'recurring' => false, 'label' => '<script>alert(1)</script>'],
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('value="2026-08-01"', $html);
    }

    public function testRenderExceptionRowMarksHoursAsRequiredOnlyWhenNotClosed(): void {
        $inputfield = $this->makeInputfield();

        $open = self::callMethod($inputfield, 'renderExceptionRow', [
            ['startDate' => '2026-08-01', 'closed' => false, 'times' => [['start' => '08:00', 'finish' => '12:00']]],
        ]);
        $closed = self::callMethod($inputfield, 'renderExceptionRow', [
            ['startDate' => '2026-08-01', 'closed' => true],
        ]);

        $this->assertStringContainsString('InputfieldStateRequired', $open);
        $this->assertStringNotContainsString('InputfieldStateRequired', $closed);
    }

    public function testRenderExceptionRowChecksTheClosedAndRecurringCheckboxesToMatchTheException(): void {
        $inputfield = $this->makeInputfield();

        $html = self::callMethod($inputfield, 'renderExceptionRow', [
            ['startDate' => '2026-08-01', 'closed' => true, 'recurring' => true],
        ]);

        $this->assertSame(2, substr_count($html, ' checked'));
    }

    public function testRenderExceptionsConfigMarkupGivesEachRowsClosedCheckboxAUniqueIncrementingId(): void {
        $inputfield = $this->makeInputfield();

        $html = self::callMethod($inputfield, 'renderExceptionsConfigMarkup', [[
            ['startDate' => '2026-01-01', 'closed' => true],
            ['startDate' => '2026-02-01', 'closed' => true],
        ]]);

        preg_match_all('/exception-closed-(\d+)/', $html, $matches);
        $this->assertSame(['0', '1'], $matches[1]);
    }

    public function testRenderExceptionsConfigMarkupResetsTheRowIndexOnEveryCall(): void {
        $inputfield = $this->makeInputfield();
        self::callMethod($inputfield, 'renderExceptionsConfigMarkup', [[
            ['startDate' => '2026-01-01', 'closed' => true],
            ['startDate' => '2026-02-01', 'closed' => true],
        ]]);

        $html = self::callMethod($inputfield, 'renderExceptionsConfigMarkup', [[
            ['startDate' => '2026-03-01', 'closed' => true],
        ]]);

        preg_match_all('/exception-closed-(\d+)/', $html, $matches);
        $this->assertSame(['0'], $matches[1]);
    }
}
