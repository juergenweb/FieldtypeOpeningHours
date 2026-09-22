<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use ProcessWire\Field;
use ProcessWire\HookEvent;
use ProcessWire\OpeningHours;
use ProcessWire\TestTemplateStub;
use ProcessWire\TestWireContainer;

/**
 * Tests for the database self-heal (ensureSchemaUpToDate()/ensureColumnExists()) and the
 * daily cleanup of expired, non-recurring exceptions (cleanupExpiredExceptions()) - both
 * driven entirely through the $fields/$pages/$database/$modules API-variable stubs, since
 * neither talks to the real (non-existent, in these tests) ProcessWire database.
 */
final class FieldtypeOpeningHoursSchemaAndCleanupTest extends FieldtypeOpeningHoursTestCase {

    public function testEnsureSchemaUpToDateAddsTheMissingColumnAndBumpsTheStoredVersion(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['name' => 'opening_hours']);

        TestWireContainer::get('fields')->setAll([$field]);
        TestWireContainer::get('database')->setColumns('field_opening_hours', ['id', 'pages_id', 'data']);
        // installedSchemaVersion defaults to 0 (no config data yet) - below SCHEMA_VERSION (1)

        self::callMethod($fieldtype, 'ensureSchemaUpToDate');

        $executed = TestWireContainer::get('database')->executedStatements;
        $this->assertCount(1, $executed);
        $this->assertStringContainsString('ALTER TABLE `field_opening_hours` ADD `exceptions`', $executed[0]);

        $savedConfig = TestWireContainer::get('modules')->getModuleConfigData($fieldtype);
        $this->assertSame(1, $savedConfig['schemaVersion']);
    }

    public function testEnsureSchemaUpToDateSkipsColumnsThatAlreadyExist(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['name' => 'opening_hours']);

        TestWireContainer::get('fields')->setAll([$field]);
        // 'exceptions' column is already there
        TestWireContainer::get('database')->setColumns('field_opening_hours', ['id', 'pages_id', 'data', 'exceptions']);

        self::callMethod($fieldtype, 'ensureSchemaUpToDate');

        $this->assertSame([], TestWireContainer::get('database')->executedStatements);
    }

    public function testEnsureSchemaUpToDateDoesNothingOnceAlreadyAtTheCurrentVersion(): void {
        $fieldtype = $this->makeFieldtype();
        TestWireContainer::get('modules')->saveModuleConfigData($fieldtype, ['schemaVersion' => 1]);
        TestWireContainer::get('fields')->setAll([$this->makeField()]);
        TestWireContainer::get('database')->setColumns('field_test', []);

        self::callMethod($fieldtype, 'ensureSchemaUpToDate');

        // no columns should have been inspected/altered - the version check short-circuits
        // the whole method before it ever touches $fields or $database
        $this->assertSame([], TestWireContainer::get('database')->executedStatements);
    }

    public function testEnsureColumnExistsAddsTheColumnWhenMissing(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['name' => 'opening_hours']);
        TestWireContainer::get('database')->setColumns('field_opening_hours', ['id']);

        self::callMethod($fieldtype, 'ensureColumnExists', [$field, 'exceptions', 'TEXT DEFAULT NULL']);

        $this->assertSame(
            ['ALTER TABLE `field_opening_hours` ADD `exceptions` TEXT DEFAULT NULL'],
            TestWireContainer::get('database')->executedStatements
        );
    }

    public function testEnsureColumnExistsDoesNothingWhenTheColumnAlreadyExists(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['name' => 'opening_hours']);
        TestWireContainer::get('database')->setColumns('field_opening_hours', ['id', 'exceptions']);

        self::callMethod($fieldtype, 'ensureColumnExists', [$field, 'exceptions', 'TEXT DEFAULT NULL']);

        $this->assertSame([], TestWireContainer::get('database')->executedStatements);
    }

    public function testCleanupExpiredExceptionsSkipsFieldsWithNoTemplates(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(); // no setTemplates() call -> empty list
        TestWireContainer::get('fields')->setAll([$field]);
        // if the method didn't skip early, it would call $pages->find() next; leaving the
        // pages stub empty either way, so what actually matters here is that save() is
        // never reached below
        TestWireContainer::get('pages')->setAll([$this->pageWithExceptions([$this->expiredException()])]);

        $fieldtype->cleanupExpiredExceptions(new HookEvent());

        $this->assertSame([], TestWireContainer::get('pages')->find('')[0]->savedFields);
    }

    public function testCleanupExpiredExceptionsRemovesOnlyExpiredNonRecurringExceptionsAndSaves(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['name' => 'opening_hours']);
        $field->setTemplates([new TestTemplateStub('event')]);
        TestWireContainer::get('fields')->setAll([$field]);

        $expired = $this->expiredException();
        $recurring = ['label' => 'Christmas', 'startDate' => '2000-12-24', 'endDate' => '2000-12-24', 'recurring' => true, 'closed' => true];
        $page = $this->pageWithExceptions([$expired, $recurring]);
        TestWireContainer::get('pages')->setAll([$page]);

        $fieldtype->cleanupExpiredExceptions(new HookEvent());

        $this->assertSame(['opening_hours'], $page->savedFields);
        $this->assertSame([$recurring], $page->getUnformatted('opening_hours')->exceptions);
    }

    public function testCleanupExpiredExceptionsDoesNotSaveWhenNothingExpired(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['name' => 'opening_hours']);
        $field->setTemplates([new TestTemplateStub('event')]);
        TestWireContainer::get('fields')->setAll([$field]);

        $recurring = ['label' => 'Christmas', 'startDate' => '2000-12-24', 'endDate' => '2000-12-24', 'recurring' => true, 'closed' => true];
        $page = $this->pageWithExceptions([$recurring]);
        TestWireContainer::get('pages')->setAll([$page]);

        $fieldtype->cleanupExpiredExceptions(new HookEvent());

        $this->assertSame([], $page->savedFields);
    }

    public function testCleanupExpiredExceptionsSkipsPagesWhoseValueIsNotAnOpeningHoursInstance(): void {
        $fieldtype = $this->makeFieldtype();
        $field = $this->makeField(['name' => 'opening_hours']);
        $field->setTemplates([new TestTemplateStub('event')]);
        TestWireContainer::get('fields')->setAll([$field]);

        $page = $this->makePage();
        $page->setUnformatted('opening_hours', 'not an OpeningHours instance');
        TestWireContainer::get('pages')->setAll([$page]);

        // must not throw despite the malformed value, and must not try to save it
        $fieldtype->cleanupExpiredExceptions(new HookEvent());

        $this->assertSame([], $page->savedFields);
    }

    private function expiredException(): array {
        return ['label' => 'Past event', 'startDate' => '2000-01-01', 'endDate' => '2000-01-02', 'recurring' => false, 'closed' => true];
    }

    private function pageWithExceptions(array $exceptions): \ProcessWire\Page {
        $page = $this->makePage();
        $value = new OpeningHours();
        $value->set('exceptions', $exceptions);
        $page->setUnformatted('opening_hours', $value);
        return $page;
    }
}
