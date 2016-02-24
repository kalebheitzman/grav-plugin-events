<?php

namespace Events;

require_once __DIR__.'/../vendor/autoload.php';

use Carbon\Carbon;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Taxonomy;
use RocketTheme\Toolbox\Event\Event;


class Events
{
	/**
	 * Clone a Grav Page
	 * @param  object $page    Grav Page Object
	 * @param  object $newDate Carbon Date
	 * @return object          Grav Cloned Page
	 */
	public function clonePage($page, $newDate)
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
		$suffix =  '/' . $newStart->format('U');

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

	/**
	 * Calculate how many times to iterate event based on freq and until. The
	 * Carbon DateTime api extension is used to calculcate these differences.
	 * 
	 * @param string $freq How often to repeat
	 * @param string $until The date to repeat event until
	 * @return integer How many times to loops
	 */
	public function calculateIteration($start, $freq, $until)
	{
		$count = 0;
		
		$untilDate = Carbon::parse($until);
		$startDate = Carbon::parse($start);

		switch($freq) {
			case 'daily':
				$count = $untilDate->diffInDays($startDate);
				break;

			case 'weekly':
				$count = $untilDate->diffInWeeks($startDate);
				break;

			case 'monthly':
				$count = $untilDate->diffInMonths($startDate);
				break;

			case 'yearly':
				$count = $untilDate->diffInYears($startDate);
				break;
		}

		return $count;
	} 

	/**
	 * Generate new date from rule
	 * @param  object $page Grav Page
	 * @param  string $rule Rule to generate the new date
	 * @return array       Carbon Date Objects
	 */
	public function newDateFromRule($page, $rule)
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
		$carbonStart = Carbon::parse($start);
		$carbonEnd = Carbon::parse($end);

		// calculate the next date based on the rule
		$sDOW = $carbonStart->dayOfWeek;
		$eDOW = $carbonEnd->dayOfWeek;

		$sDiff = $rules[$rule]-$sDOW;
		$eDiff = $rules[$rule]-$eDOW;

		$date['start'] = $carbonStart->copy()->addDays($sDiff);
		$date['end'] = $carbonEnd->copy()->addDays($eDiff);		

