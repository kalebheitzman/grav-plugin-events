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

namespace Grav\Plugin;

// import classes
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/classes/calendarProcessor.php';
require_once __DIR__.'/classes/eventsProcessor.php';

use Grav\Common\Plugin;
use Grav\Common\Grav;
use Grav\Common\Page\Collection;
use Grav\Common\Page\Page;
use Grav\Common\Page\Pages;
use Grav\Common\Taxonomy;
use RocketTheme\Toolbox\Event\Event;

use Carbon\Carbon;

use Events\CalendarProcessor;
use Events\EventsProcessor;

/**
 * Grav Events
 *
 * The Events Plugin provides Event Listings and Calendars for your Grav
 * powered website. This plugin searches each page for `event:` frontmatter
 * and then sets a custom taxonomy named *type* to *event*. It also sets
 * a repeating and frequency taxonomy to build more intricate collections.
 * The `event_repeat` taxonomy will take a string in the format `MTWRFSU` and
 * the `event_freq` taxonomy will take `daily, weekly, monthly, or yearly`.
 * These taxonomies are automatically added and processed by the plugin.
 *
 * Below is a sample of what an `event:` front matter section would look like in
 * a Grav page. Note: You can used the event template and yaml included in the
 * plugin for use in the admin plugin or add `event:` frontmatter to any page of
 * your choice. This plugin is smart enough to add any page to `@taxonomy.type`
 * as an event so you can build collections off of pages taxonomized with the
 * __event__ taxonomy type.
 *
 * ```
 * event:
 *  	start: 01/01/2015 6:00pm
 *   	end: 01/01/2015 7:00pm
 *    	repeat: MTWRFSU
 *    	freq: weekly
 *    	until: 01/01/2020
 *    	location: Raleigh, NC
 *    	coordinates: 35.7795897, -78.6381787
 * ```
 *
 * If you use the Admin pluin, the events plugin will automatically geo-decode
 * the location field to a set of coordinates so that you don't have too.
 *
 * PHP version 5.6+
 *
 * @package    Events
 * @author     Kaleb Heitzman <kalebheitzman@gmail.com>
 * @copyright  2016 Kaleb Heitzman
 * @license    https://opensource.org/licenses/MIT MIT
 * @version    1.0.15 Major Refactor
 * @link       https://github.com/kalebheitzman/grav-plugin-events
 * @since      1.0.0 Initial Release
 *
 * @todo 				Implement Date Formats
 * @todo 				Implement ICS Feeds
 * @todo 				Implement All Day Option
 */
class EventsPlugin extends Plugin
{
	/**
	 * Current Carbon DateTime
	 *
	 * @since  1.0.0 Initial Release
	 * @var object Carbon DateTime
	 */
	protected $now;

	/**
	 * Events/Events Class
	 *
	 * Processes pages for `event:` frontmatter and then inserts repeating and
	 * reoccuring events into Grav Pages with updated dates, route, and path.
	 *
	 * @since  1.0.0 Initial Release
	 * @var object Events
	 */
	protected $events;

	/**
	 * Events/Calendar Class
	 *
	 * Provides data to be used in the `calendar.html.twig` template.
	 *
	 * @since  1.0.0 Initial Release
	 * @var object Calendar
	 */
	protected $calendar;

	/**
	 * Get Subscribed Events
	 *
	 * @since  1.0.0 Initial Release
	 * @return array
	 */
	public static function getSubscribedEvents()
	{
		return [
			'onPluginsInitialized' => ['onPluginsInitialized', 0],
			'onGetPageTemplates'   => ['onGetPageTemplates', 0],
		];
	}

	/**
	 * Initialize plugin configuration
	 *
	 * Determine if the plugin should run and set the custom
	 * taxonomies to store event information in. We also initialize the Events
	 * and Calendar class that this plugin utilizes and then we start
	 * intercepting Grav hooks to build our events list and insert any vars
	 * we need into the system.
	 *
	 * @since  1.0.0 Initial Release
	 * @return  void
	 */
	public function onPluginsInitialized()
	{
		// Nothing else is needed for admin so close it out
		if ( $this->isAdmin() ) {

			$this->enable([
				'onAdminSave' => ['onAdminSave', 0],
			]);

			return;
		}

		// Add these to taxonomy for events management
		$event_taxonomies = array('type', 'event_freq', 'event_repeat', 'event_location');
		$taxonomy_config = array_merge((array)$this->config->get('site.taxonomies'), $event_taxonomies);
		$this->config->set('site.taxonomies', $taxonomy_config);

		// get the current datetime with c
		$this->now = Carbon::now();

		// set the calendar accessor
		$this->calendar = new \Events\CalendarProcessor();

		// set the events accessor
		$this->events = new \Events\EventsProcessor();

		// enable the following hooks
		$this->enable([
			'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onPagesInitialized' => ['onPagesInitialized', 0],
			'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
		]);
	}

