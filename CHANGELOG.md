# Change Log
All notable changes to this project will be documented in this file.

## [1.0.1] - 2020-07-21

- Added multi-language support for timeformat and 2 additional Schema.org markup methods

## [1.0.3] - 2023-06-09

### Adding the module to the module directory
After I published the module a long time ago, no issues were reported. Now it is time to add this module
to the PW module directory. Some changes/improvements have been made, so I set the status to beta at the moment, but
it should be stable.

- Made the module work with the new PHP 8.2 version
- Improved the code by adding union types, so it now needs at least PHP 8.0 to work

## [1.1] - 2023-07-12
This version comes with some feature requests from wbmnfktr from the PW-support forum.

### First request: More possibilities to influence the markup
By default, the opening times are rendered as an unordered list, which is fine in most cases. Until now, you did
not have a lot of possibilities to change the markup (fe to a table or a definition list).
Of course, there is always the option to use the array output and to create the desired markup yourself, but this is
not very handy.
To simplify this, I have extended the configuration options with a lot of different new parameters, listed
below:

* wrappertag (NEW): set the tag for the outermost container (default is ul, can be any other tag or false to disable it)
* wrapperclass (NEW): add a CSS class to the wrapper tag (default: '')
* itemtag (NEW): set the outer tag for the container containing the day opening times per day (default is li, can be any other
tag or false to disable it)
* daytag (NEW): the tag element which surrounds the day name (default: false -> can be set fe as a span tag)
* timetag (NEW): the tag element which surrounds the opening times on that day (default: false -> can be set fe as a span tag)
* dayclass (NEW): a CSS class for the daytag element (default: '' -> means no class is set by default)
* timeclass (NEW): a CSS class for the timetag element (default: false -> means no class is set by default)
* daytimeseparator (NEW): add a string to separate the day name and the times or add false to remove it (default is :, set false to remove it)
* fulldayName: show fullname (true) or dayname abbreviation (false) -> (default: false)
* timeseparator: separator between multiple times (default: ,)
* timesuffix: add text after time string (default: '')
* showClosed: true/false show closed days or not (default: true)
* closedText: overwrite the default text for closed days (default is "closed")

By using these new parameters, it will be much easier to create the markup you want, without using and manipulating the 
array value. The best way to understand these parameters is to change them and to see what has been changed ;-).

To make things even easier: I have added 3 new rendering methods to output the opening times as a table, a
definition list, or simply inside a div container.

3 new methods:

1) renderTable()
2) renderDefinitionList()
3) renderDiv()

You can add 2 parameters to each of these functions inside the parentheses:

a) The options array as the first parameter, to change parameters as listed above (fe adding custom classes)

b) True or false as the second parameter, to render combined days or not.

For more detailed information and to study the examples, please read the docs.

### Second request: Possibility to show/hide the Holiday input
Not everyone wants to use the Holiday opening times. For this reason, I have added a new input field configuration
to the input field (inside the input tab in the backend), where you can select whether you want to display the
Holiday input field or not.

This configuration field is a checkbox: checking it hides the Holiday input field; otherwise, the input field is
displayed.

This also has an impact on the frontend display: if the input field is hidden, then the opening times for
Holidays are also hidden on the frontend.

Thanks to wbmnfktr for his contribution!

## [1.2] - 2023-07-19
This version adds another feature request from Matze: Check if there is at least one time set

Instead of creating a method that returns true or false, I created the new method getNumberOfTimes(), which returns
the number of times that were set. So the return type is an integer. If no times are set, it returns 0. This number
can be used to check whether times are set or not.

## [1.3] - 2024-10-27

- **Support for RockLanguage added**

If you have installed the [RockLanguage](https://processwire.com/modules/rock-language/) module by Bernhard Baumrock, this module now supports syncing the language files. This means that you do not have to worry about new translations after downloading a new version of FieldtypeOpeningHours. All new translations (at the moment only German translations) will be synced with your ProcessWire language files.

Please note: The sync will only take place if you are logged in as Superuser and $config->debug is set to true (take a look at the [docs](https://www.baumrock.com/en/processwire/modules/rocklanguage/docs/)).

Usage of the (old) CSV files is still supported.

## [2.0.0] - 2026-09-22

This version adds support for exceptions (holidays, company vacation, reduced hours) alongside the regular weekly
schedule, plus several smaller improvements and security fixes.

- Added exceptions (holidays/company vacation/reduced hours): each exception has a start/end date, an optional
  multi-language label, can be marked "closed all day" or have its own times, and can optionally repeat every year
- Added a warning when two exceptions' date ranges overlap, and when two time ranges on the same weekday overlap
- Added new array/status methods: `getExceptionForDate()`, `getStatusForDate()`, `getExceptionsForWeek()`,
  `getExceptionsForMonth()`, `getExceptionsForYear()`, `filterActiveExceptions()`
- Added new render methods: `renderExceptionsForWeek()`, `renderExceptionsForMonth()`, `renderExceptionsForYear()`,
  and `renderJsonLDSpecialOpeningHours()`/`getJsonLDSpecialOpeningHours()` for Schema.org's
  `specialOpeningHoursSpecification`
- Added a "Copy to..." button next to each weekday to copy its times to one or more other days
- Fixed an XSS vulnerability in the rendered time input value, and hardened multi-language exception labels against
  invalid/malicious keys
- Improved the admin input field's layout on narrow screens (horizontally scrollable table)
