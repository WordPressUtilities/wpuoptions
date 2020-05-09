Options
=================

Friendly interface for website options.

How to install :
---

* Put this folder to your wp-content/plugins/ folder.
* Activate the plugin in "Plugins" admin section.

How to add tabs :
---

Put the code below in your theme's functions.php file. Add new tabs to your convenance.

```php
add_filter( 'wpu_options_tabs', 'set_wpu_options_tabs', 10, 3 );
function set_wpu_options_tabs( $tabs ) {
    $tabs['special_tab'] = array(
        'name' => 'Special tab',
        'sidebar' => true // Load in sidebar
    );
    return $tabs;
}
```

How to add boxes :
---

Put the code below in your theme's functions.php file. Add new boxes to your convenance.

```php
add_filter( 'wpu_options_boxes', 'set_wpu_options_boxes', 10, 3 );
function set_wpu_options_boxes( $boxes ) {
    $boxes['special_box'] = array(
        'name' => 'Special box'
    );
    return $boxes;
}
```

How to add fields :
--

Put the code below in your theme's functions.php file. Add new fields to your convenance.

```php
add_filter( 'wpu_options_fields', 'set_wputh_options_fields', 10, 3 );
function set_wputh_options_fields( $options ) {
    $options['wpu_opt_email'] = array(
        'label' => __( 'Email address', 'wputh' ),
        'box' => 'special_box',
        'type' => 'email'
    );
    return $options;
}
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
