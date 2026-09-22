<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use ProcessWire\OpeningHours;
use ProcessWire\TestLanguageStub;
use ProcessWire\TestWireContainer;

/**
 * Security-focused regression tests: HTML/attribute escaping of user-controlled values
 * wherever they end up in rendered markup, and neutralization of script-injection payloads
 * by the exceptions sanitizer.
 */
final class InputfieldOpeningHoursSecurityTest extends InputfieldOpeningHoursTestCase {

    /** A handful of classic XSS payload shapes, covering tag-based and attribute-breakout attacks. */
    public static function xssPayloadProvider(): array {
        return [
            'script tag' => ['<script>alert(1)</script>'],
            'img onerror' => ['<img src=x onerror=alert(1)>'],
            'svg onload' => ['<svg onload=alert(1)>'],
            'attribute breakout' => ['"><script>alert(1)</script>'],
            'javascript URI' => ['<a href="javascript:alert(1)">click</a>'],
        ];
    }

    // --- ___render(): the regular weekly schedule table -----------------------------------

    /**
     * Regression test for the fix that added htmlspecialchars() to the day-times "value"
     * attribute in ___render(). isTimeValid() should normally keep anything but a plain
     * "H:i" string out of storage, but this value can also come straight from the database
     * (___wakeupValue() doesn't re-validate it), so a row written by something other than
     * ___processInput() - a direct DB edit, an old dataset, a future import script - must
     * still not be able to break out of the attribute.
     */
    public function testRenderEscapesADayTimeValueThatBreaksOutOfTheHtmlAttribute(): void {
        $inputfield = $this->makeInputfield();
        $inputfield->attr('value', $this->fullWeekValue([
            'mo' => [['start' => '08:00" onmouseover="alert(1)', 'finish' => '12:00']],
        ]));

        $html = $inputfield->___render();

        $this->assertStringNotContainsString('onmouseover="alert(1)"', $html);
        $this->assertStringContainsString('08:00&quot; onmouseover=&quot;alert(1)', $html);
    }

    // --- sanitizeExceptionsInput(): label XSS payloads get neutralized --------------------

    #[DataProvider('xssPayloadProvider')]
    public function testSanitizeExceptionsInputStripsXssPayloadsFromASingleLanguageLabel(string $payload): void {
        $inputfield = $this->makeInputfield();

        $result = self::callMethod($inputfield, 'sanitizeExceptionsInput', [json_encode([
            ['label' => $payload, 'startDate' => '2026-08-01', 'closed' => true],
        ])]);

        $this->assertLabelHasNoLiveTag($result[0]['label']);
    }

    #[DataProvider('xssPayloadProvider')]
    public function testSanitizeExceptionsInputStripsXssPayloadsFromAMultiLanguageLabel(string $payload): void {
        $inputfield = $this->makeInputfield();
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);

        $result = self::callMethod($inputfield, 'sanitizeExceptionsInput', [json_encode([
            ['label' => ['default' => $payload, '5' => $payload], 'startDate' => '2026-08-01', 'closed' => true],
        ])]);

