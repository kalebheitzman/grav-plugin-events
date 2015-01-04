<?php

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\GravTrait;

class Events
{
	use GravTrait;

	/**
	 * @var array
	 */ 
	protected $events;

	/**
	 * @var string
	 */ 
	protected $reference_time = "2000-01-01 00:00:00";

	/**
	 * @var string 
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
	 * @return Collection
	 */
	public function findEvents($patterns)
	{
	 	if (!$this->events) {
            $this->build();
        }

        $matches = [];

        foreach ($patterns as $key => $value) {
        	foreach ($this->events as $event) {
        		if (is_array($value)) {
	        		foreach ($value as $val) {
	        			if ($event->$key == $val) {
	        				$matches[] = $event;
	        			}
	        		}
        		}
        		else {
        			if ($event->$key == $value) {
        				$matches[] = $event;
        			}	
        		}
        	}
        }

        usort($matches, array($this, "_SortByDate"));
        $this->matched_events = $matches;

	 	return $this;
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

					$this->events[] = $entry;
				}

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

    /**
	 * Gets all matched events 
	 * 
	 * @return Array matched events
	 */
	public function get()
	{
		return $this->matched_events;
	}


}