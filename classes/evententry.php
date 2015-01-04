<?php
namespace Grav\Plugin;

class EventEntry
{
	public $title;
	public $route;
	public $start = null; 		// start date of event
	public $end = null; 		// end date of event 
	public $start_date = null;	// generated from start
	public $end_date = null; 	// generated from end 
	public $start_time = null; 	// generated from start
	public $end_time = null; 	// generated from end 	
	public $repeat = null;		// what days to repeat the event using MTWRFSU
	public $freq = null;		// how often to repeat the event
	public $until = null;		// when to quit repeating the event

}