<?php
$user_race = get_user_meta($current_user->ID, 'race', true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['race_submit'])) {
    $race = isset($_POST['race']) ? $_POST['race'] : '';
    
    if (!empty($race)) {
        update_user_meta($current_user->ID, 'race', $race);
        update_user_meta($current_user->ID, 'completed_step_8', true);

        echo "<script>window.location.href = 'profile-details?active=" . ($active_step + 1) . "';</script>";
        exit;
    }
}
?>

<div class="sud-step-content" id="step-8">
    <form method="post" id="race-form">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">What's your race?</h2>
            
            <div class="sud-race-options">
                <?php
                $race_options = array(
                    'american' => 'American',
                    'australian' => 'Australian',
                    'austrian' => 'Austrian',
                    'british' => 'British',
                    'bulgarian' => 'Bulgarian',
                    'canadian' => 'Canadian',
                    'croatian' => 'Croatian',
                    'czech' => 'Czech',
                    'danish' => 'Danish',
                    'dutch' => 'Dutch',
                    'european' => 'European',
                    'finnish' => 'Finnish',
                    'french' => 'French',
                    'german' => 'German',
                    'greek' => 'Greek',
                    'hungarian' => 'Hungarian',
                    'irish' => 'Irish',
                    'italian' => 'Italian',
                    'new_zealander' => 'New Zealander',
                    'norwegian' => 'Norwegian',
                    'polish' => 'Polish',
                    'portuguese' => 'Portuguese',
                    'romanian' => 'Romanian',
                    'russian' => 'Russian',
                    'scottish' => 'Scottish',
                    'serbian' => 'Serbian',
                    'slovak' => 'Slovak',
                    'spanish' => 'Spanish',
                    'swedish' => 'Swedish',
                    'swiss' => 'Swiss',
                    'ukrainian' => 'Ukrainian',
                    'welsh' => 'Welsh'
                );
                
                foreach ($race_options as $value => $label) {
                    $selected = ($user_race == $value) ? 'selected' : '';
                    echo '<div class="sud-race-tag ' . $selected . '" data-value="' . $value . '">' . $label . '</div>';
                }
                ?>
            </div>
            <input type="hidden" name="race" id="selected-race" value="<?php echo esc_attr($user_race); ?>">
            <input type="hidden" name="race_submit" value="1">
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
        const form = document.getElementById('race-form');
        const options = document.querySelectorAll('.sud-race-tag');
        const hiddenInput = document.getElementById('selected-race');

        if (form && options.length > 0 && hiddenInput) {
            options.forEach(option => {
                option.addEventListener('click', function() {
                    options.forEach(opt => opt.classList.remove('selected'));
                    this.classList.add('selected');
                    hiddenInput.value = this.getAttribute('data-value');

                    validateAndToggleButton(form);
                });
            });
            validateAndToggleButton(form);
        }
    });
</script>