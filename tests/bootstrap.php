<?php

declare(strict_types=1);

namespace ProcessWire;

/*
 * Minimal stand-ins for the ProcessWire core classes/functions OpeningHours.php relies on
 * (WireData, wire(), __(), WireDateTime), so the module class can be unit tested outside of
 * a full ProcessWire installation. These stubs implement just enough behavior for the tests
 * in this suite - they are not a general-purpose ProcessWire mock.
 */

if (!function_exists(__NAMESPACE__ . '\\__')) {
    /**
     * Stand-in for ProcessWire's own translation function: returns the text unchanged
     * (there is no .po file loaded in tests).
     */
    function __(string $text, string $textdomain = '', string $context = ''): string {
        return $text;
    }
}

if (!function_exists(__NAMESPACE__ . '\\wire')) {
    /**
     * Stand-in for ProcessWire's own wire() API-variable accessor, backed by
     * TestWireContainer below.
     */
    function wire(?string $name = null) {
        return TestWireContainer::get($name);
    }
}

/**
 * Stand-in for the $sanitizer API variable, covering only the two calls OpeningHours.php
 * makes on it: date() (used by formatTimestring() to parse a time string into a timestamp)
 * and minArray() (used by combinedDays() to drop fully-empty/closed days).
 */
class TestSanitizerStub {
    public function date($value, $format = null) {
        $ts = strtotime((string)$value);
        return $ts === false ? null : $ts;
    }

    public function minArray(array $value): array {
        return array_filter($value, function ($dayTimes) {
            foreach ((array)$dayTimes as $pair) {
                if (array_filter((array)$pair)) {
                    return true;
                }
            }
            return false;
        });
    }

    /** Minimal stand-in for $sanitizer->text(): strips markup and surrounding whitespace. */
    public function text($value): string {
        return trim(strip_tags((string)$value));
    }
}

/** Stand-in for the $page API variable: only ->url is read (the admin-vs-frontend guard). */
class TestPageStub {
    public string $url = '/some-page/';
}

/** Stand-in for $config->urls. */
class TestUrlsStub {
    public string $admin = '/processwire/';
}

/** Stand-in for the $config API variable: only ->urls->admin is read. */
class TestConfigStub {
    public TestUrlsStub $urls;

    public function __construct() {
        $this->urls = new TestUrlsStub();
    }
}

/** Stand-in for a ProcessWire Language page, as far as ->id and isDefault() are read. */
class TestLanguageStub {
    public function __construct(
        public string $id,
        protected bool $default = false,
        public string $title = '',
        public string $name = ''
    ) {
    }

    public function isDefault(): bool {
        return $this->default;
    }

    /**
     * Real ProcessWire Language pages resolve to their id when used directly in a string
     * context (fe "$field->set('value' . $language, ...)" - a documented PW idiom used
     * throughout this module's per-language config properties, "timeformat$language" /
     * "tableheader$language" / "value$language"), not to their name/title.
     */
    public function __toString(): string {
        return $this->id;
    }
}

/** Stand-in for the $user API variable: only ->language is read. */
class TestUserStub {
    public ?TestLanguageStub $language = null;
}

/** Stand-in for the $languages API variable: a plain iterable, countable list of Languages. */
class TestLanguagesStub implements \IteratorAggregate, \Countable {
    /** @var TestLanguageStub[] */
    protected array $languages = [];

    /** @param TestLanguageStub[] $languages */
    public function setAll(array $languages): void {
        $this->languages = $languages;
    }

    public function getIterator(): \Iterator {
        return new \ArrayIterator($this->languages);
    }

    public function count(): int {
        return count($this->languages);
    }
}

/**
 * Tiny service container backing the wire() stub above, plus test-only helpers to adjust
 * the stubbed environment (current page URL, current user language) between test cases.
 */
