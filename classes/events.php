<?php

namespace Grav\Plugin;

require __DIR__.'/../vendor/autoload.php';

use Grav\Common\Grav;
use Grav\Common\GravTrait;
use \Carbon\Carbon;

class Events
{
	use GravTrait;

	/**
	 * @var array Events collection
	 */ 
	protected $events;

	/**
	 * @var string Used to establish a time to sort by
	 */ 
	protected $reference_time = "2000-01-01 00:00:00";

	/**
	 * @var string Used in sorting times
	 */ 
	protected $absolute;

	/**
	 * Class construct
	 */
	public function __construct()
	{
		$this->absolute = strtotime($this->reference_time);
	}

	/**
	 * Find events based on text search
	 * 
	 * @param array $search Search options 
	 * @param string $order Order events by 'date'
	 * @param string $operator Operator
	 * @return array Collection of events
	 */ 
	public function findEvents2($search = null, $order = 'date', $operator = 'and')
	{
		// build the event listing
	 	if (!$this->events) {
            $this->build();
        }
	}

	/**
	 * Find events based on options
	 * 
	 * @param array $patterns patterns to search
	 * @param string $operator Operator
	 * @return Collection
	 */
	public function findEvents($patterns = null, $order = 'date', $operator = 'and')
	{
		// build the event listing
	 	if (!$this->events) {
            $this->build();
        }

        // get the event listing
        $events = $this->events;

        // get all of the matches based on patterns
        $matches = $this->matchFinder($patterns, $events, $operator);

	 	// order events by date
		if ($order == 'date') {
			usort($matches, array($this, "_SortByDate"));
		}

		// order events by time
		if ($order == 'time') {
			usort($matches, array($this, "_SortByTime"));
		} 

		return $matches;
	} 

	/**
	 * Return item if match found  
	 */
	private function matchFinder($patterns, $events, $operator)
	{
		if ($patterns == null) {
			return $events;
		}

		$count = count($patterns);

		$matches = [];
		
		foreach ($events as $index => $event) {
			
			// store true boolean in matched if event matches pattern
			$matched = [];
			foreach ($patterns as $type => $pattern) {

				// convert pattern to an array if it is not already
				if (! is_array($pattern)) {
					$pb = $pattern;
					$pattern = [];
					$pattern[] = $pb; 
				}

				// calculate the intersection
				$intersect = array_intersect($pattern, (array)$event);

				// Non-negating match
				if (strpos($type, '!') !== 0) {
					// match single pattern using 'and'
					if ($count === 1) {
						foreach ($patterns as $key => $pattern) {
							if (! is_array($pattern)) {
								$pattern_backup = $pattern;
								$pattern = [];
								$pattern[] = $pattern_backup; 
							}
							if ($count == count(array_intersect($pattern, (array)$event))) {
								array_push($matched, true);
							}
						}
					}
					// matches multiple patterns using 'and'
					if ($count > 1) {
						if ($count == count(array_intersect_assoc($patterns, (array)$event))) {
							array_push($matched, true);
						}
					}
				}
				// negate items from the list of events
				else {
					if (count($intersect) == 0) {
						array_push($matched, true);
					}
				}
			}
			// matches were found
			if (count($matched) !== 0) {
				$matches[] = $event;
			}
		}

    	return $matches;
	}

	/**
	 * Build events list
	 * 
	 * @internal
	 */ 
	protected function build()
	{
	 	require_once __DIR__ . '/evententry.php';

	 	$pages = self::$grav['pages'];
	 	$routes = $pages->routes();
		ksort($routes);

		foreach($routes as $route => $path) {
			$page = $pages->get($path);

			if ($page->routable()) {

				$header = $page->header();

				/*
				 *	If the page has event frontmatter then store it
				 */
				if (isset($header->event)) {
					$entry = new EventEntry;
					$entry->title = $header->title;
					$entry->route = $route;
					
					foreach ($header->event as $key => $value) {
						$entry->$key = $header->event[$key];
					}

					// process the start date
					if (isset($entry->start)) {
						$datetime = explode(" ", $entry->start);
						$entry->start_date = $datetime[0];						
						$entry->start_time = $datetime[1];	
						$entry->start_time_abs = strtotime($datetime[1], $this->absolute);					
						$entry->start_carbon = Carbon::parse($entry->start);
					}

					// process the end date
					if (isset($entry->end)) {
						$datetime = explode(" ", $entry->end);
						$entry->end_date = $datetime[0];						
						$entry->end_time = $datetime[1];	
						$entry->end_time_abs = strtotime($datetime[1], $this->absolute);					
						$entry->end_carbon = Carbon::parse($entry->end);
					}

					// store this event in $this->events
					$this->events[] = $entry;

					// process $entry for recurring events
					$this->buildRepeatingEntries($entry);
				}

			}

		}
	}

	/**
	 * Build repeating entries 
	 * 
	 * @internal
	 * @param object $event Event
	 */
	private function buildRepeatingEntries($event)
	{

		// check for event repeat and freq frontmatter
		if (isset($event->freq) && isset($event->repeat)) {

			$start_date = Carbon::parse($event->start);
			$end_date = Carbon::parse($event->until);

			switch($event->freq) {	

				// if daily
				case 'daily':
					$repeat = $end_date->diffInDays($start_date);
					$this->buildRepeatingDailyEvents($event, $repeat);
					break;

				// if weekly
				case 'weekly':
					$repeat = $end_date->diffInWeeks($start_date);
					$this->buildRepeatingWeeklyEvents($event, $repeat);
					break;

				// if monthly
				case 'monthly':
					$repeat = $end_date->diffInMonths($start_date);
					$this->buildRepeatingMonthlyEvents($event, $repeat);
					break;

				// if yearly
				case 'yearly':
					$repeat = $end_date->diffInYears($start_date);
					$this->buildRepeatingYearlyEvents($event, $repeat);
					break;

			}

		}
	}

	/**
	 * Build Repeating Daily Events 
	 * 
	 * @param object $event Event object
	 * @param integer $repeat How many times to repeat the event 
	 */
	private function buildRepeatingDailyEvents($event, $repeat) {

	}

	/**
	 * Build Repeating Weekly Events 
	 * 
	 * @param object $event Event object
	 * @param integer $repeat How many times to repeat the event 
	 */
	private function buildRepeatingWeeklyEvents($event, $repeat) {

	}

	/**
	 * Build Repeating Monthly Events 
	 * 
	 * @param object $event Event object
	 * @param integer $repeat How many times to repeat the event 
	 */
	private function buildRepeatingMonthlyEvents($event, $repeat) {

		for ($i = 1; $i < $repeat; $i++) {

			$recurringEvent = $event;

			var_dump($event->rules);

		}
	}

	/**
	 * Build Repeating Yearly Events 
	 * 
	 * @param object $event Event object
	 * @param integer $repeat How many times to repeat the event 
	 */
	private function buildRepeatingYearlyEvents($event, $repeat) {

	}
	/*
	 * Updates matched elements to be sorted by date
	 */
	public function sortByDate()
	{
		usort($this->matched_events, array($this, "_SortByDate"));
		return $this;
	}

	/**
	 * Updates matched elements to be sorted by time 
	 */ 
	public function sortByTime()
	{
		usort($this->matched_events, array($this, "_SortByTime"));
		return $this;
	}

	/**
	 *	Sort by start date
	 */
	private function _SortByDate($a, $b)
	{
		return strcmp($a->start, $b->start);
	}

	/**
	 * 	Sort by start time 
	 */ 
	private function _SortByTime($a, $b) 
	{
		return strcmp($a->start_time_abs, $b->start_time_abs);
	}

}