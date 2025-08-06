var SUD = SUD || {};

SUD.SwipePage = (function($) {
    'use_strict';

    let candidates = [];
    let currentIndex = -1;
    let remainingSwipes = 0;
    let isLoading = false;
    let $swipeDeck, $swipeControls, $passBtn, $likeBtn, $reverseBtn, $loadingMsg, $emptyMsg, $remainingSwipesDisplayContainer, $remainingSwipesDisplay;
    let $limitModal, $matchModal, $boostModal, $swipeUpModal, $oneSwipeLeftModal, $reverseUpgradeModal;
    let $skeletonDeck;
    let $skeletonControls, $skeletonRemainingSwipes;
    let hasMoreCandidatesToLoad = true;
    
    let currentCardElement = null;
    let hammerInstances = new Map();
    let swipedThisSessionUserIds = new Set();

    const MAX_VISIBLE_CARDS = 3;
    const CARD_OFFSET_Y = 8;
    const CARD_SCALE_DECREMENT = 0.04;
    const PRELOAD_BUFFER = 5;

    let sessionSwipeCounter = 0;
    const BOOST_PROMPT_THRESHOLD = 8;

    let lastSwipeAttempt = null;
    let swipeUpBalance = 0;

    let lastSwipedUser = null;

    function init() {
        
        $swipeDeck = $('#swipe-deck-area');
        $swipeControls = $('#swipe-controls');
        $passBtn = $('#swipe-pass-btn');
        $likeBtn = $('#swipe-like-btn');
        $reverseBtn = $('#swipe-reverse-btn');
        $loadingMsg = $('#swipe-deck-loading');
        $emptyMsg = $('#swipe-deck-empty');
        $remainingSwipesDisplayContainer = $('#remaining-swipes-display-container');
        $remainingSwipesDisplay = $('#remaining-swipes-count');
        $limitModal = $('#swipe-limit-modal');
        $matchModal = $('#match-notification-modal');
        $boostModal = $('#boost-modal');
        $swipeUpModal = $('#swipe-up-modal');
        $oneSwipeLeftModal = $('#one-swipe-left-modal');
        $reverseUpgradeModal = $('#reverse-upgrade-modal');
        $skeletonDeck = $('#swipe-deck-skeleton-area');
        $skeletonControls = $('#swipe-controls-skeleton');
        $skeletonRemainingSwipes = $('#remaining-swipes-skeleton');

        if (typeof sud_swipe_page_config === 'undefined') {
            console.error("Swipe page config not found.");
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.text("Error loading configuration.").show();
            hideAllSkeletons();
            return;
        }

        if (typeof Hammer === 'undefined') {
            console.error("Hammer.js is not loaded. Touch swipe will not work.");
        }

        swipeUpBalance = sud_swipe_page_config.swipe_up_balance || 0;
        updateSwipeUpButtonUI();
        updateReverseButtonState();
        
        // Add boost quick access button functionality
        $('#boost-quick-access-btn').on('click', function() {
            showBoostModal();
        });

        remainingSwipes = (typeof sud_swipe_page_config.remaining_swipes === 'number' && !isNaN(sud_swipe_page_config.remaining_swipes)) 
                          ? sud_swipe_page_config.remaining_swipes 
                          : 0;
        updateRemainingSwipesDisplay();
        updateSwipeButtonStates();

        if (remainingSwipes <= 0 && !sud_swipe_page_config.is_premium) {
            showSwipeLimitWall();
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
            hideAllSkeletons();
            return;
        }

        setupEventHandlers();

        candidates = sud_swipe_page_config.initial_candidates || [];
        // Ensure candidates is always an array
        if (!Array.isArray(candidates)) {
            candidates = [];
        }
        if (candidates.length > 0) {
            currentIndex = 0;
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
            showAllSkeletons();
            renderDeck();
        } else {
            showAllSkeletons();
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
            loadMoreCandidates(true);
        }
    }

    function setupEventHandlers() {
        if ($passBtn && $passBtn.length) $passBtn.on('click', function() { triggerSwipeAction('pass'); });
        if ($likeBtn && $likeBtn.length) $likeBtn.on('click', function() { triggerSwipeAction('like'); });
        if ($reverseBtn && $reverseBtn.length) $reverseBtn.on('click', function(e) { 
            e.preventDefault();
            e.stopPropagation();
            
            // Check if user has Silver tier or higher
            if (sud_swipe_page_config.user_tier_level >= 1) {
                handleReverseSwipe();
            } else {
                showReverseUpgradeModal();
            }
        });
        
        const $boostBtn = $('#swipe-boost-btn');
        if ($boostBtn.length) {
            $boostBtn.on('click', function() {
                if (swipeUpBalance > 0) {
                    // User has swipe up credit, perform the swipe
                    triggerSwipeAction('swipe_up');
                } else {
                    // No swipe up credit, show purchase modal
                    showSwipeUpModal();
                }
            });
        }

        $(document).on('keydown.swipe', function(e) {
            if (($limitModal && $limitModal.hasClass('show')) || ($matchModal && $matchModal.hasClass('show')) || isLoading) return;
            if (e.key === 'ArrowLeft') triggerSwipeAction('pass');
            if (e.key === 'ArrowRight') triggerSwipeAction('like');
            if (e.key === 'ArrowUp') {
                // Use same logic as boost button - check balance first
                if (swipeUpBalance > 0) {
                    triggerSwipeAction('swipe_up');
                } else {
                    showSwipeUpModal();
                }
            }
            if (e.key === 'Backspace' && $reverseBtn && $reverseBtn.length && !$reverseBtn.prop('disabled')) {
                // Use same logic as reverse button - check tier first
                if (sud_swipe_page_config.user_tier_level >= 1) {
                    handleReverseSwipe();
                } else {
                    showReverseUpgradeModal();
                }
            }
        });

        $('.close-modal, .close-modal-btn').on('click', function() {
            const $modal = $(this).closest('.modal, .payment-modal');
            // Don't allow closing the claim modal
            if (!$modal.hasClass('claim-modal-no-dismiss')) {
                $modal.removeClass('show');
            }
        });
        
        if ($matchModal && $matchModal.length) {
            $('#match-keep-swiping-btn').on('click', function() {
                $matchModal.removeClass('show');
            });
        }

        if ($oneSwipeLeftModal && $oneSwipeLeftModal.length) {
            $oneSwipeLeftModal.find('.continue-last-swipe-btn').on('click', function() {
                $oneSwipeLeftModal.removeClass('show');
        
                if (!sud_swipe_page_config.is_premium) {
                    remainingSwipes--;
                    updateRemainingSwipesDisplay();
                }
                performSwipeAction(lastSwipeAttempt.type, lastSwipeAttempt.cardElement);
            });
        
            $oneSwipeLeftModal.find('.upgrade-from-warning-btn').on('click', function(e) {
                $oneSwipeLeftModal.removeClass('show');
            });
        }

        if (sud_swipe_page_config.is_premium && !sessionStorage.getItem('boost_modal_shown')) {
            setTimeout(() => {
                showBoostModal();
                sessionStorage.setItem('boost_modal_shown', 'true');
            }, 5000);
        }

        $('#claim-free-swipe-up-btn').on('click', function() {
            const $button = $(this);
            const $modal = $('#claim-free-swipe-up-modal');
            
            $button.prop('disabled', true).text('Claiming...');

            $.ajax({
                url: sud_swipe_page_config.ajax_url.replace('process-swipe.php', 'claim-free-swipe-up.php'),
                type: 'POST',
                dataType: 'json',
                data: {
                    _ajax_nonce: sud_swipe_page_config.swipe_nonce
                },
                success: function(response) {
                    if (response.success) {
                        SUD.showToast('success', 'Success!', response.message);
                        
                        if (response.data && typeof response.data.new_swipe_up_balance !== 'undefined') {
                            swipeUpBalance = response.data.new_swipe_up_balance;
                            updateSwipeUpButtonUI();
                        } else if (typeof response.new_swipe_up_balance !== 'undefined') {
                            swipeUpBalance = response.new_swipe_up_balance;
                            updateSwipeUpButtonUI();
                        }
                        
                        $modal.removeClass('show');

                    } else {
                        const errorMessage = (response.data && response.data.message) ? response.data.message : 'An error occurred.';
                        SUD.showToast('error', 'Claim Failed', errorMessage);
                        $button.prop('disabled', false).text('🎁 Claim My Free Swipe Up');

                        if (errorMessage.includes('already claimed')) {
                            $modal.removeClass('show');
                        }
                    }
                },
                error: function() {
                    SUD.showToast('error', 'Error', 'Could not claim your Swipe Up. Please try again later.');
                    $button.prop('disabled', false).text('🎁 Claim My Free Swipe Up');
                }
            });
        });
    }

    function updateRemainingSwipesDisplay() {
        if ($remainingSwipesDisplay && $remainingSwipesDisplay.length) {
            $remainingSwipesDisplay.text(remainingSwipes);
        } else {
        }
        
        if (sud_swipe_page_config.is_premium && $remainingSwipesDisplayContainer && $remainingSwipesDisplayContainer.length) {
            $remainingSwipesDisplayContainer.hide();
        } else if ($remainingSwipesDisplayContainer && $remainingSwipesDisplayContainer.length) {
            $remainingSwipesDisplayContainer.show();
        }

        updateSwipeButtonStates();
    }

    function updateReverseButtonState() {
        if ($reverseBtn && $reverseBtn.length) {
            if (sud_swipe_page_config.user_tier_level >= 1) {
                // Silver+ users: enable/disable based on whether there's a swipe to reverse
                if (lastSwipedUser) {
                    $reverseBtn.prop('disabled', false);
                    $reverseBtn.attr('title', `Reverse last ${lastSwipedUser.type} on ${lastSwipedUser.user.name}`);
                } else {
                    $reverseBtn.prop('disabled', true);
                    $reverseBtn.attr('title', 'No swipe to reverse');
                }
            } else {
                // Non-premium users: always enabled to show upgrade modal
                $reverseBtn.prop('disabled', false);
                $reverseBtn.attr('title', 'Reverse Last Swipe (Premium Feature)');
            }
        }
    }

    function updateSwipeButtonStates() {
        const hasRegularSwipesLeft = remainingSwipes > 0 || sud_swipe_page_config.is_premium;
        
        if ($passBtn && $passBtn.length) {
            if (hasRegularSwipesLeft) {
                $passBtn.prop('disabled', false).removeClass('disabled');
                $passBtn.attr('title', 'Pass on this profile');
            } else {
                $passBtn.prop('disabled', true).addClass('disabled');
                $passBtn.attr('title', 'No swipes remaining - upgrade to continue');
            }
        }
        
        if ($likeBtn && $likeBtn.length) {
            if (hasRegularSwipesLeft) {
                $likeBtn.prop('disabled', false).removeClass('disabled');
                $likeBtn.attr('title', 'Like this profile');
            } else {
                $likeBtn.prop('disabled', true).addClass('disabled');
                $likeBtn.attr('title', 'No swipes remaining - upgrade to continue');
            }
        }
        
        // Swipe up button is handled by updateSwipeUpButtonUI()
        updateReverseButtonState();
    }

    function handleReverseSwipe() {
        if (!lastSwipedUser) {
            if (typeof SUD !== 'undefined' && SUD.showToast) {
                SUD.showToast('info', 'No Swipe to Reverse', 'There is no recent swipe to reverse.');
            }
            return;
        }
        
        if (isLoading) {
            return;
        }

        isLoading = true;

        $.ajax({
            url: sud_swipe_page_config.ajax_url.replace('process-swipe.php', 'reverse-swipe.php'),
            type: 'POST',
            data: {
                action: 'sud_reverse_swipe',
                user_id: lastSwipedUser.user.id,
                swipe_type: lastSwipedUser.type,
                _ajax_nonce: sud_swipe_page_config.swipe_nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    swipedThisSessionUserIds.delete(lastSwipedUser.user.id);
                    
                    createReverseAnimation(lastSwipedUser.user, lastSwipedUser.type);
                    
                    if (!sud_swipe_page_config.is_premium && response.data && typeof response.data.remaining_swipes !== 'undefined') {
                        remainingSwipes = response.data.remaining_swipes;
                        updateRemainingSwipesDisplay();
                    }
                    
                    if (typeof SUD !== 'undefined' && SUD.showToast) {
                        SUD.showToast('success', 'Reversed!', response.message || 'Your last swipe has been reversed.');
                    }
                } else {
                    if (typeof SUD !== 'undefined' && SUD.showToast) {
                        SUD.showToast('error', 'Error', (response.data && response.data.message) || 'Could not reverse your swipe.');
                    }
                }
                isLoading = false;
            },
            error: function(xhr, status, error) {
                console.error('Reverse AJAX error:', status, error, xhr.responseText);
                if (typeof SUD !== 'undefined' && SUD.showToast) {
                    SUD.showToast('error', 'Network Error', 'Could not connect to server.');
                }
                isLoading = false;
            }
        });
    }

    function createCardHtml(user) {
        const defaultPic = (sud_swipe_page_config.img_path_url || '') + '/default-profile.jpg';
        const profilePic = user.profile_pic || defaultPic;
        
        let nameAndBadgesHtml = `<span class="swipe-card-name">${escapeHtml(user.name || 'User')}, </span>`;
        if (user.age) {
            nameAndBadgesHtml += `<span class="user-age">${escapeHtml(user.age.toString())}</span>`;
        }
        nameAndBadgesHtml += `<span class="swipe-card-inline-badges">`;
        if (user.is_online) {
            nameAndBadgesHtml += `<span class="online-status" title="Online Now"><span class="status-dot"></span></span>`;
        }
        if (user.is_verified) {
            nameAndBadgesHtml += `<span class="verified-badge"><img class="verify-icon" src="${sud_swipe_page_config.img_path_url || ''}/verified-profile-badge.png" alt="verified"></span>`;
        }
        if (user.premium_badge_html_small) {
            nameAndBadgesHtml += user.premium_badge_html_small;
        }
        // Check for active boost with proper boolean handling
        if (user.has_active_boost === true || user.has_active_boost === 'true' || user.has_active_boost === 1 || user.has_active_boost === '1') {
            const boostType = user.boost_type || 'mini';
            const boostName = user.boost_name || 'Profile Boost';
            nameAndBadgesHtml += `<span class="boost-badge boost-${escapeHtml(boostType)}" title="${escapeHtml(boostName)} Active"><i class="fas fa-rocket"></i></span>`;
        }

        nameAndBadgesHtml += `</span>`;

        let html = `<div class="swipe-card" data-user-id="${user.id}" style="display:none;">
                        <div class="swipe-card-image" style="background-image: url('${escapeHtml(profilePic)}');"></div>
                        <div class="swipe-card-info">
                            <div class="swipe-card-name-line">
                                ${nameAndBadgesHtml}
                            </div>`;
        if (user.location_formatted) {
            html += `<p class="swipe-card-location"><i class="fas fa-map-marker-alt"></i> ${escapeHtml(user.location_formatted)}</p>`;
        }
        
        // Add a view profile button
        html += `<a href="${sud_swipe_page_config.sud_url || ''}/pages/profile?id=${user.id}" class="view-profile-btn-swipe">
                    <i class="fas fa-user"></i> View Profile
                </a>`;
                
        html += `</div></div>`;
        return html;
    }
    
    function destroyHammerInstance(element) {
        if (element && hammerInstances.has(element)) {
            hammerInstances.get(element).destroy();
            hammerInstances.delete(element);
        }
    }

    function applyStackStylingAndGestures() {
        const $cardsInDeck = $swipeDeck.find('.swipe-card:not(.dismissing-left):not(.dismissing-right):not(.dismissing-up)');
        currentCardElement = null;
    
        $cardsInDeck.each(function(indexInDom, cardElement) {
            destroyHammerInstance(cardElement);
            const $card = $(cardElement);
    
            if (indexInDom < MAX_VISIBLE_CARDS) {
                $card.css({
                    'transform': `translateY(${indexInDom * CARD_OFFSET_Y}px) scale(${1 - indexInDom * CARD_SCALE_DECREMENT}) translateZ(0)`,
                    'z-index': MAX_VISIBLE_CARDS - indexInDom,
                    'opacity': (indexInDom === 0) ? 1 : (1 - (indexInDom * 0.3)),
                    'display': 'flex'
                }).removeClass('card-hidden card-beyond-stack');
    
                if (indexInDom === 0) {
                    currentCardElement = cardElement;
                }
            } else {
                $card.css({
                    'transform': `translateY(${MAX_VISIBLE_CARDS * CARD_OFFSET_Y}px) scale(${1 - MAX_VISIBLE_CARDS * CARD_SCALE_DECREMENT}) translateZ(0)`,
                    'opacity': 0,
                    'z-index': 0,
                    'display': 'none'
                }).addClass('card-hidden card-beyond-stack');
            }
        });
    
        const hasRegularSwipesLeft = remainingSwipes > 0 || sud_swipe_page_config.is_premium;
        const hasSwipeUpBalance = swipeUpBalance > 0;
        const canPerformAnySwipe = hasRegularSwipesLeft || hasSwipeUpBalance;
        const $topCardElement = $cardsInDeck.first();
    
        if ($topCardElement.length > 0 && canPerformAnySwipe) {
            hideAllSkeletons();
            $swipeDeck.show();
            $swipeControls.show();
    
            if (!currentCardElement) currentCardElement = $topCardElement.get(0);
            
            if (currentCardElement && typeof Hammer !== 'undefined') {
                if (!hammerInstances.has(currentCardElement)) {
                    initCardGestures(currentCardElement);
                }
            }
            
            if (!sud_swipe_page_config.is_premium && $remainingSwipesDisplayContainer && !$remainingSwipesDisplayContainer.is(':visible')) {
                $remainingSwipesDisplayContainer.show();
            }
        } else if ($topCardElement.length > 0 && !canPerformAnySwipe) {
            // Show cards but disable regular swipe controls (user can still see cards but can't swipe)
            hideAllSkeletons();
            $swipeDeck.show();
            $swipeControls.show(); // Keep controls visible but they'll be handled by individual button logic
            
            if (!sud_swipe_page_config.is_premium && $remainingSwipesDisplayContainer && !$remainingSwipesDisplayContainer.is(':visible')) {
                $remainingSwipesDisplayContainer.show();
            }
        } else {
            if ($swipeControls && $swipeControls.is(':visible')) $swipeControls.hide();
            if ($remainingSwipesDisplayContainer && $remainingSwipesDisplayContainer.is(':visible')) $remainingSwipesDisplayContainer.hide();
            if (currentCardElement) destroyHammerInstance(currentCardElement);
            hideAllSkeletons();
        }
    
        if ($topCardElement.length === 0 && !isLoading) {
            if (remainingSwipes <= 0 && !sud_swipe_page_config.is_premium) {
                showSwipeLimitWall();
            } else {
                if ($emptyMsg && $emptyMsg.length && (!$limitModal || !$limitModal.hasClass('show'))) {
                    $emptyMsg.show();
                }
            }
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
            hideAllSkeletons();
        } else if ($topCardElement.length > 0) {
            if ($emptyMsg && $emptyMsg.length && (!$limitModal || !$limitModal.hasClass('show'))) {
                $emptyMsg.hide();
            }
        }
    }

    function renderDeck() {
        const existingCards = $swipeDeck.find('.swipe-card');
        const currentDeckSize = existingCards.length;
        
        if (currentDeckSize === 0 || currentIndex >= candidates.length) {
            return fullRenderDeck();
        }
        
        applyStackStylingAndGestures();
    }
    
    function fullRenderDeck() {
        $swipeDeck.find('.swipe-card').each((idx, el) => destroyHammerInstance(el));
        if(currentCardElement) destroyHammerInstance(currentCardElement);
        currentCardElement = null;
        $swipeDeck.empty(); 

        let cardsRenderedCount = 0;
        for (let i = 0; i < MAX_VISIBLE_CARDS; i++) {
            const candidateIdx = currentIndex + i;
            if (candidateIdx < candidates.length) {
                const user = candidates[candidateIdx];
                const cardHtml = createCardHtml(user);
                $swipeDeck.append(cardHtml);
                cardsRenderedCount++;
            } else {
                break;
            }
        }
        
        applyStackStylingAndGestures();
        updateReverseButtonState();
        updateSwipeButtonStates();

        if (cardsRenderedCount > 0) {
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
            const numCandidatesInMemory = candidates.length - currentIndex;
            if (numCandidatesInMemory < MAX_VISIBLE_CARDS + PRELOAD_BUFFER && !isLoading && currentIndex < candidates.length) {
                loadMoreCandidates(false);
            }
        } else if (!isLoading) {
            hideAllSkeletons();
            if (remainingSwipes <= 0 && !sud_swipe_page_config.is_premium) {
                 showSwipeLimitWall();
            } else {
                if ($emptyMsg && $emptyMsg.length) $emptyMsg.show();
                if ($swipeControls && $swipeControls.length) $swipeControls.hide(); 
                if ($remainingSwipesDisplayContainer && $remainingSwipesDisplayContainer.length) $remainingSwipesDisplayContainer.hide();
            }
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
        }
    }


    function initCardGestures(cardEl) {
        destroyHammerInstance(cardEl); 

        // Add click event for the view profile button to prevent swipe action
        $(cardEl).find('.view-profile-btn-swipe').on('click', function(e) {
            // Stop event propagation to prevent triggering swipe
            e.stopPropagation();
        });

        const hammer = new Hammer(cardEl);
        hammer.get('pan').set({ 
            direction: Hammer.DIRECTION_ALL, 
            threshold: 5
        });

        hammerInstances.set(cardEl, hammer);

        hammer.on('panstart', function(e) {
            if (isLoading) return;
            
            if ($(e.target).closest('.view-profile-btn-swipe').length) {
                hammer.stop(true);
                return;
            }
            
            $(cardEl).css({
                'transition': 'none',
                'will-change': 'transform'
            }); 
        });

        hammer.on('pan', function(e) {
            if (isLoading) return;
            
            if ($(e.target).closest('.view-profile-btn-swipe').length) {
                return;
            }
            
            let posX = e.deltaX;
            let posY = e.deltaY;
            let rot = posX * 0.05; 
            
            if (posY < 0 && Math.abs(posY) > Math.abs(posX)) {
                cardEl.style.transform = `translate(${posX * 0.3}px, ${posY}px) rotate(${rot * 0.5}deg) scale(${Math.max(0.9, 1 + posY / 1000)})`;
                $(cardEl).removeClass('swiping-left swiping-right').addClass('swiping-up');
            } else {
                cardEl.style.transform = `translate(${posX}px, 0px) rotate(${rot}deg)`;
                $(cardEl).removeClass('swiping-up');
                
                if (Math.abs(posX) > 30) {
                    if (posX > 0) {
                        $(cardEl).removeClass('swiping-left').addClass('swiping-right');
                    } else {
                        $(cardEl).removeClass('swiping-right').addClass('swiping-left');
                    }
                } else {
                    $(cardEl).removeClass('swiping-left swiping-right');
                }
            }

            const cardWidth = cardEl.offsetWidth;
            const panThresholdActivate = cardWidth * 0.1; 

            $passBtn.removeClass('drag-active-target');
            $likeBtn.removeClass('drag-active-target');

            if (Math.abs(e.deltaX) > panThresholdActivate && Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
                if (e.deltaX > 0) $likeBtn.addClass('drag-active-target');
                else $passBtn.addClass('drag-active-target');
            }
        });
    
        hammer.on('panend', function(e) {
            if (isLoading) return;
            
            if ($(e.target).closest('.view-profile-btn-swipe').length) {
                return;
            }
            
            $(cardEl).css('transition', 'transform 0.05s ease-out, opacity 0.05s ease-out');
            
            $passBtn.removeClass('drag-active-target');
            $likeBtn.removeClass('drag-active-target');
            $(cardEl).removeClass('swiping-up swiping-left swiping-right');

            const thresholdX = cardEl.offsetWidth * 0.20;
            const thresholdY = cardEl.offsetHeight * 0.20;

            if (e.deltaY < -thresholdY && Math.abs(e.deltaX) < Math.abs(e.deltaY)) {
                if (swipeUpBalance > 0) {
                    triggerSwipeAction('swipe_up', cardEl);
                } else {
                    showSwipeUpModal();
                    cardEl.style.transform = 'translate(0px, 0px) rotate(0deg)';
                    applyStackStylingAndGestures();
                }
            } else if (Math.abs(e.deltaX) > thresholdX || Math.abs(e.velocityX) > 0.6) {
                // Check if regular swipes are available
                if (remainingSwipes > 0 || sud_swipe_page_config.is_premium) {
                    triggerSwipeAction(e.deltaX > 0 ? 'like' : 'pass', cardEl);
                } else {
                    // No regular swipes left, reset card position
                    cardEl.style.transform = 'translate(0px, 0px) rotate(0deg)';
                    applyStackStylingAndGestures();
                    showSwipeLimitWall();
                }
            } else {
                cardEl.style.transform = 'translate(0px, 0px) rotate(0deg)'; 
                applyStackStylingAndGestures(); 
            }
        });
    }

    function triggerSwipeAction(type, cardElementBeingSwiped = null) {
        if (isLoading) return;
        if (currentIndex < 0 || currentIndex >= candidates.length) {
            if (candidates.length === 0 && !isLoading) loadMoreCandidates(true);
            return;
        }
    
        // Check if action is allowed
        if (type === 'like' || type === 'pass') {
            if (remainingSwipes <= 0 && !sud_swipe_page_config.is_premium) {
                showSwipeLimitWall();
                return;
            }
        }
    
        if (type !== 'swipe_up') {
            if (remainingSwipes === 1 && !sud_swipe_page_config.is_premium && !$oneSwipeLeftModal.hasClass('show')) {
                lastSwipeAttempt = { type: type, cardElement: cardElementBeingSwiped };
                $oneSwipeLeftModal.addClass('show');
                if (cardElementBeingSwiped) {
                    $(cardElementBeingSwiped).css('transition', 'transform 0.3s ease-out')
                                             .css('transform', 'translate(0px, 0px) rotate(0deg)');
                    applyStackStylingAndGestures();
                }
                return;
            }
        
            if (remainingSwipes <= 0 && !sud_swipe_page_config.is_premium) {
                showSwipeLimitWall();
                if (cardElementBeingSwiped) {
                    $(cardElementBeingSwiped).css('transition', 'transform 0.3s ease-out')
                                             .css('transform', 'translate(0px, 0px) rotate(0deg)');
                    applyStackStylingAndGestures();
                }
                return;
            }
    
            if (!sud_swipe_page_config.is_premium) {
                remainingSwipes--;
                updateRemainingSwipesDisplay();
            }
        }
        
        performSwipeAction(type, cardElementBeingSwiped);
    }

    function performSwipeAction(type, cardElementBeingSwiped = null, userIdOverride = null) {
        let $cardToAnimate = cardElementBeingSwiped ? $(cardElementBeingSwiped) : $(currentCardElement);
        let userId = userIdOverride;

        if (!userId) {
            if ($cardToAnimate.length) {
                userId = parseInt($cardToAnimate.data('user-id'), 10);
            } else {
                console.warn("performSwipeAction called with no card or user ID.");
                return;
            }
        }
        
        if (isLoading || isNaN(userId) || swipedThisSessionUserIds.has(userId)) {
            console.warn(`Swipe action blocked. isLoading: ${isLoading}, UserID: ${userId}`);
            return;
        }
    
        isLoading = true;
        swipedThisSessionUserIds.add(userId);
    
        const swipedUserIndex = candidates.findIndex(c => c.id === userId);
        if (swipedUserIndex === -1 && type !== 'pass' && type !== 'swipe_up') {
            console.error(`User with ID ${userId} not found in candidates list for a ${type} action.`);
            isLoading = false;
            return;
        }
        
        // For swipe_up from modal, we might not have the user in candidates list
        const swipedUser = swipedUserIndex !== -1 ? candidates[swipedUserIndex] : null;
        
        // If a card exists on the screen for this user, find it and animate it.
        const $cardOnScreen = $swipeDeck.find(`.swipe-card[data-user-id="${userId}"]`);
        if ($cardOnScreen.length) {
            if (type === 'like') {
                $cardOnScreen.addClass('dismissing-right');
            } else if (type === 'pass') {
                $cardOnScreen.addClass('dismissing-left');
            } else if (type === 'swipe_up') {
                $cardOnScreen.addClass('dismissing-up');
            }
        }
        
        if(swipedUser) {
            lastSwipedUser = {
                user: swipedUser,
                type: type,
                index: swipedUserIndex
            };
        }
        
        proceedToNextCard($cardOnScreen, userId);
        processSwipeAJAX(userId, type);
    }

    function proceedToNextCard($animatedCard, swipedUserId = null) {
        if ($animatedCard && $animatedCard.length) {
            destroyHammerInstance($animatedCard.get(0));
            setTimeout(function() {
                $animatedCard.remove();
            }, 500);
        }

        const userIdToRemove = swipedUserId || ($animatedCard.length ? parseInt($animatedCard.data('user-id'), 10) : null);
        if (userIdToRemove) {
            const removeIndex = candidates.findIndex(c => c.id === userIdToRemove);
            if (removeIndex > -1) {
                candidates.splice(removeIndex, 1);
            }
        }

        if (currentIndex >= candidates.length) {
            currentIndex = candidates.length - 1;
        }
        if (currentIndex < 0) currentIndex = 0;

        if (remainingSwipes <= 0 && !sud_swipe_page_config.is_premium) {
            showSwipeLimitWall();
            isLoading = false;
            return;
        }

        const visibleCards = $swipeDeck.find('.swipe-card').length;
        if (visibleCards < MAX_VISIBLE_CARDS && currentIndex < candidates.length) {
            const nextCardIndex = currentIndex + visibleCards;
            if (nextCardIndex < candidates.length) {
                const nextUser = candidates[nextCardIndex];
                if(nextUser) {
                    const cardHtml = createCardHtml(nextUser);
                    $swipeDeck.append(cardHtml);
                }
            }
        }

        const remainingCandidatesInArray = candidates.length - currentIndex;
        if (remainingCandidatesInArray < PRELOAD_BUFFER && !isLoading && hasMoreCandidatesToLoad) {
            loadMoreCandidates(false);
        }

        isLoading = false;
        renderDeck();
        updateReverseButtonState();
    }
    
    function processSwipeAJAX(swipedUserId, swipeType) {
        $.ajax({
            url: sud_swipe_page_config.ajax_url,
            type: 'POST',
            data: {
                action: 'sud_process_swipe',
                swiped_user_id: swipedUserId,
                swipe_type: swipeType,
                _ajax_nonce: sud_swipe_page_config.swipe_nonce
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    
                    // Check if swipe limit was reached
                    if (response.data.limit_reached && response.data.show_modal) {
                        // Show dynamic limit modal based on trial status
                        showDynamicLimitModal(response.data);
                        return;
                    }
                    
                    if (swipeType === 'like' || swipeType === 'pass') {
                        sessionSwipeCounter++;
                        checkAndTriggerBoostModal();
                        checkAndTriggerFreeSwipeUpPrompt(); // Check on every regular swipe
                    }

                    if (!sud_swipe_page_config.is_premium) {
                        remainingSwipes = response.data.remaining_swipes;
                        updateRemainingSwipesDisplay();
                    }

                    if (typeof response.data.new_swipe_up_balance !== 'undefined') {
                        swipeUpBalance = response.data.new_swipe_up_balance;
                        updateSwipeUpButtonUI();
                    }

                    if (response.data.is_match && response.data.match_user_details) {
                        showMatchNotification(response.data.match_user_details, (swipeType === 'swipe_up'));
                    }

                } else {
                    if (response.data) {
                    }
                    
                    let errorMsg = (response.data && response.data.message) ? response.data.message : 'Could not save last swipe.';
                    if (typeof SUD !== 'undefined' && SUD.showToast) {
                        SUD.showToast('error', 'Sync Error', errorMsg);
                    }
                }
            },
            error: function(xhr, status, error) {
                if (typeof SUD !== 'undefined' && SUD.showToast) {
                    SUD.showToast('error', 'Network Error', 'Could not save last swipe. Please check your connection.');
                }
            }
        });
    }

    function loadMoreCandidates(showLoadingIndicator = false) {
        if (isLoading || !hasMoreCandidatesToLoad) {
            if (!isLoading && $swipeDeck.find('.swipe-card').length === 0) {
                $emptyMsg.show();
                hideAllSkeletons();
                $swipeControls.hide();
            }
            return;
        }
        
        isLoading = true;
    
        if (showLoadingIndicator) {
            if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
            showAllSkeletons();
            if ($emptyMsg && $emptyMsg.length) $emptyMsg.hide();
        }

        if ($swipeControls && $swipeControls.length && !$swipeDeck.find('.swipe-card:not(.card-hidden):not(.card-beyond-stack)').first().length) {
            $swipeControls.hide();
        }
    
        $.ajax({
            url: sud_swipe_page_config.ajax_url.replace('process-swipe.php', 'get-swipe-candidates.php'),
            type: 'GET',
            data: {
                action: 'sud_get_candidates',
                count: 10,
            },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    if (typeof response.data.has_more !== 'undefined') {
                        hasMoreCandidatesToLoad = response.data.has_more;
                    }

                    if (response.data.candidates && response.data.candidates.length > 0) {
                        const newCandidates = response.data.candidates;
                        // Ensure candidates is always an array before concatenating
                        if (!Array.isArray(candidates)) {
                            candidates = [];
                        }
                        candidates = candidates.concat(newCandidates);
                        if (currentIndex === -1 && candidates.length > 0) currentIndex = 0;

                        if ($swipeDeck.find('.swipe-card').length < MAX_VISIBLE_CARDS) {
                            renderDeck();
                        } else {
                            applyStackStylingAndGestures();
                        }
                    } else {
                        if (!hasMoreCandidatesToLoad && currentIndex >= candidates.length && $swipeDeck.find('.swipe-card').length === 0) {
                            $emptyMsg.show();
                            $swipeControls.hide();
                        }
                    }
                } else {
                    hasMoreCandidatesToLoad = false;
                    if (candidates.length === 0 && $swipeDeck.find('.swipe-card').length === 0 && $emptyMsg && $emptyMsg.length && (!$limitModal || !$limitModal.hasClass('show'))) $emptyMsg.show();
                    if (typeof SUD !== 'undefined' && SUD.showToast) {
                        SUD.showToast('info', 'Notice', (response.data && response.data.message) || 'Could not fetch more profiles at this time.');
                    }
                }
            },
            error: function() {
                hasMoreCandidatesToLoad = false;
                if (candidates.length === 0 && $swipeDeck.find('.swipe-card').length === 0 && $emptyMsg && $emptyMsg.length && (!$limitModal || !$limitModal.hasClass('show'))) $emptyMsg.show();
                if (typeof SUD !== 'undefined' && SUD.showToast) {
                    SUD.showToast('error', 'Network Error', 'Could not fetch more profiles.');
                }
            },
            complete: function() {
                isLoading = false;
                if ($loadingMsg && $loadingMsg.is(':visible')) $loadingMsg.hide();

                if (remainingSwipes <= 0 && !sud_swipe_page_config.is_premium) {
                    if ($swipeDeck.find('.swipe-card:not(.card-hidden):not(.card-beyond-stack)').length === 0 && currentIndex >= candidates.length) {
                        showSwipeLimitWall();
                        return;
                    }
                }

                const deckIsEmpty = $swipeDeck.find('.swipe-card:not(.card-hidden):not(.card-beyond-stack)').length === 0;

                if (deckIsEmpty && !hasMoreCandidatesToLoad) {
                    hideAllSkeletons();
                    $emptyMsg.show();
                    $swipeControls.hide();
                } else if (!deckIsEmpty) {
                    applyStackStylingAndGestures();
                }
            }
        });
    }

    function showSwipeLimitWall() {
        if ($loadingMsg && $loadingMsg.length) $loadingMsg.hide();
        if ($swipeDeck && $swipeDeck.length) $swipeDeck.hide();
        if ($swipeControls && $swipeControls.length) $swipeControls.hide();
        
        if ($remainingSwipesDisplayContainer && $remainingSwipesDisplayContainer.length) {
            $remainingSwipesDisplayContainer.remove();
        }
        hideAllSkeletons();
    
        let emptyMsgContent = `
            <i class="fas fa-lock"></i> You've hit your daily swipe limit. 
            <br><small>Upgrade to keep swiping!</small>
            <br><br><a href="${sud_swipe_page_config.premium_url || '/wordpress/sud/pages/premium'}" class="btn btn-primary">Upgrade Now</a>
        `;
        if (sud_swipe_page_config.currentUserFirstName) {
            emptyMsgContent = `
                <i class="fas fa-lock"></i> No more swipes for today, ${escapeHtml(sud_swipe_page_config.currentUserFirstName)}! 
                <br><small>Upgrade to keep swiping or check back tomorrow.</small>
                <br><br><a href="${sud_swipe_page_config.premium_url || '/wordpress/sud/pages/premium'}" class="btn btn-primary">Upgrade Now</a>
            `;
        }
        if ($emptyMsg && $emptyMsg.length) {
            $emptyMsg.html(emptyMsgContent).show();
        }
        
        if (sud_swipe_page_config && sud_swipe_page_config.premium_url && $limitModal && $limitModal.length) {
            const $modalTitle = $limitModal.find('#swipe-limit-modal-title');
            if ($modalTitle.length) {
                 $modalTitle.text(
                    sud_swipe_page_config.currentUserFirstName 
                    ? `Limit Reached, ${escapeHtml(sud_swipe_page_config.currentUserFirstName)}!`
                    : 'Daily Swipe Limit Reached'
                );
            }
            $limitModal.find('.btn-primary').attr('href', sud_swipe_page_config.premium_url);
            
            if (!$matchModal.hasClass('show')) { 
                $limitModal.addClass('show');
            }
        }
    }

    function showDynamicLimitModal(limitData) {
        // Update modal content based on trial status
        if ($limitModal && $limitModal.length) {
            const $modalTitle = $limitModal.find('#swipe-limit-modal-title');
            const $modalMessage = $limitModal.find('#swipe-limit-modal-message');
            const $upgradeBtn = $limitModal.find('.btn-primary');
            
            if ($modalTitle.length) {
                $modalTitle.text('You\'ve Hit Your Daily Swipe Limit');
            }
            
            if ($modalMessage.length) {
                const message = limitData.upgrade_message || 'Upgrade now to unlock unlimited swiping!';
                $modalMessage.text(message);
            }
            
            if ($upgradeBtn.length && limitData.upgrade_url) {
                $upgradeBtn.attr('href', limitData.upgrade_url);
                $upgradeBtn.text(limitData.upgrade_text || 'Upgrade Now');
            }
            
            // Show the modal
            if (!$matchModal.hasClass('show')) { 
                $limitModal.addClass('show');
            }
        }
        
        // Also update the swipe limit wall
        showSwipeLimitWall();
    }

    function showMatchNotification(matchDetails, isSwipeUpMatch = false) {
        if (!matchDetails || !($matchModal && $matchModal.length)) return;
        
        if (typeof SUD !== 'undefined' && SUD.playNotificationSound) SUD.playNotificationSound('match');

        const defaultPicUrl = (sud_swipe_page_config.img_path_url || '') + '/default-profile.jpg';
        $matchModal.find('#match-user2-img').attr('src', matchDetails.profile_pic || defaultPicUrl);
        $matchModal.find('#match-user-name').text(matchDetails.name || 'Someone');
        $matchModal.find('#match-send-message-btn').attr('href', matchDetails.message_url || '#');
        
        // Customize text based on match type
        if (isSwipeUpMatch) {
            $matchModal.find('#match-modal-title').text("Instant Match!");
            $matchModal.find('#match-modal-description').html(`You have instantly matched with <strong>${matchDetails.name || 'Someone'}</strong> using Swipe Up!`);
        } else {
            $matchModal.find('#match-modal-title').text("It's a Match!");
            $matchModal.find('#match-modal-description').html(`You and <strong>${matchDetails.name || 'Someone'}</strong> have liked each other.`);
        }
        
        $matchModal.addClass('show');
        
        // Update sidebar match count in real-time
        updateSidebarMatchCount();
        
        createConfetti();
    }

    function createConfetti() {
        const confettiContainer = document.getElementById('confetti-container');
        if (!confettiContainer) return;
        
        confettiContainer.innerHTML = '';
        
        const colors = ['#ff6b6b', '#ff4081', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5', '#2196f3', '#03a9f4', '#00bcd4', '#009688', '#4caf50', '#8bc34a', '#cddc39', '#ffeb3b', '#ffc107', '#ff9800', '#ff5722', '#ffeb3b'];
        const shapes = ['circle', 'square', 'triangle'];
        
        for (let i = 0; i < 80; i++) {
            const confetti = document.createElement('div');
            confetti.className = 'confetti';
            
            const startX = Math.random() * 100;
            const startY = -20;
            const color = colors[Math.floor(Math.random() * colors.length)];
            const shape = shapes[Math.floor(Math.random() * shapes.length)];
            const size = Math.random() * 10 + 5;
            const duration = Math.random() * 3 + 2;
            const delay = Math.random()
            Object.assign(confetti.style, {
                left: `${startX}%`,
                top: `${startY}px`,
                width: `${size}px`,
                height: `${size}px`,
                backgroundColor: color,
                borderRadius: shape === 'circle' ? '50%' : shape === 'triangle' ? '0' : '0',
                animation: `confetti ${duration}s ease-in-out ${delay}s forwards`
            });
            
            if (shape === 'triangle') {
                confetti.style.width = '0';
                confetti.style.height = '0';
                confetti.style.backgroundColor = 'transparent';
                confetti.style.borderLeft = `${size/2}px solid transparent`;
                confetti.style.borderRight = `${size/2}px solid transparent`;
                confetti.style.borderBottom = `${size}px solid ${color}`;
            }
            
            confettiContainer.appendChild(confetti);
        }
    }

    function createReverseAnimation(user, swipeType) {
        $swipeDeck.find(`.swipe-card[data-user-id="${user.id}"]`).remove();
        
        const $reverseCard = $(createCardHtml(user));
        $reverseCard.addClass('swipe-card');
        $reverseCard.data('user-id', user.id);
        
        let startTransform = '';
        if (swipeType === 'like') {
            startTransform = 'translateX(150%) rotate(30deg)';
        } else if (swipeType === 'pass') {
            startTransform = 'translateX(-150%) rotate(-30deg)';
        } else if (swipeType === 'swipe_up') {
            startTransform = 'translateY(-150%) scale(0.8)';
        }
        
        $reverseCard.css({
            'transform': startTransform,
            'opacity': '0',
            'z-index': '1000'
        });
        
        $swipeDeck.prepend($reverseCard);
        
        setTimeout(() => {
            $reverseCard.addClass('reversing-in');
        }, 50);
        
        setTimeout(() => {
            $reverseCard.removeClass('reversing-in');
            
            candidates = candidates.filter(candidate => candidate.id !== user.id);
            
            candidates.unshift(user);
            currentIndex = 0;
            
            lastSwipedUser = null;
            updateReverseButtonState();
            
            renderDeck();
        }, 600);
    }
    
    function showReverseUpgradeModal() {
        if ($reverseUpgradeModal && $reverseUpgradeModal.length) {
            $reverseUpgradeModal.addClass('show');
        }
    }

    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#39;");
    }
    
    function showAllSkeletons() {
        if ($skeletonDeck && $skeletonDeck.length) $skeletonDeck.show();
        if ($skeletonControls && $skeletonControls.length) $skeletonControls.show();
        if ($skeletonRemainingSwipes && $skeletonRemainingSwipes.length) $skeletonRemainingSwipes.show();
    }

    function hideAllSkeletons() {
        if ($skeletonDeck && $skeletonDeck.length) $skeletonDeck.hide();
        if ($skeletonControls && $skeletonControls.length) $skeletonControls.hide();
        if ($skeletonRemainingSwipes && $skeletonRemainingSwipes.length) $skeletonRemainingSwipes.hide();
    }

    function showSwipeUpModal() {
        if ($swipeUpModal && $swipeUpModal.length) {
            $swipeUpModal.addClass('show');
        }
    }

    function showBoostModal() {
        if ($boostModal && $boostModal.length) {
            $boostModal.addClass('show');
        }
    }

    function updateSwipeUpButtonUI() {
        const $boostBtn = $('#swipe-boost-btn');
        if (!$boostBtn.length) return;

        const $balanceCounter = $boostBtn.find('.swipe-up-count');
        
        $boostBtn.prop('disabled', false).removeClass('disabled');
        
        if (swipeUpBalance > 0) {
            $boostBtn.attr('title', `Swipe Up - Instant Match (${swipeUpBalance} left)`);
            if ($balanceCounter.length) {
                $balanceCounter.text(swipeUpBalance);
            } else {
                $boostBtn.append(`<span class="swipe-up-count">${swipeUpBalance}</span>`);
            }
        } else {
            $boostBtn.attr('title', 'Get Swipe Ups to instantly match');
            if ($balanceCounter.length) {
                $balanceCounter.remove();
            }
        }
    }

    function checkAndTriggerBoostModal() {
        const eligiblePlans = ['silver', 'gold', 'diamond'];
        const currentUserPlan = sud_swipe_page_config.user_plan_id || 'free';
    
        if (!eligiblePlans.includes(currentUserPlan)) {
            return; 
        }
    
        if (sessionSwipeCounter >= BOOST_PROMPT_THRESHOLD) {
            showBoostModal();
            sessionSwipeCounter = 0;
        }
    }

    /**
     * Checks if the free swipe up prompt should be shown.
     * This is now tied to a daily server check.
     */
    function checkAndTriggerFreeSwipeUpPrompt() {
        // Only trigger for non-premium users after the 3rd swipe of the session.
        if (sud_swipe_page_config.is_premium || sessionSwipeCounter !== 3) {
            return;
        }
    
        $.ajax({
            url: sud_swipe_page_config.ajax_url.replace('process-swipe.php', 'claim-free-swipe-up.php'),
            type: 'POST',
            dataType: 'json',
            data: { 
                check_only: true,
                _ajax_nonce: sud_swipe_page_config.swipe_nonce
            },
            success: function(response) {
                // If the check succeeds, it means the user is eligible to claim today. Show the modal.
                if (response.success) {
                    const $modal = $('#claim-free-swipe-up-modal');
                    if ($modal.length) {
                        $modal.addClass('show');
                    }
                }
                // If it fails, the user has already claimed or is ineligible. Do nothing.
            },
            error: function() {
                console.error("Could not check free swipe up eligibility.");
            }
        });
    }

    function updateSidebarMatchCount() {
        const $matchStatValue = $('.sidebar-stats .stat-item a[href*="my-matches"] .stat-value');
        if ($matchStatValue.length) {
            const currentCount = parseInt($matchStatValue.text()) || 0;
            $matchStatValue.text(currentCount + 1);
        }
    }

    return {
        init: init,
        performSwipeAction: performSwipeAction
    };

})(jQuery);

jQuery(document).ready(function() {
    if ($('body.sud-swipe-page-body').length) {
        SUD.SwipePage.init();
    }

    if ($('#match-notification-modal').hasClass('show')) {
        setTimeout(() => {
            createConfetti();
        }, 500);
    }
});