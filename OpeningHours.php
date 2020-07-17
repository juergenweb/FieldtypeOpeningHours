<?php
namespace ProcessWire;

/**
 * Helper WireData Class
 */

class OpeningHours extends WireData
{
    const DAYS = ['mo','tu','we','th','fr','sa','su','ho'];
    const DEFAULTTIMEFORMAT = '%R';

    public function __construct()
    {
        //set default values
        parent::__construct();
        try {
            $this->set('times', json_decode(file_get_contents(__DIR__ . '/defaultData.json'), true));
            $this->set('timeformat', self::DEFAULTTIMEFORMAT);
            $this->set('numberOftimes', '2');
        } catch (WireException $e) {
        }
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
    * Method to return an multidimensional array with day abbreviations as key and daynames as value
    * @return array
    */
    public static function getDaysOfTheWeek(): array
    {
        return [
          ['mo', _('Monday')],
          ['tu', _('Tuesday')],
          ['we', _('Wednesday')],
          ['th', _('Thursday')],
          ['fr', _('Friday')],
          ['sa', _('Saturday')],
          ['su', _('Sunday')],
          ['ho', _('Holiday')]
        ];
    }

    /**
    * Method to return the daynames as an array with abbreviation as key and fullname as value (fe 'mo' => 'Monday')
    * @return array
    */
    public static function getWeekdayNames(): array
    {
        $weekdayNames = [];
        foreach (self::getDaysOfTheWeek() as $key=>$abbrName) { // key = 0,1,2,3... $abbrName = ['mo' => 'Monday'],[],....
            $weekdayNames[$abbrName[0]] = $abbrName[1];
        }
        return $weekdayNames;
    }


    /**
    * Create a multidimensional array of all times from the onedimensional POST array after form submission
    * This step is requiered for various sanitizations and validations of the input values which cannot be processed from an onedimensional array
    * @param array $values
    * @return array
    */
    public static function createMultidimArray(array $values): array
    {
        $temp_array = [];
        foreach (self::getDaysOfTheWeek() as $dayAbbr=>$dayname) {
            foreach ($values as $k=>$v) {
                if ($dayname[0] === (explode('-', $k)[1])) {
                    $temp_array[$dayname[0]][] = $v;
                }
            }
        }
        $newArray = [];
        foreach ($temp_array as $day=>$values) {
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
    * Method to flatten the multidimensional array back to a one-dimensional array like we have got from POST
    * @param array $array - multidimensional array
    * @param string $fieldname - the name of the field
    * @return array
    */
    public static function flattenArray(array $array, string $fieldname): array
    {
        $flattenArray = [];
        foreach (self::getDaysOfTheWeek() as $key => $day) { //key = 0,1,2, day = [mo => Monday]

            foreach ($array as $dayAbbr=>$timesarray) { // key = mo,tu,we,...value = times array

                if ($day[0] === $dayAbbr) {
                    foreach ($timesarray as $keyNum=>$dayArray) { // key = 0,1,2, value = [start] => 08:00, [finish] => 16:00

                        foreach ($dayArray as $key=>$value) {
                            $key = $fieldname.'-'.$day[0].'-'.$keyNum.'-'.$key;
                            $flattenArray[$key] = $value;
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
    public static function isTimeValid(string $time_str, $format = 'H:i')
    {
        $DateTime = \DateTime::createFromFormat("d/m/Y {$format}", "10/10/2010 {$time_str}");
        return $DateTime && $DateTime->format("d/m/Y {$format}") == "10/10/2010 {$time_str}";
    }


    /**
    * Format a time value according to the format settings in the field configuration
    * fe 16:00 will be formatted to 04:00 AM if strftime setting is %r
    * @param string $time
    * @return string
    */
    private function formatTimestring(string $time): string
    {
        if ($time) {
            $timeStamp = strtotime('10.10.2010 '.$time); // virtual date/time string needed for manipulation
            $timeformat = $this->timeformat ? $this->timeformat : self::DEFAULTTIMEFORMAT;
            return strftime($timeformat, $timeStamp);
        }
        return $time;
    }

    /**
    * Method to return all opening times as an array considering the timeformat set in the backend
    * @return array
    */
    public function getTimes(): array
    {
      $times = $this->times;
      array_walk_recursive($times, function(&$value, &$key) {
        if(($key === 'start') || ($key === 'finish')){
          $value = $this->formatTimestring($value);
        }
      });
      return $times;
    }

    /**
    * Method to return all opening times as an array on a specific day considering the timeformat set in the backend
    * @return array
    */
    public function getDay(string $day): array
    {
      $day = trim($day);
      if(in_array($day , self::DAYS)){
        return $this->getTimes()[$day];
      }
      return [];
    }

    /**
    * Renders a string of opening times on a specific day
    * @param string $day
    * @param string $separator
    * @param string $timesuffix
    * @return string
    */
    public function renderDay(string $day, string $separator = ', ', $timesuffix = ''): string
    {
        $getTimes = $this->times[$day];
        $times = [];
        $numberOfTimes = count($getTimes);
        if ($numberOfTimes > 1) { // multiple opening times per day
            foreach ($getTimes as $value) {
                $startTime = $this->formatTimestring($value['start']);
                $endTime = $this->formatTimestring($value['finish']);
                $times[] = $startTime.' - '.$endTime.$timesuffix;
            }
            $out = implode($separator, $times);
        } else { // single opening time or closed
            if (array_filter($getTimes[0])) {
                $startTime = $this->formatTimestring($getTimes[0]['start']);
                $endTime = $this->formatTimestring($getTimes[0]['finish']);
                $times[] = $startTime.' - '.$endTime.$timesuffix;
                $out = implode('-', $times);
            } else {
                $out = $this->_('closed');
            }
        }
        return $out;
    }


    /**
    * Method to render all times per week in an unordered list
    * @param array $options
    * @return string
    */
    public function render(array $options = []): string
    {
        $defaultOptions =  ['ulclass' => '', 'fulldayName' => false, 'timeseparator' => ', ', 'timesuffix' => ''];
        $options = array_merge($defaultOptions, $options);
        $out = '';
        $out .= '<ul';
        $out .= $options['ulclass'] ? ' class="'.$options['ulclass'].'"' : '';
        $out .= '>';
        foreach (self::getDaysOfTheWeek() as $day => $name) {
            $day = $name[0];
            $out .= '<li class="time day-'.$day.'">';
            $out .= $options['fulldayName'] ? $name[1] : ucfirst($name[0]);
            $out .= ': '.$this->renderDay($day, $options['timeseparator'], $options['timesuffix']);
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    }


    /**
    * Method to output a multidimens. array containing all days with same times combined
    * @return array
    */
    public function combinedDays(): array
    {
        $equalDays = [];
        $allOpeningHours = $this->times;

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
    * Method to render combined opening times as an unordered list
    * @param array $options - various output formatting options
    * ulclass: Set a CSS class to the ul tag
    * fulldayName: true/false -> if set to true the full day name (fe Monday) will be displayed, otherwise only the abbreviation (fe Mo)
    * timeseparator : The sign between multiple opening times on the same day
    * closedText : What should be displayed if it is closed on that day
    * timesuffix: A text that should be displayed after the time
    * @return string
    */

    public function renderCombinedDays(array $options = []): string
    {
        $defaultOptions =  ['ulclass' => '', 'fulldayName' => false, 'timeseparator' => ', ', 'closedText' => $this->_('closed'), 'timesuffix' => ''];
        $options = array_merge($defaultOptions, $options);
        $out = '';
        $out .= '<ul';
        $out .= $options['ulclass'] ? ' class="'.$options['ulclass'].'"' : '';
        $out .= '>';
        foreach ($this->combinedDays() as $key => $arrays) {
            $out .= '<li>';
            $dayNames = [];
            foreach ($arrays['days'] as $key => $names) {
                $dayNames[] = $options['fulldayName'] ? self::getWeekdayNames()[[$names]] : ucfirst($names);
            }
            $out .= implode(', ', $dayNames).': ';
            $dayTimes = [];
            foreach ($arrays['opening_hours'] as $key => $times) {
                if (count(array_filter($times)) === 0) {
                    //closed
                    $dayTimes[] = $options['closedText'];
                } else {
                    $start = $this->formatTimestring($times['start']);
                    $finish = $this->formatTimestring($times['finish']);
                    $dayTimes[] = implode(' - ', ['start' => $start, 'finish' => $finish]).$options['timesuffix'];
                }
            }
            $out .= implode(', ', $dayTimes);
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
    }


    public function __toString()
    {
        return $this->render();
    }
}
