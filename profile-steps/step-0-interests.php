<?php
$user_terms = get_user_meta($current_user->ID, 'relationship_terms', true);
$selectedCount = is_array($user_terms) ? count($user_terms) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['terms_submit'])) {
    $selected_terms = isset($_POST['terms']) ? $_POST['terms'] : array();
    if (!empty($selected_terms)) {
        update_user_meta($current_user->ID, 'relationship_terms', $selected_terms);
        update_user_meta($current_user->ID, 'completed_step_0', true);

        $user_gender = get_user_meta($current_user->ID, 'gender', true);
        $user_functional_role = get_user_meta($current_user->ID, 'functional_role', true); 
        $is_receiver_role = ($user_functional_role === 'receiver');
        $next_step = $is_receiver_role ? 4 : 1;

        echo "<script>window.location.href = '" . SUD_URL . "/profile-details?active=" . $next_step . "';</script>";
        exit;
    }
}

$term_descriptions = array(
    'ppm' => '<b>Pay Per Meet (PPM):</b> Financial support provided on a per-meeting basis',
    'ma' => '<b>Monthly Allowance (MA):</b> I\'ll give you monthly financial support',
    'dtf' => '<b>DTF Tonight/Right Now:</b> Looking for immediate intimate connection',
    'discreet' => '<b>Discreet:</b> Privacy and discretion are important to me',
    'high_net_worth' => '<b>Access to High Net Worth Individuals:</b> Connect with financially successful people',
    'all_ethnicities' => '<b>All Ethnicities:</b> I\'m open to date anyone',
    'exclusive' => '<b>Exclusive:</b> Seeking a monogamous arrangement',
    'friends_benefits' => '<b>Friends with Benefits:</b> Friendship with physical intimacy',
    'hookups' => '<b>Hookups:</b> Casual physical encounters',
    'in_relationship' => '<b>In a Relationship:</b> Already in a committed relationship',
    'lgbtq' => '<b>LGBTQ Friendly:</b> Welcoming to all gender identities and sexual orientations',
    'marriage' => '<b>Marriage:</b> Looking for a lifelong partnership',
    'mentorship' => '<b>Mentorship:</b> Offering guidance and career advice',
    'no_strings' => '<b>No Strings Attached:</b> Casual arrangement without emotional commitment',
    'open_relationship' => '<b>Open Relationship:</b> Non-exclusive romantic or sexual relationships',
    'passport_ready' => '<b>Passport Ready:</b> Available for international travel',
    'platonic' => '<b>Platonic:</b> Non-physical relationship based on companionship',
    'serious' => '<b>Serious Relationship:</b> Seeking long-term commitment',
    'transgender' => '<b>Transgender Friendly:</b> Open to dating transgender individuals',
    'travel_companion' => '<b>Travel Companion:</b> Looking for someone to travel with',
    'travel_to_you' => '<b>Travel To You:</b> Willing to visit your location',
    'weekly_allowance' => '<b>Weekly Allowance (WA):</b> I\'ll provide financial support on a weekly basis'
);

// Determine the next step for skip functionality
$user_gender = get_user_meta($current_user->ID, 'gender', true);
$user_functional_role = get_user_meta($current_user->ID, 'functional_role', true); 
$is_receiver_role = ($user_functional_role === 'receiver');
$next_step = $is_receiver_role ? 4 : 1;
?>