        foreach ($result[0]['label'] as $text) {
            $this->assertLabelHasNoLiveTag($text);
        }
    }

    // --- renderExceptionLabelInputs()/renderExceptionRow(): render output stays escaped ---

    #[DataProvider('xssPayloadProvider')]
    public function testRenderExceptionLabelInputsEscapesAPayloadOnASingleLanguageSite(string $payload): void {
        $inputfield = $this->makeInputfield();

        $html = self::callMethod($inputfield, 'renderExceptionLabelInputs', [$payload]);

        $this->assertPayloadIsNotExecutable($html, $payload);
    }

    #[DataProvider('xssPayloadProvider')]
    public function testRenderExceptionLabelInputsEscapesAPayloadOnAMultiLanguageSite(string $payload): void {
        $inputfield = $this->makeInputfield();
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);

        $html = self::callMethod($inputfield, 'renderExceptionLabelInputs', [['default' => $payload, '5' => $payload]]);

        $this->assertPayloadIsNotExecutable($html, $payload);
    }

    #[DataProvider('xssPayloadProvider')]
    public function testRenderExceptionRowEscapesAPayloadInTheLabelAndDateFields(string $payload): void {
        $inputfield = $this->makeInputfield();

        $html = self::callMethod($inputfield, 'renderExceptionRow', [
            ['startDate' => $payload, 'endDate' => $payload, 'closed' => true, 'label' => $payload],
        ]);

        $this->assertPayloadIsNotExecutable($html, $payload);
    }

    // --- unrecognized/malicious multi-language label keys are dropped ---------------------

    /**
     * A submitted multi-language label is stored as-is and later handed back out as a JSON
     * object (getExceptionLanguagesJson()/openinghours.js), so an unrecognized key here isn't
     * an HTML-escaping problem but a data-integrity one: only keys that are actually "default"
     * or one of this site's configured language ids should survive - see
     * getAllowedExceptionLabelLanguageKeys(). This also blocks a JS-prototype-pollution-style
     * key such as "__proto__" or "constructor" from being stored and later assigned into a
     * plain object client-side.
     */
    public function testSanitizeExceptionsInputDropsLabelKeysThatArentAConfiguredLanguage(): void {
        $inputfield = $this->makeInputfield();
        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);

        $result = self::callMethod($inputfield, 'sanitizeExceptionsInput', [json_encode([
            ['label' => [
                'default' => 'Holiday',
                '5' => 'Feiertag',
                '__proto__' => 'Evil',
                'constructor' => 'Evil',
                '99' => 'Unknown language',
            ], 'startDate' => '2026-08-01', 'closed' => true],
        ])]);

        $this->assertSame(['default' => 'Holiday', '5' => 'Feiertag'], $result[0]['label']);
    }

    public function testSanitizeExceptionsInputAcceptsOnlyDefaultOnASingleLanguageSite(): void {
        $inputfield = $this->makeInputfield();
        // no TestWireContainer::setLanguages() call - single-language site

        $result = self::callMethod($inputfield, 'sanitizeExceptionsInput', [json_encode([
            ['label' => ['default' => 'Holiday', '__proto__' => 'Evil'], 'startDate' => '2026-08-01', 'closed' => true],
        ])]);

        $this->assertSame(['default' => 'Holiday'], $result[0]['label']);
    }

    // --- helpers ----------------------------------------------------------------------------

    private function fullWeekValue(array $overrides = []): OpeningHours {
        $times = [];
        foreach (OpeningHours::getWeekdays() as $abbr => $names) {
            $times[$abbr] = $overrides[$abbr] ?? [['start' => '', 'finish' => '']];
        }
        return $this->makeValue(['times' => $times, 'exceptions' => []]);
    }

    /**
     * Assert that the raw payload string doesn't appear verbatim in the given HTML - it must
     * have been HTML-escaped (turned into inert text), not dropped into the markup as live
     * tags/attributes. Every payload in xssPayloadProvider() contains at least one of
     * < > " so this is enough to catch a missing htmlspecialchars() call; it deliberately
     * doesn't also demand the substring "javascript:" itself be gone, since that text is
     * harmless once it's inert (escaped) content rather than a live href/src.
     */
    private function assertPayloadIsNotExecutable(string $html, string $payload): void {
        $this->assertStringNotContainsString($payload, $html);
    }

    /**
     * strip_tags() (used by sanitizer->text() for the exception label) guarantees no "<"
     * character survives, so no tag can start - that's the actual guarantee worth testing at
     * the sanitize step. It does NOT guarantee a stray "&" or ">" with no matching "<" is
     * removed (fe stripping "<script>" out of the middle of "\"><script>...</script>" leaves
     * the leading "\">" behind as plain text) - that's fine, since a lone ">" can't open a
     * tag, and whatever ends up stored here still gets htmlspecialchars()'d again at render
     * time (see the renderException*() tests above), which is the layer responsible for that.
     */
    private function assertLabelHasNoLiveTag(string $text): void {
        $this->assertStringNotContainsString('<', $text);
    }
}
