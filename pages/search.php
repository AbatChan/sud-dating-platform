<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/location-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/messaging-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/search-functions.php');

require_login();

$current_user = wp_get_current_user();
$current_user_id = $current_user->ID;

// Use centralized validation for all profile requirements
validate_core_profile_requirements($current_user_id, 'search');

// Allow access to search page but limit functionality for non-premium users
$is_premium_user = sud_is_user_premium($current_user_id);

$display_name = $current_user->display_name;

$profile_picture_id = get_user_meta($current_user->ID, 'profile_picture', true);
$profile_pic_url = !empty($profile_picture_id) ? wp_get_attachment_image_url($profile_picture_id, 'medium_large') : SUD_IMG_URL . '/default-profile.jpg';
$completion_percentage = get_profile_completion_percentage($current_user->ID); 
$user_messages = get_user_messages($current_user->ID); 
$unread_message_count = $user_messages['unread_count'] ?? 0; 

$current_user_functional_role = get_user_meta($current_user_id, 'functional_role', true);
$target_functional_role = '';
$can_search = true;
$search_error_message = '';
$is_admin_view = current_user_can('manage_options');

$base_meta_query = ['relation' => 'AND'];

if ($is_admin_view) {
    // Admin View: Allow search, no role filtering needed in base query
} else {
    if ($current_user_functional_role === 'provider') {
        $target_functional_role = 'receiver';
    } elseif ($current_user_functional_role === 'receiver') {
        $target_functional_role = 'provider';
    }
    if ($can_search && !empty($target_functional_role)) {
        $base_meta_query['role_clause'] = [
            'key' => 'functional_role',
            'value' => $target_functional_role,
            'compare' => '='
        ];
    } else {
        $can_search = false;
        $search_error_message = "Your user role is not properly defined.";
    }
}

// Store user's gender and preferences for later use, but don't apply automatic filtering
$current_user_gender = get_user_meta($current_user_id, 'gender', true);
$current_user_looking_for = get_user_meta($current_user_id, 'looking_for', true);

// Note: Gender filtering will be handled in search criteria processing
// This allows users to manually override default gender preferences in search

// Use centralized member display configuration  
$members_per_page = 12;           // Members to display per page/load
$background_fetch_size = 40;      // Background pool size for faster loading
$prefetch_threshold = 8;          // When to trigger background refetch

$results_per_page = $members_per_page;

// Initialize search variables
$search_results = null;
$search_performed = false;
$search_criteria = [
    'gender' => '',
    'looking_for' => '', 
    'min_age' => 18,
    'max_age' => 70,
    'location' => '',
    'ethnicity' => '',
    'verified_only' => false,
    'online_only' => false,
    'body_type' => ''
];

$can_use_advanced = sud_user_can_access_feature($current_user_id, 'advanced_filters');
$premium_page_url = SUD_URL . '/pages/premium';

// Unified search processing using centralized functions
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['search'])) {
    $search_performed = true;

    // Process search criteria using centralized function
    $search_criteria = sud_process_search_criteria($_GET, $current_user_id, $can_use_advanced);
    
    // Apply default gender preferences if no explicit gender selected
    $search_criteria = sud_apply_default_gender_preferences($search_criteria, $current_user_id, $is_admin_view);
    
    // Validate search criteria
    $validation_result = sud_validate_search_criteria($search_criteria);
    if ($validation_result !== true) {
        $search_error_message = implode(', ', $validation_result);
        $search_performed = false;
    } else {
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        if ($can_search && function_exists('search_users')) {
            $search_results = search_users($search_criteria, $page, $results_per_page, $base_meta_query);
            
            // Log search activity for debugging
            sud_log_search_activity($current_user_id, $search_criteria, $search_results);
        } elseif (!$can_search) {
            $search_results = ['users' => [], 'total' => 0, 'page' => 1, 'pages' => 0]; 
        } else {
            $search_results = null; 
            error_log("Search Error: search_users function not found.");
        }
    }
}

$show_featured = false;
$featured_members = [];

