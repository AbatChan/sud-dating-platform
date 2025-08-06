<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');
require_once(dirname(__FILE__, 2) . '/includes/user-functions.php');
require_once(dirname(__FILE__, 2) . '/includes/profile-functions.php');

require_login();

$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Use centralized validation for all profile requirements (no photo requirement)
validate_core_profile_requirements($user_id, 'profile-edit');
$display_name = $current_user->display_name;

$profile_picture_id = get_user_meta($user_id, 'profile_picture', true);
$profile_pic_url = !empty($profile_picture_id) ?
    wp_get_attachment_image_url($profile_picture_id, 'medium') :
    SUD_IMG_URL . '/default-profile.jpg';

$date_of_birth = get_user_meta($user_id, 'date_of_birth', true);
$age = !empty($date_of_birth) ? calculate_age($date_of_birth) : '';

$gender = get_user_meta($user_id, 'gender', true);

$user_data = get_complete_user_profile($user_id);
if (!$user_data) {
    $user_data = [
        'id' => $user_id,
        'name' => $display_name,
        'email' => $current_user->user_email,
        'user_login' => $current_user->user_login,
        'age' => $age,
    ];
}

$fallbacks = [
    'location' => 'Not specified', 'city' => '', 'region' => '', 'country' => '',
    'height' => '', 'body_type' => '', 'ethnicity' => '', 'race' => '', 'eye_color' => '', 'hair_color' => '',
    'occupation' => '', 'industry' => '', 'education' => '', 'relationship_status' => '', 'smoke' => '', 'drink' => '',
    'about_me' => '', 'looking_for_age_min' => 18, 'looking_for_age_max' => 70, 'looking_for_ethnicities' => [],
    'annual_income' => '', 'net_worth' => '', 'dating_budget' => '', 'dating_style' => [], 'relationship_terms' => []
];
$user_data = array_merge($fallbacks, $user_data);

$completion_percentage = get_profile_completion_percentage($user_id);

$completed_terms = count($user_data['relationship_terms']) >= 3;
$completed_dating = !empty($user_data['dating_styles']) && is_array($user_data['dating_styles']) && count($user_data['dating_styles']) > 0;
$completed_location = !empty($user_data['city']) && !empty($user_data['country']);
$completed_appearance = !empty($user_data['height']) && !empty($user_data['body_type']) && !empty($user_data['ethnicity']) && !empty($user_data['race']);
$completed_personal = !empty($user_data['occupation']) && !empty($user_data['relationship_status']) && !empty($user_data['smoke']) && !empty($user_data['drink']);
$completed_about = !empty($user_data['about_me']);
$completed_looking = !empty($user_data['looking_for_age_min']);
$completed_photos = !empty(get_user_meta($user_id, 'user_photos', true));
$completed_financial = true;
if ($gender !== 'Woman' && $gender !== 'LGBTQ+') {
    $completed_financial = !empty($user_data['annual_income']) && !empty($user_data['net_worth']) && !empty($user_data['dating_budget']);
}

