<?php

namespace Grav\Plugin;

use \Grav\Common\Plugin;

class EventsPlugin extends Plugin
{
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

		$this->enable([
			'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
			'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
		]);

	}

	/**
	 * Add current direcotry to twig lookup paths.
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
		require_once __DIR__ . '/classes/events.php';

		$twig = $this->grav['twig'];
		$twig->twig_vars['events'] = new Events();
	} 

}