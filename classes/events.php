<?php
/**
 * Grav Events Plugin Events Class
 *
 * The Events Class instantiates an instance of all Events available in Grav
 * and provides filters for filtering out uneeded events, setting offsets,
 * limits and etc.
 *
 * PHP version 5.6+
 *
 * @category   Plugins
 * @package    Events
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.15
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      1.0.0 Initial Release
 */

namespace Events;

// import classes
require_once __DIR__.'/../vendor/autoload.php';

// tools to use
use Carbon\Carbon;
use Grav\Common\Grav;

/**
 * Events Plugin Events Class
 *
 * The Events Class instantiates an instance of all Events available in Grav
 * and provides filters for filtering out uneeded events, setting offsets,
 * limits and etc. This creates a listing of events applies repeat rules,
 * for example **MTWRFSU**, frequency rules, for example **daily, weekly,
 * monthly, or yearly,** creates event instances with just date information
 * tied to a 6 digit token, and then it filters down and adds an events that
 * need added to Grav Pages.
 *
 * @package    Events
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.15
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      1.0.0 Initial Release
 */
class Events
{
	/**
	 * @var object Grav Object
	 */
	protected $grav;

	/**
	 * @var object Grav Config
	 */
	protected $config;

	/**
	 * @var  object Grav Pages
	 */
	protected $pages;

	/**
	 * @var  object Grav Collection
	 */
	protected $events;

	/**
	 * @var object Grav Taxonomy
	 */
	protected $taxonomy;

	/**
	 * Events Class Construct
	 *
	 * Through the construct we pull in the main Grav instance and the main
	 * config instance. We also set up the main rules and params to calculate
	 * against in other parts of this class.
	 *
	 * @param object $grav   Grav Instance
	 * @param object $config Grav Configuration
	 * @since  1.0.0 Initial Release
	 *
	 * @return void
	 */
	public function __construct( $grav, $config )
	{
		$this->grav = $grav;
		$this->config = $config;
		$this->taxonomy = $this->grav['taxonomy'];
	}

	/**
	 * Get an instance of events
	 *
	 * This is the bulkhead of this class. It creates an events listing using
	 * various rules and params to add events to the page list while also
	 * adding all events, including repeating, to an events array with a
	 * searchable token. The events array contains the appropriate date
	 * information for use in templates
	 *
	 * @since  1.0.0 Initial Release
	 *
	 * @return object Grav Pages List
	 */
	public function all()
	{
		// get pages
		$pages = $this->grav['pages'];
		$this->pages = $pages;

		// get the events
		$collection = $pages->all();
		$events = $collection->ofType('event');
		$this->events = $events;

		/**
		 * STEP 1: Preprocess the Event
		 * preprocess the front matter for processing down the line
		 * this adds carbon _event frontmatter data for processing repeating
		 * dates and etc.
		 */
		$events = $this->preprocessEventPages( $events );

		/**
		 * STEP 2: Process Repeating Events
		 * add repeating events to the collection [MTWRFSU]
		 */
		$this->processRepeatingEvents( $events );
		$events = $this->pages->all()->ofType('event');

		/**
		 * STEP 3: Process Reoccuring Events
		 * add reoccuring events to the collection
		 * [daily, weekly, monthly, yearly]
		 */
		$this->processReoccuringEvents( $events );

		// merge the collection back into grav pages
		return $this->pages;
	}

