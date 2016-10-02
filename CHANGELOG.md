# v1.0.15
## 10/02/2016

1. [](#new)
	* Added a location field with auto geo-decoded coordinates from address
	* New visual styles and templates for calendar and events
	* Calendar shows a modal when clicking on a day so the end user can see every event that day.
	* French language translation has been added
1. [](#improved)
	* Cleaned up plugin blueprint but preserved old options in comments
	* The events processor has been rewritten from the ground up to use Page and Collection objects instead of a custom tokenized array for serving pages.
	* Atoum testing framework has been added to the plugin and I'll be writing tests in the near future.

# v1.0.14
## 09/15/2016

1. [#bugfix]
	* Issue #25 - Variable not initialized throws error in for loop.

# v1.0.13
## 08/19/2016

1. [](#new)
	* Added Events sidebar with events listing
1. [](#bugfix)
	* Issue #21 - Admin form now automatically shows up
	* Fixed event template types in blueprints.
	* Fixed monthly frequency dates.
	* Fixed doubling of events.
	* Fixed repeat rules.
	* Removed uncoded show future events toggle

# v1.0.12
## 08/18/2016

1. [](#new)
	* Issue #24 - Added German translation from @aender6840

# v1.0.11
## 08/12/2016

1. [](#new)
	* [microformats2](http://microformats.org) support
	* Dates are now translated in the `event_item` template, if `events.date_format.translate` setting

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
