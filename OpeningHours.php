<?php
declare(strict_types=1);

namespace ProcessWire;

use DateTime;

/**
 * Helper WireData Class
 */
class OpeningHours extends WireData
{
    const DEFAULTTIMEFORMAT = '%R';

    /**
     * Constructor. Sets the default values for this object (opening times, time format,
     * number of times per day, whether holidays are hidden, and an empty exceptions list)
     * so they are present even before the database values are loaded/set.
     */
    public function __construct()
    {
        parent::__construct();

        //set default values if they are not present inside the database
        $this->set('times', json_decode(file_get_contents(__DIR__ . '/defaultData.json'), true));
        $this->set('timeformat', self::DEFAULTTIMEFORMAT);
        $this->set('numberOftimes', '2');
        $this->set('hideholiday', 0);
        // exceptions (holidays/company vacation etc.) - configured per field, copied in by
        // FieldtypeOpeningHours::___wakeupValue() from $field->exceptions
        $this->set('exceptions', []);
    }

    /**
     * Helper methods for arrays
     */

    /**
     * Method to return a multidimensional array with day abbreviations and daynames
     * First item in sub-array is the abbreviation and the second the fullname of the day
     * @return array
     */
    public static function getWeekdays(): array
    {
        $mo1 = __('Mo');
        $mo2 = __('Monday');
        $tu1 = __('Tu');
        $tu2 = __('Tuesday');
        $we1 = __('We');
        $we2 = __('Wednesday');
        $th1 = __('Th');
        $th2 = __('Thursday');
        $fr1 = __('Fr');
        $fr2 = __('Friday');
        $sa1 = __('Sa');
        $sa2 = __('Saturday');
        $su1 = __('Su');
        $su2 = __('Sunday');
        $ho1 = __('Ho');
        $ho2 = __('Holiday');

        return [
            'mo' => [$mo1, $mo2],
            'tu' => [$tu1, $tu2],
            'we' => [$we1, $we2],
            'th' => [$th1, $th2],
            'fr' => [$fr1, $fr2],
            'sa' => [$sa1, $sa2],
            'su' => [$su1, $su2],
            'ho' => [$ho1, $ho2]
        ];
    }

    /**
     * Method to return only the lowercase day abbreviations in an array (['mo','tu',....])
     * @return array
     */
    public static function getDayAbbreviations(): array
    {
        $days = [];
        foreach (self::getWeekdays() as $v) {
            $days[] = $v;
        }
        return $days;
    }

    /**
     * Create a multidimensional array of all times from the one-dimensional POST array after form submission
     * This step is required for various sanitizations and validations of the input values which cannot
     * be processed from a one-dimensional array
     * @param array $values
     * @return array
     */
    public static function createMultidimArray(array $values): array
    {
        // Avoid double conversion
        if (self::isMultidimArray($values)) {
            return $values;
        }

        $temp_array = [];
        foreach (self::getWeekdays() as $key => $dayname) {
            foreach ($values as $k => $v) {
                if ($key === (explode('-', $k)[1])) {
                    $temp_array[$key][] = $v;
                }
            }
        }
        $newArray = [];
        foreach ($temp_array as $day => $values) {
            $daytimes = array_chunk($values, 2);
            //change keys from numeric to assoc
            $daytimes = array_map(function ($tag) {
                return array(
                    'start' => $tag[0],
                    'finish' => $tag[1]
                );
            }, $daytimes);
            $newArray[$day] = $daytimes;
        }
        return $newArray;
    }

    /**
     * Check if the array is already multidimensional
     * This check is required to avoid double-converting values
     * in some circumstances, e.g. hidden fieldsets
     * @param array $array
     * @return bool
     */
    protected static function isMultidimArray(array $array): bool
    {
        $firstItem = reset($array);
        return is_array($firstItem);
    }

    /**
     * Method to flatten the multidimensional array back to a one-dimensional array like we have got from POST
     * @param array $array - multidimensional array
     * @param string $fieldname - the name of the field
     * @return array
     */
    public static function flattenArray(array $array, string $fieldname): array
    {
        $flattenArray = [];
        foreach (self::getWeekdays() as $key => $day) { //key = mo,tu,... day = [Mo => Monday]

            foreach ($array as $dayAbbr => $timesarray) { // key = mo,tu,...value = [times array]

                if ($key === $dayAbbr) {
                    foreach ($timesarray as $keyNum => $dayArray) { // key = 0,1,2, value = ['start' => '08:00', 'finish' => '16:00']

                        foreach ($dayArray as $name => $value) {
                            $createdKey = $fieldname . '-' . $key . '-' . $keyNum . '-' . $name;
                            $flattenArray[$createdKey] = $value;
                        }
                    }
                }
            }
        }
        return $flattenArray;
    }

    /* Validation methods */

    /**
     * Checks if input data is a valid time string
     * @param string $time_str
     * @param string $format
     * @return boolean
     */
    public static function isTimeValid(string $time_str, string $format = 'H:i'): bool
    {
        $DateTime = DateTime::createFromFormat("d/m/Y $format", "10/10/2010 $time_str");
        return $DateTime && $DateTime->format("d/m/Y $format") == "10/10/2010 $time_str";
    }

    /**
     * Return all predefined PHP date() formats for use as times
     *
     * Note: this method moved to the WireDateTime class and is kept here for backwards compatibility.
     *
     * @return array
     *
     * @deprecated Use WireDateTime class instead
     */
    public static function getTimeFormats(): array
    {
        return WireDateTime::_getTimeFormats();
    }

    /**
     * Format a date with the given PHP date() or PHP strftime() format
     *
     * Note: this method moved to the WireDateTime class and is kept here for backwards compatibility.
     *
     * @param int $value Unix timestamp of date
     * @param string $format date() or strftime() format string to use for formatting
     * @return string Formatted date string
     * @deprecated Use WireDateTime class instead
     *
     */
    public static function formatDate(int $value, string $format): string
    {
        $wdt = new WireDateTime();
        return $wdt->formatDate($value, $format);
    }

    /**
     * Format a time value according to the format settings in the field configuration
     * fe 16:00 will be formatted to 04:00 AM if strftime setting is %r
     * @param string|null $time
     * @param string|null $timeformat
     * @return string
     */
    public static function formatTimestring(?string $time, ?string $timeformat = null): string
    {
        if ($time) {
            $timeStamp = wire('sanitizer')->date('10.10.2010 ' . $time);
            $timeformat = $timeformat ?: self::DEFAULTTIMEFORMAT;
            $time = OpeningHours::formatDate($timeStamp, $timeformat);
        }
        // normalize to an empty string (rather than returning $time, which may still be null
        // here since the parameter itself is nullable) - every caller already treats an empty
        // string as "no time set", and the declared return type isn't nullable
        return $time ?? '';
    }

