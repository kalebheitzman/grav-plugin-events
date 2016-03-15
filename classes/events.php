<?php
/**
 * Grav Events Plugin Events Class
 *
 * The Events Class instantiates an instance of all Events available in Grav
 * and provides filters for filtering out uneeded events, setting offsets,
 * limits and etc.
 *
 * PHP version 5.6+
 *
 * @category   Plugins
 * @package    Events
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.8
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      1.0.0 Initial Release
 */

namespace Events;

// import classes
require_once __DIR__.'/../vendor/autoload.php';

// tools to use
use Carbon\Carbon;
use Grav\Common\Grav;

/**
 * Events Plugin Events Class
 *
 * The Events Class instantiates an instance of all Events available in Grav
 * and provides filters for filtering out uneeded events, setting offsets,
 * limits and etc. This creates a listing of events applies repeat rules,
 * for example **MTWRFSU**, frequency rules, for example **daily, weekly,
 * monthly, or yearly,** creates event instances with just date information
 * tied to a 6 digit token, and then it filters down and adds an events that
 * need added to Grav Pages.
 *
 * @package    Events
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.8
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      1.0.0 Initial Release
 */
class Events
{
	/**
	 * @var object Grav Object
	 */
	protected $grav;

	/**
	 * @var object Grav Config
	 */
	protected $config;

	/**
	 * @var array Event Instance
	 */
	protected $event;

	/**
	 * @var array All Events Dates
	 */
	protected $events;

	/**
	 * @var array All Events
	 */
	protected $eventPages;

	/**
	 * @var array All Event Times by ID
	 */
	protected $eventsByToken;

	/**
	 * @var array Repeat Rules
	 */
	protected $repeatRules;

	/**
	 * @var array Carbon Rules
	 */
	protected $carbonRules;

	/**
	 * Events Class Construct
	 *
	 * Through the construct we pull in the main Grav instance and the main
	 * config instance. We also set up the main rules and params to calculate
	 * against in other parts of this class.
	 *
	 * @param object $grav   Grav Instance
	 * @param object $config Grav Configuration
	 * @since  1.0.0 Initial Release
	 *
	 * @return void
	 */
	public function __construct( $grav, $config )
	{
		$this->grav = $grav;
		$this->config = $config;

		// set repeat rules
		$repeatRules[0] = 'U';
		$repeatRules[1] = 'M';
		$repeatRules[2] = 'T';
		$repeatRules[3] = 'W';
		$repeatRules[4] = 'R';
		$repeatRules[5] = 'F';
		$repeatRules[6] = 'S';

		$this->repeatRules = $repeatRules;

		// carbon calc rules
		$carbonRules['M'] = Carbon::MONDAY;
		$carbonRules['T'] = Carbon::TUESDAY;
		$carbonRules['W'] = Carbon::WEDNESDAY;
		$carbonRules['R'] = Carbon::THURSDAY;
		$carbonRules['F'] = Carbon::FRIDAY;
		$carbonRules['S'] = Carbon::SATURDAY;
		$carbonRules['U'] = Carbon::SUNDAY;

		$this->carbonRules = $carbonRules;

		// get the url params
		$this->yearParam = $this->grav['uri']->param('year') !== false ? $this->grav['uri']->param( 'year' ) : false;
		$this->monthParam = $this->grav['uri']->param('month') !== false ? $this->grav['uri']->param( 'month' ) : false;
	}

	/**
	 * Get an Event by Token
	 *
	 * Return the main 6 digit alphanumeric token. The token is protected so
	 * we use a getter to prevent unauthorized manipulation of the token.
	 *
	 * @param  string $evt Event Token
	 * @since  1.0.0 Initial Release
	 *
	 * @return array       Event instance
	 */
	public function getEventByToken( $evt )
	{
		if ( isset($this->eventsByToken[$evt]) )
		{
			return $this->eventsByToken[$evt];
		}
		else
		{
			return [];
		}
	}

