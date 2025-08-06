<?php
require_once('includes/config.php');
require_login();

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

$existing_display_name = $current_user->display_name;
$existing_dob = get_user_meta($user_id, 'date_of_birth', true);
$existing_latitude = get_user_meta($user_id, 'latitude', true);
$existing_longitude = get_user_meta($user_id, 'longitude', true);
$existing_city = get_user_meta($user_id, 'city', true);
$existing_region = get_user_meta($user_id, 'region', true);
$existing_country = get_user_meta($user_id, 'country', true);
$location_string_display = get_user_meta($user_id, 'location_string', true);

if (empty($location_string_display) && !empty($existing_city) && !empty($existing_latitude)) {
    $loc_parts = array_filter([$existing_city, $existing_region, $existing_country]);
    $location_string_display = implode(', ', $loc_parts);
} elseif (empty($location_string_display) && !empty($existing_latitude)) {
    $location_string_display = "Location Set (Coords: " . round($existing_latitude, 4) . ", " . round($existing_longitude, 4) . ")";
}

$google_api_key = function_exists('sud_get_google_api_key') ? sud_get_google_api_key() : '';
$load_Maps = !empty($google_api_key);
if (!$load_Maps) {
    error_log("Profile Setup Error: Google API Key is missing. Cannot proceed.");
    wp_die("Location setup required. Please contact support.", "Configuration Error");
}

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nonce = isset($_POST['_sud_signup_nonce']) ? $_POST['_sud_signup_nonce'] : '';
    $action = 'sud_signup_profile_setup_action';
    if (!wp_verify_nonce($nonce, $action)) {
        header('Location: ' . SUD_URL . '/profile-setup?error=security_check_failed');
        exit;
    }

    $display_name = isset($_POST['display_name']) ? trim(sanitize_text_field($_POST['display_name'])) : '';
    $date_of_birth_input = isset($_POST['date_of_birth']) ? trim($_POST['date_of_birth']) : '';
    $date_of_birth = $date_of_birth_input; // Use the actual input value
    $general_consent = isset($_POST['general_consent']) ? $_POST['general_consent'] : '';

    $latitude_input = filter_input(INPUT_POST, 'latitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $longitude_input = filter_input(INPUT_POST, 'longitude', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $latitude = ($latitude_input === '' || $latitude_input === null) ? null : (float)$latitude_input;
    $longitude = ($longitude_input === '' || $longitude_input === null) ? null : (float)$longitude_input;

    $city_google = isset($_POST['city_google']) ? trim(sanitize_text_field($_POST['city_google'])) : '';
    $region_google = isset($_POST['region_google']) ? trim(sanitize_text_field($_POST['region_google'])) : '';
    $country_google = isset($_POST['country_google']) ? trim(sanitize_text_field($_POST['country_google'])) : '';
    $accuracy = isset($_POST['accuracy']) ? sanitize_text_field($_POST['accuracy']) : 'google_places';

    if (empty($display_name)) {
        $errors['display_name'] = 'Display Name is required.';
    }

    if (empty($date_of_birth_input)) {
        $errors['date_of_birth'] = 'Date of Birth is required.';
    }

    if (empty($general_consent) || $general_consent !== 'on') {
        $errors['general_consent'] = 'You must agree to the terms to continue.';
    }

    if (empty($errors)) {
        $update_success = true;

        $user_update_result = wp_update_user(['ID' => $user_id, 'display_name' => $display_name]);
        if (is_wp_error($user_update_result)) {
            $errors['display_name'] = 'Error updating display name: ' . $user_update_result->get_error_message();
            $update_success = false;
        }
        update_user_meta($user_id, 'date_of_birth', $date_of_birth);

        if ($update_success) {
            if (function_exists('update_user_location') && !empty($latitude) && !empty($longitude)) {
                $location_update_result = update_user_location( $user_id, $latitude, $longitude, $accuracy );
                if (!$location_update_result) {
                    $errors['location'] = 'Failed to save the selected location.';
                    $update_success = false;
                } else {
                    update_user_meta($user_id, 'city', $city_google);
                    update_user_meta($user_id, 'region', $region_google);
                    update_user_meta($user_id, 'country', $country_google);
                    $loc_parts_g = array_filter([$city_google, $region_google, $country_google]);
                    update_user_meta($user_id, 'location_string', implode(', ', $loc_parts_g));
                    update_user_meta($user_id, 'latitude', $latitude);
                    update_user_meta($user_id, 'longitude', $longitude);
                    update_user_meta($user_id, 'location_updated', current_time('mysql', true));
                    delete_user_meta($user_id, 'location_needs_update');
                }
            } else {
                $errors['location'] = 'An unexpected error occurred saving the location.';
                $update_success = false;
            }
        }

        if ($update_success && $display_name !== $current_user->user_login) {
            $safe_username = sanitize_user(strtolower(str_replace(' ', '', $display_name)), true);
            $safe_username = trim($safe_username, '-_');
            if (empty($safe_username)) $safe_username = 'user' . $user_id;
            $base_username = $safe_username; $counter = 1;
            while (username_exists($safe_username) && $safe_username !== $current_user->user_login) {
                $safe_username = $base_username . $counter++; if ($counter > 10) break;
            }
            if ($safe_username !== $current_user->user_login && $counter <= 10 && !empty($safe_username)) {
                global $wpdb; $result = $wpdb->update($wpdb->users, ['user_login' => $safe_username], ['ID' => $user_id], ['%s'], ['%d']);
                if ($result === false) { $errors['display_name'] = 'Could not update username.'; $update_success = false; error_log("Failed WPDB update user_login for $user_id to '$safe_username'. Error: " . $wpdb->last_error); }
                else { clean_user_cache($user_id); }
            } elseif ($counter > 10) { $errors['display_name'] = 'Could not generate unique username.'; $update_success = false; }
        }

        if ($update_success) {
            update_user_meta($user_id, 'profile_setup_complete', true);
            update_user_meta($user_id, 'profile_completed', true);
            update_user_meta($user_id, 'just_completed_profile', true);
            wp_clear_auth_cookie(); wp_set_current_user($user_id); wp_set_auth_cookie($user_id, true);
            // Redirect to welcome page first, then welcome will take them to profile-details
            header('Location: ' . SUD_URL . '/welcome'); 
            exit;
        } else {
            error_log("profile-setup: Profile setup failed for user $user_id. Errors: " . print_r($errors, true));
        }
    } else {
        error_log("profile-setup: Validation errors for user $user_id: " . print_r($errors, true));
    }
}

