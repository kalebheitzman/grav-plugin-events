#     __                         __              
#    / /_  _________ _____  ____/ /_____________ 
#   / __ \/ ___/ __ `/ __ \/ __  / ___/ ___/ __ \
#  / /_/ / /  / /_/ / / / / /_/ / /  / /__/ /_/ /
# /_.___/_/   \__,_/_/ /_/\__,_/_/   \___/\____/ 
#                                                              
# Designed + Developed 
# by Kaleb Heitzman
# https://brandr.co
# 
# (c) 2016
(($) ->
  $(document).ready ->

    # Function to change the page url
    ChangeUrl = (page, url) ->
      if typeof history.pushState != 'undefined'
        obj =
          Page: page
          Url: url
        history.pushState obj, obj.Page, obj.Url
      else
        alert 'Browser does not support HTML5.'
      return

    # Calendar Controls
    $('.calendar-table').on 'click', 'a.calendar-control', (event) ->
      event.preventDefault()

      tbl_height = $('table.calendar').height();
      $('.calendar-table').css('height', tbl_height + 'px');

      href = $(this).attr('href')
      $.get href, (data) ->
        $calendar = $('table.calendar', data)
        $('.calendar-table').html $calendar
        return
      
      ChangeUrl document.title, href      
      false

    # Calendar Details
    $('.calendar-table').on 'click', '.calendar-day-link', (event) ->
      event.preventDefault()

      $('.calendar-day-link').removeClass('active');
      $(this).addClass('active');
      title = $(this).attr('title');
      content = $(this).parent().next('.calendar-day-details').html();
      
      $details = $('.calendar-details .calendar-day-details');
      $details.hide();
      $details.html(content);
      $details.fadeIn(300);
      false

    # Calendar Details
    $('.calendar-day-link').on 'click', (event) ->
      event.preventDefault()

      $('.calendar-day-link').removeClass('active');
      $(this).addClass('active');
      title = $(this).attr('title');
      content = $(this).parent().next('.calendar-day-details').html();
      
      $details = $('.calendar-details .calendar-day-details');
      $details.hide();
      $details.html(content);
      $details.fadeIn(300);
      false

    return
  return
) jQuery