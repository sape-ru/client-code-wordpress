# Sape - website monetization
Plugin Name: Sape - website monetization
Plugin URI: https://github.com/sape-ru/client-code-wordpress/releases
Description: Plugin for sape.ru webmaster services integration
Contributors: Sape
Donate link: https://www.sape.ru/
Tags:  sape, seo, link, site, teaser, rtb
Requires at least: 4.2
Tested up to: 6.0.1
Stable tag: trunk
Version: 3.4.2
Author: Sape.ru
Author URI: https://www.sape.ru/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin for automatic installation of the Sape system code on the webmaster's site

Plugin for site monetization from [Sape](https://www.sape.ru/). The plugin supports the following monetization formats:

- Rental Links
- Sidewide rental links
- Contextual rental links
- Teaser blocks (links in teaser format)
- Rental articles
- Display advertising (RTB blocks)


##Installation

**Important!** If you previously set the system code and folders manually, then delete them!

1. In the WordPress admin panel, go to the "Plugins" section and click "Add New".
2. In the search field, enter "sape", find our plugin and click the "Install" button. After installation, click "Activate".
3. After activating the plugin, go to its settings and enter your ID in the "User Key" field.
4. Check off the monetization formats that we will post on the site. Save the settings.
5. After setting, go to the "Outside view" -> "Widgets" section. The final step - you need to place in the right places of your topic (template) the monetization formats that you connected on the previous step. To do this, place widgets, for example, in Sidebar or Footer

### Widget setup
All settings are optional - everything will work with the default settings. Let's consider setting the widget using the example of "Sape: Rental Links" widget

- Set a title (optional)
- Set the number of links that will be displayed in the block (if left empty, all purchased links will be displayed)
- If you want to display links in block format - specify ```Format=Block``` and set how the block will be displayed: vertically or horizontally
- Click "Save" button

## Frequently Asked Questions
FAQ
https://help.sape.ru/sape/faq/1757

## Upgrade Notice
### v 0.01
First versiov don't have upgrade

### v 0.02
Fix Sape Articles Integration

### v 0.03
Fix context back links placement

### v 0.04
Add sharding for links.db file

### v 0.05
Fix multisite mode

### v 0.06
SapeArticles: Work without URL-template and .htaccess config

### v 0.07
Added backward compartibility for articles placement old common mode

### v 0.08
Fix context back links placement
Fix split_data_file mode

### v 0.09
Fix recreating articles
Fix deleting links in split_data_file mode

### v 0.13
Add and update translation

### v 0.14
Upgrade translation

### v 0.15
Change readme text

### v 0.16
fix bugs

### v 3.4
Compatible with Word Press 6.0
The ability to display verification code was added

### v 3.4.1
Interface edits

### v 3.4.2
Translate edits

## Changelog
### v 0.01
First version

### v 0.02
Change SAPE_USER.php generation

### v 0.03
Change SAPE_context::replace_in_text_segment

### v 0.04
Change store mode for links.db file

### v 0.05
Fix multisite mode

### v 0.06
SapeArticles: Work without URL-template and .htaccess config

### v 0.07
Added backward compartibility for articles placement old common mode

### v 0.08
Fix context back links placement
Fix split_data_file mode

### v 0.09
Fix recreating articles
Fix deleting links in split_data_file mode

### v 0.10
Fix duplicate articles

### v 0.11
Fix duplicate articles

### v 0.12
Add translator

### v 0.13
Update  translations

### v 0.14
Upgrade  translations

### v 0.15
Change readme text

### v 0.16
Change readme text

### v 3.4
Compatible with Word Press 6.0
The ability to display verification code was added

### v 3.4.1
Interface edits

### v 3.4.2
Translate edits