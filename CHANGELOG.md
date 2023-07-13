# Change Log
All notable changes to this project will be documented in this file.

## [1.0.1] - 2020-07-21

- Add multilang support for timeformat and add 2 additional Schema.org markup methods 

## [1.0.3] - 2023-06-09

### Adding the module to the module directory
After I had published the module a lot of time ago, no issues will be reported. Now it is time to add this module
to the PW module directory. Some changes/improvements has been done, so I set the status to beta at the moment, but it
should be stable.

- making the module working with new PHP 8.2 version
- Improve the code by adding union types, so it needs at least PHP 8.0 to work

## [1.1] - 2023-07-12
This version comes with some feature requests from wbmnfktr from the PW-support forum.

### First request: More possibilities to influence the markup
By default, the opening times will be rendered as an unordered list, which is fine in most cases. Until now, you had
not a lot of possibilities to change the markup (fe to a table or a definition list).
Of course, there was always the option to use the array output and to create the desired markup by yourself, but this is
not very handy.
To simplify these things I have extended the configuration options with a lot of different new parameters in the list
below:

* wrappertag (NEW): set the tag for the most outer container (default is ul, can be any other tag or false to disable it)
* wrapperclass (NEW): add a CSS class to the wrapper tag (default: '')
* itemtag (NEW): set the outer tag for the container containing the day opening times per day (default is li, can be any other
tag or false to disable it)
* daytag (NEW): the tag element which surrounds the day name (default: false -> can be set fe as a span tag)
* timetag (NEW): the tag element which surrounds the opening times on that day (default: false -> can be set fe as a span tag)
* dayclass (NEW): a CSS class  for the daytag element (default: '' -> means no class is set by default)
* timeclass (NEW): a CSS class  for the timetag element (default: false -> means no class is set by default)
* daytimeseparator (NEW): add a string to separate the day name and the times or add false to remove it (default is :, set false to remove it)
* fulldayName: show fullname (true) or dayname abbreviation (false) -> (default: false)
* timeseparator: separator between multiple times (default: ,)
* timesuffix: add text after time string (default: '')
* showClosed: true/false show closed days or not (default: true)
* closedText (NEW): overwrite the default text for closed days (default is "closed")

By using these new parameters, it will be much easier to create the markup you want, without using and manipulating the 
array value. The best way to understand these parameters is to change them and to see what has been changed ;-).

To make it a further step easier: I have added 3 new rendering methods to output the opening times as a table, a
definition list or simple inside div container. 

3 new methods:

1) renderTable()
2) renderDefinitionList
3) renderDiv()

You can add 2 parameters to each of these functions inside the parenthesis: 

a) The options array as first parameter to change parameters as listed above (fe adding custom classes)
b) True or false as second parameter to render combined days or not.

For more detailed information and to study the examples please read the docs.

### Second request: Possibility to show/hide the Holiday input
Not everyone wants to use the Holiday opening times. For this reason, I have added a new input field configuration
to the input field (inside the input tab in the backend), where you can select if you want to display the Holiday input
field or not.

This configuration field is a checkbox: clicking the checkbox means to hide the Holiday input field, otherwise the
input field will be displayed.

This has also an impact of the display on the frontend: If the input field is hidden, then the opening times for
Holidays on the frontend are also hidden.