if (!$search_performed || ($search_performed && (!is_array($search_results) || !isset($search_results['users']) || empty($search_results['users'])))) {
    if ($can_search) {
        $show_featured = true;
        $featured_fetch_limit = $is_premium_user ? ($results_per_page * 2) : $results_per_page;

        // Apply gender preferences to featured members using the same logic as search
        $featured_criteria = ['gender' => '', 'looking_for' => ''];
        $featured_criteria = sud_apply_default_gender_preferences($featured_criteria, $current_user_id, $is_admin_view);
        
        $featured_meta_query = $base_meta_query;
        
        // Add gender filter if user has specific preferences
        if (!empty($featured_criteria['gender'])) {
            $featured_meta_query['gender_clause'] = [
                'key' => 'gender',
                'value' => $featured_criteria['gender'],
                'compare' => '='
            ];
        }

        $featured_args = [
            'orderby' => 'last_active',
            'order' => 'DESC',
            'meta_query' => $featured_meta_query
        ];
        $featured_members_raw = function_exists('custom_get_users') ? custom_get_users($current_user_id, $featured_fetch_limit, 0, $featured_args) : [];

        // Apply both profile boost and priority user sorting
        if(function_exists('sud_apply_profile_boost')) {
            $featured_members = sud_apply_profile_boost($featured_members_raw, $current_user_id);
        } else if(function_exists('sud_sort_users_with_priority')) {
            $featured_members = sud_sort_users_with_priority($featured_members_raw, 'last_active');
        } else {
            $featured_members = $featured_members_raw;
        }
        $featured_members = array_slice($featured_members, 0, $results_per_page);
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
        if ( function_exists( 'get_site_icon_url' ) && ( $icon_url = get_site_icon_url() ) ) {
        echo '<link rel="icon" href="' . esc_url( $icon_url ) . '" />';
        }
    ?>
    <title>Search - <?php echo esc_html(SUD_SITE_NAME); ?></title>
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/search.css">
    <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/swipe.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Include base config before common.js if needed -->
    <script>
        var sud_config_base = {
        current_user_id: <?php echo json_encode($current_user_id); ?>,
        sud_url: "<?php echo esc_js(SUD_URL); ?>",
        is_premium_user: <?php echo json_encode($is_premium_user); ?>,
        initial_limit: <?php echo esc_js($results_per_page); ?>,
        members_per_page: <?php echo esc_js($members_per_page); ?>,
        background_fetch_size: <?php echo esc_js($background_fetch_size); ?>,
        prefetch_threshold: <?php echo esc_js($prefetch_threshold); ?>
        };
    </script>
    <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
    <script src="<?php echo SUD_JS_URL; ?>/mobile-filter.js"></script>
    <style>
        .premium-search-overlay {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            margin-top: 10px;
        }
        .premium-search-overlay p {
            margin: 0 0 15px 0;
            font-weight: 600;
        }
        .premium-search-overlay .fas {
            margin-right: 8px;
            color: #ffd700;
        }
        <?php if (!$is_premium_user): ?>
        .search-sidebar .filter-group input,
        .search-sidebar .filter-group select {
            opacity: 0.6;
            pointer-events: none;
            display: none;
        }
        .search-sidebar .filter-group label {
            opacity: 0.6;
        }
        <?php endif; ?>
    </style>
</head>
<body>
    <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>

    <main class="main-content">
        <div class="container">
            <div class="search-container">
                <div class="search-sidebar">
                    <div class="sidebar-container">
                        <h2>Search Filters</h2>
                        <form method="get" id="search-form">
                            <input type="hidden" name="search" value="1">

                            <div class="filter-group">
                                <h3>I am looking for</h3>
                                <div class="filter-options">
                                     <label class="<?php echo ($search_criteria['gender'] === 'Man') ? 'selected' : ''; ?>">
                                         <input type="radio" name="gender" value="Man" <?php checked($search_criteria['gender'], 'Man'); ?>> <span>Men</span>
                                     </label>
                                     <label class="<?php echo ($search_criteria['gender'] === 'Woman') ? 'selected' : ''; ?>">
                                         <input type="radio" name="gender" value="Woman" <?php checked($search_criteria['gender'], 'Woman'); ?>> <span>Women</span>
                                     </label>
                                     <label class="<?php echo ($search_criteria['gender'] === 'LGBTQ+') ? 'selected' : ''; ?>">
                                         <input type="radio" name="gender" value="LGBTQ+" <?php checked($search_criteria['gender'], 'LGBTQ+'); ?>> <span>LGBTQ+</span>
                                     </label>
                                </div>
                            </div>
                            <?php  ?>
                            <div class="filter-group">
                                <h3>Age Range</h3>
                                <div class="age-range-slider">
                                     <div id="age-slider" class="sud-age-slider"></div>
                                     <div class="age-range-values"> <span id="age-display"><?php echo $search_criteria['min_age']; ?> - <?php echo $search_criteria['max_age']; ?> yo</span> </div>
                                     <input type="hidden" name="min_age" id="min-age" value="<?php echo $search_criteria['min_age']; ?>">
                                     <input type="hidden" name="max_age" id="max-age" value="<?php echo $search_criteria['max_age']; ?>">
                                 </div>
                            </div>
                            <div class="filter-group">
                                <h3>Location</h3>
                                <input type="text" name="location" id="location" value="<?php echo esc_attr($search_criteria['location']); ?>" placeholder="City, Country" class="filter-input">
                            </div>

                            <div class="filter-group <?php echo !$can_use_advanced ? 'locked-filter' : ''; ?>">
                                <h3>Ethnicity <?php if (!$can_use_advanced) echo '<span>(Diamond)</span>'; ?></h3>
                                <select name="ethnicity" id="ethnicity" class="filter-select" <?php if (!$can_use_advanced) echo 'disabled'; ?>>
                                    <option value="">Any Ethnicity</option>
                                    <?php

                                    $ethnicity_options = function_exists('get_attribute_options') ? get_attribute_options('ethnicity') : [
                                        'african' => 'African', 'asian' => 'Asian', 'caucasian' => 'Caucasian',
                                        'hispanic' => 'Hispanic', 'middle_eastern' => 'Middle Eastern', 'latino' => 'Latino',
                                        'native_american' => 'Native American', 'pacific_islander' => 'Pacific Islander',
                                        'multiracial' => 'Multiracial', 'other' => 'Other'
                                    ];
                                    foreach ($ethnicity_options as $value => $label): ?>
                                        <option value="<?php echo esc_attr($value); ?>" <?php selected($search_criteria['ethnicity'], $value); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (!$can_use_advanced): ?>
                                <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=diamond" class="filter-lock-overlay-wrapper" title="Upgrade to Diamond to use this filter">
                                    <div class="filter-lock-overlay"><i class="fas fa-lock"></i><span>Unlock Filter</span></div>
                                </a>
                                <?php endif; ?>
                            </div>

                            <div class="filter-group <?php echo !$can_use_advanced ? 'locked-filter' : ''; ?>">
                                <h3>Additional Filters <?php if (!$can_use_advanced) echo '<span>(Diamond)</span>'; ?></h3>
                                <div class="filter-options">
                                    <label class="<?php echo $search_criteria['verified_only'] ? 'selected' : ''; ?>">
                                        <input type="checkbox" name="verified_only" value="1" <?php checked($search_criteria['verified_only'], true); ?> <?php if (!$can_use_advanced) echo 'disabled'; ?>>
                                        <span>Verified members only</span>
                                    </label>
                                    <label class="<?php echo $search_criteria['online_only'] ? 'selected' : ''; ?>">
                                        <input type="checkbox" name="online_only" value="1" <?php checked($search_criteria['online_only'], true); ?> <?php if (!$can_use_advanced) echo 'disabled'; ?>>
                                        <span>Online now</span>
                                    </label>
                                </div>
                                <?php if (!$can_use_advanced): ?>
                                <a href="<?php echo esc_url($premium_page_url); ?>?highlight_plan=diamond" class="filter-lock-overlay-wrapper" title="Upgrade to Diamond to use this filter">
                                     <div class="filter-lock-overlay"><i class="fas fa-lock"></i><span>Unlock Filters</span></div>
                                 </a>
                                <?php endif; ?>
                            </div>

                            <div class="filter-actions">
                                <?php if ($is_premium_user): ?>
                                    <button type="submit" class="btn-primary" <?php if (!$can_search) echo 'disabled title="' . esc_attr($search_error_message) . '"'; ?>>Search</button>
                                    <button type="reset" class="btn-secondary">Reset Filters</button>
                                <?php else: ?>
                                    <div class="premium-search-overlay">
                                        <p><i class="fas fa-crown"></i> Search is a Premium Feature</p>
                                        <a href="<?php echo esc_url($premium_page_url); ?>" class="btn-primary">Upgrade to Search</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="search-results">
                    <?php if (!$can_search): ?>
                    <div class="no-results-placeholder">
                        <div class="no-results-icon"><i class="fas fa-exclamation-triangle"></i></div>
                        <h3>Search Unavailable</h3>
                        <p><?php echo esc_html($search_error_message ?: 'Your role is not set correctly.'); ?></p> 
                    </div>
                    <?php elseif ($search_performed): ?>
                        <div class="results-header"><h2>Search Results</h2></div>
                        <?php 
                        if ($search_results && is_array($search_results) && isset($search_results['users'], $search_results['total'], $search_results['pages'])):
                            if (!empty($search_results['users'])): ?>
                                <p class="results-count">Found <?php echo number_format($search_results['total']); ?> members matching your criteria</p>
                                <div class="user-grid">
                                    <?php foreach ($search_results['users'] as $user):
                                        $style = 'overlay';
                                        include(dirname(__FILE__, 2) . '/templates/components/user-card.php');
                                    endforeach; ?>
                                </div>
                                <?php 
                                if ($search_results['pages'] > 1 && $is_premium_user):
                                    $current_page = $search_results['page'];
                                    $total_pages = $search_results['pages'];
                                    $url_params = $_GET; 
                                    ?>
                                    <div class="pagination">
                                        <?php 
                                        if ($current_page > 1):
                                            $url_params['page'] = $current_page - 1;
                                        ?>
                                        <a href="?<?php echo http_build_query($url_params); ?>" class="pagination-link prev">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                        <?php endif; ?>

                                        <?php 
                                        $start_page = max(1, $current_page - 2);
                                        $end_page = min($total_pages, $current_page + 2);

                                        if ($start_page > 1): 
                                            $url_params['page'] = 1; ?>
                                            <a href="?<?php echo http_build_query($url_params); ?>" class="pagination-link">1</a>
                                            <?php if ($start_page > 2): echo '<span class="pagination-ellipsis">...</span>'; endif;
                                        endif;

                                        for ($i = $start_page; $i <= $end_page; $i++): 
                                            $url_params['page'] = $i;
                                            $active_class = ($i == $current_page) ? 'active' : ''; ?>
                                            <a href="?<?php echo http_build_query($url_params); ?>" class="pagination-link <?php echo $active_class; ?>"><?php echo $i; ?></a>
                                        <?php endfor;

                                        if ($end_page < $total_pages): 
                                            if ($end_page < $total_pages - 1): echo '<span class="pagination-ellipsis">...</span>'; endif;
                                            $url_params['page'] = $total_pages; ?>
                                            <a href="?<?php echo http_build_query($url_params); ?>" class="pagination-link"><?php echo $total_pages; ?></a>
                                        <?php endif; ?>

                                        <?php 
                                        if ($current_page < $total_pages):
                                            $url_params['page'] = $current_page + 1;
                                        ?>
                                        <a href="?<?php echo http_build_query($url_params); ?>" class="pagination-link next">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-results-placeholder">
                                    <div class="no-results-icon"><i class="fas fa-search-minus"></i></div>
                                    <h3>No Members Found</h3>
                                    <p>No members matched your specific search criteria.<?php echo $show_featured ? ' Try broadening your search or browse featured members below.' : ' Try broadening your search.'; ?></p>
                                </div>
                            <?php endif; else: ?>
                            <div class="alert alert-error">An error occurred while retrieving search results. Please try again.</div>
                             <?php error_log("Search Page Error: search_users returned invalid data. Criteria: " . print_r($search_criteria, true)); ?>
                        <?php endif; else: ?>
                        <div class="search-intro">
                            <h2>Find Your Perfect Match</h2>
                            <?php if ($is_premium_user): ?>
                                <p>Use the filters on the left to start your search<?php echo $show_featured ? ' or browse featured members.' : '.'; ?></p>
                            <?php else: ?>
                                <p>Browse featured members below or <a href="<?php echo esc_url($premium_page_url); ?>" style="color: #667eea; font-weight: 600;">upgrade to Premium</a> to unlock advanced search and filtering.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif;
                    if ($show_featured):
                        if (!empty($featured_members)): ?>
                            <div class="featured-members">
                                <h3>Featured Members</h3>
                                <?php if (!$is_premium_user): ?>
                                    <div class="featured-preview-notice" style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: center; border-left: 4px solid #667eea;">
                                        <p style="margin: 0; color: #495057;"><i class="fas fa-info-circle" style="color: #667eea; margin-right: 8px;"></i>
                                        Showing preview of featured members. <a href="<?php echo esc_url($premium_page_url); ?>" style="color: #667eea; font-weight: 600;">Upgrade to Premium</a> to search, filter, and see all members.</p>
                                    </div>
                                <?php endif; ?>
                                <div class="user-grid">
                                    <?php foreach ($featured_members as $user):
                                        $style = 'overlay';
                                        include(dirname(__FILE__, 2) . '/templates/components/user-card.php');
                                    endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (!$search_performed): ?>
                            <div class="no-results-placeholder">
                                <div class="no-results-icon"><i class="fas fa-users-slash"></i></div>
                                <h3>No Members to Display</h3>
                                <p>There are currently no featured members matching the criteria for your role.</p>
                            </div>
                        <?php endif; 
                    endif; 
                    ?>
                </div>
            </div>
        </div>
    </main>
    <div id="toast-container" class="toast-container"></div>

    <?php include(dirname(__FILE__, 2) . '/templates/components/message-modal.php'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ageSlider = document.getElementById('age-slider');
            if(ageSlider) {
                let ageMin = parseInt(document.getElementById('min-age').value) || 18;
                let ageMax = parseInt(document.getElementById('max-age').value) || 70;
                const MIN_AGE = 18; const MAX_AGE = 70; const RANGE = MAX_AGE - MIN_AGE;
                const minHandle = document.createElement('div'); minHandle.className = 'sud-slider-handle sud-slider-handle-min'; minHandle.dataset.handle = 'min';
                const maxHandle = document.createElement('div'); maxHandle.className = 'sud-slider-handle sud-slider-handle-max'; maxHandle.dataset.handle = 'max';
                const connectElement = document.createElement('div'); connectElement.className = 'sud-slider-connect';
                ageSlider.appendChild(minHandle); ageSlider.appendChild(maxHandle); ageSlider.appendChild(connectElement);
                const ageDisplay = document.getElementById('age-display');
                const ageMinInput = document.getElementById('min-age');
                const ageMaxInput = document.getElementById('max-age');

                function positionFromAge(age) {  return ((Math.max(MIN_AGE, Math.min(MAX_AGE, age)) - MIN_AGE) / RANGE) * 100; }
                function ageFromPosition(position) {  return Math.max(MIN_AGE, Math.min(MAX_AGE, Math.round(MIN_AGE + (position / 100) * RANGE))); }
                function updateHandlePositions() {  const minPos = positionFromAge(ageMin); const maxPos = positionFromAge(ageMax); minHandle.style.left = minPos + '%'; maxHandle.style.left = maxPos + '%'; connectElement.style.left = minPos + '%'; connectElement.style.width = (maxPos - minPos) + '%'; ageDisplay.textContent = ageMin + ' - ' + ageMax + ' yo'; ageMinInput.value = ageMin; ageMaxInput.value = ageMax; }
                updateHandlePositions();
                let isDragging = false; let currentHandle = null;
                function startDrag(e, handle) {  e.preventDefault(); isDragging = true; currentHandle = handle; document.addEventListener('mousemove', drag); document.addEventListener('mouseup', stopDrag); document.addEventListener('touchmove', drag, { passive: false }); document.addEventListener('touchend', stopDrag); document.body.style.userSelect = 'none'; }
                function drag(e) {  if (!isDragging) return; let clientX = e.touches ? e.touches[0].clientX : e.clientX; const rect = ageSlider.getBoundingClientRect(); const offsetX = clientX - rect.left; let percent = Math.max(0, Math.min(100, (offsetX / rect.width) * 100)); const newAge = ageFromPosition(percent); if (currentHandle.dataset.handle === 'min') { ageMin = Math.min(ageMax - 1, newAge); } else { ageMax = Math.max(ageMin + 1, newAge); } updateHandlePositions(); }
                function stopDrag() {  isDragging = false; document.removeEventListener('mousemove', drag); document.removeEventListener('mouseup', stopDrag); document.removeEventListener('touchmove', drag); document.removeEventListener('touchend', stopDrag); document.body.style.userSelect = ''; }
                minHandle.addEventListener('mousedown', (e) => startDrag(e, minHandle)); maxHandle.addEventListener('mousedown', (e) => startDrag(e, maxHandle));
                minHandle.addEventListener('touchstart', (e) => startDrag(e, minHandle)); maxHandle.addEventListener('touchstart', (e) => startDrag(e, maxHandle));
                window.addEventListener('resize', updateHandlePositions);
            }

            const filterOptions = document.querySelectorAll('.filter-options label');
            filterOptions.forEach(label => {
                const inputElement = label.querySelector('input[type="radio"], input[type="checkbox"]');
                if(inputElement) {
                    const updateSelectedClass = () => {
                        if (inputElement.type === 'radio') {
                            const name = inputElement.name;
                            document.querySelectorAll(`input[type="radio"][name="${name}"]`).forEach(radioInGroup => {
                                radioInGroup.closest('label').classList.toggle('selected', radioInGroup.checked);
                            });
                        } else { 
                            label.classList.toggle('selected', inputElement.checked);
                        }
                    };
                    label.addEventListener('click', (e) => {
                    setTimeout(updateSelectedClass, 0);
                    });
                    updateSelectedClass();
                }
             });

            const canUseAdvancedJS = <?php echo json_encode($can_use_advanced); ?>;
            if (!canUseAdvancedJS) {
                document.querySelectorAll('.locked-filter select, .locked-filter input[type="checkbox"]').forEach(el => el.disabled = true);
            }

            const resetButton = document.querySelector('button[type="reset"]');
            if (resetButton) {
                resetButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    const ageMinInputReset = document.getElementById('min-age');
                    const ageMaxInputReset = document.getElementById('max-age');
                    if (ageMinInputReset && ageMaxInputReset && typeof updateHandlePositions === 'function') {
                        ageMin = 18; ageMax = 70;
                        updateHandlePositions();
                    }
                    const searchForm = document.getElementById('search-form');
                    if (searchForm) { searchForm.reset(); }
                    document.querySelectorAll('.filter-options label.selected').forEach(label => { label.classList.remove('selected'); });
                    document.querySelectorAll('.filter-select').forEach(select => { select.selectedIndex = 0; });
                    window.location.href = window.location.pathname; 
                });
            }
        });
    </script>

    <!-- Search Premium Modal -->
    <div id="search-premium-modal" class="modal">
        <div class="sud-modal-content">
            <span class="close-modal">×</span>
            <div class="modal-icon"><i class="fas fa-search"></i></div>
            <h3>Unlock Advanced Search</h3>
            <p>Advanced search filters are a premium feature. Upgrade to access location filters, ethnicity filters, and more!</p>
            <ul class="upgrade-benefits-list">
                <li><i class="fas fa-filter"></i> Advanced Search Filters</li>
                <li><i class="fas fa-map-marked-alt"></i> Location & Distance Filters</li>
                <li><i class="fas fa-user-friends"></i> Unlimited Search Results</li>
                <li><i class="fas fa-star"></i> Premium Member Access</li>
            </ul>
            <div class="premium-modal-actions">
                <a href="<?php echo esc_url(SUD_URL . '/pages/premium'); ?>" class="btn-primary">Upgrade to Premium</a>
                <a href="<?php echo esc_url(SUD_URL . '/pages/swipe'); ?>" class="btn-secondary">Keep Swiping</a>
            </div>
        </div>
    </div>
</body>
</html>