	/**
	 * STEP 1: Preprocess the Event
	 * Preprocess Event Pages
	 *
	 * Take an events collection and preprocess the page header, etc for later
	 * date processing, slug processing, etc.
	 *
	 * @param  object $events Grav Collection
	 * @return object         Grav Collection
	 */
	private function preprocessEventPages( $events )
	{
		foreach ( $events as $page ) {

			// get header information
			$header = $page->header();
			if ( ! isset( $header->event['start'] ) ) {
				return;
			}

			// process date information
			$event = $header->event;

			// build a carbon events object to insert into header frontmatter
			$carbonEvent = [];
			$carbonEvent['start'] = Carbon::parse( $event['start'] );
			$carbonEvent['end'] = Carbon::parse( $event['end'] );

			// build an until date if needed
			if ( isset( $event['until'] ) ) {
				$carbonEvent['until'] = Carbon::parse( $event['until'] );
			}
			elseif ( isset( $event['freq'] ) && ! isset( $event['until'] ) ) {
				$carbonEvent['until'] = Carbon::parse( $event['start'] )->addMonths( 3 );
				$header->event['until'] = Carbon::parse( $event['start'] )->addMonths( 3 )->format('m/d/Y g:ia');
			}

			// setup grav date
			$header->date = $header->event['start'];
			$page->date($header->date);

			// store the new carbon based dates in the header frontmatter
			$header->_event = $carbonEvent;

			// add taxonomies
			$taxonomy = $page->taxonomy();
			$eventTaxonomies = array('type' => array('event'));
			$newTaxonomy = array_merge($taxonomy, $eventTaxonomies);

			$page->taxonomy($newTaxonomy);
			$header->taxonomy = $newTaxonomy;
		}

		return $events;
	}

	/**
	 * STEP 2: Process Repeating Events
	 * Process Repeating Events [MTWRFSU]
	 *
	 * @param  object $events Grav Collection
	 *
	 * @since  1.0.15 Major Refactor
	 *
	 * @return object         Grav Collection
	 */
	private function processRepeatingEvents( $events )
	{
		// look for events with repeat rules
		foreach ( $events as $page ) {
			$header = $page->header();

			if ( isset( $header->event['repeat'] ) ) {
				$rules = str_split( $header->event['repeat'] );

				// multiple repeating events
				if ( count( $rules ) > 1 ) {
					foreach ( $rules as $rule ) {

						// get new dates based on the rule
						$s_dow = $header->_event['start']->dayOfWeek;
						$e_dow = $header->_event['end']->dayOfWeek;

						// carbon calc rules
						$carbonRules['M'] = Carbon::MONDAY;
						$carbonRules['T'] = Carbon::TUESDAY;
						$carbonRules['W'] = Carbon::WEDNESDAY;
						$carbonRules['R'] = Carbon::THURSDAY;
						$carbonRules['F'] = Carbon::FRIDAY;
						$carbonRules['S'] = Carbon::SATURDAY;
						$carbonRules['U'] = Carbon::SUNDAY;

						// calculate the difference in days
						$s_diff = ( $carbonRules[$rule]-$s_dow );
						$e_diff = ( $carbonRules[$rule]-$e_dow );

						$dates['start'] = $header->_event['start']->copy()->addDays($s_diff);
						$dates['end'] = $header->_event['end']->copy()->addDays($e_diff);

						// clone the page and add the new dates
						$clone = $this->cloneEvent( $page, $dates );

						// insert the page into grav pages
						$this->pages->addPage( $clone );
						$this->taxonomy->addTaxonomy($clone, $clone->taxonomy());
					}
				}
				// only one repeat rule
				else {

				}
			}
		}
		return $events;
	}

