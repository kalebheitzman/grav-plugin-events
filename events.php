<?php

namespace Grav\Plugin;

// import classes
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/classes/calendar.php';
require_once __DIR__.'/classes/events.php';

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Taxonomy;
use RocketTheme\Toolbox\Event\Event;

use Carbon\Carbon;

use Events\Calendar;
use Events\Events;

class EventsPlugin extends Plugin
{
	/**
	 * Carbon Currente Date Time
	 * @var object
	 */
	protected $now;

	/**
	 * Events Events Class
	 * @var object
	 */
	protected $events;

	/**
	 * Events Calendar Class
	 * @var object
	 */
	protected $calendar;

	/**
	 * Date Range
	 * @var array
	 */
	protected $dateRange;

	/**
	 * Get Subscribed Events
	 * @return array
	 */
	public static function getSubscribedEvents() 
	{
		return [
			'onPluginsInitialized' => ['onPluginsInitialized', 0],
		];
	}

	/**
	 * Initialize configuration
	 */
	public function onPluginsInitialized()
	{

		// Nothing else is needed for admin so close it out
		if ( $this->isAdmin() ) {
			$this->active = false;
			return;
		}

		// Add these to taxonomy for events management
		$event_taxonomies = array('type', 'event_freq', 'event_repeat');
		$taxonomy_config = array_merge((array)$this->config->get('site.taxonomies'), $event_taxonomies);
		$this->config->set('site.taxonomies', $taxonomy_config);

		// get the current datetime with c
		$this->now = Carbon::now();

		// set the calendar accessor 
		$this->calendar = new Calendar();

		// set the events accessor
		$this->events = new Events($this->grav, $this->config);

		$this->enable([
			'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onPagesInitialized' => ['onPagesInitialized', 0],
			'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
		]);
	}

	/**
	 * Add current directory to twig lookup paths.
	 */ 
	public function onTwigTemplatePaths()
	{
		// add templates to twig path
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
	}

	/**
	 * Check for repeating entries and add them to the page collection
	 */
	public function onPagesInitialized()
	{
		$pages = $this->grav['pages'];

		// get instances of all events
		$events = $this->events->instances();

		//unset($this->grav['pages']);
		//$this->grav['pages'] = $events;
		//dump($events);
		//$this->_buildPageList();
	}

	/**
	 * Add Events blueprints to admin
	 * @return [type] [description]
	 */
	public function onBlueprintCreated()
	{
		// todo: add events event blueprint to admin
		// $this->grav['blueprints'];
	}

	/**
	 * Set needed variables to display events
	 */
	public function onTwigSiteVariables()
	{
		// setup 
		$page = $this->grav['page'];
		$collection = $page->collection();
		$twig = $this->grav['twig'];

		// only load the vars if calendar page
		if ($page->template() == 'calendar') {

			$yearParam = $this->grav['uri']->param('year');
			$monthParam = $this->grav['uri']->param('month');

			$twigVars = $this->calendar->twigVars($yearParam, $monthParam);
			$calVars = $this->calendar->calendarVars($collection);

			// add calendar to twig as calendar
			$twigVars['calendar']['events'] = $calVars;
			$twig->twig_vars['calendar'] = array_shift($twigVars);

			// styles
			$css = 'plugin://events/css-compiled/events.css';
			$js = 'plugin://events/js/events.js';
			$assets = $this->grav['assets'];
			$assets->addCss($css);
			$assets->add('jquery');
			$assets->addJs($js);
		}
	}


	/**
	 * This Eventually Needs moved to a class of its own.
	 * Probably a Calendar, Events, and Possibly Page Cloning Class
	 */