class TestWireContainer {
    protected static ?TestSanitizerStub $sanitizer = null;
    protected static ?TestPageStub $page = null;
    protected static ?TestConfigStub $config = null;
    protected static ?TestUserStub $user = null;
    protected static ?TestModulesStub $modules = null;
    protected static ?TestFieldsStub $fields = null;
    protected static ?TestPagesStub $pages = null;
    protected static ?TestDatabaseStub $database = null;
    protected static ?TestLanguagesStub $languages = null;

    public static function get(?string $name) {
        switch ($name) {
            case 'sanitizer':
                return self::$sanitizer ??= new TestSanitizerStub();
            case 'page':
                return self::$page ??= new TestPageStub();
            case 'config':
                return self::$config ??= new TestConfigStub();
            case 'user':
                return self::$user ??= new TestUserStub();
            case 'modules':
                return self::$modules ??= new TestModulesStub();
            case 'fields':
                return self::$fields ??= new TestFieldsStub();
            case 'pages':
                return self::$pages ??= new TestPagesStub();
            case 'database':
                return self::$database ??= new TestDatabaseStub();
            case 'languages':
                // NOT auto-vivified: a single-language site has no $languages API variable
                // at all (wire('languages') is falsy), which several methods branch on -
                // tests opt into multi-language via setLanguages() below
                return self::$languages;
            default:
                return null;
        }
    }

    /** Point the stubbed $page->url somewhere else (fe into the admin, to test the guard). */
    public static function setPageUrl(string $url): void {
        self::get('page')->url = $url;
    }

    /** Set (or clear, with null) the stubbed current user's language. */
    public static function setUserLanguage(?TestLanguageStub $language): void {
        self::get('user')->language = $language;
    }

    /**
     * Turn the stubbed site into a multi-language one with exactly these languages (one of
     * them should have $isDefault true). Leaving this uncalled keeps wire('languages') falsy,
     * matching a single-language site.
     * @param TestLanguageStub[] $languages
     */
    public static function setLanguages(array $languages): void {
        self::$languages = new TestLanguagesStub();
        self::$languages->setAll($languages);
    }

    /** Reset every stub back to its default state; call from tearDown(). */
    public static function reset(): void {
        self::$sanitizer = null;
        self::$page = null;
        self::$config = null;
        self::$user = null;
        self::$modules = null;
        self::$fields = null;
        self::$pages = null;
        self::$database = null;
        self::$languages = null;
    }
}

/**
 * Stand-in for ProcessWire's WireDateTime, covering only formatDate() (used by
 * OpeningHours::formatDate()) with just enough strftime-style translation to support the
 * formats this module actually uses ('%R' by default, 'H:i' for JSON-LD).
 */
class WireDateTime {
    public static function _getTimeFormats(): array {
        return [];
    }

    public function formatDate($value, string $format): string {
        if ($value === null || $value === '' || $value === false) {
            return '';
        }
        if (strpos($format, '%') !== false) {
            $map = [
                '%R' => 'H:i',
                '%H' => 'H',
                '%M' => 'i',
                '%I' => 'h',
                '%p' => 'A',
                '%r' => 'h:i A',
            ];
            $format = strtr($format, $map);
        }
        return date($format, (int)$value);
    }
}

/**
 * Stand-in for ProcessWire's WireData base class: a minimal get/set property bag, the change
 * tracking (setTrackChanges()/isChanged()) that Wire (WireData's real parent) provides and
 * that FieldtypeOpeningHours.module relies on, the _() translation passthrough, and a
 * fallback in __get() to the wire() API variables (fe $this->modules), the same as Wire's own
 * __get() falls back to the Fuel/API variables for any property name it doesn't recognize
 * itself; and ArrayAccess (fe $obj['times'] = ...), delegating to get()/set() exactly like
 * ProcessWire's real WireData does - InputfieldOpeningHours::___processInput() relies on this
 * to write "$this->value['times'] = ..." into an OpeningHours instance.
 */
class WireData implements \ArrayAccess {
    protected array $data = [];
    protected bool $trackChanges = false;
    protected array $changedFields = [];

