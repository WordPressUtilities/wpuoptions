jQuery(document).ready(function($) {
    var form = jQuery('.wpu-options-form');
    wputh_options_set_exportcheck();
    wputh_options_set_media();
    wputh_options_set_accordion();
    wputh_options_set_editor();
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
}

/* ----------------------------------------------------------
  Set Editor
---------------------------------------------------------- */

var wputh_options_set_editor = function() {
    jQuery('.wpuoptions-view-editor-switch').on('click', '.edit-link', function(e) {
        e.preventDefault();
        jQuery(this).closest('.wpuoptions-view-editor-switch').find('.editor-view, .original-view').toggle();
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
            if(!$targetInput){
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

            if ($preview.data('type') == 'file') {
                var att = attachment.url;
                att = att.replace($preview.data('removethis'), '');
                previewContent += '<div class="wpu-options-upload-preview--file">' + att + '</div>';
            }
            else {
                previewContent += '<img src="' + attachment.url + '" />';
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