	/**
	 * Build A Page List
	 *
	 * This builds a list of pages for Grav. This includes the dynamically
	 * generated pages from repeating events.
	 * @return void
	 */
	private function _buildPageList()
	{
		// get the page
		$page = $this->grav['page'];
		
		/**
		 * We check against page templates to make sure this isn't running
		 * on other pages. This can be memory intensive depending on the 
		 * settings the user sets in plugin.
		 */
		$pageTemplates = array('calendar', 'events', 'event');
		$pageTemplate = $this->grav['page']->template();
		// if build pages should occur
		if ( ! in_array($pageTemplate, $pageTemplates)) {
			return;
		}
		
		/**
		 * The Grav Pages object allows to add and delete pages that Grav
		 * later processes and caches.
		 */
		$pages = $this->grav['pages'];

		/**
		 * We need access to taxonomy to allow us to add the page to
		 * collections. If I ever figure out how to add the page to pages
		 * and taxonomy automatically pick it up, then this will be cleaner.
		 */
		$taxonomy = $this->grav['taxonomy'];

		/**
		 * We create a new page list so that we can process its items at the
		 * end of this function into pages.
		 */
		$pageList = [];
		
		/**
		 * We iterate through the pages to begin processing the new pages list.
		 * The first step is to set up any date range filters. Step two is to
		 * determin whether there are any frequency rules which indicates we
		 * need to clone the page horizontally across a week. If there are
		 * repeat rules we then need to clone the event vertically via into
		 * the date range.
		 */
		foreach($pages->instances() as $key => $page) {
			
			// get the page header
			$header = $page->header();
	
			/**
			 * Update the taxonomy based off of the event header. This is what
			 * allows the plugin to search, order, and put together header 
			 * page collections.
			 */
			if (isset($header->event)) {
				// set the header date automatically based on the event start date
				$header->date = $header->event['start'];
				// set the new header
				$page->header($header);
				/**
				 * Instead of having the user set taxonomy and event
				 * fontmatter, I choose to convert frontmatter to taxonomy
				 * so that we can sort off of it later in the flow.
				 */
				$taxonomy = $this->_eventFrontmatterToTaxonomy($page, $header);
				// update the page with the new taxonomy
				$page->taxonomy($taxonomy);
			}

			/**
			 * Search the event matter found in page headers to see if this 
			 * page is a repeating event or if it has repeat rules.
			 */
			if (isset($header->event) && (isset($header->event['repeat']) || isset($header->event['freq']))) {
				// $pages->addPage($page);
				// $pageList[] = $page;
				// build a list of repeating pages
				$repeatingEvents = $this->_processRepeatingEvent($page);
				/**
				 * After running the repeating events function, we have a 
				 * repeating events list that we can now populate the page
				 * list with. This includes events with freq or repeat set.
				 */
				foreach($repeatingEvents as $key => $eventPage) {
					// add the page to the stack
					$pageList[] = $eventPage;
				}
			}

			/**
			 * Add the original page to the pagelist. I'm not sure if this is
			 * needed or not as it addes pages outside of events into the
			 * pagelist as well. They should already be included in the list.
			 * 
			 */
			// $pageList[] = $page;	
		}

		/**
		 * This is where the magin happens. We've created pages dynamically
		 * based on repeat rules and frequency. We've also contained the posts
		 * to a certain date range. We add the page back to $pages along with
		 * a unique route so the page saves to Grav. We also add the processed
		 * page to the taxonomy stack so that it appears in collections.
		 */
		foreach ($pageList as $eventPage) {
			// add the page to the stack
			$pages->addPage($eventPage, $eventPage->route());
			// add the page to the taxonomy map
			$this->grav['taxonomy']->addTaxonomy($eventPage);
		}
	}

