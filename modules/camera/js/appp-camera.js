var isGallery = false;

appcamera = (function(window, document, $, undefined) {

    var camv1 = ( typeof appcamera !== 'undefined' ) ? appcamera : {};

    camv1.init = function(){
        //var $ = jQuery;
        camv1.el = {
            $body  : $('body'),
            $title : $('#appp_cam_post_title'),
            $file  : $('#appp_cam_file'),
            $status : $('#cam_status')
        };

        // Since the ids for the buttons are the same for the appcamera shortcode Take Photo and the appbuddy Take Photo
        // we need to determine which is currently being used, only the shortcode uses the class="btn-camera"
        if( jQuery('#capture-photo-btn').hasClass('btn-camera') ) {
            // for appcamera shortcode
            jQuery('#capture-photo-btn').on('click', camv1.capturePhoto);
            jQuery('#photo-library-btn').on('click', camv1.photoLibrary);
        } else {
            // for appbuddy
            jQuery('#capture-photo-btn').on('click', camv1.attachPhoto);
            jQuery('#photo-library-btn').on('click', camv1.attachLibrary);
        }

        

        camv1.el.$body.on( 'change', '#appp_cam_file', function( event ){
            event.preventDefault();
            var $self = $(this);
            var title = camv1.el.$title.val();

            if ( title && title.trim() )
                return;

            var val   = $self.val();
            var parts = val ? val.split('.') : false;

            if ( parts[0] ) {
                parts = parts[0].split('\\');
                val = parts[ parts.length - 1 ];
                camv1.el.$title.val( val );
            }
        });
    };

    camv1.capturePhoto = function() {

    	isGallery = false;
        // Retrieve image file location from specified source
        window.navigator.camera.getPicture(
            camv1.uploadPhoto,
            function(message) {
                /*alert('No photo was uploaded.');*/
                if ( typeof apppresser.log == 'function' ) {
                    apppresser.log( 'No photo was taken from the camera.', 'appp-camera.js, line 35' );
                }
            },
            {
                quality         : 30,
                destinationType : window.navigator.camera.DestinationType.FILE_URI,
                correctOrientation: true,
                 targetWidth: 1204,
    			 targetHeight: 1204
            }
        );
    };

    camv1.photoLibrary = function() {

    	isGallery = true;
        // Retrieve image file location from specified source
        window.navigator.camera.getPicture(
            camv1.uploadPhoto,
            function(message) {
                /*alert('No photo was uploaded.');*/
                if ( typeof apppresser.log == 'function' ) {
                    apppresser.log( 'No photo was added from the library.', 'appp-camera.js, line 53' );
                }
            },
            {
                quality         : 30,
                destinationType : window.navigator.camera.DestinationType.FILE_URI,
                sourceType      : window.navigator.camera.PictureSourceType.PHOTOLIBRARY,
                correctOrientation: true,
                targetWidth: 1204,
    			targetHeight: 1204
            }
        );
    };

    camv1.statusDom = function() {
        camv1.statusDomEl = camv1.statusDomEl ? camv1.statusDomEl : document.getElementById('cam-status');
        return camv1.statusDomEl;
    };

    camv1.uploadPhoto = function(imageURI) {

    	var image = imageURI.substr( imageURI.lastIndexOf('/') + 1 );

    	var name = image.split("?")[0];
    	var number = image.split("?")[1];

    	if( 'Android' === device.platform && isGallery ) {
    		image = number + '.jpg';
    	}

    	console.log(image);

        var options      = new FileUploadOptions();
        options.fileKey  = 'appp_cam_file';
        options.fileName = imageURI ? image : '';
        options.mimeType = 'image/jpeg';

        console.log(options);

        var params = {};
        var form_fields = [];
        var form_values = [];
        var iterator;
        var form_elements = document.appp_camera_form.elements;

        for( iterator = 0; iterator < form_elements.length; iterator++ ){
            form_fields[iterator] = form_elements[iterator].name;
            form_values[iterator] = form_elements[iterator].value;
        }

        params.form_fields = JSON.stringify(form_fields);
        params.form_values = JSON.stringify(form_values);

        document.getElementById('appp_cam_post_title').value = '';
        options.params = params;

        var ft = new FileTransfer();

        // need so maybeGoBack() doesn't send the browser back page on deviceready
        apppCore.noGoBackFlag = true;

        ft.upload( imageURI, encodeURI(document.URL), camv1.win, camv1.fail, options);

        ft.onprogress = function(progressEvent) {
            if ( progressEvent.lengthComputable ) {
                //camv1.statusProgress().innerHTML = '<progress id="progress" value="1" max="100"></progress>';
                jQuery('#cam-progress').css('visibility', 'visible');
                var perc = Math.floor(progressEvent.loaded / progressEvent.total * 100);
                document.getElementById('progress').value = perc;
            } else {
                if ( camv1.statusDom().innerHTML === '' ) {
                    camv1.statusDom().innerHTML = camv1.msg.loading;
                } else {
                    camv1.statusDom().innerHTML += '.';
                }
            }
        };

    };

    camv1.win = function(r) {

        //console.log('Code = ' + r.responseCode);
        //console.log('Response = ' + r.response);
        //console.log('Sent = ' + r.bytesSent);

        var msg = camv1.msg.moderation;
        var action = document.getElementById('appp_action').value;

        if ( ! camv1.moderation_on ) {

            // var type = jQuery('#appp_post_type_label').val();
            // type = type ? type : camv1.msg.default_type;

            msg = camv1.msg.success;
        }

        jQuery('#cam-status').html('<p>'+ msg +'</p>');
        jQuery('#cam-progress').css('visibility', 'hidden');

    };

    camv1.fail = function(error) {
        // alert('An error has occurred: Code = ' + error.code);
        console.log('upload error source ' + error.source);
        console.log('upload error target ' + error.target);
        jQuery('#cam-status').html('<p>'+ camv1.msg.error +'= '+ error.code +'</p>');
        jQuery('#cam-progress').css('visibility', 'hidden');
    };

    camv1.attachPhoto = function() {
        // Retrieve image file location from specified source
        window.navigator.camera.getPicture(
            camv1.uploadAttachPhoto,
            function(message) {
                /*alert('No photo was uploaded.');*/
                if ( typeof apppresser.log == 'function' ) {
                    apppresser.log( 'No photo was added from the library.', 'appp-camera.js, line 53' );
                }
            },
            {
                quality         : 30,
                destinationType : window.navigator.camera.DestinationType.FILE_URI,
                correctOrientation: true,
                targetWidth: 1204,
    			targetHeight: 1204
            }
        );
    };

    camv1.attachLibrary = function() {

        // need so maybeGoBack() doesn't send the browser back page on deviceready
        apppCore.noGoBackFlag = true;

        // Retrieve image file location from specified source
        window.navigator.camera.getPicture(
            camv1.uploadAttachPhoto,
            function(message) {
                /*alert('No photo was uploaded.');*/
                if ( typeof apppresser.log == 'function' ) {
                    apppresser.log( 'No photo was added from the library.', 'appp-camera.js, line 53' );
                }
            },
            {
                quality         : 30,
                destinationType : window.navigator.camera.DestinationType.FILE_URI,
                sourceType      : window.navigator.camera.PictureSourceType.PHOTOLIBRARY,
                correctOrientation: true,
                targetWidth: 1204,
    			targetHeight: 1204
            }
        );
    };

    camv1.uploadAttachPhoto = function(imageURI) {

        console.log(imageURI);

        var imagenew = '';

    	var image = imageURI.substr( imageURI.lastIndexOf('/') + 1 );

    	var name = image.split("?")[0];
    	var number = image.split("?")[1];
    	var time = Math.floor( Date.now() / 1000 );

    	number = (typeof number === 'undefined') ? time - 1 : number;

    	imagenew = time + '-' + name;

    	if( 'Android' === device.platform ) {
    		imagenew = number + time + '.jpg';
    	}

    	console.log(imagenew);

        var options      = new FileUploadOptions();
        options.fileKey  = 'appp_cam_file';
        options.fileName = imageURI ? imagenew : '';
        options.mimeType = 'image/jpeg';
        options.appp_action = 'attach';

        var params = {};
        params.action = 'upload_image';
        
        if( document.getElementById('apppcamera-upload-image') ) { // from camv1 shortcode
            params.nonce = document.getElementById('apppcamera-upload-image').value;
        } else if( document.getElementById('attach-photo') ) { // from buddypress activity upload
            params.nonce = document.getElementById('attach-photo').getAttribute('data-nonce');
        }

        options.params = params;

        var ft = new FileTransfer();

        ft.upload( imageURI, ajaxurl, camv1.attachWin, camv1.fail, options);

        ft.onprogress = function(progressEvent) {
            if ( progressEvent.lengthComputable ) {
                //camv1.statusDom().innerHTML = '<progress id="progress" value="1" max="100"></progress>';
                jQuery('#cam-progress').css('visibility', 'visible');
                var perc = Math.floor(progressEvent.loaded / progressEvent.total * 100);
                document.getElementById('progress').value = perc;
            } else {
                if ( camv1.statusDom().innerHTML === '') {
                    camv1.statusDom().innerHTML = camv1.msg.loading;
                } else {
                    camv1.statusDom().innerHTML += '.';
                }
            }
        };

    };

    camv1.attachWin = function(r) {

        var nonce_failure = 'Nonce Failed';

        console.log('Code = ' + r.responseCode);
        console.log('Response = ' + r.response);
        console.log('Sent = ' + r.bytesSent);

        var action = document.getElementById('appp_action').value;

        if ( action == 'appbuddy' ) {
            if( r.response == nonce_failure ) {
                msg = 'Nonce failed';
            } else {
                msg = 'Image attached';
            }
        }

        jQuery('#cam-status').html('<p>'+ msg +'</p>');

        if( r.response != nonce_failure ) {
            document.getElementById('attach-image').value = JSON.parse(r.response);
        }

        jQuery('#attach-image-sheet').removeClass('active').addClass('hide');
        if( r.response != nonce_failure ) {
            jQuery('#image-status').html('<img src="'+ JSON.parse(r.response) +'">');
        }
        jQuery('#cam-progress').css('visibility', 'hidden');
        jQuery('#cam-status').html('');

    };

    return camv1;

})(window, document, jQuery);

jQuery(document).ready( appcamera.init ).bind( 'load_ajax_content_done', appcamera.init );