<div class="sud-step-content" id="step-0">
    <form method="post" id="terms-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What are your<br>Terms of Relationship™?</h2>
            <p class="sud-step-description">Terms of Relationship™ (TOR) lets you control who you want to meet, and the relationship you want.</p>
            <p class="sud-step-instruction">You may select up to 5 options.</p>
            <div class="sud-instruction-box" id="term-description">
                Tap on the tags to see the details
            </div>
            <div class="sud-terms-container">
                <div class="sud-terms-grid">
                    <!-- Row 1 -->
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('ppm', $user_terms)) ? 'selected' : ''; ?>" data-value="ppm">
                        Pay Per Meet (PPM)
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('ma', $user_terms)) ? 'selected' : ''; ?>" data-value="ma">
                        Monthly Allowance (MA)
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('dtf', $user_terms)) ? 'selected' : ''; ?>" data-value="dtf">
                        DTF Tonight/Right Now
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('discreet', $user_terms)) ? 'selected' : ''; ?>" data-value="discreet">
                        Discreet
                    </div>

                    <!-- Row 2 -->
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('high_net_worth', $user_terms)) ? 'selected' : ''; ?>" data-value="high_net_worth">
                        Access to High Net Worth Individuals
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('all_ethnicities', $user_terms)) ? 'selected' : ''; ?>" data-value="all_ethnicities">
                        All Ethnicities
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('exclusive', $user_terms)) ? 'selected' : ''; ?>" data-value="exclusive">
                        Exclusive
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('friends_benefits', $user_terms)) ? 'selected' : ''; ?>" data-value="friends_benefits">
                        Friends with Benefits
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('hookups', $user_terms)) ? 'selected' : ''; ?>" data-value="hookups">
                        Hookups
                    </div>

                    <!-- Row 3 -->
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('in_relationship', $user_terms)) ? 'selected' : ''; ?>" data-value="in_relationship">
                        In a Relationship
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('lgbtq', $user_terms)) ? 'selected' : ''; ?>" data-value="lgbtq">
                        LGBTQ Friendly
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('marriage', $user_terms)) ? 'selected' : ''; ?>" data-value="marriage">
                        Marriage
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('mentorship', $user_terms)) ? 'selected' : ''; ?>" data-value="mentorship">
                        Mentorship
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('no_strings', $user_terms)) ? 'selected' : ''; ?>" data-value="no_strings">
                        No Strings Attached
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('open_relationship', $user_terms)) ? 'selected' : ''; ?>" data-value="open_relationship">
                        Open Relationship
                    </div>

                    <!-- Row 4 -->
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('passport_ready', $user_terms)) ? 'selected' : ''; ?>" data-value="passport_ready">
                        Passport Ready
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('platonic', $user_terms)) ? 'selected' : ''; ?>" data-value="platonic">
                        Platonic
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('serious', $user_terms)) ? 'selected' : ''; ?>" data-value="serious">
                        Serious Relationship
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('transgender', $user_terms)) ? 'selected' : ''; ?>" data-value="transgender">
                        Transgender Friendly
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('travel_companion', $user_terms)) ? 'selected' : ''; ?>" data-value="travel_companion">
                        Travel Companion
                    </div>

                    <!-- Row 5 -->
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('travel_to_you', $user_terms)) ? 'selected' : ''; ?>" data-value="travel_to_you">
                        Travel To You
                    </div>
                    <div class="sud-term-tag <?php echo (is_array($user_terms) && in_array('weekly_allowance', $user_terms)) ? 'selected' : ''; ?>" data-value="weekly_allowance">
                        Weekly Allowance (WA)
                    </div>
                </div>

                <!-- Hidden inputs for selected terms -->
                <div id="selected-terms-inputs">
                    <?php
                    if (is_array($user_terms)) {
                        foreach ($user_terms as $term) {
                            echo '<input type="hidden" name="terms[]" value="' . esc_attr($term) . '">';
                        }
                    }
                    ?>
                </div>
            </div>
        </div>
        <input type="hidden" name="terms_submit" value="1">
        <?php
            if (isset($active_step)) {
                wp_nonce_field( 'sud_profile_step_' . $active_step . '_action', '_sud_step_nonce' );
            }
        ?>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const termDescriptions = <?php echo json_encode($term_descriptions); ?>;
        const descriptionBox = document.getElementById('term-description');
        const termTags = document.querySelectorAll('.sud-term-tag');
        const selectedTermsContainer = document.getElementById('selected-terms-inputs');
        const termsForm = document.getElementById('terms-form');

        let lastClickedTag = null;

        termTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                lastClickedTag = value;

                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                    const inputToRemove = selectedTermsContainer.querySelector(`input[name="terms[]"][value="${value}"]`); // More specific selector
                    if (inputToRemove) {
                        inputToRemove.remove();
                    }
                } else {
                    const currentCount = selectedTermsContainer.querySelectorAll('input[name="terms[]"]').length;
                    if (currentCount < 5) {
                        this.classList.add('selected');
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'terms[]';
                        input.value = value;
                        selectedTermsContainer.appendChild(input);
                    }
                }
                
                if (descriptionBox && termDescriptions[value]) {
                    descriptionBox.innerHTML = termDescriptions[value];
                }
                validateAndToggleButton(termsForm);
            });

            tag.addEventListener('mouseenter', function() {
                if (!lastClickedTag && descriptionBox) {
                    const value = this.getAttribute('data-value');
                    if (termDescriptions[value]) {
                        descriptionBox.innerHTML = termDescriptions[value];
                    }
                }
            });
        });

        const termsGrid = document.querySelector('.sud-terms-grid');
        if (termsGrid && descriptionBox) {
            termsGrid.addEventListener('mouseleave', function() {
                if (!lastClickedTag) {
                    descriptionBox.innerHTML = 'Tap on the tags to see the details';
                }
            });
        }
        validateAndToggleButton(termsForm);
    });
</script>