    /**
     * Renders a string of opening times on a specific day (without the day name)
     * @param string $day
     * @param array $options
     * timeseparator: string -> separator between multiple times (default: ,)
     * timesuffix: string -> add text after timestring (default: '')
     * showClosed: true/false -> show closed days or not (default: true)
     * timetag: false/string -> add a surrounding tag (fe div) or none (false) -> default: false
     * timeclass: string -> add a custom CSS class for the timetag (default: '')
     * @return string
     */
    public function renderDay(string $day, array $options = []): string
    {
        $out = '';

        // do not run in backend
        if (strpos(wire('page')->url, wire('config')->urls->admin) !== 0) {
            // merge in the defaults up front, before anything below reads from $options - a
            // caller going through render() already did this (so this is a harmless no-op
            // for that case), but renderDay() is also public API in its own right, and a
            // direct call with a partial (or no) $options array would otherwise trigger an
            // "undefined array key" warning the moment $options['timetag'] is read next
            $options = array_merge($this->defaultOptions(), $options);

            // add a wrapper tag for the times if set
            if ($options['timetag']) {
                $out .= '<' . $options['timetag'];
                $timeClass[] = 'oh-time';
                $timeClass[] = $day;
                if ($options['timeclass']) {
                    $timeClass[] = $options['timeclass'];
                }
                $out .= ' class="' . implode(' ', $timeClass) . '">';
            }

            if ($this->times) {
                //opening_hours-mo-0-start
                $getTimes = $this->times[$day] ?? [];
                $times = [];

                if ((is_array($getTimes)) && (count($getTimes))) {
                    $numberOfTimes = count($getTimes);

                    // add time suffix if set
                    if ($options['timesuffix']) {
                        $timesuffix = ' ' . $options['timesuffix'];
                    } else {
                        $timesuffix = '';
                    }
                    if ($numberOfTimes > 1) { // multiple opening times per day
                        foreach ($getTimes as $value) {
                            $times[] = $value['start'] . ' - ' . $value['finish'] . $timesuffix;
                        }
                        $out .= implode($options['timeseparator'], $times);
                    } else { // single opening time or closed
                        if (array_filter($getTimes[0])) {
                            $times[] = $getTimes[0]['start'] . ' - ' . $getTimes[0]['finish'] . $timesuffix;
                            $out .= implode('-', $times);
                        } else {
                            $closed = ($options['showClosed']) ? $this->_('closed') : '';
                            $out .= $closed;
                        }
                    }
                }
            }

            // enter closing tag for the times if set
            if ($options['timetag']) {
                $out .= '</' . $options['timetag'] . '>';
            }
        }
        return $out;
    }

    /**
     * Alias function for renderDay() because this function name fits better
     * @param string $day
     * @param array $options
     * @return string
     */
    public function renderDayTime(string $day, array $options = []): string
    {
        return $this->renderDay($day, $options);
    }

    /**
     * Array, that contains all default rendering settings
     * @return array
     */
    protected function defaultOptions(): array
    {
        return [
            'wrappertag' => 'ul',
            'wrapperclass' => '',
            'itemtag' => 'li',
            'daytag' => false,
            'dayclass' => '',
            'timetag' => false,
            'timeclass' => '',
            'daytimeseparator' => ':',
            'fulldayName' => false,
            'timeseparator' => ', ',
            'timesuffix' => '',
            'showClosed' => true,
            'closedText' => $this->_('closed')
        ];
    }

    /**
     * Internal method to render the day name including/excluding the day time separator
     * @param string $dayAbbr - the day abbreviation (fe mo)
     * @param string $dayName - the day name (fe Monday)
     * @param array $options - the options array containing the settings
     * @return string
     */
    protected function renderDayName(string $dayAbbr, string $dayName, array $options, bool $combined = false): string
    {
        $out = '';

        // do not run in backend
        if (strpos(wire('page')->url, wire('config')->urls->admin) !== 0) {
            // check if day is closed or not
            if (!$combined) {
                $times = $this->times[$dayAbbr][0]['start'] ?? '';
                // hide closed days and day is closed
                if ((!$options['showClosed']) && (!$times)) {
                    return $out;
                } // return empty string
            }


            // add surrounding tag for day name if set
            if ($options['daytag']) {
                $out .= '<' . $options['daytag'];
                if (!empty($dayAbbr)) {
                    $dayClass[] = 'oh-day day-' . $dayAbbr;
                } else {
                    $dayClass[] = 'oh-day';
                }
                if ($options['dayclass']) {
                    $dayClass[] = $options['dayclass'];
                }
                $out .= ' class="' . implode(' ', $dayClass) . '">';
            }
            $out .= $dayName;

            // add day-time-separator if set
            if ($options['daytimeseparator']) {
                $out .= $options['daytimeseparator'];
            }

            if ($options['daytag']) {
                $out .= '</' . $options['daytag'] . '>';
            }
        }
        return $out;
    }

    /**
     * Internal function to change the key name "ulclass" to "wrapperclass" inside the options array
     * This key name was changed in version 1.1, so this is only as a fallback for older versions
     * @param array $options
     * @return array
     */
    protected function convertUlClassFallback(array $options): array
    {
        if (array_key_exists('ulclass', $options)) {
            $options['wrapperclass'] = $options['ulclass'];
            unset($options['ulclass']);
        }
        return $options;
    }

