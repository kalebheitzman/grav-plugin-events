<?php
namespace Grav\Plugin;

class EventEntry
{
	public $title;
	public $route;
	public $start; 	// start date of event
	public $end; 	// end date of event 
	public $repeat;	// what days to repeat the event using MTWRFSU
	public $freq;	// how often to repeat the event
	public $until;	// when to quit repeating the event
}