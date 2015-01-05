<?php
namespace Grav\Plugin;

use \Grav\Common\Plugin;

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

		$this->enable([
			'onTwigSiteVariables' => ['onTwigSiteVariables', 0]
		]);

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