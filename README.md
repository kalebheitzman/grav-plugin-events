# Events plugin for Grav CMS

This is an events plugin that works with [Grav CMS](http://getgrav.org)  0.9.13+.

## Frontmatter example

```
event:
    start: 01/01/2015 6:00pm
    end: 01/01/2015 7:00pm
    repeat: MTWRFSU
    freq: weekly
    until: 01/01/2020
```

### Dates and Times

The `event.start` and `event.end` dates looks for the American 01/01/2015 12:00am format.

### Repeating Dates

This plugin supports creating repeating events using `event.repeat`, `event.freq`, and `event.until`. 

`event.repeat` specifies what days you would like for your event to repeat. This can be for Monday through Sunday as specified by MTWRFSU. 

**M**onday, **T**uesday, **W**ednesday, Th**U**rsday, **F**riday, **S**aturday, S**U**nday

`event.freq` can be set to daily, weekly, monthly, or yearly.

`event.until` is a date and time specification like 01/01/2016 12:00am
