<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use Exception;
use ProcessWire\FieldtypeOpeningHours;
use ProcessWire\OpeningHours;
use ProcessWire\TestLanguageStub;
use ProcessWire\TestWireContainer;

/**
 * Tests for the core Fieldtype hooks: getModuleInfo(), ___getCompatibleFieldtypes(),
 * getBlankValue(), getDatabaseSchema(), ___wakeupValue(), ___sleepValue(), sanitizeValue(),
 * formatValue() and getInputfield().
 */
final class FieldtypeOpeningHoursCoreTest extends FieldtypeOpeningHoursTestCase {

    public function testGetModuleInfoDeclaresTheExpectedMetadata(): void {
        $info = FieldtypeOpeningHours::getModuleInfo();

        $this->assertSame('Openinghours', $info['title']);
        $this->assertSame('InputfieldOpeningHours', $info['installs']);
        // the module must stay autoload=true, otherwise init()'s LazyCron hook registration
        // and the schema self-heal never run
        $this->assertTrue($info['autoload']);
    }

    public function testGetCompatibleFieldtypesAlwaysReturnsNull(): void {
        $fieldtype = $this->makeFieldtype();
        $this->assertNull($fieldtype->___getCompatibleFieldtypes($this->makeField()));
    }

    public function testGetBlankValueReturnsAnEmptyChangeTrackingOpeningHours(): void {
        $fieldtype = $this->makeFieldtype();
        $value = $fieldtype->getBlankValue($this->makePage(), $this->makeField());

        $this->assertInstanceOf(OpeningHours::class, $value);
        // setTrackChanges() must have been enabled, otherwise sanitizeValue() could never
        // detect that 'times'/'exceptions' changed
        $this->assertFalse($value->isChanged());
        $value->set('times', ['mo' => []]);
        $this->assertTrue($value->isChanged('times'));
    }

    public function testGetDatabaseSchemaAddsTimesAndExceptionsColumns(): void {
        $fieldtype = $this->makeFieldtype();
        $schema = $fieldtype->getDatabaseSchema($this->makeField());

        $this->assertSame('TEXT DEFAULT NULL', $schema['times']);
        $this->assertSame('TEXT DEFAULT NULL', $schema['exceptions']);
        // still carries whatever the base Fieldtype schema already had (fe 'data')
        $this->assertArrayHasKey('data', $schema);
    }

    public function testWakeupValueDecodesStoredTimesJson(): void {
        $fieldtype = $this->makeFieldtype();
        $raw = [
            'times' => json_encode(['mo' => [['start' => '08:00', 'finish' => '12:00']]]),
            'exceptions' => '',
        ];

        $value = $fieldtype->___wakeupValue($this->makePage(), $this->makeField(), $raw);

        $this->assertSame(['start' => '08:00', 'finish' => '12:00'], $value->times['mo'][0]);
    }

    public function testWakeupValueFallsBackToDefaultDataWhenTimesAreEmpty(): void {
        $fieldtype = $this->makeFieldtype();
        $raw = ['times' => '', 'exceptions' => ''];

        $value = $fieldtype->___wakeupValue($this->makePage(), $this->makeField(), $raw);

        // defaultData.json has all 8 days present, each with one empty (closed) pair
        $this->assertSame(['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su', 'ho'], array_keys($value->times));
        $this->assertSame(['start' => '', 'finish' => ''], $value->times['mo'][0]);
    }

    public function testWakeupValueCopiesTheFieldsConfigurationOntoTheValue(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['numberOftimes' => '3', 'timeformat' => 'H:i', 'hideholiday' => 1]);
        $raw = ['times' => '', 'exceptions' => ''];

        $value = $fieldtype->___wakeupValue($this->makePage(), $field, $raw);