		return $date;
	}

	/**
	 * Apply Special Date Rules
	 * @param  object $page   Grav Page Object
	 * @param  string $repeat Repeat Rules
	 * @param  string $freq   Repeat Frequency
	 * @return page      	  Grav Page Object    
	 */
	public function applySpecialRules($page, $repeat, $freq)
	{
		$events[] = $page;
		$rules = str_split($repeat);

		// if only repeat rule, safe to assume same day so return events.
		if (count($rules) == 1) {
			return $events;			
		}
		// more than one
		else {
			foreach ($rules as $key => $rule) {
				if ( $key == 0 ) {
					$events[] = $page;
				}
				else {
					$newDate = $this->newDateFromRule($page, $rule);
					$newPage = $this->clonePage($page, $newDate);
					$events[] = $newPage;
				}
			}
		}
		return $events;
	}

	/**
	 * Process Upcoming Date
	 * @param  object $start  Carbon Start Date
	 * @param  string $repeat Repeat Rules
	 * @param  string $freq   Frequency to repeat
	 * @return array          Carbon DateTime Objects
	 */
	public function processNewDate($i, $carbonStart, $carbonEnd, $repeat, $freq) {

		// update the start and end dates of the event frontmatter 			
		switch($freq) {
			case 'daily':
				$newStart = $carbonStart->addDays(1);
				$newEnd = $carbonEnd->addDays(1);
				break;

			case 'weekly':
				$newStart = $carbonStart->addWeeks(1);
				$newEnd = $carbonEnd->addWeeks(1);
				break;

			// special case for monthly because there aren't the same 
			// number of days each month.
			case 'monthly':
				// start vars
				$sDayOfWeek = $carbonStart->dayOfWeek;
				$sWeekOfMonth = $carbonStart->weekOfMonth;
				$sHours = $carbonStart->hour;
				$sMinutes = $carbonStart->minute;

				// end vars
				$eDayOfWeek = $carbonEnd->dayOfWeek;
				$eWeekOfMonth = $carbonEnd->weekOfMonth;
				$eHours = $carbonEnd->hour;
				$eMinutes = $carbonEnd->minute;
				
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
				if ($i == 0) {
					$sStringDateTime = $rd[$sWeekOfMonth] . ' ' . $ry[$sDayOfWeek] . ' of next month';
					$eStringDateTime = $rd[$eWeekOfMonth] . ' ' . $ry[$eDayOfWeek] . ' of next month';
				}
				else {
					$sStringDateTime = $rd[$sWeekOfMonth] . ' ' . $ry[$sDayOfWeek] . ' of +' . $i . 'months';
					$eStringDateTime = $rd[$eWeekOfMonth] . ' ' . $ry[$eDayOfWeek] . ' of +' . $i . 'months';	
				}

				$newStart = Carbon::parse($sStringDateTime)->addHours($sHours)->addMinutes($sMinutes);				
				$newEnd = Carbon::parse($eStringDateTime)->addHours($eHours)->addMinutes($eMinutes);	
				break;

			case 'yearly':
				$newStart = $carbonStart->addYears(1);
				$newEnd = $carbonEnd->addYears(1);
				break;
		}
		// save the new datetimes
		$date['start'] = $newStart;
		$date['end'] = $newEnd;
		// return the datetimes
		return $date;
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
	public function processRepeatingEvent($page)
	{
		$pages = [];

		// get header information alone with event frontmatter
		$header 	= $page->header();
 		$start 		= $header->event['start'];
 		$end  		= $header->event['end'];
		$repeat 	= isset($header->event['repeat']) ? $header->event['repeat'] : null; // calculate the repeat if not set?
		$freq 		= $header->event['freq'];
		$until 		= $header->event['until'];

 		// use carbon to calculate datetime info
 		$carbonStart = Carbon::parse($start);
 		$carbonEnd = Carbon::parse($end);
 		$carbonDay = $carbonStart->dayOfWeek;
 		$carbonWeek = $carbonStart->weekOfMonth;
 		$carbonWeekYear = $carbonStart->weekOfYear;

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
 			$events = $this->applySpecialRules($page, $repeat, $freq);
 		} else {
 			$events[] = $page;
 		}

 		foreach($events as $event) {

 		}


 		// run a loop on events now to populate the $pages[] array
 		foreach ($events as $event) {


 			// how many dynamic pages should we create?
 			$count = $this->calculateIteration($start, $freq, $until);
 			// create the pages based on the count received 
	 		for($i=1; $i <= $count; $i++) {

	 			// create a clone of the page
	 			$newPage = clone($event);
	 			$newPage->unsetRouteSlug();

	 			// get the new dates
	 			$newCarbonDate = $this->processNewDate($i, $carbonStart, $carbonEnd, $repeat, $freq);
	 			$newStart = $newCarbonDate['start'];
	 			$newEnd = $newCarbonDate['end'];

	 			// frontmatter strings
				$newStartString = $newStart->format('d-m-Y H:i');
				$newEndString = $newEnd->format('d-m-Y H:i');

				// form new page below
				$newHeader = new \stdClass();
				$newHeader->event['start'] = $newStartString;
				$newHeader->event['end'] = $newEndString;
				$newHeader = (object) array_merge((array) $header, (array) $newHeader);

				// get the page route and build a slug off of it
				$route = $page->route();
				$route_parts = explode('/', $route);

				// set a suffix
				$suffix =  '/' . $newStart->format('U');

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
	 			$pages[] = $newPage;

	 		}
 		} 		
		return $pages;
	}

	/**
	 * Convert event frontmatter to taxonomy
	 * 
	 * @param array $taxonomy Taxonomy
	 * @param array $event Event details
	 */ 
	public function eventFrontmatterToTaxonomy($page, $header)
	{	
		// event frontmatter
		$event = $header->event;
		// set type taxonomy to event
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
	 * Build A Page List
	 * @return void
	 */
	public function buildPageList()
	{
		// get a new instance of grav pages
		// I instantiate a new pages object to deal with cloning and pointer issues
		$gravPages = new \Grav\Common\Page\Pages($this->grav);
		$gravPages->init();

		// get taxonomy so we can add generated pages
		$taxonomy = $this->grav['taxonomy'];

		// create a page list to save pages
		$pageList = [];
		
		// iterate through page instances to find event frontmatter
		foreach($gravPages->instances() as $key => $page) {
			$header = $page->header();
			// update taxonomy based off of event frontmatter
			if (isset($header->event)) {
				// set the header date
				$header->date = $header->event['start'];
				$page->header($header);
				// set the new event taxonomy
				$taxonomy = $this->_eventFrontmatterToTaxonomy($page, $header);
				$page->taxonomy($taxonomy);
				// add page to taxonomy
			}
			// process for repeating events if event front matter is set
			if (isset($header->event) && (isset($header->event['repeat']) || isset($header->event['freq']))) {
				$gravPages->addPage($page);
				$pageList[] = $page;
				// build a list of repeating pages
				$repeatingEvents = $this->events->processRepeatingEvent($page);
				// add the new $repeatingEvents pages to the $pages object
				foreach($repeatingEvents as $key => $eventPage) {
					// add the page to the stack
					$pageList[] = $eventPage;
				}
			}
			$pageList[] = $page;			
		}

		// insert the page list
		foreach ($pageList as $key => $eventPage) {
			// add the page to the stack
			$gravPages->addPage($eventPage, $eventPage->route());
			// add the page to the taxonomy map
			$this->grav['taxonomy']->addTaxonomy($eventPage);
		}

		// store the pages back into grav
		unset($this->grav['pages']);
		$this->grav['pages'] = $gravPages;
	}

}