    /**
     * This is the base Method to render all times per week
     * @param array $options
     *
     * Description of all possible options to influence the rendering process
     *
     * wrappertag: set the tag for the outer container (default is ul)
     * itemtag: set the outer tag for the container containing the day opening times per day (default is li)
     * daytag: the tag element which surrounds the day name (default: false -> not surrounding element)
     * timetag: the tag element which surrounds the opening times on that day (default: false -> not surrounding element)
     * dayclass: a CSS class  for the daytag element (default: false -> no class)
     * timeclass: a CSS class  for the timetag element (default: false -> no class)
     * daytimeseparator: add a string to separate the day name and the times or add false to remove it (default is :)
     * wrapperclass: add a CSS class to the wrapper tag (default: '')
     * fulldayName: show fullname (true) or dayname abbreviation (false) -> (default: false)
     * timeseparator: separator between multiple times (default: ,)
     * timesuffix: add text after timestring (default: '')
     * showClosed: true/false show closed days or not (default: true)
     * closedText: overwrite the default text for closed days (default is closed)
     *
     * By default, all times will be rendered as an unordered list, until you change the tags
     *
     * @return string
     */
    public function render(array $options = []): string
    {
        // fallback for older versions -> convert key "ulclass" to "wrapperclass" because it was renamed in version 1.1
        $options = $this->convertUlClassFallback($options);

        $options = array_merge($this->defaultOptions(), $options);

        // start creating the markup
        $out = '';

        // do not run in backend
        if (strpos(wire('page')->url, wire('config')->urls->admin) !== 0) {
            // add opening wrapper tag if set
            if ($options['wrappertag']) {
                $out .= '<' . $options['wrappertag'];
                $out .= $options['wrapperclass'] ? ' class="' . $options['wrapperclass'] . '"' : '';
                $out .= '>';
            }

            $days = self::getWeekdays();

            // remove Holiday opening times according to the settings
            if ($this->hideholiday) {
                unset($days['ho']);
            }


            // loop over all weekdays
            foreach ($days as $day => $name) {
                // set opening item tag if set
                $out .= $options['itemtag'] ? '<' . $options['itemtag'] . ' class="time day-' . $day . '">' : '';

                // add surrounding tag for day name if set
                $dayName = $options['fulldayName'] ? $name[1] : $name[0];
                $out .= $this->renderDayName($day, $dayName, $options);

                // render all times on that day
                $out .= ' ' . $this->renderDay($day, $options);

                // set closing item tag if set
                $out .= $options['itemtag'] ? '</' . $options['itemtag'] . '>' : '';
            }

            // add closing wrapper tag if set
            if ($options['wrappertag']) {
                $out .= '</' . $options['wrappertag'] . '>';
            }
        }
        return $out;
    }

    /**
     * Method to output a multidimensional array containing all days with same times combined
     * @param bool $showClosed -> true: closed days will be displayed; false: closed days will not be displayed
     * @return array
     */
    public function combinedDays(bool $showClosed = true): array
    {
        $equalDays = [];
        if ($showClosed) {
            $allOpeningHours = $this->times;
        } else {
            //remove all empty (closed) times
            $allOpeningHours = wire('sanitizer')->minArray($this->times);
        }
        $uniqueOpeningHours = array_unique($allOpeningHours, SORT_REGULAR);
        $nonUniqueOpeningHours = $allOpeningHours;

        foreach ($uniqueOpeningHours as $day => $value) {
            $equalDays[$day] = ['days' => [$day], 'opening_hours' => $value];
            unset($nonUniqueOpeningHours[$day]);
        }

        foreach ($uniqueOpeningHours as $uniqueDay => $uniqueValue) {
            foreach ($nonUniqueOpeningHours as $nonUniqueDay => $nonUniqueValue) {
                // use the same loose comparison as array_unique() above (SORT_REGULAR), so a day
                // that was grouped as "identical" there can't fail to match here and silently
                // drop out of the combined result
                if ($uniqueValue == $nonUniqueValue) {
                    $equalDays[$uniqueDay]['days'][] = $nonUniqueDay;
                }
            }
        }
        return $equalDays;
    }

    /**
     * Return a string of combined days with same hours
     * @param array $arrays
     * @param array $options - array containing settings option
     * @return string
     */
    protected function timesPerDayString(array $arrays, array $options): string
    {
        $out = '';

        $dayNames = [];

        foreach ($arrays['days'] as $dayAbbr) {
            $add = true;
            if ($dayAbbr == 'ho') {
                if ($this->hideholiday) {
                    $add = false;
                }
            }
            if ($add) {
                $dayNames[] = $options['fulldayName'] ? self::getWeekdays()[$dayAbbr][1] : self::getWeekdays()[$dayAbbr][0];
            }
        }


        // add surrounding tag for day names if set
        $out .= $this->renderDayName('', implode(', ', $dayNames), $options, true);

        $dayTimes = [];
        foreach ($arrays['opening_hours'] as $times) {
            if (count(array_filter($times)) === 0) {
                //closed
                $dayTimes[] = $options['closedText'];
            } else {
                $dayTimes[] = implode(' - ',
                        ['start' => $times['start'], 'finish' => $times['finish']]) . $options['timesuffix'];
            }
        }

        // add opening surrounding tag if set
        if ($options['timetag']) {
            $out .= '<' . $options['timetag'];
            $timeClass[] = 'time';
            if ($options['timeclass']) {
                $timeClass[] = $options['timeclass'];
            }
            $out .= ' class="' . implode(' ', $timeClass) . '">';
        }
        $out .= '<span class="op-time">' . implode(', ', $dayTimes) . '</span>';

        // add closing surrounding tag if set
        if ($options['timetag']) {
            $out .= '</' . $options['timetag'] . '>';
        }

        return $out;
    }

    /**
     * Method to render combined opening times as an unordered list
     * You can use the samoe options as parameter as in the render method
     * @param array $options - various output formatting options
     * @return string
     */
    public function renderCombinedDays(array $options = []): string
    {
        // fallback for older versions -> convert key "ulclass" to "wrapperclass" because it was renamed in version 1.1
        $options = $this->convertUlClassFallback($options);

        $options = array_merge($this->defaultOptions(), $options);

        // start creating the markup
        $out = '';

        // add opening wrapper tag if set
        if ($options['wrappertag']) {
            $out .= '<' . $options['wrappertag'];
            $out .= $options['wrapperclass'] ? ' class="' . $options['wrapperclass'] . '"' : '';
            $out .= '>';
        }

        foreach ($this->combinedDays($options['showClosed']) as $arrays) {
            // set opening item tag if set
            $out .= $options['itemtag'] ? '<' . $options['itemtag'] . ' class="oh-item">' : '';
            $out .= $this->timesPerDayString($arrays, $options);

            // set closing item tag if set
            $out .= $options['itemtag'] ? '</' . $options['itemtag'] . '>' : '';
        }

        // add closing wrapper tag if set
        if ($options['wrappertag']) {
            $out .= '</' . $options['wrappertag'] . '>';
        }

        return $out;
    }

