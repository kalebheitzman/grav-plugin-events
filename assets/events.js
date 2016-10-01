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
    });
  })(jQuery);

}).call(this);
