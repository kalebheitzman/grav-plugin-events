<?php

namespace Grav\Plugin;

require_once __DIR__.'/../vendor/autoload.php';

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
	 * Get events based on passed attributes
	 * 
	 * @return object Collection of all events
	 */
	/*public function findEvents($attrs = null)
	{
		if (is_null($attrs)) {
			return $this->events;			
		}

		// get an instance of events
		$events = $this->_processEvents($attrs);

		return $events;			
	}*/

	/**
	 * Event Processing
	 * 
	 * Processors take attributes that are passed via the 
	 * events.findEvents({}) method in twig and filters down the $events 
	 * collection. 
	 * 
	 * Current Filters:
	 * days = What days should the processor look for, MTWRFSU
	 * freq = How often does the event occur, weekly, monthly
	 * startDate = Find events on or after this date, 01/01/2015
	 * endDate = Find events on or before this date, 12/01/2015
	 */

	/**
	 * Process Events
	 * 
	 * @param array $attrs Attributes to process on
	 * @return object Collection of events
	 */ 
	/*private function _processEvents($attrs)
	{
		// process days
		if ($attrs['days']) {
			$events = $this->_daysProcessor($attrs['days']);
		}
		// process frequency 
		if ($attrs['freq']) {
			$events = $this->_daysProcessor($attrs['freq']);
		}
		// process startDate
		if ($attrs['startDate']) {
			$events = $this->_startDateProcessor($attrs['startDate']);
		}
		// process endDate
		if ($attrs['endDate']) {
			$events = $this->_endDateProcessor($attrs['startDate']);
		}
	}*/

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
	 * Carbon Date Processing
	 * 
	 * Use the Carbon date library to provide various dates to use when
	 * pulling entries between certain date ranges, after a date, before a 
	 * date, and etc. Carbon provides a nice relative date parser that is
	 * generically exposed via event.dateParse('first of this month') in Twig.
	 */ 

	/**
	 * Parse a relative date
	 * 
	 * @return string DateTime
	 */
	public function parseDate($string = null)
	{
		if ($string == null) {
			return null;
		}

		$date = Carbon::parse($string);
		return $date->format($this->dateFormat);
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

	/**
	 * Get start of year
	 * 
	 * @return string DateTime
	 */
	public function startOfYear()
	{
		$date = Carbon::parse('first day of this year');
		return $date->format($this->dateFormat);
	}

	/**
	 * Get end of year
	 * 
	 * @return string DateTime
	 */ 
	public function endOfYear()
	{
		$date = Carbon::parse('last day of this year');
		return $date->format($this->dateFormat);
	}

}