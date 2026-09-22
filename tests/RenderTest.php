<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\TestWireContainer;

/**
 * Tests for render(), renderDay(), renderDefinitionList(), renderTable(), renderDiv(),
 * __toString(), combinedDays(), renderCombinedDays() and getNumberOfTimes() - the regular
 * (non-exception) weekly-schedule rendering.
 */
final class RenderTest extends OpeningHoursTestCase {

    /** Full 8-day times array (mo..su + ho) used by most tests in this file. */
    private function sampleTimes(): array {
        return [
            'mo' => [self::timePair('08:00', '12:00')],
            'tu' => [self::timePair()], // closed
            'we' => [self::timePair('08:00', '12:00'), self::timePair('13:00', '18:00')],
            'th' => [self::timePair()],
            'fr' => [self::timePair()],
            'sa' => [self::timePair()],
            'su' => [self::timePair()],
            'ho' => [self::timePair()],
        ];
    }

    public function testRenderDayFormatsASingleOpeningTime(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame('08:00 - 12:00', $openingHours->renderDay('mo'));
    }

    public function testRenderDayFormatsMultipleOpeningTimesForOneDay(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame('08:00 - 12:00, 13:00 - 18:00', $openingHours->renderDay('we'));
    }

    public function testRenderDayShowsClosedTextForAClosedDayByDefault(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame('closed', $openingHours->renderDay('tu'));
    }

    public function testRenderDayHidesClosedTextWhenShowClosedIsFalse(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame('', $openingHours->renderDay('tu', ['showClosed' => false]));
    }

    public function testRenderDayWrapsTimesInTheGivenTimetagWhenCalledDirectlyWithoutRender(): void {
        // regression test for the fix that moved defaultOptions() merging to the top of
        // renderDay(): calling it directly (not through render()) with a partial $options
        // array used to trigger a PHP "undefined array key timetag" warning
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame('<span class="oh-time mo">08:00 - 12:00</span>', $openingHours->renderDay('mo', ['timetag' => 'span']));
    }

    public function testRenderDayReturnsEmptyStringInTheAdmin(): void {
        TestWireContainer::setPageUrl('/processwire/page/edit/');
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame('', $openingHours->renderDay('mo'));
    }

    public function testRenderBuildsAnUnorderedListOfAllWeekdays(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $out = $openingHours->render();

        $this->assertStringStartsWith('<ul>', $out);
        $this->assertStringEndsWith('</ul>', $out);
        $this->assertStringContainsString('<li class="time day-mo">Mo: 08:00 - 12:00</li>', $out);
        $this->assertStringContainsString('<li class="time day-tu">Tu: closed</li>', $out);
        $this->assertStringContainsString('<li class="time day-we">We: 08:00 - 12:00, 13:00 - 18:00</li>', $out);
        // holiday row is included by default (hideholiday = 0)
        $this->assertStringContainsString('day-ho', $out);
    }

    public function testRenderOmitsTheHolidayRowWhenHideholidayIsSet(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes(), 'hideholiday' => 1]);
        $this->assertStringNotContainsString('day-ho', $openingHours->render());
    }

    public function testRenderUsesFullDayNamesWhenRequested(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertStringContainsString('Monday:', $openingHours->render(['fulldayName' => true]));
    }

    public function testRenderReturnsEmptyStringInTheAdmin(): void {
        TestWireContainer::setPageUrl('/processwire/page/edit/');
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame('', $openingHours->render());
    }

    public function testToStringDelegatesToRender(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame($openingHours->render(), (string)$openingHours);
    }

    public function testRenderDefinitionListUsesDlDtDdTags(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $out = $openingHours->renderDefinitionList();

        $this->assertStringStartsWith('<dl>', $out);
        $this->assertStringContainsString('<dt', $out);
        $this->assertStringContainsString('<dd', $out);
    }

    public function testRenderTableUsesTableTrTdTags(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $out = $openingHours->renderTable();

        $this->assertStringStartsWith('<table>', $out);
        $this->assertStringContainsString('<tr', $out);
        $this->assertStringContainsString('<td', $out);
    }

    public function testRenderDivUsesDivAndSpanTags(): void {
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $out = $openingHours->renderDiv();

        $this->assertStringStartsWith('<div>', $out);
        $this->assertStringContainsString('<span', $out);
    }

    public function testCombinedDaysGroupsDaysWithIdenticalHours(): void {
        $times = $this->sampleTimes();
        $times['tu'] = $times['mo']; // give Tuesday the exact same hours as Monday

        $openingHours = $this->makeOpeningHours(['times' => $times]);
        $combined = $openingHours->combinedDays();

        $this->assertSame(['mo', 'tu'], $combined['mo']['days']);
        // Wednesday has its own, different hours and stays in its own group
        $this->assertSame(['we'], $combined['we']['days']);
    }

    public function testGetNumberOfTimesCountsOnlyNonEmptyPairs(): void {
        // 1 pair on Monday + 2 pairs on Wednesday = 3 non-empty pairs; every other day is
        // closed (empty) and doesn't count
        $openingHours = $this->makeOpeningHours(['times' => $this->sampleTimes()]);
        $this->assertSame(3, $openingHours->getNumberOfTimes());
    }

    public function testGetNumberOfTimesExcludesHolidayWhenHideholidayIsSet(): void {
        $times = $this->sampleTimes();
        $times['ho'] = [self::timePair('10:00', '11:00')];

        $openingHours = $this->makeOpeningHours(['times' => $times, 'hideholiday' => 1]);
        // the 3 regular pairs from sampleTimes(), the extra holiday pair excluded
        $this->assertSame(3, $openingHours->getNumberOfTimes());
    }
}
