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

    public function __construct()
    {
        //set default values
        parent::__construct();

        $this->set('times', json_decode(file_get_contents(__DIR__ . '/defaultData.json'), true));
        $this->set('timeformat', self::DEFAULTTIMEFORMAT);
        $this->set('numberOftimes', '2');

    }

    /* Setter and getter  */
    public function set($key, $value)
    {
        return parent::set($key, $value);
    }


    public function get($key)
    {
        return parent::get($key);
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
     * This step is requiered for various sanitizations and validations of the input values which cannot be processed from an one-dimensional array
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
        return $time;
    }


    /**
     * Renders a string of opening times on a specific day
     * @param string $day
     * @param array $options
     * timeseparator: separator between multiple times (default: ,)
     * timesuffix: add text after timestring (default: '')
     * showClosed: true/false show closed days or not (default: true)
     * @return string
     */
    public function renderDay(string $day, array $options = []): string
    {
        $defaultOptions = ['timeseparator' => ', ', 'timesuffix' => '', 'showClosed' => true];
        $options = array_merge($defaultOptions, $options);
        $getTimes = $this->times[$day];
        $times = [];
        $numberOfTimes = count($getTimes);
        if ($numberOfTimes > 1) { // multiple opening times per day
            foreach ($getTimes as $value) {
                $times[] = $value['start'] . ' - ' . $value['finish'] . $options['timesuffix'];
            }
            $out = implode($options['timeseparator'], $times);
        } else { // single opening time or closed
            if (array_filter($getTimes[0])) {
                $times[] = $getTimes[0]['start'] . ' - ' . $getTimes[0]['finish'] . $options['timesuffix'];
                $out = implode('-', $times);
            } else {
                $closed = ($options['showClosed']) ? $this->_('closed') : '';
                $out = $closed;
            }
        }
        return $out;
    }


    /**
     * Method to render all times per week in an unordered list
     * @param array $options
     * ulclass: add a CSS class to the ul tag (default: '')
     * fulldayName: show fullname (true) or dayname abbreviation (false) -> (default: false)
     * timeseparator: separator between multiple times (default: ,)
     * timesuffix: add text after timestring (default: '')
     * showClosed: true/false show closed days or not (default: true)
     * @return string
     */
    public function render(array $options = []): string
    {
        $defaultOptions = [
            'ulclass' => '',
            'fulldayName' => false,
            'timeseparator' => ', ',
            'timesuffix' => '',
            'showClosed' => true
        ];
        $options = array_merge($defaultOptions, $options);
        $out = '<ul';
        $out .= $options['ulclass'] ? ' class="' . $options['ulclass'] . '"' : '';
        $out .= '>';
        foreach (self::getWeekdays() as $day => $name) {
            if ($this->renderDay($day, [
                'timeseparator' => $options['timeseparator'],
                'timesuffix' => $options['timesuffix'],
                'showClosed' => $options['showClosed']
            ])) {
                $out .= '<li class="time day-' . $day . '">';
                $out .= $options['fulldayName'] ? $name[1] : $name[0];
                $out .= ': ' . $this->renderDay($day, [
                        'timeseparator' => $options['timeseparator'],
                        'timesuffix' => $options['timesuffix'],
                        'showClosed' => $options['showClosed']
                    ]);
                $out .= '</li>';
            }
        }
        $out .= '</ul>';
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
            //$allOpeningHours = self::arrayFilterRecursive($this->times);
        }
        $uniqueOpeningHours = array_unique($allOpeningHours, SORT_REGULAR);
        $nonUniqueOpeningHours = $allOpeningHours;

        foreach ($uniqueOpeningHours as $day => $value) {
            $equalDays[$day] = ['days' => [$day], 'opening_hours' => $value];
            unset($nonUniqueOpeningHours[$day]);
        }

        foreach ($uniqueOpeningHours as $uniqueDay => $uniqueValue) {
            foreach ($nonUniqueOpeningHours as $nonUniqueDay => $nonUniqueValue) {
                if ($uniqueValue === $nonUniqueValue) {
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
        $dayNames = [];
        foreach ($arrays['days'] as $dayAbbr) {
            $dayNames[] = $options['fulldayName'] ? self::getWeekdays()[$dayAbbr][1] : self::getWeekdays()[$dayAbbr][0];
        }

        $out = implode(', ', $dayNames) . ': ';
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
        $out .= implode(', ', $dayTimes);
        return $out;
    }

    /**
     * Method to render combined opening times as an unordered list
     * @param array $options - various output formatting options
     * ulclass: Set a CSS class to the ul tag
     * fulldayName: true/false -> if set to true the full day name (fe Monday) will be displayed, otherwise only the abbreviation (fe Mo)
     * timeseparator : The sign between multiple opening times on the same day
     * closedText : What should be displayed if it is closed on that day
     * timesuffix: A text that should be displayed after the time
     * showClosed: true => closed days will be displayed; false => closed days will be removed
     * @return string
     */
    public
    function renderCombinedDays(
        array $options = []
    ): string {
        $defaultOptions = [
            'ulclass' => '',
            'fulldayName' => false,
            'timeseparator' => ', ',
            'closedText' => $this->_('closed'),
            'timesuffix' => '',
            'showClosed' => true
        ];
        $options = array_merge($defaultOptions, $options);
        $out = '<ul';
        $out .= $options['ulclass'] ? ' class="' . $options['ulclass'] . '"' : '';
        $out .= '>';
        foreach ($this->combinedDays($options['showClosed']) as $arrays) {
            $out .= '<li class="times">';
            $out .= $this->timesPerDayString($arrays, $options);
            /*
            $dayNames = [];
            foreach ($arrays['days'] as $dayAbbr) {
                $dayNames[] = $options['fulldayName'] ? self::getWeekdays()[$dayAbbr][1] : self::getWeekdays()[$dayAbbr][0];
            }
            $out .= implode(', ', $dayNames) . ': ';
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
            $out .= implode(', ', $dayTimes);
            */
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    }


    /**
     * Method to render combined opening times inside a self chosen tag
     * @param array $options - various output formatting options
     * tagName: Set the preferred tag for each line (default is div)
     * fulldayName: true/false -> if set to true the full day name (fe Monday) will be displayed, otherwise only the abbreviation (fe Mo)
     * timeseparator : The sign between multiple opening times on the same day
     * closedText : What should be displayed if it is closed on that day
     * timesuffix: A text that should be displayed after the time
     * showClosed: true => closed days will be displayed; false => closed days will be removed
     * @return string
     */
    public
    function renderCombinedDaysTag(
        array $options = []
    ): string {
        $defaultOptions = [
            'tagName' => 'div',
            'fulldayName' => false,
            'timeseparator' => ', ',
            'closedText' => $this->_('closed'),
            'timesuffix' => '',
            'showClosed' => true
        ];
        $options = array_merge($defaultOptions, $options);
        $out = '';
        foreach ($this->combinedDays($options['showClosed']) as $arrays) {
            $out .= '<' . $options['tagName'] . ' class="times">';
            $out .= $this->timesPerDayString($arrays, $options);
            /*
            $dayNames = [];
            foreach ($arrays['days'] as $dayAbbr) {
                $dayNames[] = $options['fulldayName'] ? self::getWeekdays()[$dayAbbr][1] : self::getWeekdays()[$dayAbbr][0];
            }
            $out .= implode(', ', $dayNames) . ': ';
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
            $out .= implode(', ', $dayTimes);
            */
            $out .= '</' . $options['tagName'] . '>';
        }
        return $out;
    }


    /**
     * Method to create an array of combined opening hours for usage in json LD markup of schema.org
     * Based on https://schema.org/openingHours
     * @return array fe Array ( [0] => Mo,Tu,We 08:00-12:00 [1] => Mo,Th 13:00-18:00 [2] => Th 08:00-11:00 )
     */
    public
    function getjsonLDTimes(): array
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
    public
    function renderjsonLDTimes(): string
    {
        $times = $this->getjsonLDTimes();
        array_walk($times, function (&$value) {
            $value = '"' . $value . '"';
        });
        return implode(', ', $times);
    }

    public
    function __toString()
    {
        return $this->render();
    }
}
