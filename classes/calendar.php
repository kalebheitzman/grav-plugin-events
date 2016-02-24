<?php

namespace Events;

// import classes
require_once __DIR__.'/../vendor/autoload.php';

use Carbon\Carbon;

/**
 * Events Plugin Calendar Class
 */
class Calendar
{
	/**
	 * Twig Calendar Vars
	 * @param  object $collection Grav Collection
	 * @return array              Twig Array
	 */
	public function calendarVars($collection)
	{
		// build a calendar array to use in twig
		$calendar = array();
		foreach($collection as $event) {
			$header = $event->header();
			$start = $header->event['start'];
			
			// build dates to create an associate array
			$carbonStart = Carbon::parse($start);
			$year = $carbonStart->year;
 			$month = $carbonStart->month;
 			$day = $carbonStart->day;

 			$eventItem = $event->toArray();
 			$eventItem['header']['url'] = $event->url();

 			// add the event to the calendar
 			$calendar[$year][$month][$day][] = $eventItem;
		}

		return $calendar;
	}	

	public function twigVars($yearParam, $monthParam)
	{
		if ( $yearParam === false ) {
			$yearParam = date('Y');
		}

		if ( $monthParam === false ) {
			$monthParam = date('m');
		}

		$monthYearString = "${yearParam}-${monthParam}-01";
		$carbonMonthYear = Carbon::parse($monthYearString);
		
		// add vars for use in the calendar twig var
		$twigVars['calendar']['daysInMonth'] = $carbonMonthYear->daysInMonth;
		$twigVars['calendar']['currentDay'] = date('d');

		// current dates
		$twigVars['calendar']['date'] = $carbonMonthYear->timestamp;
		$twigVars['calendar']['year'] = $carbonMonthYear->year;
		$twigVars['calendar']['month'] = $carbonMonthYear->month;
		$twigVars['calendar']['day'] = $carbonMonthYear->day;

		// next dates
		$nextMonth = $carbonMonthYear->copy()->addMonth();
		$twigVars['calendar']['next']['date'] = $nextMonth->timestamp;
		
		// prev dates
		$prevMonth = $carbonMonthYear->copy()->subMonth();
		$twigVars['calendar']['prev']['date'] = $prevMonth->timestamp;
		
		// years
		$nextYear = $carbonMonthYear->copy()->addYear();
		$prevYear = $carbonMonthYear->copy()->subYear();
		$twigVars['calendar']['prevYear'] = $prevYear;
		$twigVars['calendar']['nextYear'] = $nextYear;

		return $twigVars;
	}	
}