	/**
	 * Get an instance of events
	 *
	 * This is the bulkhead of this class. It creates an events listing using
	 * various rules and params to add events to the page list while also
	 * adding all events, including repeating, to an events array with a
	 * searchable token. The events array contains the appropriate date
	 * information for use in templates
	 *
	 * @since  1.0.0 Initial Release
	 *
	 * @return object Grav Pages List
	 */
	public function instances()
	{
		$pages = $this->grav['pages'];

		/**
		 * Initialize the Date Range
		 *
		 * We use a date range to filter events later on. This helps speed page
		 * load times up by only calculating the events we need displayed on page.
		 */
		$dateRange = $this->getDateRange();
		$this->startRangeDate = $dateRange['start'];
		$this->endRangeDate = $dateRange['end'];

		$events = [];
		$eventPages = [];
		foreach ( $pages->instances() as $page ) {
			// get the event instance
			$event = $this->initEvent($page);
			// process the event for repeating dates
			if ( ! is_null($event) ) {
				$eventPages[$page->id()] = $page;
				$events[$page->id()] = $this->processEvents();
			}
		}

		// set some processing vars
		$this->eventPages = $eventPages;
		$this->events = $events;

		// process the event stack
		return $this->processEventPages();
	}

	/**
	 * Initialize the Event
	 *
	 * Initialize the event with date and time information. We store a Carbon
	 * DateTime object or each time needed as well as an epoch string. It may
	 * be possible to reduce some overhead here using just one or the other
	 * but it doesn't seem like it's adding major time to the processing time
	 * for generating dynamic events.
	 *
	 * @param  object $event Grav Object
	 * @since  1.0.0 Initial Release
	 *
	 * @return object        Event Object
	 */
	private function initEvent( $page )
	{
		/**
		 * If the page is not an event related page or there is not an events
		 * related collection on the page, then we don't need to process this.
		 * Processing events when they are not needed just slows the system
		 * down.
		 */
		$pageTemplates = array_map('trim', explode( ',', $this->config->get('plugins.events.event_template_types') ) );
		if ( ! in_array($page->template(), $pageTemplates) ) return;

		/**
		 * Getting the associated date information with the event to store
		 * as protected vars in the instantiated object. This will allow us
		 * to use these for calculations throughout the rest of the class.
		 */
		$header 	= $page->header();
 		$start 		= isset($header->event['start']) ? $header->event['start'] : false;
 		$end  		= isset($header->event['end']) ? $header->event['end'] : false;
		$repeat 	= isset($header->event['repeat']) ? $header->event['repeat'] : false;
		$freq 		= isset($header->event['freq']) ? $header->event['freq'] : false;
		$until 		= isset($header->event['until']) ? $header->event['until'] : false;

		/**
		 * Bug out if start or end is null. We can't do the calculations if
		 * these are missing.
		 */
		if ( ! $start  || ! $end ) return;

		// get the epoch strings to use for later calculations
		$this->event['startDate'] = $this->getCarbonDate( $start );
		$this->event['endDate'] = $this->getCarbonDate( $end );
		$this->event['untilDate'] = $this->getCarbonDate( $until );

		// get the epoch time string
		$this->event['startEpoch'] = strtotime( $start );
		$this->event['endEpoch'] = strtotime( $end );
		$this->event['untilEpoch'] = strtotime( $until );

		// store the repeat rules
		$this->event['repeat'] = $repeat;
		$this->event['freq'] = $freq;

		// we save the event title to generate tokens against
		$this->event['id'] = $page->id();

		return $this->event;
	}