	/**
	 * Convert event frontmatter to taxonomy
	 * 
	 * @param array $taxonomy Taxonomy
	 * @param array $event Event details
	 */ 
	private function _eventFrontmatterToTaxonomy($page, $header)
	{	
		// get the event frontmatter
		$event = $header->event;
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
	 * Process a repeating event
	 *
	 * Handle repeating dates set by the `freq` variable. Also handle any 
	 * special rules set by the `repeat` variable. 
	 * 
	 * @param object $page Page object
	 * @return array Newly created event pages.
	 */
	private function _processRepeatingEvent($page)
	{
		/**
		 * We build a pages array that we can return to buildPageList. The
		 * build page list function will take the events we dynamically 
		 * generate and add them to the stack. We shouldn't need to add any
		 * events generated here to the pages or taxonomy stack
		 */
		$pages = [];

		// get header information alone with default event frontmatter
		$header 	= $page->header();
 		$start 		= $header->event['start'];
 		$end  		= $header->event['end'];
		$repeat 	= isset($header->event['repeat']) ? $header->event['repeat'] : null;
		$freq 		= isset($header->event['freq']) ? $header->event['freq'] : null;
		$until 		= isset($header->event['until']) ? $header->event['until'] : null;

 		// use carbon to calculate datetime info
 		$cStart = Carbon::parse($start);
 		$cEnd = Carbon::parse($end);
 		$cUntil = Carbon::parse($until);
 		$cDay = $cStart->dayOfWeek;
 		$cWeek = $cStart->weekOfMonth;
 		$cWeekYear = $cStart->weekOfYear;

 		/** 
 		 * take the event and apply any special rules to it found in the 
 		 * `repeat` variable. We store the original into an array even if it's 
 		 * by itself so that can iterate through the event if there have been
 		 * special rules applied to it. This gives the plugin the ability to
 		 * say and event repeats monthly on tuesdays and thursdays for example.
 		 */
 		if ( ! is_null($repeat) ) {
 			/** 
 			 * duplicate the event based on the repeat rules (not the freq 
 			 * rules). If the event is supposed to happen every tueday and 
 			 * thursday, then make sure the tuesday event exists and create
 			 * the thursday event.
 			 */
 			$events = $this->_applySpecialRules($page, $repeat, $freq);
 		} else {
 			$events[] = $page;
 		}
 		// run a loop on events now to populate the $pages[] array
 		foreach ($events as $event) {
 			/**
 			 * Each event is unique at this point. This is a place for 
 			 * performance conisderations. Instead of parsing new dates with
 			 * every increment, we should use the already established date
 			 * and just increment it to save on memory usage. To further help 
 			 * with memory consumption, we should consider integrating a date
 			 * range feature so that not every single event is generated. If 
 			 * I'm only displaying a month's worth of dates, I should only do
 			 * the work for those month's worth of events.
 			 */

 			// how many dynamic pages should we create?
 			$count = $this->_calculateIteration($cStart, $cUntil, $freq);

	 		$newPage = clone($event);
	 		$newPage->unsetRouteSlug();
	 		$pages[] = $newPage;

 			// create the pages based on the count received 
	 		for($i=0; $i < $count; $i++) {

 				$newHeader = $event->header();
 				$newStart = Carbon::parse($newHeader->event['start']);
 				$newEnd = Carbon::parse($newHeader->event['end']);

	 			// get the new dates
	 			$newCarbonDate = $this->_processNewDate($i, $newStart, $newEnd, $repeat, $freq);
	 			$newDate['start'] = $newCarbonDate['start'];
	 			$newDate['end'] = $newCarbonDate['end'];

	 			// clone the page
	 			$newPage = $this->_clonePage($event, $newDate);

	 			// add the page to the pages array
	 			$pages[] = $newPage;
	 		}
 		}
		return $pages;
	}

	/**
	 * Get a date range based on params/plugin configuration
	 * @return array Array of CarbonDate Objects to check against
	 */
	private function _dateRange()
	{
		$page = $this->grav['page'];
		$pageTemplate = $page->template();

		$yearParam = $this->grav['uri']->param('year');
		$monthParam = $this->grav['uri']->param('month');

		// check if calendar page
		if ($pageTemplate == 'calendar') {
			$yearParam = $yearParam !== false ? $yearParam : date('Y');
			$monthParam = $monthParam !== false ? $monthParam : date('m');
			$cDateStart = Carbon::create($yearParam, $monthParam, 1, 0, 0, 0);
			$cDateEnd = $cDateStart->copy()->endOfMonth();
		}		

		// check if events page
		if ($pageTemplate == 'events') {
			$cDateStart = Carbon::now()->startOfYear();
			$cDateEnd = Carbon::now()->endOfYear();
		}

		$cDateRange['start'] = $cDateStart;
		$cDateRange['end'] = $cDateEnd;

		return $cDateRange;
	}

	/**
	 * Calculate how many times to iterate event based on freq and until. The
	 * Carbon DateTime api extension is used to calculcate these differences.
	 * 
	 * @param string $freq How often to repeat
	 * @param string $until The date to repeat event until
	 * @return integer How many times to loops
	 */
	private function _calculateIteration($cStartDate, $cUntilDate, $freq)
	{
		$count = 0;

		/**
		 * Flow: We calculate how many times the event should be repeated 
		 * as set by the date range. All this is returning is an iteration
		 * count. We have to determing whether to use the start or until dates
		 * comparted to the date range.
		 */

		// calculate the startDiff
		$cStartDiff = $this->dateRange['start'];
		if ($cStartDate >= $cStartDiff) {
			$cStartDiff = $cStartDate;
		}

		// calculate the untilDiff
		$cUntilDiff = $this->dateRange['end'];
		if ($cUntilDate <= $cUntilDiff) {
			$cUntilDiff = $cUntilDate;
		}

		// calculate the count
		switch($freq) {
			case 'daily':
				$count = $cUntilDiff->diffInDays($cStartDiff);
				break;

			case 'weekly':
				$count = $cUntilDiff->diffInWeeks($cStartDiff);
				break;

			case 'monthly':
				$count = $cUntilDiff->diffInMonths($cStartDiff);
				break;

			case 'yearly':
				$count = $cUntilDiff->diffInYears($cStartDiff);
				break;
		}

		return $count;
	} 

	/**
	 * Process Upcoming Date
	 * @param  object $start  Carbon Start Date
	 * @param  string $repeat Repeat Rules
	 * @param  string $freq   Frequency to repeat
	 * @return array          Carbon DateTime Objects
	 */
	private function _processNewDate($i, $cStart, $cEnd, $repeat, $freq) {

		/**
		 * Flow: we build repeating events off a dateRange to help with
		 * performance. In the processNewDate function, we have to account for
		 * the dateRange
		 */

		// set a default newStart and newEnd
		$newStart = $cStart;
		$newEnd = $cEnd;

		$i++;

		// update the start and end dates of the event frontmatter 			
		switch($freq) {
			case 'daily':
				$newStart = $cStart->addDays($i);
				$newEnd = $cEnd->addDays($i);
				break;

			case 'weekly':
				$newStart = $cStart->addWeeks($i);
				$newEnd = $cEnd->addWeeks($i);
				break;

			// special case for monthly because there aren't the same 
			// number of days each month.
			case 'monthly':
				// start vars
				$sDayOfWeek = $cStart->dayOfWeek;
				$sWeekOfMonth = $cStart->weekOfMonth;
				$sHours = $cStart->hour;
				$sMinutes = $cStart->minute;

				// end vars
				$eDayOfWeek = $cEnd->dayOfWeek;
				$eWeekOfMonth = $cEnd->weekOfMonth;
				$eHours = $cEnd->hour;
				$eMinutes = $cEnd->minute;
				
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
				$newStart = $cStart->addYears($i);
				$newEnd = $cEnd->addYears($i);
				break;
		}
		// save the new datetimes
		$date['start'] = $newStart;
		$date['end'] = $newEnd;
		// return the datetimes
		return $date;
	}

	/**
	 * Clone Events based on Repeat Rules
	 * @param  object $page   Grav Page Object
	 * @param  string $repeat Repeat Rules
	 * @param  string $freq   Frequency of Repeat
	 * @return array          Array of Generated Pages
	 */
	private function _applySpecialRules($page, $repeat, $freq)
	{
		// events array to store pages
		$events = [];
		// rules to clone events on
		$rules = str_split($repeat);
		// header info
		$header = $page->header();
		$eventMatter = $header->event;
		// get the date to check against
		$cDate = Carbon::parse($eventMatter['start']);
		$dow = $cDate->dayOfWeek;
		// rulesToInt
		$rulesToInt[0] = 'U';
		$rulesToInt[1] = 'M';
		$rulesToInt[2] = 'T';
		$rulesToInt[3] = 'W';
		$rulesToInt[4] = 'R';
		$rulesToInt[5] = 'F';
		$rulesToInt[6] = 'S';

		// check to see if event is starting on repeat rule (it should be)
		if ($rules[0] == $rulesToInt[$dow] && count($rules) == 1) {
			$events[] = $page;
			return $events;
		}
		// more than one
		else {
			foreach ($rules as $key => $rule) {
				if ( $key == 0 ) {
					$events[] = $page;
				}
				else {
					$newDate = $this->_newDateFromRule($page, $rule);
					$newPage = $this->_clonePage($page, $newDate);
					$events[] = $newPage;
				}
			}
		}
		return $events;
	}

	/**
	 * Generate new date from rule
	 * @param  object $page Grav Page
	 * @param  string $rule Rule to generate the new date
	 * @return array       Carbon Date Objects
	 */
	private function _newDateFromRule($page, $rule)
	{
		// get the page event date
		$header = $page->header();
		$start = $header->event['start'];
		$end = $header->event['end'];

		// rules
		$rules['M'] = Carbon::MONDAY;
		$rules['T'] = Carbon::TUESDAY;
		$rules['W'] = Carbon::WEDNESDAY;
		$rules['R'] = Carbon::THURSDAY;
		$rules['F'] = Carbon::FRIDAY;
		$rules['S'] = Carbon::SATURDAY;
		$rules['U'] = Carbon::SUNDAY;

		// days
		$cStart = Carbon::parse($start);
		$cEnd = Carbon::parse($end);

		// calculate the next date based on the rule
		$sDOW = $cStart->dayOfWeek;
		$eDOW = $cEnd->dayOfWeek;

		$sDiff = $rules[$rule]-$sDOW;
		$eDiff = $rules[$rule]-$eDOW;

		$date['start'] = $cStart->copy()->addDays($sDiff);
		$date['end'] = $cEnd->copy()->addDays($eDiff);		

		return $date;
	}

	private function _clonePage($page, $newDate)
	{
		$originalHeader = $page->header();

		// create a clone of the page
		$newPage = clone($page);
		$newPage->unsetRouteSlug();

		// get the page header
		$header = $newPage->header();

		// get the new dates
		$newStart = $newDate['start'];
		$newEnd = $newDate['end'];

		// frontmatter strings
		$newStartString = $newStart->format('d-m-Y H:i');
		$newEndString = $newEnd->format('d-m-Y H:i');

		// form new page below
		$newHeader = new \stdClass();
		$newHeader->event['start'] = $newStartString;
		$newHeader->event['end'] = $newEndString;
		$newHeader = (object) array_merge((array) $header, (array) $newHeader);

		if (isset($originalHeader->event['repeat'])) {
			$newHeader->event['repeat'] = $originalHeader->event['repeat'];
		}
		if (isset($originalHeader->event['freq'])) {
			$newHeader->event['freq'] = $originalHeader->event['freq'];
		}
		if (isset($originalHeader->event['until'])) {
			$newHeader->event['until'] = $originalHeader->event['until'];
		}

		// get the page route and build a slug off of it
		$route = $page->route();
		$route_parts = explode('/', $route);

		// set a suffix
		$suffix =  '/e:' . $newStart->format('U');

		// set a new page slug
		$slug = end($route_parts);
		$newSlug = $slug . $suffix;
		$newHeader->slug = $newSlug;
		// $newPage->slug($newSlug);

		// set a new route
		$newRoute = $route . $suffix;
		$newHeader->routes = array('aliases' => $newRoute );
		
		// set the date
		$newHeader->date = $newStartString;

		// set a fake path
		$path = $page->path();
		$newPath = $path . $suffix;
		$newPage->path($newPath);

		// save the eventPageheader
		$newPage->header($newHeader);
		
		return $newPage;
	}
}