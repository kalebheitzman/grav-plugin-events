<?php
/**                          
 *     __                         __              
 *    / /_  _________ _____  ____/ /_____________ 
 *   / __ \/ ___/ __ `/ __ \/ __  / ___/ ___/ __ \
 *  / /_/ / /  / /_/ / / / / /_/ / /  / /__/ /_/ /
 * /_.___/_/   \__,_/_/ /_/\__,_/_/   \___/\____/ 
 *                                                              
 * Designed + Developed 
 * by Kaleb Heitzman
 * https://brandr.co
 * 
 * (c) 2016
 */
namespace Events;
require_once __DIR__.'/../vendor/autoload.php';
use Carbon\Carbon;

/**
 * Events Plugin Calendar Class
 *
 * The Events Calendar Class provides variables for Twig to create a dynamic
 * calendar with previous and next links that relate to month and year. This
 * class is also used to display a traditional calendar and form the rows
 * and columns that make up the calendar. It does not calculate dates or
 * manipulate any information. It's simply for displaying a nice **calendar
 * page** on your Grav website. It is referenced under the
 * `onTwigSiteVariables` hook in the root events plugin file.
 *
 * @package    Events
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.15
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      1.0.0 Initial Release
 */
class CalendarProcessor
{
	/**
	 * Twig Calendar Vars
	 *
	 * Adds a url to the event header and stores each event in an associative
	 * array that can be accessed from `calendar.html.twig` via **year,
	 * month, and day** params. Here is an example of accessing a particular
	 * day on the calendar.
	 *
	 * ```twig
	 * {% for events in calendar.events[calendar.year][calendar.month][day] %}
	 *  	{% for event in events %}
   *          {% if event.title %}
   *              <div class="event"><a href="{{ event.url }}">{{ event.title }}</a></div>
   *          {% endif %}
   *      {% endfor %}
	 * {% endfor %}
	 * ```
	 *
	 * @since  1.0.0 Initial Release
	 * @param  object $collection Grav Collection
	 * @return array              Calendar variables for Twig
	 */
	public function calendarVars( \Grav\Common\Page\Collection $collection )
	{
		// build a calendar array to use in twig
		$calendar = array();

		$collection->order('date', 'asc');

		foreach($collection as $event) {

			$header = $event->header();
			$start = $header->event['start'];

			// build dates to create an associate array
			$carbonStart = Carbon::parse($start);
			$year = $carbonStart->year;
 			$month = $carbonStart->month;
 			$day = $carbonStart->day;

 			// add the event to the calendar
 			$calendar[$year][$month][$day][] = $event; //$eventItem;
		}

		return $calendar;
	}

	/**
	 * Twig Display Vars
	 *
	 * Returns vars used to navigate and display content in the calendar twig
	 * template. **Past, present, and future** vars are provided to twig for
	 * creating custom navigation ui's. Below is a listing of some of the
	 * variables that are available.
	 *
	 * ```twig
	 * {% calendar.prevYear %}
	 * {% calendar.nextYear %}
	 * {% calendar.daysInMonth %}
	 * {% calendar.currentDay %}
	 * {% calendar.date %}
	 * {% calendar.year %}
	 * {% calendar.month %}
	 * {% calendar.day %}
	 * {% calendar.next %}
	 * {% calendar.prev %}
	 * ```
	 *
	 * @param  object $yearParam  	Grav URI `year:` param
	 * @param  object $monthParam  	Grav URI `month:` param
	 * @since  1.0.0. Initial Release
	 * @return array              	Twig Array
	 */
	public function twigVars($yearParam, $monthParam)
	{
		if ( $yearParam === false ) {
			$yearParam = date('Y');
		}

		if ( $monthParam === false ) {
			$monthParam = date('m');
		}

		$monthYearString = "${yearParam}-${monthParam}-01";
		$carbonMonthYear = Carbon::parse($monthYearString);

		// add vars for use in the calendar twig var
		$twigVars['calendar']['daysInMonth'] = $carbonMonthYear->daysInMonth;
		$twigVars['calendar']['currentDay'] = date('d');

		// current dates
		$twigVars['calendar']['date'] = $carbonMonthYear->timestamp;
		$twigVars['calendar']['year'] = $carbonMonthYear->year;
		$twigVars['calendar']['month'] = $carbonMonthYear->month;
		$twigVars['calendar']['day'] = $carbonMonthYear->day;

		// next dates
		$nextMonth = $carbonMonthYear->copy()->addMonth();
		$twigVars['calendar']['next']['date'] = $nextMonth->timestamp;

		// prev dates
		$prevMonth = $carbonMonthYear->copy()->subMonth();
		$twigVars['calendar']['prev']['date'] = $prevMonth->timestamp;

		// years
		$nextYear = $carbonMonthYear->copy()->addYear();
		$prevYear = $carbonMonthYear->copy()->subYear();
		$twigVars['calendar']['prevYear'] = $prevYear;
		$twigVars['calendar']['nextYear'] = $nextYear;

		return $twigVars;
	}
}
