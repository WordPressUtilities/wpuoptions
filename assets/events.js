jQuery(document).ready(function($) {
    var form = jQuery('.wpu-options-form');
    wputh_options_set_exportcheck();
    wputh_options_set_media();
    wputh_options_set_accordion();
    wputh_options_set_langs();
    wputh_options_set_editor();
    wputh_options_set_wp_link();
    wputh_options_set_multiple_selects();
    wputh_options_set_polyfills(form);
});

/* ----------------------------------------------------------
  Set export checkboxes
---------------------------------------------------------- */

var wputh_options_set_exportcheck = function() {
    jQuery('.wpu-export-section').on('change', '.wpu-export-title-checkbox', function() {
        var $this = jQuery(this),
            $parent = $this.closest('.wpu-export-section');
        $parent.find('.wpu-export-boxes-check').prop("checked", $this.prop('checked'));
    });
};

/* ----------------------------------------------------------
  Set wp-link
---------------------------------------------------------- */

var wputh_options_set_wp_link = function() {
    jQuery('[data-wpuoptions-wplink]').each(function() {
        var $this = jQuery(this),
            $parent = $this.parent(),
            $preview = $parent.find('.link-preview'),
            $textarea = $parent.find('textarea');

        $this.click(function() {
            /* Open link */
            wpLink.open($textarea.attr('id'));

            /* Set values */
            var src_json = {};
            try {
                src_json = JSON.parse(response);
            }
            catch (e) {}
            if (typeof src_json == 'object') {
                if (src_json.href) {
                    jQuery('#wp-link-url').val(src_json.href);
                }
                if (src_json.text) {
                    jQuery('#wp-link-text').val(src_json.text);
                }
                if (src_json.target && src_json.target == '_blank') {
                    jQuery('#wp-link-target').prop('checked', 1);
                }
            }

            /* Update function */
            wpLink.htmlUpdate = function() {
                /* Save value */
                var attrs = wpLink.getAttrs();
                attrs.text = jQuery('#wp-link-text').val();
                $textarea.val(JSON.stringify(attrs));

                /* Update preview */
                var $a = document.createElement('a');
                $a.href = attrs.href;
                $a.innerText = attrs.text;
                $preview.html($a.outerHTML);
                wpLink.close();
            };
        });
    });
};

/* ----------------------------------------------------------
  Set multiple selects
---------------------------------------------------------- */

var wputh_options_set_multiple_selects = function() {
    if (!jQuery.fn.select2) {
        return;
    }
    $('.wpu-options-box select[multiple]').select2();
};

/* ----------------------------------------------------------
  Set polyfills
---------------------------------------------------------- */

var wputh_options_set_polyfills = function(form) {
    form.find('input[type=date]').each(function() {
        jQuery(this).attr('type', 'text').datepicker({
            dateFormat: 'dd/mm/yy'
        });
    });

    form.find('input[type=color]').each(function() {
        jQuery(this).attr('type', 'text').iris();
    });
};

/* ----------------------------------------------------------
  Set Editor
---------------------------------------------------------- */

var wputh_options_set_editor = function() {
    jQuery('.wpuoptions-view-editor-switch').on('click', '.edit-link', function(e) {
        e.preventDefault();
        var $this = jQuery(this),
            $wrapper = $this.closest('.wpuoptions-view-editor-switch');
        if ($this.hasClass('cancel-link')) {
            var $editor = $wrapper.find('.editor-view');
            tinymce.get($editor.attr('data-id-editor')).setContent($editor.attr('data-original-value'));
        }
        $wrapper.find('.editor-view, .original-view').toggle();
    });
};

/* ----------------------------------------------------------
  Upload files
---------------------------------------------------------- */

var wpuopt_file_frame,
    wpuopt_datafor;

