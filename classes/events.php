<?php

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Page\Collection;

class Events
{
	use GravTrait;

	/**
	 * @var array Events collection
	 */ 
	protected $events;

	/**
	 * @var Config $config
	 */
	protected $config;

	/**
	 * Class construct
	 */
	public function __construct()
	{
		// get configuration
		$this->config = self::$grav['config'];

		// Dynamically add the needed taxonomy types to the taxonomies config
		$event_taxonomies = array('type');
		$taxonomy_config = array_merge((array)$this->config->get('site.taxonomies'), $event_taxonomies);
		$this->config->set('site.taxonomies', $taxonomy_config);
	}

	/**
	 * Find events based on options
	 * 
	 * @param array $patterns patterns to search
	 * @param string $operator Operator
	 * @return object Collection
	 */
	public function findEvents($patterns = null, $order = 'date', $operator = 'and')
	{
		// build the event listing
	 	if (!$this->events) {
	 		$this->build();
	 	}

        return $this->events;
	}

	/**
	 * Build events list
	 * 
	 * @internal
	 * @return object Collection
	 */ 
	protected function build()
	{
		/** @var Taxonomy $taxonomy_map */
		$taxonomy_map = self::$grav['taxonomy'];
	 	$pages = self::$grav['pages'];
	 	$routes = $pages->routes();
		ksort($routes);

		// add event taxonomy to pages with event frontmatter
		foreach($routes as $route => $path) {
			$page = $pages->get($path);
			// check if the page is routable
			if ($page->routable()) {
				// get the page header
				$header = $page->header();
				// check for event frontmatter 
				if (isset($header->event)) {
					// set type taxonomy to event
					$taxonomy = $page->taxonomy();
					if (!isset($taxonomy['type'])) {
						$taxonomy['type'] = array($this->config->get('plugins.events.filters.type'));
					}
					$page->taxonomy($taxonomy);
				}
			}
		}

		// get the plugin filters setting
		$filters = (array) $this->config->get('plugins.events.filters');
		$operator = $this->config->get('plugins.events.filter_combinator');

		// create a new collection of all events
		$collection = new Collection();
		$collection->append($taxonomy_map->findTaxonomy($filters, $operator)->toArray());

		$this->events = $collection;
	}
}