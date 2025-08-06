<?php
$user_dating_style = get_user_meta($current_user->ID, 'dating_style', true);
$user_dating_styles = get_user_meta($current_user->ID, 'dating_styles', true);
$prev_step = $active_step - 1;
$prev_step = max(0, $prev_step);

if (!is_array($user_dating_styles)) {
    $user_dating_styles = array();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dating_style_submit'])) {
    $dating_styles = isset($_POST['dating_styles']) ? $_POST['dating_styles'] : array();
    
    if (!empty($dating_styles)) {
        update_user_meta($current_user->ID, 'dating_styles', $dating_styles);
        update_user_meta($current_user->ID, 'completed_step_5', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-5">
    <form method="post" id="dating-style-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">Express your dating styles</h2>
            <p class="sud-step-description">Ready to find someone who matches your vibe?<br>Let's make it happen! Tell us how you like to date.</p>
            <div class="sud-dating-styles-grid">
                <?php
                $style_options = array(
                    'animal_lovers' => '<i class="fas fa-paw"></i> Animal Lovers',
                    'arts_culture' => '<i class="fas fa-palette"></i> Arts & Culture Dates',
                    'beach_days' => '<i class="fas fa-umbrella-beach"></i> Beach Days',
                    'brunch_dates' => '<i class="fas fa-coffee"></i> Brunch Dates',
                    'clubbing' => '<i class="fas fa-music"></i> Clubbing & Partying',
                    'coffee_dates' => '<i class="fas fa-mug-hot"></i> Coffee Dates',
                    'comedy_night' => '<i class="fas fa-laugh"></i> Comedy Night',
                    'cooking_classes' => '<i class="fas fa-utensils"></i> Cooking Classes',
                    'crafting' => '<i class="fas fa-cut"></i> Crafting Workshops',
                    'dinner_dates' => '<i class="fas fa-utensils"></i> Dinner Dates',
                    'drinks' => '<i class="fas fa-glass-martini"></i> Drinks',
                    'fitness_dates' => '<i class="fas fa-dumbbell"></i> Fitness Dates',
                    'foodie_dates' => '<i class="fas fa-hamburger"></i> Foodie Dates',
                    'gaming' => '<i class="fas fa-gamepad"></i> Gaming',
                    'lunch_dates' => '<i class="fas fa-utensils"></i> Lunch Dates',
                    'luxury' => '<i class="fas fa-gem"></i> Luxury High-Tea',
                    'meet_tonight' => '<i class="fas fa-calendar-day"></i> Meet Tonight',
                    'movies' => '<i class="fas fa-film"></i> Movies & Chill',
                    'music_festivals' => '<i class="fas fa-music"></i> Music Festivals',
                    'nature' => '<i class="fas fa-leaf"></i> Nature & Outdoors',
                    'sailing' => '<i class="fas fa-ship"></i> Sailing & Water Sports',
                    'shopping' => '<i class="fas fa-shopping-bag"></i> Shopping',
                    'shows' => '<i class="fas fa-ticket-alt"></i> Shows & Concerts',
                    'spiritual' => '<i class="fas fa-pray"></i> Spiritual Journeys',
                    'travel' => '<i class="fas fa-plane"></i> Travel',
                    'wine_tasting' => '<i class="fas fa-wine-glass-alt"></i> Wine Tasting'
                );
                
                foreach ($style_options as $value => $label) {
                    $selected = in_array($value, $user_dating_styles) ? 'selected' : '';
                    echo '<div class="sud-dating-style-tag ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            <div id="selected-styles-inputs">
                <?php
                if (is_array($user_dating_styles)) {
                    foreach ($user_dating_styles as $style) {
                        echo '<input type="hidden" name="dating_styles[]" value="' . esc_attr($style) . '">';
                    }
                }
                ?>
            </div>
            <input type="hidden" name="dating_style_submit" value="1">
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
        const form = document.getElementById('dating-style-form');
        const styleTags = form.querySelectorAll('.sud-dating-style-tag'); 
        const selectedStylesContainer = form.querySelector('#selected-styles-inputs'); 

        const MAX_SELECTIONS = 5;

        if (form && styleTags.length > 0 && selectedStylesContainer) {
            styleTags.forEach(tag => {
                tag.addEventListener('click', function() {
                    const value = this.getAttribute('data-value');
                    let currentSelectedCount = selectedStylesContainer.querySelectorAll('input[name="dating_styles[]"]').length;

                    if (this.classList.contains('selected')) {
                        this.classList.remove('selected');
                        const inputToRemove = selectedStylesContainer.querySelector(`input[name="dating_styles[]"][value="${value}"]`);
                        if (inputToRemove) {
                            inputToRemove.remove();
                        }
                    } else {
                        if (currentSelectedCount < MAX_SELECTIONS) {
                            this.classList.add('selected');
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.name = 'dating_styles[]';
                            input.value = value;
                            selectedStylesContainer.appendChild(input);
                        }
                    }
                    validateAndToggleButton(form);
                });
            });
            validateAndToggleButton(form);
        }
    }); 
</script>