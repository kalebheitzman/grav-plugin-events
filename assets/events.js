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
      $('.calendar-table').on('click', 'a.calendar-control', function(event) {
        var href;
        href = $(this).attr('href');
        $.get(href, function(data) {
          var $calendar;
          $calendar = $('table.calendar', data);
          $('.calendar-table').html($calendar);
        });
        ChangeUrl(document.title, href);
        event.preventDefault();
        return false;
      });
      $('.calendar-table').on('click', '.calendar-day-link', function(event) {
        var $details, content, title;
        $('.calendar-day-link').removeClass('active');
        $(this).addClass('active');
        title = $(this).attr('title');
        content = $(this).parent().next('.calendar-day-details').html();
        $details = $('.calendar-details .calendar-day-details');
        $details.hide();
        $details.html(content);
        $details.fadeIn(300);
        event.preventDefault();
        return false;
      });
    });
  })(jQuery);

}).call(this);

//# sourceMappingURL=events.js.map
