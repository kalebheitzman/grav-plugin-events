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

		// get an instance of events
		$events = $this->events;

		// process startDate
		if ($attrs['startDate']) {
			$events = $this->_startDateProcessor($attrs['startDate']);
		}

		// process endDate
		if ($attrs['endDate']) {
			$events = $this->_endDateProcessor($attrs['startDate']);
		}

		return $events;			
	}

	/**
	 * Days processor
	 */
	private function _daysProcessor($attr)
	{
		return $this->events;
	}

	/**
	 * Frequency processor
	 */   
	private function _freqProcessor($attr)
	{
		return $this->events;
	}

	/**
	 * Start date processor
	 */ 
	private function _startDateProcessor($attr)
	{
		return $this->events;
	}

	/**
	 * End date processor 
	 */
	private function _endDateProcessor($attr)
	{
		return $this->events;
	}

	/**
	 * Get start of week
	 * 
	 * @return string DateTime
	 */
	public function startOfWeek()
	{
		$date = Carbon::parse('last monday');
		return $date->format($this->dateFormat);
	}

	/**
	 * Get end of week
	 * 
	 * @return string DateTime
	 */ 
	public function endOfWeek()
	{
		$date = Carbon::parse('next monday');
		return $date->format($this->dateFormat);
	}

	/**
	 * Get start of month
	 * 
	 * @return string DateTime
	 */
	public function startOfMonth()
	{
		$date = Carbon::parse('first day of this month');
		return $date->format($this->dateFormat);
	}

	/**
	 * Get end of month
	 * 
	 * @return string DateTime
	 */ 
	public function endOfMonth()
	{
		$date = Carbon::parse('last day of this month');
		return $date->format($this->dateFormat);
	}

}