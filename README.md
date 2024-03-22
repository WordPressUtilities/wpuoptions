Options
=================

[![JS workflow](https://github.com/WordPressUtilities/wpuoptions/actions/workflows/js.yml/badge.svg 'JS workflow')](https://github.com/WordPressUtilities/wpuoptions/actions) [![PHP workflow](https://github.com/WordPressUtilities/wpuoptions/actions/workflows/php.yml/badge.svg 'PHP workflow')](https://github.com/WordPressUtilities/wpuoptions/actions)

Friendly interface for website options.

How to install :
---

* Put this folder to your wp-content/plugins/ folder.
* Activate the plugin in "Plugins" admin section.

How to add fields :
---

Put the code below in your theme's functions.php file.

```php

/* Tabs */
add_filter( 'wpu_options_tabs', function ( $tabs ) {
    $tabs['special_tab'] = array(
        'name' => 'Special tab',
        /* Load in sidebar */
        'sidebar' => true
    );
    /* Only in multisite options */
    $tabs['multisite_tab'] = array(
        'name' => 'Multisite tab',
        'visibility_admin' => false,
        'visibility_network' => true,
    );
    return $tabs;
}, 10, 1 );


/* Boxes */
add_filter( 'wpu_options_boxes', function ( $boxes ) {
    $boxes['special_box'] = array(
        'tab' => 'special_tab',
        'name' => 'Special box'
    );
    return $boxes;
}, 10, 1 );

/* Fields */
add_filter( 'wpu_options_fields', function ( $options ) {
    /* Default field */
    $options['special_field'] = array(
        'label' => 'Special field',
        'box' => 'special_box',
        'type' => 'email'
    );
    /* Only in multisite options */
    $options['multisite_field'] = array(
        'label' => 'Special field',
        'box' => 'special_box',
        'type' => 'email',
        'visibility_admin' => false,
        'visibility_network' => true,
    );
    return $options;
}, 10, 1 );

```


Field types :
---

* Default : A "text" input.
* "title" : A simple section separator.
* "editor" : A WYSIWYG editor used in the content of a post.
* "file" : An attached file.
* "media" : An attached file present in the Media editor.
* "page" : A WordPress page.
* "category" : A WordPress category.
* "taxonomy" : A WordPress taxonomy. (default : category. Use the argument "taxonomy" value to specify.)
* "post" : A WordPress post. (default : post. Use the argument "post_type" value to specify.)
* "select" : A value inside an array present into "datas" (default : yes/no)
* "radio" : A value inside an array present into "datas" (default : yes/no)
* "textarea" : A classic textarea
* "color" : A "color" field
* "date" : A "date" input
* "email" : A "email" input
* "number" : A "number" input
* "url" : A "url" input

Field tests :
---

* "email" : value must be a valid email
* "page" : value must be a numeric ID
* "radio" : value must be contained into the "datas" array.
* "select" : value must be contained into the "datas" array.
* "url" : value must be a valid url
