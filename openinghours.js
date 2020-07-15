/*
JavaScript for opening hours inputfield
by Jürgen Kern
*/

$(document).ready(function() {

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
    if (($(isChecked).length)) {
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
    //get number of existing items in the times table
    var number = $('#' + field + '-hours-' + dayName + ' tr').length; //fe openinghours-hours-mo
    console.log(number);
    //restrict number of times to 5
    var maxTimes = $(this).attr('data-max');
    //check if number of max items per day is reached
    if (number < maxTimes) {
      //get Labels
      var fromLabel = document.getElementsByClassName('from')[0].innerHTML;
      var toLabel = document.getElementsByClassName('to')[0].innerHTML;
      //add a new tableRow
      var $newElement = '<tr id="' + field + '-' + dayName + '-' + number + '" style="display:none">' +
        '<td><span class="number">' + (parseInt(number) + 1) + '</span></td>' +
        '<td><label class="openinghours-label from">' + fromLabel + '</label><input type="time" name="' + field + '-' + dayName + '-' + number + '-start" value=""/></td>' +
        '<td><label class="openinghours-label to">' + toLabel + '</label><input type="time" name="' + field + '-' + dayName + '-' + number + '-finish" value=""/></td>' +
        '<td><button id="' + field + '-remove-' + dayName + '-' + number + '" class="remove-btn ui-button ui-priority-secondary" type="button">Remove</button></td>' +
        '</tr>';
      $('#' + field + '-hours-' + dayName + ' > tbody').append($newElement);
      $('#' + field + '-' + dayName + '-' + number).fadeIn(300);
    } else {
      //disable add button and add hover class
      $(this).attr('disabled', 'disabled');
    }
  });

});
