(function() {
  (function($) {
    $(document).ready(function() {
      var ChangeUrl;
      ChangeUrl = function(page, url) {
        var obj;
        if (typeof history.pushState !== 'undefined') {
          obj = {
            Page: page,
            Url: url
          };
          history.pushState(obj, obj.Page, obj.Url);
        } else {
          alert('Browser does not support HTML5.');
        }
      };
      $('.calendar').on('click', 'a.calendar-control', function(event) {
        var href;
        href = $(this).attr('href');
        $.get(href, function(data) {
          var $calendar;
          $calendar = $('table.calendar', data);
          $('table.calendar').html($calendar);
        });
        ChangeUrl(document.title, href);
        event.preventDefault();
        return false;
      });
      $('.calendar').on('click', '.calendar-day-link', function(event) {
        var content, title;
        title = $(this).attr('title');
        content = $(this).parent().next('.events-list').html();
        $('.calendar-modal-title').html(title);
        $('.calendar-modal-content').html(content);
        $('.calendar-modal').fadeIn(100);
        event.preventDefault();
        return false;
      });
      $('.calendar-close-modal').on('click', function(event) {
        $('.calendar-modal').fadeOut(100);
        event.preventDefault();
        return false;
      });
      $(document).keyup(function(event) {
        if (event.keyCode === 27) {
          return $('.calendar-modal').fadeOut(100);
        }
      });
      $(document).on('click', function(event) {
        if (!$(event.target).closest('.calendar-modal-inner').is(":visible")) {
          return $('.calendar-modal').fadeOut(100);
        }
      });
    });
  })(jQuery);

}).call(this);
