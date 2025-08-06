<?php
require_once(dirname(__FILE__, 2) . '/includes/config.php');

$profile_picture_id_initial = get_user_meta($current_user->ID, 'profile_picture', true);
$user_photos_initial = get_user_meta($current_user->ID, 'user_photos', true);
if (!is_array($user_photos_initial)) {
    $user_photos_initial = array();
}
$saved_photos = array(); 
if (!empty($user_photos_initial)) {
    foreach ($user_photos_initial as $photo_id) {
        $photo_url = wp_get_attachment_image_url($photo_id, 'medium');
        if ($photo_url) {
            $saved_photos[] = array(
                'id' => $photo_id,
                'url' => $photo_url
            );
        }
    }
}
$has_photos = !empty($saved_photos); 

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['photos_submit'])) {
    $upload_count = 0;
    $new_upload_attempted = !empty($_FILES['user_photos']['name'][0]);
    $profile_picture_id = $profile_picture_id_initial; 
    $user_photos = $user_photos_initial; 

    if ($new_upload_attempted) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');

        foreach ($_FILES['user_photos']['name'] as $key => $value) {
            if ($_FILES['user_photos']['error'][$key] === UPLOAD_ERR_NO_FILE) {
                continue; 
            }
            if ($_FILES['user_photos']['error'][$key] !== UPLOAD_ERR_OK) {
                $display_error_message .= "Error uploading file: " . htmlspecialchars($_FILES['user_photos']['name'][$key]) . " (Code: " . $_FILES['user_photos']['error'][$key] . ")<br>";
                continue;
            }

            $file = array(
                'name'     => $_FILES['user_photos']['name'][$key],
                'type'     => $_FILES['user_photos']['type'][$key],
                'tmp_name' => $_FILES['user_photos']['tmp_name'][$key],
                'error'    => $_FILES['user_photos']['error'][$key],
                'size'     => $_FILES['user_photos']['size'][$key]
            );

            $attachment_id = media_handle_sideload($file, 0); 

            if (is_wp_error($attachment_id)) {
                $display_error_message .= "Error processing '{$file['name']}': " . $attachment_id->get_error_message() . "<br>";
            } else {
                if (!in_array($attachment_id, $user_photos)) { 
                    $user_photos[] = $attachment_id;
                }

                $upload_count++;

                if (empty($profile_picture_id)) {
                    update_user_meta($current_user->ID, 'profile_picture', $attachment_id);
                    $profile_picture_id = $attachment_id; 
                }
            }
        }
        if ($upload_count > 0) {
            update_user_meta($current_user->ID, 'user_photos', array_unique($user_photos)); 
            update_user_meta($current_user->ID, 'completed_step_13', true);
            echo "<script>window.location.href = '" . esc_url_raw(SUD_URL . "/pages/swipe") . "';</script>";
            exit;
        } else {
            if (empty($display_error_message)) { 
                $display_error_message = 'Failed to upload selected photos. Please check file types/sizes.';
            }
        }
    } else {
        if ($has_photos) { 
            update_user_meta($current_user->ID, 'completed_step_13', true); 
            echo "<script>window.location.href = '" . esc_url_raw(SUD_URL . "/pages/swipe") . "';</script>";
            exit;
        } else {
            $display_error_message = 'Please select at least one photo to upload.';
        }
    }
} else if (isset($_POST['skip_photos'])) { 
    update_user_meta($current_user->ID, 'completed_step_13', true);
    header('Location: ' . SUD_URL . '/pages/swipe');
    exit;
}
?>

