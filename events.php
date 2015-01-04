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
		]);
		//}
	}

	/**
	 * Add current directory to twig lookup paths.
	 */
	public function onTwigTemplatePaths()
	{
		$this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
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
					$entry->start_date = isset($header->event['start_date']) ? $header->event['start_date'] : null;
					$entry->end_date = isset($header->event['end_date']) ? $header->event['end_date'] : null;
					$entry->repeat = isset($header->event['repeat']) ? $header->event['repeat'] : null;
					$entry->rules = isset($header->event['rules']) ? $header->event['rules'] : null;				

					$this->events[] = $entry;
				}

			}

		}

	}

	public function onPageInitialized()
	{
		var_dump($this->events);
	}


}