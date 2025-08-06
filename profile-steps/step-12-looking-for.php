<?php
$user_looking_for = get_user_meta($current_user->ID, 'profile_looking_for', true);
$age_min = get_user_meta($current_user->ID, 'looking_for_age_min', true) ?: 18;
$age_max = get_user_meta($current_user->ID, 'looking_for_age_max', true) ?: 70;
$ethnicities_saved = get_user_meta($current_user->ID, 'looking_for_ethnicities', true);

$is_any_selected = false;
if (is_array($ethnicities_saved) && count($ethnicities_saved) === 1 && in_array(SUD_ANY_ETHNICITY_KEY, $ethnicities_saved, true)) {
    $is_any_selected = true;
    $ethnicities_specific = array(); 
} elseif (is_array($ethnicities_saved)) {
    $ethnicities_specific = array_filter($ethnicities_saved, function($value) {
        return $value !== SUD_ANY_ETHNICITY_KEY;
    });

    $valid_specific_ethnicities = [];
    foreach ($ethnicities_specific as $combo) {
        if (strpos($combo, '|') !== false) {
            list($eth_part, $race_part) = explode('|', $combo, 2);
            if (!empty($eth_part) && !empty($race_part)) {
                $valid_specific_ethnicities[] = $combo;
            }
        }
    }
    $ethnicities_specific = $valid_specific_ethnicities;
} else {
    $ethnicities_specific = array(); 
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['looking_for_submit'])) {
    $age_min = isset($_POST['age_min']) ? intval($_POST['age_min']) : 18;
    $age_max = isset($_POST['age_max']) ? intval($_POST['age_max']) : 70;

    if (isset($_POST['any_ethnicity']) && $_POST['any_ethnicity'] === '1') {
        $ethnicities_to_save = [SUD_ANY_ETHNICITY_KEY];
    } else {
        $ethnicities_to_save = isset($_POST['ethnicities']) ? $_POST['ethnicities'] : array();
        if (!is_array($ethnicities_to_save)) {
            $ethnicities_to_save = [];
        }
    }

    update_user_meta($current_user->ID, 'looking_for_age_min', $age_min);
    update_user_meta($current_user->ID, 'looking_for_age_max', $age_max);
    update_user_meta($current_user->ID, 'looking_for_ethnicities', $ethnicities_to_save); 
    update_user_meta($current_user->ID, 'completed_step_12', true);

    echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
    exit;
}

$ethnicity_options_map = array(
    'african' => 'African', 'asian' => 'Asian', 'caucasian' => 'Caucasian',
    'hispanic' => 'Hispanic/Latino', 'indian' => 'Indian', 'middle_eastern' => 'Middle Eastern',
    'mixed' => 'Mixed', 'native_american' => 'Native American', 'pacific_islander' => 'Pacific Islander',
    'other' => 'Other'
);
$race_options_map = array(
    'american' => 'American', 'australian' => 'Australian', 'austrian' => 'Austrian',
    'british' => 'British', 'bulgarian' => 'Bulgarian', 'canadian' => 'Canadian',
    'croatian' => 'Croatian', 'czech' => 'Czech', 'danish' => 'Danish',
    'dutch' => 'Dutch', 'european' => 'European', 'finnish' => 'Finnish',
    'french' => 'French', 'german' => 'German', 'greek' => 'Greek',
    'hungarian' => 'Hungarian', 'irish' => 'Irish', 'italian' => 'Italian',
    'new_zealander' => 'New Zealander', 'norwegian' => 'Norwegian', 'polish' => 'Polish',
    'portuguese' => 'Portuguese', 'romanian' => 'Romanian', 'russian' => 'Russian',
    'scottish' => 'Scottish', 'serbian' => 'Serbian', 'slovak' => 'Slovak',
    'spanish' => 'Spanish', 'swedish' => 'Swedish', 'swiss' => 'Swiss',
    'ukrainian' => 'Ukrainian', 'welsh' => 'Welsh'
);

?>

