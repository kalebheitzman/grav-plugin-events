<?php

namespace Grav\Plugin;

require_once __DIR__.'/vendor/autoload.php';

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Page;
use Grav\Common\Taxonomy;
use RocketTheme\Toolbox\Event\Event;

use Carbon\Carbon;

class EventsPlugin extends Plugin
{
	/**
	 * @var object Carbon date 
	 */
	protected $now;

	/**
	 * @var  string Route
	 */
	protected $route = 'events';

	/**
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

		// Add the type taxonomy and make it available in Admin
		$event_taxonomies = array('type');
		$taxonomy_config = array_merge((array)$this->config->get('site.taxonomies'), $event_taxonomies);
		$this->config->set('site.taxonomies', $taxonomy_config);

		// Nothing else is needed for admin so close it out
		if ( $this->isAdmin() ) {
			$this->active = false;
			return;
		}

		// Add these to taxonomy for creating collections
		$event_taxonomies = array('event_freq', 'event_repeat');
		$taxonomy_config = array_merge((array)$this->config->get('site.taxonomies'), $event_taxonomies);
		$this->config->set('site.taxonomies', $taxonomy_config);

		// get the current datetime with carbon
		$this->now = Carbon::now();

		$this->enable([
			'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onPagesInitialized' => ['onPagesInitialized', 0],
			'onPageProcessed' => ['onPageProcessed', 0],
			'onBlueprintCreated' => ['onBlueprintCreated', 0]
		]);
	}

	/**
	 * Add current direcotry to twig lookup paths.
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
		/** @var Pages $pages */
		$pages = $this->grav['pages'];
		// get all the page instances
		$pageInstances = $pages->instances();

		// iterate through page instances to find event frontmatter
		foreach($pageInstances as $page) {
			$header = $page->header();
			
			// process for repeating events if event front matter is set
			if (isset($header->event) && isset($header->event['repeat'])) {
				// build a list of repeating pages
				$repeatingEvents = $this->_processRepeatingEvent($page);
				// add the new $repeatingEvents pages to the $pages object
				foreach($repeatingEvents as $key => $eventPage) {
					// get the start date to create a slug
					$header = $eventPage->header();
					$eventStart = $header->event['start'];
					// build the slug 
					$eventRoute = $eventPage->route();
					$newRoute = $eventRoute . '-' . $eventStart->toDateString();
					// insert the page into the stack
					$pages->addPage($eventPage, $newRoute);
				}
			}
		}

		// unset grav pages
		unset($this->grav['pages']);
		// set new grav pages
		$this->grav['pages'] = $pages;
	}

	/**
	 * Process pages that have event frontmatter 
	 */
	public function onPageProcessed(Event $event)
	{
		// Get the page header
		$page = $event['page'];
		$header = $page->header();
		$taxonomy = $page->taxonomy();

		// check for event frontmatter 
		if (isset($header->event)) {
			// set the header date
			$header->date = $header->event['start'];
			$page->header($header);
			// set the new event taxonomy
			$taxonomy = $this->_eventFrontmatterToTaxonomy($page, $header);
			$page->taxonomy($taxonomy);
		}
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
	 * Convert event frontmatter to taxonomy
	 * 
	 * @param array $taxonomy Taxonomy
	 * @param array $event Event details
	 */ 
	private function _eventFrontmatterToTaxonomy($page, $header)
	{	
		// event frontmatter
		$event = $header->event;
		// set type taxonomy to event
		$taxonomy = $page->taxonomy();
		if (!isset($taxonomy['type'])) {
			$taxonomy['type'] = array($this->config->get('plugins.events.filters.type'));
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
	 * @param object $page Page object
	 * @return array Newly created event pages.
	 */
	private function _processRepeatingEvent($page)
	{
		$pages = [];

		// header information
		$header = $page->header();
 		$start = $header->event['start'];
 		$end = $header->event['end'];
		$repeat = $header->event['repeat'];
		$freq = $header->event['freq'];
		$until = $header->event['until'];
 		$count = $this->_calculateIteration($freq, $until);

 		// date calculation vars
 		$carbonStart = Carbon::parse($start);
 		$carbonEnd = Carbon::parse($end);
 		$carbonDay = $carbonStart->dayOfWeek;
 		$carbonWeek = $carbonStart->weekOfMonth;
 		$carbonWeekYear = $carbonStart->weekOfYear;

 		for($i=1; $i <= $count; $i++) {
 			
 			// update the start and end dates of the event frontmatter 			
 			switch($freq) {
				case 'daily':
					$newStart = $carbonStart->addDays($i);
					$newEnd = $carbonEnd->addDays($i);
					break;

				case 'weekly':
					$newStart = $carbonStart->addWeeks($i);
					$newEnd = $carbonEnd->addWeeks($i);
					break;

				case 'monthly':
					$newStart = $carbonStart->addMonths($i);
					$newEnd = $carbonEnd->addMonths($i);
					break;

				case 'yearly':
					$newStart = $carbonStart->addYears($i);
					$newEnd = $carbonEnd->addYears($i);
					break;
			}

			$header->event['start'] = $newStart;
			$header->event['end'] = $newEnd;

 			// save the eventPageheader
 			$page->header($header);

 			array_push($pages, $page);
 		}

		return $pages;
	}

	/**
	 * Calculate how many times to iterate event based on freq and until. The
	 * Carbon DateTime api extension is used to calculcate these differences.
	 * 
	 * @param string $freq How often to repeat
	 * @param string $until The date to repeat event until
	 * @return integer How many times to loops
	 */
	private function _calculateIteration($freq, $until)
	{
		$count = 0;
		
		$currentDate = $this->now;
		$untilDate = Carbon::parse($until);

		switch($freq) {
			case 'daily':
				$count = $untilDate->diffInDays($currentDate);
				break;

			case 'weekly':
				$count = $untilDate->diffInWeeks($currentDate);
				break;

			case 'monthly':
				$count = $untilDate->diffInMonths($currentDate);
				break;

			case 'yearly':
				$count = $untilDate->diffInYears($currentDate);
				break;
		}

		return $count;
	} 

}