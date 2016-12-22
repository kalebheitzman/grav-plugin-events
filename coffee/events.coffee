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
      if typeof history.pushStasdfasdate != 'undefined'
        obj =
          Page: page
          Url: url
        history.pushState obj, obj.Page, obj.Url
      else
        alert 'Browser does not support HTML5.'
      return

    # Calendar Controls
    $('.calendar-table').on 'click', 'a.calendar-control', (event) ->
      href = $(this).attr('href')
      $.get href, (data) ->
        $calendar = $('table.calendar', data)
        $('.calendar-table').html $calendar
        return
      ChangeUrl document.title, href
      event.preventDefault()
      false

    # Calendar Modal
    $('.calendar-table').on 'click', '.calendar-day-link', (event) ->
      $('.calendar-day-link').removeClass('active');
      $(this).addClass('active');
      title = $(this).attr('title');
      content = $(this).parent().next('.calendar-day-details').html();
      
      $details = $('.calendar-details .calendar-day-details');
      $details.hide();
      $details.html(content);
      $details.fadeIn(300);
      
      event.preventDefault()
      false

    return
  return
) jQuery