        $this->assertSame('3', $value->numberOftimes);
        $this->assertSame('H:i', $value->timeformat);
        $this->assertSame(1, $value->hideholiday);
    }

    public function testWakeupValueDecodesAndKeepsActiveExceptions(): void {
        $fieldtype = $this->makeFieldtype();
        $future = ['label' => 'Future', 'startDate' => '2099-01-01', 'endDate' => '2099-01-01', 'recurring' => false, 'closed' => true];
        $raw = ['times' => '', 'exceptions' => json_encode([$future])];

        $value = $fieldtype->___wakeupValue($this->makePage(), $this->makeField(), $raw);

        $this->assertSame([$future], $value->exceptions);
    }

    public function testWakeupValueFiltersOutExpiredNonRecurringExceptions(): void {
        $fieldtype = $this->makeFieldtype();
        $expired = ['label' => 'Past', 'startDate' => '2000-01-01', 'endDate' => '2000-01-02', 'recurring' => false, 'closed' => true];
        $recurring = ['label' => 'Christmas', 'startDate' => '2000-12-24', 'endDate' => '2000-12-24', 'recurring' => true, 'closed' => true];
        $raw = ['times' => '', 'exceptions' => json_encode([$expired, $recurring])];

        $value = $fieldtype->___wakeupValue($this->makePage(), $this->makeField(), $raw);

        $this->assertSame([$recurring], $value->exceptions);
    }

    public function testWakeupValueTreatsAMissingOrMalformedExceptionsColumnAsEmpty(): void {
        $fieldtype = $this->makeFieldtype();

        $value = $fieldtype->___wakeupValue($this->makePage(), $this->makeField(), ['times' => '', 'exceptions' => null]);
        $this->assertSame([], $value->exceptions);

        $value = $fieldtype->___wakeupValue($this->makePage(), $this->makeField(), ['times' => '', 'exceptions' => 'not json']);
        $this->assertSame([], $value->exceptions);
    }

    public function testSleepValueThrowsWhenGivenSomethingOtherThanOpeningHours(): void {
        $fieldtype = $this->makeFieldtype();
        $this->expectException(Exception::class);
        $fieldtype->___sleepValue($this->makePage(), $this->makeField(), 'not an OpeningHours instance');
    }

    public function testSleepValueEncodesTimesAndExceptionsAsJson(): void {
        $fieldtype = $this->makeFieldtype();
        $value = new OpeningHours();
        $value->set('times', ['mo' => [['start' => '08:00', 'finish' => '12:00']]]);
        $value->set('exceptions', [['label' => 'X', 'startDate' => '2026-01-01', 'endDate' => '2026-01-01', 'recurring' => false, 'closed' => true]]);

        $result = $fieldtype->___sleepValue($this->makePage(), $this->makeField(), $value);

        $this->assertSame(0, $result['data']);
        $this->assertSame(['mo' => [['start' => '08:00', 'finish' => '12:00']]], json_decode($result['times'], true));
        $this->assertCount(1, json_decode($result['exceptions'], true));
    }

    public function testSleepValueStoresAnEmptyJsonArrayWhenThereAreNoExceptions(): void {
        $fieldtype = $this->makeFieldtype();
        $value = new OpeningHours();
        $value->set('times', ['mo' => [['start' => '', 'finish' => '']]]);
        $value->set('exceptions', []);

        $result = $fieldtype->___sleepValue($this->makePage(), $this->makeField(), $value);

        $this->assertSame('[]', $result['exceptions']);
    }

    public function testSanitizeValueReplacesANonOpeningHoursValueWithABlankOne(): void {
        $fieldtype = $this->makeFieldtype();
        $page = $this->makePage();

        $result = $fieldtype->sanitizeValue($page, $this->makeField(), 'not an OpeningHours instance');

        $this->assertInstanceOf(OpeningHours::class, $result);
    }

    public function testSanitizeValueTracksAPageChangeWhenTimesOrExceptionsChanged(): void {
        $fieldtype = $this->makeFieldtype();
        $page = $this->makePage();
        $field = $this->makeField(['name' => 'opening_hours']);

        $value = $fieldtype->getBlankValue($page, $field);
        $value->set('times', ['mo' => [['start' => '08:00', 'finish' => '12:00']]]);

        $fieldtype->sanitizeValue($page, $field, $value);

        $this->assertSame(['opening_hours'], $page->trackedChanges);
    }

    public function testSanitizeValueDoesNotTrackAChangeWhenNothingChanged(): void {
        $fieldtype = $this->makeFieldtype();
        $page = $this->makePage();
        $field = $this->makeField();

        $value = $fieldtype->getBlankValue($page, $field);
        // no set() calls at all - nothing changed since the value was created

        $fieldtype->sanitizeValue($page, $field, $value);

        $this->assertSame([], $page->trackedChanges);
    }

    public function testFormatValueReturnsNullForANullValue(): void {
        $fieldtype = $this->makeFieldtype();
        $this->assertNull($fieldtype->formatValue($this->makePage(), $this->makeField(), null));
    }

    public function testFormatValueFormatsStartAndFinishTimesAccordingToTheFieldsTimeformat(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['timeformat' => 'H:i']);
        $value = new OpeningHours();
        $value->set('times', ['mo' => [['start' => '08:00', 'finish' => '12:00']]]);

        $formatted = $fieldtype->formatValue($this->makePage(), $field, $value);

        $this->assertSame('08:00', $formatted->times['mo'][0]['start']);
        $this->assertSame('12:00', $formatted->times['mo'][0]['finish']);
    }

    public function testFormatValueDoesNotMutateTheOriginalValue(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['timeformat' => 'H:i']);
        $value = new OpeningHours();
        $value->set('times', ['mo' => [['start' => '08:00', 'finish' => '12:00']]]);

        $fieldtype->formatValue($this->makePage(), $field, $value);

        // formatValue() clones its input - the original object passed in must be untouched
        $this->assertSame('08:00', $value->times['mo'][0]['start']);
    }

    public function testFormatValueUsesTheCurrentUsersLanguageSpecificTimeformatWhenSet(): void {
        $fieldtype = $this->makeFieldtype();
        // the field's own (default-language) format is '%R' -> mapped to 'H:i' by the test's
        // WireDateTime stub; the German field has its own override, "timeformat5", for
        // language id "5"
        $field = $this->makeField(['timeformat' => '%R', 'timeformat5' => 'H:i']);
        $value = new OpeningHours();
        $value->set('times', ['mo' => [['start' => '16:00', 'finish' => '20:00']]]);

        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true), // default language
            new TestLanguageStub('5', false), // German
        ]);
        TestWireContainer::setUserLanguage(new TestLanguageStub('5'));

        $formatted = $fieldtype->formatValue($this->makePage(), $field, $value);

        $this->assertSame('16:00', $formatted->times['mo'][0]['start']);
    }

    public function testFormatValueFallsBackToTheFieldsDefaultFormatWhenNoLanguageOverrideIsSet(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['timeformat' => 'H:i']); // no "timeformat5" override

        TestWireContainer::setLanguages([
            new TestLanguageStub('1', true),
            new TestLanguageStub('5', false),
        ]);
        TestWireContainer::setUserLanguage(new TestLanguageStub('5'));

        $value = new OpeningHours();
        $value->set('times', ['mo' => [['start' => '16:00', 'finish' => '20:00']]]);

        $formatted = $fieldtype->formatValue($this->makePage(), $field, $value);

        $this->assertSame('16:00', $formatted->times['mo'][0]['start']);
    }

    public function testGetInputfieldReturnsWhateverTheModulesApiVariableProvides(): void {
        $fieldtype = $this->makeFieldtype();
        $inputfield = $fieldtype->getInputfield($this->makePage(), $this->makeField());

        // the test's TestModulesStub registers a fixed marker value for this module name
        $this->assertSame('stub-InputfieldOpeningHours', $inputfield);
    }
}
