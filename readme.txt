=== Plugin Name ===
Contributors: jonathankissam
Donate link: http://jonathankissam.com/support/
Tags: signup, events, action network, online organizing
Requires at least: 4.6
Tested up to: 4.8
Stable tag: trunk
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl.html

Provides Action Network (actionnetwork.org) action embed codes as shortcodes and a calendar and signup widget

== Description ==

A free Wordpress plugin for the [Action Network](https://actionnetwork.org) online organizing tools, developed by [Jonathan Kissam](http://jonathankissam.com/).

Features:
* Create a Wordpress shortcode from any Action Network embed code.
* Manage your saved embed codes using the Wordpress backend. Supports sorting by title, type and last modified date, and provides a search function.
* Use `[actionnetwork_calendar]` shortcode and Action Network Calendar widget to show a list of upcoming events. Optionally outputs upcoming events in JSON. Development of this feature was supported by [The People's Lobby](http://www.thepeopleslobbyusa.org/) - if you like it, please consider [making a donation to them](https://actionnetwork.org/fundraising/donate-to-the-peoples-lobby).
* If you are an [Action Network Partner](https://actionnetwork.org/partnerships), use your API key to sync all of your actions from Action Network to Wordpress.
* Create signup widgets which allow visitors to your site to sign up for your email list _without_ using Action Network javascript embeds. This allows you to place a signup form on every page (for example in the sidebar), and still load Action Network embed codes for actions on particular pages (since Action Network's scripts will only load one embed code per page).  This feature does require the API key, so you have to be an [Action Network Partner](https://actionnetwork.org/partnerships) to use it.

Find this plugin useful? Please consider supporting further development by [hiring me or making a donation](http://jonathankissam.com/support).

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/plugin-name` directory, or install the plugin through the WordPress plugins screen directly.
1. Activate the plugin through the 'Plugins' screen in WordPress
1. If you have an [Action Network API Key](https://actionnetwork.org/partnerships), go to the Action Network section and click on the "Settings" tab to enter your API key. Your actions will automatically be synced from Action Network to your Wordpress site.

== Frequently Asked Questions ==

= My ticketed events aren't showing up on the list =

Action Network does not currently provide access to Ticketed Events through its API. You can, however, use the "Add Action" tab and copy and paste the embed code manually.

Answer to foo bar dilemma.

== Screenshots ==

1. This screen shot description corresponds to screenshot-1.(png|jpg|jpeg|gif). Note that the screenshot is taken from
the /assets directory or the directory that contains the stable readme.txt (tags or trunk). Screenshots in the /assets
directory take precedence. For example, `/assets/screenshot-1.png` would win over `/tags/4.3/screenshot-1.png`
(or jpg, jpeg, gif).
2. This is the second screen shot

== Changelog ==

= 1.0 =
* First release on wordpress.org

Previous development versions can be found on 

== Upgrade Notice ==

= 1.0 =
Install from wordpress.org to stay up-to-date


