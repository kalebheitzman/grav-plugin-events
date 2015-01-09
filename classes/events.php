<?php

namespace Grav\Plugin;

use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Page\Collection;

class Events
{
	use GravTrait;

	/**
	 * @var object Config
	 */ 
	protected $config;

	/**
	 * @var object Collection of events
	 */ 
	protected $events;

	/**
	 * Construct
	 */ 
	public function __construct()
	{
		$this->config = self::$grav['config'];

		/** @var Taxonomy $taxonomy_map */
		$taxonomy_map = self::$grav['taxonomy'];

		// get the plugin filters setting
		$filters = (array) $this->config->get('plugins.events.filters');
		$operator = $this->config->get('plugins.events.filter_combinator');

		// create a new collection of all events
		$collection = new Collection();
		$collection->append($taxonomy_map->findTaxonomy($filters, $operator)->toArray());

		$this->events = $collection;
	}

	/**
	 * Get all events
	 * 
	 * @return object Collection of all events
	 */
	public function get()
	{
		return $this->events;
	}

}