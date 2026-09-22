<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use PHPUnit\Framework\TestCase;
use ProcessWire\InputfieldOpeningHours;
use ProcessWire\InputfieldWrapper;
use ProcessWire\OpeningHours;
use ProcessWire\TestWireContainer;
use ReflectionMethod;

/**
 * Common helpers for testing InputfieldOpeningHours.module: building the inputfield (with its
 * "name" attribute and configuration properties set), and calling protected/private methods
 * via reflection.
 */
abstract class InputfieldOpeningHoursTestCase extends TestCase {
    protected function tearDown(): void {
        TestWireContainer::reset();
        InputfieldWrapper::setTestMarkup([]);
        parent::tearDown();
    }

    /**
     * A ready-to-use InputfieldOpeningHours with attr('name') set and the given configuration
     * property overrides applied (fe ['numberOftimes' => 3]).
     */
    protected function makeInputfield(string $name = 'opening_hours', array $configOverrides = []): InputfieldOpeningHours {
        $inputfield = new InputfieldOpeningHours();
        $inputfield->attr('name', $name);
        foreach ($configOverrides as $key => $value) {
            $inputfield->set($key, $value);
        }
        return $inputfield;
    }

    /** A blank OpeningHours value with the given weekly times/exceptions overrides. */
    protected function makeValue(array $overrides = []): OpeningHours {
        $value = new OpeningHours();
        foreach ($overrides as $key => $value2) {
            $value->set($key, $value2);
        }
        return $value;
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