    /**
     * !! DEPRICATED !!
     * Method to render combined opening times inside a self chosen tag
     * This method is depricated!!!!
     *
     * @param array $options - various output formatting options
     * tagName: Set the preferred tag for each line (default is div)
     * fulldayName: true/false -> if set to true the full day name (fe Monday) will be displayed, otherwise only the abbreviation (fe Mo)
     * timeseparator : The sign between multiple opening times on the same day
     * closedText : What should be displayed if it is closed on that day
     * timesuffix: A text that should be displayed after the time
     * showClosed: true => closed days will be displayed; false => closed days will be removed
     * @param bool $combined
     * @return string
     */
    public function renderCombinedDaysTag(array $options = [], bool $combined = false): string
    {
        // fallback for key name "tagName" to "itemtag" which was renamed in version 1.1
        if (array_key_exists('tagName', $options)) {
            $options['itemtag'] = $options['tagName'];
            unset($options['tagName']);
        } else {
            // set div as default value
            $options['itemtag'] = 'div';
        }

        $options = array_merge($this->defaultOptions(), $options);

        if ($combined) {
            return $this->renderCombinedDays($options);
        } else {
            return $this->render($options);
        }
    }

    /**
     * Return the number of how many times were set
     * @return int
     */
    public function getNumberOfTimes()
    {
        $times = $this->times;
        if ($this->hideholiday) {
            unset($times['ho']);
        }
        $timesPerDay = [];
        foreach ($times as $key => $day) {
            foreach ($day as $dayTime) {
                if (array_filter($dayTime)) {
                    $timesPerDay[] = $dayTime;
                }
            }
        }
        return count($timesPerDay);
    }

    /**
     * Method to create an array of combined opening hours for usage in json LD markup of schema.org
     * Based on https://schema.org/openingHours
     * @return array fe Array ( [0] => Mo,Tu,We 08:00-12:00 [1] => Mo,Th 13:00-18:00 [2] => Th 08:00-11:00 )
     */
    public function getjsonLDTimes(): array
    {
        $times = array_filter($this->get('times'));

        //convert times always to H:i format (fe 08:00), because Schema.org only accepts this format
        array_walk_recursive($times, function (&$value, $key) {
            if (($key === 'start') || ($key === 'finish')) {
                if ($value) {
                    $value = OpeningHours::formatTimestring($value, 'H:i');
                }
            }
        });
        $temp_times = [];
        foreach ($times as $day => $daytimes) {
            foreach ($daytimes as $num => $time) {
                $timeStr = array_filter($time);
                $timeStr = implode('-', $timeStr);
                $temp_times[$day . '-' . $num] = $timeStr;
            }
        }
        $times = array_filter($temp_times);

        $val = array_unique(array_values($times));
        $dat = [];
        foreach ($val as $v) {
            $dat[$v] = array_keys($times, $v);
        }
        $combined = [];
        foreach ($dat as $time => $days) {
            $combined[$time] = implode(',', $days);
        }
        //manipulate values
        array_walk($combined, function (&$value) {
            $values = explode(',', $value);
            $newValues = [];
            foreach ($values as $val) {
                $newValues[] = ucfirst(substr($val, 0, 2));
            }
            $value = implode(',', $newValues);
        });
        $corr = [];
        foreach ($combined as $time => $days) {
            $corr[] = $days . ' ' . $time;
        }
        return $corr;
    }


    /**
     * Method to render a string of combined opening hours for usage in json LD markup of schema.org
     * Based on https://schema.org/openingHours
     * @return string -> fe "Mo-Fr 10:00-19:00", "Mo-Di 21:00-23:00", "Sa 10:00-22:00", "Su 10:00-21:00"
     */
    public function renderjsonLDTimes(): string
    {
        $times = $this->getjsonLDTimes();
        array_walk($times, function (&$value) {
            $value = '"' . $value . '"';
        });
        return implode(', ', $times);
    }

    /*
     * --- Exceptions in JSON-LD markup ---------------------------------------------------
     * Schema.org's plain "openingHours" string format (used by getjsonLDTimes()/
     * renderjsonLDTimes() above) only covers the regular, recurring weekly schedule - it has
     * no way to express a date-limited override. Exceptions (holidays, company vacation,
     * reduced/custom hours) are instead expressed with the structured
     * "specialOpeningHoursSpecification" property, a sibling of "openingHours" holding one or
     * more "OpeningHoursSpecification" objects, each scoped to a date range via "validFrom"/
     * "validThrough" (see https://schema.org/specialOpeningHoursSpecification and
     * https://schema.org/OpeningHoursSpecification). Per Google's own guidance for this
     * property, a closed exception is represented as "opens"/"closes" both set to "00:00"
     * rather than being omitted. getJsonLDSpecialOpeningHours()/
     * renderJsonLDSpecialOpeningHours() below produce this structure, mirroring
     * getjsonLDTimes()/renderjsonLDTimes()'s naming and usage pattern.
     */

    /**
     * Resolve the actual calendar date range (as DateTime objects) an exception applies to,
     * for use in structured (JSON-LD) markup. For a non-recurring exception this is simply
     * its stored startDate/endDate. For a recurring exception - which only stores a month/day
     * (the year is irrelevant, see exceptionMatchesDate()) - this projects that month/day onto
     * a concrete year: $referenceDate's year by default, advanced to the following year if
     * that occurrence has already fully passed relative to $referenceDate, so structured data
     * always exposes the current or next upcoming occurrence rather than a stale one from
     * earlier in the year. A range that wraps across New Year's Eve (fe recurring
     * 28.12. - 03.01.) has its end date pushed into the following year, the same way
     * exceptionMatchesDate() treats it.
     * @param array $exception
     * @param DateTime|null $referenceDate defaults to today
     * @return array|null ['start' => DateTime, 'end' => DateTime], or null if malformed
     */
    protected static function resolveExceptionValidityRange(array $exception, ?DateTime $referenceDate = null): ?array
    {
        $referenceDate = $referenceDate ?: new DateTime();
        $startDate = $exception['startDate'] ?? null;
        $endDate = $exception['endDate'] ?? $startDate;
        if (!$startDate || !$endDate) {
            return null;
        }
        $storedStart = DateTime::createFromFormat('Y-m-d', $startDate);
        $storedEnd = DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$storedStart || !$storedEnd) {
            return null;
        }

        if (empty($exception['recurring'])) {
            return ['start' => $storedStart, 'end' => $storedEnd];
        }

        $year = (int)$referenceDate->format('Y');
        $start = DateTime::createFromFormat('Y-m-d', $year . '-' . $storedStart->format('m-d'));
        // a range wrapping across New Year's Eve (fe 28.12. - 03.01.) ends in the following year
        $endYear = $storedEnd->format('m-d') < $storedStart->format('m-d') ? $year + 1 : $year;
        $end = DateTime::createFromFormat('Y-m-d', $endYear . '-' . $storedEnd->format('m-d'));