include('templates/header.php');
?>

<div class="sud-join-content">
    <div class="sud-join-image"></div>
    <div class="sud-join-form-container sud-profile-setup-container">
        <form class="sud-join-form sud-profile-setup-form" method="post" id="profile-setup-form" novalidate>
            <div class="cont-form">
                <h2 class="sud-welcome-content sud-let-get-profile">Let's get your profile started</h2>
                <p class="sud-subtitle">Tell us your display name, date of birth, and location.</p>

                <div class="sud-form-group sud-input-floating">
                    <input type="text" id="display_name" name="display_name" class="sud-form-input<?php echo isset($errors['display_name']) ? ' sud-input-error' : ''; ?>" value="<?php echo htmlspecialchars($_POST['display_name'] ?? $existing_display_name ?? ''); ?>" required>
                    <label for="display_name" class="sud-floating-label">Display Name <span class="sud-required">*</span></label>
                    <div class="sud-error-message" <?php echo isset($errors['display_name']) ? ' style="display: block;"' : ''; ?>>
                        <?php echo htmlspecialchars($errors['display_name'] ?? 'Display Name is required'); ?>
                    </div>
                </div>

                <div class="sud-form-group sud-input-floating">
                    <?php
                        $dob_post_val = $_POST['date_of_birth'] ?? null;
                        $dob_existing_val = $existing_dob ?? '';
                        $dob_current_val = $dob_post_val ?? $dob_existing_val;
                        $dob_value_attr = ''; $dob_display_val = '';
                        if ($dob_current_val) {
                            $dob_obj = DateTime::createFromFormat('Y-m-d', $dob_current_val);
                            if (!$dob_obj) $dob_obj = date_create($dob_current_val);
                            if ($dob_obj) { $dob_value_attr = $dob_obj->format('Y-m-d'); $dob_display_val = $dob_obj->format('F j, Y'); }
                            else { $dob_display_val = $dob_current_val; }
                        }
                    ?>
                    <input type="text" id="date_of_birth" name="date_of_birth"
                           class="sud-form-input<?php echo isset($errors['date_of_birth']) ? ' sud-input-error' : ''; ?>"
                           value="<?php echo htmlspecialchars($dob_display_val); ?>"
                           data-date="<?php echo htmlspecialchars($dob_value_attr); ?>"
                           placeholder=" " readonly required>
                    <label for="date_of_birth" class="sud-floating-label">Date of Birth <span class="sud-required">*</span></label>
                    <span class="sud-date-icon"><i class="fas fa-calendar-alt"></i></span>
                    <div class="sud-error-message" <?php echo isset($errors['date_of_birth']) ? ' style="display: block;"' : ''; ?>>
                        <?php echo htmlspecialchars($errors['date_of_birth'] ?? 'Date of Birth is required'); ?>
                    </div>
                </div>

                <h3 class="sud-form-section-title">Your Location</h3>

                <div id="auto-location-entry">
                    <p class="sud-form-description" id="auto-location-instructions">Start typing your city/address and select from the suggestions.</p>
                    <div class="sud-form-group sud-input-floating">
                        <?php
                        $google_location_display = '';
                        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['location_autocomplete_input'])) {
                                $google_location_display = $_POST['location_autocomplete_input'];
                        } elseif (!empty($location_string_display)) {
                            $google_location_display = $location_string_display;
                        }
                        ?>
                        <input type="text" id="location-autocomplete-input" name="location_autocomplete_input" class="sud-form-input" placeholder=" "
                            value="<?php echo htmlspecialchars($google_location_display); ?>" required>
                        <label for="location-autocomplete-input" class="sud-floating-label">Enter City / Address <span class="sud-required">*</span></label>
                        <div class="sud-error-message google-location-error">Please select a valid location from the suggestions.</div>
                        <div class="sud-error-message sud-location-error-general" <?php echo isset($errors['location']) ? ' style="display: block;"' : ''; ?>>
                        <?php echo htmlspecialchars($errors['location'] ?? 'A valid location is required.'); ?>
                        </div>
                        <div id="location-status-message" class="sud-location-status-message"></div>
                    </div>
                </div>

                <input type="hidden" name="latitude" id="latitude" value="<?php echo esc_attr($_POST['latitude'] ?? ($existing_latitude ?? '')); ?>">
                <input type="hidden" name="longitude" id="longitude" value="<?php echo esc_attr($_POST['longitude'] ?? ($existing_longitude ?? '')); ?>">
                <input type="hidden" name="accuracy" id="accuracy" value="<?php echo esc_attr($_POST['accuracy'] ?? 'google_places'); ?>">
                <input type="hidden" name="city_google" id="city_google" value="<?php echo esc_attr($_POST['city_google'] ?? ($existing_latitude ? $existing_city : '')); ?>">
                <input type="hidden" name="region_google" id="region_google" value="<?php echo esc_attr($_POST['region_google'] ?? ($existing_latitude ? $existing_region : '')); ?>">
                <input type="hidden" name="country_google" id="country_google" value="<?php echo esc_attr($_POST['country_google'] ?? ($existing_latitude ? $existing_country : '')); ?>">

                <!-- General Consent Checkbox -->
                <div class="sud-form-group sud-checkbox-group">
                    <div class="sud-checkbox">
                        <input type="checkbox" id="general_consent" name="general_consent" class="sud-checkbox-input" required>
                        <label for="general_consent" class="sud-checkbox-label">
                            I confirm that I am at least 18 years old, I have read and agree to the 
                            <a href="<?php echo site_url('/terms-of-service'); ?>" target="_blank">Terms of Service</a> and 
                            <a href="<?php echo site_url('/privacy-policy'); ?>" target="_blank">Privacy Policy</a>, 
                            and I understand that this site facilitates sugar dating and financially supportive relationships.
                            <span class="sud-required">*</span>
                        </label>
                    </div>
                    <div class="sud-error-message" id="consent-error">
                        You must agree to the terms to continue.
                    </div>
                </div>

                <button type="submit" id="continue-btn" class="sud-join-btn sud-continue-btn" disabled>Continue</button>
            </div>
            <?php wp_nonce_field('sud_signup_profile_setup_action', '_sud_signup_nonce'); ?>
        </form>
    </div>
