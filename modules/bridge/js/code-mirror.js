jQuery(function () {
    if (!window.wp || !wp.codeEditor) return;
    ['bridge_custom_styles', 'bridge_custom_javascript'].forEach(function (key) {
        var field = document.getElementById('apppresser--' + key);
        if (field) wp.codeEditor.initialize(field, {
            codemirror: {mode: key === 'bridge_custom_styles' ? 'css' : 'javascript', lineNumbers: true}
        });
    });
});
