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

        usort($matches, array($this, "sort_by_date"));
        $this->matched_events = $matches;

	 	return $this;
	} 

	public function get()
	{
		return $this->matched_events;
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
					}

					// process the end date
					if (isset($entry->end)) {
						$datetime = explode(" ", $entry->end);
						$entry->end_date = $datetime[0];						
						$entry->end_time = $datetime[1];						
					}

					$this->events[] = $entry;
				}

			}

		}
	}

	/**
	 *	Sort by start date
	 */
	private function sort_by_date($a, $b)
	{
		return strcmp($a->start, $b->start);
	}

	/**
	 * 	Sort by start time 
	 */ 
	private function sort_by_time($a, $b) 
	{
		return strcmp($a->start_time, $b->start_time);
	}

	public function sortByDate()
	{
		return usort($this->events, array($this, "sort_by_date"));
	}

	public function sortByTime()
	{
		return usort($this->events, array($this, "sort_by_time"));
	}

}