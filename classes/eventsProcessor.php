<?php
/**
 *                  __ _           _ _           _    _
 *                 / _| |         | | |         | |  | |
 *   ___ _ __ __ _| |_| |_ ___  __| | |__  _   _| | _| |__
 *  / __| '__/ _` |  _| __/ _ \/ _` | '_ \| | | | |/ / '_ \
 * | (__| | | (_| | | | ||  __/ (_| | |_) | |_| |   <| | | |
 *  \___|_|  \__,_|_|  \__\___|\__,_|_.__/ \__, |_|\_\_| |_|
 *                                          __/ |
 * Designed + Developed by Kaleb Heitzman  |___/
 * (c) 2016
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
 * The Events Class processes `event:` frontmatter and adds virtual Event pages
 * to the Grav page stack depending on **frequency** and **repeat** rules. Each
 * virtual page is assigned a new route using a static & unique 6 character
 * token based off the existing page id and date.
 *
 * PHP version 5.6+
 *
 * @category   Plugins
 * @package    Events
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.15 Major Refactor
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      1.0.0 Initial Release
 */
class EventsProcessor
{
	/**
	 * @var  object Grav Pages
	 * @since  1.0.0 	Initial Release
	 */
	protected $pages;

	/**
	 * @var object Grav Taxonomy
	 * @since  1.0.0 	Initial Release
	 */
	protected $taxonomy;

	/**
	 * Events Class Construct
	 *
	 * Setup a pointer to pages and taxonomy for processing cloned event pages
	 * into Grav.
	 *
	 * @since  1.0.0 Initial Release
	 * @return void
	 */
	public function __construct()
	{
		// get a grav instance
		$grav = \Grav\Common\Grav::instance();

		// we use pages and taxonomy to add cloned pages back into the flow
		$this->pages = $grav['pages'];
		$this->taxonomy = $grav['taxonomy'];
	}

	/**
	 * Process Event Pages
	 *
	 * This is where the Events plugin processes events into new dynamic pages.
	 * There are 3 processing steps for updating `$this->pages` before returning
	 * it for use in the main plugin Events class.
	 *
	 * ### STEP 1: Preprocess the Event
	 *
	 * This processes all Grav pages for `event:` frontmatter and runs appropriate
	 * filters for adding the page to `@taxonomy.type: event`.
	 *
	 * ### STEP 2: Process Repeating Events
	 *
	 * In this step, we process the page for the horizontal structure of events.
	 * This processes events with `event: repeat` frontmatter duplicating pages
	 * and updating dates based on `MTWRFSU` params.
	 *
	 * ### STEP 3: Process Reoccuring Events
	 *
	 * The final processing step looks at pages with `event: freq` frontmatter.
	 * This clones the event vertically on a calender using `daily, weekly,
	 * monthly, yearly` params.
	 *
	 * @since  1.0.15 Major Refactor
	 * @return object Grav Pages List
	 */
	public function all()
	{
		// get pages
		$pages = $this->pages;

		// get the events
		$collection = $pages->all();
		$events = $collection->ofType('event');

		/**
		 * STEP 1: Preprocess the Event
		 *
		 * Preprocess the front matter for processing down the line
		 * this adds carbon _event frontmatter data for processing repeating
		 * dates and etc.
		 */
		$this->preprocessEventPages( $collection );

		/**
		 * STEP 2: Process Repeating Events
		 *
		 * Add repeating events to the collection  via [MTWRFSU]
		 */
		$this->processRepeatingEvents( $collection );

		/**
		 * STEP 3: Process Reoccuring Events
		 *
		 * Add reoccuring events to the collection [daily, weekly, monthly, yearly].
		 * We also have to initialize the collection in order to get pages that have
		 * been added to the flow.
		 */
		$collection = $this->pages->all();
		$this->processReoccuringEvents( $collection );

		// merge the collection back into grav pages
		return $this->pages;
	}

	/**
	 * STEP 1: Preprocess Event Pages
	 *
	 * Preprocess the front matter for processing down the line
	 * this adds carbon `_event:` frontmatter data for processing repeating
	 * dates and etc.
	 *
	 * @param  object $collection Grav Collection
	 * @since  1.0.15 Major Refactor
	 * @return object $events Grav Collection
	 */
	private function preprocessEventPages( $collection )
	{
		foreach ( $collection as $page ) {

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
				// get a grav instance
				$grav = \Grav\Common\Grav::instance();

				$carbonEvent['until'] = Carbon::parse( $event['start'] )->addMonths( $grav['config']->get('plugins.events.display_months_out') );
				$header->event['until'] = Carbon::parse( $event['start'] )->addMonths( $grav['config']->get('plugins.events.display_months_out') )->format('m/d/Y g:ia');

			}

			// setup grav date
			$header->date = $header->event['start'];
			$page->date($header->date);

			// store the new carbon based dates in the header frontmatter
			$header->_event = $carbonEvent;

			// create new event taxonomy
			$eventTaxonomy = [];
			$eventTaxonomy['type'] = 'event';

			// get freq taxonomy
			if ( isset($event['freq']) ) {
				$eventTaxonomy['event_freq'] = $event['freq'];
			}

			// get repeat taxonomy
			if ( isset($event['repeat']) ) {
				$rules = str_split($event['repeat']);
				$eventTaxonomy['event_repeat'] = [];
				foreach($rules as $rule) {
					array_push($eventTaxonomy['event_repeat'], $rule);
				}
			}

			// get location taxonomy
			if ( isset($event['location']) ) {
				$eventTaxonomy['event_location'] = $event['location'];
			}

			// add taxonomies
			$taxonomy = $page->taxonomy();
			$newTaxonomy = array_merge($taxonomy, $eventTaxonomy);

			// set the page taxonomy
			$page->taxonomy($newTaxonomy);
			$header->taxonomy = $newTaxonomy;

			// add the page to the taxonomy map
			$this->taxonomy->addTaxonomy($page, $newTaxonomy);
		}

