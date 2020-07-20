<?php
namespace ProcessWire;

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
    * First item in sub-array is the abbreviation and the second the fullname of the day
    * @return array
    */
    public static function getWeekdays(): array
    {
        return [
            'mo' => [_('Mo'), _('Monday')],
            'tu' => [_('Tu'), _('Tuesday')],
            'we' => [_('We'), _('Wednesday')],
            'th' => [_('Th'), _('Thursday')],
            'fr' => [_('Fr'), _('Friday')],
            'sa' => [_('Sa'), _('Saturday')],
            'su' => [_('Su'), _('Sunday')],
            'ho' => [_('Ho'), _('Holiday')]
        ];
    }

    /**
    * Method to return only the lowercase day abbreviations in an array (['mo','tu',....])
    * @return array
    */
    public static function getDayAbbreviations(): array
    {
        $days = [];
        foreach(self::getWeekdays() as $k=>$v){
          $days[] = $v;
        }
        return $days;
    }


    /**
    * Create a multidimensional array of all times from the onedimensional POST array after form submission
    * This step is requiered for various sanitizations and validations of the input values which cannot be processed from an one-dimensional array
    * @param array $values
    * @return array
    */
    public static function createMultidimArray(array $values): array
    {
        $temp_array = [];
        foreach (self::getWeekdays() as $key=>$dayname) {
            foreach ($values as $k=>$v) {
                if ($key === (explode('-', $k)[1])) {
                    $temp_array[$key][] = $v;
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
        foreach (self::getWeekdays() as $key => $day) { //key = mo,tu,... day = [Mo => Monday]

            foreach ($array as $dayAbbr=>$timesarray) { // key = mo,tu,...value = [times array]

                if ($key === $dayAbbr) {
                    foreach ($timesarray as $keyNum=>$dayArray) { // key = 0,1,2, value = ['start' => '08:00', 'finish' => '16:00']

                        foreach ($dayArray as $name=>$value) {
                            $createdKey = $fieldname.'-'.$key.'-'.$keyNum.'-'.$name;
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
    public static function isTimeValid(string $time_str, $format = 'H:i')
    {
        $DateTime = \DateTime::createFromFormat("d/m/Y {$format}", "10/10/2010 {$time_str}");
        return $DateTime && $DateTime->format("d/m/Y {$format}") == "10/10/2010 {$time_str}";
    }


    /**
   * Return all predefined PHP date() formats for use as times
   *
   * Note: this method moved to the WireDateTime class and is kept here for backwards compatibility.
   *
   * @deprecated Use WireDateTime class instead
   * @return array
   *
   */
    static public function getTimeFormats(): array
    {
      return WireDateTime::_getTimeFormats();
    }


    /**
    * Return all predefined PHP strftime() formats for use as times
    * @return array
    */
    static public function getStrftimeFormats(): array
    {
      return ['%H','%k','%I','%l','%M','%p','%P','%r','%R','%S','%T','%X','%z','%Z'];
    }

    /**
    * Format a time value according to the format settings in the field configuration
    * fe 16:00 will be formatted to 04:00 AM if strftime setting is %r
    * @param string|null $time
    * @param string|null $timeformat
    * @return string
    */
    public static function formatTimestring($time, $timeformat): string
    {
        if ($time) {
            $dateTime = '10.10.2010 '.$time;
            $timeStamp = strtotime($dateTime); // virtual date/time string needed for manipulation
            $timeformat = $timeformat ? $timeformat : self::DEFAULTTIMEFORMAT;
            if (strpos($timeformat, '%') !== false) {
              if(strspn($timeformat, '%') == 1){
                if(!in_array($timeformat, self::getStrftimeFormats())){
                  $timeformat = self::DEFAULTTIMEFORMAT;
                }
                return strftime($timeformat, $timeStamp);
              }
              return '?';
            } else {
              $d = new \DateTime($dateTime);
              //check if date format is correct -> otherwise set it back to a predefined default format
              if(!in_array($timeformat, self::getTimeFormats())){
                $timeformat = 'H:i'; //set it to a default format to prevent error messages
              }
              return $d->format($timeformat);

            }
        }
        return $time;
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
                $times[] = $value['start'].' - '.$value['finish'].$timesuffix;
            }
            $out = implode($separator, $times);
        } else { // single opening time or closed
            if (array_filter($getTimes[0])) {
                $times[] = $getTimes[0]['start'].' - '.$getTimes[0]['finish'].$timesuffix;
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
        foreach (self::getWeekdays() as $day => $name) {
            $out .= '<li class="time day-'.$day.'">';
            $out .= $options['fulldayName'] ? $name[1] : $name[0];
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
            foreach ($arrays['days'] as $key => $dayAbbr) {
                $dayNames[] = $options['fulldayName'] ? self::getWeekdays()[$dayAbbr][1] : self::getWeekdays()[$dayAbbr][0];
            }
            $out .= implode(', ', $dayNames).': ';
            $dayTimes = [];
            foreach ($arrays['opening_hours'] as $key => $times) {
                if (count(array_filter($times)) === 0) {
                    //closed
                    $dayTimes[] = $options['closedText'];
                } else {
                    $dayTimes[] = implode(' - ', ['start' => $times['start'], 'finish' => $times['finish']]).$options['timesuffix'];
                }
            }
            $out .= implode(', ', $dayTimes);
            $out .= '</li>';
        }
        $out .= '</ul>';
        return $out;
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
      array_walk_recursive($times, function(&$value, &$key) {
        if(($key === 'start') || ($key === 'finish')){
          if($value){
            $value = OpeningHours::formatTimestring($value, 'H:i');
          }
        }
      });
      $temp_times = [];
      foreach($times as $day => $times){
        foreach($times as $num => $time){
          $timeStr = array_filter($time);
          $timeStr = implode('-', $timeStr);
          $temp_times[$day.'-'.$num] = $timeStr;
        }
      }
      $times = array_filter($temp_times);
      $val   = array_unique(array_values($times));
      foreach ($val As $v){
        $dat[$v] = array_keys($times,$v);
      }
      $combined = [];
      foreach($dat as $time=>$days){
        $combined[$time] = implode(',',$days);
      }
      //manipulate values
      array_walk($combined, function(&$value, &$key) {
        $values = explode(',', $value);
        $newValues = [];
        foreach($values as $val){
          $newValues[] = ucfirst(substr($val, 0,2));
        }
        $value = implode(',', $newValues);
      });
      $corr = [];
      foreach($combined as $time=>$days){
        $corr[] = $days.' '.$time;
      }

      return ($corr);

    }

    /**
    * Method to render a string of combined opening hours for usage in json LD markup of schema.org
    * Based on https://schema.org/openingHours
    * @return string -> fe "Mo-Fr 10:00-19:00", "Mo-Di 21:00-23:00", "Sa 10:00-22:00", "Su 10:00-21:00"
    */
    public function renderjsonLDTimes(): string
    {
      $out = '';
      $times = $this-> getjsonLDTimes();
      array_walk($times, function(&$value, &$key) {
        $value = '"'.$value.'"';
      });
      return implode(', ', $times);
    }

    public function __toString()
    {
        return $this->render();
    }

}
