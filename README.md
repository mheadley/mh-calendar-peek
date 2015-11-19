MH Calendar Peek plugin
=================
A simple calendar integration plugin for visually (colorwise) showing how busy your day is based on events on your calendar. Simply install plugin and activate it.  Go to settings page, and place in your google calendar url.  It is expecting an ics file, it can work for other end points as well, as long as the endpoint is sent in ics format.  Based off an idea for a freelancer to have ~realtime~ update and (visualization of this) on his website of events on his calendar that would prevent him from booking appointments for the next week.

To display anywhere in your theme use:

```php

mh_calendar_peek_plugin_template_display();

```

Fork it and have fun with it if you like the simplicity and the idea.

##Requirements
WordPress 3.2+
(PHP 5.1.6 as well)

##TODO:
* error cases clearer; tried my best to mitigate those with the cache
* Repeat rules (this is large)
* more efficient way to parsing individual event and thus rrule too