	/**
	 * Process Events
	 *
	 * From here, we want to build a list of dates to repeat the event on.
	 * This involves calculating new start and end dates that fall within the
	 * dateRange that has been set.
	 *
	 * @since  1.0.0 Initial Release
	 *
	 * @return void
	 */
	private function processEvents()
	{
		// get the event
		$event = $this->event;
		// create an events stack
		$eventsStack[] = $event;

		/**
		 * Does the event have a repeat rule? If so, we need to clone the
		 * event horizontally across the week.
		 */
		if ( $event['repeat'] !== false)
		{
			$eventsByRepeat = $this->getEventsByRepeat();
			foreach ($eventsByRepeat as $singleEvent) {
				$eventsStack[] = $singleEvent;
			}
		}

		/**
		 * Does the event have frequency rules? If so, we need to clone the
		 * event vertically down the calendar.
		 */
		if ( $event['freq'] !== false )
		{
			/**
			 * including and repeat based events, generate a list of event
			 * dates that we can add to the stack
			 */
			foreach ($eventsStack as $key => $singleEvent) {
				// get a list of new event dates
				$eventsByFreq = $this->getEventsByFreq( $singleEvent );
				// add the events to the stack as full event instances (dates)
				foreach ($eventsByFreq as $singleEvent) {
					$eventsStack[] = $singleEvent;
				}
			}
		}

		/**
		 * Generate a token to keep urls safer. When we look up a cloned page
		 * we'll look for the token and pull time/date info from it and send
		 * it via twig vars.
		 */
		foreach ( $eventsStack as $key => $singleEvent )
		{
			$time = $singleEvent['startDate']->format('Ymdhi');
			$token = substr( md5( $singleEvent['id'] . $singleEvent['startEpoch'] ),0,6);
			$singleEvent['token'] = $token;
			$eventsStack[$key] = $singleEvent;

			// save the event information to the token
			$this->eventsByToken[$token] = $singleEvent;
		}

		return $eventsStack;
	}

	/**
	 * Process Event Pages
	 *
	 * We have a full list of events and new event datetimes at this point.
	 * This will process and add the events to the event stack. The easiest
	 * way that this works is by adding the events directly to grav pages
	 * and grav taxonomy. This will list everything including the repeat
	 * dates. The downfall of this is that it slows those particular pages
	 * down. So this is the spot to apply filters like date range, offsets,
	 * limits etc. This will make Grav's job easier.
	 *
	 * @since  1.0.0 Initial Release
	 *
	 * @return void
	 */
	private function processEventPages()
	{
		$eventsStack = [];

		// apply removal filters to each events segment
		foreach ( $this->events as $key => $events )
		{
			// run the single event filter on $events
			$filteredEvents = $this->singleEventFilter( $events );
			// run the url params filter on $events
			$filteredEvents = $this->urlParamsFilter( $filteredEvents );
			// run the date range filter on $events
			$filteredEvents = $this->dateRangeFilter( $filteredEvents );

			//$filteredEvents = $events;
			// save the new filteredEvents
			if ( count( $filteredEvents ) > 0 ) {
				$eventsStack[$key] = $filteredEvents;
			}
		}

		/**
		 * We're approaching final here. We have an events listing with
		 * modified dates and each dates stack is tied to the page id that
		 * Grav generates. Pre-adding these pages back into the Grav Pages
		 * list, we're running ~100 ms page load time. Not too shabby. It's
		 * around 60 ms on my machine pre all this date processing. It may be
		 * faster to compare via epoch than Carbon DateTime but I don't know.
		 *
		 * At this point we add the pages back into the Grav Pages list.
		 */
		return $this->addEventsToGrav( $eventsStack );
	}

	/**
	 * Filter the events by a date range
	 *
	 * Return a set of filtered events based on whether the start date falls
	 * in the date range.
	 *
	 * @param  array $events     Events instance
	 * @param  object $startDate Carbon DateTime
	 * @param  object $endDate   Carbon DateTime
	 * @since  1.0.0 Initial Release
	 *
	 * @return array             Array of events
	 */
	private function filterByDate( $events, $startDate, $endDate )
	{
		$filteredEvents = $events;

		/**
		 * Unset the event if the event start date is less than the start date
		 * for the range. Unset the even if the start date is greater than the
		 * end date for the range.
		 */
		foreach ( $filteredEvents as $key => $event )
		{
			if ( $event['startDate']->lt($startDate) ||  $event['startDate']->gt($endDate) )
			{
				// unset the key to filter it out
				unset($filteredEvents[$key]);
			}
		}

		return $filteredEvents;
	}

