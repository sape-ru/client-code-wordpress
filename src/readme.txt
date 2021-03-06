# Sape.ru integration
Plugin Name: Sape.ru integration
Plugin URI: https://github.com/sape-ru/client-code-wordpress/releases
Description: Plugin for sape.ru webmaster services integration
Contributors: Sape
Donate link: https://www.sape.ru/
Tags:  sape, seo, link, site, teaser, rtb
Requires at least: 4.2
Tested up to: 5.4.1
Stable tag: trunk
Version: 0.10
Author: Sape.ru
Author URI: https://www.sape.ru/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Плагин для автоматической установки кода системы Sape на сайт вебмастера

## Description

Плагин для автоматической установки кода системы [Sape](https://www.sape.ru/) на сайт вебмастера

Поддержка функций

- арендные ссылки (обычный и блочный режим вывода)
- пакеты умных сквозных ссылок
- контекстные ссылки
- тизерные размещения
- блоки rtb.sape
- статьи

## Installation
Установка кода на CMS Wordpress с помощью плагина.

! Важно ! Если Вы до этого устанавливали код системы и папки вручную, то удалите их!

1. Загрузите архив с плагином с сайта;
2. Распакуйте архив на компьютере в папку «Sape.ru integration»;
3. Распакованные папки и файлы «sape», «lib», saperu-integration.php скопируйте на хостинг в папку расположенную по следующими пути: название сайта ? public_html ? wp-content ? plugins ? “Sape wordpress plugin”.
4. После выполнения предыдущих шагов заходим в «Панель администратора» ? «Плагины» и активируем плагин «Sape.ru integration».
5. После активации плагина заходим в его настройки в поле «_SAPE_USER» указываем свой идентификатор — screenshot-1;
6. Отмечаем галочкой типы ссылок, которые будем размещать на сайте — screenshot-2
Сейчас для размещения  в автоматическом режиме доступны почти все виды ссылок: «Простые ссылки», «Контекстные ссылки», «Размещение статей», Размещение тизеров.
Если Вы планируете размещать тизерные ссылки, то необходимо задать название файла для изображения тизеров — screenshot-3;
7. После настройки и активации плагина переходим в раздел «Внешний вид» ? «Виджеты» и выбираем виджеты, с типами ссылками, которые Вы хотите размещать на сайте. Виджеты имеют следующие названия: «Sape: Ссылки», «Sape тизеры», «Sape Articles», «Sape RTB».
8. Добавляем виджеты в «Sidebar»
= Setting widgets =
Настройка виджета «Sape: Ссылки» - screenshot-4:
	- Задайте заголовок блока(если есть такая необходимость)
	- Задайте количество ссылок, которое будет выводиться в блоке
	- Выберите формат в котором будут размещаться ссылки. Доступны следующие форматы для отображения ссылок: в виде текста, в виде блоков — screenshot-5`
	- Выберите, как будет отображаться блок: вертикально или горизонтально — screenshot-6
После, того как Вы выбрали необходимые опции нажмите в кнопку виджете «Сохранить».

Настройка виджета «Sape тизеры» - screenshot-7:
	- Задайте заголовок блока(если есть такая необходимость)
	- Укажите ID тизерного блока, о том как создать тизерный блок для сайта Вы можете прочитать в разделе http://help.sape.ru/sape/faq/1677
После, того как Вы выбрали необходимые опции нажмите в кнопку виджете «Сохранить».

Настройка виджета «Sape RTB»:
- Задайте заголовок блока(если есть такая необходимость)
- В окне «Код RTB блока» разместите код, который имеет следующий вид:

Код для размещения формируется в разделе «RTB» - https://rtb.sape.ru/wm/
Блок в конечном итоге должен иметь следующий вид — screenshot-8

Настройка виджета «Sape Articles»:
	- Задайте заголовок блока(если есть такая необходимость)
	- Задайте количество анонсов, которое будет отображаться на странице.
	- Сохраните изменения

Затем Вам необходимо произвести необходимые настройки в разделе «Статьи» - https://articles.sape.ru/wm/sites/

## Screenshots

1. screenshot-1
2. screenshot-2
3. screenshot-3
4. screenshot-4
5. screenshot-5
6. screenshot-6
7. screenshot-7
8. screenshot-8

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