function render_completion_icon($is_complete, $section_key) {
  $icon_class_complete = 'fa-check status-checkmark';
  $icon_class_incomplete = 'fa-times status-incomplete';
  $style_complete = $is_complete ? '' : 'display: none;';
  $style_incomplete = !$is_complete ? '' : 'display: none;';

  echo '<i class="fas ' . $icon_class_complete . ' completion-icon completion-icon-success-' . $section_key . '" data-status="complete" style="' . $style_complete . '"></i>';
  echo '<i class="fas ' . $icon_class_incomplete . ' completion-icon completion-icon-incomplete-' . $section_key . '" data-status="incomplete" style="' . $style_incomplete . '"></i>';
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
  <title><?php echo function_exists('sud_get_formatted_page_title') ? sud_get_formatted_page_title('Edit Profile') : esc_html('Edit Profile - ' . SUD_SITE_NAME); ?></title>
  <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/style.css">
  <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/dashboard.css">
  <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/user-card.css">
  <link rel="stylesheet" href="<?php echo SUD_CSS_URL; ?>/profile-edit.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Lato:wght@300;400;700;900&display=swap" rel="stylesheet">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    window.sud_vars = window.sud_vars || {};
    sud_vars.sud_url = '<?php echo esc_url(SUD_URL); ?>';
    sud_vars.is_logged_in = true;
    sud_vars.userFunctionalRole = '<?php echo esc_js(get_user_meta($user_id, 'functional_role', true)); ?>';
    sud_vars.default_avatar_url = '<?php echo esc_js(SUD_IMG_URL . '/default-profile.jpg'); ?>';
  </script>
  <script src="<?php echo SUD_JS_URL; ?>/profile-edit.js"></script>
  <script src="<?php echo SUD_JS_URL; ?>/common.js"></script>
</head>
<body>
  <?php include(dirname(__FILE__, 2) . '/templates/components/user-header.php'); ?>
  <div id="toast-container" class="toast-container"></div>

  <div class="main-content">
    <div class="mobile-profile-edit">
      <!-- Profile Header -->
      <div class="profile-header">
        <div class="profile-avatar">
          <img src="<?php echo esc_url($profile_pic_url); ?>" alt="<?php echo esc_attr($display_name); ?>" onerror="this.src='<?php echo SUD_IMG_URL; ?>/default-profile.jpg';">
        </div>
        <div class="profile-info">
          <h2 class="profile-name" id="user-name"><?php echo esc_html($display_name); ?></h2>
          <div class="completion-info">
            <div class="completion-bar">
              <div class="completion-progress" style="width: <?php echo $completion_percentage; ?>%;"></div>
            </div>
            <div class="completion-text"><?php echo $completion_percentage; ?>% Complete</div>
          </div>
        </div>
      </div>

      <!-- Profile Sections as Accordion -->
      <div class="profile-accordion">
        <!-- Basic Information -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="basic">
            <div class="accordion-title">
              <i class="fas fa-user accordion-icon"></i>
              Basic Information
              <?php render_completion_icon(!empty($display_name) && !empty($age), 'basic'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <div class="summary-row"> <div class="summary-label">Email:</div> <div class="summary-value" id="user-email"><?php echo esc_html($current_user->user_email); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Display Name:</div> <div class="summary-value" id="user-display-name-summary"><?php echo esc_html($display_name); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Age:</div> <div class="summary-value" id="user-age"><?php echo $age ? esc_html($age) : 'Not set'; ?></div> </div>
            </div>
            <div class="section-actions"> <button class="edit-btn" data-section="basic"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>

        <!-- Terms of Relationship -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="terms">
            <div class="accordion-title">
              <i class="fas fa-tag accordion-icon"></i>
              Terms of Relationship™
              <?php render_completion_icon($completed_terms, 'terms'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <p>These terms define what kind of relationship you're looking for.</p>
              <div class="tag-list" id="terms-summary">
                    <?php if (count($user_data['relationship_terms']) > 0): ?>
                      <?php foreach ($user_data['relationship_terms'] as $term): ?>
                        <span class="tag"><?php echo esc_html(get_attribute_display_label('interests', $term)); ?></span>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <span class="empty-text">No terms selected yet (Min 3 required)</span>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="section-actions"> <button class="edit-btn" data-section="terms"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
              </div>
          </div>

        <!-- Dating Styles -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="dating">
            <div class="accordion-title">
              <i class="fas fa-heart accordion-icon"></i>
              Dating Styles
              <?php render_completion_icon($completed_dating, 'dating'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <p>Your preferred dating activities and experiences.</p>
              <div class="tag-list" id="dating-summary">
                 <?php if (!empty($user_data['dating_styles']) && is_array($user_data['dating_styles'])): ?>
                   <?php foreach ($user_data['dating_styles'] as $style): ?>
                    <span class="tag"><?php echo esc_html(get_attribute_display_label('dating_style', $style)); ?></span>
                   <?php endforeach; ?>
                <?php else: ?>
                  <span class="empty-text">No dating styles selected yet</span>
                <?php endif; ?>
              </div>
            </div>
             <div class="section-actions"> <button class="edit-btn" data-section="dating"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>

        <!-- Financial Information -->
        <?php
        $loggedInUserFunctionalRole = get_user_meta($user_id, 'functional_role', true);
        if ($loggedInUserFunctionalRole === 'provider'):
        ?>
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="financial">
            <div class="accordion-title">
              <i class="fas fa-university accordion-icon"></i>
              Financial Information
              <?php
                $completed_financial_provider = !empty($user_data['annual_income'])
                                                && !empty($user_data['net_worth'])
                                                && !empty($user_data['dating_budget'])
                                                && $user_data['annual_income'] !== $fallbacks['annual_income']
                                                && $user_data['net_worth'] !== $fallbacks['annual_income'] /* Use a distinct fallback check */
                                                && $user_data['dating_budget'] !== $fallbacks['annual_income']; /* Use a distinct fallback check */
                render_completion_icon($completed_financial_provider, 'financial');
              ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <div class="summary-row">
                  <div class="summary-label">Annual Income:</div>
                  <div class="summary-value" id="financial-income" data-value="<?php echo esc_attr($user_data['annual_income']); ?>">
                      <?php echo esc_html(get_attribute_display_label('annual_income', $user_data['annual_income'], 'Not set')); // Use 'Not set' fallback for edit page ?>
                  </div>
              </div>
              <div class="summary-row">
                  <div class="summary-label">Net Worth:</div>
                  <div class="summary-value" id="financial-networth" data-value="<?php echo esc_attr($user_data['net_worth']); ?>">
                      <?php echo esc_html(get_attribute_display_label('net_worth', $user_data['net_worth'], 'Not set')); ?>
                  </div>
              </div>
              <div class="summary-row">
                  <div class="summary-label">Dating Budget:</div>
                  <div class="summary-value" id="financial-budget" data-value="<?php echo esc_attr($user_data['dating_budget']); ?>">
                      <?php echo esc_html(get_attribute_display_label('dating_budget', $user_data['dating_budget'], 'Not set')); ?>
                  </div>
              </div>
            </div>
             <div class="section-actions"> <button class="edit-btn" data-section="financial"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>
        <?php
        endif;
        ?>
        
        <!-- Location -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="location">
            <div class="accordion-title">
              <i class="fas fa-map-marker-alt accordion-icon"></i>
              Location
              <?php
                $completed_location = !empty($user_data['city']) && !empty($user_data['country']);
                render_completion_icon($completed_location, 'location');
              ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <div class="summary-row">
                  <div class="summary-label">Location:</div>
                  <?php
                      // Use location_string primarily, fallback to city/country combo
                      $display_location = (!empty($user_data['location_string']) && strpos($user_data['location_string'], 'Coords:') === false)
                                          ? $user_data['location_string']
                                          : (!empty($user_data['city']) ? trim($user_data['city'] . ', ' . $user_data['country'], ', ') : 'Not specified');
                   ?>
                  <div class="summary-value" id="location-formatted"><?php echo esc_html($display_location); ?></div>
                  <input type="hidden" id="current-city" value="<?php echo esc_attr($user_data['city']); ?>">
                  <input type="hidden" id="current-region" value="<?php echo esc_attr($user_data['region']); ?>">
                  <input type="hidden" id="current-country" value="<?php echo esc_attr($user_data['country']); ?>">
                  <input type="hidden" id="current-latitude" value="<?php echo esc_attr(get_user_meta($user_id, 'latitude', true)); ?>">
                  <input type="hidden" id="current-longitude" value="<?php echo esc_attr(get_user_meta($user_id, 'longitude', true)); ?>">
              </div>
            </div>
             <div class="section-actions"> <button class="edit-btn" data-section="location"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>

        <!-- Appearance -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="appearance">
            <div class="accordion-title">
              <i class="fas fa-smile accordion-icon"></i>
              Appearance
              <?php render_completion_icon($completed_appearance, 'appearance'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <div class="summary-row"> <div class="summary-label">Height:</div> <div class="summary-value" id="appearance-height"><?php echo !empty($user_data['height']) ? esc_html($user_data['height']) . ' cm' : 'Not specified'; ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Body Type:</div> <div class="summary-value" id="appearance-body"><?php echo esc_html(get_attribute_display_label('body_type', $user_data['body_type'])); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Ethnicity:</div> <div class="summary-value" id="appearance-ethnicity"><?php echo esc_html(get_attribute_display_label('ethnicity', $user_data['ethnicity'])); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Race:</div> <div class="summary-value" id="appearance-race"><?php echo esc_html(get_attribute_display_label('race', $user_data['race'])); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Eye Color:</div> <div class="summary-value" id="appearance-eye"><?php echo esc_html(get_attribute_display_label('eye_color', $user_data['eye_color'])); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Hair Color:</div> <div class="summary-value" id="appearance-hair"><?php echo esc_html(get_attribute_display_label('hair_color', $user_data['hair_color'])); ?></div> </div>
            </div>
             <div class="section-actions"> <button class="edit-btn" data-section="appearance"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>

        <!-- Personal Information -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="personal">
            <div class="accordion-title">
              <i class="fas fa-lock accordion-icon"></i>
              Personal Information
              <?php render_completion_icon($completed_personal, 'personal'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <div class="summary-row"> <div class="summary-label">Occupation:</div> <div class="summary-value" id="personal-occupation" data-value="<?php echo esc_attr($user_data['occupation']); ?>"><?php echo !empty($user_data['occupation']) ? esc_html($user_data['occupation']) : 'Not specified'; ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Industry:</div> <div class="summary-value" id="personal-industry" data-value="<?php echo esc_attr($user_data['industry']); ?>"><?php echo esc_html(get_attribute_display_label('industry', $user_data['industry'])); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Education:</div> <div class="summary-value" id="personal-education" data-value="<?php echo esc_attr($user_data['education']); ?>"> <?php echo esc_html(get_attribute_display_label('education', $user_data['education'])); ?> </div></div>
              <div class="summary-row"> <div class="summary-label">Relationship:</div> <div class="summary-value" id="personal-relationship" data-value="<?php echo esc_attr($user_data['relationship_status']); ?>"><?php echo esc_html(get_attribute_display_label('relationship_status', $user_data['relationship_status'])); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Smoking:</div> <div class="summary-value" id="personal-smoke" data-value="<?php echo esc_attr($user_data['smoke']); ?>"><?php echo esc_html(get_attribute_display_label('smoke', $user_data['smoke'])); ?></div> </div>
              <div class="summary-row"> <div class="summary-label">Drinking:</div> <div class="summary-value" id="personal-drink" data-value="<?php echo esc_attr($user_data['drink']); ?>"><?php echo esc_html(get_attribute_display_label('drink', $user_data['drink'])); ?></div> </div>
            </div>
             <div class="section-actions"> <button class="edit-btn" data-section="personal"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>

        <!-- About Me -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="about">
            <div class="accordion-title">
              <i class="fas fa-align-left accordion-icon"></i>
              About Me
              <?php render_completion_icon($completed_about, 'about'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <p id="about-summary"><?php echo !empty($user_data['about_me']) ? nl2br(esc_html($user_data['about_me'])) : '<span class="empty-text">Tell potential matches about yourself</span>'; ?></p>
            </div>
             <div class="section-actions"> <button class="edit-btn" data-section="about"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>

        <!-- Looking For -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="looking">
            <div class="accordion-title">
              <i class="fas fa-search accordion-icon"></i>
              Looking For
              <?php render_completion_icon($completed_looking, 'looking'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <div class="summary-row">
                <div class="summary-label">Age Range:</div>
                <div class="summary-value" id="looking-age">
                  <?php echo esc_html($user_data['looking_for_age_min']); ?> -
                  <?php echo esc_html($user_data['looking_for_age_max']); ?> years
                </div>
              </div>
              <div class="summary-row">
                <div class="summary-label">Ethnicities:</div>
                <div class="summary-value tag-list" id="looking-ethnicities" data-raw-values="<?php echo esc_attr(implode(',', $user_data['looking_for_ethnicities'])); ?>">
                    <?php
                    if (!empty($user_data['looking_for_ethnicities']) && is_array($user_data['looking_for_ethnicities'])) {
                        if (count($user_data['looking_for_ethnicities']) === 1 && $user_data['looking_for_ethnicities'][0] === SUD_ANY_ETHNICITY_KEY) {
                            echo '<span class="tag">Open to Any Ethnicity</span>';
                        } else {
                          $ethnicity_options_lookup = get_attribute_options('ethnicity');
                          $race_options_lookup = get_attribute_options('race');
                          $displayed_tags = 0; 

                          foreach ($user_data['looking_for_ethnicities'] as $combo) {
                            if ($combo === SUD_ANY_ETHNICITY_KEY) continue;
                            if (strpos($combo, '|') !== false) {
                              list($ethnicity_val, $race_val) = explode('|', $combo, 2);

                              $ethnicity_label = get_attribute_display_label('ethnicity', $ethnicity_val, ucfirst($ethnicity_val));
                              $race_label = get_attribute_display_label('race', $race_val, ucfirst($race_val));
                              echo '<span class="tag">' . esc_html($ethnicity_label . ($race_val ? ', ' . $race_label : '')) . '</span>';
                              $displayed_tags++;
                            } else {
                              $label = get_attribute_display_label('ethnicity', $combo, ucfirst($combo));
                              echo '<span class="tag">' . esc_html($label) . '</span>';
                              $displayed_tags++;
                            }
                          }
                          if ($displayed_tags === 0) {
                            echo '<span class="empty-text">No specific preferences set</span>';
                          }
                        }
                    } else {
                      echo '<span class="empty-text">No preferences set</span>';
                    }
                    ?>
                </div>
              </div>
            </div>
             <div class="section-actions"> <button class="edit-btn" data-section="looking"><i class="fas fa-pencil-alt"></i> Edit</button> </div>
          </div>
        </div>

        <!-- Photos -->
        <div class="accordion-item">
          <div class="accordion-header" data-section-completion="photos">
            <div class="accordion-title">
              <i class="fas fa-images accordion-icon"></i>
              Photos
              <?php render_completion_icon($completed_photos, 'photos'); ?>
            </div>
            <i class="fas fa-chevron-down accordion-chevron"></i>
          </div>
          <div class="accordion-content">
            <div class="content-summary">
              <p>Upload photos to show potential matches who you are.</p>
              <?php $user_photo_ids = get_user_meta($user_id, 'user_photos', true); ?>
              <div class="photos-grid" id="photos-summary-grid">
                <?php if (!empty($user_photo_ids) && is_array($user_photo_ids)): ?>
                  <?php foreach ($user_photo_ids as $photo_id):
                      $photo_url = wp_get_attachment_image_url($photo_id, 'medium_large');
                      if ($photo_url):
                        $is_profile_pic = ($photo_id == $profile_picture_id);
                      ?>
                      <div class="photo-item <?php echo $is_profile_pic ? 'is-profile-pic' : ''; ?>" data-photo-id="<?php echo esc_attr($photo_id); ?>">
                        <img src="<?php echo esc_url($photo_url); ?>" alt="User photo" loading="lazy" onerror="this.style.display='none';">
                        <?php if ($is_profile_pic): ?>
                          <span class="profile-badge-photo-summary">P</span>
                        <?php endif; ?>
                      </div>
                    <?php endif; ?>
                  <?php endforeach; ?>
                <?php else: ?>
                  <span class="empty-text">No photos uploaded yet</span>
                <?php endif; ?>
            </div>
            </div>
            <div class="section-actions"> <button class="edit-btn" data-section="photos"><i class="fas fa-pencil-alt"></i> Edit Photos</button> </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal for Editing -->
  <div class="modal-overlay">
    <div class="modal-container">
      <div class="modal-header">
        <h3 class="modal-title">Edit Profile</h3>
        <button class="modal-close">×</button>
      </div>
      <div class="modal-body">
        <!-- Form content will be loaded here -->
      </div>
      <div class="modal-footer">
        <button type="button" class="modal-btn cancel-btn">Cancel</button>
        <button type="button" class="modal-btn save-btn">Save Changes</button>
      </div>
    </div>
  </div>

  <?php include(dirname(__FILE__, 2) . '/templates/components/user-footer.php'); ?>
</body>
</html>