	/**
	 * Singl Events Filter
	 *
	 * Check to see if this is a single event and if it is, return the single
	 * event. This prevents a single event page from  displaying any other
	 * events by default. It should check for a plugin param that bypasses
	 * this filter if you want an events listing on single events.
	 *
	 * @param  array $events Instace of all events
	 * @since  1.0.0 Initial Release
	 *
	 * @return array         Filtered Events
	 */
	private function singleEventFilter( $events )
	{
		$enabled = $this->config->get('plugins.events.enable_single_event_filter');
		if ( ! $enabled )
		{
			return $events;
		}

		$filteredEvents = $events;
		$evt = $this->grav['uri']->param('evt');

		if ( $evt !== false )
		{
			foreach( $filteredEvents as $key => $event )
			{
				if ( $event['token'] != $evt )
				{
					unset($filteredEvents[$key]);
				}
			}
		}

		return $filteredEvents;
	}

	/**
	 * URL Param Filter
	 *
	 * We check for the year: and month: url params. Additionally we
	 * instantiate these params if we're on a calendar template. If not,
	 * we just pass events through unfiltered.
	 *
	 * @param  array $events Array of events instances
	 * @since  1.0.0 Initial Release
	 *
	 * @return array         Array of events instances
	 */
	private function urlParamsFilter( $events )
	{
		$filteredEvents = $events;

		// filter on the url params if they exist
		if ( $this->grav['page']->template() == 'calendar' && $this->yearParam !== false && $this->monthParam !== false )
		{
			$dateString = $this->yearParam . "-" . $this->monthParam . "-1";
			$startDate = Carbon::parse($dateString);
			$endDate = Carbon::parse($dateString)->endOfMonth();

			$filteredEvents = $this->filterByDate( $events, $startDate, $endDate );
		}

		return $filteredEvents;
	}

	/**
	 * Date Range Filter
	 *
	 * We filter events based on a date range.
	 *
	 * @param  array $events  Array of events instances
	 * @since  1.0.0 Initial Release
	 *
	 * @return array          Array of events instances
	 */
	private function dateRangeFilter( $events )
	{
		$filteredEvents = $events;

		// filter the events by date range
		$filteredEvents = $this->filterByDate( $events, $this->startRangeDate, $this->endRangeDate );

		return $filteredEvents;
	}

	/**
	 * Get Events
	 *
	 * Returns an instance of all events
	 *
	 * @since  1.0.0 Initial Release
	 *
	 * @return array Array of events
	 */
	private function getEvents()
	{
		return $this->events;
	}

	/**
	 * Get Carbon DateTime from a date and time string
	 *
	 * @param  string $dateString Date and Time String
	 * @since  1.0.0 Initial Release
	 *
	 * @return object             Carbon DateTime
	 */
	private function getCarbonDate( $dateString )
	{
		$date = Carbon::parse( $dateString );
		return $date;
	}

