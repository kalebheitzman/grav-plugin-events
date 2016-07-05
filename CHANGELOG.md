# v1.0.10
## 07/04/2016
1. [](#bugfix)
	* Bumped version number

# v1.0.9
## 07/04/2016
1. [](#improved)
	* Added start and end times to calendar template
	* Added demo link that points to the start for this calendar (advance forward to see new events, etc)
	* Added a link to a github repo to see Event configuration under the user/pages Grav directory
1. [](#bugfix)
	* Issue #13 - Wrong link in read me for demo site.
	* Issue #15 - Current day in current month only (not multiple months)
	* Issue #16 - Update for Grav 1.1 fixed with 1.0.4 DateTools plugin

# v1.0.8
## 03/15/2016

1. [](#bugfix)
	* Issue #8 - Fixed unset arrays causing fatal error

# v1.0.7
## 03/14/2016

1. [](#improved)
	* Templates now reflect default Grav Antimatter Theme
1. [](#bugfix)
	* Issue #7 - Event repeating once a week not rendedered correctly
	* Issue #6 - Media now being displayed with each dynamic event

# v1.0.6
## 03/05/2016

1. [](#new)
	* Templates now display default tag and category taxonomy type as links.
1. [](#improved)
	* Default templates updated
	* Page load times have been decreased from ~250ms to ~90ms on PHP7.
1. [](#bugfix)
	* Issue #4 - Fixed repeating rule display from MTWRFSU to full Monday, Tuesday, etc in templates.
	* Fixed singular repeating display rule in templates.

# v1.0.5
## 02/29/2016

1. [](#new)
	* Added detailed code documentation via phpdoc. These can be found under the /docs folder.
1. [](#bugfix)
	* Updated changelog to work on Grav Website

# v1.0.4
## 02/28/2016

1. [](#new)
	* Refactored code into events and calendar classes
	* Added phpdoc based docs under the docs folder
1. [](#improved)
	* When generating a large number of events, page load speeds would drastically slow down. That has been improved to roughly 100ms on PHP 7 and 160ms on PHP 5.6
	* Instead of using an epoch string in the url to generate date times, we use a unique 6 digit token and reference event date information via the
	token.
1. [](#bugfix)
	* There were several repeating and frequency date issues that have now been resolved. Please update to 1.0.4 to ensure you don't run into these issues.

# v1.0.3
## 02/24/2016

1. [](#bugfix)
	* Fixed major fatal error when events don't exist

# v1.0.2
## 02/24/2016

1. [](#new)
	* Added calendar controls
1. [](#improved)
	* Updated readme documentation
1. [](#bugfix)
	* Fixed repeating issues
	* Fixed frequency issues

# v1.0.1
## 02/23/2016

1. [](#new)
	* Added calendar view with previous and next month navigation
1. [](#bugfix)
	* Issue #2 - Fixed Changelog format

# v1.0.0
## 02/22/2016

1. [](#new)
    * ChangeLog started...
