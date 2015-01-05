<?php

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\GravTrait;

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
					}

					// process the end date
					if (isset($entry->end)) {
						$datetime = explode(" ", $entry->end);
						$entry->end_date = $datetime[0];						
						$entry->end_time = $datetime[1];	
						$entry->end_time_abs = strtotime($datetime[1], $this->absolute);					
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
	 */
	private function buildRepeatingEntries($entry)
	{
		// require date library

		// check for event repeat and freq frontmatter
		if (isset($entry->freq) && isset($entry->repeat)) {

			switch($this->freq) {	

				// if daily
				case 'daily':

					break;

				// if weekly
				case 'weekly':

					break;

				// if monthly
				case 'monthly':
					
					break;

				// if yearly
				case 'yearly':

					break;

			}

		}
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