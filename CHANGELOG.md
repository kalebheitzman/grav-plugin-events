# v1.0.5
## 02/29/2016

1 [](#new)
	* Added code documentation via phpdoc. These can be found under the /docs folder.
1 [](#bugfixes)
	* Updated changelog to work on Grav Website

# v1.0.4
## 02/28/2016

1 [](#new)
	* Refactored code into events and calendar classes
	* Added phpdoc based docs under the docs folder
2 [](#improved)
	* When generating a large number of events, page load speeds would drastically slow down. That has been improved to roughly 100ms on PHP 7 and 160ms on PHP 5.6
	* Instead of using an epoch string in the url to generate date times, we use a unique 6 digit token and reference event date information via the 
	token.
3 [](#bugfixes) 
	* There were several repeating and frequency date issues that have now been resolved. Please update to 1.0.4 to ensure you don't run into these issues.

# v1.0.3
## 02/24/2016

1 [](#bugfixes)
	* Fixed major fatal error when events don't exist

# v1.0.2
## 02/24/2016

1 [](#new)
	* Added calendar controls
2 [](#improved)
	* Updated readme documentation
3 [](#bugfixes)
	* Fixed repeating issues
	* Fixed frequency issues

# v1.0.1
## 02/23/2016

1 [](#new)
	* Added calendar view with previous and next month navigation

# v1.0.0
## 02/22/2016

1. [](#new)
    * ChangeLog started...