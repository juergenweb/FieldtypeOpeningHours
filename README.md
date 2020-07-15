# Inputfield-Fieldtype Openinghours for ProcessWire CMS
An inputfield and fieldtype to store various times on each day fe opening times for a company.<br />
This inputfield can also be used to enter the times of courses (first course starts from 08:00-9:30, second from 10:00-11:30,..), times for theater performances and so on, but it was primarily developed for opening times of a company.
Each day can have multiple times (max. 5) or nothing at all.<br />

## What it does

This fieldtype let you enter various times per day in in an userfriendly UI. You can add more times by clicking an add button. The new input will be built dynamically via JQuery. The status (open/closed) can be set via a toggle switch.<br />
![alt text](https://github.com/juergenweb/Processwire-Simple-Address-Inputfield-Fieldtype/blob/master/OpeningHours.jpg?raw=true)
The values will be stored in the database in 1 column in json format. It is not recommended to store multiple values in 1 column but in this case it is a possibility because we have an unknown number of times on each day.
![alt text](https://github.com/juergenweb/Processwire-Simple-Address-Inputfield-Fieldtype/blob/master/OpeningHoursDatabase.jpg?raw=true)

### Sanitization and validation (server-side)

A lot of sanitization validation will take place inside the processInput method to 'clean' user inputs:

- duplicate times on one day will be removed - only one entry remains of each kind per day (doesnt make sense to have same times on one day)
- multiple empty times (empty inputs) will be removed. This can result from clicking the add button multiple times to create new inputs and do not enter any values.
- incomplete times (only start or end time) will be removed - every opening time must have a start and end time, otherwise they are invalid.
- inputs which are not a string and/or not in a valid time format will be deleted.
- re-ordering of multiple times on each day by sorting the start times (fe first time is 14:00 - 18:00 and second time is 07:00 - 12:00, then the second one will be the first one after ascending re-ordering).
- checking if start time is equal end time (if yes then an error message will be shown, because this doesnt make sense)
- checking if start time is before end time (if not then a warning message will be shown. Could only be valid if end time is on the next day - fe: 20:00 - 03:00).
- if the max. number of times per day (configured in the backend) is reached, then all times after that will be removed. Fe. a user enters 4 times on one day and only 3 times are allowed, then the last time will be removed automatically. The max number of times will be controlled by Jquery on the frontend too and let the user only enter the max number of times.  

## Output the values in templates

There are several methods to output the times in templates.
The following methods return the results as (multidimensional) arrays. You can use these arrays to create the markup by yourself.

### Array methods
The array methods doesnt render any markup. They output an array of values which can be displayed fe via foreach loops inside the templates.
These methods provide raw data for personal markup creation. It doesnt take account to timeformatting.

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

#### 2) Get the opening times on a specific day.<br />
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

### Render methods
The render methods returns a string for direct output in the templates. You can use these methods if they satisfy your needs. If you want to customize your markup it will be better to use the array methods above and create the markup by your own.

#### 1) Render all opening times

This renders all opening times in an unordered list. You can set some options like ulclass, fulldayName and timesseparator to change the markup a little bit.
Render methods take care of the format configuration settings in the backend.

* ulclass: enter a class for the unordered list (default:none)
* fulldayName: output the fullname (fe Monday) is set to true and the abbreviation (fe Mo) if set to false (default: false)
* timeseparator: separator string between the different times per day (default: ,)

```
echo $page->fieldname->render();

or a little bit more advanced with some parameters

echo $page->fieldname->render(['ulclass' => 'uk-list', 'fulldayName' => true, 'timeseparator' => '; ']);
```

This renders all times in an unordered list:

```
<ul class="uk-list">
  <li class="time day-mo">Monday: 11:00-11:30; 12:00-13:00; 14:00-15:00</li>
  <li class="time day-tu">Tuesday: closed</li>
  <li class="time day-we">Wednesday: closed</li>
  <li class="time day-th">Thursday: closed</li>
  <li class="time day-fr">Friday: closed</li>
  <li class="time day-sa">Saturday: closed</li>
  <li class="time day-su">Sunday: closed</li>
  <li class="time day-ho">Holiday: closed</li>
</ul>
```

#### 2) Render only the opening of one specific day.

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

### Field Settings

You can select how many times are allowed on each day (minimum 1, maximum 10, default 2). In most cases you will need two times on each day: morning and afternoon.<br />
You can also set the output formatting of the time string (default is %R which is equal to an output like 08:00) on the frontend.


### To do

At the moment nothing planned. Maybe multilang. settings of time format.

## How to install

1. Download and place the module folder named "FieldtypeOpeningHours" in:
/site/modules/

2. In the admin control panel, go to Modules. At the bottom of the
screen, click the "Check for New Modules" button.

3. Now scroll to the FieldtypeOpeningHours module and click "Install". The required InputfieldOpeningHours will get installed automatic.

4. Create a new Field with the new "OpeningHours" Fieldtype.
