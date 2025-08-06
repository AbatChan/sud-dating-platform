<?php 

if (!isset($is_receiver_role)) {
    $current_user_id_nav = get_current_user_id();
    $user_functional_role_nav = $current_user_id_nav ? get_user_meta($current_user_id_nav, 'functional_role', true) : '';
    $is_receiver_role = ($user_functional_role_nav === 'receiver');
}
if (!isset($total_steps)) {
   $total_steps = 14;
}
$skipped_steps = $is_receiver_role ? [1, 2, 3] : [];

?>
<div class="sud-step-navigation">
    <div class="sud-nav-buttons">
        <?php
        if ($active_step > 0):
            $prev_step = $active_step - 1;
            while (in_array($prev_step, $skipped_steps) && $prev_step > 0) {
                $prev_step--;
            }
            $prev_step = max(0, $prev_step);
            ?>
            <a href="<?php echo SUD_URL; ?>/profile-details?active=<?php echo $prev_step; ?>" class="sud-prev-btn">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        <?php endif; ?>

        <?php
        $effective_last_step = $total_steps - 1;
        if ($is_receiver_role) {
            for ($last_idx = $total_steps - 1; $last_idx >= 0; $last_idx--) {
                if (!in_array($last_idx, $skipped_steps)) {
                    $effective_last_step = $last_idx;
                    break;
                }
            }
        }
        $is_on_last_visible_step = ($active_step == $effective_last_step);


        if ($is_on_last_visible_step): ?>
            <button type="button" id="desktop-action-btn" class="sud-next-btn desktop-btn active">
                Finish
            </button>
        <?php elseif ($active_step == 0):
            $user_terms_count = is_array($user_terms) ? count($user_terms) : 0;
            $is_step_0_valid = ($user_terms_count >= 3);
        ?>
            <button type="button" id="desktop-action-btn" class="sud-next-btn desktop-btn <?php echo $is_step_0_valid ? 'active' : ''; ?>" <?php echo !$is_step_0_valid ? 'disabled' : ''; ?>>
                <span id="desktop-selection-counter"><?php echo $user_terms_count; ?>/5 Selected</span>
            </button>
        <?php elseif ($active_step == 5):
            $user_styles_count = is_array($user_dating_styles) ? count($user_dating_styles) : 0;
            $is_step_5_valid = ($user_styles_count > 0);
            $max_styles = 5;
        ?>
            <button type="button" id="desktop-action-btn" class="sud-next-btn desktop-btn <?php echo $is_step_5_valid ? 'active' : ''; ?>" <?php echo !$is_step_5_valid ? 'disabled' : ''; ?>>
                <span id="desktop-selection-counter"><?php echo $user_styles_count; ?>/<?php echo $max_styles; ?> Selected</span>
            </button>
        <?php else: ?>
            <button type="button" id="desktop-action-btn" class="sud-next-btn desktop-btn active">
                Continue
            </button>
        <?php endif; ?>
    </div>

    <?php
        $no_skip_steps = array(0, $effective_last_step);
        if (!in_array($active_step, $no_skip_steps) || ($active_step == $effective_last_step && $active_step != 13) ):
        $skip_target_step = $active_step + 1;
        while (in_array($skip_target_step, $skipped_steps) && $skip_target_step < $total_steps) {
            $skip_target_step++;
        }
        // Check for required steps before allowing skip all
        $is_step0_done = get_user_meta($current_user->ID, 'completed_step_0', true);
        $relationship_terms = get_user_meta($current_user->ID, 'relationship_terms', true);
        $has_valid_terms = is_array($relationship_terms) && count($relationship_terms) >= 3;
        
        $profile_pic_exists_nav = get_user_meta($current_user->ID, 'profile_picture', true);
        $is_photo_step_done_nav = get_user_meta($current_user->ID, 'completed_step_13', true);

        if (!$is_step0_done || !$has_valid_terms) {
            $skip_all_href = SUD_URL . '/profile-details?active=0&step_error=' . urlencode('Please complete your relationship terms first.');
        } elseif (!$profile_pic_exists_nav || !$is_photo_step_done_nav) {
            $skip_all_href = SUD_URL . '/profile-details?active=13&from_skip_all=1&error_message=' . urlencode('Please upload a profile picture to complete setup.');
        } else {
            $skip_all_href = SUD_URL . '/pages/swipe?skipped_setup=1';
        }
        
        $skip_target_step = min($skip_target_step, $effective_last_step);
    ?>
    <div class="sud-skip-option">
        <?php
        if ($active_step != 13): ?>
        <a href="<?php echo SUD_URL; ?>/profile-details?active=<?php echo $skip_target_step; ?>" class="sud-skip-link">Skip</a>
        <?php endif; ?>

        <a href="<?php echo esc_url($skip_all_href); ?>" class="sud-skip-all-link">
            Skip All
        </a>
    </div>
    <?php endif; ?>
</div>