    public function __construct() {
    }

    public function set(string $key, $value) {
        if ($this->trackChanges && (!array_key_exists($key, $this->data) || $this->data[$key] !== $value)) {
            $this->changedFields[$key] = true;
        }
        $this->data[$key] = $value;
        return $this;
    }

    public function get(string $key) {
        return $this->data[$key] ?? null;
    }

    public function __get($key) {
        if (array_key_exists($key, $this->data)) {
            return $this->data[$key];
        }
        // fall back to an API variable of the same name (fe ->modules, ->fields), same as
        // Wire::__get() does in real ProcessWire
        return wire($key);
    }

    public function __set($key, $value): void {
        $this->set($key, $value);
    }

    /** Stand-in for Wire::setTrackChanges(). */
    public function setTrackChanges(bool $track = true) {
        $this->trackChanges = $track;
        return $this;
    }

    /** Stand-in for Wire::isChanged(): with no argument, whether anything changed at all. */
    public function isChanged(?string $key = null): bool {
        if ($key === null) {
            return count($this->changedFields) > 0;
        }
        return !empty($this->changedFields[$key]);
    }

    /** Stand-in for Wire::resetTrackChanges(). */
    public function resetTrackChanges(): void {
        $this->changedFields = [];
    }

    /** Stand-in for Wire::_(), ProcessWire's per-instance translation helper. */
    public function _(string $text): string {
        return $text;
    }

    /** Stand-in for Wire::wire(), the instance-method form of the global wire() function. */
    public function wire(?string $name = null) {
        return wire($name);
    }

    public function offsetExists($offset): bool {
        return array_key_exists($offset, $this->data);
    }

    public function offsetGet($offset): mixed {
        return $this->get((string)$offset);
    }

    public function offsetSet($offset, $value): void {
        $this->set((string)$offset, $value);
    }

    public function offsetUnset($offset): void {
        unset($this->data[$offset]);
    }
}

/**
 * Stand-in for ProcessWire's Fieldtype base class - just enough of its default (to-be-
 * overridden) hook implementations for FieldtypeOpeningHours.module's parent::___xyz() calls
 * to have something sensible to call.
 */
class Fieldtype extends WireData {
    public function __construct() {
        parent::__construct();
    }

    /** Base implementation is a pass-through; a subclass converts the raw value further. */
    public function ___wakeupValue(Page $page, Field $field, $value) {
        return $value;
    }

    /** Base implementation is a pass-through; a subclass converts to a storable array. */
    public function ___sleepValue(Page $page, Field $field, $value) {
        return $value;
    }

    /** Base schema every Fieldtype's table has, before a subclass adds its own columns. */
    public function getDatabaseSchema(Field $field): array {
        return [
            'data' => 'TEXT NOT NULL',
        ];
    }
}

/**
 * Stand-in for ProcessWire's Inputfield base class: WireData's property bag (used for config
 * properties such as numberOftimes/timeformat/hideholiday) plus the separate attr()-based
 * attribute storage (name/value/...) real Inputfields have, and the warning()/error()
 * user-notice methods InputfieldOpeningHours.module calls during input processing.
 */
class Inputfield extends WireData {
    protected array $attributes = [];
    /** @var string[] every message passed to warning(), in call order */
    public array $warnings = [];
    /** @var string[] every message passed to error(), in call order */
    public array $errors = [];

    public function __construct() {
        parent::__construct();
    }

    /** Stand-in for Inputfield::attr(): a getter with one argument, a setter with two. */
    public function attr($key, $value = null) {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->attributes[$k] = $v;
            }
            return $this;
        }
        if (func_num_args() > 1) {
            $this->attributes[$key] = $value;
            return $this;
        }
        return $this->attributes[$key] ?? null;
    }

    public function __get($key) {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }
        return parent::__get($key);
    }

    /** Stand-in for Wire::warning(): records a non-fatal, user-facing notice. */
    public function warning($text, $flags = 0) {
        $this->warnings[] = $text;
        return $this;
    }

    /** Stand-in for Wire::error(): records a user-facing validation error. */
    public function error($text, $flags = 0) {
        $this->errors[] = $text;
        return $this;
    }

    /** Stand-in for Wire::_n(), the plural-form translation helper (no real i18n needed here). */
    public function _n(string $textSingular, string $textPlural, int $count): string {
        return $count == 1 ? $textSingular : $textPlural;
    }
}

