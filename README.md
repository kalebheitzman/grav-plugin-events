# Events plugin for Grav CMS

## Example use

```
<ul>
    {% for event in events.findEvents({'freq':[null, 'monthly']}).get() %}
        <li>
            <span class="date">{{ event.start|date("M d, g:ia") }}</span>
            <span class="title"><a href="{{ event.route }}">{{ event.title }}</a></span>
        </li>
    {% endfor %}
</ul>
```

## Frontmatter usage

```
event:
    start: 01/01/2015 6:00pm
    end: 01/01/2015 7:00pm
    repeat: MWF
    freq: weekly
    until: 01/01/2020
```

Repeat uses the MTWRFSU format.