		return $collection;
	}

	/**
	 * STEP 2: Process Repeating Events
	 *
	 * Search for `event: repeat:` frontmatter and add repeating events to the
	 * collection via [MTWRFSU].
	 *
	 * @param  object $collection Grav Collection
	 * @since  1.0.15 Major Refactor
	 * @return object         Grav Collection
	 */
	private function processRepeatingEvents( $collection )
	{
		// look for events with repeat rules
		foreach ( $collection as $page ) {
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

						if ($s_diff != 0) {
							$dates['start'] = $header->_event['start']->copy()->addDays($s_diff);
							$dates['end'] = $header->_event['end']->copy()->addDays($e_diff);

							// clone the page and add the new dates
							$clone = $this->clonePage( $page, $dates, $rule );
						}
					}
				}
			}
		}
		return $collection;
	}

	/**
	 * STEP 3: Process Reoccuring Events
	 *
	 * Search for `event: freq:` frontmatter and add pages vertically down
	 * the calendar to the collection. This processor will also look for
	 * `event: until:` frontmatter for determining when to stop processing the
	 * reoccuring event. If this front matter doesn't exist, then the plugin will
	 * look for a value set in the plugin config (+6 months) and process
	 * reoccuring event out to this date. If you need the event to reoccur further
	 * than this default then you must set an until date.
	 *
	 * @param  object $collection Grav Collection
	 * @since  1.0.15 Major Refactor
	 * @return object         Grav Collection
	 */
	private function processReoccuringEvents( $collection )
	{
		foreach ( $collection as $page ) {

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

					// update the start and end dates of the event frontmatter
					switch($freq) {
						case 'daily':
							$newStart = $start->copy()->addDays($i);
							$newEnd = $end->copy()->addDays($i);
							break;

						case 'weekly':
							$newStart = $start->copy()->addWeeks($i);
							$newEnd = $end->copy()->addWeeks($i);
							break;

						// special case for monthly because there aren't the same
						// number of days each month.
						case 'monthly':
							// start vars
							$sDayOfWeek = $start->copy()->dayOfWeek;
							$sWeekOfMonth = $start->copy()->weekOfMonth;
							$sHours = $start->copy()->hour;
							$sMinutes = $start->copy()->minute;
							$sMonth = $start->copy()->month;
							$sYear = $start->copy()->year;
							$sNext = $start->copy()->addMonths($i)->firstOfMonth();

							// end vars
							$eDayOfWeek = $end->copy()->dayOfWeek;
							$eWeekOfMonth = $end->copy()->weekOfMonth;
							$eHours = $end->copy()->hour;
							$eMinutes = $end->copy()->minute;
							$eMonth = $end->copy()->month;
							$eYear = $end->copy()->year;
							$eNext = $end->copy()->addMonths($i)->firstOfMonth();

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
							$newStart = $start->copy()->addYears($i);
							$newEnd = $end->copy()->addYears($i);
							break;
					}

					// build the date params
					$dates['start'] = $newStart;
					$dates['end'] = $newEnd;

					// access the saved original for repeating MTWRFSU events
					if (isset($page->header()->_event['page'])) {
						$page = $page->header()->_event['page'];
					}

					// get the new cloned event
					$this->clonePage( $page, $dates );
				}
			}
		}

		return $collection;
	}

	/**
	 * Clone an Event Page
	 *
	 * This function clones a Grav Page and adds it as a virtual page to
	 * `$this->pages`. It also adds the page to `$this->taxonomy` so that we can
	 * query pages in templates and collection headers using `@taxonomy.event`.
	 * This will not create a physical page that is added to the filesystem.
	 *
	 * Adding a new page to the system happens by adding the new dates to the
	 * `event:` frontmatter, updating the `$page->date`, and adding a new and
	 * unique `$page->path` and `$page->route`.
	 *
	 * @param  object $page  Grav Page
	 * @param  array $dates  Carbon Dates
	 * @param  string $rule	 Rule for repeating events
	 * @since  1.0.1 Initial Release
	 * @return object        Grav Page
	 */
	private function clonePage( $page, $dates, $rule = null ) {

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

		// set the media
		$media = $page->media();
		$clone->media($media);

		// a token is needed because the key is null
		// build a page token for lookup
		$id = $clone->id();
		$token = substr( md5( $id . $header->event['start'] ),0,6);
		$header->token = $token;

		// store a processing token for future repeating pages
		if ( ! is_null( $rule ) ) {
			$header->_event['rule'] = $rule;
			$header->_event['token'] = $token;
			$header->_event['page'] = $page;
		}

		// build the path
		$path = $page->path() . '/' . $token;
		// build the route
		$route = $page->route() . '/' . $token;

		// check to see if this has been tokenized already and
		// clean it up.
		if ( is_null($rule) && isset($header->_event['rule']) ) {
			$path = str_replace('/' . $header->_event['token'], '', $path);
			$route = str_replace('/' . $header->_event['token'], '', $route);
		}

		// build a unique path
		$clone->path( $path );
		// build a unique route
		$clone->route( $route );
		// update the clone with the new header
		$clone->header( $header );

		// insert the page into grav pages
		$this->pages->addPage( $clone );
		$this->taxonomy->addTaxonomy($clone, $clone->taxonomy());

		return $clone;
	}

}