	/**
	 * STEP 3: Process Reoccuring Events
	 * Process Reoccuring Events
	 * @param  object $events Grav Collection
	 * @return object         Grav Collection
	 */
	private function processReoccuringEvents( $events )
	{
		foreach ( $events as $page ) {

			$header = $page->header();

			if ( isset( $header->event['freq'] ) && isset( $header->event['until'] ) ) {

				// get some params to calculate
				$freq  = $header->event['freq'];
				$until = Carbon::parse($header->event['until']);
				$start = Carbon::parse($header->event['start']);
				$end   = Carbon::parse($header->event['end']);

				/**
				 * Calculate the iteration count depending on frequency set
				 */
				switch($freq) {
					case 'daily':
						$count = $until->diffInDays($start);
						break;

					case 'weekly':
						$count = $until->diffInWeeks($start);
						break;

					case 'monthly':
						$count = $until->diffInMonths($start);
						break;

					case 'yearly':
						$count = $until->diffInYears($start);
						break;
				}

				/**
				 * Calculate the New Dates based on the Count and Freq
				 */
				for( $i=1; $i < $count; $i++ )
				{
					//$newStart = $start->copy();
					//$newEnd = $end->copy();

					// update the start and end dates of the event frontmatter
					switch($freq) {
						case 'daily':
							$newStart = $newStart->addDays($i);
							$newEnd = $newEnd->addDays($i);
							break;

						case 'weekly':
							$newStart = $start->copy()->addWeeks($i);
							$newEnd = $end->copy()->addWeeks($i);
							break;

						// special case for monthly because there aren't the same
						// number of days each month.
						case 'monthly':
							// start vars
							$sDayOfWeek = $newStart->dayOfWeek;
							$sWeekOfMonth = $newStart->weekOfMonth;
							$sHours = $newStart->hour;
							$sMinutes = $newStart->minute;
							$sMonth = $newStart->month;
							$sYear = $newStart->year;
							$sNext = $newStart->addMonths($i)->firstOfMonth();

							// end vars
							$eDayOfWeek = $newEnd->dayOfWeek;
							$eWeekOfMonth = $newEnd->weekOfMonth;
							$eHours = $newEnd->hour;
							$eMinutes = $newEnd->minute;
							$eMonth = $newEnd->month;
							$eYear = $newEnd->year;
							$eNext = $newEnd->addMonths($i)->firstOfMonth();

							// weeks
							$rd[1] = 'first';
							$rd[2] = 'second';
							$rd[3] = 'third';
							$rd[4] = 'fourth';
							$rd[5] = 'fifth';

							// days
							$ry[0] = 'sunday';
							$ry[1] = 'monday';
							$ry[2] = 'tuesday';
							$ry[3] = 'wednesday';
							$ry[4] = 'thursday';
							$ry[5] = 'friday';
							$ry[6] = 'saturday';

							// months
							$rm[1] = 'jan';
							$rm[2] = 'feb';
							$rm[3] = 'mar';
							$rm[4] = 'apr';
							$rm[5] = 'may';
							$rm[6] = 'jun';
							$rm[7] = 'jul';
							$rm[8] = 'aug';
							$rm[9] = 'sep';
							$rm[10] = 'oct';
							$rm[11] = 'nov';
							$rm[12] = 'dec';

							// get the correct next date
							$sStringDateTime = $rd[$sWeekOfMonth] . ' ' . $ry[$sDayOfWeek] . ' of ' . $rm[$sNext->month] . ' ' . $sNext->year;
							$eStringDateTime = $rd[$eWeekOfMonth] . ' ' . $ry[$eDayOfWeek] . ' of ' . $rm[$eNext->month] . ' ' . $eNext->year;

							$newStart = Carbon::parse($sStringDateTime)->addHours($sHours)->addMinutes($sMinutes);
							$newEnd = Carbon::parse($eStringDateTime)->addHours($eHours)->addMinutes($eMinutes);
							break;

						case 'yearly':
							$newStart = $newStart->addYears($i);
							$newEnd = $newEnd->addYears($i);
							break;
					}

					// build the date params
					$dates['start'] = $newStart;
					$dates['end'] = $newEnd;

					// get the new cloned event
					$clone = $this->cloneEvent( $page, $dates );

					// insert the page into grav pages
					$this->pages->addPage( $clone );
					$this->taxonomy->addTaxonomy($clone, $clone->taxonomy());
				}
			}
		}

		return $events;
	}

	/**
	 * Clone an Event Page
	 * @param  object $page  Grav Page
	 * @param  array $dates  Carbon Dates
	 * @return object        Grav Page
	 */
	private function cloneEvent( $page, $dates ) {

		// clone the page
		$clone = clone $page;

		// get the clone header
		$header = clone $clone->header();
		$taxonomy = $clone->taxonomy();

		// update the header dates
		$header->date = $dates['start']->format('m/d/Y g:i a');
		$header->event['start'] = $dates['start']->format('m/d/Y g:i a');
		$header->event['end'] = $dates['end']->format('m/d/Y g:i a');
		$clone->date($header->date);

		// build a page token for lookup
		$id = $clone->id();
		$token = substr( md5( $id . $header->event['start'] ),0,6);
		$header->token = $token;

		// set the media
		$media = $page->media();
		$clone->media($media);

		// build a unique path
		$path = $clone->path() . '/' . $token;
		$clone->path( $path );

		// build a unique route
		$route = $clone->route() . '/' . $token;
		$clone->route( $route );

		// update the clone with the new header
		$clone->header( $header );

		// return the clone
		return $clone;
	}

}
