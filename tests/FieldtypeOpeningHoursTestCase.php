<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use PHPUnit\Framework\TestCase;
use ProcessWire\Field;
use ProcessWire\FieldtypeOpeningHours;
use ProcessWire\Page;
use ProcessWire\TestWireContainer;
use ReflectionMethod;

/**
 * Common helpers for testing FieldtypeOpeningHours.module: building the fieldtype, a Field
 * and a Page stub, and calling protected methods (fe ensureSchemaUpToDate()) via reflection.
 */
abstract class FieldtypeOpeningHoursTestCase extends TestCase {
    protected function tearDown(): void {
        TestWireContainer::reset();
        parent::tearDown();
    }

    protected function makeFieldtype(): FieldtypeOpeningHours {
        return new FieldtypeOpeningHours();
    }

    /** A Field with the given property overrides (fe ['numberOftimes' => '3']). */
    protected function makeField(array $overrides = []): Field {
        $field = new Field();
        $field->set('name', 'opening_hours');
        $field->set('numberOftimes', '2');
        $field->set('timeformat', '%R');
        $field->set('hideholiday', 0);
        foreach ($overrides as $key => $value) {
            $field->set($key, $value);
        }
        return $field;
    }

    protected function makePage(): Page {
        return new Page();
    }

    /** Call a protected/private instance or static method by name and return its result. */
    protected static function callMethod($objectOrClass, string $method, array $args = []) {
        $reflection = new ReflectionMethod($objectOrClass, $method);
        $reflection->setAccessible(true);
        if (is_string($objectOrClass)) {
            return $reflection->invokeArgs(null, $args);
        }
        return $reflection->invokeArgs($objectOrClass, $args);
    }
}
