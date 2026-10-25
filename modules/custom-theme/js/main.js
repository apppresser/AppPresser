(function (window, document, $, undefined) {
    'use strict';

    if (parent) {
        // Message our frame so we know when to run scripts
        parent.postMessage('site_loaded', '*');
    }

    var body = $('body');

    body
        .on('click', '.push-page', function (e) {
            e.preventDefault();
            var message = { url: e.currentTarget.href, title: '' };
            parent.postMessage(JSON.stringify(message), '*');
            return;
        })
        .on('click', '.app-media-modal', function (e) {
            if (e.currentTarget && e.currentTarget.href) {
                e.preventDefault();
                var message = { media: e.currentTarget.href }
                parent.postMessage(JSON.stringify(message), '*');
                return;
            }
        })
        .on('click', '.app-login-modal', function (e) {
            e.preventDefault();
            parent.postMessage('open_login_modal', '*');
            return;
        })
        .on('click', '.appshare', function (e) {
            e.preventDefault();
            var message = { link: e.currentTarget.dataset.link, msg: e.currentTarget.dataset.msg };
            parent.postMessage(JSON.stringify(message), '*');
            return;
        })
        .on('click', '.app-download', function (e) {
            e.preventDefault();
            var url = (typeof e.target.href === 'undefined') ? $(this).attr('href') : e.target.href;
            var message = { download_url: url, download_title: e.currentTarget.dataset.downloadTitle };
            parent.postMessage(JSON.stringify(message), '*');
            return;
        })
        .on('click', '.appp-spinner', function (e) {
            e.preventDefault();
            parent.postMessage('show_spinner', '*');
            return;
        })
        .on('click', '.external', function (e) {
            e.preventDefault();
            var url = (typeof e.target.href === 'undefined') ? $(this).attr('href') : e.target.href;
            var target = '_blank';
            if ($(this).hasClass('system') || (e.target.target && e.target.target == '_system') || $(this).parent().attr('target') == '_system' || $(this).attr('target') == '_system') {
                target = '_system';
            }
            var message = { iablink: url, target: target };
            parent.postMessage(JSON.stringify(message), '*');
            return;
        })
        .on('click', '.app-root-page', function (e) {
            e.preventDefault();
            var url = (typeof e.target.href === 'undefined') ? $(this).attr('href') : e.target.href;
            var message = { apppage: { url: url } };
            parent.postMessage(JSON.stringify(message), '*');
            return;
        })
        .on('click', '*[data-applink], *[data-apptablink]', function (e) {
            e.preventDefault();
            var json = { menulink: {} };
            if ($(this).data('applink') && $(this).data('applink').toString()) {
                json.menulink.menu = $(this).data('applink');
            } else {
                json.menulink.tab_menu = $(this).data('apptablink');
            }
            parent.postMessage(JSON.stringify(json), '*');
            return;
        })

})(window, document, jQuery);