        // this year's (or wrapped) occurrence is already fully over -> use next year's instead
        if ($end->format('Y-m-d') < $referenceDate->format('Y-m-d')) {
            $start->modify('+1 year');
            $end->modify('+1 year');
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Method to create an array of structured "OpeningHoursSpecification" entries for the
     * exceptions (holidays, company vacation, reduced/custom hours), for use as the
     * "specialOpeningHoursSpecification" property in JSON-LD markup of schema.org. A closed
     * exception becomes one entry with "opens"/"closes" both "00:00" (Google's own
     * recommendation for a closure); an exception with its own hours becomes one entry per
     * time pair, all sharing the same "validFrom"/"validThrough" (so fe a lunch-break-style
     * second time pair becomes a second entry for the same dates). Past, non-recurring
     * exceptions are left out (see filterActiveExceptions()); a recurring exception's
     * month/day is projected onto a concrete year (see resolveExceptionValidityRange()).
     * Based on https://schema.org/specialOpeningHoursSpecification
     * @param DateTime|null $referenceDate defaults to today; also determines which year a
     *   recurring exception's date range is resolved into
     * @return array list of ['@type' => 'OpeningHoursSpecification', 'opens' => 'H:i',
     *   'closes' => 'H:i', 'validFrom' => 'Y-m-d', 'validThrough' => 'Y-m-d']
     */
    public function getJsonLDSpecialOpeningHours(?DateTime $referenceDate = null): array
    {
        $referenceDate = $referenceDate ?: new DateTime();
        $exceptions = self::filterActiveExceptions($this->exceptions ?: [], $referenceDate);

        $specs = [];
        foreach ($exceptions as $exception) {
            $range = self::resolveExceptionValidityRange($exception, $referenceDate);
            if (!$range) {
                continue;
            }
            $validFrom = $range['start']->format('Y-m-d');
            $validThrough = $range['end']->format('Y-m-d');

            if (!empty($exception['closed'])) {
                $specs[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'opens' => '00:00',
                    'closes' => '00:00',
                    'validFrom' => $validFrom,
                    'validThrough' => $validThrough,
                ];
                continue;
            }

            foreach (self::normalizeExceptionTimes($exception) as $pair) {
                $start = $pair['start'] ?? '';
                $finish = $pair['finish'] ?? '';
                if (!$start || !$finish) {
                    continue;
                }
                $specs[] = [
                    '@type' => 'OpeningHoursSpecification',
                    'opens' => self::formatTimestring($start, 'H:i'),
                    'closes' => self::formatTimestring($finish, 'H:i'),
                    'validFrom' => $validFrom,
                    'validThrough' => $validThrough,
                ];
            }
        }

        return $specs;
    }

    /**
     * Method to render the exceptions as a string of JSON-encoded "OpeningHoursSpecification"
     * objects, ready to embed as the "specialOpeningHoursSpecification" property in JSON-LD
     * markup of schema.org (see getJsonLDSpecialOpeningHours()) - same usage pattern as
     * renderjsonLDTimes() for the regular weekly hours, fe in a template:
     *   "openingHours": [<?= $page->opening_hours->renderjsonLDTimes() ?>],
     *   "specialOpeningHoursSpecification": [<?= $page->opening_hours->renderJsonLDSpecialOpeningHours() ?>]
     * Based on https://schema.org/specialOpeningHoursSpecification
     * @param DateTime|null $referenceDate defaults to today
     * @return string fe '{"@type":"OpeningHoursSpecification","opens":"00:00","closes":"00:00","validFrom":"2026-12-24","validThrough":"2026-12-26"}'
     */
    public function renderJsonLDSpecialOpeningHours(?DateTime $referenceDate = null): string
    {
        $specs = $this->getJsonLDSpecialOpeningHours($referenceDate);
        return implode(', ', array_map(function ($spec) {
            return json_encode($spec, JSON_UNESCAPED_SLASHES);
        }, $specs));
    }

    /**
     * Render method to render all opening times as a definition list
     * @param array $options - add some styling parameters
     * @param bool $combined - render combined times (true) or not (false)
     * @return string
     */
    public function renderDefinitionList(array $options = [], bool $combined = false): string
    {
        // set fixed values for the definition list
        $options['wrappertag'] = 'dl';
        $options['itemtag'] = false;
        $options['daytag'] = 'dt';
        $options['timetag'] = 'dd';

        if ($combined) {
            return $this->renderCombinedDays($options);
        } else {
            return $this->render($options);
        }
    }

    /**
     * Render method to render all opening times as a table
     * @param array $options - add some styling parameters
     * @param bool $combined - render combined times (true) or not (false)
     * @return string
     */
    public function renderTable(array $options = [], bool $combined = false): string
    {
        // set fixed values for the definition list
        $options['wrappertag'] = 'table';
        $options['itemtag'] = 'tr';
        $options['daytag'] = 'td';
        $options['timetag'] = 'td';

        if ($combined) {
            return $this->renderCombinedDays($options);
        } else {
            return $this->render($options);
        }
    }

    /**
     * Render method to render all opening times using div and span container
     * @param array $options - add some styling parameters
     * @param bool $combined - render combined times (true) or not (false)
     * @return string
     */
    public function renderDiv(array $options = [], bool $combined = false): string
    {
        // set fixed values for the definition list
        $options['wrappertag'] = 'div';
        $options['itemtag'] = 'div';
        $options['daytag'] = 'span';
        $options['timetag'] = 'span';

        if ($combined) {
            return $this->renderCombinedDays($options);
        } else {
            return $this->render($options);
        }
    }

    /**
     * Magic method to allow the object to be used as a string, rendering all opening
     * times with the default render() options
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /* Exceptions (holidays, company vacation, ...) */

    /**
     * Structure of one exception entry:
     * [
     *   'label'     => string  (fe "Weihnachten"),
     *   'startDate' => string  Y-m-d,
     *   'endDate'   => string  Y-m-d (same as startDate for a single day),
     *   'recurring' => bool    (true: repeats every year, only month/day are compared),
     *   'closed'    => bool    (true: closed all day; false: hours via 'times'),
     *   'times'     => array   list of ['start' => 'H:i', 'finish' => 'H:i'] pairs
     *                          (only relevant if closed === false; up to numberOftimes entries)
     * ]
     * Note: entries saved before multiple times were supported may still carry a single
     * 'start'/'finish' pair directly on the exception instead of a 'times' list -
     * normalizeExceptionTimes() below reads both formats.
     */

    /**
     * Return the list of start/finish time-pairs for an exception, regardless of whether
     * it was saved in the current ('times' => [...]) or legacy (top-level 'start'/'finish')
     * format.
     * @param array $exception
     * @return array list of ['start' => string, 'finish' => string] pairs
     */
    protected static function normalizeExceptionTimes(array $exception): array
    {
        if (!empty($exception['times']) && is_array($exception['times'])) {
            return array_values($exception['times']);
        }
        if (isset($exception['start']) || isset($exception['finish'])) {
            return [['start' => $exception['start'] ?? '', 'finish' => $exception['finish'] ?? '']];
        }
        return [];
    }

    /**
     * Remove expired, non-recurring exceptions from a list of exceptions
     * Recurring exceptions are never removed, since they apply again every year.
     * @param array $exceptions
     * @param DateTime|null $referenceDate defaults to today
     * @return array
     */
    public static function filterActiveExceptions(array $exceptions, ?DateTime $referenceDate = null): array
    {
        $referenceDate = $referenceDate ?: new DateTime();
        $today = $referenceDate->format('Y-m-d');
        return array_values(array_filter($exceptions, function ($exception) use ($today) {
            if (!empty($exception['recurring'])) {
                return true; // recurring exceptions never expire
            }
            $end = $exception['endDate'] ?? $exception['startDate'] ?? null;
            if (!$end) {
                return false; // malformed entry, drop it
            }
            return $end >= $today;
        }));
    }

    /**
     * Check whether a single exception entry applies to the given date
     * @param array $exception
     * @param DateTime $date
     * @return bool
     */
    protected static function exceptionMatchesDate(array $exception, DateTime $date): bool
    {
        $startDate = $exception['startDate'] ?? null;
        $endDate = $exception['endDate'] ?? $startDate;
        if (!$startDate || !$endDate) {
            return false;
        }
        $start = DateTime::createFromFormat('Y-m-d', $startDate);
        $end = DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$start || !$end) {
            return false;
        }

        if (!empty($exception['recurring'])) {
            // compare month/day only, ignoring the year (also handles ranges that wrap
            // across new year's eve, fe 28.12. - 03.01.)
            $day = $date->format('m-d');
            $startMd = $start->format('m-d');
            $endMd = $end->format('m-d');
            if ($startMd <= $endMd) {
                return $day >= $startMd && $day <= $endMd;
            }
            return $day >= $startMd || $day <= $endMd;
        }

        $d = $date->format('Y-m-d');
        return $d >= $start->format('Y-m-d') && $d <= $end->format('Y-m-d');
    }