<div class="sud-step-content" id="step-13">
    <form method="post" id="photos-form" enctype="multipart/form-data" action="<?php echo esc_url(SUD_URL . '/profile-details');?>">
        <div class="sud-step-content-inner">
            <h2 class="sud-step-heading">Upload your photos</h2>
            <p class="sud-step-description">Upload at least one photo to complete your profile.</p>
            
            <div class="sud-photos-upload-container">
                <div class="sud-photo-upload-box <?php echo $has_photos ? 'has-photos' : ''; ?>">
                    <label for="user_photos" class="sud-photo-label">
                        <i class="fas fa-plus"></i>
                        <span>Add Photos</span>
                    </label>
                    <input type="file" name="user_photos[]" id="user_photos" multiple accept="image/*" style="display: none;">
                    <div class="sud-photo-stack" id="photo-previews">
                        <?php if (!empty($saved_photos)): ?>
                            <?php if (count($saved_photos) > 1): ?>
                                <div class="sud-photo-count"><?php echo count($saved_photos); ?></div>
                            <?php endif; ?>
                            
                            <?php
                            $previewCount = min(count($saved_photos), 5);
                            for ($i = 0; $i < $previewCount; $i++): 
                                $photo = $saved_photos[$i];
                                $index = $previewCount - 1 - $i;
                                $randomAngle = ($index % 2 === 0 ? -1 : 1) * (3 + ($index * 2));
                                $randomShift = 5 + ($index * 2);
                                $zIndex = $index + 3;
                                $transform = "rotate({$randomAngle}deg) translateY({$randomShift}px)";
                            ?>
                                <div class="sud-photo-preview" data-photo-id="<?php echo $photo['id']; ?>" 
                                     style="transform: <?php echo $transform; ?>; z-index: <?php echo $zIndex; ?>;">
                                    <img src="<?php echo $photo['url']; ?>" alt="Uploaded photo">
                                </div>
                            <?php endfor; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <input type="hidden" name="photos_submit" value="1">
            <?php
                if (isset($active_step)) {
                    wp_nonce_field( 'sud_profile_step_' . $active_step . '_action', '_sud_step_nonce' );
                } else {
                    wp_nonce_field( 'sud_profile_step_13_action', '_sud_step_nonce' );
                }
            ?>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.getElementById('photos-form');
        const fileInput = document.getElementById('user_photos');
        const photoStack = document.getElementById('photo-previews');
        const photoBox = form.querySelector('.sud-photo-upload-box');
        window.savedPhotoData = <?php echo json_encode($saved_photos); ?> || [];
        let savedPhotoCount = window.savedPhotoData.length;

        const clearBtn = document.createElement('button');
        clearBtn.className = 'sud-photo-clear';
        clearBtn.innerHTML = '<i class="fas fa-times"></i>';
        clearBtn.type = 'button';

        const addBtn = document.createElement('button');
        addBtn.className = 'sud-photo-add';
        addBtn.innerHTML = '<i class="fas fa-plus"></i>';
        addBtn.type = 'button';

        if (!photoBox) {
            console.error("Photo upload box not found!");
            return;
        }
        photoBox.appendChild(clearBtn);
        photoBox.appendChild(addBtn);

        function updatePhotoActionButtonsVisibility() {
            const hasAnyFilesInInput = fileInput.files && fileInput.files.length > 0;
            const hasSavedPhotos = savedPhotoCount > 0;
            const showButtons = hasAnyFilesInInput || hasSavedPhotos;
            clearBtn.style.display = showButtons ? 'flex' : 'none';
            addBtn.style.display = showButtons ? 'flex' : 'none';
            photoBox.classList.toggle('has-photos', showButtons);
        }

        function updatePreview() {
            photoStack.innerHTML = '';
            savedPhotoCount = window.savedPhotoData.length;
            const currentFiles = fileInput.files ? Array.from(fileInput.files) : [];
            const totalCount = savedPhotoCount + currentFiles.length;

            if (totalCount > 1) {
                const countBadge = document.createElement('div');
                countBadge.className = 'sud-photo-count';
                countBadge.textContent = totalCount;
                if (photoStack.firstChild) {
                    photoStack.insertBefore(countBadge, photoStack.firstChild);
                } else {
                    photoStack.appendChild(countBadge);
                }
            }

            const savedPreviewLimit = 5;
            const savedToShow = window.savedPhotoData.slice(0, savedPreviewLimit);
            savedToShow.forEach((photo, i) => {
                const preview = document.createElement('div');
                preview.className = 'sud-photo-preview saved-photo';
                preview.dataset.photoId = photo.id;
                const img = document.createElement('img');
                img.src = photo.url;
                img.alt = 'Saved photo';
                preview.appendChild(img);
                const stackIndexSaved = savedToShow.length - 1 - i;
                if (savedToShow.length > 1 && stackIndexSaved >= 0) {
                    const randomAngle = (stackIndexSaved % 2 === 0 ? -1 : 1) * (3 + (stackIndexSaved * 2));
                    const randomShift = 5 + (stackIndexSaved * 2);
                    preview.style.transform = `rotate(${randomAngle}deg) translateY(${randomShift}px)`;
                    preview.style.zIndex = 3 + stackIndexSaved;
                }
                photoStack.appendChild(preview);
            });

            const newPreviewLimit = 5;
            const filesToPreview = currentFiles.slice(0, newPreviewLimit);
            filesToPreview.forEach((file, i) => {
                const reader = new FileReader();
                reader.onload = function(e_reader) {
                    const preview = document.createElement('div');
                    preview.className = 'sud-photo-preview new-upload';
                    const img = document.createElement('img');
                    img.src = e_reader.target.result;
                    img.alt = 'Preview';
                    preview.appendChild(img);
                    const totalVisiblePreviews = savedToShow.length + filesToPreview.length;
                    const stackIndexNew = filesToPreview.length - 1 - i;
                    if (totalVisiblePreviews > 1 && stackIndexNew >= 0) {
                        const randomAngle = (stackIndexNew % 2 === 0 ? -1 : 1) * (3 + (stackIndexNew * 2));
                        const randomShift = 5 + (stackIndexNew * 2);
                        preview.style.transform = `rotate(${randomAngle}deg) translateY(${randomShift}px)`;
                        preview.style.zIndex = 10 + savedToShow.length + stackIndexNew;
                    }
                    photoStack.appendChild(preview);
                };
                if (file instanceof File) reader.readAsDataURL(file);
            });
            photoBox.classList.toggle('has-photos', totalCount > 0);
        }

        photoBox.addEventListener('click', (e) => {
            if (e.target !== clearBtn && !clearBtn.contains(e.target) &&
                e.target !== addBtn && !addBtn.contains(e.target)) {
                fileInput.click();
            }
        });

        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            if (!files || files.length === 0) {
                updatePreview();
                updatePhotoActionButtonsVisibility();
                return;
            }

            const dataTransfer = new DataTransfer();
            const compressionPromises = [];
            const MAX_SIZE_MB = 2; 

            const form = document.getElementById('photos-form');
            if (typeof showLoader === 'function') showLoader(); 
            const currentMobileBtn = form.closest('.sud-step-content').querySelector('.sud-mobile-nav-container .sud-next-btn');
            const currentDesktopBtn = document.getElementById('desktop-action-btn');
            if(currentMobileBtn) { currentMobileBtn.disabled = true; currentMobileBtn.innerHTML = '<i class="fas fa-cog fa-spin"></i> Processing...'; }
            if(currentDesktopBtn) { currentDesktopBtn.disabled = true; currentDesktopBtn.innerHTML = '<i class="fas fa-cog fa-spin"></i> Processing...'; }

            Array.from(files).forEach(file => {

                if (!file.type.startsWith('image/')) {
                    dataTransfer.items.add(file); 
                    return;
                }

                const promise = new Promise((resolve, reject) => {
                    new Compressor(file, {
                        quality: 0.75,       
                        maxWidth: 1920,      
                        maxHeight: 1920,     
                        mimeType: 'image/jpeg',
                        convertSize: 500000, 
                        success(result) {

                            const compressedFile = new File([result], file.name, {
                                type: result.type,
                                lastModified: Date.now(),
                            });
                            dataTransfer.items.add(compressedFile);
                            resolve();
                        },
                        error(err) {
                            console.error('Compressor.js Error:', err.message);

                            dataTransfer.items.add(file);
                            reject(err);
                        },
                    });
                });
                compressionPromises.push(promise);
            });

            Promise.all(compressionPromises)
                .catch(err => {

                    console.warn("One or more images could not be compressed, using originals.", err);
                })
                .finally(() => {

                    fileInput.files = dataTransfer.files;

                    if (typeof hideLoader === 'function') hideLoader();
                    if(currentMobileBtn) { currentMobileBtn.innerHTML = 'Continue'; } 
                    if(currentDesktopBtn) { currentDesktopBtn.innerHTML = 'Continue'; }

                    updatePreview();
                    updatePhotoActionButtonsVisibility();
                    setTimeout(() => validateAndToggleButton(form), 100);
                });
        });

        clearBtn.addEventListener('click', function(e) {
            e.preventDefault(); e.stopPropagation();
            if (confirm("Clear current selection? Saved photos remain.")) {
                fileInput.value = '';
                const dt = new DataTransfer();
                fileInput.files = dt.files;
                updatePreview();
                updatePhotoActionButtonsVisibility();
                setTimeout(() => validateAndToggleButton(form), 100);
            }
        });

        addBtn.addEventListener('click', (e) => {
            e.preventDefault(); e.stopPropagation(); fileInput.click();
        });

        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            photoBox.addEventListener(eventName, preventDefaults, false);
        });
        ['dragenter', 'dragover'].forEach(eventName => {
            photoBox.addEventListener(eventName, () => photoBox.style.borderColor = 'var(--primary-color)');
        });
        ['dragleave', 'drop'].forEach(eventName => {
            photoBox.addEventListener(eventName, () => photoBox.style.borderColor = 'rgba(255, 255, 255, 0.5)');
        });
        photoBox.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            if (dt.files && dt.files.length) {
                const currentFiles = fileInput.files ? Array.from(fileInput.files) : [];
                const droppedFiles = Array.from(dt.files);
                const combinedFiles = currentFiles.concat(droppedFiles);
                const dataTransfer = new DataTransfer();
                combinedFiles.forEach(file => {
                    if (file instanceof File) dataTransfer.items.add(file);
                });
                fileInput.files = dataTransfer.files;
                updatePreview();
                updatePhotoActionButtonsVisibility();
                setTimeout(() => validateAndToggleButton(form), 100);
            }
        });

        if (form) {
            form.addEventListener('submit', function(e) {
                const hasAnyPhotos = (fileInput.files && fileInput.files.length > 0) || window.savedPhotoData.length > 0;
                if (!hasAnyPhotos) {
                    e.preventDefault();
                    let errorDiv = this.querySelector('.sud-error-alert');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'sud-error-alert';
                        const innerContent = this.querySelector('.sud-step-content-inner');
                        if(innerContent) innerContent.prepend(errorDiv);
                    }
                    errorDiv.textContent = 'Please select at least one photo to upload.';
                    errorDiv.style.display = 'block';
                    const currentMobileBtn = form.querySelector('.sud-mobile-navigation .sud-next-btn');
                    const currentDesktopBtn = document.getElementById('desktop-action-btn');
                    if (currentMobileBtn) { currentMobileBtn.disabled = true; }
                    if (currentDesktopBtn) { currentDesktopBtn.disabled = true; }
                    return false;
                }
                let errorDiv = this.querySelector('.sud-error-alert');
                if (errorDiv) errorDiv.style.display = 'none';
                
                const currentMobileBtn = form.closest('.sud-step-content').querySelector('.sud-mobile-nav-container .sud-next-btn');
                const currentDesktopBtn = document.getElementById('desktop-action-btn');
                if (currentMobileBtn) {
                    currentMobileBtn.disabled = true;
                    currentMobileBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                }
                if (currentDesktopBtn) {
                    currentDesktopBtn.disabled = true;
                    currentDesktopBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
                }
                if (typeof showLoader === 'function') showLoader();
                return true;
            });
        }

        if (window.validateAndToggleButton) {
            const originalValidateAndToggle = window.validateAndToggleButton;
            
            window.validateAndToggleButton = function(formElement) {
                if (!formElement) return;
                if (formElement.id === 'photos-form') {
                    const fileInputElement = formElement.querySelector('#user_photos');
                    const savedPhotosExist = (typeof window.savedPhotoData !== 'undefined' && window.savedPhotoData.length > 0);
                    const newFilesSelected = fileInputElement && fileInputElement.files && fileInputElement.files.length > 0;
                    
                    if (savedPhotosExist || newFilesSelected) {
                        if (typeof enableStepButtons === 'function') enableStepButtons(formElement);
                    } else {
                        if (typeof disableStepButtons === 'function') disableStepButtons(formElement);
                    }
                } else {
                    originalValidateAndToggle(formElement);
                }
            };
        }
        
        if (form && typeof window.validateAndToggleButton === 'function') {
            setTimeout(() => window.validateAndToggleButton(form), 100);
        }
    });
</script>