	/**
	 * Add current directory to twig lookup paths.
	 *
	 * Add the templates directory to the twig directory look up path so we
	 * can load our page templates. These are overridable by the theme and
	 * are only meant as a starting point.
	 *
	 * @since  1.0.0 Initial Release
	 * @return void
	 */
	public function onTwigTemplatePaths()
	{
		// add templates to twig path
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
	}

	/**
	 * Add repeating and reoccuring events as Grav pages
	 *
	 * Repeating Events: events the tile horizontally in a week `MTWRFSU`
	 * Reoccuring Events: events that tile vertically through `daily, weekly,
	 * monthly, yearly`
	 *
	 * The Events/Events class searches for pages with `event:` frontmatter and
	 * processes these into new Grav pages as needed. This is a dynamic operation
	 * and does not add new physical pages to the filesystem.
	 *
	 * @since  1.0.0 Initial Release
	 * @return  void
	 */
	public function onPagesInitialized()
	{
		// get instances of all events
		$pages = $this->events->all();
	}

	/**
	 * Association with page templates
	 *
	 * @param	 Event Event
	 * @since  1.0.15 Major Refactor
	 * @return void
	 */
	public function onGetPageTemplates(Event $event)
	{
		$types = $event->types;

    /* @var Locator $locator */
    $locator = Grav::instance()['locator'];

    // Set blueprints & templates.
    $types->scanBlueprints($locator->findResource('plugin://events/blueprints'));
    $types->scanTemplates($locator->findResource('plugin://events/templates'));

    // reverse the FUBARd order of blueprints
    $event = array_reverse($types['event']);
    $types['event'] = $event;
	}

	/**
	 * Set needed variables to display events
	 *
	 * For the calendar page, we load the appropriate js and css to make the
	 * calendar work smoothly as well as add the appropriate calendar twig
	 * variables.
	 *
	 * @since  1.0.0 Initial Release
	 * @return  void
	 */
	public function onTwigSiteVariables()
	{
		// setup
		$page = 			$this->grav['page'];
		$pages = 			$this->grav['pages'];
		$collection = $pages->all()->ofType('event');
		$twig = 			$this->grav['twig'];
		$assets = 		$this->grav['assets'];

		// only load the vars if calendar page
		if ($page->template() == 'calendar')
		{
			$yearParam = $this->grav['uri']->param('year');
			$monthParam = $this->grav['uri']->param('month');

			$twigVars = $this->calendar->twigVars($yearParam, $monthParam);
			$calVars = $this->calendar->calendarVars($collection);

			// add calendar to twig as calendar
			$twigVars['calendar']['events'] = $calVars;
			$twig->twig_vars['calendar'] = array_shift($twigVars);
		}

		// scripts
		$js = 'plugin://events/assets/events.js';
		$assets->add('jquery');
		$assets->addJs($js);

		// styles
		$css = 'plugin://events/assets/events.css';
		$assets->addCss($css);

	}

	/**
	 * Process Event Information
	 *
	 * This hook fires a reverse geocoding hook for the location field
	 * on single events.
	 *
	 * @param  Event  $event
	 * @since  1.0.15 Location Field Update
	 * @return void
	 */
	public function onAdminSave(Event $event)
  {
		// get the ojbect being saved
  	$obj = $event['object'];

		// check to see if the object is a `Page` with template `event`
    if ($obj instanceof Page &&  $obj->template() == 'event' ) {

			// get the header
			$header = $obj->header();

			// check for location information
    	if ( isset( $header->event['location'] ) && ! isset( $header->event['coordinates'] ) ) {
	    	$location = $header->event['location'];

	    	// build a url
	    	$url = "http://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($location);

	    	// fetch the results
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				$geoloc = json_decode(curl_exec($ch), true);

				// build the coord string
				$lat = $geoloc['results'][0]['geometry']['location']['lat'];
				$lng = $geoloc['results'][0]['geometry']['location']['lng'];
				$coords = $lat . ", " . $lng;

				// set the header info
				$header->event['coordinates'] = $coords;
				$obj->header($header);
    	}
    }
  }
}
