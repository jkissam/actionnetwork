# Action Network Wordpress Plugin

A free Wordpress plugin for the [Action Network](https://actionnetwork.org) online organizing tools, developed by [Jonathan Kissam](http://jonathankissam.com/).

Latest version available on the [Wordpress.org plugin repository](https://wordpress.org/plugins/wp-action-network/).

Features:
* Create a Wordpress shortcode from any Action Network embed code.
* Manage your saved embed codes using the Wordpress backend. Supports sorting by title, type and last modified date, and provides a search function.
* Use `[actionnetwork_calendar]` shortcode and Action Network Calendar widget to show a list of upcoming events. Optionally outputs upcoming events in JSON. Development of this feature was supported by [The People's Lobby](http://www.thepeopleslobbyusa.org/) - if you like it, please consider [making a donation to them](https://actionnetwork.org/fundraising/donate-to-the-peoples-lobby).
* If you are an [Action Network Partner](https://actionnetwork.org/partnerships), use your API key to sync all of your actions from Action Network to Wordpress.
* Create signup widgets which allow visitors to your site to sign up for your email list _without_ using Action Network javascript embeds. This allows you to place a signup form on every page (for example in the sidebar), and still load Action Network embed codes for actions on particular pages (since Action Network's scripts will only load one embed code per page).  This feature does require the API key, so you have to be an [Action Network Partner](https://actionnetwork.org/partnerships) to use it.

Find this plugin useful? Please consider supporting further development by [hiring me or making a donation](http://jonathankissam.com/support).

## Updates

_6/29/2017: Now available on the Wordpress plugin repository!_

_6/28/2017: version 1.0_

Finally, we're ready to release a 1.0 version! Submitted to the Wordpress Directory, at which point documentation will be moved there.

_4/9/2017: version 1.0-beta7_

New feature:
* json="1" attribute in `[actionnetwork_calendar]` shortcode will output upcoming events as a JSON object called actionNetworkEvents which can then be used by local javascript.

_4/3/2017: version 1.0-beta6_

New features:
* Add/edit the _time_ as well as date for events added via the text interface

Bug fixes:
* Prevent Actionnetwork_Sync::processQueue from nesting too many times
* remove extraneous `return $full_simple_collection` line from `traverseFullCollection` method in actionnetwork.class.php that was generating notices

_3/29/2017: version 1.0-beta5_

New features:
* Upcoming events shortcode & widget
* Ability to add non-Action-Network events to upcoming events
* loads description field and location hash (for events) into database
* Validates API key
* Starts sync as soon as a valid API key is entered

And some miscellaneous bug fixes, including:
* Fundraising embed codes given the correct OSDI type

_10/6/2016: version 1.0-beta2_

Includes a few minor improvements to the beta version:
* terminology updated to reflect Action Network's terms rather than OSDI's (i.e., "Letters" rather than "Advocacy Campaigns"
* sync process streamlined somewhat

Also, added inline documentation for shortcode options, and drop-down help pages.

_10/3/2016: version 1.0-beta_

__The Action Network Plugin is ready for beta testing!__

If you download the beta version of the plugin, please [sign up for my email list](http://eepurl.com/cabLYT) to be notified when updates are ready.

Please [contact me](http://jonathankissam.com/about#contact) with bug reports, feedback, thoughts or feature requests
