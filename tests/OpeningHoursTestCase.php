<?php

declare(strict_types=1);

namespace ProcessWire\Tests;

use PHPUnit\Framework\TestCase;
use ProcessWire\OpeningHours;
use ProcessWire\TestWireContainer;
use ReflectionMethod;

/**
 * Common helpers for testing OpeningHours.php: building an instance with a given set of
 * weekly times/exceptions, and calling its protected/private methods via reflection (several
 * of the methods under test - fe exceptionMatchesDate(), getDaysInMonth() - are intentionally
 * not part of the module's public API).
 */
abstract class OpeningHoursTestCase extends TestCase {
    protected function tearDown(): void {
        TestWireContainer::reset();
        parent::tearDown();
    }

    /**
     * Build an OpeningHours instance with default (all-closed) weekly times, then apply any
     * overrides (fe ['times' => [...], 'exceptions' => [...], 'timeformat' => '%R']).
     */
    protected function makeOpeningHours(array $overrides = []): OpeningHours {
        $openingHours = new OpeningHours();
        foreach ($overrides as $key => $value) {
            $openingHours->set($key, $value);
        }
        return $openingHours;
    }

    /** A single ['start' => ..., 'finish' => ...] time pair, or a closed (empty) one. */
    protected static function timePair(string $start = '', string $finish = ''): array {
        return ['start' => $start, 'finish' => $finish];
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
