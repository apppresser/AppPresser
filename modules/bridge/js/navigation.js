(function ($) {
    'use strict';

    $(function () {
        $('a:not([href^="#"]):not(.external)').addClass('push-page');
    });
})(jQuery);
