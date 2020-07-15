<?php
namespace ProcessWire;

/**
 * Helper WireData Class
 */

class OpeningHours extends WireData
{
    const DAYS = ['mo','tu','we','th','fr','sa','su','ho'];

    public function __construct()
    {
        //set default values
        parent::__construct();
        try {
            $this->set('times', json_decode(file_get_contents(__DIR__ . '/defaultData.json'), true));
            $this->set('timeformat', '%R');
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
            $timeformat = $this->timeformat ? $this->timeformat : '%R';
            return strftime($timeformat, $timeStamp);
        }
        return $time;
    }


    /**
    * Renders a string of opening times on a specific day
    * @param string $day
    * @param string $separator
    * @return string
    */
    public function renderDay(string $day, string $separator = ', '): string
    {
        $getTimes = $this->times[$day];
        $times = [];
        $numberOfTimes = count($getTimes);
        if ($numberOfTimes > 1) { // multiple opening times per day
            foreach ($getTimes as $value) {
                $startTime = $this->formatTimestring($value['start']);
                $endTime = $this->formatTimestring($value['finish']);
                $times[] = $startTime.' - '.$endTime;
            }
            $out = implode($separator, $times);
        } else { // single opening time or closed
            if (array_filter($getTimes[0])) {
                $startTime = $this->formatTimestring($getTimes[0]['start']);
                $endTime = $this->formatTimestring($getTimes[0]['finish']);
                $times[] = $startTime.' - '.$endTime;
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
        $defaultOptions =  ['ulclass' => '', 'fulldayName' => false, 'timeseparator' => ', '];
        $options = array_merge($defaultOptions, $options);
        $out = '';
        $out .= '<ul';
        $out .= $options['ulclass'] ? ' class="'.$options['ulclass'].'"' : '';
        $out .= '>';
        foreach (self::getDaysOfTheWeek() as $day => $name) {
            $day = $name[0];
            $out .= '<li class="time day-'.$day.'">';
            $out .= $options['fulldayName'] ? $name[1] : ucfirst($name[0]);
            $out .= ': '.$this->renderDay($day, $options['timeseparator']);
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