	/**
	 * Get Date Range
	 *
	 * Get a date range based on params/plugin configuration
	 *
	 * @since  1.0.0 Initial Release
	 *
	 * @return array Array of CarbonDate Objects to check against
	 */
	private function getDateRange()
	{
		$page = $this->grav['page'];
		$pageTemplate = $page->template();

		$yearParam = $this->grav['uri']->param('year');
		$monthParam = $this->grav['uri']->param('month');

		$cDateStart = Carbon::now();
		$cDateEnd = Carbon::now()->addMonths($this->config->get('plugins.events.display_months_out'));

		// check if calendar page
		if ( $pageTemplate == 'calendar' )
		{

			$yearParam = $yearParam !== false ? $yearParam : date('Y');
			$monthParam = $monthParam !== false ? $monthParam : date('m');

			$cDateStart = Carbon::create($yearParam, $monthParam, 1, 0, 0, 0);
			$cDateEnd = $cDateStart->copy()->endOfMonth();
		}

		// check if events page
		if ( $pageTemplate == 'events' || $pageTemplate == 'event' )
		{
			$cDateStart = Carbon::now();
			$cDateEnd = Carbon::now()->addMonths($this->config->get('plugins.events.display_months_out'));
		}

		if ( $pageTemplate == 'event' )
		{
			$cDateStart = Carbon::now()->subYears(5);
		}

		// build the dateRange
		$cDateRange['start'] = $cDateStart;
		$cDateRange['end'] = $cDateEnd;

		return $cDateRange;
	}

	/**
	 * Get Repeating Events by Repeat Rule
	 *
	 * This will get events based on the `MTWRFSU` rule.
	 *
	 * @since  1.0.0 Initial Release
	 *
	 * @return array Events
	 */
	private function getEventsByRepeat()
	{
		// get the event
		$event = $this->event;

		// store the events
		$events = [];

		// rules to clone events on
		$rules = str_split($event['repeat']);

		// check to see if event is starting on repeat rule (it should be)
		if ($rules[0] == $this->repeatRules[$event['startDate']->dayOfWeek] && count($rules) == 1) {
			$events[] = $event;
		}
		// more than one repeat rule so we create new dates for each new event
		else {
			foreach ($rules as $key => $rule) {
				$newDates = $this->getRepeatDates( $rule );
				$events[] = $this->cloneEventWithNewDates( $newDates );
			}
		}
		return $events;
	}

	/**
	 * Get Events By Frequency
	 *
	 * This takes a single event (and any cloned events from the repeat rules)
	 * and creates new event dates based on the date range, and frequency. It
	 * calculates how many times to repeat the event and what dates to set.
	 *
	 * @param  array $event  Event Dates, Times, and Rules
	 * @since  1.0.0 Initial Release
	 *
	 * @return array         New Events
	 */
	private function getEventsByFreq( $event )
	{
		$events = [];

		/**
		 * We need to get each event and populte new dates based off of the
		 * endDate and startDate. We'll fill an array of events with these
		 * and then filter them later as needed
		 */
		$newDates = $this->getFreqDates( $event );

		/**
		 * We iterate through the new dates and add a new them to events
		 */
		foreach ( $newDates as $key => $newDate )
		{
			// no need to process the first event
			if ( $key == 0 ) { continue; }
			else {
				$events[] = $this->cloneEventWithNewDates( $newDate );
			}
		}

		return $events;
	}

