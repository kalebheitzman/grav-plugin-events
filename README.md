# Events Plugin for Grav CMS

[![Join the chat at https://gitter.im/grav-plugin-events/Lobby](https://badges.gitter.im/grav-plugin-events/Lobby.svg)](https://gitter.im/grav-plugin-events/Lobby?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![Build Status](https://travis-ci.org/kalebheitzman/grav-plugin-events.svg?branch=develop)](https://travis-ci.org/kalebheitzman/grav-plugin-events)

This is an events plugin for [Grav CMS](http://getgrav.org)  1.1.5+.

Create single and repeating events using `event:` frontmatter on any page you choose. The Events Plugin provides templates for calendar, events, and event views and supports creating events via the [Admin plugin.](https://github.com/getgrav/grav-plugin-admin)

View our related
[Pages Repo](https://github.com/kalebheitzman/grav-pages-events) to see how Calendar, Events, and Event pages are properly created.

### Installation

From the root of your Grav install.

```
$ bin/gpm install events
```

You can also install this plugin from the Admin plugin using the plugins search feature.

### Translations

Date translations can be enabled by setting `date_format.translate` to true in the config. They use the official [Grav translation files](https://github.com/getgrav/grav/tree/develop/system/languages), so if your language is missing, don't hesitate to contribute upstream.

### Taxonomy

**Events** creates a few different taxonomy types and adds them programatically to Grav. You do not need to specify them in a config file as they are automatically inserted into the system based off of event frontmatter on event pages.

Currently, the following taxonomies are created and available for building page collections:

`'@taxonomy.event_repeat'` with params `[M,T,W,R,F,S,U]` When using this taxonomy, be sure to separate your repeat rules with a comma. When adding them to `event.repeat: MTWRFSU` do not separate the rules with a comma.

`'@taxonomy.event_freq'` with params `[daily, weekly, monthly, yearly]`.

`'@taxonomy.type'` with param `'event'`.

### How it works

**Events** parses all of your markdown files for `event:` frontmatter and then automagically assigns taxonomies to your events based on whether they repeat through the week and through what intervals. This lets you build powerful collections based on the `event_freq:` and `event_repeat:` intervals. This lets you create custom displays. For example, if you want to build a list of all events that happen on Mondays you can filter on `'@taxonomy.event_repeat':['M']` or pull out your Weekly events by filtering on `'@taxonomy.event_freq':'weekly'`.

It also adds any page found with event frontmatter to `''@taxonomy.type': 'event'`. This allows you to build collections based on this taxonomy type.

The `date:` of a page will be set to `event.start:` automatically if not specified. This allows you to order your events by date using the `order:` paramater for collections.

If the event is a repeating event, pages will be added to the pages collection with the correct dates and times for use throughout the rest of a Grav site. Each virtual page is given a unique path with a 6 character tokenized suffix. The URL generated is safe to copy and paste on other sites and will go back to the specific reoccurring or repeating date of the event.

### Specifying dates and times

The `event.start:` and `event.end:` dates can be specified using `m/d/y` or `d-m-y` formats along with times.

### Repeating dates

This plugin supports creating repeating events using `event.repeat:`,
`event.freq:`, and `event.until:`.

`event.repeat:` specifies what days you would like for your event to repeat horizontally across a calendar. This can be for Monday through Sunday as specified by `MTWRFSU`. (**M**onday, **T**uesday, **W**ednesday, Thu**R**sday, **F**riday, **S**aturday, S**U**nday).

`event.freq:` can be set to `daily, weekly, monthly, or yearly.`

`event.until:` is a date and time specification like `01/01/2016 12:00am`

### Event frontmatter example

You can edit the front matter of your pages or use the Admin plugin with the supplied blueprints to update event information. If you use the Admin plugin, coordinates will automatically be geo-decoded from address that you enter.

```twig
event:
    start: '01/01/2015 6:00pm'
    end: '01/01/2015 7:00pm'
    repeat: 'MTWRFSU'
    freq: weekly
    until: '01/01/2020'
    location: 'Raleigh, NC'
    coordinates: '35.7795897, -78.6381787'
```

### Collection frontmatter examples

A collection of weekend events.

```yaml
collection:
    @items:
        @taxonomy.type: 'event'
        @taxonomy.event_repeat: [S, U]
```

A collection of weekly events.

```yaml
collection:
  @items:
    @taxonomy.type: 'event'
    @taxonomy.event_freq: 'weekly'
```

A collection of featured events.

```yaml
collection:
  @items:
    @taxonomy.type: 'event'
    @taxonomy.category: [ featured ]
```

### Collection twig examples

A collection of weekend events with a _dateRange_ using the [DateTools Plugin.](https://github.com/kalebheitzman/grav-plugin-datetools) Note the use of limit for pagination. This plugin uses Grav's [Pagination Plugin](https://github.com/getgrav/grav-plugin-pagination) automatically if it is enabled.

```
{% set events =
  page.collection({
    'items': {
      '@taxonomy': {
        'type': 'event',
        'event_repeat': ['S','U']
      }
    },
    'dateRange': {
      'start': datetools.startOfWeek,
      'end': datetools.endOfWeek
    },
    'order': {
      'by': 'date',
      'dir': 'asc'
    },
    'limit': 10
  })
%}

<ul>
    {% for event in events %}
        <li>
            <a href="{{ event.url }}">{{ event.title }}</a>
            <time class="dt-start" datetime="{{ event.header.event.start|date('c') }}">
              {{ event.header.event.start|date('F j, Y') }}
            </time>
        </li>
    {% endfor %}
</ul>
```

### DateTools Plugin

Be sure to checkout the [DateTools Plugin](https://github.com/kalebheitzman/grav-plugin-datetools). This plugin uses DateTools to filter date ranges on collection in twig templates.
