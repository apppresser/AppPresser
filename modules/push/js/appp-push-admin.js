jQuery(document).ready(function($) {
    
    // --- Phone Preview Logic ---
    var $wpTitle = $('#title');
    var $appPushTitle = $('#apppush_notification_title');
    var $messageInput = $('#excerpt');
    var $previewTitle = $('.notification-title');
    var $previewMessage = $('.notification-message');    
    // Check if notification was already sent
    var notificationSent = (typeof apppush_local !== 'undefined' && apppush_local.notification_sent === '1');

    function updatePreview() {
        var title = $appPushTitle.val() || $wpTitle.val() || 'Notification Title';
        var message = $messageInput.val() || 'Notification message will appear here...';
        
        $previewTitle.text(title);
        $previewMessage.text(message);
    }


    // --- Lock Fields if Notification Already Sent ---
    function lockFieldsIfSent() {
        if (!notificationSent) {
            return;
        }

        // Add sent indicator banner to content metabox
        var $contentMetabox = $('#apppush_content .inside');
        if ($contentMetabox.length && !$('.apppush-sent-banner').length) {
            $contentMetabox.prepend(
                '<div class="apppush-sent-banner">' +
                '<span class="dashicons dashicons-lock"></span> ' +
                '<strong>Notification Sent</strong> — This notification has already been sent. Fields are locked to preserve the original message.' +
                '</div>'
            );
        }

        // Disable Content fields
        $appPushTitle.prop('readonly', true).addClass('apppush-field-locked');
        $wpTitle.prop('readonly', true).addClass('apppush-field-locked');
        $messageInput.prop('readonly', true).addClass('apppush-field-locked');

        // Disable Click Action fields
        $('input[name="apppush_action"]').prop('disabled', true);
        $('#apppush_app_page').prop('disabled', true);
        $('#apppush-page-search').prop('disabled', true).addClass('apppush-field-locked');
        $('#apppush_custom_url').prop('readonly', true).addClass('apppush-field-locked');

        // Disable Audience fields
        $('input[name="apppush_audience"]').prop('disabled', true);
        $('input[name="apppush_roles[]"]').prop('disabled', true);
        $('#apppush-user-search').prop('disabled', true).addClass('apppush-field-locked');
        $('#apppush_memberpress_membership').prop('disabled', true);
        $('#apppush_rcp_level').prop('disabled', true);
        $('select[name="segments"]').prop('disabled', true);

        // Hide remove buttons for selected users
        $('.remove-user').hide();

        // Add locked indicator to metaboxes
        $('#apppush_action .hndle, #apppush_audience .hndle').append(' <span class="apppush-locked-badge"><span class="dashicons dashicons-lock"></span></span>');
    }

    // --- Phone Time Display ---
    function updatePhoneTime() {
        var now = new Date();
        var hours = now.getHours();
        var minutes = now.getMinutes();
        
        // Format as 12-hour time without AM/PM (iOS style)
        var displayHours = hours % 12 || 12;
        var displayMinutes = minutes < 10 ? '0' + minutes : minutes;
        var timeString = displayHours + ':' + displayMinutes;
        
        // Update both time displays
        $('.status-time').text(timeString);
        $('.lockscreen-time').text(timeString);
        
        // Update the date display
        var days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        
        var dayName = days[now.getDay()];
        var monthName = months[now.getMonth()];
        var date = now.getDate();
        
        $('.lockscreen-header').text(dayName);
        $('.lockscreen-date').text(monthName + ' ' + date);
    }

    // Update time immediately and then every minute
    updatePhoneTime();
    setInterval(updatePhoneTime, 60000);

    // Sync Titles
    $appPushTitle.on('keyup change', function() {
        $wpTitle.val($(this).val()).trigger('input');
        updatePreview();
    });

    $wpTitle.on('keyup change', function() {
        $appPushTitle.val($(this).val());
        updatePreview();
    });

    // Initial update
    updatePreview();

    // --- Click Target Logic ---
    var $actionRadios = $('input[name="apppush_action"]');
    var $appPageSection = $('#apppush-action-app-page');
    var $pageSection = $('#apppush-action-page');
    var $urlSection = $('#apppush-action-url');
    var $notifyUrl = $('#notify_url');
    var $customUrlInput = $('#apppush_custom_url');
    var $pageSearch = $('#apppush-page-search');
    var $pageIdInput = $('#apppush_page_id');
    var $selectedPage = $('#apppush-selected-page');
    var $appPageSelect = $('#apppush_app_page');
    var $appPageStatus = $('#apppush-app-page-status');
    var appPagesLoaded = false;

    function toggleActionSections() {
        var selected = $actionRadios.filter(':checked').val();
        
        $appPageSection.hide();
        $pageSection.hide();
        $urlSection.hide();
        
        if (selected === 'app_page') {
            $appPageSection.show();
            if (!appPagesLoaded) {
                loadAppPages();
            }
            updateNotifyUrlFromAppPage();
        } else if (selected === 'page') {
            $pageSection.show();
            updateNotifyUrlFromPage();
        } else if (selected === 'url') {
            $urlSection.show();
            updateNotifyUrlFromCustom();
        } else {
            // App (default)
            $notifyUrl.val('');
        }
    }

    function loadAppPages() {
        $appPageSelect.html('<option value="">-- Loading app pages... --</option>');
        $appPageStatus.text('Fetching pages from your app...');

        $.ajax({
            url: apppush_local.ajax_url,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'apppush_get_app_pages',
                nonce: apppush_local.nonce
            },
            success: function(response) {
                if (response.success && response.data.length > 0) {
                    var options = '<option value="">-- Select an App Page --</option>';
                    var currentMenu = '';
                    
                    // Group by menu type
                    var menuLabels = {
                        'side_menu': 'Side Menu',
                        'tab_menu': 'Tab Menu',
                        'aux_menu': 'Auxiliary Menu'
                    };

                    // Sort pages by menu type
                    response.data.sort(function(a, b) {
                        return a.menu_type.localeCompare(b.menu_type);
                    });

                    response.data.forEach(function(page) {
                        if (page.menu_type !== currentMenu) {
                            if (currentMenu !== '') {
                                options += '</optgroup>';
                            }
                            currentMenu = page.menu_type;
                            options += '<optgroup label="' + (menuLabels[currentMenu] || currentMenu) + '">';
                        }
                        options += '<option value="' + page.slug + '">' + page.title + '</option>';
                    });

                    if (currentMenu !== '') {
                        options += '</optgroup>';
                    }

                    $appPageSelect.html(options);
                    $appPageStatus.text('');
                    appPagesLoaded = true;
                } else {
                    $appPageSelect.html('<option value="">-- No pages found --</option>');
                    $appPageStatus.text(response.data && response.data.message ? response.data.message : 'No pages found in your app configuration.');
                }
            },
            error: function() {
                $appPageSelect.html('<option value="">-- Error loading pages --</option>');
                $appPageStatus.text('Failed to load app pages. Please check your settings.');
            }
        });
    }

    function updateNotifyUrlFromAppPage() {
        var slug = $appPageSelect.val();
        if (slug) {
            $notifyUrl.val(slug);
        } else {
            $notifyUrl.val('');
        }
    }

    function updateNotifyUrlFromCustom() {
        $notifyUrl.val($customUrlInput.val());
    }

    function updateNotifyUrlFromPage() {
        // If a page is selected, we might have stored its URL in a data attribute or just rely on the hidden input if we want to send ID.
        // But the PHP expects a URL in notify_url usually.
        // Let's assume we stored the URL when selecting.
        var url = $pageIdInput.data('url');
        if (url) {
            $notifyUrl.val(url);
        } else {
            $notifyUrl.val('');
        }
    }

    $actionRadios.on('change', toggleActionSections);
    
    $appPageSelect.on('change', function() {
        if ($actionRadios.filter(':checked').val() === 'app_page') {
            updateNotifyUrlFromAppPage();
        }
    });

    $customUrlInput.on('keyup change', function() {
        if ($actionRadios.filter(':checked').val() === 'url') {
            updateNotifyUrlFromCustom();
        }
    });

    // Page Search Autocomplete
    $pageSearch.autocomplete({
        source: function(request, response) {
            $.ajax({
                url: apppush_local.ajax_url,
                dataType: "json",
                data: {
                    action: 'apppush_search_pages',
                    term: request.term,
                    nonce: apppush_local.nonce
                },
                success: function(data) {
                    if (data.success) {
                        var mappedData = $.map(data.data, function(item) {
                            return {
                                label: item.text,
                                value: item.text,
                                id: item.id,
                                url: item.url,
                                text: item.text
                            };
                        });
                        response(mappedData);
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            $pageIdInput.val(ui.item.id);
            $pageIdInput.data('url', ui.item.url); // Store URL
            $selectedPage.html('Selected: <a href="' + ui.item.url + '" target="_blank">' + ui.item.text + '</a>');
            $pageSearch.val('');
            
            if ($actionRadios.filter(':checked').val() === 'page') {
                updateNotifyUrlFromPage();
            }
            return false;
        }
    });


    // --- Audience Selection Logic ---
    var $audienceRadios = $('input[name="apppush_audience"]');
    var $roleSection = $('#apppush-audience-role');
    var $specificSection = $('#apppush-audience-specific');
    var $segmentSection = $('#apppush-audience-segment');
    var $memberpressSection = $('#apppush-audience-memberpress');
    var $memberpressSelect = $('#apppush_memberpress_membership');
    var $rcpSection = $('#apppush-audience-rcp');
    var $rcpSelect = $('#apppush_rcp_level');
    var $totalCount = $('#apppush-total-count');
    var $validCount = $('#apppush-valid-count');

    function toggleAudienceSections() {
        var selected = $audienceRadios.filter(':checked').val();
        
        $roleSection.hide();
        $specificSection.hide();
        $segmentSection.hide();
        $memberpressSection.hide();
        $rcpSection.hide();
        $('#apppush-counts').show();

        if (selected === 'role') {
            $roleSection.show();
        } else if (selected === 'specific') {
            $specificSection.show();
        } else if (selected === 'segment') {
            $segmentSection.show();
            $('#apppush-counts').hide();
        } else if (selected === 'memberpress') {
            $memberpressSection.show();
        } else if (selected === 'rcp') {
            $rcpSection.show();
        }
        
        if (selected !== 'segment') {
            updateRecipientCount();
        }
    }

    $audienceRadios.on('change', toggleAudienceSections);
    
    // MemberPress membership change
    $memberpressSelect.on('change', function() {
        if ($audienceRadios.filter(':checked').val() === 'memberpress') {
            updateRecipientCount();
        }
    });
    
    // RCP membership level change
    $rcpSelect.on('change', function() {
        if ($audienceRadios.filter(':checked').val() === 'rcp') {
            updateRecipientCount();
        }
    });
    
    // Role checkboxes change
    $('input[name="apppush_roles[]"]').on('change', updateRecipientCount);

    // --- User Search Logic ---
    var $userSearch = $('#apppush-user-search');
    var $selectedUsersContainer = $('#apppush-selected-users');
    var $specificUsersInput = $('#apppush_specific_users');
    var selectedUsers = [];

    $userSearch.autocomplete({
        source: function(request, response) {
            $.ajax({
                url: apppush_local.ajax_url,
                dataType: "json",
                data: {
                    action: 'apppush_search_users',
                    term: request.term,
                    nonce: apppush_local.nonce
                },
                success: function(data) {
                    if (data.success) {
                        var mappedData = $.map(data.data, function(item) {
                            return {
                                label: item.text,
                                value: item.text,
                                id: item.id,
                                text: item.text
                            };
                        });
                        response(mappedData);
                    } else {
                        response([]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui) {
            addUser(ui.item);
            $userSearch.val('');
            return false;
        }
    });

    function addUser(user) {
        // Check if already selected
        if (selectedUsers.some(u => u.id === user.id)) {
            return;
        }

        selectedUsers.push(user);
        renderSelectedUsers();
        updateRecipientCount();
    }

    function removeUser(userId) {
        selectedUsers = selectedUsers.filter(u => u.id !== userId);
        renderSelectedUsers();
        updateRecipientCount();
    }

    function renderSelectedUsers() {
        $selectedUsersContainer.empty();
        var ids = [];
        
        selectedUsers.forEach(function(user) {
            ids.push(user.id);
            var $tag = $('<span class="apppush-user-tag">' + user.text + ' <span class="remove-user" data-id="' + user.id + '">&times;</span></span>');
            $selectedUsersContainer.append($tag);
        });

        $specificUsersInput.val(ids.join(','));
    }

    $(document).on('click', '.remove-user', function() {
        var id = $(this).data('id');
        removeUser(id);
    });

    // --- Recipient Count Logic ---
    function updateRecipientCount() {
        $totalCount.text('Calculating...');
        $validCount.text('Calculating...');
        
        var audience = $audienceRadios.filter(':checked').val();
        var data = [];

        if (audience === 'role') {
            $('input[name="apppush_roles[]"]:checked').each(function() {
                data.push($(this).val());
            });
        } else if (audience === 'specific') {
            if ($specificUsersInput.val()) {
                data = $specificUsersInput.val().split(',');
            }
        } else if (audience === 'memberpress') {
            data = $memberpressSelect.val();
        } else if (audience === 'rcp') {
            data = $rcpSelect.val();
        }

        $.ajax({
            url: apppush_local.ajax_url,
            type: 'POST',
            dataType: "json",
            data: {
                action: 'apppush_get_recipient_count',
                type: audience,
                data: data,
                nonce: apppush_local.nonce
            },
            success: function(response) {
                if (response.success) {
                    $totalCount.text(response.data.total);
                    $validCount.text(response.data.valid);
                } else {
                    $totalCount.text('Error');
                    $validCount.text('Error');
                }
            }
        });
    }

    // --- Segments Visibility Logic ---
    function checkSegmentsAvailability() {
        var $segmentOption = $('input[name="apppush_audience"][value="segment"]').closest('label');
        var $segmentDropdown = $('#apppush-audience-segment select[name="segments"]');
        
        // Check if the segment dropdown exists and has options beyond the default
        if ($segmentDropdown.length) {
            var optionCount = $segmentDropdown.find('option').length;
            // If only 1 option (the "All subscribers" default) or 0 options, hide the segment option
            if (optionCount <= 1) {
                $segmentOption.addClass('apppush-segment-hidden');
                // If segment was selected, switch to 'all'
                if ($audienceRadios.filter(':checked').val() === 'segment') {
                    $('input[name="apppush_audience"][value="all"]').prop('checked', true).trigger('change');
                }
            } else {
                $segmentOption.removeClass('apppush-segment-hidden');
            }
        } else {
            // No segment dropdown at all, hide the option
            $segmentOption.addClass('apppush-segment-hidden');
        }
    }

    // --- Required Fields Validation ---
    function validateRequiredFields() {
        var title = $appPushTitle.val().trim() || $wpTitle.val().trim();
        var message = $messageInput.val().trim();
        var $publishButton = $('#publish, input#publish');
        
        // Check if both fields have content
        if (title && message) {
            $publishButton.prop('disabled', false).removeClass('button-disabled');
        } else {
            $publishButton.prop('disabled', true).addClass('button-disabled');
        }
    }

    // Validate on input
    $appPushTitle.on('input keyup', function() {
        validateRequiredFields();
    });

    $wpTitle.on('input keyup', function() {
        validateRequiredFields();
    });

    $messageInput.on('input keyup', function() {
        validateRequiredFields();
    });

    // Initial validation
    validateRequiredFields();

    // Initial setup
    toggleActionSections();
    toggleAudienceSections();
    checkSegmentsAvailability();
    lockFieldsIfSent();

});