/**
 * Stand-in for ProcessWire's WireInputData (fe $input->post): an iterable, array-accessible
 * bag of raw submitted values, exactly as ___processInput(WireInputData $input) iterates over
 * it with a plain foreach.
 */
class WireInputData implements \IteratorAggregate, \ArrayAccess, \Countable {
    public function __construct(protected array $data = []) {
    }

    public function getIterator(): \Iterator {
        return new \ArrayIterator($this->data);
    }

    public function offsetExists($offset): bool {
        return isset($this->data[$offset]);
    }

    public function offsetGet($offset): mixed {
        return $this->data[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            $this->data[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void {
        unset($this->data[$offset]);
    }

    public function count(): int {
        return count($this->data);
    }
}

/**
 * Stand-in for ProcessWire's InputfieldWrapper, covering only the one static call
 * InputfieldOpeningHours::getExceptionInputClasses() makes (getMarkup(), to read the active
 * admin theme's field-label markup template). Defaults to no markup registered (as if no
 * admin theme were active), same as a plain single-language, default-theme test environment.
 */
class InputfieldWrapper {
    protected static array $markup = [];

    public static function getMarkup(): array {
        return self::$markup;
    }

    /** Simulate an admin theme having registered its field-label markup via setMarkup(). */
    public static function setTestMarkup(array $markup): void {
        self::$markup = $markup;
    }
}

/** Marker interface stand-in for ProcessWire's Module interface (only used in a type hint). */
interface Module {
}

/** Marker class stand-in for ProcessWire's internal _Module type (only used in a type hint). */
class _Module {
}

/**
 * Stand-in for a ProcessWire Field: a WireData property bag (name, numberOftimes,
 * timeformat, hideholiday, ...) plus getTable() and getTemplates(), set via
 * setTemplates() for cleanupExpiredExceptions() tests.
 */
class Field extends WireData {
    protected array $templates = [];

    public function getTable(): string {
        return 'field_' . ($this->get('name') ?: 'test');
    }

    /** @return TestTemplateStub[] */
    public function getTemplates(): array {
        return $this->templates;
    }

    /** @param TestTemplateStub[] $templates */
    public function setTemplates(array $templates): void {
        $this->templates = $templates;
    }
}

/** Stand-in for a ProcessWire Template: only ->name is read (by cleanupExpiredExceptions()). */
class TestTemplateStub {
    public function __construct(public string $name) {
    }
}

/**
 * Stand-in for a ProcessWire Page: a WireData property bag plus trackChange(), a settable
 * per-field "unformatted" value (as getUnformatted() would return) and a recorded save() call
 * list, so tests can both feed in field values and assert what got saved.
 */
class Page extends WireData {
    protected array $unformattedValues = [];
    /** @var string[] field names save() was called for, in call order */
    public array $savedFields = [];
    /** @var string[] field names trackChange() was called for, in call order */
    public array $trackedChanges = [];

    public function trackChange(string $what): void {
        $this->trackedChanges[] = $what;
    }

    public function setUnformatted(string $fieldName, $value): void {
        $this->unformattedValues[$fieldName] = $value;
    }

    public function getUnformatted(string $fieldName) {
        return $this->unformattedValues[$fieldName] ?? null;
    }

    public function save(string $fieldName): bool {
        $this->savedFields[] = $fieldName;
        return true;
    }
}

/** Stand-in for ProcessWire's HookEvent: cleanupExpiredExceptions() only needs the type. */
class HookEvent {
}

/**
 * Stand-in for the $modules API variable: get() returns whatever was registered via
 * registerModule() (default: a fixed marker string for 'InputfieldOpeningHours'), and
 * get/saveModuleConfigData() persist a small in-memory per-module-class config array, exactly
 * like ensureSchemaUpToDate() expects.
 */
class TestModulesStub {
    protected array $modules = ['InputfieldOpeningHours' => 'stub-InputfieldOpeningHours'];
    protected array $configData = [];

    public function registerModule(string $name, $instance): void {
        $this->modules[$name] = $instance;
    }

    public function get(string $name) {
        return $this->modules[$name] ?? null;
    }

    public function getModuleConfigData($module): array {
        return $this->configData[get_class($module)] ?? [];
    }

    public function saveModuleConfigData($module, array $data): void {
        $this->configData[get_class($module)] = $data;
    }
}

/**
 * Stand-in for the $fields API variable: find() ignores the selector string entirely (there
 * is no real selector engine here) and just returns whatever list the test pre-populated with
 * setAll() - fine since every test using this fully controls what "fields of this type"
 * exist.
 */
class TestFieldsStub {
    /** @var Field[] */
    protected array $all = [];

    /** @param Field[] $fields */
    public function setAll(array $fields): void {
        $this->all = $fields;
    }

    /** @return Field[] */
    public function find(string $selector): array {
        return $this->all;
    }
}

/** Stand-in for the $pages API variable: find() works exactly like TestFieldsStub::find(). */
class TestPagesStub {
    /** @var Page[] */
    protected array $all = [];

    /** @param Page[] $pages */
    public function setAll(array $pages): void {
        $this->all = $pages;
    }

    /** @return Page[] */
    public function find(string $selector): array {
        return $this->all;
    }
}

/** Tiny result-set stand-in for a PDOStatement, as far as fetchAll() is used. */
class TestDatabaseStatementStub {
    public function __construct(protected array $rows) {
    }

    public function fetchAll($mode = null): array {
        return $this->rows;
    }
}

/**
 * Stand-in for the $database API variable: query() only understands "SHOW COLUMNS FROM
 * `table`" (returning the columns set via setColumns()), and exec() just records every SQL
 * statement it was given (fe an "ALTER TABLE ... ADD ..."), so a test can assert on it and
 * also keep setColumns() in sync to simulate the column now existing.
 */
class TestDatabaseStub {
    /** @var array<string, string[]> table name => list of existing column names */
    protected array $columns = [];
    /** @var string[] every SQL statement passed to exec(), in call order */
    public array $executedStatements = [];

    public function setColumns(string $table, array $columns): void {
        $this->columns[$table] = $columns;
    }

    public function query(string $sql): TestDatabaseStatementStub {
        if (preg_match('/^SHOW COLUMNS FROM `([^`]+)`/', $sql, $m)) {
            return new TestDatabaseStatementStub($this->columns[$m[1]] ?? []);
        }
        return new TestDatabaseStatementStub([]);
    }

    public function exec(string $sql): void {
        $this->executedStatements[] = $sql;
        // keep setColumns() in sync so a second ensureColumnExists() call for the same
        // column sees it as already existing, same as a real ALTER TABLE would
        if (preg_match('/^ALTER TABLE `([^`]+)` ADD `([^`]+)`/', $sql, $m)) {
            $this->columns[$m[1]][] = $m[2];
        }
    }
}

require_once __DIR__ . '/../OpeningHours.php';
require_once __DIR__ . '/../FieldtypeOpeningHours.module';
require_once __DIR__ . '/../InputfieldOpeningHours.module';

// PHPUnit's directory-based test discovery only picks up files matching "*Test.php", so the
// shared base test cases (which don't themselves contain tests) have to be loaded explicitly.
require_once __DIR__ . '/OpeningHoursTestCase.php';
require_once __DIR__ . '/FieldtypeOpeningHoursTestCase.php';
require_once __DIR__ . '/InputfieldOpeningHoursTestCase.php';
