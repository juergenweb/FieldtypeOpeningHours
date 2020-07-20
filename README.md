# Inputfield-Fieldtype Openinghours for ProcessWire CMS
An inputfield and fieldtype to store various times on each day fe opening times for a company.<br />
This inputfield can also be used to enter the times of courses (first course starts from 08:00-9:30, second from 10:00-11:30,..), times for theater performances and so on, but it was primarily developed for opening times of a company.
Each day can have multiple times (max. 10) or nothing at all.<br />

## What it does

This fieldtype let you enter various times per day in in an userfriendly UI. You can add more times by clicking an add button. The new input will be built dynamically via JQuery. The status (open/closed) can be set via a toggle switch.<br /><br />
![alt text](https://github.com/juergenweb/FieldtypeOpeningHours/blob/master/OpeningHours.jpg?raw=true)<br />
The values will be stored in the database in 1 column in json format. It is not recommended to store multiple values in 1 column but in this case it is a possibility because we have an unknown number of times on each day.<br /><br />
![alt text](https://github.com/juergenweb/FieldtypeOpeningHours/blob/master/OpeningHoursDatabase.jpg?raw=true)

## What it does not

This inputfield does not take account on exceptions. To take account of exceptions is very difficult because you have to handle different durations (days, weeks, moths) and you have to handle recurrences too (fe every first Monday on each month, every year,...). So there are so much possibilities and therefore it will be very difficult to find a working solution.

### Sanitization and validation (server-side)

A lot of sanitization validation will take place inside the processInput method to 'clean' user inputs:

- duplicate times on one day will be removed - only one entry remains of each kind per day (doesnt make sense to have same times on one day)
- multiple empty times (empty inputs) will be removed. This can result from clicking the add button multiple times to create new inputs and do not enter any values.
- incomplete times (only start or end time) will be removed - every opening time must have a start and end time, otherwise they are invalid.
- inputs which are not a string and/or not in a valid timeformat will be deleted.
- re-ordering of multiple times on each day by sorting the start times (fe first time is 14:00 - 18:00 and second time is 07:00 - 12:00, then the second one will be the first one after ascending re-ordering).
- checking if start time is equal end time (if yes then an error message will be shown, because this doesnt make sense)
- checking if start time is before end time (if not then a warning message will be shown. Could only be valid if end time is on the next day - fe: 20:00 - 03:00).
- if the max. number of times per day (configured in the backend) is reached, then all times after that will be removed. Fe. a user enters 4 times on one day and only 3 times are allowed, then the last time will be removed automatically. The max number of times will be controlled by Jquery on the frontend too and let the user only enter the max number of times.  

## Output the values in templates

There are several methods to output the times in templates.
The following methods return the results as (multidimensional) arrays. You can use these arrays to create the markup by yourself.

### Array methods
The array methods doesnt render any markup. They output an array of values which can be displayed fe via foreach loops inside the templates.
These methods provide raw data for personal markup creation.

#### 1) Get all times a week

```
print_r($page->fieldname->times);
```
The call will always output all times for each day of the week (including holiday) as an multidimensional assoc. array.

```
[mo] => Array (
    [0] => Array (
        [start] => 14:00
        [finish] => 18:00
        )
    )
[tu] => Array (
    [0] => Array (
        [start] => 08:00
        [finish] => 12:00
        )
    [1] => Array (
        [start] => 14:00
        [finish] => 18:00
        )
    [2] => Array (
        [start] => 20:30
            ...
            ...
[ho] => Array (
    [0] => Array (
        [start] =>
        [finish] =>
        )
    )
]
```

If a day has no times (like ho in this example) means that the company is closed on that day.

#### 2) Get all times a week without output formatting
If you need the values as stored in the database without output formatting, you have to prevent output formatting by setting it to false. This prevent the formatValue() method to change the times to the format you have set in the backend.

```
$page->setOutputFormatting(false);
print_r($page->fieldname->times);
$page->setOutputFormatting(true);
```
Be aware to not forget to set output formatting to true afterwards.

#### 3) Get the opening times on a specific day<br />
You can use the following day abbreviation to select the specific day:<br />
mo,tu,we,th,fr,sa,su,ho. ho stands for holiday in this case.<br />
If you want fe all opening times for Monday you will use the following method and set as paramater the day inside the parenthesis.


```
print_r($page->fieldname->times['mo']);
```

This will output all opening times of Monday in the following array:

```
[0] => Array (
    [start] => 14:00
    [finish] => 18:00
    )
```

As you can see we will always output an array, because we can have multiple times on each day.<br />
You can use this array to create the markup by yourself, so you are completely independent.


#### 4) Get combined days with same opening hours.<br />
Sometimes we have same opening hours on different days. With this method you can combine them and output an array.

```
print_r($page->fieldname->combinedDays());
```
This will output an array like this:
```
[mo] => Array (
    [days] => Array (
        [0] => mo
        [1] => tu
        )
    [opening_hours] => Array (
        [0] => Array (
            [start] => 08:00
            [finish] => 16:00
            )
        )
    )
[we] => Array (
    [days] => Array (
        [0] => we
        )
    [opening_hours] => Array (
        [0] => Array (
            [start] => 16:00
            [finish] => 23:05
            )
        )
    )
[th] => Array (
    [days] => Array (
        [0] => th
        [1] => fr
        [2] => sa
        [3] => su
        [4] => ho
        )
    [opening_hours] => Array (
        [0] => Array (
            [start] =>
            )
        )
    )

```
This method was inspired by Spatie/Openinghours (https://github.com/spatie/opening-hours).

#### 5) Get combined times for Schema.org JsonLD Markup (only 1 time)
The following method returns an array with combined opening times for a week. You can use it to create your own render function for schema.org markup.

```
$page->setOutputFormatting(false);
print_r($page->fieldname->getjsonLDTimes());
$page->setOutputFormatting(true);
```
This returns an array as fe the following:

```
Array ( [0] => Mo,Tu,We 08:00-12:00 [1] => Mo,Th 13:00-18:00 [2] => Th 08:00-11:00 )
```
As you can see days with the same opening times will be combined and output in the value. You can use this array to create the markup by yourself (or you use the pre-defined render method afterwards).
Be aware to set outputformatting to false before, because schema.org accepts only 24 hours format and no fe 08:00 AM or something like that. This must be language independent.


### Render methods
The render methods return a string for direct output in the templates. You can use these methods if they satisfy your needs. If you want to customize your markup it will be better to use the array methods above and create the markup by yourself.

#### 1) Render all opening times

This renders all opening times in an unordered list. You can set some options like ulclass, fulldayName timesseparator and timesuffix to change the markup a little bit.
Render methods take care of the format configuration settings in the backend, because the will run through the formatValue() before output.

* ulclass: enter a class for the unordered list (default:none)
* fulldayName: output the fullname (fe Monday) is set to true and the abbreviation (fe Mo) if set to false (default: false)
* timeseparator: separator string between the different times per day (default: ,)
* timesuffix: A text that should be displayed after the time

```
echo $page->fieldname->render();

or a little bit more advanced with some parameters

echo $page->fieldname->render(['ulclass' => 'uk-list', 'fulldayName' => true, 'timeseparator' => '; ', 'timesuffix' = ' h']);
```

This renders all times in an unordered list:

```
<ul class="uk-list">
  <li class="time day-mo">Monday: 11:00-11:30 h; 12:00-13:00 h; 14:00-15:00 h</li>
  <li class="time day-tu">Tuesday: closed</li>
  <li class="time day-we">Wednesday: closed</li>
  <li class="time day-th">Thursday: closed</li>
  <li class="time day-fr">Friday: closed</li>
  <li class="time day-sa">Saturday: closed</li>
  <li class="time day-su">Sunday: closed</li>
  <li class="time day-ho">Holiday: closed</li>
</ul>
```

#### 2) Render only the opening time of one specific day.

* timeseparator: separator string between the different times per day (default: ,)

```
echo $page->fieldname->renderDay('mo');

or more advanced with a timeseparator parameter

echo $page->openinghours->renderDay('mo', '; ');
```
This leads to the following output:

```
08:00-12:00; 14:00-18:00
```
#### 3) Render combined days with same opening times.
You can set the following parameters inside an options-array to manipulate the output:

* ulclass: enter a class for the unordered list (default:none)
* fulldayName: output the fullname (fe Monday) is set to true or the abbreviation (fe Mo) if set to false (default: false)
* timeseparator: separator string between the different times per day (default: ,)
* closedText: Text (or other markup) that should be displayed if it is closed on that day (default: closed)
* timesuffix: A text that should be displayed after the time

```
echo $page->fieldname->renderCombinedDays();

or a little bit more advanced with some parameters

echo $page->fieldname->renderCombinedDays(['ulclass' => 'uk-list', 'fulldayName' => true, 'timeseparator' => '; ', 'closedText' => '-']);
```
This renders all combined days with same times in an unordered list:

```
<ul class="uk-list">
  <li>Mo, Fr: 08:00 - 16:00</li>
  <li>Tu, Th: 08:00 - 16:00, 18:00 - 20:00</li>
  <li>We: 16:00 - 23:05</li>
  <li>Sa, Su, Ho: closed</li>
</ul>
```

#### Render method for JsonLD Schema.org markup

```
$page->setOutputFormatting(false);
echo $page->fieldname->renderjsonLDTimes();
$page->setOutputFormatting(true);
```
This method renders a string like this:

```
"Mo,Tu,We 08:00-12:00", "Mo,Th 13:00-18:00", "Th 08:00-11:00"
```

This string can be used in schema.org markup of Local business opening times like this:
```
.....
"openingHours": [
    "Mo-Sa 11:00-14:30",
    "Mo-Th 17:00-21:30",
    "Fr-Sa 17:00-22:00"
  ],
.....
```
You can find examples at https://schema.org/LocalBusiness

Be aware to set outputformatting to false before, because schema.org accepts only 24 hours format and no fe 08:00 AM or something like that. This must be language independent.

### Multilanguage support
All static texts are fully translateable (frontend and backend). The timeformat on the frontend can also be set for each language in the backend configuration of the inputfield (fe Default %R and English %r).
This will only be taken into account if outputformatting is on (default).

### Field Settings

You can select how many times are allowed on each day (minimum 1, maximum 10, default 2). In most cases you will need two times on each day: morning and afternoon.<br />
You can also set the output formatting of the time string (default is %R which is equal to an output like 08:00) on the frontend. 


### To do

At the moment nothing planned.

## How to install

1. Download and place the module folder named "FieldtypeOpeningHours" in:
/site/modules/

2. In the admin control panel, go to Modules. At the bottom of the
screen, click the "Check for New Modules" button.

3. Now scroll to the FieldtypeOpeningHours module and click "Install". The required InputfieldOpeningHours will get installed automatic.

4. Create a new Field with the new "OpeningHours" Fieldtype.
