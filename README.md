# Inputfield-Fieldtype Openinghours for ProcessWire CMS
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](https://opensource.org/licenses/MIT)
[![ProcessWire 3](https://img.shields.io/badge/ProcessWire-3.x-orange.svg)](https://github.com/processwire/processwire)

An Inputfield and Fieldtype to store various times on each day fe business times for a company.
This input field can also be used to enter times of courses (first course starts from 08:00-9:30, second from
10:00-11:30,..), times for theater performances, workshops and so on, but it was primarily developed for opening times
of a company.
Each day can have multiple times (max. 5) or nothing at all (closed).

## Requirements
- ProcessWire 3.0.181 or newer
- PHP 8.0 or newer

## Quick-start guide
To let you use this field without reading the docs first, I have included a quick-start guide inside the configuration of the inputfield. So if you have created a new field of this field type, you will find this quick start guide in the field configuration inside the input tab.

![alt text](https://github.com/juergenweb/FieldtypeOpeningHours/blob/master/images/quickstartguide.png?raw=true)

You only have to copy the preferred code from there and paste it inside your template, and now you can output the opening times. For more advanced integration you will need to read the docs.

## Inputfield

This module let you enter various times per day by using a special designed UI. You can add more times by clicking
an add button. The new input will be created dynamically via JQuery. The status (open/closed) can be set via a
toggle switch.

![alt text](https://github.com/juergenweb/FieldtypeOpeningHours/blob/master/images/inputfield.png?raw=true)

The values will be stored in the database in 1 column in json format. BTW: It is not the proof of concept to store
multiple values in 1 column, but in this case it seems to be the best solution, because we have an unknown number of
times per day.

![alt text](https://github.com/juergenweb/FieldtypeOpeningHours/blob/master/images/database.png?raw=true)

### Configuration

- **`Number of times per day`** You can select how many times are allowed on each day (minimum 1, maximum 5, default 2). In most cases, you will need
two times on each day: morning and afternoon.
- **`Output formatting of time string`** You can also set the output formatting of the time string (default is %R which is equal to an output like 08:00 in
24-hour-format) on the frontend.
The format of the time can be set in date() and strftime() format.
- **`Change tableheader text in input field`** You can alter the text of the table header of the 'times column'. If you are showing opening hours, you will set the
table header as "Opening hours". If you want to enter times for courses, you would probably add a table header like
"Times of courses" or something like that. You can alter the heading of the 'times column' in the backend to fit your
requirements (supports multi-language value).
- **`Show/hide Holiday input`** You can select if you want to use the input field for Holiday or not (default is yes)

## Limitations

This input field does not take account of exceptions. Handling exceptions (fe special opening times on Christmas) is
very complicated. So this module can handle only default times.

### Sanitization and validation (server-side)

A lot of sanitization and validation will take place inside the processInput method to 'clean' user inputs:

- **`duplicate times on one day will be removed`** Only one entry remains of each kind per day (doesn't make sense to have 
same times on one day)
- **`multiple empty times (empty inputs) will be removed`** This can result from clicking the add button multiple times to
create new inputs and do not enter any values.
- **`incomplete times (only start or end time) will be removed`** Every opening time must have a start and end time
- **`inputs which are not a string and/or not in a valid time format will be deleted`**
- **`re-ordering of multiple times on each day by sorting the start times`** Fe first time is 14:00 - 18:00 and second
time is 07:00 - 12:00, then the second one will be the first one after ascending re-ordering.
- **`checking if start time is equal the end time`** If yes, an error message will be shown, because this doesn't make sense
- **`checking if start time is before the end time`** If not, a warning message will be shown. Could only be valid if end
time is on the next day - fe: 20:00 - 03:00.

## Output in templates

There are several methods to output the times in templates.
The following methods return the results as (multidimensional) arrays. You can use these arrays to create the markup
for the output by yourself.

### Array methods
The array methods don't render any markup. They output an array of values which can be displayed fe via 'foreach loops'
inside the templates.
These methods provide raw data for your own markup creation.

#### 1) Get all times a week

```
print_r($page->fieldname->times);
```
The call will always output all times for each day of the week (including holiday) as a multidimensional assoc. array.

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

#### 2) Get the opening times on a specific day

You can use the day abbreviation as an array key to get the times on a specific day:
Abbreviations that can be used: mo, tu, we, th, fr, sa, su, ho. ho stands for Holiday in this case.

Example to get all times on Monday:

```
print_r($page->fieldname->times['mo']);
```

This will output all opening times on Monday as an array:

```
[0] => Array (
    [start] => 14:00
    [finish] => 18:00
    )
```

As you can see in the example above, we will always get an array. This is because we can have multiple times on
each day (not only one, as in this example).

You can use this array on the frontend to create the markup by yourself.


#### 3) Get combined days with same opening hours
Sometimes we have same opening hours on different days. With this method, you can combine and output them as an array.

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

You can set a boolean value as parameter whether to display closed days or not:

```
print_r($page->fieldname->combinedDays(false));
```
Adding false inside the parenthesis will remove days with no times from the array (default is true to show all times).

This method was inspired by Spatie/Openinghours (https://github.com/spatie/opening-hours).

#### 4) Get combined times for Schema.org JsonLD Markup
The following method returns an array with combined opening times for a week. You can use it to create your own render
function for schema.org markup.

```
print_r($page->fieldname->getjsonLDTimes());
```
This returns an array, which combines all days with the same hours.

```
Array ( [0] => Mo,Tu,We 08:00-12:00 [1] => Mo,Th 13:00-18:00 [2] => Th 08:00-11:00 )
```
As you can see, days with the same opening times will be combined. You can use this array to create the markup by
yourself.
The times are always in H:i format (independent of language settings), because Schema.org only accepts this format.
So keep this in mind if you are running a multilingual site.

### Get raw values without output formatting
Tip: If you need the values as stored in the database without output formatting, you have to prevent output formatting by
setting it to false. This prevents the formatValue() method to change the times to the format you have set in the
backend. This is nothing special for this module - this is a standard ProcessWire method.

```
$page->setOutputFormatting(false);
// put here your method/property call
$page->setOutputFormatting(true);
```


### Render methods
The render methods return a string for direct output inside templates. You can use these methods if they satisfy your
needs. If you want to customize your markup it will be better to use the array methods above and create the markup by
your own.
By the way: The render methods have also some parameter settings to influence the output - so a little of
customization is always possible ;-)

#### 1) Render all opening times with the render() method

This is the base rendering method. You have a lot of options to change the output to your needs:

  * wrappertag: set the tag for the outer container (default is ul)
  * wrapperclass: add a CSS class to the wrapper tag (default: '')
  * itemtag: set the outer tag for the container containing the day opening times per day (default is li)
  * daytag: the tag element which surrounds the day name (default: false -> not surrounding element)
  * dayclass: a CSS class for the daytag element (default: false -> no class)
  * timetag: the tag element which surrounds the opening times on that day (default: false -> not surrounding element)
  * timeclass: a CSS class  for the timetag element (default: false -> no class)
  * daytimeseparator: add a string to separate the day name and the times or add false to remove it (default is :)
  * fulldayName: show fullname (true) or dayname abbreviation (false) -> (default: false)
  * timeseparator: separator between multiple times (default: ,)
  * timesuffix: add text after timestring (default: '')
  * showClosed: true/false show closed days or not (default: true)
  * closedText: overwrite the default text for closed days (default is closed)

All of these parameters can be set as an array inside the brackets of the method to overwrite the default parameters.
By default, the opening times will be rendered as an unordered list.

```
echo $page->fieldname->render();
```

This renders all times as an unordered list like this:

```
<ul class="opening-list">
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

A little bit more advanced with some parameters changed.

Explanation of the settings parameter:
* wrappertag: set the tag for the outer container (default is ul)
* daytag: set the tag for the container containing the day opening times (default is li)
* wrapperclass: enter a class for the wrapping element (default:none)
* fulldayName: output the full name (fe Monday) is set to true and the abbreviation (fe Mo) if set to false
  (default: false)
* timeseparator: separator string between the different times per day (default: ',')
* timesuffix: A text that should be displayed after the time (default: none)
* showClosed: true: closed days will be displayed; false: closed days will not be displayed (default: true)

Please use these parameters as an array (see example below) inside the parenthesis to adapt the markup to your needs.

```
echo $page->fieldname->render(['wrappertag' => 'div', 'itemtag' => 'div', 'daytag' => 'span', 'timetag' => 'span', 'wrapperclass' => 'opening-list', 'fulldayName' => true, 'timeseparator' => '; ',
    'timesuffix' => ' h', 'showClosed' => false]);
```

This renders all times in a list like this:

```
<div class="opening-list">
<div class="time day-mo"><span class="day-mo">Monday:</span> <span class="time mo">8:00 - 12:00 h</span></div>
<div class="time day-tu"><span class="day-tu">Tuesday:</span> <span class="time tu">8:00 - 12:00 h</span></div>
<div class="time day-we"><span class="day-we">Wednesday:</span> <span class="time we">8:00 - 12:00 h; 14:00 - 18:00 h</span></div>
<div class="time day-th"><span class="day-th">Thursday:</span> <span class="time th">12:00 - 16:00 h</span></div>
<div class="time day-fr"><span class="day-fr">Friday:</span> <span class="time fr"></span></div><div class="time day-sa"><span class="day-sa">Saturday:</span> <span class="time sa"></span></div>
<div class="time day-su"><span class="day-su">Sunday:</span> <span class="time su"></span></div><div class="time day-ho"><span class="day-ho">Holiday:</span> <span class="time ho"></span></div>
</div>
```

Only to mention: There are also 3 pre-definded functions to render the opening times as a table, a definition list or
using div and span containers. 

You will find the description of these methods later on in these docs.

#### 2) Render only the opening times of one specific day.

Available parameters:

* timeseparator: separator string between the different times per day (default: ',')
* $timesuffix: show some text or markup after the time string (default: none)
* $showClosed = show(true) or hide (false) days with no opening times (default: true)

```
echo $page->fieldname->renderDayTime('mo');

or more advanced with parameters

echo $page->fieldname->renderDayTime('mo', ['timeseparator' => '; ', 'timesuffix' => ' hour', 'showClosed' => true]);
```
This results fe in the following output:

```
08:00-12:00; 14:00-18:00
```
Please note: The deprecated method renderDay() does the same and can be used, but this method name fits better
to the result of this method, so it was renamed.


#### 3) Render combined days with same opening times.

You can set the following parameters inside an options-array to manipulate the output:

```
echo $page->fieldname->renderCombinedDays();

or a little bit more advanced with some parameters

echo $page->fieldname->renderCombinedDays(['ulclass' => 'uk-list', 'fulldayName' => true, 'timeseparator' => '; ',
'closedText' => '-']);
```

This renders all combined days with same times as an unordered list:

```
<ul class="uk-list">
<li class="oh-item">Monday, Tuesday:8:00 - 12:00</li>
<li class="oh-item">Wednesday:8:00 - 12:00, 14:00 - 18:00</li>
<li class="oh-item">Thursday:12:00 - 16:00</li>
<li class="oh-item">Friday, Saturday, Sunday, Holiday:-</li>
</ul>
```

#### 4) Pre-defined rendering functions

As an addition and to keep things short and easy, you can use these methods to get another markup:

On every method you can enter 2 parameters inside the parenthesis: The first one is the option array, which has been 
described before, and the second one is a boolean parameter to render combined times or not.

##### renderTable()
This outputs all opening times inside a table.

```
echo $page->fieldname->renderTable();
```

or with parameters:

```
echo $page->fieldname->renderTable(['wrapperclass' = 'myclass'], true);
```

The first parameter adds the CSS class "myclass" to the table tag and the second parameter forces the output of combined
times.

##### renderDefinitionList()
This outputs all opening times as a definition list.

```
echo $page->fieldname->renderDefinitionList();
```

or with parameters:

```
echo $page->fieldname->renderDefinitionList(['wrapperclass' = 'mydefinitionlist'], true);
```

The first parameter adds the CSS class "mydefinitionlist" to the dl tag and the second parameter forces the output of 
combined times.

##### renderDiv()
This outputs all opening times using classical div and span containers.

```
echo $page->fieldname->renderDiv();
```

or with parameters:

```
echo $page->fieldname->renderDiv(['wrapperclass' = 'mycontainer'], true);
```

The first parameter adds the CSS class "mycontainer" to the wrapping div tag and the second parameter forces the output of
combined times.


#### Render method for JsonLD Schema.org markup

```
echo $page->fieldname->renderjsonLDTimes();
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

The times are always in H:i format (independent of language settings), because Schema.org only accepts this format.
You can find examples of how to create opening hours as structured data at https://schema.org/LocalBusiness

### Multi-language support
All static texts are fully translatable (frontend and backend). The time format on the frontend can also be set for
each language in the backend configuration of the input field (fe default is %R and English is %r).
This will only be taken into account if output formatting is not set to false (default is true).
This module includes also the German translation file.

### To do

At the moment nothing is planned.

### Known issues
If you are using the old default admin template of Processwire and you decrease the screen size, the JavaScript does not
work properly by adding/removing the times per day. 
But this is only the case on reduced screen sizes in combination with the old admin template. If the screen size is
larger, there are no problems. On the UIKit admin template everything works fine.
I recommend you to use the UIKit template.

## How to install

1. Download and place the module folder named "FieldtypeOpeningHours" in:
/site/modules/

2. In the admin control panel, go to Modules. At the bottom of the
screen, click the "Check for New Modules" button.

3. Now scroll to the FieldtypeOpeningHours module and click "Install". The required InputfieldOpeningHours will get
installed automatic.

4. Create a new Field with the new "OpeningHours" Fieldtype.