var wputh_options_set_media = function() {
    var options_form = jQuery('.wpu-options-form');
    // Remove media
    options_form.on('click', '.wpu-options-upload-preview .x', function(event) {
        event.preventDefault();
        var $this = jQuery(this),
            $td = $this.closest('td'),
            divLabel = $td.find('[data-defaultlabel]'),
            defaultLabel = divLabel.attr('data-defaultlabel');

        // Asks for confirmation
        var confirm = window.confirm(divLabel.attr('data-confirm'));
        if (!confirm) {
            return false;
        }

        // Remove preview
        $td.find('.wpu-options-upload-preview').remove();

        // Empty value
        $td.find('.hidden-value').val('');

        // Set default text to button
        $td.find('.wpuoptions_add_media').text(defaultLabel);
    });
    // Add media
    options_form.on('click', '.wpuoptions_add_media', function(event) {
        event.preventDefault();
        var $this = jQuery(this);

        wpuopt_datafor = $this.data('for');

        // If the media frame already exists, reopen it.
        if (wpuopt_file_frame) {
            wpuopt_file_frame.open();
            return;
        }

        // Create the media frame.
        wpuopt_file_frame = wp.media.frames.wpuopt_file_frame = wp.media({
            title: $this.data('uploader_title'),
            button: {
                text: $this.data('uploader_button_text'),
            },
            multiple: false // Set to true to allow multiple files to be selected
        });

        wpuopt_file_frame.on('open', function() {
            var $targetInput = jQuery('#' + wpuopt_datafor);
            if (!$targetInput) {
                return false;
            }

            if (!$targetInput.attr('value')) {
                return;
            }
            var attachment = wp.media.attachment($targetInput.attr('value'));
            attachment.fetch();
            wpuopt_file_frame.state().get('selection').add(attachment ? [attachment] : []);
        });

        // When an image is selected, run a callback.
        wpuopt_file_frame.on('select', function() {
            // We set multiple to false so only get one image from the uploader
            var attachment = wpuopt_file_frame.state().get('selection').first().toJSON(),
                $preview = jQuery('#preview-' + wpuopt_datafor),
                previewContent = '<div class="wpu-options-upload-preview"><span class="x">&times;</span>';

            var att = false;
            if (attachment.sizes && attachment.sizes.medium) {
                att = attachment.sizes.medium.url;
            }
            if (!att) {
                att = attachment.url;
                att = att.replace($preview.data('removethis'), '');
                previewContent += '<div class="wpu-options-upload-preview--file">' + att + '</div>';
            }
            else {
                previewContent += '<img src="' + att + '" />';
            }
            previewContent += '</div>';

            // Set attachment ID
            jQuery('#' + wpuopt_datafor).attr('value', attachment.id);

            // Set preview image
            $preview.html(previewContent);

            // Change button label
            $this.html($preview.attr('data-label'));

        });

        // Finally, open the modal
        wpuopt_file_frame.open();
    });
};

/* ----------------------------------------------------------
  Accordion
---------------------------------------------------------- */

var wputh_options_set_accordion = function() {
    var form = jQuery('.wpu-options-form'),
        boxes = form.find('.wpu-options-form__box');

    form.on('click', 'h3', function() {
        var $this = jQuery(this);
        boxes.addClass('is-closed');
        // Open and save state of first box
        window.location.hash = '#' + $this.attr('id');
        $this.closest('.wpu-options-form__box').removeClass('is-closed');
    });

    // Initial class
    boxes.addClass('is-closed');

    // Set opened box
    if (window.location.hash && jQuery(window.location.hash).length > 0) {
        jQuery(window.location.hash).trigger('click');
    }
    else {
        boxes.eq(0).removeClass('is-closed');
    }

};

/* ----------------------------------------------------------
  Langs
---------------------------------------------------------- */

var wputh_options_set_langs = function() {
    var $form = jQuery('.wpu-options-form'),
        $boxes = $form.find('.wpu-options-box[data-lang]'),
        $langs = jQuery('.wpu-options-lang-switcher').find('a[data-lang]');

    $langs.on('click', function(e) {
        var $this = jQuery(this),
            $lang = $this.attr('data-lang');
        e.preventDefault();
        $boxes.hide();
        $langs.removeClass('is-active');
        $this.addClass('is-active');
        $boxes.filter('[data-lang="' + $lang + '"]').show();
    });

    $langs.eq(0).trigger('click');
};

/* ----------------------------------------------------------
  Heartbeat
---------------------------------------------------------- */

jQuery(document).ready(function($) {

    var $body = jQuery('body'),
        $document = jQuery(document),
        $form = jQuery('.wpu-options-form'),
        textVar = wpuoptions__settings.last_updated__text,
        versionVar = 'wpuoptions__last_updated';

    $document.on('heartbeat-send', function(e, data) {
        data[versionVar] = wpuoptions__settings.last_updated;
    });

    $document.on('heartbeat-tick', function(e, data) {
        /* Stop is var is not present */
        if (!data[versionVar]) {
            return;
        }

        /* If saved version is not the same */
        if (data[versionVar] != wpuoptions__settings.last_updated) {
            /* Only one alert */
            if ($body.attr('data-' + versionVar) == '1') {
                return;
            }
            $body.attr('data-' + versionVar, 1);

            /* Visible alert */
            alert(textVar);

            /* Insert a notice below the submit button */
            $form.append(jQuery('<div class="notice notice-warning"><p>' + textVar + '</p></div>'));
        }

    });

    // Move interval to fast ( every 5 sec )
    wp.heartbeat.interval('fast');
});
