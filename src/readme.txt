# Sape.ru integration
Plugin Name: Sape.ru integration
Plugin URI: https://github.com/sape-ru/client-code-wordpress/releases
Description: Plugin for sape.ru webmaster services integration
Contributors: Sape
Donate link: https://www.sape.ru/
Tags:  sape, seo, link, site, teaser, rtb
Requires at least: 4.2
Tested up to: 5.8.1
Stable tag: trunk
Version: 0.12
Author: Sape.ru
Author URI: https://www.sape.ru/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Плагин для автоматической установки кода системы Sape на сайт вебмастера

## Описание

Плагин для монетизации сайта от [Sape](https://www.sape.ru/). Плагин поддерживает следующие форматы монетизации:

- Арендные ссылки
- Сквозные арендные ссылки
- Контекстные арендные ссылки
- Тизерные блоки (ссылки в формате тизеров)
- Арендные статьи
- Медийная реклама (RTB блоки)

## Установка
**Важно!** Если Вы до этого устанавливали код системы и папки вручную, то удалите их!

1. В панеле администратора WordPress переходим в раздел "Плагины" и нажимаем "Добавить новый".
2. В поле поиска вводим "sape", находим наш плагин и нажимаем кнопку "Установить". После установки нажимаем "Активировать".
3. После активации плагина переходим в его настройки и в поле "Ключ Пользователя" указываем свой идентификатор.
4. Отмечаем галочкой форматы монетизации, которые будем размещать на сайте. Сохраняем настройки.
5. После настройки переходим в раздел "Внешний вид" -> "Виджеты". Завершающий шаг - вам нужно разместить в нужных местах вашей темы (шаблона) форматы монетизации, которые вы подключили на прошлом шаге. Для этого разместите виджеты, например в Sidebar или Footer

## Настройка виджета
Все настройки необязательные - все будет работать с настройками по умолчанию. Настройка виджета рассмотрим на примере виджета "Sape: Арендные ссылки"

- Задайте заголовок (необязательно)
- Задайте количество ссылок, которое будет выводиться в блоке (если оставить пустым - будут выводиться все купленные ссылки)
- Если вы хотите выводить ссылки в формате блоков - укажите ```Формат=Блок``` и задайте как будет отображаться блок: вертикально или горизонтально
- Нажмите кнопку "Сохранить"



Plugin from Sape to monetize your website

## Description

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

### v 0.10
Add translator

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