	/**
	 * Get Event Dates by Frequency
	 *
	 * Generate event dates based on the `daily, weekly, monthly, yearly`
	 * frequency rule that has been set.
	 *
	 * @param  array $event Event instance
	 * @since  1.0.0 Initial Release
	 *
	 * @return array        New Event Dates
	 */
	private function getFreqDates( $event )
	{
		$newDatesStack = [];

		// determine how many times we should iterate
		$count = $this->calculateEventIteration( $event );

		// for each iteration, increase the date apportionately
		for( $i=1; $i < $count; $i++ )
		{
			$startDate = $event['startDate']->copy();
			$endDate = $event['endDate']->copy();

			// update the start and end dates of the event frontmatter
			switch($event['freq']) {
				case 'daily':
					$newStart = $startDate->addDays($i);
					$newEnd = $endDate->addDays($i);
					break;

				case 'weekly':
					$newStart = $startDate->addWeeks($i);
					$newEnd = $endDate->addWeeks($i);
					break;

				// special case for monthly because there aren't the same
				// number of days each month.
				case 'monthly':
					// start vars
					$sDayOfWeek = $startDate->dayOfWeek;
					$sWeekOfMonth = $startDate->weekOfMonth;
					$sHours = $startDate->hour;
					$sMinutes = $startDate->minute;

					// end vars
					$eDayOfWeek = $endDate->dayOfWeek;
					$eWeekOfMonth = $endDate->weekOfMonth;
					$eHours = $endDate->hour;
					$eMinutes = $endDate->minute;

					// weeks
					$rd[1] = 'first';
					$rd[2] = 'second';
					$rd[3] = 'third';
					$rd[4] = 'fourth';
					$rd[5] = 'fifth';

					// days
					$ry[0] = 'sunday';
					$ry[1] = 'monday';
					$ry[2] = 'tuesday';
					$ry[3] = 'wednesday';
					$ry[4] = 'thursday';
					$ry[5] = 'friday';
					$ry[6] = 'saturday';

					// get the correct next date
					$sStringDateTime = $rd[$sWeekOfMonth] . ' ' . $ry[$sDayOfWeek] . ' of +' . $i . 'months';
					$eStringDateTime = $rd[$eWeekOfMonth] . ' ' . $ry[$eDayOfWeek] . ' of +' . $i . 'months';

					$newStart = Carbon::parse($sStringDateTime)->addHours($sHours)->addMinutes($sMinutes);
					$newEnd = Carbon::parse($eStringDateTime)->addHours($eHours)->addMinutes($eMinutes);
					break;

				case 'yearly':
					$newStart = $startDate->addYears($i);
					$newEnd = $endDate->addYears($i);
					break;
			}

			$newDates['startDate'] = $newStart;
			$newDates['endDate'] = $newEnd;

			// add the new dates to the stack
			$newDatesStack[] = $newDates;
		}

		return $newDatesStack;
	}

	/**
	 * Calculate Iteration Count
	 *
	 * Determine how many times the event should be repeated based on the
	 * date range and repeat rules. This will base the count on the start date
	 * and how it relates to the daterange. It's possible for the end date to
	 * fall outside of the date range because of this.
	 *
	 * @param  array $event Event instance
	 * @since  1.0.0 Initial Release
	 *
	 * @return integer      Iteration
	 */
	private function calculateEventIteration( $event ) {

		// calculate the count
		switch($event['freq']) {
			case 'daily':
				$count = $event['untilDate']->diffInDays($event['startDate']);
				break;

			case 'weekly':
				$count = $event['untilDate']->diffInWeeks($event['startDate']);
				break;

			case 'monthly':
				$count = $event['untilDate']->diffInMonths($event['startDate']);
				break;

			case 'yearly':
				$count = $event['untilDate']->diffInYears($event['startDate']);
				break;
		}

		return $count;
	}

	/**
	 * Calculate a Repeating Date
	 *
	 * This calculates horizontal dates throughout the week. This is different
	 * than claculating the repeating dates based on frequency. This is for
	 * specifying dates in the MTWRFSU format and calucating dates off of the
	 * original date.
	 *
	 * @param  string $rule Repeat Rule
	 * @since  1.0.0 Initial Release
	 *
	 * @return array        New Start and End Dates
	 */
	private function getRepeatDates( $rule )
	{
		$event = $this->event;

		// get the start and end day of week (DOW)
		$sDOW = $event['startDate']->dayOfWeek;
		$eDOW = $event['endDate']->dayOfWeek;

		// calculate the difference in days
		$sDiff = $this->carbonRules[$rule]-$sDOW;
		$eDiff = $this->carbonRules[$rule]-$eDOW;

		// calculate the new start and end dates
		$newDates['startDate'] = $event['startDate']->copy()->addDays($sDiff);
		$newDates['endDate'] = $event['endDate']->copy()->addDays($eDiff);

		return $newDates;
	}

