<?php

namespace Grav\Plugin;

require __DIR__.'/../vendor/autoload.php';

use Grav\Common\Grav;
use Grav\Common\GravTrait;
use Grav\Common\Page\Collection;
use \Carbon\Carbon;

class Events
{
	use GravTrait;

	/**
	 * @var object Config
	 */ 
	protected $config;

	/**
	 * @var string Date format
	 */
	protected $dateFormat; 

	/**
	 * @var object Collection of events
	 */ 
	protected $events;

	/**
	 * @var object Carbon date 
	 */
	protected $now;

	/**
	 * Construct
	 */ 
	public function __construct()
	{
		// set the current date
		$this->now = Carbon::now();
		// get the config
		$this->config = self::$grav['config'];
		// date format
		$this->dateFormat = $this->config->get('plugins.events.date_format');
		/** @var Taxonomy $taxonomy_map */
		$taxonomy_map = self::$grav['taxonomy'];
		// get the plugin filters setting
		$filters = (array) $this->config->get('plugins.events.filters');
		$operator = $this->config->get('plugins.events.filter_combinator');
		// create a new collection of all events
		$collection = new Collection();
		$collection->append($taxonomy_map->findTaxonomy($filters, $operator)->toArray());
		// set events
		$this->events = $collection;
	}

	/**
	 * Get all events
	 * 
	 * @return object Collection of all events
	 */
	public function getEvents($attrs = null)
	{
		if (is_null($attrs)) {
			return $this->events;			
		}

		var_dump($attrs);


		return $this->events;			
	}

	/**
	 * Days processor
	 */
	private function _daysProcessor($attr)
	{

	}

	/**
	 * Frequency processor
	 */   
	private function _freqProcessor($attr)
	{

	}

	/**
	 * Start date processor
	 */ 
	private function _startDateProcessor($attr)
	{

	}

	/**
	 * End date processor 
	 */
	private function _endDateProcessor($attr)
	{
		
	}

	/**
	 * Get start of week
	 * 
	 * @return string DateTime
	 */
	public function startOfWeek()
	{
		$startDate = Carbon::parse('last monday');
		return $startDate->format($this->dateFormat);
	}

	/**
	 * Get end of week
	 * 
	 * @return string DateTime
	 */ 
	public function endOfWeek()
	{
		$endDate = Carbon::parse('next monday');
		return $endDate->format($this->dateFormat);
	}

}