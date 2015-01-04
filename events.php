<?php

namespace Grav\Plugin;

use \Grav\Common\Plugin;
use \Grav\Common\Grav;
use \Grav\Common\Cache;
use \Grav\Common\Debugger;
use \Grav\Common\Config\Config;
use \Grav\Common\Page\Page;
use \Grav\Common\Page\Pages;
use RocketTheme\Toolbox\Event\Event;

class EventsPlugin extends Plugin
{
	/** @var Array $events */
	protected $events = [];

	/** @var Config $config */
	protected $config;

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

		$this->config = $this->grav['config']->get('plugins.events');

		// get route that events should run on 
		//$uri = $this->grav['uri'];
		//$route = $this->config->get('plugins.events.route');

		// E.g only active on route defined in events.yaml
		//if ($route && $route == $uri->path()) {
		$this->enable([
			'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onPagesInitialized' => ['onPagesInitialized', 0],
			'onPageInitialized' => ['onPageInitialized', 0],
			'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
		]);
		//}
	}

	/**
	 *	 
	 */
	public function onPagesInitialized()
	{
		require_once __DIR__ . '/classes/evententry.php';

		/** @var Pages $pages */
		$pages = $this->grav['pages'];
		$routes = $pages->routes();
		ksort($routes);

		foreach($routes as $route => $path) {
			$page = $pages->get($path);

			if ($page->routable()) {

				$header = $page->header();

				/*
				 *	If the page has event frontmatter then store it
				 */
				if (isset($header->event)) {
					$entry = new EventEntry();
					$entry->title = $header->title;
					$entry->url = $route;
					$entry->start = isset($header->event['start']) ? $header->event['start'] : null;
					$entry->end = isset($header->event['end']) ? $header->event['end'] : null;
					$entry->repeat = isset($header->event['repeat']) ? $header->event['repeat'] : null;
					$entry->freq = isset($header->event['freq']) ? $header->event['freq'] : null;				
					$entry->until = isset($header->event['until']) ? $header->event['until'] : null;

					$this->events[] = $entry;
				}

			}

		}

	}

	public function onPageInitialized()
	{

	}

	/**
	 * Add current directory to twig lookup paths.
	 */
	public function onTwigTemplatePaths()
	{
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
	} 

	/**
	 * Set needed variables to display events
	 */
	public function onTwigSiteVariables()
	{
		$twig = $this->grav['twig'];
		$twig->twig_vars['events'] = $this->events;
	} 

	public function findEvents($events)
	{
		var_dump($events);
		var_dump($this->events);
	}

}