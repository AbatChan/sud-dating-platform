var SUD = SUD || {};

(function($) {
    'use strict';

    SUD.toastTimeout = null;
    SUD.stripe = null;
    SUD.elements = null;
    SUD.paymentSuccessAnim = null;
    SUD.notificationsLoaded = false;
    SUD.isMessagePollingActive = false;
    SUD.realTimeInterval = null;

    SUD.sidebarTimeUpdateInterval = null;
    let dynamicTooltipElement = null;
    let hideTooltipTimeout = null;

    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    $(document).ready(function() {
        try {
            SUD.initConfig();

            SUD.initUserFavorites();
            SUD.initProfileDropdowns();
            SUD.initPhotoViewer();
            SUD.initNotifications();
            SUD.initModalClosing();
            SUD.initAlerts();
            SUD.initUserCardClickable();
            SUD.initDashboardFilterModal();

            if ($('.messages-container').length) {
                SUD.initMessaging(); 
            } else {
                SUD.isMessagePollingActive = false; 
            }
            if ($('.premium-container').length) {
                SUD.initBillingToggle();
            }
            if ($('.profile-container').length && typeof sud_config !== 'undefined' && sud_config.profile_id) {
                SUD.initProfileActions();
            }

            if ($('.user-grid').length) {
                SUD.initMemberTabs();
                SUD.initLoadMoreMembers();
            }

            if (typeof sud_config !== 'undefined' && sud_config.is_logged_in && !SUD.isMessagePollingActive) {
                SUD.initRealTimeUpdates();
            }
            SUD.initGeneralPremiumPrompt();
        } catch (error) {
            console.error('[SUD Error] Initialization failed:', error);
        }
    });

    SUD.initConfig = function() {
        window.sud_config = {...sud_config_base};
        if (typeof sud_page_specific_config !== 'undefined') {
            window.sud_config = { ...window.sud_config, ...sud_page_specific_config };
        }

        if (typeof sud_swipe_page_config !== 'undefined' && sud_swipe_page_config.sounds) {
            if (!window.sud_config.sounds) {
                window.sud_config.sounds = {};
            }
            window.sud_config.sounds = { ...window.sud_config.sounds, ...sud_swipe_page_config.sounds };
        }
        
        if (typeof sud_payment_settings === 'undefined') {
            //console.warn("Payment settings not localized, will attempt to fetch dynamically if needed.");
        } else {
            window.sud_config.payment_settings = sud_payment_settings;
        }
        if ($('.profile-container').length && typeof sud_profile_id !== 'undefined') {
            window.sud_config.profile_id = sud_profile_id;
        }
        return window.sud_config;
    };

    SUD.updateSidebarTimestamps = function() {
        const $timeElements = $('.conversation-item .time'); // Select all time elements
        if (!$timeElements.length) return;

        $timeElements.each(function() {
            const $el = $(this);
            const $item = $el.closest('.conversation-item');
            // Retrieve the stored UNIX timestamp
            const lastTimestampUnix = $item.data('last-timestamp-unix');
    
            if (lastTimestampUnix && !isNaN(parseInt(lastTimestampUnix))) {
                const newRelativeTime = SUD.formatRelativeTime(parseInt(lastTimestampUnix));
                // Update only if the text changed to avoid unnecessary DOM manipulation
                if ($el.text() !== newRelativeTime) {
                    $el.text(newRelativeTime);
                }
            }
        });
    };    

    SUD.initUserFavorites = function() {
        $(document).off('click.favoriteToggle').on('click.favoriteToggle', '.user-favorite', function(e) {
            e.preventDefault(); e.stopPropagation();
            const $favoriteElement = $(this);
            if ($favoriteElement.data('processing') === true) return;

            const userId = $favoriteElement.data('user-id');

            let $icon = $favoriteElement.find('i.fa-heart').first();
            if (!$icon.length && $favoriteElement.is('i.fa-heart')) $icon = $favoriteElement;

            if (!$icon.length) { console.error("No heart icon:", $favoriteElement); $favoriteElement.removeData('processing').prop('disabled', false); return; }

            const isCurrentlySolid = $icon.hasClass('fas');
            const isValidId = userId && !isNaN(parseInt(userId)) && parseInt(userId) > 0;

            if (!isValidId || typeof sud_config === 'undefined' || !sud_config.sud_url) {
                 console.warn(`Favorite toggle skipped: Invalid User ID (${userId}) or sud_config not defined/incomplete.`);
                 SUD.showToast('error', 'Error', 'Cannot update favorite status.');
                 return; 
            }

            $favoriteElement.data('processing', true).prop('disabled', true); 

            const shouldBeFavorited = !isCurrentlySolid;
            const originalIconClasses = $icon.attr('class');
            const originalElementClasses = $favoriteElement.attr('class');

            if (shouldBeFavorited) { $icon.removeClass('far').addClass('fas'); }
            else { $icon.removeClass('fas').addClass('far'); }
            $favoriteElement.toggleClass('favorited', shouldBeFavorited);

            const newTitle = shouldBeFavorited ? 'Remove Favorite' : 'Add Favorite';
            $icon.attr('title', newTitle);
            if ($icon.attr('data-original-title')) {
                $icon.attr('data-original-title', newTitle);
            }
            $favoriteElement.attr('title', newTitle);
            if ($favoriteElement.attr('data-original-title')) {
                $favoriteElement.attr('data-original-title', newTitle);
            }

            const newButtonText = shouldBeFavorited ? 'Favorited' : 'Favorite';
            if ($favoriteElement.is('button')) {
                if (!$favoriteElement.hasClass('icon-only-display')) {
                    const newIconClass = shouldBeFavorited ? 'fas' : 'far';
                    $favoriteElement.html(`<i class="${newIconClass} fa-heart"></i> ${newButtonText}`);
                }
            }

            $.ajax({
                url: `${sud_config.sud_url}/ajax/save-selection.php`,
                type: 'POST',
                data: { action: 'toggle_favorite', user_id: userId, favorite: shouldBeFavorited ? 1 : 0 },
                dataType: 'json',
                success: function(response) {
                    if (!response || !response.success) {

                        $icon.attr('class', originalIconClasses);
                        $favoriteElement.attr('class', originalElementClasses);
                        SUD.showToast('error', 'Error', response?.message || 'Could not update favorite status.');
                    }
                },
                error: function(xhr) {
                    $icon.attr('class', originalIconClasses);
                    $favoriteElement.attr('class', originalElementClasses);
                    SUD.showToast('error', 'Network Error', 'Could not update favorite status.');
                    console.error("Favorite toggle error:", xhr.status, xhr.responseText);
                },
                complete: function() {
                    $favoriteElement.removeData('processing').prop('disabled', false);
                }
            });
        });
    };

    SUD.initProfileDropdowns = function() {

        $(document).off('click.toggleDropdown').on('click.toggleDropdown', '.message-dropdown-toggle, .user-dropdown-toggle', function(e) {
            e.stopPropagation();
            const $thisToggle = $(this);
            const $currentMenu = $thisToggle.next('.dropdown-menu, .user-utility-dropdown-menu');

            $('.dropdown-menu.show, .user-utility-dropdown-menu.show').not($currentMenu).removeClass('show');
            $('.message-dropdown-toggle.active, .user-dropdown-toggle.active').not($thisToggle).removeClass('active');

            $currentMenu.toggleClass('show');
            $thisToggle.toggleClass('active'); 
        });

        const $profileMenu = $('#user-profile-menu');
        const $profileDropdown = $('#profile-dropdown');
        if ($profileMenu.length && $profileDropdown.length) {
            let hideProfileTimeout;
            const setupHover = ($el, $target) => {
                $el.on('mouseenter', () => { clearTimeout(hideProfileTimeout); $target.addClass('show'); })
                   .on('mouseleave', () => { hideProfileTimeout = setTimeout(() => $target.removeClass('show'), 300); });
            };
            setupHover($profileMenu, $profileDropdown);
            setupHover($profileDropdown, $profileDropdown); 

            $('.user-profile-link', $profileMenu).on('click', (e) => e.stopPropagation());
        }

        $(document).on('click.closeDropdowns', function(e) {
            const $target = $(e.target);

            if ($profileMenu.length && !$profileMenu.is($target) && $profileMenu.has($target).length === 0 && !$profileDropdown.is($target) && $profileDropdown.has($target).length === 0) {
                $profileDropdown.removeClass('show');
            }

            if (!$target.closest('.dropdown, .user-profile-dropdown').length) {
                $('.dropdown-menu, .user-utility-dropdown-menu').removeClass('show');
                $('.message-dropdown-toggle, .user-dropdown-toggle').removeClass('active');
            }
        });
    };

    SUD.initPhotoViewer = function() {

        $(document).on('click', '.photo-img', function() {
            const $img = $(this);
            const imgSrc = $img.attr('src');

            if (!imgSrc || imgSrc.includes('default-profile.') || $img.closest('.no-photo').length) {
                return;
            }

            $('.photo-modal').remove();

            const $modal = $('<div class="photo-modal"></div>');
            const $closeBtn = $('<span class="modal-close" title="Close">×</span>');
            const $image = $('<img class="modal-img">').attr('src', imgSrc).on('error', function() {
                console.warn("Modal image failed to load:", imgSrc);
                $modal.remove(); 
            });

            $modal.append($closeBtn).append($image);
            $('body').append($modal);
            setTimeout(() => $modal.addClass('show'), 10); 
            $modal.on('click', function(e) {

                if (e.target === this || $(e.target).hasClass('modal-close')) {
                    $modal.removeClass('show');
                    setTimeout(() => $modal.remove(), 300);
                }
            });

             $(document).on('keydown.photoModal', function(e) {
                 if (e.key === "Escape") {
                     $modal.removeClass('show');
                     setTimeout(() => $modal.remove(), 300);
                     $(document).off('keydown.photoModal'); 
                 }
             });
        });
    };

    SUD.initNotifications = function() {
        const $toggle = $('#notification-toggle');
        const $dropdown = $('#notification-dropdown');

        $toggle.on('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            $dropdown.toggleClass('show');
            $toggle.toggleClass('active'); 

            // Always fetch notifications when bell is clicked with skeleton loader
            if ($dropdown.hasClass('show')) {
                SUD.loadNotifications(false, true); // Always show skeleton when user opens modal
            }
        });

        $(document).on('click.notifDropdown', (e) => {
             if (!$dropdown.is(e.target) && $dropdown.has(e.target).length === 0 && !$toggle.is(e.target) && $toggle.has(e.target).length === 0) {
                 $dropdown.removeClass('show');
                 $toggle.removeClass('active');
             }
        });

        $(document).on('click', '.mark-all-read', function(e) {
            e.preventDefault(); e.stopPropagation();

            $('#notification-list .notification-item').removeClass('unread');
            $('#notification-list .unread-indicator').remove();
            $('.notification-badge').hide().text('0');
            SUD.notificationsLoaded = true;

            if (typeof sud_config !== 'undefined' && sud_config.sud_url) {

                $.ajax({
                    url: `${sud_config.sud_url}/ajax/get-notifications.php`,
                    type: 'POST',
                    data: { action: 'mark_all_read' },
                    dataType: 'json',
                    success: function(response) {
                        if (!response || !response.success) {
                            console.warn("Mark all read failed on backend:", response?.message);

                        } else {
                             SUD.updateNotificationCount(0);
                        }
                    },
                    error: function(xhr) {
                         console.error("Mark all read AJAX error:", xhr.status, xhr.responseText);

                    }
                });
            } else {
                console.error("sud_config not defined or incomplete for mark all read.");
            }
        });

        $(document).ready(function() {
            $(document).on('click', '.notification-item', function(e) {
                const $item = $(this);
                const notificationText = $item.find('.notification-content-text').text();
                const notificationType = $item.data('type');
                const relatedId = $item.data('related-id');
                const notificationId = $item.data('id');
        
                // Mark notification as read if unread
                if (notificationId && !$item.hasClass('read')) {
                    $item.removeClass('unread').addClass('read');
                    $item.find('.unread-indicator').remove();
                    $.ajax({
                        url: `${sud_config.sud_url}/ajax/get-notifications.php`,
                        type: 'POST',
                        data: { action: 'mark_read', notification_id: notificationId },
                        dataType: 'json'
                    });
                }

                // Check if this is a premium teaser notification
                const isPremiumTeaser = 
                    (notificationType === 'favorite' || notificationType === 'profile_view') && 
                    (notificationText.includes('Upgrade to Premium') || 
                     notificationText.includes('Someone viewed your profile') || 
                     notificationText.includes('Someone favorited you'));
        
                if (isPremiumTeaser && typeof sud_config !== 'undefined' && 
                    sud_config.sud_url && !sud_config.user_can_view_profiles) {
                    const upgradeUrl = $item.data('upgrade-url') || `${sud_config.sud_url}/pages/premium`;
                    window.location.href = upgradeUrl;
                    return false;
                }

                // Route based on notification type
                let targetUrl = '';

                switch(notificationType) {
                    case 'message':
                    case 'gift':
                        // Route to messages page with specific user
                        if (relatedId) {
                            targetUrl = `${sud_config.sud_url}/pages/messages?user=${relatedId}`;
                        } else {
                            targetUrl = `${sud_config.sud_url}/pages/messages`;
                        }
                        break;

                    case 'match':
                        // Route to profile page for matches (both regular and instant)
                        if (relatedId) {
                            targetUrl = `${sud_config.sud_url}/pages/profile?id=${relatedId}`;
                        } else {
                            // If no related ID, go to matches page
                            targetUrl = `${sud_config.sud_url}/pages/activity?tab=my-matches`;
                        }
                        break;

                    case 'instant_match':
                        // Route to profile page for instant matches
                        if (relatedId) {
                            targetUrl = `${sud_config.sud_url}/pages/profile?id=${relatedId}`;
                        } else {
                            // If no related ID, go to instant matches page
                            targetUrl = `${sud_config.sud_url}/pages/activity?tab=instant-matches`;
                        }
                        break;

                    case 'profile_view':
                    case 'favorite':
                    case 'new_favorite':
                        // For premium users, route to profile; for non-premium, route to premium page
                        if (relatedId && sud_config.user_can_view_profiles) {
                            targetUrl = `${sud_config.sud_url}/pages/profile?id=${relatedId}`;
                        } else if (relatedId) {
                            // Non-premium user trying to view who favorited/viewed
                            targetUrl = `${sud_config.sud_url}/pages/premium`;
                        } else {
                            // Fallback to activity page
                            const tab = notificationType === 'profile_view' ? 'profile-views' : 'favorited-me';
                            targetUrl = `${sud_config.sud_url}/pages/activity?tab=${tab}`;
                        }
                        break;

                    case 'system':
                    case 'account_banned':
                    case 'account_warning':
                    case 'profile_hidden':
                        // Route to settings or dashboard for system notifications
                        targetUrl = `${sud_config.sud_url}/pages/settings`;
                        break;

                    default:
                        // Default fallback to dashboard
                        targetUrl = `${sud_config.sud_url}/pages/dashboard`;
                        break;
                }

                // Navigate to the target URL
                if (targetUrl) {
                    window.location.href = targetUrl;
                }
            });
        
            $('.notification-item').each(function() {
                const $item = $(this);
                const notificationText = $item.find('.notification-content-text').text();
                const notificationType = $item.data('type');
        
                if ((notificationType === 'favorite' || notificationType === 'profile_view') && 
                    (notificationText.includes('Upgrade to Premium') || 
                     notificationText.includes('Someone viewed your profile') || 
                     notificationText.includes('Someone favorited you'))) {
        
                    $item.addClass('premium-teaser');
        
                    if ($item.find('.premium-indicator').length === 0) {
                        $item.append('<span class="premium-indicator"><i class="fas fa-lock"></i></span>');
                    }
                }
            });
        });
    };

    SUD.initAlerts = function() {
        $(document).on('click', '.alert-close', function() {
            $(this).closest('.alert').fadeOut(300, function() { $(this).remove(); });
        });

        setTimeout(() => {
            $('.alert:not(.persistent)').fadeOut('slow', function() { $(this).remove(); });
        }, 5000); 
    };

    SUD.initModalClosing = function() {
        $(document).on('click', '.close-modal, .close-modal-btn', function() {
            const $modal = $(this).closest('.modal, .payment-modal');

            if (document.activeElement && $modal[0].contains(document.activeElement)) {
                document.activeElement.blur();
            }

            // Don't allow closing the claim modal
            if (!$modal.hasClass('claim-modal-no-dismiss')) {
                $modal.removeClass('show');
            }
        });
        $(document).on('click', '.modal, .payment-modal', function(e) {
            if ($(e.target).is('.modal, .payment-modal')) {
                if (document.activeElement && this.contains(document.activeElement)) {
                    document.activeElement.blur();
                }
                if (!$(this).hasClass('claim-modal-no-dismiss')) {
                    $(this).removeClass('show');
                }
            }
        });

        // Press Escape key to close
        $(document).on('keydown.modalClose', function(e) {
            if (e.key === "Escape") {
                // Find the currently shown modal
                const $shownModal = $('.modal.show:not(.claim-modal-no-dismiss), .payment-modal.show:not(.claim-modal-no-dismiss)');
                if ($shownModal.length > 0) {
                    if (document.activeElement && $shownModal[0].contains(document.activeElement)) {
                        document.activeElement.blur();
                    }
                    $shownModal.removeClass('show');
                }
            }
        });
    };

    SUD.initUserCardClickable = function() {
        $(document).off('click.cardNavigate').on('click.cardNavigate', '.user-card, .user-card-list', function(e) {
            const cardElement = $(this);
            const ignoreSelectors = [
                '.user-favorite', 
                'a',              
                'button',         
                '.message-button', 
                '.user-card-list-actions' 
            ];
            if ($(e.target).closest(ignoreSelectors.join(', ')).length) {
                return; 
            }
            
            let profileUrl = cardElement.data('profile-url');
            
            // Fallback: Generate profile URL from user ID if profile URL is missing
            if (!profileUrl || profileUrl === '#' || profileUrl === 'javascript:void(0);') {
                const userId = cardElement.data('user-id');
                if (userId && typeof sud_config_base !== 'undefined' && sud_config_base.sud_url) {
                    profileUrl = sud_config_base.sud_url + '/pages/profile?id=' + userId;
                }
            }
            
            if (profileUrl && profileUrl !== '#' && profileUrl !== 'javascript:void(0);') {
                window.location.href = profileUrl;
            } else {
                console.warn("User card clicked but no valid profile URL found. User ID:", cardElement.data('user-id'));
            }
        });
    };

    SUD.initProfileActions = function() {
        $(document).on('click', '.user-profile-dropdown [data-modal-target]', function(e){
            e.preventDefault();
            const targetModalSelector = $(this).data('modal-target');
            const $targetModal = $(targetModalSelector);
            if ($targetModal.length) {
                $('.user-utility-dropdown-menu').removeClass('show');
                $targetModal.addClass('show');
            } else {
                 console.warn("Modal target not found:", targetModalSelector);
            }
        });

        $('#confirm-block-profile, #confirm-unblock-profile').on('click', function(){
            const $button = $(this);
            const action = $button.data('action'); 
            const $modal = $button.closest('.modal');
            const originalText = $button.text();

            if (!action || typeof sud_config === 'undefined' || !sud_config.profile_id || $button.prop('disabled')) {
                console.warn("Block/Unblock action skipped: Missing data or already processing.", action, sud_config?.profile_id);
                return;
            }

            $button.prop('disabled', true).text(action === 'block' ? 'Blocking...' : 'Unblocking...');

            $.ajax({
                url: `${sud_config.sud_url}/ajax/block-user.php`,
                type: 'POST',
                data: {
                    user_id: sud_config.profile_id,
                    action: action,
                    nonce: sud_config.ajax_nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response && response.success) {
                        const userName = sud_config.profile_name || 'User';
                        SUD.showToast('success', (action === 'block' ? 'User Blocked' : 'User Unblocked'), response.message || `${userName} has been ${action}ed.`);
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        SUD.showToast('error', 'Error', response?.message || `Failed to ${action} user.`);
                        $button.prop('disabled', false).text(originalText);
                    }
                },
                error: (xhr) => {
                    SUD.showToast('error', 'Network Error', `Could not ${action} user due to a network issue.`);
                    $button.prop('disabled', false).text(originalText);
                    console.error(`Block/Unblock AJAX error (${action}):`, xhr.status, xhr.responseText);
                },
                complete: () => {

                    if ($modal) $modal.removeClass('show');
                }
            });
        });

         $('#report-profile-form').on('submit', function(e) {
            e.preventDefault();
             const $form = $(this);
             const $modal = $form.closest('.modal');
             const userId = sud_config.profile_id;
             const reason = $form.find('#report-profile-reason').val();
             const details = $form.find('#report-profile-details').val();

             if (!userId || !reason) {
                 SUD.showToast('error', 'Incomplete Report', 'Please select a reason for the report.');
                 return;
             }

             const $btn = $form.find('button[type="submit"]');
             const originalBtnText = $btn.text();
             $btn.prop('disabled', true).text('Submitting...');

             $.ajax({
                 url: `${sud_config.sud_url}/ajax/report-user.php`,
                 type: 'POST',
                 data: {
                    user_id: userId,
                    reason: reason,
                    details: details,
                    nonce: sud_config.ajax_nonce
                 },
                 dataType: 'json',
                 success: (response) => {
                     if (response && response.success) {
                        const profileName = $('#report-username-profile').text() || sud_config.profile_name || 'User';
                        SUD.showToast('success', 'Report Submitted', `Your report regarding ${profileName} has been submitted.`);
                        if ($modal) $modal.removeClass('show');
                        $form[0].reset();
                    } else {
                        SUD.showToast('error', 'Submission Failed', response?.message || 'Could not submit the report.');
                    }
                },
                error: (xhr) => {
                    SUD.showToast('error', 'Network Error', 'Failed to submit report due to a network issue.');
                    console.error("Report User AJAX error:", xhr.status, xhr.responseText);
                },
                complete: () => {
                    $btn.prop('disabled', false).text(originalBtnText);
                }
            });
        });
    };

    SUD.updateHeaderCoinBalance = function(newBalance) {
        setTimeout(() => {
            // Update all coin balance displays (header + messages page)
            const $coinCountElements = $('.coin-count[data-coin-balance]');
            if ($coinCountElements.length) {
                const finalFormattedString = typeof newBalance === 'number' ? newBalance.toLocaleString() : String(newBalance);
                
                // Force update all elements - don't check if changed since DOM might be stale
                $coinCountElements.each(function() {
                    const $element = $(this);
                    $element.text(finalFormattedString);
                });
            }
        }, 50); 
    };

    SUD.initMessaging = function() {
        setTimeout(function() {
            recalculateTotalUnreadCount();
        }, 500);
        const $messageContainer = $('.messages-container');
        if (!$messageContainer.length) {
            return;
        }

        let isLoadingOlder = false;
        let hasMoreOlderMessages = true; 
        let oldestMessageId = 0;
        let messageCheckInterval = null;
        let messageErrorCount = 0;
        let isInitialLoad = true;
        const currentUserId = sud_config.current_user_id; 

        const $messageContent = $('.message-content');
        const $messageInput = $('#message-input');
        const $emojiPicker = $('#emoji-picker-container');
        const $sendMessageForm = $('#message-form');
        const $receiverIdInput = $('#receiver-id'); 
        const $sidebar = $('.message-sidebar');
        const $mainArea = $('.message-main');
        const $allConversationsList = $('.all-conversations');
        const $unreadConversationsList = $('.unread-conversations');
        const $unreadTabCount = $('.message-tabs .tab[data-tab="unread"] .unread-tab-count');

        const $giftDrawer = $('#gift-drawer');
        const $giftDrawerItems = $('#gift-drawer-items');
        const $giftDrawerToggle = $('#gift-drawer-toggle');
        let availableGifts = []; 

        let activePartnerId = sud_config.active_partner_id;
        let activePartnerName = sud_config.active_partner_name;
        const isBlockedView = sud_config.is_blocked_view;

        function initializeChatView() {
            scrollToBottom(true, false); 
            if (activePartnerId) {
                oldestMessageId = $('.message-bubble[data-id]:first, .system-message[data-id]:first', $messageContent).data('id') || 0;

                if ($messageContent.children(':not(.loading-spinner, .no-messages-yet, .loading-spinner-older, .no-more-messages)').length === 0) {
                    hasMoreOlderMessages = true; 
                    $messageContent.find('.loading-spinner').hide();
                     if(!isBlockedView) { 
                        $messageContent.html('<div class="no-messages-yet"><p>Start the conversation!</p></div>');
                     }
                } else {
                    hasMoreOlderMessages = true; 
                }
            } else {
                hasMoreOlderMessages = false; 
            }
            if (activePartnerId && !isBlockedView) {
                SUD.isMessagePollingActive = true;
                startMessagePolling();
                loadGifts(); 
            } else {
                SUD.isMessagePollingActive = false;
                $giftDrawer.hide();
                if (isBlockedView) {
                    disableChatInput();
                }
            }
            SUD.updateMessageInputUI(sud_config);
            isInitialLoad = false;
        }

        function initMobileMessagingUI() {
            const $messagesContainer = $('.messages-container');
            const $messagesWrapper = $('.messages-wrapper');
            const $messageSidebar = $('.message-sidebar');
            const $messageMain = $('.message-main');
            
            // Check if we're on mobile
            const isMobileView = window.innerWidth <= 768;
            
            if (isMobileView) {
                // Create back button with unread indicator
                if ($('.back-to-conversations').length === 0) {
                    const totalUnreadCount = parseInt($('.unread-tab-count').text()) || 0;
                    const backBtnHtml = `
                        <div class="back-to-conversations">
                            <a class="back-button">
                                <i class="fas fa-arrow-left"></i>
                                <span>Messages</span>
                                ${totalUnreadCount > 0 ? `<span class="unread-count-badge">${totalUnreadCount}</span>` : ''}
                            </a>
                        </div>
                    `;
                    
                    $messageMain.prepend(backBtnHtml);
                    
                    // Add click handler to back button
                    $('.back-button').on('click', function() {
                        $messagesContainer.removeClass('conversation-active');
                    });
                }
                
                // If we have an active conversation, show it right away
                if (sud_config && sud_config.active_partner_id) {
                    $messagesContainer.addClass('conversation-active');
                    
                    // Make sure the message content is scrolled to bottom after transition
                    setTimeout(function() {
                        scrollToBottom(true, false);
                    }, 300);
                }
                
                // Handle conversation item clicks on mobile
                $messageSidebar.off('click.mobileConvo').on('click.mobileConvo', '.conversation-item:not(.blocked-user-item)', function(e) {
                    const userId = $(this).data('user-id');
                    const currentActivePartnerId = sud_config.active_partner_id;
                    
                    if (userId && userId == currentActivePartnerId) {
                        e.preventDefault();
                        e.stopPropagation();
                        $messagesContainer.addClass('conversation-active');
                        setTimeout(function() {
                            scrollToBottom(true, false);
                        }, 300);
                    }
                });
            }
            
            // Update back button unread count when the total count changes
            const originalRecalculateTotalUnreadCount = recalculateTotalUnreadCount;
            recalculateTotalUnreadCount = function() {
                const result = originalRecalculateTotalUnreadCount.apply(this, arguments);
                updateBackButtonUnreadCount();
                return result;
            };
            
            function updateBackButtonUnreadCount() {
                const totalUnreadCount = parseInt($('.unread-tab-count').text()) || 0;
                const $badge = $('.back-button .unread-count-badge');
                
                if (totalUnreadCount > 0) {
                    if ($badge.length) {
                        $badge.text(totalUnreadCount).show();
                    } else {
                        $('.back-button').append(`<span class="unread-count-badge">${totalUnreadCount}</span>`);
                    }
                } else {
                    $badge.hide();
                }
            }
            
            // Handle window resize
            $(window).off('resize.mobileMsg').on('resize.mobileMsg', function() {
                const wasNotMobile = !$messagesContainer.hasClass('conversation-active') && sud_config && sud_config.active_partner_id;
                const isNowMobile = window.innerWidth <= 768;
                
                if (isNowMobile) {
                    if (wasNotMobile) {
                        // Re-init when switching to mobile
                        initMobileMessagingUI();
                    }
                } else {
                    // Switching from mobile to desktop view
                    $messagesContainer.removeClass('conversation-active');
                }
            });
        }

        initMobileMessagingUI();

        $sidebar.off('click.loadConvo').on('click.loadConvo', '.conversation-item:not(.blocked-user-item)', function() {
            const userId = $(this).data('user-id');
            const currentActivePartnerId = sud_config.active_partner_id; 
            if (userId && userId != currentActivePartnerId) {
                window.location.href = `${sud_config.sud_url}/pages/messages?user=${userId}`;
            }

        });

        function disableChatInput() {
            $messageInput.prop('disabled', true).attr('placeholder', 'Interaction disabled');
            $sendMessageForm.find('button').prop('disabled', true);
            $('#initiate-video-call, #send-gift').prop('disabled', true);
            $giftDrawer.hide(); 
        }

        function scrollToBottom(instant = false, isIncoming = false) { 
            const $messageContent = $('.message-content');
            if ($messageContent.length) {
                setTimeout(() => {
                    const scrollHeight = $messageContent[0].scrollHeight;
                    const currentScrollTop = $messageContent.scrollTop();
                    const clientHeight = $messageContent[0].clientHeight;
        
                    if (instant) {
                        $messageContent.scrollTop(scrollHeight);
                    } else {
                        if (currentScrollTop + clientHeight < scrollHeight - 10) {
                            $messageContent.stop().animate({ scrollTop: scrollHeight }, 300);
                        } else {
                            $messageContent.scrollTop(scrollHeight);
                        }
                    }

                    const isScrolledUp = currentScrollTop + clientHeight < scrollHeight - 50;
                    $messageContent.find('.new-messages-indicator').remove();
        
                    if (isIncoming && isScrolledUp) {
                        const $indicator = $('<div class="new-messages-indicator"><i class="fas fa-chevron-down"></i> New messages</div>');
                        $messageContent.append($indicator);

                        $indicator.on('click', function() {
                            scrollToBottom(true, false);
                        });
        
                        let scrollTimeout;
                        $messageContent.off('scroll.newMsgIndicator').on('scroll.newMsgIndicator', function() {
                            clearTimeout(scrollTimeout);
                            scrollTimeout = setTimeout(() => {
                                const currentScroll = $(this).scrollTop();
                                const totalHeight = this.scrollHeight;
                                const viewHeight = $(this).innerHeight();
                                if (currentScroll + viewHeight >= totalHeight - 30) { 
                                    $messageContent.find('.new-messages-indicator').fadeOut(function() { $(this).remove(); });
                                    $messageContent.off('scroll.newMsgIndicator'); 
                                }
                            }, 150);
                        });
        
                        setTimeout(() => {
                            $messageContent.find('.new-messages-indicator').fadeOut(function() { $(this).remove(); });
                            $messageContent.off('scroll.newMsgIndicator');
                        }, 8000); 
                    }
                }, 50);
            }
        }

        scrollToBottom(true, false);

        const hasActiveChat = sud_config && sud_config.active_partner_id;
        if (hasActiveChat) {
             oldestMessageId = $('.message-bubble:first', $messageContent).data('id') || 0;
             if ($('.message-bubble', $messageContent).length === 0 && !$messageContent.find('.no-messages-yet').length) {
                 hasMoreOlderMessages = false;
                  $messageContent.find('.loading-spinner').hide();
             } else if ($messageContent.find('.no-messages-yet').length) {
                 hasMoreOlderMessages = false;
             }
        } else {
            hasMoreOlderMessages = false;
        }

        if (sud_config && sud_config.is_blocked_view) {
            $messageInput.prop('disabled', true).attr('placeholder', 'User is blocked');
            $sendMessageForm.find('button').prop('disabled', true);
            $('#initiate-video-call, #send-gift').prop('disabled', true);
            SUD.isMessagePollingActive = false;
        } else if (hasActiveChat) {
            SUD.isMessagePollingActive = true;
            startMessagePolling();
        } else {
            SUD.isMessagePollingActive = false; 
        }
        isInitialLoad = false; 

        $messageContent.on('scroll', function() {
            if (this.scrollTop < 100 && !isLoadingOlder && hasMoreOlderMessages && activePartnerId) {
                loadOlderMessages();
            }
        });

        $('.message-tabs .tab').click(function() {
            const $thisTab = $(this);
            if ($thisTab.hasClass('active')) return;

            const tabType = $thisTab.data('tab');
            $('.message-tabs .tab').removeClass('active');
            $thisTab.addClass('active');

            $('.conversations-container').removeClass('active');
            $(`.${tabType}-conversations`).addClass('active');
        });

        $sidebar.on('click', '.conversation-item', function() {
            const userId = $(this).data('user-id');
            if (userId && userId != activePartnerId) {
                window.location.href = `${sud_config.sud_url}/pages/messages?user=${userId}`;
            }
        });

         $sidebar.on('click', '.blocked-user-item .unblock-btn', function() {
             const userId = $(this).data('user-id');
             const $listItem = $(this).closest('.blocked-user-item');
             unblockUser(userId, $listItem);
         });

        $messageInput.on('input', function() {
            this.style.height = 'auto';
            const initialHeight = 42;
            this.style.height = Math.max(initialHeight, Math.min(this.scrollHeight, 150)) + 'px';
        })
        .on('keydown', function(e) {
             if (e.key === 'Enter' && !e.shiftKey) {
                 e.preventDefault();
                 $sendMessageForm.submit();
             }
        });

        const emojis = ["😊", "😂", "😍", "😎", "😘", "😉", "🤔", "😢", "🤣", "😁", "🥰", "😇", "🤩", "😋", "😌", "🙄", "😒", "😏", "😜", "🤗", "🤭", "😻", "😃", "😛", "😅", "😄", "🙃", "😼", "😶", "😈", "🥺", "🤤", "😵", "🥵", "🥶", "😤", "😭", "😩", "🤯", "😳", "😵‍💫", "🫠", "😞", "😖", "🥲", "💀", "☠️", "👻", "👽", "🤖", "🙈", "🙉", "🙊", "😹", "🫣", "❤️", "💕", "💖", "💘", "💓", "💞", "💝", "💜", "💙", "💚", "💛", "🧡", "🤍", "🤎", "🖤", "🌹", "💋", "💍", "💌", "🫶", "🍆", "💦", "👅", "👀", "🫦", "🥒", "🍑", "💨", "👄", "💢", "🔥", "💯", "✨", "🎶", "💃", "🕺", "🛏", "🔞", "🥂", "🍷", "🎉", "🎊", "🎈", "🎁", "🎀", "⭐", "🌟", "🎇", "🎵", "🎸", "🎤", "🎧", "🎥", "📸", "📺", "🍓", "🍒", "🍩", "🍪", "🍰", "🎂", "🧁", "🍫", "🍔", "🌮", "🍎", "🍇", "🍍", "🥑", "🍜", "🍣", "☕", "🍼", "🍵", "🫖", "👠", "🦵", "👣", "💪", "👫", "👰"]; 
        const $emojiGrid = $('.simple-emoji-grid');
        if ($emojiGrid.length && $emojiGrid.is(':empty')) { 
            emojis.forEach(emoji => $emojiGrid.append(`<span class="emoji-item">${emoji}</span>`));
        }
        $('#emoji-button').on('click', (e) => {
            e.stopPropagation();
            $emojiPicker.toggleClass('show');
        });

        $(document).on('click', '.emoji-item', function() {
            const emoji = $(this).text();
            const input = $messageInput[0]; 
            const currentVal = input.value;
            const start = input.selectionStart;
            const end = input.selectionEnd;
            input.value = currentVal.slice(0, start) + emoji + currentVal.slice(end);
            $messageInput.trigger('input').focus();
            input.selectionStart = input.selectionEnd = start + emoji.length;

        });

        $(document).on('click', (e) => {
            if (!$emojiPicker.is(e.target) && $emojiPicker.has(e.target).length === 0 && !$('#emoji-button').is(e.target) && $('#emoji-button').has(e.target).length === 0) {
                $emojiPicker.removeClass('show');
            }
        });

        $('#block-user').on('click', function(e) { e.preventDefault(); $('.dropdown-menu').removeClass('show'); $('#block-username').text(sud_config.active_partner_name || 'User'); $('#block-modal').addClass('show'); });
        $('#report-user').on('click', function(e) { e.preventDefault(); $('.dropdown-menu').removeClass('show'); $('#report-username').text(sud_config.active_partner_name || 'User'); $('#report-user-form')[0].reset(); $('#report-modal').addClass('show'); });
        $('#clear-messages').on('click', function(e) { e.preventDefault(); $('.dropdown-menu').removeClass('show'); $('#clear-username').text(sud_config.active_partner_name || 'User'); $('#clear-modal').addClass('show'); });
        $('#view-blocked-list').on('click', function(e) {
            e.preventDefault();
            $('.dropdown-menu').removeClass('show');

            $('#blocked-users-modal').addClass('show');

            const $blockedListContainer = $('#blocked-users-list-container');
            if (!$blockedListContainer.data('loaded')) {
                $blockedListContainer.html('<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');

                $.ajax({
                    url: `${sud_config.sud_url}/ajax/get-blocked-users.php`,
                    type: 'GET',
                    dataType: 'json',
                    success: function(response) {
                        if (response && response.success && response.data && typeof response.data.blocked_users !== 'undefined') {

                            if (response.data.blocked_users.length > 0) {
                                renderBlockedUsers(response.data.blocked_users, $blockedListContainer);
                            } else {
                                $blockedListContainer.html('<p style="text-align: center; color: #666;">You haven\'t blocked any users.</p>');
                            }
                        } else {

                            const errorMessage = response?.data?.message || response?.message || 'Error loading blocked users data.'; 
                            $blockedListContainer.html(`<p style="color: red; padding: 15px;">${$('<div>').text(errorMessage).html()}</p>`);
                            console.warn("Failed to load or parse blocked users list:", response);
                        }
                        $blockedListContainer.data('loaded', true); 
                    },

                    error: function(xhr) {
                        $blockedListContainer.html('<p style="color: red; padding: 15px;">Error contacting server to load blocked users. Please try again later.</p>');
                        $blockedListContainer.data('loaded', true);
                        console.error("AJAX Error loading blocked users:", xhr.status, xhr.responseText);
                    }
                });
            }
        });
        $(document).on('click', '#unblock-user-dropdown, #unblock-user-overlay', handleUnblockClick);
        $('#confirm-block-btn').on('click', handleBlockConfirm);
        $('#report-user-form').on('submit', handleReportSubmit);
        $('#confirm-clear-btn').on('click', handleClearConfirm);

        $('#send-gift').on('click', function(){

            if ($giftDrawer.hasClass('open')) {

                $giftDrawerToggle.click();
            }

            else if ($giftDrawer.is(':visible') && !$giftDrawer.hasClass('open')) {

                $giftDrawerToggle.click();
            }

            else if (!$giftDrawer.is(':visible') && activePartnerId && !isBlockedView) {

                 loadGifts();

                 setTimeout(() => {
                    if ($giftDrawer.is(':visible') && !$giftDrawer.hasClass('open')) {
                         $giftDrawerToggle.click();
                    } else if (!$giftDrawer.is(':visible')) {

                    }
                 }, 150); 
            }

            else if (!activePartnerId) {
                SUD.showToast('info', 'Select Chat', 'Please select a conversation to send a gift.');
            }

             else if (isBlockedView) {
                 SUD.showToast('info', 'Blocked', 'Cannot send gifts to a blocked user.');
             }
        });

        $(document).on('click.closeGiftDrawer', function(e) {

            const $sendGiftButton = $('#send-gift'); 

            const isTargetDrawer = $giftDrawer.is(e.target);
            const isTargetInDrawer = $giftDrawer.has(e.target).length > 0;
            const isTargetToggle = $giftDrawerToggle.is(e.target); 
            const isTargetInToggle = $giftDrawerToggle.has(e.target).length > 0;
            const isTargetHeaderGift = $sendGiftButton.is(e.target); 
            const isTargetInHeaderGift = $sendGiftButton.has(e.target).length > 0; 

            if ($giftDrawer.hasClass('open') &&
                !isTargetDrawer &&
                !isTargetInDrawer &&
                !isTargetToggle &&
                !isTargetInToggle &&
                !isTargetHeaderGift &&      
                !isTargetInHeaderGift      
                )
            {
                $giftDrawer.removeClass('open').addClass('collapsed');
                $giftDrawerToggle.attr('title', 'Show More Gifts');
            }
        });

        $('#initiate-video-call').on('click', handleVideoCallClick);
        $sendMessageForm.on('submit', handleSendMessage);
        $giftDrawerToggle.on('click', function() {
            $giftDrawer.toggleClass('open collapsed');
            $(this).attr('title', $giftDrawer.hasClass('open') ? 'Hide Gifts' : 'Show More Gifts');
        });

        $giftDrawerItems.on('click', '.gift-drawer-item', function() {
            const $clickedItem = $(this);
            if ($clickedItem.hasClass('sending')) return;
            const giftId = $clickedItem.data('gift-id');
            const selectedGift = availableGifts.find(g => g.id === giftId);

            if (!selectedGift) { console.warn("Selected gift not found in availableGifts"); return; }
            
            // Remove client-side balance validation - server will handle this securely
            // Client-side validation can be easily bypassed and is not secure
            
            $clickedItem.addClass('sending').css('pointer-events', 'none');
            
            $.ajax({
                url: sud_config.sud_url + '/ajax/send-gift.php',
                method: 'POST', 
                data: { 
                    receiver_id: activePartnerId, 
                    gift_id: giftId,
                    nonce: sud_config.ajax_nonce || ''
                }, 
                dataType: 'json',
                success: function(response) {
                    // Check for upgrade required in success response
                    if (response && response.success && response.data && response.data.upgrade_required) {
                        const upgradeMessage = response.data.message || 'Upgrade required';
                        const cleanMessage = upgradeMessage.replace('upgrade required:', '').trim();
                        
                        // Show upgrade prompt modal
                        const $modal = $('#upgrade-prompt-modal');
                        if ($modal.length) {
                            $modal.find('.modal-reason-text').text(cleanMessage);
                            $modal.find('#upgrade-prompt-title').text('Message Limit Reached');
                            $modal.find('.modal-icon i').removeClass().addClass('fas fa-gift');
                            $modal.addClass('show');
                        } else {
                            SUD.showToast('warning', 'Limit Reached', cleanMessage);
                        }
                        return;
                    }
                    
                    if (response.success) {
                        if (typeof SUD !== 'undefined' && SUD.playNotificationSound) SUD.playNotificationSound('cash');
                        SUD.showToast('success', 'Gift Sent!', response.message || `You sent ${selectedGift.name}!`);
                        
                        // Check both top level and data level for balance info
                        const balanceFormatted = response.new_balance_formatted || response.data?.new_balance_formatted;
                        const balanceNumber = response.new_balance || response.data?.new_balance;
                        
                        if (typeof balanceFormatted !== 'undefined') {
                            SUD.updateHeaderCoinBalance(balanceFormatted);
                            $('#gift-modal-balance').text(balanceFormatted);
                        } else if (typeof balanceNumber !== 'undefined') {
                            SUD.updateHeaderCoinBalance(balanceNumber);
                            $('#gift-modal-balance').text(balanceNumber.toLocaleString());
                        }

                        if (response.data && response.data.message_object) {
                            const newMessage = response.data.message_object;
                            renderMessages([newMessage], 'append');
                            updateSidebarPreviews(
                                parseInt(newMessage.receiver_id),
                                newMessage.message,
                                newMessage.timestamp_unix,
                                true
                            );
                        } else {
                            if (messageCheckInterval) clearTimeout(messageCheckInterval);
                            setTimeout(checkNewMessages, 500);
                            messageCheckInterval = setInterval(checkNewMessages, 4000);
                        }
                        if (SUD.isMessagePollingActive && messageCheckInterval === null) {
                            startMessagePolling();
                        }
                    } else { 
                        // Handle specific error cases with better UX
                        if (response.data && response.data.action_needed === 'purchase_coins') {
                            SUD.showToast('error', 'Insufficient Balance', response.message || 'Not enough coins');
                            // Could add a "Buy Coins" button here in the future
                        } else if (response.data && response.data.action_needed === 'wait') {
                            SUD.showToast('error', 'Please Wait', response.message || 'Too many requests');
                        } else {
                            SUD.showToast('error', 'Failed', response.message || 'Could not send gift');
                        }
                    }
                },
                error: function(xhr) { 
                    let errorMessage = 'Server error sending gift.';
                    try {
                        const errorResponse = JSON.parse(xhr.responseText);
                        if (errorResponse.data && errorResponse.data.message) {
                            errorMessage = errorResponse.data.message;
                        }
                    } catch (e) {
                        console.error("Error parsing server response:", e);
                    }
                    SUD.showToast('error', 'Error', errorMessage);
                },
                complete: function() { $clickedItem.removeClass('sending').css('pointer-events', ''); }
            });
        });
        
        function loadGifts() {
            if (!activePartnerId || isBlockedView) {
                $giftDrawer.hide(); return;
            }
            $giftDrawer.show();
            $giftDrawerItems.html('<div class="gift-placeholder">Loading gifts... <i class="fas fa-spinner fa-spin"></i></div>');

            $.ajax({
                url: sud_config.sud_url + '/ajax/get-gifts.php',
                method: 'GET', dataType: 'json',
                success: function(response) {
                    if (response.success && response.data && response.data.gifts && response.data.gifts.length > 0) {
                        availableGifts = response.data.gifts;
                        renderGifts(availableGifts);
                    } else { $giftDrawerItems.html('<div class="gift-placeholder">No gifts available.</div>'); }
                },
                error: function(xhr) { $giftDrawerItems.html('<div class="gift-placeholder">Error loading gifts.</div>'); console.error("Gift Load Error:", xhr.responseText);}
            });
        }

        function renderGifts(gifts) {
            $giftDrawerItems.empty();
            gifts.forEach(gift => {
                let displayElement = '';
                const giftNameEscaped = escapeHtml(gift.name);
                const giftCost = parseInt(gift.cost) || 0;
                if (gift.image_url) {
                    displayElement = `<img src="${escapeHtml(gift.image_url)}" alt="${giftNameEscaped}" class="gift-image">`;
                } else if (gift.icon) {
                    if (gift.icon.match(/^\s?fa[srbld]?\sfa-/) || gift.icon.match(/^(fas|far|fab|fal|fad|fa)\s/)) {
                        const safeIconClasses = gift.icon.replace(/[^a-z0-9\s\-]/gi, '').trim(); 
                        displayElement = `<i class="${escapeHtml(safeIconClasses)} gift-fa-icon" aria-hidden="true"></i>`; 
                   } else { 
                        displayElement = `<span class="gift-emoji">${escapeHtml(gift.icon)}</span>`;
                   }
                }
                else { displayElement = `<span class="gift-emoji">🎁</span>`; }

                const giftHtml = `
                    <div class="gift-drawer-item" data-gift-id="${gift.id}" title="${giftNameEscaped} (${giftCost} Coins)">
                        ${displayElement}
                        <div class="gift-drawer-cost">
                            <span>${giftCost}</span>
                            <img src="${sud_config.urls.img_path}/sud-coin.png" alt="c" class="coin-xxs">
                        </div>
                    </div>`;
                $giftDrawerItems.append(giftHtml);
            });
        }

        function appendSystemGiftMessage(giftId, senderId, receiverId, icon, imageUrl, name, cost, usdValue, time) {
            const isSenderViewing = true; 
            let displayText = `You sent ${escapeHtml(name)}`; 
            let iconHtml = '';

            if (imageUrl) {
                iconHtml = `<img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(name)}" class="gift-image-in-message">`;
            } else if (icon) {
                if (icon.match(/^\s?fa[srbld]?\sfa-/) || icon.match(/^(fas|far|fab|fal|fad)\s/)) {
                    const safeIconClasses = icon.replace(/[^a-z0-9\s\-]/gi, '').trim();
                    iconHtml = `<i class="${escapeHtml(safeIconClasses)}" style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;"></i>`;
                } else {
                    iconHtml = `<span style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;">${escapeHtml(icon)}</span>`;
                }
            } else {
                iconHtml = `<span style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;">🎁</span>`;
            }
            const systemMessageHtml = `
                <div class="system-message gift-message gift-sent">
                    <div class="gift-message-main">
                        ${iconHtml}
                        <span style="vertical-align: middle;">${displayText}</span>
                        <span class="message-time system-time">${escapeHtml(time)}</span>
                    </div>
                </div>`;

            $messageContent.append(systemMessageHtml);
            scrollToBottom(false, false);
        }

        function loadOlderMessages() {
            if (!sud_config.active_partner_id || isLoadingOlder || !hasMoreOlderMessages) {
                return;
            }
            isLoadingOlder = true;
            $messageContent.prepend('<div class="loading-spinner-older" style="text-align:center; padding: 10px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>');
            const beforeId = oldestMessageId;
            const messagesToLoad = 30;
            const ajaxData = {
                user_id: sud_config.active_partner_id,
                before_message_id: beforeId,
                limit: messagesToLoad
            };

            $.ajax({
                url: `${sud_config.sud_url}/ajax/load-messages.php`,
                type: 'GET',
                data: ajaxData,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success && response.data && response.data.messages) {
                        if (response.data.messages.length > 0) {
                            const oldScrollHeight = $messageContent[0].scrollHeight;
                            const oldScrollTop = $messageContent[0].scrollTop;
                            renderMessages(response.data.messages, 'prepend');
                            oldestMessageId = response.data.messages[0].id;
                            const newScrollHeight = $messageContent[0].scrollHeight;
                            $messageContent.scrollTop(oldScrollTop + (newScrollHeight - oldScrollHeight));
                            hasMoreOlderMessages = response.data.has_more_older ?? (response.data.messages.length === messagesToLoad);
                        } else {
                            hasMoreOlderMessages = false;
                            $messageContent.prepend('<div class="no-more-messages" style="text-align:center; padding: 10px; color: #999;">Conversation start</div>');
                            setTimeout(() => $('.no-more-messages').fadeOut(1000, function(){ $(this).remove(); }), 3000);
                        }
                    } else {
                        SUD.showToast('error', 'Load Error', response?.data?.message || response?.message || 'Could not load older messages.');
                        console.warn("Load older messages failed or returned unexpected data:", response);
                        hasMoreOlderMessages = false;
                    }
                },
                error: function(xhr) {
                    SUD.showToast('error', 'Network Error', 'Failed to load older messages.');
                    console.error("Load older messages AJAX error:", xhr.status, xhr.responseText);
                    hasMoreOlderMessages = false
                },
                complete: function() {
                    $messageContent.find('.loading-spinner-older').remove();
                    isLoadingOlder = false;
                }
            });
        }

        function renderMessages(messages, appendOrPrepend = 'append') {
            const isPrepending = appendOrPrepend === 'prepend';

            const existingIds = $('.message-bubble, .system-message').map(function() {
                return String($(this).attr('data-id')); 
            }).get();
            const filteredMessages = messages.filter(msg => {
                const msgIdStr = String(msg.id);
                const shouldSkip = existingIds.includes(msgIdStr) && !msgIdStr.startsWith('temp_'); 
                return !shouldSkip;
            });
            filteredMessages.forEach(msg => {
                const tempBubbleExists = $(`.message-bubble[data-id^="temp_"]`).length > 0;
                if (!isPrepending && tempBubbleExists && !String(msg.id).startsWith('temp_')) {
                    console.warn('renderMessages: Rendering a non-temp message while a temp bubble still exists. Potential duplicate source if temp update failed. Message ID:', msg.id);
                }
            });
            if (filteredMessages.length === 0) {
                return;
            }
            const partnerInfo = {
                pic: $('.message-user-info img').attr('src') || (sud_config.urls ? `${sud_config.urls.img_path}/default-profile.jpg` : ''),
                name: sud_config.active_partner_name || 'User'
            };
        
            const giftMessagePrefix = "SUD_GIFT::";
            let generatedHtml = '';
            const $lastExistingElement = $messageContent.find('.message-bubble:last, .system-message:last');
            const $lastExistingSeparator = $messageContent.find('.date-separator:last');
            let dateOfLastExistingElement = null;
            if ($lastExistingElement.length) dateOfLastExistingElement = $lastExistingElement.data('date-raw');
            if (!dateOfLastExistingElement && $lastExistingSeparator.length) dateOfLastExistingElement = parseDisplayDate($lastExistingSeparator.find('span').text());
            let lastDateProcessed = isPrepending ? null : dateOfLastExistingElement;
        
            const $firstExistingElement = $messageContent.find('.message-bubble:first, .system-message:first');
            const dateOfFirstExisting = $firstExistingElement.length ? $firstExistingElement.data('date-raw') : null;
        
            filteredMessages.forEach(function(msg, index) {
                if (!msg || !msg.id || ($(`.message-bubble[data-id="${msg.id}"], .system-message[data-id="${msg.id}"]`).length > 0 && !msg.id.toString().startsWith('temp_'))) {
                    if (!isPrepending) lastDateProcessed = msg.date_raw; 
                    return;
                }
        
                const msgDateRaw = msg.date_raw;
                let dateSeparatorHtml = '';
                let currentMessageHtml = '';
                const currentSenderId = parseInt(msg.sender_id);
                const isSenderViewing = (currentSenderId === currentUserId);
        
                if (msg.message && msg.message.startsWith(giftMessagePrefix)) {
                    try {
                        const parts = msg.message.substring(giftMessagePrefix.length).split('::');
                        if (parts.length >= 7) { 
                            const giftId = parts[0];
                            const senderIdParsed = parseInt(parts[1], 10);
                            const receiverIdParsed = parseInt(parts[2], 10);
                            const displayElementStr = parts[3];
                            const name = parts[4];
                            const cost = parseInt(parts[5], 10);
                            const usdValue = parseFloat(parts[6]).toFixed(2);
                            const isSenderViewing = (senderIdParsed === currentUserId);

                            let displayText = isSenderViewing ? `You sent ${escapeHtml(name)}` : `${escapeHtml(partnerInfo.name)} sent you ${escapeHtml(name)}`;
                            let iconHtml = '';

                            if (displayElementStr.includes('.png') ||  displayElementStr.includes('.webp')) {
                                iconHtml = `<img src="${escapeHtml(displayElementStr)}" alt="${escapeHtml(name)}" class="gift-image-in-message">`;
                            } else if (displayElementStr.match(/^\s?fa[srbld]?\sfa-/) || displayElementStr.match(/^(fas|far|fab|fal|fad)\s/)) {
                                const safeIconClasses = displayElementStr.replace(/[^a-z0-9\s\-]/gi, '').trim();
                                iconHtml = `<i class="${escapeHtml(safeIconClasses)}" style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;"></i>`;
                            } else if (displayElementStr) { 
                                iconHtml = `<span style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;">${escapeHtml(displayElementStr)}</span>`;
                            } else { iconHtml = `<span style="font-size: 1.8em; margin-right: 10px; vertical-align: middle;">🎁</span>`; }

                            let detailsActionHtml = '';
                            if (receiverIdParsed === currentUserId) {
                                const valueDetailsHtml = `<span class="gift-value-details">(${cost} <img src="${sud_config.urls.img_path}/sud-coin.png" alt="c" class="coin-xxs"> / ~$${usdValue} USD)</span>`;
                                const withdrawButtonHtml = `
                                    <button class="btn-withdraw-gift" data-gift-log-id="unknown_js_${msg.id}" data-gift-value-usd="${usdValue}" title="Withdraw $${usdValue}">
                                        <i class="fas fa-hand-holding-usd"></i> Withdraw
                                    </button>`;
                                detailsActionHtml = `
                                <div class="gift-details-action">
                                    ${valueDetailsHtml}
                                    ${withdrawButtonHtml}
                                </div>`;
                            }

                            currentMessageHtml = `
                                <div class="system-message gift-message ${isSenderViewing ? 'gift-sent' : 'gift-received'}" data-id="${msg.id}" data-date-raw="${msgDateRaw}">
                                    <div class="gift-message-main">
                                        ${iconHtml}
                                        <span style="vertical-align: middle;">${displayText}</span>
                                        <span class="message-time system-time">${msg.timestamp_formatted || ''}</span>
                                    </div>
                                    ${detailsActionHtml}
                                </div>`;

                            if (!isPrepending) {
                                updateSidebarPreviews(
                                    isSenderViewing ? parseInt(msg.receiver_id) : parseInt(msg.sender_id),
                                    msg.message,
                                    msg.timestamp_unix,
                                    isSenderViewing
                                );
                            }
                        } else { throw new Error("Incorrect parts count for gift message"); }
                    } catch (e) {
                        console.warn("Could not parse gift message format:", msg.message, e);
                        currentMessageHtml = `<div class="system-message" ...>System event could not be displayed...</div>`;
                    }
                } else {
                    const isOutgoing = isSenderViewing;
                    const bubbleClass = isOutgoing ? 'outgoing' : 'incoming';
                    const safeMessageHtml = escapeHtml(msg.message).replace(/\n/g, '<br>');

                    currentMessageHtml = `
                        <div class="message-bubble ${bubbleClass}" data-id="${msg.id}" data-date-raw="${msgDateRaw}">
                            ${!isOutgoing ? `<img src="${partnerInfo.pic}" alt="${escapeHtml(partnerInfo.name)}" class="message-avatar" onerror="this.src='${sud_config.urls ? sud_config.urls.img_path+'/default-profile.jpg' : ''}';">` : ''}
                            <div class="message-text">
                                <p>${safeMessageHtml}</p>
                                <span class="message-time">${msg.timestamp_formatted || ''}</span>
                            </div>
                        </div>`;
        
                    if (!isPrepending) {
                        updateSidebarPreviews(
                            isOutgoing ? parseInt(msg.receiver_id) : parseInt(msg.sender_id),
                            msg.message,
                            msg.timestamp_unix,
                            isOutgoing
                        );
                    }
                }
        
                if (isPrepending) { generatedHtml = dateSeparatorHtml + currentMessageHtml + generatedHtml; }
                else { generatedHtml += dateSeparatorHtml + currentMessageHtml; }
                lastDateProcessed = msgDateRaw;
            });

            if (isPrepending) {
                const dateOfNewestPrepended = filteredMessages.length > 0 ? filteredMessages[0].date_raw : null;
                const tsOfNewestPrepended = filteredMessages.length > 0 ? filteredMessages[0].timestamp_unix : null;
                const $originalTopSeparator = $messageContent.find('.date-separator:first');
        
                if (dateOfNewestPrepended && dateOfNewestPrepended === dateOfFirstExisting && $originalTopSeparator.length && tsOfNewestPrepended) {
                    const displayDateOfBoundary = getDisplayDate(dateOfNewestPrepended, tsOfNewestPrepended);
                    if ($originalTopSeparator.find('span').text() === displayDateOfBoundary) {
                        $originalTopSeparator.remove(); 
                    }
                }
                if(generatedHtml) $messageContent.prepend(generatedHtml); 
            } else { 
                if(generatedHtml) {
                    $messageContent.find('.loading-spinner, .no-messages-yet').remove();
                    $messageContent.append(generatedHtml);
                    const hasIncoming = filteredMessages.some(msg => parseInt(msg.sender_id) !== currentUserId);
                    scrollToBottom(false, hasIncoming);
                } else if (filteredMessages.length === 0 && $messageContent.children(':not(.loading-spinner-older, .no-more-messages)').length === 0) {
                    $messageContent.html('<div class="no-messages-yet"><p>No messages yet. Start the conversation!</p></div>');
                }
            }
        }

        function parseDisplayDate (displayDateText) {
            if (!displayDateText) return null;
            displayDateText = displayDateText.trim();
            const today = new Date(); today.setHours(0,0,0,0);
            const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);

            if (displayDateText === 'Today') return today.toISOString().split('T')[0];
            if (displayDateText === 'Yesterday') return yesterday.toISOString().split('T')[0];

            try {
                const parsed = new Date(displayDateText);
                 if (!isNaN(parsed.getTime())) {
                     if (displayDateText.includes(parsed.getFullYear())) {
                         return parsed.toISOString().split('T')[0];
                     } else {

                         console.warn("Ambiguous date parsed (missing year?):", displayDateText);

                          return null; 
                     }
                 }
            } catch {  }
            console.warn("Could not parse display date:", displayDateText);
            return null;
       }

       function getDisplayDate(msgDateRaw, msgTime) {
           if (!msgDateRaw && !msgTime) return "Invalid Date"; 

            const today = new Date(); today.setHours(0,0,0,0);
            const yesterday = new Date(today); yesterday.setDate(today.getDate() - 1);

            let msgDate;
            try {
               if(msgTime && !isNaN(parseInt(msgTime))) {
                    msgDate = new Date(parseInt(msgTime) * 1000);
                    if(isNaN(msgDate.getTime())) throw new Error("Invalid timestamp");
               } else if (msgDateRaw) {
                    msgDate = new Date(msgDateRaw + 'T00:00:00Z');
                    if(isNaN(msgDate.getTime())) throw new Error("Invalid date raw string");
               } else {
                    throw new Error("No valid date source");
               }
                msgDate.setHours(0,0,0,0);

            } catch(e) {
                 console.error("Error creating date object:", e, "Raw:", msgDateRaw, "Time:", msgTime);
                 return msgDateRaw || "Invalid Date";
            }

            if (msgDate.getTime() === today.getTime()) return 'Today';
            if (msgDate.getTime() === yesterday.getTime()) return 'Yesterday';

            try {
                const formatTime = msgTime ? parseInt(msgTime) * 1000 : new Date(msgDateRaw + 'T00:00:00Z').getTime();
                const dateToFormat = new Date(formatTime);
                if (isNaN(dateToFormat.getTime())) throw new Error("Invalid time for formatting");

                return new Intl.DateTimeFormat('en-US', { month: 'long', day: 'numeric', year: 'numeric' }).format(dateToFormat);
            } catch (e) {
                 console.error("Error formatting date:", e);
                 return msgDateRaw;
            }
       }

        function handleUnblockClick(e) {
            e.preventDefault();
            const userId = $(this).data('user-id');
            if (!userId || !sud_config.active_partner_id || userId != sud_config.active_partner_id) {
                console.warn("Unblock button clicked with mismatching/missing user ID.");
                return;
            }
            const $button = $(this);

            $button.prop('disabled', true).css('opacity', 0.7);
            if ($button.is('button')) $button.text('Unblocking...');

            unblockUser(userId, null, function(success) {
                if (success) {
                    window.location.reload();
                } else {

                    $button.prop('disabled', false).css('opacity', 1);
                    if ($button.is('button')) $button.text('Unblock User');

                }
            });
        }

        function unblockUser(userId, listItem = null, callback = null) {
            const $buttons = $(`#unblock-user-dropdown[data-user-id="${userId}"], #unblock-user-overlay[data-user-id="${userId}"], .unblock-btn[data-user-id="${userId}"]`);
            $buttons.prop('disabled', true).text('Unblocking...');

            $.ajax({
                url: `${sud_config.sud_url}/ajax/block-user.php`,
                type: 'POST',
                data: {
                    user_id: userId,
                    action: 'unblock',
                    nonce: sud_config.ajax_nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response && response.success) {
                        if (listItem && listItem.closest('#blocked-users-list-container, #blocked-users-list-sidebar').length) {
                            listItem.fadeOut(300, function() {
                                $(this).remove();

                                const $listContainer = $('#blocked-users-list-container, #blocked-users-list-sidebar');
                                if ($listContainer.find('.blocked-user-item').length === 0) {
                                    $listContainer.html('<p>You haven\'t blocked any users.</p>');
                                }
                            });
                        }
                        SUD.showToast('success', 'User Unblocked', response.message || 'User unblocked successfully.');
                        if (typeof callback === 'function') callback(true); 
                    } else {
                        SUD.showToast('error', 'Error', response?.message || 'Failed to unblock user.');
                        if (typeof callback === 'function') callback(false); 
                    }
                },
                error: (xhr) => {
                    SUD.showToast('error', 'Network Error', 'Failed to unblock user.');
                     console.error("Unblock AJAX error:", xhr.status, xhr.responseText);
                    if (typeof callback === 'function') callback(false); 
                },
                complete: () => {
                    if (!(typeof callback === 'function' && callback.toString().includes('reload'))) {
                        $buttons.prop('disabled', false).text('Unblock');
                    }
                }
            });
        }

        function recalculateTotalUnreadCount() {
            let totalUnreadConversations = 0;
            let totalUnreadMessages = 0;
            const $allConversationItems = $('.all-conversations .conversation-item');

            $allConversationItems.each(function() {
                const $item = $(this);
                const userId = $item.data('user-id');
                const $badge = $item.find('.unread-badge');
    
                if ($badge.length && $badge.is(':visible')) {
                    const countText = $badge.text() || '0';
                    const count = parseInt(countText);
    
                    if (!isNaN(count) && count > 0) {
                        totalUnreadMessages += count;
                        totalUnreadConversations++;
                    } else if (isNaN(count)) {
                        console.warn(`recalculateTotalUnreadCount: Invalid count '${countText}' for user ${userId}`);
                    }
                }
            });
    
            const $tabCount = $('.message-tabs .tab[data-tab="unread"] .unread-tab-count');
            if (totalUnreadMessages > 0) {
                $tabCount.text(totalUnreadMessages).removeClass('hidden');
            } else {
                $tabCount.text('0').addClass('hidden');
            }
    
            SUD.updateHeaderMessageCount(totalUnreadConversations);
            updateUnreadTabContent();
            return totalUnreadConversations;
        }

        function updateUnreadTabContent() {
            const $unreadContainer = $('.unread-conversations');
            $unreadContainer.empty();
    
            let hasUnreadItems = false;
            $('.all-conversations .conversation-item').each(function() {
                const $badge = $(this).find('.unread-badge');
                if ($badge.length && $badge.is(':visible') && parseInt($badge.text() || '0') > 0) {
                    $(this).clone(true).appendTo($unreadContainer);
                    hasUnreadItems = true;
                }
            });
    
            if (!hasUnreadItems) {
                $unreadContainer.html('<div class="no-conversations"><p>No unread messages.</p></div>');
            }
        }

        if (SUD.sidebarTimeUpdateInterval) clearInterval(SUD.sidebarTimeUpdateInterval);
        SUD.sidebarTimeUpdateInterval = setInterval(SUD.updateSidebarTimestamps, 60000);

        function updateSidebarPreviews(userId, messageText, time, isOutgoing, partnerData = null) {
            const userIdInt = parseInt(userId);
            if (!userIdInt || isNaN(userIdInt)) {
                console.error("updateSidebarPreviews called with invalid userId:", userId);
                return;
            }
            let $conversationItem = $(`.conversation-item[data-user-id="${userIdInt}"]`);
            const $allList = $('.all-conversations');
            const giftMessagePrefix = "SUD_GIFT::";
            let isGift = typeof messageText === 'string' && messageText.startsWith(giftMessagePrefix);
            let previewText = '';
            let partnerNameForPreview = '';
    
            if (isGift) {
                let giftNameParsed = 'a gift'; 
                try {
                    const parts = messageText.substring(giftMessagePrefix.length).split('::');
                    if(parts.length >= 5) {
                        giftNameParsed = parts[4]; 
                    }
                } catch(e){
                    console.warn("Could not parse gift name from message string:", messageText);
                }
                if (isOutgoing) { previewText = `You sent ${escapeHtml(giftNameParsed)}`; }
                else { previewText = `<i class="fas fa-gift" style="margin-right: 4px; font-size: 0.9em;"></i> Gift Received`; }
            } else if (typeof messageText === 'string') {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = messageText; 
                const plainText = tempDiv.textContent || tempDiv.innerText || "";
                const truncatedText = plainText.length > 30 ? plainText.substring(0, 27) + '...' : plainText;

                if (isOutgoing) { previewText = 'You: ' + escapeHtml(truncatedText); }
                else { previewText = escapeHtml(truncatedText); }
            } else { previewText = '[System Message]'; }

            let messageTimestampUnix = null;
            if (typeof time === 'object' && time !== null && time.timestamp_unix) {
                messageTimestampUnix = parseInt(time.timestamp_unix);
            } else if (!isNaN(parseInt(time))) {
                messageTimestampUnix = parseInt(time);
            }

            let displayTime = 'Just now';
            if (messageTimestampUnix && !isNaN(messageTimestampUnix)) {
                displayTime = SUD.formatRelativeTime(messageTimestampUnix);
            }

            const safeTime = escapeHtml(displayTime);
            const newItemNeeded = $conversationItem.length === 0;

            if (newItemNeeded) {
                if (!partnerData || !partnerData.name || !partnerData.profile_pic) {
                    if (sud_config.active_partner_id == userId) {
                        partnerData = {
                            name: sud_config.active_partner_name || ('User ' + userId),
                            profile_pic: $('.message-user-info img').attr('src') || (sud_config.urls ? `${sud_config.urls.img_path}/default-profile.jpg` : ''),
                            is_verified: $('.message-user-info .verified-badge-header').length > 0,
                            is_online: $('.message-user-info .status.online').length > 0,
                        };
                    } else {
                        console.warn("Minimal partner data used for new sidebar item:", userId);
                        partnerData = partnerData || {};
                        partnerData.name = partnerData.name || 'User ' + userId; 
                        partnerData.profile_pic = partnerData.profile_pic || (sud_config.urls ? `${sud_config.urls.img_path}/default-profile.jpg` : '');
                        partnerData.is_verified = partnerData.is_verified || false;
                        partnerData.is_online = partnerData.is_online || false;
                    }
                }
    
                partnerNameForPreview = partnerData.name;
                const verifiedBadgeHtml = partnerData.is_verified ? `<span class="verified-badge-sidebar"><img src="${sud_config.urls.img_path}/verified-profile-badge.png" alt="verified"></span>` : '';
                const premiumBadgeHtmlSidebar = partnerData.premium_badge_html_sidebar || '';
                const onlineIndicatorHtml = partnerData.is_online ? '<span class="online-indicator"></span>' : '';
                const safeProfilePic = escapeHtml(partnerData.profile_pic);
                const safePartnerName = escapeHtml(partnerData.name);

                const newItemHtml = `
                    <div class="conversation-item" data-user-id="${userIdInt}">
                        <div class="user-avatar"> <img src="${safeProfilePic}" alt="${safePartnerName}" onerror="this.src='${escapeHtml(sud_config.urls.img_path)}/default-profile.jpg';"> ${onlineIndicatorHtml} </div>
                        <div class="conversation-info">
                            <div class="conversation-header">
                                <div class="name-badge-wrapper"> <h4>${safePartnerName}</h4> ${premiumBadgeHtmlSidebar} ${verifiedBadgeHtml} </div>
                                <span class="time">${safeTime}</span>
                            </div> <p class="message-preview">${previewText}</p>
                        </div>
                    </div> `;
                $allList.find('.no-conversations').remove();
                $allList.prepend(newItemHtml);
                $conversationItem = $(`.conversation-item[data-user-id="${userIdInt}"]`);
                if (messageTimestampUnix) {
                    $conversationItem.data('last-timestamp-unix', messageTimestampUnix);
                }
            } else {
                partnerNameForPreview = $conversationItem.find('h4').text();
                $conversationItem.find('.message-preview').html(previewText);
                $conversationItem.find('.time').text(safeTime);
                if (messageTimestampUnix) {
                    $conversationItem.data('last-timestamp-unix', messageTimestampUnix);
                }
                if ($allList.children().first().data('user-id') != userIdInt) {
                    $allList.prepend($conversationItem.detach());
                }
            }

            const isActiveConversation = $conversationItem.hasClass('active');
            let $unreadBadge = $conversationItem.find('.unread-badge');
            
            if (!isOutgoing && !isActiveConversation) {
                let currentCount = parseInt($unreadBadge.text() || '0');
                let newCount = currentCount + 1;
                if ($unreadBadge.length) {
                    $unreadBadge.text(newCount).show().removeClass('hidden');
                } else {
                    $conversationItem.append(`<span class="unread-badge">${newCount}</span>`);
                    $unreadBadge = $conversationItem.find('.unread-badge');
                }
            } else {
                if ($unreadBadge.length) {
                    $unreadBadge.hide().addClass('hidden').text('0');
                }
            }
            recalculateTotalUnreadCount();
        }

        function playNewMessageSound() {
            try {
                const soundUrl = sud_config?.sounds?.message || `${sud_config.urls.sound_path}/message.mp3`; 
                if(soundUrl) {
                    const audio = new Audio(soundUrl);
                    audio.volume = 0.4; 
                    audio.play().catch(e => console.warn("Audio play prevented by browser:", e));
                } else {
                    console.warn("Message sound URL missing.");
                }
            } catch (e) {
                console.error("Error playing message sound:", e);
            }
        }

        function startMessagePolling() {
            if (SUD.isMessagePollingActive && sud_config.active_partner_id && !sud_config.is_blocked_view) {
                if (messageCheckInterval) clearInterval(messageCheckInterval);
                messageErrorCount = 0;
                setTimeout(checkNewMessages, 1500);

                messageCheckInterval = setInterval(checkNewMessages, 7000);
            } else {
                 if (messageCheckInterval) clearInterval(messageCheckInterval);
            }
        }

        function checkNewMessages() {
            if (!SUD.isMessagePollingActive || !sud_config || !sud_config.sud_url || !sud_config.active_partner_id || window.isLoadingMessages || sud_config.is_blocked_view) {
                return;
            }

            window.isLoadingMessages = true;
            const lastRealMessageId = $('.message-bubble:not([data-id^="temp_"]), .system-message:not([data-id^="temp_"])')
                .map(function() { return parseInt($(this).data('id')) || 0; })
                .get()
                .reduce((max, id) => Math.max(max, id), 0);
        
            $.ajax({
                url: `${sud_config.sud_url}/ajax/load-messages.php`,
                type: 'GET',
                data: {
                    user_id: sud_config.active_partner_id,
                    last_message_id: lastRealMessageId
                },
                dataType: 'json',
                success: function(response) {
                    if (response && response.success && response.data) {
                        // Update balance if provided in response
                        if (response.data.current_balance_formatted) {
                            SUD.updateHeaderCoinBalance(response.data.current_balance_formatted);
                        }
                        
                        if (response.data.messages?.length > 0) {
                            const existingIds = $('.message-bubble, .system-message').map(function() {
                                return String($(this).attr('data-id')); 
                            }).get();
                            
                            const receivedMessages = response.data.messages;
                            const newMessages = receivedMessages.filter(msg => {
                                const msgIdStr = String(msg.id);
                                const exists = existingIds.includes(msgIdStr);
                                return !exists;
                            });

                            if (newMessages.length > 0) {
                                renderMessages(newMessages, 'append'); 
                                if (newMessages.some(m => parseInt(m.sender_id) === sud_config.active_partner_id)) {
                                    playNewMessageSound();
                                }
                                recalculateTotalUnreadCount(); 
                            }
                        } 
                        messageErrorCount = 0; 
                    } else {
                        console.warn("Check new messages response indicates failure or invalid data:", response);
                        messageErrorCount++;
                    }
                },
                error: function(xhr) {
                    console.error('Message Poll Error:', xhr.status, xhr.responseText);
                    messageErrorCount++;
        
                    if (messageErrorCount > 5) {
                        console.warn("Too many message polling errors. Pausing polling.");
                        if (messageCheckInterval) clearInterval(messageCheckInterval);
                        SUD.isMessagePollingActive = false;
                        SUD.showToast('error', 'Connection Issue', 'Could not check for new messages. Please refresh later.');
                    }
                },
                complete: function() {
                    window.isLoadingMessages = false;
                }
            });
        }

        function handleSendMessage(e) {
            e.preventDefault();
            const formData = $(this).serialize();
            const receiverId = $receiverIdInput.val();
            const messageText = $messageInput.val().trim(); 
        
            if (!messageText || !receiverId) return;
            if (messageCheckInterval) clearInterval(messageCheckInterval);
        
            const tempId = 'temp_' + Date.now();
            const now = new Date();
        
            let hours = now.getHours();
            const minutes = now.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const formattedTime = hours.toString().padStart(2, '0') + ':' + minutes + ' ' + ampm;

            const optimisticMessageData = {
                id: tempId,
                sender_id: sud_config.current_user_id,
                receiver_id: receiverId,
                message: messageText,
                timestamp_unix: Math.floor(now.getTime()/1000),
                timestamp_formatted: formattedTime,
                date_raw: now.toISOString().split('T')[0],
                is_read: false
            };
            renderMessages([optimisticMessageData], 'append');
            
            const $tempBubbleJustAdded = $(`.message-bubble[data-id="${tempId}"]`);
            if ($tempBubbleJustAdded.length) {
                $tempBubbleJustAdded.addClass('temporary-message');
            } else {
                console.warn(`Could not find temp bubble ${tempId} immediately after rendering to add class.`);
            }

            const partnerDataForSidebar = {
                name: sud_config.active_partner_name || ('User ' + receiverId),
                profile_pic: $('.message-user-info img').attr('src') || (sud_config.urls ? `${sud_config.urls.img_path}/default-profile.jpg` : ''),
                is_verified: $('.message-user-info .verified-badge-header').length > 0,
            };
            updateSidebarPreviews( receiverId, messageText, optimisticMessageData.timestamp_unix, true, partnerDataForSidebar );
            $messageInput.val('').trigger('input');
            scrollToBottom(false, false);
            $emojiPicker.removeClass('show');
        
            $.ajax({
                url: `${sud_config.sud_url}/ajax/send-message.php`,
                type: 'POST',
                data: formData,
                dataType: 'json',
                statusCode: {
                    403: function(jqXHR, textStatus, errorThrown) {
                        let errorMessage = 'Permission denied or limit reached.';
                        let showUpgradePrompt = false;
                        let upgradeReason = '';
                        let isLimitReached = false;
    
                        try {
                            const errorData = JSON.parse(jqXHR.responseText);
                            errorMessage = errorData.message || (errorData.data ? errorData.data.message : null) || errorMessage;
                            if (errorData.upgrade_required === true || (errorData.data && errorData.data.upgrade_required === true) || errorData.not_matched === true) {
                                showUpgradePrompt = true;
                                upgradeReason = errorData.reason || (errorData.data ? errorData.data.reason : null) || '';
                                if (upgradeReason === 'message_limit_reached') {
                                    isLimitReached = true;
                                }
                            }
                        } catch (e) {
                            console.warn("Could not parse 403 error response text:", jqXHR.responseText);
                            errorMessage = 'You do not have permission or have reached a limit.'; 
                        }
    
                        const $failedBubble = $(`.message-bubble.temporary-message[data-id="${tempId}"]`);
                        if ($failedBubble.length) {
                             $failedBubble.addClass('failed').attr('title', errorMessage);
                             $failedBubble.removeClass('temporary-message');
                             $failedBubble.find('.message-sending-indicator').remove();
                             if ($failedBubble.find('.message-failed-indicator').length === 0) {
                                 $failedBubble.append('<i class="fas fa-exclamation-circle message-failed-indicator" style="font-size: 0.8em; margin-left: 5px; color: red;" title="Failed to send"></i>');
                             }
                        }
    
                        if (showUpgradePrompt) {
                             const $modal = $('#upgrade-prompt-modal');
                             if ($modal.length) {
                                 $modal.find('.modal-reason-text').text(errorMessage);
                                 if (upgradeReason === 'not_matched') {
                                     // Show "not matched" prompt instead of upgrade prompt
                                     SUD.showNotMatchedPrompt();
                                 } else if (isLimitReached) {
                                     $modal.find('#upgrade-prompt-title').text('Message Limit Reached');
                                     $modal.find('.modal-icon i').removeClass().addClass('fas fa-comments');
                                     $modal.addClass('show');
                                 } else {
                                     $modal.find('#upgrade-prompt-title').text('Upgrade Required');
                                     $modal.find('.modal-icon i').removeClass().addClass('fas fa-lock');
                                     $modal.addClass('show');
                                 }
                             } else {
                                 if (upgradeReason === 'not_matched') {
                                     SUD.showToast('info', 'Not Matched', errorMessage);
                                 } else {
                                     SUD.showToast('warning', (isLimitReached ? 'Limit Reached' : 'Upgrade Required'), errorMessage);
                                 }
                             }
                        } else {
                             if (!$failedBubble.length) { 
                                 SUD.showToast('error', 'Permission Denied', errorMessage);
                             }
                        }
                    } 
    
                },
                success: function(response) {
                    // Check for upgrade required in success response
                    if (response && response.success && response.data && response.data.upgrade_required) {
                        const upgradeMessage = response.data.message || 'Upgrade required';
                        const cleanMessage = upgradeMessage.replace('upgrade required:', '').trim();
                        
                        // Remove the failed message bubble
                        const $failedBubble = $(`.message-bubble.temporary-message[data-id="${tempId}"]`);
                        if ($failedBubble.length) {
                            $failedBubble.remove();
                        }
                        
                        // Show upgrade prompt modal
                        const $modal = $('#upgrade-prompt-modal');
                        if ($modal.length) {
                            $modal.find('.modal-reason-text').text(cleanMessage);
                            $modal.find('#upgrade-prompt-title').text('Message Limit Reached');
                            $modal.find('.modal-icon i').removeClass().addClass('fas fa-comments');
                            $modal.addClass('show');
                        } else {
                            SUD.showToast('warning', 'Limit Reached', cleanMessage);
                        }
                        return;
                    }
                    
                    const isSuccess = response && (response.success === true || (response.data && response.data.success === true));
                    const messageData = response ? (response.message || (response.data ? response.data.message : null)) : null;
                    const messageId = messageData ? messageData.id : null;

                    if (isSuccess && messageId) {
                        const $tempBubble = $(`.message-bubble.temporary-message[data-id="${tempId}"]`);

                        if ($tempBubble.length) {
                            try {
                                $tempBubble
                                    .attr('data-id', messageId)
                                    .data('id', messageId);
                                $tempBubble.removeClass('failed temporary-message')
                                    .removeAttr('title');
                                $tempBubble.find('.message-time')
                                    .text(messageData.timestamp_formatted || ' ');
                                $tempBubble.find('.message-sending-indicator, .message-failed-indicator').remove();
                            } catch (e) {
                                console.error("Error updating temp bubble UI:", e);
                                $tempBubble.addClass('failed').attr('title', 'UI update failed after send.');
                                if ($tempBubble.find('.message-failed-indicator').length === 0) {
                                    $tempBubble.append('<i class="fas fa-exclamation-circle message-failed-indicator" style="font-size: 0.8em; margin-left: 5px; color: red;" title="UI Update Error"></i>');
                                }
                            }
                        } else {
                            console.warn("Couldn't find temp message bubble to update with ID:", tempId, "- Was it already removed or altered?");
                        }
                    } else {
                        console.warn("Send message success callback - Condition NOT met or success:false. Response:", response);
                        const $failedBubble = $(`.message-bubble.temporary-message[data-id="${tempId}"]`);
                        const failureMsg = response?.message || response?.data?.message || 'Failed to send message or confirmation failed.';

                        // Check for upgrade required prefix in error message
                        if (failureMsg && failureMsg.startsWith('upgrade required:')) {
                            const upgradeMessage = failureMsg.substring('upgrade required:'.length);
                            
                            // Remove the failed message bubble
                            if ($failedBubble.length) {
                                $failedBubble.remove();
                            }
                            
                            // Show upgrade prompt modal
                            const $modal = $('#upgrade-prompt-modal');
                            if ($modal.length) {
                                $modal.find('.modal-reason-text').text(upgradeMessage);
                                $modal.find('#upgrade-prompt-title').text('Message Limit Reached');
                                $modal.find('.modal-icon i').removeClass().addClass('fas fa-comments');
                                $modal.addClass('show');
                            } else {
                                SUD.showToast('warning', 'Limit Reached', upgradeMessage);
                            }
                        } else {
                            // Handle regular errors
                            if ($failedBubble.length) {
                                $failedBubble.addClass('failed').attr('title', failureMsg);
                                $failedBubble.removeClass('temporary-message');
                                $failedBubble.find('.message-sending-indicator').remove();
                                if ($failedBubble.find('.message-failed-indicator').length === 0) {
                                    $failedBubble.append('<i class="fas fa-exclamation-circle message-failed-indicator" style="font-size: 0.8em; margin-left: 5px; color: red;" title="Failed to send"></i>');
                                }
                            } else {
                                console.warn("Couldn't find temp bubble to mark as failed:", tempId);
                                SUD.showToast('error', 'Send Error', failureMsg);
                            }
                            if (!$failedBubble.length) {
                               SUD.showToast('error', 'Send Error', failureMsg);
                            }
                        }
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    let errorMessage = 'An unknown network or server error occurred.'; 
                    if (jqXHR.status === 0) {
                        console.error("Send Message AJAX Error: Network error or CORS issue.");
                        errorMessage = 'Could not connect to the server. Please check your network.';
                    } else if (textStatus === 'timeout') {
                         console.error("Send Message AJAX Error: Request Timed Out.");
                         errorMessage = 'The request timed out. Please try again.';
                    } else if (jqXHR.status >= 500) { // Explicitly check for server errors
                         console.error("Send Message AJAX Error (Server Error):", jqXHR.status, errorThrown);
                         console.error("Response Text:", jqXHR.responseText);
                         
                         // Check if this is an upgrade required error disguised as 500 error
                         try {
                             const errorResponse = JSON.parse(jqXHR.responseText);
                             const errorMsg = errorResponse?.data?.message || errorResponse?.message;
                             if (errorMsg && errorMsg.startsWith('upgrade required:')) {
                                 const upgradeMessage = errorMsg.substring('upgrade required:'.length);
                                 
                                 // Remove the failed message bubble
                                 const $failedBubble = $(`.message-bubble.temporary-message[data-id="${tempId}"]`);
                                 if ($failedBubble.length) {
                                     $failedBubble.remove();
                                 }
                                 
                                 // Show upgrade prompt modal
                                 const $modal = $('#upgrade-prompt-modal');
                                 if ($modal.length) {
                                     $modal.find('.modal-reason-text').text(upgradeMessage);
                                     $modal.find('#upgrade-prompt-title').text('Message Limit Reached');
                                     $modal.find('.modal-icon i').removeClass().addClass('fas fa-comments');
                                     $modal.addClass('show');
                                 } else {
                                     SUD.showToast('warning', 'Limit Reached', upgradeMessage);
                                 }
                                 return; // Exit early, don't show generic error
                             }
                         } catch (e) {
                             // Not JSON or doesn't contain upgrade message, continue with normal error handling
                         }
                         
                         errorMessage = `The server encountered an error (${jqXHR.status}). Please try again later.`;
                    } else {
                        // Catch other unexpected statuses or errors that bypassed statusCode
                        console.error("Send Message AJAX Error (Unhandled):", textStatus, errorThrown);
                        console.error("Status Code:", jqXHR.status);
                        console.error("Response Text:", jqXHR.responseText);
                        // Use the default message declared above or try to refine
                        errorMessage = `An unexpected error occurred (${jqXHR.status || textStatus}). Please try again.`;
                    }
    
                    // Mark the temporary bubble as failed
                    const $failedBubble = $(`.message-bubble.temporary-message[data-id="${tempId}"]`);
                    if ($failedBubble.length) {
                        // Use the potentially updated errorMessage from the if/else blocks
                        $failedBubble.addClass('failed').attr('title', errorMessage);
                        $failedBubble.removeClass('temporary-message'); // Optional
                        $failedBubble.find('.message-sending-indicator').remove();
                        if ($failedBubble.find('.message-failed-indicator').length === 0) {
                            $failedBubble.append('<i class="fas fa-exclamation-circle message-failed-indicator" style="font-size: 0.8em; margin-left: 5px; color: red;" title="Failed to send"></i>');
                        }
                    }

                    if (!$failedBubble.length) {
                        SUD.showToast('error', 'Send Error', errorMessage);
                    }
                },
    
                complete: function(jqXHR, textStatus) { 
                    if (!$('#upgrade-prompt-modal').hasClass('show')) {
                        setTimeout(startMessagePolling, 1500);
                    }
                } 
            });
        }

        $(document).on('click', '#upgrade-prompt-modal .close-modal, #upgrade-prompt-modal .btn-secondary', function() {
            $('#upgrade-prompt-modal').removeClass('show');
            if (SUD.isMessagePollingActive && !messageCheckInterval) {
                startMessagePolling();
            }
        });

        function handleBlockConfirm() {
            const userId = sud_config.active_partner_id;
            if (!userId) return;
            const $btn = $(this); $btn.prop('disabled', true).text('Blocking...');
            $.ajax({
                url: `${sud_config.sud_url}/ajax/block-user.php`, type: 'POST',
                data: {
                    user_id: userId,
                    action: 'block',
                    nonce: sud_config.ajax_nonce
                },
                dataType: 'json',
                success: (response) => {
                    if (response && response.success) {
                        SUD.showToast('success', 'User Blocked', `${sud_config.active_partner_name || 'User'} has been blocked.`);
                        setTimeout(() => window.location.href = `${sud_config.sud_url}/pages/messages`, 1500);
                    } else {
                        SUD.showToast('error', 'Error', response?.data?.message || response?.message || 'Failed to block user.');
                        $btn.prop('disabled', false).text('Block User');
                    }
                },
                error: (xhr) => {
                    console.error("Block confirm error:", xhr.status, xhr.responseText);
                    try { 
                        const errorData = JSON.parse(xhr.responseText);
                        SUD.showToast('error', 'Error', errorData?.data?.message || 'Failed to block user due to a network issue.');
                    } catch(e) {
                        SUD.showToast('error', 'Error', 'Failed to block user due to a network issue.');
                    }
                    $btn.prop('disabled', false).text('Block User');
                },
                complete: () => { $('#block-modal').removeClass('show'); }
            });
        }

        function handleReportSubmit(e) {
            e.preventDefault();
            const $form = $(this);
            const userId = sud_config.active_partner_id;
            const reason = $form.find('#report-reason').val();
            const details = $form.find('#report-details').val();

            if (!userId || !reason) return;
            const $btn = $form.find('button[type="submit"]'); $btn.prop('disabled', true).text('Submitting...');
            $.ajax({
                url: `${sud_config.sud_url}/ajax/report-user.php`, type: 'POST',
                data: { user_id: userId, reason: reason, details: details, nonce: sud_config.ajax_nonce }, dataType: 'json',
                success: (response) => {
                    if (response && response.success) {
                        const userName = sud_config.active_partner_name || 'User';
                        SUD.showToast('success', 'Report Submitted', `Your report regarding ${userName} has been submitted.`);
                        $('#report-modal').removeClass('show');
                        $form[0].reset();
                    } else {
                        SUD.showToast('error', 'Error', response?.message || 'Failed to submit report.');
                    }
                },
                error: (xhr) => {
                    SUD.showToast('error', 'Error', 'Failed to submit report due to a network issue.');
                    console.error("Report submit error:", xhr.status, xhr.responseText);
                },
                complete: () => { $btn.prop('disabled', false).text('Submit Report'); }
            });
        }
        function handleClearConfirm() {
            const userId = sud_config.active_partner_id; if (!userId) return; const $btn = $(this); $btn.prop('disabled', true).text('Clearing...');
            $.ajax({
                url: `${sud_config.sud_url}/ajax/clear-messages.php`, type: 'POST',
                data: { 
                    user_id: userId,
                    nonce: sud_page_specific_config.clear_messages_nonce || sud_config.ajax_nonce 
                }, dataType: 'json',
                success: (response) => {
                    if (response && response.success) {
                        SUD.showToast('success', 'Conversation Cleared', 'Your view of this conversation has been cleared.');
                        $messageContent.html('<div class="no-messages-yet"><p>Your view of this conversation is empty.</p></div>'); 

                        const $sidebarItem = $(`.conversation-item[data-user-id="${userId}"]`);
                        $sidebarItem.find('.message-preview, .time').text('');
                        hasMoreOlderMessages = false; 
                        oldestMessageId = 0;
                    } else {
                        SUD.showToast('error', 'Error', response?.message || 'Failed to clear conversation.');
                    }
                },
                error: (xhr) => {
                    SUD.showToast('error', 'Error', 'Failed to clear messages due to a network issue.');
                    console.error("Clear confirm error:", xhr.status, xhr.responseText);
                },
                complete: () => { $btn.prop('disabled', false).text('Clear Messages'); $('#clear-modal').removeClass('show'); }
            });
        }

        function handleVideoCallClick() {
            const userId = $(this).data('user-id');
            if (!userId) return;
            SUD.showToast('info', 'Coming Soon', 'Video call feature is under development!');
        }

        function renderBlockedUsers(blockedUsers, $container) {
            let generatedHtml = '';
            blockedUsers.forEach(user => {
                const profilePic = user.profile_pic || sud_config.urls.img_path + '/default-profile.jpg';
                const name = user.name || 'User';
                const blockedDate = user.blocked_date ? `<p class="blocked-date">Blocked: ${user.blocked_date}</p>` : '';

                generatedHtml += `
                    <div class="conversation-item blocked-user-item" data-user-id="${user.id}">
                        <div class="user-avatar">
                            <img src="${profilePic}" alt="${name}" onerror="this.src='${sud_config.urls.img_path}/default-profile.jpg';">
                        </div>
                        <div class="conversation-info">
                            <h4>${name}</h4>
                            ${blockedDate}
                        </div>
                        <button type="button" class="unblock-btn btn-icon-sm" data-user-id="${user.id}" title="Unbloc ${name}"><i class="fas fa-check-circle"></i></button>
                    </div>
                `;
            });
            $container.html(generatedHtml);
        }

        $messageContent.on('click', '.btn-withdraw-gift', function() {
            const $button = $(this);
            const usdValue = parseFloat($button.data('gift-value-usd'));
            const giftLogId = $button.data('gift-log-id') || 'unknown';

            if (isNaN(usdValue) || usdValue <= 0) {
                SUD.showToast('error', 'Error', 'Invalid gift value for withdrawal.');
                console.warn('Withdraw button clicked with invalid USD value:', $button.data('gift-value-usd'));
                return;
            }
            const withdrawalUrl = `${sud_config.sud_url}/pages/withdrawal?amount_usd=${usdValue}&ref_gift=${giftLogId}`;
            window.location.href = withdrawalUrl;
            $button.prop('disabled', true).css('opacity', 0.6).attr('title', 'Withdrawal Initiated');
        });
        initializeChatView();
    };

    SUD.updateMessageInputUI = function(config) {
        const $messageInput = $('#message-input');
        const $sendButton = $('.send-btn'); 
        const $emojiButton = $('#emoji-button');
        const $inputContainer = $('.message-input'); 
        const $messagesContainer = $('.messages-container'); 

        $messagesContainer.removeClass('is-message-limit-blocked');
        $inputContainer.find('.sud-blocked-messaging-overlay').remove(); 
        $messageInput.prop('disabled', false).attr('placeholder', 'Type your message...');
        $sendButton.prop('disabled', false);
        $emojiButton.css('pointer-events', 'auto').css('opacity', '1');

        if (config.is_blocked_view) {
            $messageInput.prop('disabled', true).attr('placeholder', 'Interaction blocked');
            $sendButton.prop('disabled', true);
            $emojiButton.css('pointer-events', 'none').css('opacity', '0.5');
            if ($inputContainer.find('.sud-blocked-messaging-overlay').length === 0) {
                  $inputContainer.append(`
                    <div class="sud-blocked-messaging-overlay">
                        <p><i class="fas fa-ban"></i> Interaction with this user is blocked.</p>
                    </div>
                 `);
            }
            return;
        }

        if (!config.active_partner_id) {
            $messageInput.prop('disabled', false).attr('placeholder', 'Type your message...');
            $sendButton.prop('disabled', false);
            $emojiButton.css('pointer-events', 'auto').css('opacity', '1');
            return;
        }

        if (!config.is_sender_premium) {
            const sentCount = config.sent_message_count;
            const limit = config.free_message_limit || 10;
            const partnerName = config.active_partner_name || 'this user';

            if (config.active_partner_id && !config.is_blocked_view && sentCount !== null && sentCount >= limit) {
                $messagesContainer.addClass('is-message-limit-blocked');
                $messageInput.prop('disabled', true).attr('placeholder', `Message limit reached with ${partnerName}`);
                $sendButton.prop('disabled', true);
                $emojiButton.css('pointer-events', 'none').css('opacity', '0.5');

                const overlayMessage = `You've used your ${limit} free messages with <strong style="color:#333;">${escapeHtml(partnerName)}</strong>.`;
                const overlaySubMessage = `Upgrade for unlimited chat!`;

                $inputContainer.append(`
                    <div class="sud-blocked-messaging-overlay">
                        <p><i class="fas fa-comment-slash"></i> ${overlayMessage}</p>
                        <p>${overlaySubMessage}</p>
                       <a href="${sud_config.sud_url}/pages/premium" class="btn btn-primary btn-sm">Upgrade Now</a>
                    </div>
                `);
            }
        }
    };

    SUD.initRealTimeUpdates = function() {
        if (typeof sud_config === 'undefined' || !sud_config.is_logged_in || !sud_config.sud_url) {
            return;
        }
        if (SUD.realTimeInterval || SUD.isMessagePollingActive) {
            return;
        }
        setTimeout(SUD.checkForUpdates, 2000);

        SUD.realTimeInterval = setInterval(SUD.checkForUpdates, 8000);
    };

    SUD.checkForUpdates = function() {
        if (typeof sud_config === 'undefined' || !sud_config.is_logged_in || !sud_config.sud_url || SUD.isMessagePollingActive) {
            if (SUD.isMessagePollingActive && SUD.realTimeInterval) {
                clearInterval(SUD.realTimeInterval);
                SUD.realTimeInterval = null;
            }
            return;
        }
    
        $.ajax({
            url: `${sud_config.sud_url}/ajax/check-updates.php`,
            type: 'GET',
            data: { 
                type: 'all', check_toast: '1'
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    const data = response.data || response; 
    
                    if (typeof data.unread_conversation_count !== 'undefined') {
                        SUD.updateHeaderMessageCount(data.unread_conversation_count);
                    }
    
                    if (typeof data.unread_notification_count !== 'undefined') {
                        SUD.updateNotificationCount(data.unread_notification_count);
                            if (parseInt(data.unread_notification_count) > 0 && $('#notification-dropdown').hasClass('show')) {
                                SUD.loadNotifications(false, false); // Silent update - no loader
                            }
                    }
    
                    if (data.toast_data && data.toast_data.id) { 
                        let shouldShowThisToast = true;
                        const toastInfo = data.toast_data;
    
                        if (toastInfo.type === 'message' && window.location.pathname.includes('/messages')) {
                            if (sud_page_specific_config && 
                                typeof sud_page_specific_config.active_partner_id !== 'undefined' &&
                                parseInt(toastInfo.sender_id) === parseInt(sud_page_specific_config.active_partner_id)) {
                                shouldShowThisToast = false;
                            }
                        }
    
                        if (toastInfo.type === 'message' && window.location.pathname.includes('/profile') && senderId) {
                                const urlParams = new URLSearchParams(window.location.search);
                                const profilePageId = parseInt(urlParams.get('id'));
                                if (profilePageId === parseInt(toastInfo.sender_id)) {
                                    shouldShowThisToast = false;
                                }
                        }
    
                        if (shouldShowThisToast) {
                            SUD.showMessageToast(toastInfo);
                            SUD.playNotificationSound(toastInfo.type === 'message' ? 'message' : 'notification');
                            
                            // Update notification dropdown when toast is shown (parallel update)
                            if (toastInfo.type !== 'message' && $('#notification-dropdown').hasClass('show')) {
                                SUD.loadNotifications(false, false); // Silent update - no loader
                            }
                        }
                    }
                } else {
                    console.warn("Check-updates call failed or returned success:false:", response?.message);
                }
            },
            error: function(xhr) {
                console.warn("Failed to check for updates (all types):", xhr.status, xhr.statusText);
            }
        });
    };

    SUD.showMessageToast = function(toastData) {
        const $container = $('#toast-container');
        if (!$container.length) {
            $('body').append('<div id="toast-container" class="toast-container"></div>');
        }
    
        const toastIdHtml = 'toast-' + String(toastData.id).replace(/[^a-zA-Z0-9]/g, '');
    
        if ($(`.message-toast-notification[data-toast-id="${toastData.id}"]`).length > 0 || $(`#${toastIdHtml}`).length > 0) {
            return;
        }
    
        const profilePic = toastData.profile_pic || (sud_config.urls ? `${sud_config.urls.img_path}/default-profile.jpg` : '');
        const senderName = toastData.sender_name || 'System'; 
        const senderId = toastData.sender_id || 0;     
        const originalMessageText = toastData.message || ''; 
        const notificationType = toastData.type || 'general';
        const isSystemNotification = ['account_banned', 'account_warning', 'account_unbanned'].includes(notificationType);
    
        let displayContentHtml = '';
        const giftMessagePrefix = "SUD_GIFT::";
    
        let toastTitle = `New Message from ${escapeHtml(senderName)}`;
        let toastActionText = "Reply";
        let toastActionLink = senderId ? `${sud_config.sud_url}/pages/messages?user=${senderId}` : '#';
    
        if (notificationType === 'message' && originalMessageText.startsWith(giftMessagePrefix)) {
            toastTitle = `New Gift from ${escapeHtml(senderName)}`; 
            try {
                const parts = originalMessageText.substring(giftMessagePrefix.length).split('::');
                if (parts.length >= 5) {
                    const giftImageUrl = parts[3];
                    const giftName = parts[4];
                    let giftVisualHtml = '';
    
                    if (giftImageUrl) {
                        if (giftImageUrl.includes('.png') || giftImageUrl.includes('.webp') || giftImageUrl.includes('.jpg') || giftImageUrl.includes('.gif')) {
                            giftVisualHtml = `<img src="${escapeHtml(giftImageUrl)}" alt="${escapeHtml(giftName)}" style="width:32px; height:32px; border-radius:4px; margin-right:8px; vertical-align:middle; object-fit:contain;">`;
                        } else if (giftImageUrl.match(/^\s?fa[srbld]?\sfa-/) || giftImageUrl.match(/^(fas|far|fab|fal|fad|fa)\s/)) {
                            const safeIconClasses = giftImageUrl.replace(/[^a-z0-9\s\-]/gi, '').trim();
                            giftVisualHtml = `<i class="${escapeHtml(safeIconClasses)}" style="margin-right: 8px; vertical-align: middle; font-size: 1.5em; color: var(--primary-color);"></i>`;
                        } else {
                            giftVisualHtml = `<span style="margin-right: 8px; vertical-align: middle; font-size: 1.5em;">${escapeHtml(giftImageUrl)}</span>`;
                        }
                    } else {
                        giftVisualHtml = `<span style="margin-right: 8px; vertical-align: middle; font-size: 1.5em;">🎁</span>`;
                    }
                    displayContentHtml = `
                        <div style="display:flex; align-items:center;">
                            ${giftVisualHtml}
                            <span style="vertical-align: middle;">Received: <strong style="font-weight: bold; color: #000;">${escapeHtml(giftName)}</strong></span>
                        </div>`;
                } else {
                    displayContentHtml = $('<div>').text('Received a special gift!').html();
                }
            } catch (e) {
                console.error("Error parsing gift message for toast:", e, originalMessageText);
                displayContentHtml = $('<div>').text('Received a gift (display error).').html();
            }
        } else if (notificationType === 'message') {
            const tempDiv = document.createElement("div");
            tempDiv.innerHTML = originalMessageText;
            const plainMessage = tempDiv.textContent || tempDiv.innerText || "";
            const shortMessage = plainMessage.length > 60 ? plainMessage.substring(0, 57) + '...' : plainMessage;
            displayContentHtml = escapeHtml(shortMessage);
        } else { 
            displayContentHtml = escapeHtml(originalMessageText); 
            toastActionText = "View"; 
            toastActionLink = `${sud_config.sud_url}/pages/activity?tab=notifications`; 
    
            if (notificationType === 'profile_view') {
                toastTitle = `${escapeHtml(senderName)} viewed your profile!`;
                if (originalMessageText.toLowerCase().includes('upgrade to premium')) {
                    toastActionText = "Upgrade";
                    toastActionLink = `${sud_config.sud_url}/pages/premium`;
                } else if (senderId) {
                    toastActionText = "View Profile";
                    toastActionLink = `${sud_config.sud_url}/pages/profile?id=${senderId}`;
                }
            } else if (notificationType === 'favorite') {
                toastTitle = `${escapeHtml(senderName)} favorited you!`;
                if (originalMessageText.toLowerCase().includes('upgrade to premium')) {
                    toastActionText = "Upgrade";
                    toastActionLink = `${sud_config.sud_url}/pages/premium`;
                } else if (senderId) {
                    toastActionText = "View Profile";
                    toastActionLink = `${sud_config.sud_url}/pages/profile?id=${senderId}`;
                }
            } else if (notificationType === 'gift' || notificationType === 'gift_received_general') {
                toastTitle = `You Received a Gift!`;
                toastActionText = "View Messages";
                toastActionLink = senderId ? `${sud_config.sud_url}/pages/messages?user=${senderId}` : `${sud_config.sud_url}/pages/activity?tab=notifications`;
            } else if (notificationType === 'account_banned') {
                toastTitle = "Account Suspended";
                toastActionText = "Contact Support";
                toastActionLink = `mailto:${sud_config.admin_email}`;
            } else if (notificationType === 'account_warning') {
                toastTitle = "Account Warning";
                toastActionText = "View Details";
                toastActionLink = `${sud_config.sud_url}/pages/activity?tab=notifications`;
            } else if (notificationType === 'account_unbanned') {
                toastTitle = "Account Reinstated";
                toastActionText = "Continue";
                toastActionLink = `${sud_config.sud_url}/pages/dashboard`;
            } else {
                toastTitle = "New Notification";
            }
        }
    
        // Create toast element safely using jQuery to prevent HTML injection issues
        const $toast = $('<div>', {
            'class': 'toast-notification message-toast-notification info',
            'id': toastIdHtml,
            'data-toast-id': toastData.id,
            'data-sender-id': senderId
        });
        
        // Create header
        const $header = $('<div>', { 'class': 'toast-header' });
        
        // Add avatar/icon using server-provided HTML or fallback logic
        if (toastData.avatar_html) {
            // Use centralized avatar HTML from server
            $header.append($(toastData.avatar_html).addClass('toast-avatar'));
        } else if (isSystemNotification) {
            // Fallback for older notifications without centralized avatar HTML
            let iconClass = '';
            let iconColor = '';
            
            switch (notificationType) {
                case 'account_banned':
                    iconClass = 'fas fa-ban';
                    iconColor = '#EF4444';
                    break;
                case 'account_warning':
                    iconClass = 'fas fa-exclamation-triangle';
                    iconColor = '#F59E0B';
                    break;
                case 'account_unbanned':
                    iconClass = 'fas fa-check-circle';
                    iconColor = '#10B981';
                    break;
            }
            
            $header.append($('<div>', {
                'class': 'toast-avatar system-notification-icon',
                'style': `background-color: ${iconColor}; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 8px; flex-shrink: 0;`
            }).append($('<i>', {
                'class': iconClass,
                'style': 'color: #FFFFFF; font-size: 16px;'
            })));
        } else {
            // Fallback to profile picture for non-system notifications
            $header.append($('<img>', {
                'src': profilePic,
                'alt': senderName,
                'onerror': `this.src='${escapeHtml(sud_config.urls.img_path)}/default-profile.jpg';`,
                'style': 'width: 32px; height: 32px; border-radius: 50%; margin-right: 8px; flex-shrink: 0;'
            }));
        }
        
        $header.append($('<div>', { 'class': 'toast-title' }).html(toastTitle));
        $header.append($('<div>', { 'class': 'toast-close', 'title': 'Dismiss' }).text('×'));
        
        // Create body
        const $body = $('<div>', { 'class': 'toast-body' });
        $body.append($('<div>', { 'class': 'toast-message' }).html(displayContentHtml));
        
        const $actions = $('<div>', { 'class': 'toast-actions' });
        $actions.append($('<button>', { 
            'class': 'toast-btn',
            'data-action-link': toastActionLink 
        }).text(toastActionText));
        $body.append($actions);
        
        $toast.append($header).append($body);
        $container.append($toast);
        setTimeout(() => $toast.addClass('show'), 10);
    
        const autoHideDuration = 7000;
        let remainingDuration = autoHideDuration;
        let hideTimeoutId = null;
        let lastVisibleTimestamp = null;
    
        const startOrResumeHideTimer = () => {
            clearTimeout(hideTimeoutId);
            if (document.visibilityState === 'visible' && remainingDuration > 0) {
                lastVisibleTimestamp = Date.now();
                hideTimeoutId = setTimeout(() => {
    
                    if ($toast.length && $toast.closest('body').length) { 
                        SUD.hideToast($toast);
                    }
                }, remainingDuration);
                $toast.data('hideTimeoutId', hideTimeoutId);
            }
        };
    
        const pauseHideTimer = () => {
            clearTimeout(hideTimeoutId);
            if (lastVisibleTimestamp) {
                const elapsedWhileVisible = Date.now() - lastVisibleTimestamp;
                remainingDuration -= elapsedWhileVisible;
                if (remainingDuration < 0) remainingDuration = 0;
            }
            lastVisibleTimestamp = null;
        };
    
        const handleVisibilityChange = () => {
            if (!$toast.closest('body').length) { 
                document.removeEventListener('visibilitychange', handleVisibilityChange);
                return;
            }
            if (document.visibilityState === 'visible') {
                startOrResumeHideTimer();
            } else {
                pauseHideTimer();
            }
        };
    
        if (document.visibilityState === 'visible') { startOrResumeHideTimer(); }
        else { lastVisibleTimestamp = null; }
        document.addEventListener('visibilitychange', handleVisibilityChange);
        $toast.data('visibilityChangeHandler', handleVisibilityChange); 
    
        $toast.on('mouseenter', pauseHideTimer);
        $toast.on('mouseleave', startOrResumeHideTimer);
    
        $toast.find('.toast-close').on('click', function(e) {
            e.stopPropagation();
            SUD.hideToast($(this).closest('.toast-notification'));
        });
    
        const navigateToTarget = function(e) {
            const $clickedToast = $(this).closest('.toast-notification');
    
            const actionLink = $(e.currentTarget).is('.toast-btn') ? $(e.currentTarget).data('action-link') : $clickedToast.find('.toast-btn').data('action-link');
    
            if (actionLink && actionLink !== '#') {
                window.location.href = actionLink;
            }
            SUD.hideToast($clickedToast);
        };
    
        $toast.find('.toast-body').on('click', function(e) { 
            if (!$(e.target).is('.toast-btn, .toast-btn *')) { 
                navigateToTarget.call(this, e);
            }
        });
        $toast.find('.toast-btn').on('click', function(e) { 
            e.stopPropagation();
            navigateToTarget.call(this, e);
        });
        $toast.find('.toast-header').on('click', function(e) { 
            if (!$(e.target).is('.toast-close, .toast-close *')) { 
                navigateToTarget.call(this, e);
            }
        });
    };

    SUD.updateHeaderMessageCount = function(unreadCount) {
        const count = parseInt(unreadCount) || 0;
        const $msgBadge = $('.nav-item a[href*="messages"] .badge'); 

        if (count > 0) {
            if ($msgBadge.length) {
                $msgBadge.text(count).show();
            } else {
                const $link = $('.nav-item a[href*="messages"]');
                if ($link.length && $link.find('.badge').length === 0) {
                    $link.append(`<span class="badge">${count}</span>`);
                }
            }
        } else {
            $msgBadge.hide().text('0');
        }

        const $tabCount = $('.message-tabs .tab[data-tab="unread"] .unread-tab-count');
        if ($tabCount.length) {
            if (count > 0) {
                $tabCount.text(count).removeClass('hidden');
            } else {
                $tabCount.text('0').addClass('hidden');
            }
        }
    };

    SUD.playNotificationSound = function(type = 'notification') {
        if (typeof sud_config === 'undefined' || !sud_config.sounds) {
            console.warn("SUD sound config is not available.");
            return;
        }
    
        let soundUrl = '';
        let volume = 0.4;
    
        switch (type) {
            case 'match':
                soundUrl = sud_config.sounds.match;
                volume = 0.5;
                break;
            case 'cash':
                soundUrl = sud_config.sounds.cash;
                volume = 0.6;
                break;
            case 'message':
                soundUrl = sud_config.sounds.message || sud_config.sounds.notification;
                break;
            case 'notification':
            default:
                soundUrl = sud_config.sounds.notification || sud_config.sounds.message;
                break;
        }
    
        if (!soundUrl && sud_config.sud_url) {
            console.warn(`Sound URL for type '${type}' not found in config, using fallback.`);
            soundUrl = `${sud_config.sud_url}/assets/sounds/message.mp3`;
        }
    
        if (!soundUrl) {
            console.warn(`Could not determine a sound URL for type: ${type}`);
            return;
        }
    
        try {
            const audio = new Audio(soundUrl);
            audio.volume = volume;
            audio.play().catch(error => {
            });
        } catch (e) {
            console.error("Error creating or playing audio:", e);
        }
    };

    SUD.loadNotifications = function(markAsReadOnClick = false, showLoader = true) {
        const $container = $('#notification-list');
        if (!$container.length) return;
        
        // Show skeleton loader when requested (e.g., when user clicks bell)
        if (showLoader) {
            $container.html(`
                <div class="notification-skeleton" style="padding: 15px;">
                    <div class="skeleton-item" style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div class="skeleton-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: #262626; margin-right: 12px;"></div>
                        <div class="skeleton-content" style="flex: 1;">
                            <div class="skeleton-line" style="height: 12px; background: #262626; border-radius: 6px; margin-bottom: 8px; width: 80%;"></div>
                            <div class="skeleton-line" style="height: 10px; background: #1a1a1a; border-radius: 5px; width: 60%;"></div>
                        </div>
                    </div>
                    <div class="skeleton-item" style="display: flex; align-items: center; margin-bottom: 15px;">
                        <div class="skeleton-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: #262626; margin-right: 12px;"></div>
                        <div class="skeleton-content" style="flex: 1;">
                            <div class="skeleton-line" style="height: 12px; background: #262626; border-radius: 6px; margin-bottom: 8px; width: 70%;"></div>
                            <div class="skeleton-line" style="height: 10px; background: #1a1a1a; border-radius: 5px; width: 50%;"></div>
                        </div>
                    </div>
                    <div class="skeleton-item" style="display: flex; align-items: center;">
                        <div class="skeleton-avatar" style="width: 40px; height: 40px; border-radius: 50%; background: #262626; margin-right: 12px;"></div>
                        <div class="skeleton-content" style="flex: 1;">
                            <div class="skeleton-line" style="height: 12px; background: #262626; border-radius: 6px; margin-bottom: 8px; width: 90%;"></div>
                            <div class="skeleton-line" style="height: 10px; background: #1a1a1a; border-radius: 5px; width: 40%;"></div>
                        </div>
                    </div>
                </div>
            `); 
        }

        if (typeof sud_config === 'undefined' || !sud_config.sud_url) {
            $container.html('<p style="padding:15px; color:red;">Error loading notifications (Config missing).</p>'); return;
        }

        $.ajax({
            url: `${sud_config.sud_url}/ajax/get-notifications.php`,
            type: 'GET',
            data: {
                limit: 7
            },
            dataType: 'json',
            success: (response) => {
                if (response && response.success) {
                    SUD.renderNotifications(response.notifications || []);

                    SUD.updateNotificationCount(response.unread_count || 0);
                    SUD.notificationsLoaded = true;
                } else {
                    $container.html('<p style="padding:15px; color:orange;">Could not load notifications.</p>');
                    console.warn("Load notifications failed:", response?.message);
                }
            },
            error: (xhr) => {
                $container.html('<p style="padding:15px; color:red;">Failed to load notifications (Network Error).</p>');
                console.error("Load notifications AJAX error:", xhr.status, xhr.responseText);
            }
        });
    };

    SUD.renderNotifications = function(notifications) {
        const $container = $('#notification-list'); 
        $container.empty();
    
        if (!notifications || notifications.length === 0) {
            $container.html('<div class="empty-notifications" style="padding: 20px; text-align: center; color: #888;">No new notifications.</div>');
            return;
        }
    
        notifications.forEach(function(notification) {
            const profilePic = notification.profile_pic || `${sud_config.urls.img_path}/default-profile.jpg`;
            const isUnread = !notification.is_read;
            const isPremiumTeaser = notification.is_premium_teaser || false;
            const canViewProfiles = sud_config?.user_can_view_profiles || false;
            let displayContent = notification.content || ''; 
            let upgradedContent = displayContent;
            
            // Use server-provided avatar HTML from centralized function
            let avatarHtml = notification.avatar_html || `<img src="${profilePic}" alt="User" class="notification-avatar" onerror="this.src='${sud_config.urls.img_path}/default-profile.jpg';">`;
    
            if (isPremiumTeaser && !canViewProfiles) {
                upgradedContent = displayContent.replace(
                    /(Upgrade to Premium|upgrade to premium|Upgrade to see who)/g, 
                    '<span class="upgrade-text">$1</span>'
                );
            }
    
            if (notification.type === 'gift') {
                upgradedContent = upgradedContent.replace(/\s+(fas?|far|fab|fal|fad)\s+fa-[\w-]+(!?)$/gi, '$2').trim();
            }
    
            const premiumTeaserClass = (isPremiumTeaser && !canViewProfiles) ? 'premium-teaser' : '';
    
            const itemHtml = `
                <div class="notification-item ${isUnread ? 'unread' : ''} ${premiumTeaserClass}" 
                     data-id="${notification.id}" 
                     data-type="${notification.type}" 
                     data-related-id="${notification.related_id || ''}"
                     ${notification.upgrade_url ? `data-upgrade-url="${notification.upgrade_url}"` : ''}>
                    ${avatarHtml}
                    <div class="notification-content">
                        <div class="notification-content-text">${upgradedContent}</div>
                        <div class="notification-time">${notification.time_ago || ''}</div>
                    </div>
                    ${isUnread ? '<span class="unread-indicator"></span>' : ''}
                    ${(isPremiumTeaser && !canViewProfiles) ? '<span class="premium-indicator"><i class="fas fa-lock"></i></span>' : ''}
                </div>`;
            $container.append(itemHtml);
        });
    };

    SUD.updateNotificationCount = function(count) {
        const $badge = $('.notification-badge'); 
        count = parseInt(count) || 0;

        if (count > 0) {
            if ($badge.length) {
                $badge.text(count).show();
            } else {
                const $toggle = $('#notification-toggle');
                if ($toggle.length && $toggle.find('.notification-badge').length === 0) {
                    $toggle.append(`<span class="badge notification-badge">${count}</span>`);
                }
            }
        } else {
            $badge.hide().text('0');
        }
    };

    SUD.showToast = function(type, title, message, duration = 3000) {
        const $container = $('#toast-container');
        if (!$container.length) {
            $('body').append('<div id="toast-container" class="toast-container"></div>');
        }

        const iconClass = SUD.getIconForType(type);
        const toastId = 'toast-' + Date.now() + Math.random().toString(36).substr(2, 5);
        
        // Get color for notification-specific toasts
        let iconColor = '';
        switch (type) {
            case 'boost_purchased':
                iconColor = 'style="color: #9B59B6;"';
                break;
            case 'coins_purchased':
                iconColor = 'style="color: #F2D04F;"';
                break;
            case 'super_swipe_purchased':
                iconColor = 'style="color: #E91E63;"';
                break;
            case 'subscription':
                iconColor = 'style="color: #FF66CC;"';
                break;
        }
        
        const toast = $(`
            <div class="toast-notification ${type} ${duration <= 0 ? 'persistent' : ''}" id="${toastId}">
                <div class="toast-icon"><i class="fas ${iconClass}" ${iconColor}></i></div>
                <div class="toast-content">
                    <div class="toast-title">${$('<div>').text(title).html()}</div>
                    <div class="toast-message">${$('<div>').text(message).html()}</div>
                </div>
                <div class="toast-close" title="Dismiss">×</div>
            </div>`);

        $container.append(toast);
        setTimeout(() => toast.addClass('show'), 10);

        if (duration > 0) {
            const hideTimeoutId = setTimeout(() => SUD.hideToast(toast), duration);
            toast.data('hideTimeoutId', hideTimeoutId); 
            
            // Add hover behavior to pause/resume auto-close timer
            toast.on('mouseenter', function() {
                const currentTimeoutId = $(this).data('hideTimeoutId');
                if (currentTimeoutId) {
                    clearTimeout(currentTimeoutId);
                    $(this).removeData('hideTimeoutId');
                }
            });
            
            toast.on('mouseleave', function() {
                // Restart the timer when mouse leaves
                if (!$(this).data('hideTimeoutId')) {
                    const newTimeoutId = setTimeout(() => SUD.hideToast($(this)), duration);
                    $(this).data('hideTimeoutId', newTimeoutId);
                }
            });
        }

        toast.find('.toast-close').on('click', function() {
            const $thisToast = $(this).closest('.toast-notification');
            const timeoutId = $thisToast.data('hideTimeoutId');
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            SUD.hideToast($thisToast);
        });
    };

    SUD.hideToast = function(toastElement) {
        if (toastElement && toastElement.length) {
            const timeoutId = toastElement.data('hideTimeoutId');
            if (timeoutId) {
                clearTimeout(timeoutId);
            }
            const visibilityHandler = toastElement.data('visibilityChangeHandler');
            if (visibilityHandler) {
                document.removeEventListener('visibilitychange', visibilityHandler);
                toastElement.removeData('visibilityChangeHandler');
            }
            toastElement.removeClass('show');
            setTimeout(() => toastElement.remove(), 350);
        }
    };

    SUD.getIconForType = function(type) {
        switch (type) {
            case 'success': return 'fa-check-circle'; 
            case 'error': return 'fa-times-circle';
            case 'info': return 'fa-info-circle';
            case 'warning': return 'fa-exclamation-triangle';
            // Purchase notification icons
            case 'boost_purchased': return 'fa-rocket';
            case 'coins_purchased': return 'fa-coins';
            case 'super_swipe_purchased': return 'fa-heart';
            case 'subscription': return 'fa-crown';
            default: return 'fa-bell';
        }
    };

    SUD.loadMoreItems = function(options) {
        const { buttonSelector, url, data, onSuccess, onError, containerSelector, itemSelector } = options;
        const $button = $(buttonSelector);
        if ($button.prop('disabled')) return;
        const originalText = $button.text();
        $button.text('Loading...').prop('disabled', true);

        $.ajax({
            url: url, type: 'GET', data: data, dataType: 'json',
            success: function(response) {
                if (response && response.success) {
                    if (typeof onSuccess === 'function') {
                        onSuccess(response); 
                    } else {
                        console.warn("SUD.loadMoreItems: No onSuccess callback provided.");
                    }

                    if (response.has_more) {
                        $button.text(originalText).prop('disabled', false);
                    } else {
                        $button.text('No more items').prop('disabled', true);

                    }
                } else {
                    if (typeof onError === 'function') {
                        onError(response);
                    } else {
                        SUD.showToast('error', 'Error', response?.message || 'Failed to load more items.');
                    }
                    $button.text(originalText).prop('disabled', false); 
                }
            },
            error: function(xhr) {
                if (typeof onError === 'function') {
                    onError({ success: false, message: 'Network error.' });
                } else {
                    SUD.showToast('error', 'Network Error', 'Failed to load more items.');
                }
                console.error("Load More AJAX error:", xhr.status, xhr.responseText);
                $button.text(originalText).prop('disabled', false);
            }
        });
    };
    SUD.formatRelativeTime = function(timestamp) {
        const now = new Date();
        const past = new Date(timestamp * 1000); 
        const diffInSeconds = Math.floor((now - past) / 1000);
        const diffInMinutes = Math.floor(diffInSeconds / 60);
        const diffInHours = Math.floor(diffInMinutes / 60);
        const diffInDays = Math.floor(diffInHours / 24);

        if (diffInSeconds < 60) return "Just now";
        if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
        if (diffInHours < 24) return `${diffInHours}h ago`;
        if (diffInDays === 1) return "Yesterday";
        if (diffInDays < 7) return `${diffInDays}d ago`;

        return past.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    };

    // Create skeleton loaders matching user card design
    SUD.createSkeletonLoaders = function(count = 12) {
        const skeletonCards = [];
        
        for (let i = 0; i < count; i++) {
            skeletonCards.push(`
                <div class="user-card skeleton-card-grid">
                    <div class="user-card-img skeleton-img">
                        <div class="skeleton-shimmer"></div>
                    </div>
                    <div class="user-card-overlay">
                        <div class="user-details">
                            <div class="top-details">
                                <div class="name-age-status">
                                    <span class="skeleton-text skeleton-name"></span>
                                    <span class="skeleton-badge"></span>
                                </div>
                                <div class="favorite-icon">
                                    <i class="skeleton-heart"></i>
                                </div>
                            </div>
                            <div class="location-container">
                                <div class="location">
                                    <i class="skeleton-icon"></i>
                                    <span class="skeleton-text skeleton-location"></span>
                                </div>
                                <div class="skeleton-level-badge"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        }
        
        return skeletonCards.join('');
    };

    SUD.currentPageByFilter = {};
    
    // Enhanced member management with background prefetching
    SUD.memberPools = {}; // Stores pre-fetched members for each filter: {filter: {pool: [], nextPage: 2, isLoading: false}}
    SUD.displayedMembersByFilter = {}; // Track how many members are displayed for each filter
    SUD.displayedMemberIds = {}; // Track IDs of members already shown to prevent duplicates
    
    // Initialize member pool for a filter
    SUD.initMemberPool = function(filter) {
        if (!SUD.memberPools[filter]) {
            SUD.memberPools[filter] = {
                pool: [],
                nextPage: 1,
                isLoading: false,
                hasMore: true
            };
            SUD.displayedMembersByFilter[filter] = 0;
            SUD.displayedMemberIds[filter] = new Set(); // Track displayed member IDs
        }
    };
    
    // Get members per page from config with fallback
    SUD.getMembersPerPage = function() {
        return sud_config.members_per_page || sud_config.initial_limit || 12;
    };
    
    // Get background fetch size from config with fallback  
    SUD.getBackgroundFetchSize = function() {
        return sud_config.background_fetch_size || 40;
    };
    
    // Get prefetch threshold from config with fallback
    SUD.getPrefetchThreshold = function() {
        return sud_config.prefetch_threshold || 8;
    };
    
    // Remove duplicate members from array based on displayed IDs
    SUD.removeDuplicateMembers = function(members, filter) {
        if (!members || !Array.isArray(members)) return [];
        
        const displayedIds = SUD.displayedMemberIds[filter] || new Set();
        return members.filter(member => {
            const memberId = member.id || member.user_id;
            return memberId && !displayedIds.has(memberId);
        });
    };
    
    // Mark members as displayed to prevent future duplicates
    SUD.markMembersAsDisplayed = function(members, filter) {
        if (!members || !Array.isArray(members)) return;
        
        if (!SUD.displayedMemberIds[filter]) {
            SUD.displayedMemberIds[filter] = new Set();
        }
        
        members.forEach(member => {
            const memberId = member.id || member.user_id;
            if (memberId) {
                SUD.displayedMemberIds[filter].add(memberId);
            }
        });
    };
    
    // Background prefetch function - silently fetches members for faster loading
    SUD.backgroundPrefetch = function(filter) {
        const pool = SUD.memberPools[filter];
        if (!pool || pool.isLoading || !pool.hasMore) {
            return;
        }
        
        const backgroundFetchSize = SUD.getBackgroundFetchSize();
        
        pool.isLoading = true;
        
        // Silent AJAX call - no UI changes, just populate pool
        $.ajax({
            url: `${sud_config.sud_url}/ajax/get-members.php`,
            type: 'GET',
            data: { 
                filter: filter, 
                page: pool.nextPage, 
                limit: backgroundFetchSize 
            },
            dataType: 'json',
            success: function(response) {
                if (response && response.success && response.users) {
                    // Filter out duplicates before adding to pool
                    const newMembers = SUD.removeDuplicateMembers(response.users, filter);
                    
                    // Add to pool
                    pool.pool = pool.pool.concat(newMembers);
                    pool.nextPage++;
                    pool.hasMore = response.pagination ? response.pagination.has_more : false;
                    
                    // If we got fewer than expected due to duplicates and still have more, fetch again
                    if (newMembers.length < response.users.length / 2 && pool.hasMore && !pool.isLoading) {
                        setTimeout(() => SUD.backgroundPrefetch(filter), 500);
                    }
                }
            },
            error: function() {
                // Silent failure for background prefetch
            },
            complete: function() {
                pool.isLoading = false;
            }
        });
    };
    
    // Smart member display function - uses pool when available, falls back to AJAX
    SUD.displayMembersFromPool = function(filter, replaceContent = false, $button = null) {
        SUD.initMemberPool(filter);
        
        const pool = SUD.memberPools[filter];
        const membersPerPage = SUD.getMembersPerPage();
        const prefetchThreshold = SUD.getPrefetchThreshold();
        
        // Check if we have enough members in pool
        if (pool.pool.length >= membersPerPage) {
            // For tab switching (replaceContent=true), always start from beginning of pool
            // For load more (replaceContent=false), continue from where we left off
            let startIndex, endIndex, membersToShow;
            
            if (replaceContent) {
                // Tab switch - start from beginning of pool
                startIndex = 0;
                endIndex = membersPerPage;
                membersToShow = pool.pool.slice(startIndex, endIndex);
            } else {
                // Load more - continue from current position
                startIndex = SUD.displayedMembersByFilter[filter] || 0;
                endIndex = startIndex + membersPerPage;
                membersToShow = pool.pool.slice(startIndex, endIndex);
            }
            
            // Mark members as displayed to prevent duplicates
            SUD.markMembersAsDisplayed(membersToShow, filter);
            if (replaceContent) {
                // Tab switch - reset display count to current batch
                SUD.displayedMembersByFilter[filter] = membersToShow.length;
            } else {
                // Load more - add to existing count
                SUD.displayedMembersByFilter[filter] += membersToShow.length;
            }
            
            // Display members instantly 
            const $grid = $('#user-grid-dashboard');
            const $loadMoreContainer = $('#load-more-container-dashboard');
            
            if (replaceContent) {
                $grid.empty();
                SUD.displayedMembersByFilter[filter] = membersToShow.length;
                // Reset displayed IDs tracking for fresh start
                SUD.displayedMemberIds[filter] = new Set();
                SUD.markMembersAsDisplayed(membersToShow, filter);
            }
            
            SUD.appendUsersToGrid(membersToShow, $grid);
            
            // Update load more button - check if we have more members to show
            const currentDisplayed = SUD.displayedMembersByFilter[filter] || 0;
            const hasMoreInPool = currentDisplayed < pool.pool.length;
            const hasMore = hasMoreInPool || pool.hasMore;
            if ($button) {
                if (hasMore) {
                    $button.text('Load More Members').prop('disabled', false);
                    $loadMoreContainer.show();
                } else {
                    $button.text('No More Members').prop('disabled', true);
                    $loadMoreContainer.hide(); // Hide the entire container when no more members
                }
            }
            
            // Background prefetch disabled to maintain data consistency
            // Users will see fresh data on each tab switch instead
            
            return true; // Successfully displayed from pool
        }
        
        // If pool doesn't have enough members but has some, show what we have and hide load more
        if (pool.pool.length > 0 && !pool.hasMore) {
            // For tab switch, start from beginning; for load more, continue from current position
            const startIndex = replaceContent ? 0 : (SUD.displayedMembersByFilter[filter] || 0);
            const remainingMembers = pool.pool.slice(startIndex); // Take remaining without modifying pool
            
            if (remainingMembers.length > 0) {
                SUD.markMembersAsDisplayed(remainingMembers, filter);
                SUD.displayedMembersByFilter[filter] += remainingMembers.length;
                
                const $grid = $('#user-grid-dashboard');
                const $loadMoreContainer = $('#load-more-container-dashboard');
                
                if (replaceContent) {
                    $grid.empty();
                    SUD.displayedMembersByFilter[filter] = remainingMembers.length;
                    SUD.displayedMemberIds[filter] = new Set();
                    SUD.markMembersAsDisplayed(remainingMembers, filter);
                }
                
                SUD.appendUsersToGrid(remainingMembers, $grid);
                
                // Hide load more since no more members available
                if ($button) {
                    $button.text('No More Members').prop('disabled', true);
                    $loadMoreContainer.hide();
                }
                
                return true;
            }
        }
        
        return false; // Pool doesn't have enough members, caller should use AJAX
    };

    SUD.initMemberTabs = function() {
        $('.tab').off('click.memberTabSwitch').on('click.memberTabSwitch', function() {
            const $thisTab = $(this);
            if ($thisTab.hasClass('active')) {
                return;
            }

            const previousFilter = $('.tab.active').data('filter') || 'unknown';
            $('.tab').removeClass('active');
            $thisTab.addClass('active');

            const newFilter = $thisTab.data('filter') || $thisTab.text().toLowerCase().replace(' ', '-');
            if (!newFilter) {
                return;
            }

            // Reset tracking and clear pool to ensure fresh data on every tab switch
            SUD.currentPageByFilter[newFilter] = 1;
            if (SUD.displayedMemberIds[newFilter]) {
                SUD.displayedMemberIds[newFilter].clear();
            }
            SUD.displayedMembersByFilter[newFilter] = 0;
            
            // Clear the pool to force fresh AJAX fetch for consistent data
            if (SUD.memberPools[newFilter]) {
                SUD.memberPools[newFilter].pool = [];
                SUD.memberPools[newFilter].nextPage = 1;
                SUD.memberPools[newFilter].hasMore = true;
                SUD.memberPools[newFilter].isLoading = false;
            }
            
            SUD.fetchAndDisplayMembers(newFilter, 1, true, $('#load-more-btn'));
        });

        const initialFilter = $('.tab.active').data('filter') || $('.tab.active').text().toLowerCase().replace(' ', '-');
        if (initialFilter) {
            SUD.currentPageByFilter[initialFilter] = 1;
            
            // Initialize pools for all filters but disable background prefetching
            // to ensure consistent data ordering when switching tabs
            const allFilters = ['active', 'nearby', 'newest'];
            allFilters.forEach(filter => {
                SUD.initMemberPool(filter);
                // Background prefetching disabled to prevent data inconsistency
            });
        }
    };

    SUD.initLoadMoreMembers = function() {
        $('#load-more-btn').off('click.loadMoreMembers').on('click.loadMoreMembers', function() {
            const $button = $(this);
            if ($button.prop('disabled')) {
                return;
            }

            const currentFilter = $('.tab.active').data('filter') || $('.tab.active').text().toLowerCase().replace(' ', '-');
            if (!currentFilter) {
                $button.text('Error').prop('disabled', true);
                return;
            }

            // Try pool first for instant loading
            if (SUD.displayMembersFromPool(currentFilter, false, $button)) {
                return;
            }
            
            // Fallback to AJAX if pool doesn't have enough members
            const currentPageForFilter = SUD.currentPageByFilter[currentFilter] || 1;
            const nextPage = currentPageForFilter + 1;

            SUD.fetchAndDisplayMembers(currentFilter, nextPage, false, $button);
        });
    };

    SUD.fetchAndDisplayMembers = function(filter, page, replaceContent = false, $button = null, searchCriteria = null) {
        const $grid = $('#user-grid-dashboard');
        const $loadMoreContainer = $('#load-more-container-dashboard');
        const $noResultsPlaceholder = $('#dashboard-no-results');
        const limit = SUD.getMembersPerPage();

        if (!$grid.length || typeof sud_config === 'undefined' || !sud_config.sud_url) {
            return;
        }
        
        // Always fetch fresh data for first page to ensure consistency
        // This prevents stale pool data from causing inconsistent member arrangements

        let originalButtonText = '';
        const isLoadMoreButton = $button && $button.prop('id') === 'load-more-btn';
        const isApplyFilterButton = $button && $button.prop('id') === 'apply-dashboard-filters';

        if (isLoadMoreButton) {
            originalButtonText = $button.text();
            $button.text('Loading...').prop('disabled', true);
        } else if (isApplyFilterButton) {
            originalButtonText = $button.text();
            $button.text('Applying...').prop('disabled', true);
        }

        if (replaceContent) {
            $grid.html(SUD.createSkeletonLoaders(limit));
            if ($loadMoreContainer.length) $loadMoreContainer.hide();
            if ($noResultsPlaceholder.length) $noResultsPlaceholder.hide();
        }

        const ajaxData = { filter: filter, page: page, limit: limit };
        if (filter === 'search' && searchCriteria) {
            $.extend(ajaxData, searchCriteria);
        }

        $.ajax({
            url: `${sud_config.sud_url}/ajax/get-members.php`,
            type: 'GET', data: ajaxData, dataType: 'json',
            success: function(response) {
                
                if (replaceContent) {
                    $grid.empty();
                }

                if (response && response.success) {
                    SUD.currentPageByFilter[filter] = page;

                    if (response.users && response.users.length > 0) {
                        // Filter out duplicates before displaying
                        const uniqueUsers = SUD.removeDuplicateMembers(response.users, filter);
                        
                        if (uniqueUsers.length > 0) {
                            SUD.appendUsersToGrid(uniqueUsers, $grid);
                            if ($noResultsPlaceholder.length) $noResultsPlaceholder.hide();
                            
                            // Mark displayed members to prevent future duplicates
                            SUD.markMembersAsDisplayed(uniqueUsers, filter);
                            
                            // Initialize pool and add members to tracking
                            SUD.initMemberPool(filter);
                            const pool = SUD.memberPools[filter];
                            
                            if (replaceContent) {
                                SUD.displayedMembersByFilter[filter] = uniqueUsers.length;
                                // Reset displayed IDs for fresh start
                                SUD.displayedMemberIds[filter] = new Set();
                                SUD.markMembersAsDisplayed(uniqueUsers, filter);
                            } else {
                                SUD.displayedMembersByFilter[filter] += uniqueUsers.length;
                            }
                            
                            // Update pool state
                            pool.nextPage = page + 1;
                            pool.hasMore = response.pagination ? response.pagination.has_more : false;
                            
                            // Trigger initial background prefetch for this filter if needed
                            if (page === 1 && pool.pool.length === 0 && pool.hasMore) {
                                setTimeout(() => SUD.backgroundPrefetch(filter), 500); // Small delay
                            }
                        } else {
                            // All users were duplicates, try to fetch more if available
                            if (response.pagination && response.pagination.has_more) {
                                SUD.fetchAndDisplayMembers(filter, page + 1, false, $button, searchCriteria);
                                return;
                            }
                        }

                        // Check if we have more members available AND if we got expected amount
                        const expectedMembersPerPage = SUD.getMembersPerPage();
                        const actualMembersReceived = uniqueUsers ? uniqueUsers.length : 0;
                        const hasMoreMembers = response.pagination && response.pagination.has_more;
                        const gotFullPage = actualMembersReceived >= expectedMembersPerPage;
                        
                        if (hasMoreMembers && gotFullPage) {
                            if (isLoadMoreButton) {
                                const defaultButtonText = 'Load More Members';
                                $button.text(defaultButtonText).prop('disabled', false);
                            }
                            if ($loadMoreContainer.length) $loadMoreContainer.show();
                        } else {
                            // No more members OR didn't get a full page (indicating end of data)
                            if (isLoadMoreButton) {
                                $button.text('No More Members').prop('disabled', true);
                            }
                            if ($loadMoreContainer.length) $loadMoreContainer.hide();
                            
                            // Update pool state to reflect no more members
                            if (SUD.memberPools[filter]) {
                                SUD.memberPools[filter].hasMore = false;
                            }
                        }
                    } else {
                        if (replaceContent) {
                            const message = (filter === 'search') ? "No members matched your search criteria." : "There are currently no members matching your preferences.";
                            $grid.html(
                                '<div class="no-results-placeholder" id="dashboard-no-results">' +
                                '<div class="no-results-icon"><i class="fas fa-search-minus"></i></div>' +
                                '<h3>No Members Found</h3>' +
                                '<p>' + message + ' Try adjusting filters or check back later.</p>' +
                                '</div>'
                            );
                            if ($noResultsPlaceholder.length) $noResultsPlaceholder.show();
                        }
                        if (isLoadMoreButton) {
                            $button.text('No More Members').prop('disabled', true);
                        }
                        if ($loadMoreContainer.length) $loadMoreContainer.hide();
                    }
                } else {
                    if (replaceContent) {
                        $grid.html('<div class="error"><i class="fas fa-exclamation-circle"></i> Error loading members.</div>');
                        if ($noResultsPlaceholder.length) $noResultsPlaceholder.hide();
                    }
                    if (isLoadMoreButton) {
                        $button.text(originalButtonText || 'Load More Members').prop('disabled', false);
                    }
                    // Handle specific error types
                    if (response && response.message === 'security_check_failed') {
                        SUD.showToast('error', 'Security Error', 'Session expired. Please refresh the page and try again.');
                        // Optionally reload the page after a delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                    } else {
                        SUD.showToast('error', 'Error', response?.message || 'Failed to load members.');
                    }
                }
            },
            error: function(xhr, status, error) {
                // Check if this is a security error from the response
                let errorMessage = 'Failed to load members.';
                try {
                    const response = JSON.parse(xhr.responseText);
                    if (response && response.message === 'security_check_failed') {
                        SUD.showToast('error', 'Security Error', 'Session expired. Please refresh the page and try again.');
                        setTimeout(() => {
                            window.location.reload();
                        }, 2000);
                        return;
                    }
                    if (response && response.message) {
                        errorMessage = response.message;
                    }
                } catch(e) {
                    // Not JSON response, use default error handling
                }
                
                if (replaceContent) {
                    $grid.html('<div class="error"><i class="fas fa-exclamation-circle"></i> Network error loading members.</div>');
                    if ($noResultsPlaceholder.length) $noResultsPlaceholder.hide();
                }
                if (isLoadMoreButton) {
                    $button.text(originalButtonText || 'Load More Members').prop('disabled', false);
                }
                SUD.showToast('error', 'Network Error', errorMessage);
            },
            complete: function() {
                if (isApplyFilterButton) {
                    $button.text(originalButtonText || 'Apply Filters').prop('disabled', false);
                }
            }
        });
    };

    SUD.appendUsersToGrid = function (users, $gridContainer) {
        if (!users || users.length === 0 || !$gridContainer || !$gridContainer.length) {
            return;
        }

        const fragment = document.createDocumentFragment();
        const defaultProfilePic = `${sud_config.urls.img_path}/default-profile.jpg`;
        const verifiedBadgeUrl = `${sud_config.urls.img_path}/verified-profile-badge.png`;

        users.forEach(function (user) {
            if (typeof user !== 'object' || user === null) return;

            const userId = user.id || 0;
            const nameRaw = user.name || 'User';
            const age = user.age ? `, ${parseInt(user.age)}` : '';
            const locationFull = user.location_formatted || 'Location not specified';

            const nameDisplay = nameRaw.length > 7 ? nameRaw.substring(0, 7) + '...' : nameRaw;
            const locationDisplay = locationFull.length > 25 ? locationFull.substring(0, 25) + '...' : locationFull;

            const nameAttrEscaped = $('<div>').text(nameRaw).html();
            const nameDisplayEscaped = $('<div>').text(nameDisplay).html();
            const locationFullEscaped = $('<div>').text(locationFull).html();
            const locationDisplayEscaped = $('<div>').text(locationDisplay).html();

            const profilePic = user.profile_pic || defaultProfilePic;
            const isOnline = user.is_online || false;
            const isVerified = user.is_verified || false;
            const isFavorite = user.is_favorite || false;
            const level = user.level || null;
            const premiumBadgeHtml = user.premium_badge_html_small || '';
            const hasActiveBoost = user.has_active_boost || false;
            const boostType = user.boost_type || 'mini';
            const boostName = user.boost_name || 'Profile Boost';
            
            // Helper function to escape HTML
            const escapeHtml = (unsafe) => {
                return $('<div>').text(unsafe).html();
            };
            
            // Create boost badge HTML if user has active boost
            let boostBadgeHtml = '';
            if (hasActiveBoost === true || hasActiveBoost === 'true' || hasActiveBoost === 1 || hasActiveBoost === '1') {
                const safeBoostType = escapeHtml(boostType);
                const safeBoostName = escapeHtml(boostName);
                boostBadgeHtml = `<span class="boost-badge boost-${safeBoostType}" title="${safeBoostName} Active"><i class="fas fa-rocket"></i></span>`;
            }

            let profileUrl = 'javascript:void(0);';
            if (userId && typeof sud_config !== 'undefined' && sud_config.sud_url) {
                profileUrl = `${sud_config.sud_url}/pages/profile?id=${userId}`;
            }

            const userCard = document.createElement('div');
            userCard.className = 'user-card';
            userCard.dataset.userId = userId;
            userCard.dataset.profileUrl = profileUrl;

            userCard.innerHTML = `
                <div class="user-card-img">
                    <img src="${profilePic}" alt="${nameAttrEscaped}" loading="lazy" onerror="this.onerror=null; this.src='${defaultProfilePic}';">
                </div>
                <div class="user-card-overlay">
                    <div class="user-details">
                        <div class="top-details">
                            <div class="name-age-status">
                                <span class="username">${nameDisplayEscaped}${age}</span>
                                ${premiumBadgeHtml}
                                ${boostBadgeHtml}
                                ${isVerified ? `<span class="verified-badge"><img class="verify-icon" src="${verifiedBadgeUrl}" alt="verified"></span>` : ''}
                                ${isOnline ? `<span class="online-status" title="Online Now"><span class="status-dot"></span></span>` : ''}
                            </div>
                            <div class="favorite-icon">
                                ${''}
                                <i class="${isFavorite ? 'fas' : 'far'} fa-heart user-favorite" data-user-id="${userId}" title="${isFavorite ? 'Remove Favorite' : 'Add Favorite'}"></i>
                            </div>
                        </div>
                        <div class="location-container">
                            <div class="location" title="${locationFullEscaped}">
                                <i class="fas fa-map-marker-alt"></i> ${locationDisplayEscaped}
                            </div>
                            ${level ? `
                            <div class="badge-container">
                                <span class="user-level">Lv${level}</span>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
            fragment.appendChild(userCard);
        });
        $gridContainer.append(fragment);
    };

    SUD.canInteractByTier = function(targetTierLevel) {
        if (typeof sud_config === 'undefined' || typeof sud_config.current_user_tier_level === 'undefined') {
            console.warn("SUD Config or current user tier level missing for interaction check.");
            return false; 
        }
        const currentUserLevel = parseInt(sud_config.current_user_tier_level);
        const targetLevel = parseInt(targetTierLevel);

        if (isNaN(currentUserLevel) || isNaN(targetLevel)) {
            console.warn("Invalid tier levels for comparison:", currentUserLevel, targetLevel);
            return false;
        }
        return (currentUserLevel >= targetLevel || targetLevel === 0);
    };

    SUD.showUpgradePrompt = function(context, data = {}) {
        const $modal = $('#upgrade-prompt-modal'); 
        if (!$modal.length) {
            return;
        }

        // Reset modal to original state before showing upgrade prompt
        SUD.resetUpgradeModal();

        let title = "Upgrade Required";
        let message = "Upgrade your membership to unlock this premium feature.";
        let iconClass = "fas fa-lock"; 

        switch (context) {
            case 'general':
                title = "Go Premium!";
                message = "Unlock exclusive features like advanced search, unlimited messaging, and seeing who likes you. Upgrade your experience now!";
                iconClass = "fas fa-rocket"; 
                break;
            case 'interact_tier':
                title = "Interaction Restricted";
                message = "Please upgrade your membership to a higher tier to interact (message, interact, etc.) with members at this level.";
                break;
            case 'feature_locked':
                const feature = data.featureName || 'this feature';
                title = `${feature} Locked`;
                message = `Upgrade your membership to access ${feature}.`;
                break;

            case 'chat_scope_state':
                    title = "Unlock Worldwide Chat";
                    message = "Upgrade to Premium to message members outside your current state.";
                    iconClass = "fas fa-globe-americas";
                    break;
                case 'chat_scope_limited':
                    title = "Unlock Unlimited Chat";
                    message = "Upgrade to a premium plan to send more messages or initiate conversations.";
                    iconClass = "fas fa-comments";
                    break;
        }
        $modal.find('.modal-icon i').removeClass().addClass(iconClass); 
        $modal.find('#upgrade-prompt-title').text(title); 
        $modal.find('#upgrade-prompt-message').text(message); 
        $modal.addClass('show');
    };

    SUD.showNotMatchedPrompt = function() {
        const $modal = $('#upgrade-prompt-modal'); 
        if (!$modal.length) {
            SUD.showToast('info', 'Not Matched', 'You can only message users you\'ve matched with. Keep swiping to find more matches!');
            return;
        }

        const title = "Match Required";
        const message = "You can only message users you've matched with. Keep swiping to find more connections!";
        const iconClass = "fas fa-heart";

        $modal.find('.modal-icon i').removeClass().addClass(iconClass); 
        $modal.find('#upgrade-prompt-title').text(title); 
        $modal.find('#upgrade-prompt-message').text(message); 
        
        // Update the upgrade button to say "Keep Swiping" and redirect to swipe page
        const $upgradeBtn = $modal.find('.btn-upgrade-action, #footer-upgrade-link');
        if ($upgradeBtn.length) {
            $upgradeBtn.text('Keep Swiping')
                      .removeClass('btn-primary')
                      .addClass('btn-primary')
                      .off('click.upgrade click.keepSwiping')
                      .on('click.keepSwiping', function(e) {
                          e.preventDefault();
                          $modal.removeClass('show');
                          window.location.href = sud_config_base.sud_url + '/pages/swipe';
                      });
        }

        // Update the "Maybe Later" button to say "Close"
        const $laterBtn = $modal.find('.close-modal-btn');
        if ($laterBtn.length) {
            $laterBtn.text('Close');
        }

        $modal.addClass('show');
    };

    // Function to reset the upgrade modal to its original state
    SUD.resetUpgradeModal = function() {
        const $modal = $('#upgrade-prompt-modal');
        if (!$modal.length) return;

        // Reset button text and functionality
        const $upgradeBtn = $modal.find('.btn-upgrade-action, #footer-upgrade-link');
        if ($upgradeBtn.length) {
            $upgradeBtn.text('Upgrade Now')
                      .off('click.keepSwiping')
                      .attr('href', sud_config_base.sud_url + '/pages/premium');
        }

        // Reset "Maybe Later" button
        const $laterBtn = $modal.find('.close-modal-btn');
        if ($laterBtn.length) {
            $laterBtn.text('Maybe Later');
        }

        // Reset icon
        $modal.find('.modal-icon i').removeClass().addClass('fas fa-crown');
    };

    SUD.initGeneralPremiumPrompt = function() {
        if (typeof sud_config === 'undefined' || sud_config.current_user_plan !== 'free') {
            return; 
        }
        if (!sessionStorage.getItem('sud_premium_prompt_shown')) {
            setTimeout(() => {
                SUD.showUpgradePrompt('general');
                sessionStorage.setItem('sud_premium_prompt_shown', 'true');
            }, 7000); 
        }
    };

    $(document).off('click.openMessageModal').on('click.openMessageModal', '.open-message-modal', function(e) {
        e.preventDefault(); e.stopPropagation(); 
        const $button = $(this);
        const userId = $button.data('user-id');
        const userName = $button.data('user-name') || 'this user';
        const targetTierLevel = $button.data('target-tier-level'); 

        if (typeof SUD.canInteractByTier === 'function' && !SUD.canInteractByTier(targetTierLevel)) {
            SUD.showUpgradePrompt('interact_tier'); 
            return; 
        }

        const $modal = $('#message-modal'); 
        if ($modal.length) {
            $modal.find('#message-recipient-id').val(userId); 
            $modal.find('#message-recipient-name').text(userName); 
            $modal.find('#message-text').val(''); 
            $modal.find('.modal-error').hide().text(''); 
            $modal.addClass('show');

            setTimeout(() => $modal.find('#message-text').focus(), 100);
        } else {
            console.error("Message modal (#message-modal) not found!");
            if (confirm(`Messaging this user requires an upgrade or is restricted. Go to premium page?`)) { 
                if(sud_config && sud_config.sud_url) {
                    window.location.href = sud_config.sud_url + '/pages/premium';
                }
            }
        }
    });

    $(document).on('click.profileMessageLink', '.profile-message-link', function(e) {
        if ($(this).hasClass('disabled') || $(this).attr('aria-disabled') === 'true') {
            e.preventDefault(); 
            SUD.showToast('info', 'Blocked', 'You cannot message a user you have blocked.');
            return;
        }

        const $link = $(this);
        const targetUserId = $link.data('target-user-id') || (function() {
            // Extract user ID from URL if not in data attribute
            const urlMatch = $link.attr('href').match(/[?&]user=(\d+)/);
            return urlMatch ? parseInt(urlMatch[1]) : null;
        })();
        
        if (!targetUserId) {
            e.preventDefault();
            SUD.showToast('error', 'Error', 'Unable to determine target user.');
            return;
        }

        e.preventDefault(); // Always prevent default, handle with AJAX

        // Check if users are matched
        $.ajax({
            url: sud_config_base.sud_url + '/ajax/check-match-status.php',
            type: 'POST',
            data: {
                target_user_id: targetUserId
            },
            success: function(response) {
                if (response.success && response.data.are_matched) {
                    // Users are matched, allow messaging
                    window.location.href = $link.attr('href');
                } else {
                    // Users are not matched, show "keep swiping" prompt
                    SUD.showNotMatchedPrompt();
                }
            },
            error: function() {
                SUD.showToast('error', 'Error', 'Unable to check match status. Please try again.');
            }
        });
    });

    SUD.initBillingToggle = function() {
        const $toggle = $('#billing-toggle');
        const $monthlyLabel = $('.billing-toggle .monthly');
        const $annuallyLabel = $('.billing-toggle .annually');
        const $allMonthlyPrices = $('.premium-plans .monthly-price');
        const $allAnnualPrices = $('.premium-plans .annual-price');

        if (!$toggle.length) { return; }

        function updatePriceDisplay(isAnnual) {
            if (isAnnual) {
                $monthlyLabel.removeClass('active');
                $annuallyLabel.addClass('active');
                $allMonthlyPrices.stop(true, true).fadeOut(200, function() {
                    $allAnnualPrices.stop(true, true).fadeIn(200);
                });
            } else {
                $annuallyLabel.removeClass('active');
                $monthlyLabel.addClass('active');
                $allAnnualPrices.stop(true, true).fadeOut(200, function() {
                    $allMonthlyPrices.stop(true, true).fadeIn(200);
                });
            }
        }

        updatePriceDisplay($toggle.is(':checked'));

        $toggle.on('change', function() {
            updatePriceDisplay($(this).is(':checked'));
        });

    };

    SUD.showSuccessAnimationAndRedirect = function(title, message, redirectUrl = null, delay = 4000) {
        const popup = document.getElementById('sud-payment-success-popup');
        const container = document.getElementById('sud-payment-lottie-container');
        const titleEl = document.getElementById('sud-payment-success-title');
        const messageEl = document.getElementById('sud-payment-success-message');
        const redirectingEl = document.getElementById('sud-payment-success-redirecting');

        if (!popup || !container || !titleEl || !messageEl || !redirectingEl) {
            console.error("Success popup elements not found. Reloading directly.");
            setTimeout(() => { redirectUrl ? window.location.href = redirectUrl : window.location.reload(); }, 500);
            return;
        }
        titleEl.textContent = title;
        messageEl.textContent = message;
        redirectingEl.style.display = 'block'; 

        if (typeof lottie !== 'undefined' && typeof sud_config !== 'undefined' && sud_config.urls && sud_config.urls.assets_path) {
            if (SUD.paymentSuccessAnim) { 
                SUD.paymentSuccessAnim.destroy();
                SUD.paymentSuccessAnim = null;
            }
            container.innerHTML = ''; 
            try {
                const animationPath = `${sud_config.urls.assets_path}/animations/payment-animation.json`;
                SUD.paymentSuccessAnim = lottie.loadAnimation({
                    container: container,
                    renderer: 'svg', loop: false, autoplay: false, path: animationPath
                });
                const doRedirect = () => { 
                    redirectUrl ? window.location.href = redirectUrl : window.location.reload();
                };
                popup.style.display = 'flex';
                setTimeout(() => {
                    popup.classList.add('show');
                    SUD.paymentSuccessAnim.play();
                }, 10);

                let redirected = false;
                SUD.paymentSuccessAnim.addEventListener('complete', () => {
                    if (!redirected) { setTimeout(doRedirect, 500); redirected = true; } 
                });
                setTimeout(() => { 
                     if (!redirected) { doRedirect(); redirected = true; }
                }, delay);

            } catch(e) {
                console.error("Lottie animation failed to load:", e);
                popup.style.display = 'flex';
                setTimeout(() => { popup.classList.add('show'); }, 10);
                setTimeout(() => { redirectUrl ? window.location.href = redirectUrl : window.location.reload(); }, Math.max(2000, delay));
            }
        } else {
            console.warn("Lottie library or config not available. Showing simple success message and redirecting.");
            popup.style.display = 'flex';
            setTimeout(() => { popup.classList.add('show'); }, 10);
            setTimeout(() => { redirectUrl ? window.location.href = redirectUrl : window.location.reload(); }, Math.max(2000, delay));
        }
    };

    function showDynamicTooltip(targetElement) {
        clearTimeout(hideTooltipTimeout);
        removeDynamicTooltip();

        const title = $(targetElement).attr('data-original-title'); 
        if (!title || title.trim() === '') {
            return; 
        }
        dynamicTooltipElement = $('<div></div>')
            .addClass('sud-dynamic-tooltip')
            .text(title)
            .appendTo('body'); 

        positionDynamicTooltip(targetElement, dynamicTooltipElement);
        requestAnimationFrame(() => {
             if (dynamicTooltipElement) { 
                dynamicTooltipElement.css('opacity', 1);
             }
        });
    }

    SUD.initDashboardFilterModal = function() {
        const $filterButton = $('#dashboard-filter-btn');
        const $filterModal = $('#dashboard-filter-modal');
    
        if ($filterButton.length && $filterModal.length) {
            $filterButton.off('click.dashboardFilter').on('click.dashboardFilter', function() {
                $filterModal.addClass('show');
                if (typeof sud_config !== 'undefined' && !sud_config.can_use_advanced_filters) {
                   $filterModal.find('.locked-filter select, .locked-filter input[type="checkbox"]')
                       .prop('disabled', true);
                }
            });
        } else {
            //console.warn("Dashboard filter button or modal not found.");
        }

        $('#apply-dashboard-filters').off('click.applyDashboardFilter').on('click.applyDashboardFilter', function() {
            const $form = $('#dashboard-filter-form');
            const $grid = $('#user-grid-dashboard'); 
            const $modal = $('#dashboard-filter-modal');
            const $button = $(this);

            if (!$form.length || !$grid.length) {
                console.error("Dashboard filter form or user grid not found for applying filters.");
                return;
            }

            const searchCriteria = {
                gender: $form.find('#filter-gender').val() || '',
                min_age: parseInt($form.find('#filter-min-age').val()) || 18,
                max_age: parseInt($form.find('#filter-max-age').val()) || 70,
                location: $form.find('#filter-location').val().trim() || '',

                ethnicity: (sud_config?.can_use_advanced_filters && $form.find('#filter-ethnicity').val()) || '',
                verified_only: (sud_config?.can_use_advanced_filters && $form.find('input[name="verified_only"]').is(':checked')) ? '1' : '0', 
                online_only: (sud_config?.can_use_advanced_filters && $form.find('input[name="online_only"]').is(':checked')) ? '1' : '0'   
            };

            if (searchCriteria.min_age > searchCriteria.max_age) {
                [searchCriteria.min_age, searchCriteria.max_age] = [searchCriteria.max_age, searchCriteria.min_age];
                $('#filter-min-age').val(searchCriteria.min_age);
                $('#filter-max-age').val(searchCriteria.max_age);
            }
            SUD.currentPageByFilter['search'] = 1; 
            $modal.removeClass('show'); 
            SUD.fetchAndDisplayMembers('search', 1, true, $button, searchCriteria);
        });
    }

    function positionDynamicTooltip(target, tooltip) {
        if (!target || !tooltip || !tooltip.length) return;
        const targetRect = target.getBoundingClientRect();
        const tooltipWidth = tooltip.outerWidth();
        const tooltipHeight = tooltip.outerHeight();
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const offset = 8; 

        let desiredTop = targetRect.top - tooltipHeight - offset;
        let desiredLeft = targetRect.left + (targetRect.width / 2) - (tooltipWidth / 2);

        if (desiredTop < offset) {
            desiredTop = targetRect.bottom + offset;
        }
        if (desiredTop + tooltipHeight > viewportHeight - offset) {
            desiredTop = targetRect.top - tooltipHeight - offset;
        }
        if (desiredLeft < offset) {
            desiredLeft = offset;
        }
        if (desiredLeft + tooltipWidth > viewportWidth - offset) {
            desiredLeft = viewportWidth - tooltipWidth - offset;
        }
        tooltip.css({
            top: `${desiredTop}px`,
            left: `${desiredLeft}px`
        });
    }

    function hideDynamicTooltip() {
        clearTimeout(hideTooltipTimeout); 
        hideTooltipTimeout = setTimeout(() => {
            removeDynamicTooltip();
        }, 100); 
    }

    function removeDynamicTooltip() {
        if (dynamicTooltipElement) {
            dynamicTooltipElement.remove();
            dynamicTooltipElement = null;
        }
    }

    $(document).on('mouseenter', '[title]', function() {
        const $element = $(this);
        const originalTitle = $element.attr('title');
        if (!originalTitle || originalTitle.trim() === '' || $element.attr('data-original-title')) {
            return;
        }
        $element.attr('data-original-title', originalTitle).removeAttr('title');
        showDynamicTooltip(this);
    });

    $(document).on('mouseleave', '[data-original-title]', function() { 
        const $element = $(this);
        const originalTitle = $element.attr('data-original-title');
        if (typeof originalTitle !== 'undefined') {
            $element.attr('title', originalTitle).removeAttr('data-original-title');
        }
        hideDynamicTooltip();
    });

    $(document).on('mousedown', '[data-original-title]', function() { 
        removeDynamicTooltip(); 
        const $element = $(this);
        const originalTitle = $element.attr('data-original-title');
        if (typeof originalTitle !== 'undefined') {
            $element.attr('title', originalTitle).removeAttr('data-original-title');
        }
    });
    $(window).on('scroll resize', removeDynamicTooltip);

    // Handle lock icon clicks on pseudo-elements using coordinate detection
    $(document).on('click', '.locked-user-card', function(e) {
        const card = $(this);
        const rect = this.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const clickY = e.clientY - rect.top;
        
        // Check if click is in the lock icon area (right side, middle)
        const cardWidth = rect.width;
        const cardHeight = rect.height;
        const lockAreaLeft = cardWidth - 50; // 50px from right edge
        const lockAreaTop = cardHeight/2 - 20; // 20px above/below center
        const lockAreaBottom = cardHeight/2 + 20;
        
        if (clickX >= lockAreaLeft && clickY >= lockAreaTop && clickY <= lockAreaBottom) {
            e.preventDefault();
            e.stopPropagation();
            const upgradeUrl = card.data('upgrade-url');
            if (upgradeUrl) {
                window.location.href = upgradeUrl;
            }
        }
    });
})(jQuery);