<?php

namespace Grav\Plugin;

require_once __DIR__.'/vendor/autoload.php';

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
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

	protected $localGrav;

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

		// Nothing else is needed for admin so close it out
		if ( $this->isAdmin() ) {
			$this->active = false;
			return;
		}

		// Add these to taxonomy for events management
		$event_taxonomies = array('type', 'event_freq', 'event_repeat');
		$taxonomy_config = array_merge((array)$this->config->get('site.taxonomies'), $event_taxonomies);
		$this->config->set('site.taxonomies', $taxonomy_config);

		// get the current datetime with carbon
		$this->now = Carbon::now();

		$this->enable([
			'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onGetPageTemplates' => ['onGetPageTemplates', 0],
			'onPagesInitialized' => ['onPagesInitialized', 0],
			'onCollectionProcessed' => ['onCollectionProcessed', 0],
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
     * Add page template types.
     *
     * @param Event $event
     */
    public function onGetPageTemplates(Event $event)
    {
        /** @var Types $types */
        $types = $event->types;
        $types->scanTemplates('plugins://events/templates');
    }

	/**
	 * Check for repeating entries and add them to the page collection
	 */
	public function onPagesInitialized()
	{
		// get a new instance of grav pages
		// I instantiate a new pages object to deal with cloning and pointer issues
		$gravPages = new \Grav\Common\Page\Pages($this->grav);
		$gravPages->init();

		// get taxonomy so we can add generated pages
		$taxonomy = $this->grav['taxonomy'];

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

				$this->grav['taxonomy']->addTaxonomy($page);
			}

			// process for repeating events if event front matter is set
			if (isset($header->event) && isset($header->event['repeat'])) {
				$gravPages->addPage($page);
				// build a list of repeating pages
				$repeatingEvents = $this->_processRepeatingEvent($page);
				// add the new $repeatingEvents pages to the $pages object
				foreach($repeatingEvents as $key => $eventPage) {
					// add the page to the stack
					$gravPages->addPage($eventPage, $eventPage->route());
					// add the page to the taxonomy map
					$this->grav['taxonomy']->addTaxonomy($eventPage);
				}
				
			}

			
		}
		unset($this->grav['pages']);
		$this->grav['pages'] = $gravPages;
		$this->localGrav = $this->grav;
	}

	/**
     * Order the collection.
     *
     * @param Event $event
     */
    public function onCollectionProcessed(Event $event)
    {
        /** @var Collection $collection */
        $collection = $event['collection'];
		$params = $collection->params();
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
	 * @param object $page Page object
	 * @return array Newly created event pages.
	 */
	private function _processRepeatingEvent($page)
	{
		$pages = [];

		// header information
		$header 	= $page->header();
 		$start 		= $header->event['start'];
 		$end  		= $header->event['end'];
		$repeat 	= $header->event['repeat'];
		$freq 		= $header->event['freq'];
		$until 		= $header->event['until'];
 		$count 		= $this->_calculateIteration($freq, $until);

 		// date calculation vars
 		$carbonStart = Carbon::parse($start);
 		$carbonEnd = Carbon::parse($end);
 		$carbonDay = $carbonStart->dayOfWeek;
 		$carbonWeek = $carbonStart->weekOfMonth;
 		$carbonWeekYear = $carbonStart->weekOfYear;

 		for($i=1; $i <= $count; $i++) {

 			$newPage = clone($page);
 			$newPage->unsetRouteSlug();

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

				case 'monthly':
					$newStart = $carbonStart->addMonths(1);
					$newEnd = $carbonEnd->addMonths(1);
					break;

				case 'yearly':
					$newStart = $carbonStart->addYears(1);
					$newEnd = $carbonEnd->addYears(1);
					break;
			}

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
			$suffix =  '-' . $newStart->format('U');

			// set a new page slug
			$slug = end($route_parts);
			$newSlug = $slug . $suffix;
			$newHeader->slug = $newSlug;
			$newPage->slug($newSlug);

			// set a new route
			$newRoute = $route . $suffix;
			$newHeader->routes = array('default' => $newRoute );
			
			// set a fake path
			$path = $page->path();
			$newPath = $path . $suffix;
			$newPage->path($newPath);

 			// save the eventPageheader
 			$newPage->header($newHeader);
 			$pages[] = $newPage;

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