    /**
     * Resolve a (possibly multi-language) exception label to a single string for the
     * current user's language. Multi-language labels are stored as a map
     * ['default' => '...', '<languageId>' => '...']; a plain string is returned as-is
     * (fe a label saved before multi-language support was added).
     * @param mixed $label
     * @return string
     */
    protected static function resolveLocalizedLabel($label): string
    {
        if (!is_array($label)) {
            return (string)$label;
        }
        if (isset(wire('user')->language)) {
            $userLang = (string)wire('user')->language->id;
            if (!empty($label[$userLang])) {
                return $label[$userLang];
            }
        }
        if (!empty($label['default'])) {
            return $label['default'];
        }
        $first = reset($label);
        return $first ?: '';
    }

    /**
     * Return the exception entry that applies to the given date, if any
     * @param DateTime|null $date defaults to today
     * @return array|null
     */
    public function getExceptionForDate(?DateTime $date = null): ?array
    {
        $date = $date ?: new DateTime();
        $exceptions = $this->exceptions ?: [];
        foreach ($exceptions as $exception) {
            if (self::exceptionMatchesDate($exception, $date)) {
                return $exception;
            }
        }
        return null;
    }

    /**
     * Check whether a single exception entry occurs at all within the given year.
     * Recurring exceptions occur every year (only month/day are stored for those), so they
     * always match; non-recurring exceptions match when their date range overlaps the year.
     * @param array $exception
     * @param int $year
     * @return bool
     */
    protected static function exceptionAppliesInYear(array $exception, int $year): bool
    {
        if (!empty($exception['recurring'])) {
            return true; // recurring exceptions apply again every year, including this one
        }
        $startDate = $exception['startDate'] ?? null;
        $endDate = $exception['endDate'] ?? $startDate;
        if (!$startDate || !$endDate) {
            return false;
        }
        $start = DateTime::createFromFormat('Y-m-d', $startDate);
        $end = DateTime::createFromFormat('Y-m-d', $endDate);
        if (!$start || !$end) {
            return false;
        }

        $yearStart = $year . '-01-01';
        $yearEnd = $year . '-12-31';
        // the exception's range overlaps the given year if it starts on/before the year
        // ends, and ends on/after the year starts (fe a company vacation spanning
        // 28.12.2025 - 03.01.2026 overlaps both 2025 and 2026)
        return $start->format('Y-m-d') <= $yearEnd && $end->format('Y-m-d') >= $yearStart;
    }

    /**
     * Return all exceptions (holidays, company vacation, reduced/custom hours) that occur
     * within the given year. Recurring exceptions (fe a public holiday repeated every
     * Christmas) are always included, since they apply every year regardless of the year
     * originally stored on their startDate/endDate.
     * @param int|null $year defaults to the current year
     * @return array
     */
    public function getExceptionsForYear(?int $year = null): array
    {
        $year = $year ?: (int)(new DateTime())->format('Y');
        $exceptions = $this->exceptions ?: [];
        return array_values(array_filter($exceptions, function ($exception) use ($year) {
            return self::exceptionAppliesInYear($exception, $year);
        }));
    }

    /**
     * Return all exceptions (holidays, company vacation, reduced/custom hours) that occur
     * on at least one of the given days. Checked day by day with exceptionMatchesDate()
     * (the same check getExceptionForDate() uses), so this also correctly picks up
     * recurring exceptions and ranges that only partially overlap the given days. Shared by
     * getExceptionsForWeek() and getExceptionsForMonth().
     * @param DateTime[] $days
     * @return array
     */
    protected function getExceptionsForDays(array $days): array
    {
        $exceptions = $this->exceptions ?: [];
        $matched = [];
        foreach ($days as $day) {
            foreach ($exceptions as $exception) {
                if (self::exceptionMatchesDate($exception, $day)) {
                    $matched[] = $exception;
                }
            }
        }
        // de-duplicate: an exception spanning several of the given days would otherwise be
        // added once per matching day (same de-dup approach as combinedDays() above)
        return array_values(array_unique($matched, SORT_REGULAR));
    }