<div class="sud-step-content" id="step-12">
    <form method="post" id="looking-for-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">Tell us who<br>you're looking for</h2>
            <p class="sud-step-description">Refine your preferred age range, location & ethnicity. We'll make sure you meet the right person.</p>
            <div class="sud-looking-for-grid">
                <!-- Age Range Section -->
                <div class="sud-looking-for-card">
                    <h3 class="sud-card-title">Age range</h3>
                    <div class="sud-age-slider-container">
                        <div id="age-slider" class="sud-age-slider"></div>
                        <div class="sud-age-display">
                            <span id="age-display"><?php echo $age_min; ?> - <?php echo $age_max; ?> yo</span>
                        </div>
                    </div>
                    <input type="hidden" name="age_min" id="age-min" value="<?php echo esc_attr($age_min); ?>">
                    <input type="hidden" name="age_max" id="age-max" value="<?php echo esc_attr($age_max); ?>">
                </div>

                <!-- Ethnicity Section -->
                <div class="sud-looking-for-card">
                    <h3 class="sud-card-title">Ethnicity</h3>
                    <div class="sud-ethnicity-container">
                        <div class="sud-checkbox" style="margin-bottom: 20px;">
                             <input type="checkbox" id="any_ethnicity_checkbox" name="any_ethnicity" value="1" class="sud-checkbox-input" <?php checked($is_any_selected); ?>>
                             <label for="any_ethnicity_checkbox" class="sud-checkbox-label">Open to Any Ethnicity</label>
                         </div>
                        <div id="specific-ethnicity-ui" class="<?php echo $is_any_selected ? 'sud-hidden' : ''; ?>">
                            <!-- Ethnicity Dropdown -->
                            <div class="sud-dropdown-container sud-input-floating">
                                <div class="sud-custom-select" id="ethnicity-select">
                                    <div class="sud-select-trigger sud-form-input">
                                        <span class="sud-select-value"></span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <label class="sud-floating-label">Select ethnicity</label>
                                    <div class="sud-options">
                                        <?php
                                        foreach ($ethnicity_options_map as $value => $label) {
                                            echo '<div class="sud-option" data-value="' . esc_attr($value) . '">' . esc_html($label) . '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Race Dropdown (initially hidden) -->
                            <div class="sud-dropdown-container sud-input-floating" id="race-dropdown" style="display: none;">
                                <div class="sud-custom-select" id="race-select">
                                    <div class="sud-select-trigger sud-form-input">
                                        <span class="sud-select-value"></span>
                                        <i class="fas fa-chevron-down"></i>
                                    </div>
                                    <label class="sud-floating-label">Select races</label>
                                    <div class="sud-options">
                                        <?php

                                        foreach ($race_options_map as $value => $label) {
                                            echo '<div class="sud-option" data-value="' . esc_attr($value) . '">' . esc_html($label) . '</div>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Selected Ethnicity/Race Tags -->
                            <div class="sud-selected-tags" id="selected-ethnicity-tags">
                                <?php

                                if (!$is_any_selected && !empty($ethnicities_specific)) {
                                    foreach ($ethnicities_specific as $combo) {
                                        if (strpos($combo, '|') !== false) {
                                             list($ethnicity, $race) = explode('|', $combo, 2);
                                             if (isset($ethnicity_options_map[$ethnicity]) && isset($race_options_map[$race])) {
                                                 echo '<div class="sud-tag" data-ethnicity="' . esc_attr($ethnicity) . '" data-race="' . esc_attr($race) . '">';
                                                 echo esc_html($ethnicity_options_map[$ethnicity]) . ', ' . esc_html($race_options_map[$race]);
                                                 echo '<span class="sud-tag-remove"><i class="fas fa-times"></i></span>';
                                                 echo '</div>';
                                             }
                                         }
                                    }
                                }
                                ?>
                            </div>

                            <!-- Hidden inputs for form submission -->
                            <div id="selected-ethnicities-container">
                                <?php

                                if (!$is_any_selected && !empty($ethnicities_specific)) {
                                    foreach ($ethnicities_specific as $combo) {
                                         if (strpos($combo, '|') !== false) {
                                            echo '<input type="hidden" name="ethnicities[]" value="' . esc_attr($combo) . '">';
                                        }
                                    }
                                }
                                ?>
                            </div>
                             <p class="sud-dropdown-hint">Select up to 3 specific ethnicity-race combinations</p>
                        </div>
                    </div>
                </div>
            </div>
            <input type="hidden" name="looking_for_submit" value="1">
            <?php
                if (isset($active_step)) {
                    wp_nonce_field( 'sud_profile_step_' . $active_step . '_action', '_sud_step_nonce' );
                }
            ?>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const ageSlider = document.getElementById('age-slider');
        const ageDisplay = document.getElementById('age-display');
        const ageMinInput = document.getElementById('age-min');
        const ageMaxInput = document.getElementById('age-max');
        
        let ageMin = parseInt(ageMinInput.value) || 18;
        let ageMax = parseInt(ageMaxInput.value) || 70;

        const minHandle = document.createElement('div');
        minHandle.className = 'sud-slider-handle sud-slider-handle-min';
        minHandle.dataset.handle = 'min';
        
        const maxHandle = document.createElement('div');
        maxHandle.className = 'sud-slider-handle sud-slider-handle-max';
        maxHandle.dataset.handle = 'max';
        
        const connectElement = document.createElement('div');
        connectElement.className = 'sud-slider-connect';
        
        ageSlider.appendChild(minHandle);
        ageSlider.appendChild(maxHandle);
        ageSlider.appendChild(connectElement);
        
        const MIN_AGE = 18;
        const MAX_AGE = 70;
        const RANGE = MAX_AGE - MIN_AGE;
        
        function positionFromAge(age) {
            return ((age - MIN_AGE) / RANGE) * 100;
        }
        
        function ageFromPosition(position) {
            return Math.round(MIN_AGE + (position / 100) * RANGE);
        }
        
        function updateHandlePositions() {
            const minPos = positionFromAge(ageMin);
            const maxPos = positionFromAge(ageMax);
            
            minHandle.style.left = minPos + '%';
            maxHandle.style.left = maxPos + '%';
            connectElement.style.left = minPos + '%';
            connectElement.style.width = (maxPos - minPos) + '%';
            
            ageDisplay.textContent = ageMin + ' - ' + ageMax + ' yo';
            ageMinInput.value = ageMin;
            ageMaxInput.value = ageMax;
        }
        
        updateHandlePositions();
        
        let isDragging = false;
        let currentHandle = null;
        
        function startDrag(e, handle) {
            e.preventDefault();
            isDragging = true;
            currentHandle = handle;
            document.addEventListener('mousemove', drag);
            document.addEventListener('mouseup', stopDrag);
            document.addEventListener('touchmove', drag);
            document.addEventListener('touchend', stopDrag);

            document.body.style.userSelect = 'none';
        }
        
        function drag(e) {
            if (!isDragging) return;
            
            let clientX;
            if (e.touches) {
                clientX = e.touches[0].clientX;
            } else {
                clientX = e.clientX;
            }
            
            const rect = ageSlider.getBoundingClientRect();
            const offsetX = clientX - rect.left;
            let percent = Math.max(0, Math.min(100, (offsetX / rect.width) * 100));
            
            const newAge = ageFromPosition(percent);
            
            if (currentHandle.dataset.handle === 'min') {
                ageMin = Math.min(ageMax - 1, newAge);
            } else {
                ageMax = Math.max(ageMin + 1, newAge);
            }
            updateHandlePositions();
        }
        
        function stopDrag() {
            isDragging = false;
            document.removeEventListener('mousemove', drag);
            document.removeEventListener('mouseup', stopDrag);
            document.removeEventListener('touchmove', drag);
            document.removeEventListener('touchend', stopDrag);
            document.body.style.userSelect = '';
        }

        minHandle.addEventListener('mousedown', function(e) {
            startDrag(e, this);
        });
        
        maxHandle.addEventListener('mousedown', function(e) {
            startDrag(e, this);
        });
        
        minHandle.addEventListener('touchstart', function(e) {
            startDrag(e, this);
        });
        
        maxHandle.addEventListener('touchstart', function(e) {
            startDrag(e, this);
        });

        window.addEventListener('resize', updateHandlePositions);
        
        const anyEthnicityCheckbox = document.getElementById('any_ethnicity_checkbox');
        const specificEthnicityUI = document.getElementById('specific-ethnicity-ui');
        const ethnicitySelect = document.getElementById('ethnicity-select');
        const raceDropdown = document.getElementById('race-dropdown');
        const raceSelect = document.getElementById('race-select');
        const ethnicityTrigger = ethnicitySelect.querySelector('.sud-select-trigger');
        const raceTrigger = raceSelect.querySelector('.sud-select-trigger');
        const ethnicityOptions = ethnicitySelect.querySelectorAll('.sud-option');
        const raceOptions = raceSelect.querySelectorAll('.sud-option');
        const selectedTags = document.getElementById('selected-ethnicity-tags');
        const hiddenContainer = document.getElementById('selected-ethnicities-container');
        const MAX_SELECTIONS = 3;
        
        // Track current state
        let currentEthnicity = '';
        let currentEthnicityLabel = '';
        let currentSelections = [];
        
        function toggleSpecificUI(isAnyChecked) {
            if (isAnyChecked) {
                specificEthnicityUI.classList.add('sud-hidden');
                currentSelections = [];
                selectedTags.innerHTML = '';
                hiddenContainer.innerHTML = '';

                ethnicityTrigger.querySelector('.sud-select-value').textContent = ' ';
                raceTrigger.querySelector('.sud-select-value').textContent = ' ';
                ethnicityTrigger.classList.remove('has-value');
                raceTrigger.classList.remove('has-value');
                raceDropdown.style.display = 'none';
            } else {
                specificEthnicityUI.classList.remove('sud-hidden');
            }
            updateAvailableRaces(); 
        }

        if (anyEthnicityCheckbox) {
            anyEthnicityCheckbox.addEventListener('change', function() {
                toggleSpecificUI(this.checked);
            });
        }

        if (!anyEthnicityCheckbox || !anyEthnicityCheckbox.checked) {
            document.querySelectorAll('#selected-ethnicity-tags .sud-tag').forEach(tag => {
                currentSelections.push({
                    ethnicity: tag.dataset.ethnicity,
                    race: tag.dataset.race
                });

                tag.querySelector('.sud-tag-remove').addEventListener('click', function() {
                    const ethnicity = tag.dataset.ethnicity;
                    const race = tag.dataset.race;
                    removeSelection(ethnicity, race);
                    if (anyEthnicityCheckbox) anyEthnicityCheckbox.checked = false;
                    toggleSpecificUI(false);
                });
            });
        } else {
            toggleSpecificUI(true);
        }

        document.querySelectorAll('#selected-ethnicity-tags .sud-tag').forEach(tag => {
            currentSelections.push({
                ethnicity: tag.dataset.ethnicity,
                race: tag.dataset.race
            });
            
            tag.querySelector('.sud-tag-remove').addEventListener('click', function() {
                const ethnicity = tag.dataset.ethnicity;
                const race = tag.dataset.race;
                removeSelection(ethnicity, race);
            });
        });
        
        if (currentSelections.length > 0) {
            ethnicityTrigger.classList.add('has-value');
            if (raceDropdown.style.display !== 'none') {
                raceTrigger.classList.add('has-value');
            }
        }

        ethnicityTrigger.addEventListener('click', function(e) {
             if (anyEthnicityCheckbox && anyEthnicityCheckbox.checked) return;
            e.stopPropagation();
            ethnicitySelect.classList.toggle('open');
            raceSelect.classList.remove('open');
            this.classList.add('has-value');
        });

        raceTrigger.addEventListener('click', function(e) {
            if (anyEthnicityCheckbox && anyEthnicityCheckbox.checked) return;
            e.stopPropagation();
            raceSelect.classList.toggle('open');
            ethnicitySelect.classList.remove('open');
            this.classList.add('has-value');
        });
        
        document.addEventListener('click', function(e) {
            if (!ethnicitySelect.contains(e.target)) ethnicitySelect.classList.remove('open');
            if (!raceSelect.contains(e.target)) raceSelect.classList.remove('open');
        });

        ethnicityOptions.forEach(option => {
            option.addEventListener('click', function() {
                if (this.classList.contains('disabled') || (anyEthnicityCheckbox && anyEthnicityCheckbox.checked)) return;
                currentEthnicity = this.dataset.value;
                currentEthnicityLabel = this.textContent.trim();
                ethnicityTrigger.querySelector('.sud-select-value').textContent = currentEthnicityLabel;
                ethnicityTrigger.classList.add('has-value');
                raceDropdown.style.display = 'block';
                ethnicitySelect.classList.remove('open');
                updateAvailableRaces();
            });
        });

        raceOptions.forEach(option => {
            option.addEventListener('click', function() {
                if (this.classList.contains('disabled') || (anyEthnicityCheckbox && anyEthnicityCheckbox.checked)) return;
                const raceValue = this.dataset.value;
                const raceLabel = this.textContent.trim();
                addSelection(currentEthnicity, raceValue, currentEthnicityLabel, raceLabel);

                ethnicityTrigger.querySelector('.sud-select-value').textContent = ' ';
                raceTrigger.querySelector('.sud-select-value').textContent = ' ';

                if (currentSelections.length === 0) ethnicityTrigger.classList.remove('has-value');
                else ethnicityTrigger.classList.add('has-value');
                raceTrigger.classList.remove('has-value');
                raceDropdown.style.display = 'none';
                raceSelect.classList.remove('open');
            });
        });
        
        function addSelection(ethnicity, race, ethnicityLabel, raceLabel) {
            if (anyEthnicityCheckbox && anyEthnicityCheckbox.checked) {
                anyEthnicityCheckbox.checked = false;
                toggleSpecificUI(false);
            }

            if (currentSelections.length >= MAX_SELECTIONS) return;
            const exists = currentSelections.some(s => s.ethnicity === ethnicity && s.race === race);
            if (exists) return;

            currentSelections.push({ ethnicity: ethnicity, race: race });

            const tag = document.createElement('div');
            tag.className = 'sud-tag';
            tag.dataset.ethnicity = ethnicity;
            tag.dataset.race = race;
            tag.innerHTML = `${ethnicityLabel}, ${raceLabel}<span class="sud-tag-remove"><i class="fas fa-times"></i></span>`;
            selectedTags.appendChild(tag);

            tag.querySelector('.sud-tag-remove').addEventListener('click', function() {
                removeSelection(ethnicity, race);
                if (anyEthnicityCheckbox) anyEthnicityCheckbox.checked = false;
                toggleSpecificUI(false);
            });

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'ethnicities[]';
            input.value = `${ethnicity}|${race}`;
            hiddenContainer.appendChild(input);

            updateAvailableRaces();
            if (ethnicityTrigger) ethnicityTrigger.classList.add('has-value');
        }

        function removeSelection(ethnicity, race) {
            currentSelections = currentSelections.filter(s => !(s.ethnicity === ethnicity && s.race === race));
            const tag = selectedTags.querySelector(`[data-ethnicity="${ethnicity}"][data-race="${race}"]`);
            if (tag) tag.remove();
            const input = hiddenContainer.querySelector(`input[value="${ethnicity}|${race}"]`);
            if (input) input.remove();
            updateAvailableRaces();

            if (currentSelections.length === 0 && ethnicityTrigger && (!anyEthnicityCheckbox || !anyEthnicityCheckbox.checked)) {
                ethnicityTrigger.classList.remove('has-value');
            }
        }

        function updateAvailableRaces() {
            const isAnyChecked = anyEthnicityCheckbox && anyEthnicityCheckbox.checked;
            const reachedMaxSpecific = currentSelections.length >= MAX_SELECTIONS;

            ethnicityOptions.forEach(option => {
                option.classList.toggle('disabled', isAnyChecked || reachedMaxSpecific);
            });

            raceOptions.forEach(option => {
                option.classList.toggle('disabled', isAnyChecked || reachedMaxSpecific);
            });

            if (!isAnyChecked && currentEthnicity) {
                raceOptions.forEach(option => {
                    const race = option.dataset.value;
                    const exists = currentSelections.some(s => s.ethnicity === currentEthnicity && s.race === race);
                    if (exists) {
                        option.classList.add('disabled');
                    }
                });
            }
        }

        const form = document.getElementById('looking-for-form');
        if (form) { validateAndToggleButton(form); }

        if (anyEthnicityCheckbox) {
            toggleSpecificUI(anyEthnicityCheckbox.checked);
        }
    });
</script>