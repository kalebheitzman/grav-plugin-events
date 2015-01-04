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
		// get a listing of all pages
		$pages = $this->grav['pages']->instances();
		
		// find pages that have event frontmatter
		foreach($pages as $page) {
			$header = $page->header();
			// page has event frontmatter
			if (isset($header->event)) {
				$this->events[] = $header;
			}
		}
	}

	public function onPageInitialized()
	{
		

	}

	public function find($filters = array())
	{
		$events = $this->events;

		var_dump($this->events);
	}

}