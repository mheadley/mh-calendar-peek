MH Calendar Peek plugin
=================
A simple calendar integration plugin for visually (colorwise) showing how busy your day is based on events on your calendar. Simply install plugin and activate it.  Go to settings page, and place in your google calendar url.  It is expecting an ics file, it can work for other end points (APIs) as well, as long as the endpoint or file returned is sent in ics format.  Based off an idea for a freelancer to have ~realtime~ update and (visualization of this) on his website of events on his calendar that would prevent him from booking appointments for the next week, 5 days, 14 days or even 28 days.

To display anywhere in your theme use:

```php

mh_calendar_peek_plugin_template_display();

```

Screenshot
----------
![Screen shot](/images/screenshot.png?raw=true "Screen Shot")

Fork it and have fun with it if you like the simplicity and the idea.

##Requirements
WordPress 3.2+
(PHP 5.1.6 as well)

##TODO:
* filter through "busy" and "free" settings on ical event
* Repeat rules tighten up edge cases for weird repeat rule cases I didn't plan for
* less memory intensive way of filtering year rrules
* support for more than one calendar feed maybe?
