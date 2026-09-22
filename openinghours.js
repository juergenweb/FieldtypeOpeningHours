/*
JavaScript for opening hours inputfield
by Jürgen Kern
*/

// Wrapped in an IIFE so this file's own variables/functions (ohTexts, ohBuildTimePair,
// ohSyncExceptions, ...) stay private to this file instead of leaking into the global
// scope, where they could collide with same-named variables from another ProcessWire
// module's own admin JS loaded on the same page-edit screen. "$" is passed in explicitly
// as an argument (jQuery.noConflict()-safe) rather than relied on as a global, in case
// another script on the page reassigns the global "$" to something other than jQuery.
(function($) {

$(document).ready(function() {

  //translated texts for the markup this file builds dynamically in the browser, written
  //into ProcessWire.config.ohTexts by InputfieldOpeningHours::init() (via $config->js(),
  //using PHP's own $this->_('...') translation calls - see getTranslateableTexts() there).
  //The English fallbacks here only apply if that config value is missing for some reason.
  var ohTexts = $.extend({
    add: 'Add',
    remove: 'Remove',
    removeException: 'Remove Exception',
    label: 'Label',
    dates: 'Dates',
    from: 'From',
    to: 'To',
    closedAllDay: 'Closed all day',
    repeatEveryYear: 'Repeat every year',
    hours: 'Hours',
    labelPlaceholder: "fe New Year's Day"
  }, (window.ProcessWire && ProcessWire.config && ProcessWire.config.ohTexts) || {});

  //uncheck all checkboxes where row has status closed on page load
  //check all checkboxes where row has status open on page load
  $('.togglestatus').each(function() {
    var parentRow = $(this).closest('tr');
    if ($(parentRow).hasClass('closed')) {
      $(this).prop("checked", false);
    } else {
      $(this).prop("checked", true);
    }
  });


  //set active or inactive (open/close) status for each day
  $(".togglestatus").click(function() {
    //get status
    var isChecked = $(this).is(':checked');
    // Add or remove open or closed class
    var tableRow = $(this).parent().parent().parent();
    if (isChecked) {
      $(tableRow).addClass('open');
      $(tableRow).removeClass('closed');
    } else {
      $(tableRow).addClass('closed');
      $(tableRow).removeClass('open');
      //remove all tr except the first
      var id = this.id;
      var items = id.split('-');
      var field = items[0];
      var day = items[2];
      var table = $('#' + field + '-hours-' + day);
      $('#' + field + '-hours-' + day + ' tr:gt(0)').remove();
      //remove values from the first row
      $('#' + field + '-hours-' + day + ' tr:first :input').each(function() {
        $(this).val("");
      });
      //remove disabled attribute if present from add button
      var addBtn = $('#' + field + '-add-' + day);
      addBtn.removeAttr('disabled');
    }
  });

  // Remove the current row by clicking on the remove button
  $(document).on("click", ".remove-btn", function() {
    var idSplit = $(this).attr('id').split('-');
    var field = idSplit[0];
    var item = idSplit[2];
    var row = idSplit[3];
    //remove this row with the specific id
    $('#' + field + '-' + item + '-' + row).fadeOut(300).promise().done(function() {
      $(this).remove();
    });
    //remove disabled attribute from add button if it was set before
    var addBtn = $('#' + field + '-add-' + item);
    addBtn.removeAttr('disabled');
  });

  // add new row add the end by clicking on the add button
  $(document).on("click", ".add-btn", function() {
    //get the day number
    var splitID = $(this).attr('id').split('-');
    var field = splitID[0];
    var dayName = splitID[2];
    var $dayTable = $('#' + field + '-hours-' + dayName); //fe openinghours-hours-mo
    var $existingRows = $dayTable.find('tbody > tr');
    //get number of existing items in the times table (used for the visible row count and the max-times check)
    var number = $existingRows.length;
    //restrict number of times to the configured max
    var maxTimes = $(this).attr('data-max');
    //check if number of max items per day is reached
    if (number < maxTimes) {
      //determine the next free row index: after a row has been removed, the remaining rows'
      //indices are no longer contiguous, so re-using the row count as the new index could
      //collide with an existing row's id/name (causing duplicate form field names and broken
      //remove-button targeting). Use the highest existing index + 1 instead.
      var nextIndex = -1;
      $existingRows.each(function() {
        var idParts = this.id.split('-');
        var idx = parseInt(idParts[idParts.length - 1], 10);
        if (!isNaN(idx) && idx > nextIndex) {
          nextIndex = idx;
        }
      });
      nextIndex = nextIndex + 1;
      //get Labels
      var fromLabel = document.getElementsByClassName('from')[0].innerHTML;
      var toLabel = document.getElementsByClassName('to')[0].innerHTML;
      //add a new tableRow
      var $newElement = '<tr id="' + field + '-' + dayName + '-' + nextIndex + '" style="display:none">' +
        '<td><span class="number">' + (number + 1) + '</span></td>' +
        '<td><label class="openinghours-label from">' + fromLabel + '</label><input type="time" name="' + field + '-' + dayName + '-' + nextIndex + '-start" value=""/></td>' +
        '<td><label class="openinghours-label to">' + toLabel + '</label><input type="time" name="' + field + '-' + dayName + '-' + nextIndex + '-finish" value=""/></td>' +
        '<td><button id="' + field + '-remove-' + dayName + '-' + nextIndex + '" class="remove-btn ui-button ui-priority-secondary" type="button">' + ohTexts.remove + '</button></td>' +
        '</tr>';
      $dayTable.find('> tbody').append($newElement);
      $('#' + field + '-' + dayName + '-' + nextIndex).fadeIn(300);
    } else {
      //disable add button and add hover class
      $(this).attr('disabled', 'disabled');
    }
  });

  // ---- "Copy to..." popover (copy one weekday's times onto other weekdays) ----
  // The button and its popover (day checklist + Apply/Cancel) are rendered server-side once
  // per day by InputfieldOpeningHours::renderCopyDayPopover() - see there - so this only
  // needs to toggle visibility and, on Apply, move the source day's current input values
  // onto the checked target day(s).

  //open/close a day's own popover; closes any other open popover first, so only one is
  //ever visible at a time
  $(document).on('click', '.oh-copy-day-btn', function(e) {
    e.preventDefault();
    e.stopPropagation();
    //look inside the shared "td.weekday" cell rather than relying on the popover being an
    //immediate DOM sibling of the button - safer if some other admin-theme script ever
    //re-wraps the button element itself (fe a jQuery UI/Uikit button enhancement)
    var $popover = $(this).closest('td').find('.oh-copy-day-popover');
    $('.oh-copy-day-popover').not($popover).hide();
    $popover.toggle();
  });

  $(document).on('click', '.oh-copy-day-cancel-btn', function() {
    $(this).closest('.oh-copy-day-popover').hide();
  });

  //clicking anywhere outside an open popover closes it without applying anything
  $(document).on('click', function(e) {
    if (!$(e.target).closest('.oh-copy-day-popover, .oh-copy-day-btn').length) {
      $('.oh-copy-day-popover').hide();
    }
  });

  $(document).on('click', '.oh-copy-day-apply-btn', function() {
    var $popover = $(this).closest('.oh-copy-day-popover');
    var sourceDay = $popover.attr('data-day');
    //the popover sits inside "<field>-table", so its id gives us the field name back
    var field = $popover.closest('table.openinghours-table').attr('id').replace(/-table$/, '');

    //read the source day's pairs straight from its current inputs, not the originally
    //rendered values, so edits made just before clicking "Copy to..." are picked up too.
    //Matched by "name" (not "id"): a row added via the "Add" button only ever gets a
    //"name" attribute (see the "add-btn" handler above), never an "id", so matching on
    //"id" would silently skip any time pair beyond the first one.
    var sourcePairs = [];
    $('#' + field + '-hours-' + sourceDay + ' tbody > tr').each(function() {
      var $row = $(this);
      sourcePairs.push({
        start: $row.find('input[name$="-start"]').val() || '',
        finish: $row.find('input[name$="-finish"]').val() || ''
      });
    });
    var sourceIsOpen = !!(sourcePairs.length && sourcePairs[0].start);

    var targetDays = [];
    $popover.find('.oh-copy-day-target:checked').each(function() {
      targetDays.push($(this).val());
    });

    targetDays.forEach(function(day) {
      //reset the target day down to a single, empty row first - mirrors what the
      //.togglestatus handler above does when a day is switched to "closed"
      $('#' + field + '-hours-' + day + ' tr:gt(0)').remove();
      $('#' + field + '-hours-' + day + ' tr:first :input').val('');

      if (sourcePairs.length) {
        var $firstRow = $('#' + field + '-hours-' + day + ' tbody > tr:first');
        $firstRow.find('input[name$="-start"]').val(sourcePairs[0].start);
        $firstRow.find('input[name$="-finish"]').val(sourcePairs[0].finish);
        //click "Add" once per additional source pair instead of building new rows by hand,
        //so the existing max-times limit and button-disabling logic in that handler above
        //keeps working exactly as it does for a manual click. Matched by "name", not "id" -
        //a row added this way only gets a "name" attribute, never an "id".
        for (var i = 1; i < sourcePairs.length; i++) {
          $('#' + field + '-add-' + day).click();
          var $newRow = $('#' + field + '-hours-' + day + ' tbody > tr:last');
          $newRow.find('input[name$="-start"]').val(sourcePairs[i].start);
          $newRow.find('input[name$="-finish"]').val(sourcePairs[i].finish);
        }
      }

      //reflect the copied open/closed status on the target day's own toggle + row class,
      //same as the .togglestatus handler above does for a manual click
      var $toggle = $('#' + field + '-toggle-' + day);
      var $dayRow = $toggle.closest('tr');
      $toggle.prop('checked', sourceIsOpen);
      if (sourceIsOpen) {
        $dayRow.addClass('open').removeClass('closed');
      } else {
        $dayRow.addClass('closed').removeClass('open');
      }
    });

    //uncheck every target checkbox again, so the next time this popover is opened it
    //starts empty instead of still showing the days just copied to
    $popover.find('.oh-copy-day-target').prop('checked', false);

    $popover.hide();
  });

  // ---- Exceptions section (holidays / company vacation / reduced hours) ----
  // rendered directly below the weekly schedule table on the page-edit screen (see
  // InputfieldOpeningHours::renderExceptionsSection()). A page can have more than one
  // OpeningHours field, so everything below is scoped per ".oh-exceptions-list" instance
  // instead of assuming a single, page-wide list/hidden field.

  //CSS classes the currently active admin theme uses for plain inputs/checkboxes (fe
  //AdminThemeUikit: "uk-input"/"uk-checkbox"), written into ProcessWire.config.ohInputClasses
  //by InputfieldOpeningHours::getExceptionInputClasses() via $config->js(). Applying these
  //to dynamically added rows/times keeps them visually identical to the PHP-rendered ones
  //and to every other input on the page-edit screen, instead of hardcoding one theme's classes.
  var ohConfig = (window.ProcessWire && ProcessWire.config && ProcessWire.config.ohInputClasses) || {};
  var ohInputClass = ohConfig.input ? ' ' + ohConfig.input : '';
  var ohCheckboxClass = ohConfig.checkbox ? ' ' + ohConfig.checkbox : '';
  //field-caption labels ("Label", "Dates", "Hours") get the admin theme's own label class
  //(fe "uk-form-label"), same as the field's other inputs - not the compact "From"/"To"
  //sub-labels, which keep their own dedicated inline styling
  var ohLabelAttr = ohConfig.label ? ' class="' + ohConfig.label + '"' : '';

  //read a given exceptions list's own settings from its data attributes: the languages to
  //render a label input for (fe [{"id":"default","label":"Default"}, {"id":"1234","label":"Deutsch"}]),
  //written by InputfieldOpeningHours::getExceptionLanguagesJson(), and the max number of
  //time-pairs allowed per exception (same setting - numberOftimes - that limits the number
  //of times per weekday in the regular schedule above), written by
  //InputfieldOpeningHours::renderExceptionsConfigMarkup()
  var ohGetListConfig = function($list) {
    var languages = [];
    try {
      languages = JSON.parse($list.attr('data-languages') || '[]');
    } catch (e) {
      languages = [];
    }
    if (!languages.length) {
      languages = [{ id: 'default', label: 'Default' }];
    }
    var maxTimes = parseInt($list.attr('data-max-times'), 10);
    if (isNaN(maxTimes) || maxTimes < 1) {
      maxTimes = 1;
    }
    return { languages: languages, maxTimes: maxTimes };
  };

  //build a single start/finish time-pair, mirroring the regular day-times table further up
  //in this file: the "Add" button sits inline in the first pair's row (isFirst=true) and
  //every additional pair gets its own inline "Remove" button instead. "index" (0-based) is
  //shown as the same "(#1)", "(#2)", ... numbering used by the weekday times, and - just
  //like there - is not renumbered again once a pair in between is removed.
  // note: deliberately NOT using the "add-btn"/"remove-btn" classes from the regular
  // day-times markup - those classes already have their own document-level click handlers
  // (targeting id patterns like "field-add-mo"/"field-remove-mo-1") which would otherwise
  // also fire here and throw, since these buttons have no such id
  //
  // Rendered as an actual <tr> (inside the <table class="openinghours"> built by
  // ohBuildTimesInputs below), matching the regular weekday times table markup instead of
  // a div-based layout. The ".oh-exception-time-pair" class used to find/add/remove/sync
  // rows stays the same, just on a <tr> now instead of a <div>.
  var ohBuildTimePair = function(maxTimes, isFirst, index) {
    var button = isFirst ?
      '<td><button type="button" class="ui-button oh-exception-time-add-btn" data-max="' + maxTimes + '">' + ohTexts.add + '</button></td>' :
      '<td><button type="button" class="ui-button ui-priority-secondary oh-exception-time-remove-btn">' + ohTexts.remove + '</button></td>';
    return '<tr class="oh-exception-time-pair">' +
      '<td><span class="number">' + (index + 1) + '</span></td>' +
      '<td><label class="openinghours-label from">' + ohTexts.from + ':</label><input type="time" class="oh-exception-time-start' + ohInputClass + '"/></td>' +
      '<td><label class="openinghours-label to">' + ohTexts.to + ':</label><input type="time" class="oh-exception-time-finish' + ohInputClass + '"/></td>' +
      button +
      '</tr>';
  };

  //build the "Hours" field content: an actual <table>, matching the regular weekday times
  //table further up in this file, with one time-pair row to start with (with the inline
  //"Add" button), up to the configured max (maxTimes). The ".oh-exception-time-list" class
  //used elsewhere in this file to find/append rows stays the same, just on the <tbody> now
  //instead of a <div>. "AdminDataTable AdminDataList AdminDataTableResponsive" are the same
  //core ProcessWire classes the outer weekday table carries (see the "openinghours-table"
  //id="...-table" markup rendered server-side) - hardcoded defaults in the core
  //MarkupAdminDataTable module, so they apply regardless of the active admin theme.
  var ohBuildTimesInputs = function(maxTimes) {
    return '<table class="AdminDataTable AdminDataList AdminDataTableResponsive openinghours"><tbody class="oh-exception-time-list">' + ohBuildTimePair(maxTimes, true, 0) + '</tbody></table>';
  };

  //build the "Label" cell for a (new) row: a single plain input on a single-language
  //site, or - mirroring ProcessWire's native language tabs - a small tab strip plus one
  //input per language, with only the active tab's input visible (see the ".oh-lang-tab"
  //click handler below)
  var ohBuildLabelInputs = function(languages) {
    if (languages.length <= 1) {
      return '<input type="text" class="oh-exception-label' + ohInputClass + '" data-lang="default" placeholder="' + ohTexts.labelPlaceholder + '"/>';
    }
    var tabs = '<ul class="oh-lang-tabs">';
    var inputs = '<div class="oh-lang-input-wrap">';
    for (var i = 0; i < languages.length; i++) {
      var activeClass = i === 0 ? ' oh-lang-tab-active' : '';
      tabs += '<li class="oh-lang-tab' + activeClass + '" data-lang="' + languages[i].id + '">' + languages[i].label + '</li>';
      var style = i === 0 ? '' : ' style="display:none"';
      inputs += '<input type="text" class="oh-exception-label' + ohInputClass + '" data-lang="' + languages[i].id + '" placeholder="' + ohTexts.labelPlaceholder + '"' + style + '/>';
    }
    tabs += '</ul>';
    inputs += '</div>';
    return '<div class="oh-exception-label-wrap">' + tabs + inputs + '</div>';
  };

  //running counter for a unique id on each newly added row's "Closed all day" checkbox
  //(fe "oh-exception-closed-js-0", "...-1", ...) - "js-" keeps these from ever colliding
  //with the PHP-rendered ids from InputfieldOpeningHours::renderExceptionRow(), which are
  //built from the field's own name instead (fe "<fieldname>-exception-closed-0")
  var ohExceptionRowCounter = 0;

  //build a single exception "card" - a stacked div per row, mirroring the PHP-rendered
  //markup in InputfieldOpeningHours::renderExceptionRow(), with fields in this fixed
  //order: Label, From/To, Closed all day, Repeat every year, Hours, Remove button. The
  //first five fields are grouped in their own wrapper (.oh-exception-fields) so a clear
  //gap can be put between them and the "Remove Exception" button below.
  var ohBuildExceptionRow = function(languages, maxTimes) {
    var closedId = 'oh-exception-closed-js-' + (ohExceptionRowCounter++);
    return '<div class="oh-exception-row">' +
      '<div class="oh-exception-fields">' +
      '<div class="oh-exception-field oh-exception-field-label">' +
      '<label' + ohLabelAttr + '>' + ohTexts.label + '</label>' + ohBuildLabelInputs(languages) +
      '</div>' +
      '<div class="oh-exception-field oh-exception-field-daterange oh-required">' +
      '<label' + ohLabelAttr + '>' + ohTexts.dates + '</label>' +
      '<div class="oh-exception-daterange-inputs">' +
      '<label class="openinghours-label from">' + ohTexts.from + ':</label>' +
      '<input type="date" class="oh-exception-start-date' + ohInputClass + '"/>' +
      '<label class="openinghours-label to">' + ohTexts.to + ':</label>' +
      '<input type="date" class="oh-exception-end-date' + ohInputClass + '"/>' +
      '</div>' +
      '</div>' +
      '<div class="oh-exception-field oh-exception-field-closed">' +
      '<label><input type="checkbox" id="' + closedId + '" class="oh-exception-closed' + ohCheckboxClass + '"/> ' + ohTexts.closedAllDay + '</label>' +
      '</div>' +
      '<div class="oh-exception-field oh-exception-field-recurring">' +
      '<label><input type="checkbox" class="oh-exception-recurring' + ohCheckboxClass + '"/> ' + ohTexts.repeatEveryYear + '</label>' +
      '</div>' +
      //a freshly added exception always starts unchecked ("closed all day" off), so Hours
      //is required from the start - matches InputfieldOpeningHours::renderExceptionRow()
      '<div class="oh-exception-field oh-exception-field-times oh-exception-times InputfieldStateRequired">' +
      '<label' + ohLabelAttr + '>' + ohTexts.hours + '</label>' + ohBuildTimesInputs(maxTimes) +
      '</div>' +
      '</div>' +
      '<div class="oh-exception-field oh-exception-field-remove">' +
      '<button type="button" class="ui-button ui-priority-secondary oh-exception-remove-btn">' + ohTexts.removeException + '</button>' +
      '</div>' +
      '</div>';
  };

  //rebuild the hidden "<fieldname>-exceptions" JSON field belonging to the given list from
  //its current cards; this is what actually gets saved when the page-edit form is submitted
  var ohSyncExceptions = function($list) {
    var $hidden = $list.closest('.oh-exceptions-wrap').find('.oh-exceptions-hidden');
    if (!$hidden.length) {
      return;
    }
    var exceptions = [];
    $list.find('> .oh-exception-row').each(function() {
      var $row = $(this);
      var startDate = $row.find('.oh-exception-start-date').val();
      if (!startDate) {
        //skip rows without a start date (fe a freshly added, still-empty row)
        return;
      }
      //collect one label value per language input in this row into a {lang: text} map
      var label = {};
      $row.find('.oh-exception-label').each(function() {
        var lang = $(this).attr('data-lang') || 'default';
        label[lang] = $(this).val() || '';
      });
      //collect one {start, finish} pair per time-pair row
      var times = [];
      $row.find('.oh-exception-time-pair').each(function() {
        var $pair = $(this);
        times.push({
          start: $pair.find('.oh-exception-time-start').val() || '',
          finish: $pair.find('.oh-exception-time-finish').val() || ''
        });
      });
      exceptions.push({
        label: label,
        startDate: startDate,
        endDate: $row.find('.oh-exception-end-date').val() || startDate,
        recurring: $row.find('.oh-exception-recurring').is(':checked'),
        closed: $row.find('.oh-exception-closed').is(':checked'),
        times: times
      });
    });
    $hidden.val(JSON.stringify(exceptions));
  };

  //switch the active language tab: show that language's label input, hide the others
  $(document).on('click', '.oh-lang-tab', function() {
    var $tab = $(this);
    var $wrap = $tab.closest('.oh-exception-label-wrap');
    var lang = $tab.attr('data-lang');
    $wrap.find('.oh-lang-tab').removeClass('oh-lang-tab-active');
    $tab.addClass('oh-lang-tab-active');
    $wrap.find('.oh-exception-label').hide();
    $wrap.find('.oh-exception-label[data-lang="' + lang + '"]').show();
  });

  //show/hide the reduced-hours time inputs depending on "closed all day", and toggle the
  //same "InputfieldStateRequired" marker InputfieldOpeningHours::renderExceptionRow() puts
  //on this field initially - Hours is only required while the exception isn't closed all
  //day (matches the server-side check in sanitizeExceptionsInput())
  $(document).on('change', '.oh-exception-closed', function() {
    var $times = $(this).closest('.oh-exception-row').find('.oh-exception-times');
    if ($(this).is(':checked')) {
      $times.hide().removeClass('InputfieldStateRequired');
    } else {
      $times.show().addClass('InputfieldStateRequired');
    }
  });

  //add another time-pair to the "Hours" field, up to the configured max. The button sits
  //inline in the first pair's row; a newly added pair gets its own inline "Remove" button.
  $(document).on('click', '.oh-exception-time-add-btn', function() {
    var $addBtn = $(this);
    var $list = $addBtn.closest('.oh-exceptions-list');
    var cfg = ohGetListConfig($list);
    var $times = $addBtn.closest('.oh-exception-time-list');
    var number = $times.find('> .oh-exception-time-pair').length;
    if (number < cfg.maxTimes) {
      $times.append(ohBuildTimePair(cfg.maxTimes, false, number));
      ohSyncExceptions($list);
    }
    if (number + 1 >= cfg.maxTimes) {
      $addBtn.attr('disabled', 'disabled');
    }
  });

  //remove this specific time-pair from the "Hours" field again (the first pair, with the
  //"Add" button, is never removable, mirroring the regular day-times table above)
  $(document).on('click', '.oh-exception-time-remove-btn', function() {
    var $pair = $(this).closest('.oh-exception-time-pair');
    var $times = $pair.closest('.oh-exception-time-list');
    var $list = $pair.closest('.oh-exceptions-list');
    $pair.remove();
    //re-enable the "Add" button now that there is room for another pair again
    $times.find('.oh-exception-time-add-btn').removeAttr('disabled');
    ohSyncExceptions($list);
  });

  //keep the hidden JSON field in sync with every change made in the list
  $(document).on('input change', '.oh-exceptions-list input', function() {
    ohSyncExceptions($(this).closest('.oh-exceptions-list'));
  });

  //add a new, empty exception card
  $(document).on('click', '.oh-exception-add-btn', function() {
    var $list = $(this).closest('.oh-exceptions-wrap').find('.oh-exceptions-list');
    var cfg = ohGetListConfig($list);
    $list.append(ohBuildExceptionRow(cfg.languages, cfg.maxTimes));
  });

  //remove an exception card
  $(document).on('click', '.oh-exception-remove-btn', function() {
    var $list = $(this).closest('.oh-exceptions-list');
    $(this).closest('.oh-exception-row').remove();
    ohSyncExceptions($list);
  });

});

})(jQuery);