</div>

<script>
    let autocomplete;
    let locationInitialized = false;
    let validLocationSelected = false;

    function initializeGoogleMaps() {
        if (locationInitialized) return;
        
        const locationInputGoogle = document.getElementById('location-autocomplete-input');
        if (!locationInputGoogle) return;

        const locationStatusMsg = document.getElementById('location-status-message');

        google.maps.importLibrary("places").then(({ Autocomplete }) => {
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const cityGoogleInput = document.getElementById('city_google');
            const regionGoogleInput = document.getElementById('region_google');
            const countryGoogleInput = document.getElementById('country_google');
            const googleInputErrorMsg = locationInputGoogle.parentElement.querySelector('.google-location-error');

            autocomplete = new Autocomplete(locationInputGoogle, {
                types: ['geocode'],
                fields: ["address_components", "geometry.location", "name"]
            });

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();
                
                latInput.value = '';
                lngInput.value = '';
                cityGoogleInput.value = '';
                regionGoogleInput.value = '';
                countryGoogleInput.value = '';
                validLocationSelected = false;
                
                if (googleInputErrorMsg) googleInputErrorMsg.style.display = 'none';
                locationInputGoogle.classList.remove('sud-input-error');
                if (locationStatusMsg) locationStatusMsg.textContent = '';
                locationStatusMsg.className = 'sud-location-status-message';

                if (!place || !place.geometry || !place.geometry.location) {
                    if (googleInputErrorMsg) googleInputErrorMsg.style.display = 'block';
                    locationInputGoogle.classList.add('sud-input-error');
                    checkFormValidity();
                    return;
                }
                
                // Valid place selected
                validLocationSelected = true;
                latInput.value = place.geometry.location.lat();
                lngInput.value = place.geometry.location.lng();
                
                let city = '', region = '', country = '';
                if (place.address_components) {
                    for (const component of place.address_components) {
                        const types = component.types;
                        if (types.includes('locality')) city = component.long_name;
                        else if (types.includes('administrative_area_level_1')) region = component.short_name;
                        else if (types.includes('country')) country = component.long_name;
                    }
                    if (!city && place.name && place.name !== country) city = place.name;
                } else {
                    if(place.name) city = place.name;
                }
                cityGoogleInput.value = city;
                regionGoogleInput.value = region;
                countryGoogleInput.value = country;
                locationInputGoogle.dispatchEvent(new Event('input', { bubbles: true }));
                checkFormValidity();
            });

            locationInputGoogle.addEventListener('keydown', (e) => { 
                if (e.key === 'Enter') e.preventDefault(); 
            });
            
            locationInputGoogle.addEventListener('input', (e) => {
                if (locationStatusMsg) locationStatusMsg.textContent = '';
                locationStatusMsg.className = 'sud-location-status-message';
                
                // If user clears the input, clear the coordinates too and reset validation
                if (e.target.value.trim() === '') {
                    if (latInput) latInput.value = '';
                    if (lngInput) lngInput.value = '';
                    if (cityGoogleInput) cityGoogleInput.value = '';
                    if (regionGoogleInput) regionGoogleInput.value = '';
                    if (countryGoogleInput) countryGoogleInput.value = '';
                    validLocationSelected = false;
                    checkFormValidity();
                } else {
                    // When user types, they haven't selected from suggestions yet
                    validLocationSelected = false;
                    checkFormValidity();
                }
            });
            
            locationInputGoogle.addEventListener('blur', () => {
                checkFormValidity();
            });

        }).catch(error => {
            console.error('Google Maps initialization failed:', error);
            if (locationStatusMsg) {
                locationStatusMsg.textContent = 'Location search unavailable. Please enable location or type manually.';
                locationStatusMsg.className = 'sud-location-status-message sud-location-message-error';
            }
        });
        
        locationInitialized = true;
    }

    // Auto-detect location function
    function attemptAutoLocation() {
        const locationStatusMsg = document.getElementById('location-status-message');
        const locationInput = document.getElementById('location-autocomplete-input');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        
        // Check if location is already set
        if (latInput && latInput.value && lngInput && lngInput.value) {
            return; // Already has location
        }
        
        if (!navigator.geolocation) {
            return;
        }

        if (locationStatusMsg) {
            locationStatusMsg.textContent = 'Detecting your location...';
            locationStatusMsg.className = 'sud-location-status-message sud-location-message-info';
        }

        // Use the existing location helper
        if (typeof sudGetUserLocation === 'function') {
            sudGetUserLocation(
                <?php echo json_encode(get_current_user_id()); ?>,
                '<?php echo SUD_AJAX_URL; ?>/update-location.php',
                function(result) {
                    // Success - populate the form
                    
                    if (latInput) latInput.value = result.latitude || '';
                    if (lngInput) lngInput.value = result.longitude || '';
                    
                    const cityInput = document.getElementById('city_google');
                    const regionInput = document.getElementById('region_google');
                    const countryInput = document.getElementById('country_google');
                    
                    if (cityInput) cityInput.value = result.city || '';
                    if (regionInput) regionInput.value = result.region || '';
                    if (countryInput) countryInput.value = result.country || '';
                    
                    // Set the location input display
                    if (locationInput) {
                        const locationDisplay = result.location_string || `${result.city || 'Unknown'}, ${result.country || 'Unknown'}`;
                        locationInput.value = locationDisplay;
                        locationInput.dispatchEvent(new Event('input', { bubbles: true }));
                        
                        // Mark as having value for styling
                        locationInput.classList.add('has-value');
                        const label = locationInput.parentElement.querySelector('.sud-floating-label');
                        if (label) label.classList.add('active');
                        
                        // Mark as valid location since it came from auto-detection
                        validLocationSelected = true;
                    }
                    
                    if (locationStatusMsg) {
                        locationStatusMsg.textContent = 'Location detected! You can edit it or continue.';
                        locationStatusMsg.className = 'sud-location-status-message sud-location-message-success';
                        
                        // Hide success message after 3 seconds
                        setTimeout(() => {
                            if (locationStatusMsg.textContent.includes('detected!')) {
                                locationStatusMsg.textContent = '';
                                locationStatusMsg.className = 'sud-location-status-message';
                            }
                        }, 3000);
                    }
                    
                    checkFormValidity();
                },
                function(errorType, errorMessage) {
                    // Error - fallback to manual entry
                    
                    if (locationStatusMsg) {
                        if (errorType === 'denied') {
                            locationStatusMsg.textContent = 'Location access denied. Please type your location below.';
                        } else {
                            locationStatusMsg.textContent = 'Auto-detection failed. Please type your location below.';
                        }
                        locationStatusMsg.className = 'sud-location-status-message sud-location-message-info';
                        
                        // Hide message after 5 seconds
                        setTimeout(() => {
                            if (locationStatusMsg.textContent.includes('type your location')) {
                                locationStatusMsg.textContent = '';
                                locationStatusMsg.className = 'sud-location-status-message';
                            }
                        }, 5000);
                    }
                }
            );
        }
    }

    function checkFormValidity() {
        const form = document.getElementById('profile-setup-form'); if (!form) return false;
        const continueBtn = document.getElementById('continue-btn'); const nameInput = document.getElementById('display_name'); const dobInput = document.getElementById('date_of_birth'); const latInput = document.getElementById('latitude'); const lngInput = document.getElementById('longitude'); const consentInput = document.getElementById('general_consent');
        
        const nameValid = nameInput && nameInput.value.trim() !== '';
        const dobData = dobInput ? dobInput.getAttribute('data-date') : null; const dobFormatValid = !!dobData && /^\d{4}-\d{2}-\d{2}$/.test(dobData); let ageValid = false; if (dobFormatValid) { try { const dobDate = new Date(dobData + 'T00:00:00Z'); const today = new Date(); let age = today.getUTCFullYear() - dobDate.getUTCFullYear(); const m = today.getUTCMonth() - dobDate.getUTCMonth(); if (m < 0 || (m === 0 && today.getUTCDate() < dobDate.getUTCDate())) age--; ageValid = age >= 18; } catch (e) { ageValid = false; } }
        const finalDobValid = dobFormatValid && ageValid;
        
        const latVal = latInput ? latInput.value.trim() : ''; const lngVal = lngInput ? lngInput.value.trim() : ''; 
        // Location is valid if we have coordinates AND either user selected from Google or location was pre-existing
        const hasValidCoords = latVal !== '' && lngVal !== '' && !isNaN(parseFloat(latVal)) && !isNaN(parseFloat(lngVal));
        const locationInput = document.getElementById('location-autocomplete-input');
        const hasLocationText = locationInput && locationInput.value.trim() !== '';
        const locationValid = hasValidCoords && hasLocationText && (validLocationSelected || (parseFloat(latVal) !== 0 && parseFloat(lngVal) !== 0));
        
        const consentValid = consentInput && consentInput.checked;
        
        const overallValid = nameValid && finalDobValid && locationValid && consentValid;
        if (continueBtn) { continueBtn.disabled = !overallValid; } return overallValid;
    }

    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('profile-setup-form');
        if (!form) { return; }

        const continueBtn = document.getElementById('continue-btn');
        
        // Check if location is already set from existing data
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        if (latInput && lngInput && latInput.value.trim() !== '' && lngInput.value.trim() !== '') {
            validLocationSelected = true;
        }

        const allInputs = form.querySelectorAll('.sud-form-input');
        allInputs.forEach(input => {
            const label = input.parentElement.querySelector('.sud-floating-label');
            const checkValue = () => {
                const hasFocus = input === document.activeElement;
                const hasValue = input.value.trim() !== '';
                const isActive = hasFocus || hasValue;
                if (label) label.classList.toggle('active', isActive);
                input.classList.toggle('has-value', hasValue);
                const errorMsg = input.parentElement.querySelector('.sud-error-message:not(.sud-location-status-message)');
                if (errorMsg) errorMsg.style.display = 'none';
                input.classList.remove('sud-input-error');
                const generalLocationError = form.querySelector('.sud-location-error-general');
                if (generalLocationError && input.id === 'location-autocomplete-input') { generalLocationError.style.display = 'none'; }
                const googleLocationError = form.querySelector('.google-location-error');
                if (googleLocationError && input.id === 'location-autocomplete-input') { googleLocationError.style.display = 'none'; }
            };
            checkValue();
            input.addEventListener('focus', checkValue);
            input.addEventListener('blur', checkValue);
            input.addEventListener('input', checkValue);
        });

        if (typeof $ !== 'undefined' && $.ui && $.ui.datepicker) {
            const dobInput = $("#date_of_birth");
            if (dobInput.length > 0) {
                const eighteenYearsAgo = new Date(); eighteenYearsAgo.setFullYear(eighteenYearsAgo.getFullYear() - 18);
                const ninetyYearsAgo = new Date(); ninetyYearsAgo.setFullYear(ninetyYearsAgo.getFullYear() - 90);

                function formatDisplayDate(dateObj) {
                    return dateObj ? $.datepicker.formatDate('MM d, yy', dateObj) : '';
                }

                dobInput.datepicker({
                    changeMonth: true, changeYear: true,
                    dateFormat: 'yy-mm-dd', 
                    yearRange: ninetyYearsAgo.getFullYear() + ':' + eighteenYearsAgo.getFullYear(),
                    maxDate: eighteenYearsAgo, minDate: ninetyYearsAgo,
                    showButtonPanel: false,
                    beforeShow: function(input, inst) {
                         const d = $(input).attr('data-date'); let dt = null;
                         if (d) { try { dt = $.datepicker.parseDate('yy-mm-dd', d); } catch(e){} }
                         return { defaultDate: dt };
                    },
                    onSelect: function(dateText, inst) {
                        const inputElement = this; 
                        const parentElement = inputElement.parentElement;
                        const label = parentElement ? parentElement.querySelector('.sud-floating-label') : null;

                        $(inputElement).attr('data-date', dateText);

                        try {
                            $(inputElement).val(formatDisplayDate($.datepicker.parseDate('yy-mm-dd', dateText)));
                        } catch(e) {
                            $(inputElement).val(dateText); 
                        }

                        if (label) {
                            label.classList.add('active');
                        }
                        inputElement.classList.add('has-value');

                        const errorMsg = parentElement ? parentElement.querySelector('.sud-error-message') : null;
                         if (errorMsg) errorMsg.style.display = 'none';
                         inputElement.classList.remove('sud-input-error');

                        checkFormValidity();
                    }
                });

                const initData = dobInput.attr('data-date');
                if (initData) {
                    try {
                        dobInput.val(formatDisplayDate($.datepicker.parseDate('yy-mm-dd', initData)));
                        const label = dobInput[0].parentElement.querySelector('.sud-floating-label');
                        if(label) label.classList.add('active');
                        dobInput[0].classList.add('has-value');
                    } catch(e){}
                }

                const dateIcon = form.querySelector('.sud-date-icon');
                if (dateIcon) {
                    dateIcon.addEventListener('click', (e) => {
                        e.stopPropagation();
                        if (!$('#ui-datepicker-div').is(':visible')) dobInput.datepicker('show');
                    });
                }
                dobInput.on('click', function(e) { 
                    if (!$('#ui-datepicker-div').is(':visible')) $(this).datepicker('show');
                });
            }
        }

        form.querySelectorAll('.sud-form-input:not(#location-autocomplete-input):not(#date_of_birth)').forEach(input => {
            input.addEventListener('input', checkFormValidity);
            input.addEventListener('blur', checkFormValidity);
        });

        // Add consent checkbox listener
        const consentCheckbox = document.getElementById('general_consent');
        if (consentCheckbox) {
            consentCheckbox.addEventListener('change', function() {
                const errorMsg = document.getElementById('consent-error');
                if (this.checked && errorMsg) {
                    errorMsg.style.display = 'none';
                }
                checkFormValidity();
            });
        }

        form.addEventListener('submit', function(e) {
            const isValid = checkFormValidity();
            if (!isValid) { 
                e.preventDefault(); 
                const nameInput = document.getElementById('display_name'); 
                const dobInput = document.getElementById('date_of_birth'); 
                const latInput = document.getElementById('latitude'); 
                const locationInputGoogle = document.getElementById('location-autocomplete-input'); 
                const generalLocationError = form.querySelector('.sud-location-error-general'); 
                const googleLocationError = form.querySelector('.google-location-error'); 
                if (nameInput && !nameInput.value.trim()) { 
                    const m = nameInput.parentElement.querySelector('.sud-error-message'); 
                    if(m){ m.textContent='Display Name is required.'; m.style.display='block';} 
                    nameInput.classList.add('sud-input-error'); 
                } 
                const dobData = dobInput ? dobInput.getAttribute('data-date') : null; 
                const dobFormatValid = !!dobData && /^\d{4}-\d{2}-\d{2}$/.test(dobData); 
                let ageValid = false; 
                if (dobFormatValid) { 
                    try { 
                        const dobDate = new Date(dobData + 'T00:00:00Z'); 
                        const today = new Date(); 
                        let age = today.getUTCFullYear() - dobDate.getUTCFullYear(); 
                        const m = today.getUTCMonth() - dobDate.getUTCMonth(); 
                        if (m < 0 || (m === 0 && today.getUTCDate() < dobDate.getUTCDate())) age--; 
                        ageValid = age >= 18; 
                    } catch(e){} 
                } 
                if (!dobFormatValid || !ageValid) { 
                    const m=dobInput.parentElement.querySelector('.sud-error-message'); 
                    if(m){ 
                        if(!dobFormatValid) m.textContent='Valid Date of Birth required.'; 
                        else m.textContent='Must be 18+'; m.style.display='block';
                    } 
                    dobInput.classList.add('sud-input-error'); 
                } 
                const latVal = latInput ? latInput.value.trim() : ''; 
                if (latVal === '' || isNaN(parseFloat(latVal)) || !validLocationSelected) { 
                    if (googleLocationError) {
                        googleLocationError.textContent = !validLocationSelected ? 'Please select a location from the suggestions.' : 'Please select a valid location from the suggestions.';
                        googleLocationError.style.display = 'block';
                    }
                    locationInputGoogle.classList.add('sud-input-error'); 
                    if (generalLocationError) generalLocationError.style.display = 'none'; 
                }
                const consentCheckbox = document.getElementById('general_consent'); 
                const consentError = document.getElementById('consent-error'); 
                if (consentCheckbox && !consentCheckbox.checked && consentError) { 
                    consentError.style.display = 'block'; 
                } 
            } else { 
                const dobJQ = $('#date_of_birth'); 
                const storedDob = dobJQ.attr('data-date'); 
                if (storedDob) { 
                    dobJQ.val(storedDob); 
                    if (typeof showLoader === 'function') { 
                        showLoader(); 
                    } 
                } else { 
                    e.preventDefault(); 
                    return; 
                } 
                if (continueBtn) { 
                    continueBtn.innerHTML = '<div class="loader"></div>'; 
                    continueBtn.disabled = true; 
                } 
            }
        });
        
        // Initialize Google Maps if API is available
        if (typeof google !== 'undefined' && google.maps) {
            initializeGoogleMaps();
        }
        
        // Attempt auto-location detection after a short delay
        setTimeout(() => {
            attemptAutoLocation();
        }, 500);
        
        checkFormValidity();
    });

    // Global function for Google Maps callback - must be in global scope
    function initMap() {
        initializeGoogleMaps();
    }
    
    // Ensure initMap is available globally
    window.initMap = initMap;
</script>

<style>
/* Consent checkbox specific styling */
.sud-checkbox-group .sud-checkbox-label a {
    color: var(--sud-primary) !important;
    font-weight: bold !important;
    text-decoration: none !important;
    transition: color 0.2s ease;
}

.sud-checkbox-group .sud-checkbox-label a:hover {
    color: var(--sud-primary-hover) !important;
    text-decoration: underline !important;
}

/* Error message styling for consent */
#consent-error {
    display: none;
    color: #ff4d4d;
    font-size: 14px;
    margin-top: 8px;
}

</style>

<?php
    include('templates/footer.php');
?>