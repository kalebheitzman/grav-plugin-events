# Events Plugin for Grav CMS

This is an events plugin that works with [Grav CMS](http://getgrav.org)  1.0.10+. You can create single and repeating events using `event` frontmatter on any page you choose. The Events Plugin provides templates for both listing and full calendar views. *Be sure to copy over the yaml files in the blueprints folder so you can edit events in your admin section.*

[View the demo](https://grav.brandr.co/calendar/year:2016/month:03) *Sidenote: the demo is running the development version of this plugin. From time to time you may see features that haven't been released yet.*

Also, check out this [Repo](https://github.com/kalebheitzman/grav-brandr-pages) of proper page setup for Calendar, Events, and Event.

### Installation

From the root of your Grav install.

```
$ bin/gpm install events
```

### Todo

Blueprints and Templates are included in this plugin for the Frontend and Admin side of things. I don't know how to include them yet so please feel free to copy them over to your theme (or symlink them to keep up-to-date with Event Plugin updates).

### How it works

**Events** parses all of your markdown files for event frontmatter and then
automagically assigns taxonomies to your events based on whether they repeat
through the week and through what intervals. This lets you build powerful
collections based on the `event_freq` and `event_repeat` intervals. This lets
you create custom displays. Forexample, if you want to build a list of all
events that happen on Mondays you can filter on `'@taxonomy.event_repeat':['M']`
or pull out your Weekly events by filtering on
`'@taxonomy.event_freq':'weekly'`.

This plugin processes event frontmatter specified in the header in multiple
ways. It adds any page found with event frontmatter to `@taxonomy.type = event`.
This allows you to build collections based on this taxonomy type. The Taxonomy
`type` is added dynamically to your Grav install.

The `date` of a page will be set to `event.start` automatically if not specified. This allows you to order your events by date.

If the event is a repeating event, pages will be added to the pages collection with the correct dates and times for use throughout the rest of a Grav site. Currently, repeating pages use the same page slug with an epoch suffix related to the start date of the next event.

### Dates and times

The `event.start` and `event.end` dates can be specified using `m/d/y` or `d-m-y` formats along with times.

### Repeating dates

This plugin supports creating repeating events using `event.repeat`,
`event.freq`, and `event.until`.

`event.repeat` specifies what days you would like for your event to repeat. This can be for Monday through Sunday as specified by MTWRFSU. (**M**onday, **T**uesday, **W**ednesday, Th**U**rsday, **F**riday, **S**aturday, S**U**nday)

`event.freq` can be set to `daily, weekly, monthly, or yearly.`

`event.until` is a date and time specification like `01/01/2016 12:00am`

### Event frontmatter example

You can edit the front matter of your pages or use the Admin plugin with the supplied blueprints to update event information.

```
event:
    start: 01/01/2015 6:00pm
    end: 01/01/2015 7:00pm
    repeat: MTWRFSU
    freq: weekly
    until: 01/01/2020
```

### Collection frontmatter example

A collection of weekend events.

```
collection:
    @items:
        @taxonomy.type: event
        @taxonomy.event_repeat: [S, U]
```

### Twig templates and example

It's easy to create a collection of events using Grav taxonomy search feature and the following taxonomies that are added by the Events plugin.

`@taxonomy.type` and the term `event` are added to all pages that have `event` frontmatter.

`@taxonomy.event_repeat` and `['M', 'T', 'W', 'R', 'F', 'S', 'U']` are added to events that specify `event.repeat: MTWRFSU`.

`@taxonomy.event_freq` and `daily, weekly, monthly, or yearly` are added to events that specify `event.freq` and the appropriate option.

A collection of weekend events.

```
{% set events =
    page.collection({
        'items':{
            '@taxonomy.type':'event',
            '@taxonomy.event_repeat':['S','U']
        }
    })
    .dateRange(datetools.startOfMonth, datetools.endOfMonth)
    .order('date', 'asc')
%}

<ul>
    {% for event in events %}
        <li>
            <a href="{{ event.url }}">{{ event.title }}</a>
            {{ event.header.event.start|date('F j, Y') }}
        </li>
    {% endfor %}
</ul>
```

### DateTools Plugin

Be sure to checkout the [DateTools Plugin](https://github.com/kalebheitzman/grav-plugin-datetools). It will supercharge your dateRange filters.
