jQuery(document).ready(function($) {

    wp.media.frames.ah_dl_res_frame = wp.media({
        title: 'Select Resource from PDF Library',
        button: {
            text: 'Select Resource'
        },
        library: {
            type: ['application/pdf']
        },
        multiple: false  // Set to true to allow multiple files to be selected
    });

    wp.media.frames.ah_dl_res_frame.on( 'select', function() {
        attachment = wp.media.frames.ah_dl_res_frame.state().get('selection').first().toJSON();
        $('#ah-dl-res-url').val(attachment.url);
    });

    $('#ah-dl-res-upload').click(function(){
        wp.media.frames.ah_dl_res_frame.open();
    });

});