    /**
     * Render the exceptions (holidays, company vacation, reduced/custom hours) that apply
     * on at least one of the given days, as a list - one entry per contiguous run of matching
     * dates, showing the date (or, for a multi-day run, "from - to"), the changed opening
     * hours (or the "closed" text) and the exception's label in parentheses. Builds on top
     * of getExceptionsForDays() for the list of exceptions, then - since one exception can
     * apply on more than one of the given days (fe a multi-day company vacation) - checks
     * each day against it with exceptionMatchesDate() again to find every date it actually
     * falls on, and groups consecutive matching dates (fe every day of a company vacation)
     * into a single "from - to" item instead of one item per day. Shared by
     * renderExceptionsForWeek(), renderExceptionsForMonth() and renderExceptionsForYear().
     * @param DateTime[] $days
     * @param array $options same keys as render()/defaultOptions() (wrappertag, itemtag,
     *   wrapperclass, timeseparator, closedText, fulldayName, ...), plus:
     *   dateformat: PHP date() format for the date (default: 'd.m.Y')
     * @param string $itemClass CSS class for each <itemtag> (fe "oh-exception-week-item")
     * @return string fe "<ul><li>Th 24.12.2026: closed (Christmas Eve)</li></ul>", or with
     *   'fulldayName' => true: "<ul><li>Thursday 24.12.2026: closed (Christmas Eve)</li></ul>",
     *   or for a multi-day run: "<ul><li>12.12.2026 - 31.12.2026: closed (Company vacation)</li></ul>"
     */
    protected function renderExceptionsForDays(array $days, array $options, string $itemClass): string
    {
        $out = '';
        if ($options['wrappertag']) {
            $out .= '<' . $options['wrappertag'];
            $out .= $options['wrapperclass'] ? ' class="' . $options['wrapperclass'] . '"' : '';
            $out .= '>';
        }

        // ISO weekday number (1=Monday..7=Sunday, PHP's "N" format) to abbreviation, used
        // below to look up each day's name for the 'fulldayName' option via getWeekdays() -
        // the same lookup table renderDay()/combinedDays() use for it. $day is a plain
        // DateTime here (not tied to a specific weekday key like 'times' is), so it has to
        // be mapped this way rather than looked up directly.
        $isoWeekdayAbbrs = ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su'];

        foreach ($this->getExceptionsForDays($days) as $exception) {
            $label = isset($exception['label']) ? self::resolveLocalizedLabel($exception['label']) : '';
            $closed = !empty($exception['closed']);

            if ($closed) {
                $timesText = $options['closedText'];
            } else {
                // only non-empty time-pairs, formatted according to the field's timeformat
                // setting (fe 16:00 -> 04:00 PM), same as the regular weekly schedule
                $times = array_values(array_filter(self::normalizeExceptionTimes($exception), function ($pair) {
                    return !empty($pair['start']) || !empty($pair['finish']);
                }));
                $timeStrings = [];
                foreach ($times as $pair) {
                    $timeStrings[] = self::formatTimestring($pair['start'] ?? '', $this->timeformat) . ' - ' . self::formatTimestring($pair['finish'] ?? '', $this->timeformat);
                }
                $timesText = $timeStrings ? implode($options['timeseparator'], $timeStrings) : $options['closedText'];
            }

            // collect every day (in $days, so already chronological) this exception matches,
            // then group them into contiguous runs (each day exactly one calendar day after
            // the previous one in the run), so a multi-day exception (fe a company vacation)
            // becomes a single "from - to" item instead of one item per day
            $matchingDays = [];
            foreach ($days as $day) {
                if (self::exceptionMatchesDate($exception, $day)) {
                    $matchingDays[] = $day;
                }
            }

            $runs = [];
            $currentRun = [];
            foreach ($matchingDays as $day) {
                if ($currentRun) {
                    $previousDay = end($currentRun);
                    $expectedNext = (clone $previousDay)->modify('+1 day');
                    if ($expectedNext->format('Y-m-d') !== $day->format('Y-m-d')) {
                        $runs[] = $currentRun;
                        $currentRun = [];
                    }
                }
                $currentRun[] = $day;
            }
            if ($currentRun) {
                $runs[] = $currentRun;
            }

            // resolves a day's weekday name according to the 'fulldayName' option (full name
            // if true, abbreviation if false), used for both single dates and each end of a
            // multi-day range below, so the option is honored consistently either way
            $dayNameFor = function (DateTime $day) use ($isoWeekdayAbbrs, $options): string {
                $dayAbbr = $isoWeekdayAbbrs[(int)$day->format('N') - 1];
                $dayNames = self::getWeekdays()[$dayAbbr];
                return $options['fulldayName'] ? $dayNames[1] : $dayNames[0];
            };

            foreach ($runs as $run) {
                $firstDay = $run[0];
                $lastDay = end($run);

                if (count($run) === 1) {
                    // single matching date: "dayName date"
                    $dateText = $dayNameFor($firstDay) . ' ' . $firstDay->format($options['dateformat']);
                } else {
                    // multi-day run: "fromDayName fromDate - toDayName toDate", the day name
                    // for each end resolved the same way (and honoring 'fulldayName') as for
                    // a single date - fe "Sa 12.12.2026 - Th 31.12.2026"
                    $dateText = $dayNameFor($firstDay) . ' ' . $firstDay->format($options['dateformat']) . ' - ' . $dayNameFor($lastDay) . ' ' . $lastDay->format($options['dateformat']);
                }

                $out .= $options['itemtag'] ? '<' . $options['itemtag'] . ' class="' . $itemClass . '">' : '';
                $out .= $dateText . $options['daytimeseparator'] . ' ' . $timesText;
                if ($label !== '') {
                    $out .= ' (' . $label . ')';
                }
                $out .= $options['itemtag'] ? '</' . $options['itemtag'] . '>' : '';
            }
        }

        if ($options['wrappertag']) {
            $out .= '</' . $options['wrappertag'] . '>';
        }

        return $out;
    }

    /**
     * Return all exceptions (holidays, company vacation, reduced/custom hours) that occur
     * within the week (Monday - Sunday) containing the given date.
     * @param DateTime|null $referenceDate any day within the desired week, defaults to today
     * @return array
     */
    public function getExceptionsForWeek(?DateTime $referenceDate = null): array
    {
        return $this->getExceptionsForDays($this->getDaysInWeek($referenceDate));
    }

    /**
     * Render the exceptions (holidays, company vacation, reduced/custom hours) that apply
     * within the week (Monday - Sunday) containing the given date - see
     * renderExceptionsForDays() for the format and $options.
     * @param DateTime|null $referenceDate any day within the desired week, defaults to today
     * @param array $options see renderExceptionsForDays()
     * @return string
     */
    public function renderExceptionsForWeek(?DateTime $referenceDate = null, array $options = []): string
    {
        $referenceDate = $referenceDate ?: new DateTime();
        $options = array_merge($this->defaultOptions(), ['dateformat' => 'd.m.Y'], $options);
        return $this->renderExceptionsForDays($this->getDaysInWeek($referenceDate), $options, 'oh-exception-week-item');
    }

