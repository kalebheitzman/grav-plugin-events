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
	 * @return array
	 */
	 public function findEvents($pattern)
	 {
	 	if (!$this->events) {
            $this->build();
        }
	 	
	 	$events = $this->events;
	 	
	 	return $events;
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
					$entry = new EventEntry();
					$entry->title = $header->title;
					$entry->url = $route;
					$entry->start = isset($header->event['start']) ? $header->event['start'] : null;
					$entry->end = isset($header->event['end']) ? $header->event['end'] : null;
					$entry->repeat = isset($header->event['repeat']) ? $header->event['repeat'] : null;
					$entry->freq = isset($header->event['freq']) ? $header->event['freq'] : null;				
					$entry->until = isset($header->event['until']) ? $header->event['until'] : null;

					$this->events[] = $entry;
				}

			}

		}
	 }
}