	/**
	 * Clones an Event
	 *
	 * Clone an existing event with dates based off of `freq` and `repeat`
	 * rules.
	 *
	 * @param  array $newDates New Dates
	 * @since  1.0.0 Initial Release
	 *
	 * @return array           Event with New Dates
	 */
	private function cloneEventWithNewDates( $newDates )
	{
		$event = $this->event;

		// set the new dates and return the new event instance
		$event['startDate'] = $newDates['startDate'];
		$event['endDate'] = $newDates['endDate'];
		$event['startEpoch'] = $newDates['startDate']->format('U');
		$event['endEpoch'] = $newDates['endDate']->format('U');

		return $event;
	}

	/**
	 * Add Events to Grav
	 *
	 * Add the Filtered Events Stack to Grav Pages. This helps generate the
	 * correct collection to be displayed on templates that call an event
	 * collection. It does not create an actual routable page.
	 *
	 * @since  1.0.0 Initial Release
	 * @param array $eventsStack Array of Filtered Events
	 *
	 * @return  object Grav Pages
	 */
	private function addEventsToGrav( $eventsStack )
	{
		/**
		 * The Grav Pages object allows to add and delete pages that Grav
		 * later processes and caches.
		 */
		$pages = $this->grav['pages'];

		/**
		 * We create a new page list so that we can process its items at the
		 * end of this function into pages.
		 */
		$pageList = [];

		// iterate through the events stack
		foreach( $eventsStack as $pageID => $events )
		{
			// get the page associated with each of these events
			$page = $this->eventPages[$pageID];

			// update the page with the new taxonomy for collections
			$taxonomy = $this->eventFrontmatterToTaxonomy( $page );
			$page->taxonomy($taxonomy);

			/**
			 * Workflow: from here, we clone the page and update the necessary
			 * attributes to the each event as a new dynamically created page
			 * back into Grav Pages
			 */
			foreach ( $events as $key => $event )
			{
				$newPage = $this->cloneNewPage( $page, $event );
				$pageList[] = $newPage;
			}
		}

		/**
		 * We need access to taxonomy to allow us to add the page to
		 * collections. If I ever figure out how to add the page to pages
		 * and taxonomy automatically pick it up, then this will be cleaner.
		 */
		$taxonomy = $this->grav['taxonomy'];

		foreach( $pageList as $newPage )
		{
			$pages->addPage($newPage, $newPage->route());
			$taxonomy->addTaxonomy($newPage, $page->taxonomy());
		}
		return $pages;
	}

	/**
	 * Clone a New Page
	 *
	 * Clones a new dynamic page based off of the existing page and related
	 * new event dates.
	 *
	 * @param  object $page  Grav Page Object
	 * @param  array $event  Event instance
	 * @since  1.0.0 Initial Release
	 * @todo   Add the page as an actual routable page to the Grav Pages Stack
	 *
	 * @return object        New Page Object
	 */
	private function cloneNewPage( $page, $event )
	{
		$header = $page->header();

		$newPage = clone($page);
		// $newPage->unsetRouteSlug();

		// form new page below
		$newHeader = new \stdClass();
		$newHeader->event['start'] = $event['startDate']->format('d-m-Y H:i');
		$newHeader->event['end'] = $event['endDate']->format('d-m-Y H:i');

		$newHeader = (object) array_merge((array) $header, (array) $newHeader);

		/**
		 *  Set any other event frontmatter. This is specifically related to the
		 *  onPagesInitialized (all pages) hook. This same code is also used in
		 *  the onPagesInitialized (single page) hook.
		 */
		if (isset($header->event['repeat'])) {
			$newHeader->event['repeat'] = $header->event['repeat'];
			$newHeader->event['repeatDisplay'] = $this->getRepeatDisplay( $header->event['repeat'] );
		}
		if (isset($header->event['freq'])) {
			$newHeader->event['freq'] = $header->event['freq'];
		}
		if (isset($header->event['until'])) {
			$newHeader->event['until'] = $header->event['until'];
		}

		// get the page route and build a slug off of it
		$route = $page->route();
		$route_parts = explode('/', $route);

		// set a new page slug
		$slug = end($route_parts);
		$newSlug = $slug . $event['token'];
		$newHeader->slug = $newSlug;
		$newPage->slug($newSlug);

		// set a new route
		$newRoute = $route . '/evt:' . $event['token'];
		$newPage->route($newRoute);
		$newPage->routeAliases($newRoute);
		//$newPage->rawRoute($newRoute);
		$newPage->routable(true);

		// set the date
		$newHeader->date = $event['startDate']->format('d-m-Y H:i');

		// set a fake path
		$path = $page->path();
		$newPath = $path . '-' . $event['token'];
		$newPage->path($newPath);

		// set the media
		$media = $page->media();
		$newPage->media($media);

		// set an event url for template use
		$url = $page->url() . '/evt:' . $event['token'];
		$newHeader->event_url = $url;

		// save the eventPageheader
		$newPage->header($newHeader);

		return $newPage;
	}

