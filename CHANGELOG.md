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
This version comes with some feature requests from wbmnfktr from the PW-support forum. One of the wishes was 
to extend the changeability of the mark-up to support other mark-ups too without manipulation of the arrays.
The opening times should also be displayed fe as a table or a definition list.

So I have added a lot of new styling parameters, which can be uses via the rendering functions. All parameters
marked with NEW are added to this version.

* wrappertag (NEW): set the tag for the outer container (default is ul, can be any other tag or false to disable it)
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
* timesuffix: add text after timestring (default: '')
* showClosed: true/false show closed days or not (default: true)
* showHoliday (NEW): true/false show Holiday or not (default: true)
* closedText (NEW): overwrite the default text for closed days (default is closed)

2 new rendering methods added:

1) renderTable()
2) renderDefinitionList
3) renderDiv()

These are pre-definded render methods to output the opening times as a table, a definition list or using div containers.
You can add 2 parameters to each of these functions: 

a) The options array as first parameter to change some markup styling (fe adding custom classes)
b) True of false as second parameter to render combinded days or not.

For more detailed information an examples please read the docs.
