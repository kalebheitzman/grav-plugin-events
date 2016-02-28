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
 * @package    Grav Events Plugin
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.4
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      File available since Release 1.0.0
 */

namespace Events;

// import classes
require_once __DIR__.'/../vendor/autoload.php';

// tools to use
use Carbon\Carbon;
use Grav\Common\Grav;

class Events
{
	/**
	 * Grav Object
	 */
	protected $grav;

	/**
	 * Grav Config
	 */
	protected $config;

	/**
	 * Event Instance
	 */
	protected $event;

	/**
	 * All Events Dates
	 */
	protected $events;

	/**
	 * All Events 
	 */
	protected $eventPages;

	/**
	 * All Event Times by ID
	 */
	protected $eventsByToken;

	/**
	 * Repeat Rules
	 */
	protected $repeatRules;

	/**
	 * Carbon Rules
	 */
	protected $carbonRules;

	/**
	 * Events Class Construct
	 * @param object $grav   Grav Object
	 * @param object $config Grav Configuration
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
	 * Return Events By Token Array
	 * @return array Events By Token
	 */
	public function eventsByToken()
	{
		return $this->eventsByToken;
	}

	/**
	 * Get an Event by Token
	 * @param  string $evt Event Token
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
	 * Instantiate Events
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
	 * @param  object $event Grav Object
	 * @return object        Event Object
	 */
	public function initEvent( $page )
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
	 * @return void
	 */
	public function processEvents()
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
	 * @param  array $events     Events instance
	 * @param  object $startDate Carbon DateTime
	 * @param  object $endDate   Carbon DateTime
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
	 * @param  array $events Instace of all events
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
	 * @param  array $events Array of events instances
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
	 * We filter events based on 
	 * @param  array $events  Array of events instances
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
	 * @return array Array of events
	 */
	private function getEvents()
	{
		return $this->events;
	}

	/**
	 * Get Carbon DateTime from a date and time string
	 * @param  string $dateString Date and Time String
	 * @return object             Carbon DateTime
	 */
	private function getCarbonDate( $dateString )
	{
		$date = Carbon::parse( $dateString );
		return $date;
	}

	/**
	 * Get a date range based on params/plugin configuration
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
	 * @param  array $event Event instance
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
	 * @param  array $event Event instance
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
	 * @param  string $rule Repeat Rule
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
	 * Clones an Event with new dates
	 * @param  array $newDates New Dates
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
	 * Add the Filtered Events Stack to Grav Pages
	 * @param array $eventsStack Array of Filtered Events
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
			//$this->$eventsByTimeID[] = 
			//dump($newPage->id());
			$pages->addPage($newPage, $newPage->route());
			$taxonomy->addTaxonomy($newPage, $page->taxonomy());
		}

		//dump($pages->routes());

		return $pages;
	}

	/**
	 * Clone a New Page
	 * @param  object $page  Grav Page Object
	 * @param  array $event  Event instance
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

		// set any other event frontmatter
		if (isset($header->event['repeat'])) {
			$newHeader->event['repeat'] = $header->event['repeat'];
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

		// save the eventPageheader
		$newPage->header($newHeader);

		return $newPage;
	}

	/**
	 * Convert event frontmatter to taxonomy
	 * 
	 * @param array $taxonomy Taxonomy
	 * @param array $event Event details
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
	
}