	/**
	 * Convert Event Frontmatter to Taxonomy
	 *
	 * The Events plugin uses 3 custom taxonomies for generating collections.
	 *
	 * `event` is used to specify the page as an event page.
	 *
	 * `event_repeat` is used to specify how the event repeats horizontally
	 * in any given week. It's the row based repeat rule.
	 *
	 * `event_freq` is used to specify how the even repeats vertically through
	 * the month, year, etc. It's the column based repeat rule.
	 *
	 * @param array $taxonomy Taxonomy
	 * @param array $event Event details
	 * @since  1.0.0 Initial Release
	 *
	 * @return  array Grav Taxonomy Items
	 */
	private function eventFrontmatterToTaxonomy( $page )
	{
		// get the event frontmatter
		$event = $page->header()->event;

		// set type taxonomy to event or whatever user has specified in the plugin config
		$taxonomy = $page->taxonomy();
		if (!isset($taxonomy['type']))  {
			$taxonomy['type'] = array($this->config->get('plugins.events.taxonomy_type'));
		}
		// set event days that repeat
		if (!isset($taxonomy['event_repeat']) && isset($event['repeat'])) {
			$taxonomy['event_repeat'] = str_split($event['repeat']);
		}
		// set event frequency
		if (!isset($taxonomy['event_freq']) && isset($event['freq'])) {
			$taxonomy['event_freq'] = array($event['freq']);
		}

		return $taxonomy;
	}

	/**
	 * Get Repeat Display Text
	 *
	 * Generates display text based on the repeat rules that are set.
	 * @param  string $repeat MTWRFSU
	 * @return string         Human Readable Repeat Rules
	 */
	public function getRepeatDisplay( $repeat ) {

		$rules = str_split( $repeat );

		// repeat display rules
		$repeatDisplay = [];
		$repeatDisplay['M'] = 'Monday';
		$repeatDisplay['T'] = 'Tuesday';
		$repeatDisplay['W'] = 'Wednesday';
		$repeatDisplay['R'] = 'Thursday';
		$repeatDisplay['F'] = 'Friday';
		$repeatDisplay['S'] = 'Saturday';
		$repeatDisplay['U'] = 'Sunday';

		// output for a single repeat rule
		if (count($rules) == 1) {
			return $repeatDisplay[$repeat];
		}

		// build the display
		$display = [];
		foreach ( $rules as $rule ) {
			array_push($display, $repeatDisplay[$rule]);
		}
		// get the end off the array
		$end = array_pop($display);
		// determine the joiner
		if ( count($display) == 1 ) {
			$joiner = ' and ';
		} else {
			$joiner = ', and ';
		}
		// build the rest of the string
		$start = implode(', ', $display);
		// build the output
		$output = $start . $joiner . $end;

		return $output;
	}

}
