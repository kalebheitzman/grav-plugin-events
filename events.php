<?php

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Debugger;
use Grav\Common\Taxonomy;
use RocketTheme\Toolbox\Event\Event;

class EventsPlugin extends Plugin
{
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
		if ( $this->isAdmin() ) {
			$this->active = false;
			return;
		}

		// Dynamically add the needed taxonomy types to the taxonomies config
		$event_taxonomies = array('type', 'event_freq', 'event_repeat');
		$taxonomy_config = array_merge((array)$this->config->get('site.taxonomies'), $event_taxonomies);
		$this->config->set('site.taxonomies', $taxonomy_config);

		$this->enable([
			'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onPagesInitialized' => ['onPagesInitialized', 0],
			'onPageProcessed' => ['onPageProcessed', 0],
		]);
	}

	/**
	 * Add current direcotry to twig lookup paths.
	 */ 
	public function onTwigTemplatePaths()
	{
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
				$repeatingEvents = $this->_processRepeatingEvent($page);
			}
		}
 
		// unset grav pages
		unset($this->grav['pages']);
		// set new grav pages
		$this->grav['pages'] = $pages;
	}

	/**
	 *	Process pages that have event frontmatter 
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
	 */
	private function _processRepeatingEvent($page)
	{
		$pages = [];

		return $pages;
	} 

}