    /**
     * Return all exceptions (holidays, company vacation, reduced/custom hours) that occur
     * within the given calendar month of the current year.
     * @param int|null $month 1 (January) through 12 (December); defaults to (and falls
     *   back to, if out of range) the current month. The year is never selectable - it's
     *   always the current year.
     * @return array
     */
    public function getExceptionsForMonth(?int $month = null): array
    {
        return $this->getExceptionsForDays($this->getDaysInMonth($month));
    }

    /**
     * Render the exceptions (holidays, company vacation, reduced/custom hours) that apply
     * within the given calendar month of the current year - see renderExceptionsForDays()
     * for the format and $options.
     * @param int|null $month 1 (January) through 12 (December); defaults to (and falls
     *   back to, if out of range) the current month (fe called with no argument, this shows
     *   all exceptions in the current month). The year is never selectable - it's always
     *   the current year.
     * @param array $options see renderExceptionsForDays()
     * @return string
     */
    public function renderExceptionsForMonth(?int $month = null, array $options = []): string
    {
        $options = array_merge($this->defaultOptions(), ['dateformat' => 'd.m.Y'], $options);
        return $this->renderExceptionsForDays($this->getDaysInMonth($month), $options, 'oh-exception-month-item');
    }

    /**
     * Render the exceptions (holidays, company vacation, reduced/custom hours) that apply
     * within the given year - see renderExceptionsForDays() for the format and $options.
     * Note: this scans the year day by day (via getDaysInYear()) rather than using the
     * existing getExceptionsForYear() above, since it needs each actual matching date (fe
     * for a multi-day company vacation) to render, not just whether an exception occurs
     * somewhere within the year.
     * @param int|null $year defaults to the current year (fe called with no argument, this
     *   shows all exceptions in the current year)
     * @param array $options see renderExceptionsForDays()
     * @return string
     */
    public function renderExceptionsForYear(?int $year = null, array $options = []): string
    {
        $options = array_merge($this->defaultOptions(), ['dateformat' => 'd.m.Y'], $options);
        return $this->renderExceptionsForDays($this->getDaysInYear($year), $options, 'oh-exception-year-item');
    }

    /**
     * Every day (as DateTime, time part at midnight) of the week (Monday - Sunday)
     * containing the given date.
     * @param DateTime|null $referenceDate any day within the desired week, defaults to today
     * @return DateTime[]
     */
    protected function getDaysInWeek(?DateTime $referenceDate = null): array
    {
        $referenceDate = $referenceDate ?: new DateTime();
        // Monday of that week ("N" = ISO-8601 day of week, 1 (Monday) through 7 (Sunday))
        $weekStart = clone $referenceDate;
        $weekStart->modify('-' . ($weekStart->format('N') - 1) . ' days');

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = (clone $weekStart)->modify('+' . $i . ' days');
        }
        return $days;
    }

    /**
     * Every day (as DateTime, time part at midnight) of the given calendar month of the
     * current year.
     * @param int|null $month 1 (January) through 12 (December); defaults to (and falls
     *   back to, if out of range) the current month. The year is never selectable - it's
     *   always the current year, so this can't be used to look at a different year's month.
     * @return DateTime[]
     */
    protected function getDaysInMonth(?int $month = null): array
    {
        $now = new DateTime();
        if ($month === null || $month < 1 || $month > 12) {
            $month = (int)$now->format('n');
        }
        $monthStart = new DateTime($now->format('Y') . '-' . str_pad((string)$month, 2, '0', STR_PAD_LEFT) . '-01');
        $daysInMonth = (int)$monthStart->format('t');

        $days = [];
        for ($i = 0; $i < $daysInMonth; $i++) {
            $days[] = (clone $monthStart)->modify('+' . $i . ' days');
        }
        return $days;
    }

    /**
     * Every day (as DateTime, time part at midnight) of the given year.
     * @param int|null $year defaults to the current year
     * @return DateTime[]
     */
    protected function getDaysInYear(?int $year = null): array
    {
        if ($year === null) {
            $year = (int)(new DateTime())->format('Y');
        }
        $yearStart = new DateTime($year . '-01-01');
        // "L" = leap year flag (1/0) for the year of this date, so this correctly returns
        // 366 for a leap year and 365 otherwise
        $daysInYear = $yearStart->format('L') ? 366 : 365;

        $days = [];
        for ($i = 0; $i < $daysInYear; $i++) {
            $days[] = (clone $yearStart)->modify('+' . $i . ' days');
        }
        return $days;
    }

    /**
     * Return the effective opening status for the given date, taking exceptions
     * (holidays, company vacation, reduced/custom hours) into account.
     * @param DateTime|null $date defaults to today
     * @return array ['closed' => bool, 'label' => string|null, 'times' => array (list of
     *   ['start' => string, 'finish' => string] pairs), 'start' => string, 'finish' =>
     *   string (the first pair, kept for backwards compatibility), 'exception' => bool]
     */
    public function getStatusForDate(?DateTime $date = null): array
    {
        $date = $date ?: new DateTime();
        $exception = $this->getExceptionForDate($date);

        if ($exception) {
            $closed = !empty($exception['closed']);
            $times = $closed ? [] : array_filter(self::normalizeExceptionTimes($exception), function ($pair) {
                return !empty($pair['start']) || !empty($pair['finish']);
            });
            $times = array_values($times);
            $first = $times[0] ?? ['start' => '', 'finish' => ''];
            return [
                'closed' => $closed,
                'label' => isset($exception['label']) ? self::resolveLocalizedLabel($exception['label']): null,
                'times' => $times,
                'start' => $first['start'] ?? '',
                'finish' => $first['finish'] ?? '',
                'exception' => true,
            ];
        }

        // fall back to the regular weekly schedule for that weekday
        // PHP's "D" format gives English abbreviations (Mon, Tue, Wed, ...) which, lowercased
        // and truncated to 2 chars, already match our day keys ('mo', 'tu', 'we', ...)
        $dayAbbr = strtolower(substr($date->format('D'), 0, 2));
        $dayTimes = $this->times[$dayAbbr] ?? [];
        $first = $dayTimes[0] ?? ['start' => '', 'finish' => ''];

        return [
            'closed' => empty($first['start']),
            'label' => null,
            'times' => $dayTimes,
            'start' => $first['start'] ?? '',
            'finish' => $first['finish'] ?? '',
            'exception' => false,
        ];
    }
}
