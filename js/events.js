(function($) {

	$(document).ready(function() {

		$('.calendar').on('click', 'a.calendar-control', function(event) {
	
			var href = $(this).attr('href');

			$.get(href, function(data) {
				$calendar = $('table.calendar', data);
				$('table.calendar').html($calendar);
			});

			ChangeUrl(document.title, href);

			event.preventDefault();
			return false;
		});

		function ChangeUrl(page, url) {
	        if (typeof (history.pushState) != "undefined") {
	            var obj = { Page: page, Url: url };
	            history.pushState(obj, obj.Page, obj.Url);
	        } else {
	            alert("Browser does not support HTML5.");
	        }
	    }

	});


})(jQuery);