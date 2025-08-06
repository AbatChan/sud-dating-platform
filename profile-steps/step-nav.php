<!-- Step indicators -->
<div class="sud-step-indicators">
    <div class="sud-step-dots">
        <?php
        $user_gender = get_user_meta($current_user->ID, 'gender', true);
        $user_functional_role = get_user_meta($current_user->ID, 'functional_role', true);
        $is_receiver_role = ($user_functional_role === 'receiver');
        $skipped_receiver_steps = [1, 2, 3];
        $displayed_step_count = 0;
        
        for ($i = 0; $i < $total_steps; $i++):
        if ($is_receiver_role && in_array($i, $skipped_receiver_steps)) {
            continue;
        }
        $is_active = ($active_step == $i);
        $displayed_step_count++;
        ?>
        <div class="sud-step-dot <?php echo $is_active ? 'active' : ''; ?> <?php echo $completed_steps[$i] ? 'completed' : ''; ?>" 
             data-step="<?php echo $i; ?>" 
             <?php if ($completed_steps[$i] || $is_active): ?>
             onclick="window.location.href='<?php echo SUD_URL; ?>/profile-details?active=<?php echo $i; ?>'"
             <?php endif; ?>>
            <?php if ($completed_steps[$i] || $is_active): ?>
                <i class="fas <?php echo $step_icons[$i]; ?>"></i>
            <?php else: ?>
                <span class="dot"></span>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
    <div class="sud-step-label">
        <?php echo $step_labels[$active_step]; ?>
    </div>
</div>