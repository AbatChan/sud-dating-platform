class DropdownManager {
    constructor(selector = '.user-dropdown') {
        this.dropdowns = document.querySelectorAll(selector);
        this.closeTimeouts = new Map();
        this.activeDropdown = null;
        this.init();
    }

    init() {
        document.addEventListener('click', this.handleDocumentClick.bind(this));
        document.addEventListener('keydown', this.handleKeyDown.bind(this));
        
        this.dropdowns.forEach(dropdown => {
            const toggle = dropdown.querySelector('.dropdown-toggle');
            const menu = dropdown.querySelector('.user-dropdown-menu');
            const profileLink = dropdown.querySelector('.user-profile-link');
            
            if (!toggle || !menu) return;

            toggle.setAttribute('aria-expanded', 'false');
            toggle.setAttribute('aria-haspopup', 'true');
            menu.setAttribute('aria-hidden', 'true');
        
            const dropdownId = dropdown.id || `dropdown-${Math.random().toString(36).substr(2, 9)}`;
            dropdown.id = dropdownId;
            menu.id = `${dropdownId}-menu`;
            toggle.setAttribute('aria-controls', menu.id);
            toggle.addEventListener('click', (e) => this.toggleDropdown(e, dropdown));
            profileLink?.addEventListener('click', (e) => e.stopPropagation());

            dropdown.addEventListener('mouseenter', (e) => this.openDropdown(e, dropdown));
            dropdown.addEventListener('mouseleave', () => this.closeDropdownWithDelay(dropdown, 300));
            menu.addEventListener('mouseenter', () => this.clearCloseTimeout(dropdown));
            menu.addEventListener('mouseleave', () => this.closeDropdownWithDelay(dropdown, 300));
            menu.addEventListener('click', (e) => e.stopPropagation());

            const menuItems = menu.querySelectorAll('a');
            menuItems.forEach((item, index) => {
                item.setAttribute('tabindex', '-1');
                item.setAttribute('role', 'menuitem');
                item.id = `${dropdownId}-item-${index}`;
                
                item.addEventListener('keydown', (e) => {
                    this.handleMenuItemKeyDown(e, index, menuItems);
                });
            });
        });
    }

    toggleDropdown(e, dropdown) {
        e.preventDefault();
        e.stopPropagation();
        
        const isActive = dropdown.classList.contains('active');
        this.closeAllDropdowns();
        
        if (!isActive) {
            this.openDropdown(e, dropdown);
        }
    }

    openDropdown(e, dropdown) {
        e?.preventDefault();
        e?.stopPropagation();
        
        this.clearCloseTimeout(dropdown);
        
        if (dropdown.classList.contains('active')) return;

        this.closeAllDropdowns();
        dropdown.classList.add('active');
        this.activeDropdown = dropdown;

        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.user-dropdown-menu');
        
        if (toggle) toggle.setAttribute('aria-expanded', 'true');
        if (menu) {
            menu.setAttribute('aria-hidden', 'false');
            const firstItem = menu.querySelector('a');
            if (firstItem) firstItem.setAttribute('tabindex', '0');
        }

        dropdown.classList.add('dropdown-animating');
        setTimeout(() => dropdown.classList.remove('dropdown-animating'), 300);
    }

    closeDropdown(dropdown) {
        if (!dropdown) return;
        
        dropdown.classList.remove('active');
        const toggle = dropdown.querySelector('.dropdown-toggle');
        const menu = dropdown.querySelector('.user-dropdown-menu');
        
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
        if (menu) {
            menu.setAttribute('aria-hidden', 'true');
            const menuItems = menu.querySelectorAll('a');
            menuItems.forEach(item => item.setAttribute('tabindex', '-1'));
        }
        
        if (this.activeDropdown === dropdown) {
            this.activeDropdown = null;
        }
    }

    closeDropdownWithDelay(dropdown, delay = 0) {
        this.clearCloseTimeout(dropdown);
        const timeoutId = setTimeout(() => {
            this.closeDropdown(dropdown);
            this.closeTimeouts.delete(dropdown);
        }, delay);
        
        this.closeTimeouts.set(dropdown, timeoutId);
    }

    closeAllDropdowns() {
        this.dropdowns.forEach(dropdown => {
            this.closeDropdown(dropdown);
        });
    }

    clearCloseTimeout(dropdown) {
        if (this.closeTimeouts.has(dropdown)) {
            clearTimeout(this.closeTimeouts.get(dropdown));
            this.closeTimeouts.delete(dropdown);
        }
    }

    handleDocumentClick(e) {
        this.dropdowns.forEach(dropdown => {
            if (!dropdown.contains(e.target)) {
                this.closeDropdown(dropdown);
            }
        });
    }

    handleKeyDown(e) {
        if (!this.activeDropdown) return;
        
        if (e.key === 'Escape') {
            this.closeDropdown(this.activeDropdown);
            const toggle = this.activeDropdown.querySelector('.dropdown-toggle');
            if (toggle) toggle.focus();
        }
    }

    handleMenuItemKeyDown(e, index, menuItems) {
        const itemCount = menuItems.length;
        
        switch (e.key) {
            case 'ArrowDown':
            case 'Down':
                e.preventDefault();
                const nextIndex = (index + 1) % itemCount;
                menuItems[nextIndex].focus();
                break;
                
            case 'ArrowUp':
            case 'Up':
                e.preventDefault();
                const prevIndex = (index - 1 + itemCount) % itemCount;
                menuItems[prevIndex].focus();
                break;
                
            case 'Home':
                e.preventDefault();
                menuItems[0].focus();
                break;
                
            case 'End':
                e.preventDefault();
                menuItems[itemCount - 1].focus();
                break;
                
            case 'Tab':
                if (index === itemCount - 1 && !e.shiftKey) {
                    this.closeAllDropdowns();
                }
                break;
        }
    }
}

function showLoader() {
    const loader = document.getElementById('sud-loader');
    if (loader) {
        loader.style.display = 'flex';
        loader.classList.add('active');

        void loader.offsetWidth;
    }
}
function hideLoader() {
    const loader = document.getElementById('sud-loader');
    if (loader) {
        loader.classList.remove('active'); 
    }
}

function showToast(message, type = 'info', duration = 5000) {
    const toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        console.error('Toast container .toast-container not found in the DOM!');
        return;
    }

    let toastTypeClass = type;
    if (type === 'notice') toastTypeClass = 'info';
    const toast = document.createElement('div');
    toast.className = `toast-notification ${toastTypeClass}`;

    let autoCloseTimeout; 

    const iconContainer = document.createElement('div');
    iconContainer.className = 'toast-icon';
    const icon = document.createElement('i');
    if (toastTypeClass === 'success') icon.className = 'fas fa-check-circle';
    else if (toastTypeClass === 'error') icon.className = 'fas fa-times-circle';
    else if (toastTypeClass === 'warning') icon.className = 'fas fa-exclamation-triangle';
    else icon.className = 'fas fa-info-circle';
    iconContainer.appendChild(icon);

    const contentContainer = document.createElement('div');
    contentContainer.className = 'toast-content';

    const titleElement = document.createElement('div');
    titleElement.className = 'toast-title';
    if (toastTypeClass === 'success') titleElement.textContent = 'Success!';
    else if (toastTypeClass === 'error') titleElement.textContent = 'Error';
    else if (toastTypeClass === 'warning') titleElement.textContent = 'Warning';
    else titleElement.textContent = 'Notice';

    const messageElement = document.createElement('div');
    messageElement.className = 'toast-message';
    messageElement.innerHTML = message;

    contentContainer.appendChild(titleElement);
    contentContainer.appendChild(messageElement);

    const closeButton = document.createElement('button');
    closeButton.className = 'toast-close';
    closeButton.innerHTML = '&times;';
    closeButton.setAttribute('aria-label', 'Close');

    const closeAction = () => {
        toast.classList.remove('show');
        clearTimeout(autoCloseTimeout);
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 400); 
    };
    closeButton.onclick = (e) => {
        e.stopPropagation();
        closeAction();
    };

    toast.appendChild(iconContainer);
    toast.appendChild(contentContainer);
    toast.appendChild(closeButton);
    toastContainer.appendChild(toast);

    requestAnimationFrame(() => {
        toast.classList.add('show');
    });

    const startAutoCloseTimer = () => {
        if (duration > 0) {
            clearTimeout(autoCloseTimeout);
            autoCloseTimeout = setTimeout(() => {
                if (toast.classList.contains('show')) {
                    closeAction();
                }
            }, duration);
        }
    };

    const pauseAutoCloseTimer = () => {
        clearTimeout(autoCloseTimeout);
    };

    toast.addEventListener('mouseenter', pauseAutoCloseTimer);
    toast.addEventListener('mouseleave', startAutoCloseTimer);

    startAutoCloseTimer(); 
}

function strpos(haystack, needle, offset) {
    var i = (haystack + '').indexOf(needle, (offset || 0));
    return i === -1 ? false : i;
}

function enableStepButtons(form) {
    const desktopBtn = document.getElementById('desktop-action-btn');
    const mobileActionBtn = form.querySelector('.sud-mobile-nav-container .sud-next-btn');
   
    if (mobileActionBtn) {
        mobileActionBtn.disabled = false;
        mobileActionBtn.classList.add('active');
        mobileActionBtn.classList.remove('disabled');
    }

    const multiStepContainer = document.querySelector('.sud-multi-step-container');
    const activeStepForm = multiStepContainer ? multiStepContainer.querySelector('.sud-step-content:not([style*="display: none"]) form') : null;
    if (desktopBtn && form === activeStepForm) {
        desktopBtn.disabled = false;
        desktopBtn.classList.add('active');
        desktopBtn.classList.remove('disabled');
    }
}
function disableStepButtons(form) {
    const desktopBtn = document.getElementById('desktop-action-btn');
    const mobileActionBtn = form.querySelector('.sud-mobile-nav-container .sud-next-btn');

    if (mobileActionBtn) {
        mobileActionBtn.disabled = true;
        mobileActionBtn.classList.remove('active');
        mobileActionBtn.classList.add('disabled');
    }

    const multiStepContainer = document.querySelector('.sud-multi-step-container');
    const activeStepForm = multiStepContainer ? multiStepContainer.querySelector('.sud-step-content:not([style*="display: none"]) form') : null;
    if (desktopBtn && form === activeStepForm) {
        desktopBtn.disabled = true;
        desktopBtn.classList.remove('active');
        desktopBtn.classList.add('disabled');
    }
}
function validateAndToggleButton(form) {
    if (!form) return;
    let isValid = true;
    const formId = form.id;
    const MAX_TERMS = 5;
    const MAX_STYLES = 5;

    const textInputs = form.querySelectorAll('input[type="text"][required], textarea[required]');
    textInputs.forEach(input => {
        if (!input.value.trim()) {
            isValid = false;
        }
    });

    const optionButtons = form.querySelectorAll('.sud-option-button');
    if (optionButtons.length > 0 && !formId.includes('terms') && !formId.includes('dating-style')) {
        const hiddenInput = form.querySelector('input[type="hidden"][id^="selected-"]');
        if (hiddenInput && !hiddenInput.value) {
            isValid = false;
        }
    }

    let counterText = '';

    if (formId === 'terms-form') {
        const selectedCount = form.querySelectorAll('input[name="terms[]"]').length;
        isValid = isValid && selectedCount >= 3;
        counterText = `${selectedCount}/${MAX_TERMS} Selected`;

        const mobileCounter = form.querySelector('#mobile-selection-counter-0');
        const desktopCounter = document.getElementById('desktop-selection-counter');
        if (mobileCounter) mobileCounter.textContent = counterText;
        if (desktopCounter) desktopCounter.textContent = counterText;

    } else if (formId === 'dating-style-form') {
        const selectedCount = form.querySelectorAll('input[name="dating_styles[]"]').length;
        isValid = isValid && selectedCount > 0;
        counterText = `${selectedCount}/${MAX_STYLES} Selected`;

        const mobileCounter = form.querySelector('#mobile-selection-counter-5');
        const desktopCounter = document.getElementById('desktop-selection-counter');
        if (mobileCounter) mobileCounter.textContent = counterText;
        if (desktopCounter) desktopCounter.textContent = counterText;

    } else if (formId === 'interests-form') {
        const selectedCount = form.querySelectorAll('.sud-interest-tag.selected').length;
        const MIN_INTERESTS = 3;
        isValid = isValid && selectedCount >= MIN_INTERESTS;
    } else if (formId === 'photos-form') {
        const fileInputElement = form.querySelector('#user_photos');
        const savedPhotosExist = (typeof savedPhotoData !== 'undefined' && savedPhotoData.length > 0);
        const newFilesSelected = fileInputElement && fileInputElement.files && fileInputElement.files.length > 0;
        const visualPreviewsExist = form.querySelector('.sud-photo-preview') !== null;
        isValid = visualPreviewsExist;
    }
    if (isValid) {
        enableStepButtons(form);
    } else {
        disableStepButtons(form);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const dropdownManager = new DropdownManager();
    window.dropdownManager = dropdownManager;

    const urlParams = new URLSearchParams(window.location.search);
    let errorToastShownFromUrl = false;
    let infoToastShownFromUrl = false;

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    function showToastFromElement(selector, type, defaultMessage = '', removeOriginal = true) {
        const element = document.querySelector(selector);
        if (element && element.offsetParent !== null) {
            const message = element.textContent.trim() || defaultMessage;
            if (message) {
                showToast(message, type);
                if (removeOriginal) {
                    element.style.display = 'none';
                }
            }
        }
    }

    let primaryErrorMessage = null;
    if (urlParams.has('error')) {
        primaryErrorMessage = decodeURIComponent(urlParams.get('error'));
    } else if (urlParams.has('step_error')) {
        primaryErrorMessage = decodeURIComponent(urlParams.get('step_error'));
    } else if (urlParams.has('error_message')) {
        primaryErrorMessage = decodeURIComponent(urlParams.get('error_message'));
    }

    if (primaryErrorMessage) {
        showToast(primaryErrorMessage, 'error');
        errorToastShownFromUrl = true;
    }

    if (urlParams.has('setup_reason')) {
        const reason = urlParams.get('setup_reason');
        let message = '';
        let type = 'info'; 

        if (reason === 'core_data_missing_gender' || reason === 'nsl_new_user_core_data_missing') {
            message = "Welcome! To continue, please select your gender and role.";
        } else if (reason === 'core_data_missing_lookingfor' || reason === 'nsl_new_user_lookingfor_missing') {
            message = "Great! Now, tell us who you are looking for.";
        } else if (reason === 'step0_incomplete_from_dashboard' && (!urlParams.has('active') || parseInt(urlParams.get('active')) === 0) ) {
            message = "Welcome! Please define your 'Terms of Relationship' to proceed."; 
        } else if ((reason === 'step13_incomplete_from_dashboard' || reason === 'detail_step_13_incomplete_from_dashboard_check') && (!urlParams.has('active') || parseInt(urlParams.get('active')) === 13) ) {
            message = "A profile picture is required to access all features. Please upload at least one photo.";
            type = 'error'; 
        } else if (strpos(reason, '_incomplete_from_dashboard_check') !== false) {
            message = "Please complete this section to continue.";
            type = 'info';
        } else if (strpos(reason, 'error') !== false) { 
            message = "There was an issue: " + reason.replace(/_/g, ' ').replace('error', '').trim();
            type = 'error';
        }

        if (message) {
            if (type === 'error') {
                if (!errorToastShownFromUrl || (primaryErrorMessage && message !== primaryErrorMessage)) {
                    showToast(message, type);
                    errorToastShownFromUrl = true; 
                }
            } else { 
                showToast(message, type);
                infoToastShownFromUrl = true;
            }
        }
    }

    if (urlParams.has('profile_updated') && urlParams.get('profile_updated') === '1') {
        showToast('Profile updated successfully!', 'success');
        infoToastShownFromUrl = true;
    }
    if (urlParams.has('skipped_setup') && urlParams.get('skipped_setup') === '1') {
        showToast('Setup skipped. You can complete your profile later.', 'info');
        infoToastShownFromUrl = true;
    }
    if (urlParams.has('resend') && urlParams.get('resend') === '1') {
        showToast('Verification code has been resent to your email.', 'info');
        infoToastShownFromUrl = true;
    }

    const pageNotice = document.querySelector('.sud-page-notice');
    if (pageNotice && pageNotice.offsetParent !== null) {
        const messageText = pageNotice.textContent.trim();
        let type = 'info';
        if (pageNotice.classList.contains('sud-notice-error')) type = 'error';
        else if (pageNotice.classList.contains('sud-notice-success')) type = 'success';

        let shouldShowInline = true;
        if (type === 'error' && errorToastShownFromUrl && messageText === primaryErrorMessage) {
            shouldShowInline = false;
        } else if (type === 'info' && infoToastShownFromUrl) {

            const setupReasonParam = urlParams.get('setup_reason');
            if (setupReasonParam) {
                if ((setupReasonParam === 'core_data_missing_gender' || setupReasonParam === 'nsl_new_user_core_data_missing') && messageText.startsWith("Welcome! To continue, please select your gender")) shouldShowInline = false;
                if ((setupReasonParam === 'core_data_missing_lookingfor' || setupReasonParam === 'nsl_new_user_lookingfor_missing') && messageText.startsWith("Great! Now, tell us who you are looking for")) shouldShowInline = false;
            }
        }

        if (shouldShowInline) {
            showToastFromElement('.sud-page-notice', type, 'Notice from page.', true);
        } else {
            pageNotice.style.display = 'none'; 
        }
    }

    const errorAlert = document.querySelector('.sud-error-alert');
    if (errorAlert && errorAlert.offsetParent !== null) {
        const alertText = errorAlert.textContent.trim();

        if (!errorToastShownFromUrl || (primaryErrorMessage && alertText !== primaryErrorMessage)) {
            showToastFromElement('.sud-error-alert', 'error', 'An error occurred.', true);
        } else {
            errorAlert.style.display = 'none'; 
        }
    }

    const noticeInfo = document.querySelector('.sud-notice-info');
    if (noticeInfo && noticeInfo.offsetParent !== null) {
        const noticeText = noticeInfo.textContent.trim();
        let shouldShowNoticeInfo = true;
        if(infoToastShownFromUrl) {
            const reason = urlParams.get('setup_reason');
            if (reason === 'step0_incomplete_from_dashboard' && noticeText === "Welcome! Please define your 'Terms of Relationship' to proceed.") {
                shouldShowNoticeInfo = false;
            } else if (strpos(reason, '_incomplete_from_dashboard_check') !== false && noticeText === "Please complete this section to continue.") {
                shouldShowNoticeInfo = false;
            }
        }

        if (shouldShowNoticeInfo) {
            showToastFromElement('.sud-notice-info', 'info', 'Please note.', true);
        } else {
            noticeInfo.style.display = 'none';
        }
    }

    const genderForm = document.getElementById('gender-form');
    const optionButtons = genderForm ? genderForm.querySelectorAll('.sud-option-button') : null;
    const hiddenInput = genderForm ? genderForm.querySelector('input[name="gender"]') : null;
    const roleModal = document.getElementById('role-modal');
    const genderError = genderForm ? genderForm.querySelector('.sud-error-message') : null;

 

    if (optionButtons && optionButtons.length > 0 && hiddenInput) {
        optionButtons.forEach(button => {
            button.addEventListener('click', function() {
                optionButtons.forEach(btn => btn.classList.remove('sud-active'));
                this.classList.add('sud-active');
                const value = this.getAttribute('data-value');
                hiddenInput.value = value;
                if (genderError) genderError.style.display = 'none';

                if (value === 'LGBTQ+') {
                    if (roleModal) {
                        const selectedRole = roleModal.querySelector('input[name="role"]:checked');
                        if (selectedRole) selectedRole.checked = false;
                        const continueModalBtn = roleModal.querySelector('.sud-modal-continue');
                        if (continueModalBtn) continueModalBtn.disabled = true;
                        const modalError = roleModal.querySelector('.sud-modal-error-message');
                        if (modalError) modalError.style.display = 'none';

                        roleModal.classList.add('show'); 
                    } else {
                        console.error("Role modal not found!");
                        submitGenderSelection(value); 
                    }
                } else {
                    submitGenderSelection(value);
                }
            });
        });
    }

    function submitGenderSelection(gender, role) {
        if (!genderForm) {
            console.error("Gender form not found!");
            window.location.href = 'looking-for'; 
            return;
        }

        showLoader();

        const hiddenInput = genderForm.querySelector('input[name="gender"]');
        if (hiddenInput) hiddenInput.value = gender;

        if (role) {
            let roleInput = genderForm.querySelector('input[name="role"]');
            if (!roleInput) {
                roleInput = document.createElement('input');
                roleInput.type = 'hidden';
                roleInput.name = 'role';
                genderForm.appendChild(roleInput);
            }
            roleInput.value = role;
        }
        genderForm.submit();
    }

    if (roleModal) {
        const closeBtn = roleModal.querySelector('.sud-modal-close');
        const continueModalBtn = roleModal.querySelector('.sud-modal-continue');
        const modalError = roleModal.querySelector('.sud-modal-error-message');
        const roleRadios = roleModal.querySelectorAll('input[name="role"]');
        const backdrop = roleModal.querySelector('.sud-modal-backdrop');
        const modalContent = roleModal.querySelector('.sud-modal-content');

        if (closeBtn) closeBtn.addEventListener('click', () => { roleModal.classList.remove('show'); });
        if (backdrop) backdrop.addEventListener('click', () => { roleModal.classList.remove('show'); });
        if (modalContent) modalContent.addEventListener('click', e => e.stopPropagation());

        roleRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.checked) {
                    if (continueModalBtn) continueModalBtn.disabled = false;
                    if (modalError) modalError.style.display = 'none';
                }
            });
        });

        if (continueModalBtn) {
            continueModalBtn.addEventListener('click', function() {
                if (this.disabled) return;
                const selectedRoleRadio = roleModal.querySelector('input[name="role"]:checked');
                if (!selectedRoleRadio) {
                    if (modalError) { modalError.textContent = 'Please select a role'; modalError.style.display = 'block'; }
                    return;
                }
                const currentGender = hiddenInput ? hiddenInput.value : null; 
                if (currentGender === 'LGBTQ+') {
                    roleModal.classList.remove('show');
                    submitGenderSelection(currentGender, selectedRoleRadio.value); 
                } else {
                    console.error("Role modal continue clicked but gender not LGBTQ+?");
                    roleModal.classList.remove('show'); 
                }
            });
        }
    }

    const accountSignupForm = document.getElementById('account-signup-form');
    if (accountSignupForm) {
        const emailInput = accountSignupForm.querySelector('#email'); 
        const passwordInput = accountSignupForm.querySelector('#password');
        const retypePasswordInput = accountSignupForm.querySelector('#retype_password');
        const agreeTermsCheckbox = accountSignupForm.querySelector('#agree_terms'); 

        if (emailInput) emailInput.addEventListener('input', function() { validateEmail(this); });
        if (passwordInput) passwordInput.addEventListener('input', function() { validatePassword(this); });
        if (passwordInput && retypePasswordInput) {
            retypePasswordInput.addEventListener('input', function() {
                validatePasswordMatch(passwordInput, this);
            });
        }

         if (agreeTermsCheckbox) {
            agreeTermsCheckbox.addEventListener('change', function() {
                const errorMsg = this.closest('.sud-form-group')?.querySelector('.sud-error-message');
                if (this.checked && errorMsg) {
                    errorMsg.style.display = 'none';
                }
            });
         }

        accountSignupForm.addEventListener('submit', function(e) {
            let isValid = true; 

            if (emailInput && !validateEmail(emailInput)) isValid = false;
            if (passwordInput && !validatePassword(passwordInput)) isValid = false;
            if (passwordInput && retypePasswordInput && !validatePasswordMatch(passwordInput, retypePasswordInput)) isValid = false;

            if (agreeTermsCheckbox && !agreeTermsCheckbox.checked) {
                const errorMsg = agreeTermsCheckbox.closest('.sud-form-group')?.querySelector('.sud-error-message');
                if (errorMsg) {
                    errorMsg.textContent = 'You must agree to the terms'; 
                    errorMsg.style.display = 'block';
                }
                isValid = false;
            } else if (agreeTermsCheckbox) {
                const errorMsg = agreeTermsCheckbox.closest('.sud-form-group')?.querySelector('.sud-error-message');
                if (errorMsg) errorMsg.style.display = 'none';
            }

            if (!isValid) {
                e.preventDefault(); 
            } else {
                showLoader();
            }
        });
    }

    function fixMobileNavigation() {
        const stepForms = document.querySelectorAll('.sud-step-content form');

        const isReceiverRole = (typeof sudProfileData !== 'undefined') ? sudProfileData.isReceiverRole : false;
        const skippedReceiverSteps = (typeof sudProfileData !== 'undefined') ? sudProfileData.skippedReceiverSteps : [];
        const totalSteps = (typeof sudProfileData !== 'undefined') ? sudProfileData.totalSteps : 14;
        const initialTermsCount = (typeof sudProfileData !== 'undefined') ? sudProfileData.initialTermsCount : 0;
        const initialStylesCount = (typeof sudProfileData !== 'undefined') ? sudProfileData.initialStylesCount : 0;
        const sudBaseUrl = (typeof sud_config !== 'undefined' && sud_config.sud_url) ? sud_config.sud_url : '/sud';

        stepForms.forEach(form => {
            const stepContentDiv = form.closest('.sud-step-content');
            let stepNumber = 0;

            if (stepContentDiv && stepContentDiv.id) {
                const match = stepContentDiv.id.match(/step-(\d+)/);
                if (match && match[1]) {
                    stepNumber = parseInt(match[1], 10);
                }
            }

            let mobileNav = form.querySelector('.sud-mobile-navigation');
            if (!mobileNav) {
                mobileNav = document.createElement('div');
                mobileNav.className = 'sud-mobile-nav-container';
                form.appendChild(mobileNav);
            }
            mobileNav.innerHTML = '';

            const mainNavRow = document.createElement('div');
            mainNavRow.className = 'sud-mobile-main-nav';

            const skipNavRow = document.createElement('div');
            skipNavRow.className = 'sud-mobile-skip-nav';
            let skipLinksAdded = false;

            let actualPrevStep = stepNumber - 1;
            if (isReceiverRole && actualPrevStep >= 0) {
                while (actualPrevStep >= 0 && skippedReceiverSteps.includes(actualPrevStep)) {
                    actualPrevStep--;
                }
            }
            actualPrevStep = Math.max(0, actualPrevStep);
            
            if (stepNumber > 0) {
                const backBtn = document.createElement('a');
                backBtn.href = `profile-details?active=${actualPrevStep}`;
                backBtn.className = 'sud-prev-btn';
                backBtn.textContent = 'Back';
                mainNavRow.appendChild(backBtn);
            } else {
                const placeholder = document.createElement('div');
                mainNavRow.appendChild(placeholder);
            }

            let continueBtn = document.createElement('button');
            continueBtn.type = 'button';
            continueBtn.className = 'sud-next-btn';
            continueBtn.id = `step-${stepNumber}-action-btn`;

            let effectiveLastStep = totalSteps - 1;
            if (isReceiverRole) {
                for (let lastIdx = totalSteps - 1; lastIdx >= 0; lastIdx--) {
                    if (!skippedReceiverSteps.includes(lastIdx)) {
                        effectiveLastStep = lastIdx;
                        break;
                    }
                }
            }

            const noSkipOptionsOnTheseSteps = [0, effectiveLastStep];
            const maxTerms = 5;
            const maxStyles = 5;

            let isInitiallyDisabled = false;
            let initialButtonText = (stepNumber === effectiveLastStep) ? 'Finish' : 'Continue';

            if (stepNumber === 0) {
                isInitiallyDisabled = (initialTermsCount < 3);
                initialButtonText = `<span id="mobile-selection-counter-${stepNumber}">${initialTermsCount}/${maxTerms} Selected</span>`;
            } else if (stepNumber === 5) {
                isInitiallyDisabled = (initialStylesCount === 0);
                initialButtonText = `<span id="mobile-selection-counter-${stepNumber}">${initialStylesCount}/${maxStyles} Selected</span>`;
            } else if (stepNumber !== effectiveLastStep) {
                const hasInitialValue = form.querySelector('input[type="hidden"][value]:not([value=""]), input[type="text"][value]:not([value=""]), textarea:not(:placeholder-shown)') !== null;
                const isRequiredInputEmpty = form.querySelector('input[required]:not([type="hidden"]):placeholder-shown, textarea[required]:placeholder-shown') !== null;
                const hasRequiredFields = form.querySelectorAll('input[required], textarea[required]').length > 0;
                isInitiallyDisabled = (hasRequiredFields && !hasInitialValue);
            }

            continueBtn.innerHTML = initialButtonText;
            continueBtn.disabled = isInitiallyDisabled;
            if (!isInitiallyDisabled) {
                continueBtn.classList.add('active');
            }

            continueBtn.addEventListener('click', function() {
                if (!this.disabled) {
                    const parentForm = this.closest('form');
                    if (parentForm) {
                        showLoader();
                        parentForm.requestSubmit();
                    } else {
                        console.error("Could not find parent form for mobile continue button.");
                    }
                }
            });

            mainNavRow.appendChild(continueBtn);

            if (!noSkipOptionsOnTheseSteps.includes(stepNumber)) {
                const skipBtn = document.createElement('a');
                skipBtn.className = 'sud-skip-link';
                skipBtn.textContent = 'Skip';
                let skipTargetStep = stepNumber + 1;
                while (isReceiverRole && skippedReceiverSteps.includes(skipTargetStep) && skipTargetStep < totalSteps) {
                    skipTargetStep++;
                }
                skipTargetStep = Math.min(skipTargetStep, effectiveLastStep);
                skipBtn.href = `profile-details?active=${skipTargetStep}&skip=1`;
                skipNavRow.appendChild(skipBtn);

                const skipAllBtn = document.createElement('a');
                skipAllBtn.className = 'sud-skip-all-link';
                skipAllBtn.textContent = 'Skip All';
                
                const skipAllHrefJs = `profile-details?active=13&from_skip_all=1&error_message=${encodeURIComponent('Please upload a profile picture to complete setup.')}`;
                skipAllBtn.href = skipAllHrefJs;
                skipNavRow.appendChild(skipAllBtn);

                skipLinksAdded = true;
            }

            mobileNav.appendChild(mainNavRow);
            if (skipLinksAdded) {
               mobileNav.appendChild(skipNavRow);
            }
            setTimeout(() => validateAndToggleButton(form), 50);
        });
    }

    fixMobileNavigation();

    const allForms = document.querySelectorAll('.sud-step-content form'); 
    allForms.forEach(form => {
        form.addEventListener('click', function(event) {
            if (event.target.matches('.sud-option-button, .sud-term-tag, .sud-dating-style-tag, .sud-interest-tag')) {
                setTimeout(() => validateAndToggleButton(form), 50);
            }
        });

        form.addEventListener('input', function(event) {
            if (event.target.matches('input[type="text"], textarea')) {
                setTimeout(() => validateAndToggleButton(form), 50);
            }
        });

        if (!form.closest('.sud-step-content').style.display || form.closest('.sud-step-content').style.display !== 'none') {
            validateAndToggleButton(form);
        }
    });

    const interestsForm = document.getElementById('interests-form'); 
    if (interestsForm) {
        const interestTags = interestsForm.querySelectorAll('.sud-interest-tag'); 
        const MIN_INTERESTS = 3;
        const MAX_INTERESTS = 5;

        function updateInterestButtonState() {
            const selectedCount = interestsForm.querySelectorAll('.sud-interest-tag.selected').length;
            const mobileCounter = interestsForm.querySelector('#selection-counter'); 
            const desktopCounter = document.getElementById('desktop-selection-counter'); 

            if (mobileCounter) mobileCounter.textContent = `${selectedCount}/${MAX_INTERESTS} Selected`;
            if (desktopCounter) desktopCounter.textContent = `${selectedCount}/${MAX_INTERESTS} Selected`;

            if (selectedCount >= MIN_INTERESTS) {
                enableStepButtons(interestsForm);
            } else {
                disableStepButtons(interestsForm);
            }

            const errorMsg = interestsForm.querySelector('.sud-error-message');
            if (errorMsg && selectedCount >= MIN_INTERESTS) {
                errorMsg.style.display = 'none';
            }
        }

        interestTags.forEach(tag => {
            tag.addEventListener('click', function() {
                const selectedCount = interestsForm.querySelectorAll('.sud-interest-tag.selected').length;
                if (this.classList.contains('selected')) {
                    this.classList.remove('selected');
                } else if (selectedCount < MAX_INTERESTS) {
                    this.classList.add('selected');
                }

                const selectedValues = Array.from(interestsForm.querySelectorAll('.sud-interest-tag.selected'))
                                           .map(t => t.getAttribute('data-value'));
                const hiddenInterestInput = interestsForm.querySelector('input[name="interests"]'); 
                if (hiddenInterestInput) {
                    hiddenInterestInput.value = JSON.stringify(selectedValues); 
                } else {
                    console.warn("Hidden input for interests not found");
                }
                updateInterestButtonState();
            });
        });
        updateInterestButtonState();
    }

    const desktopBtn = document.getElementById('desktop-action-btn');
    const multiStepContainer = document.querySelector('.sud-multi-step-container'); 

    if (desktopBtn && multiStepContainer) {
        desktopBtn.addEventListener('click', function(e) {
            if (!this.disabled && !this.classList.contains('disabled')) {
                const activeStepForm = multiStepContainer.querySelector('.sud-step-content:not([style*="display: none"]) form');
                if (activeStepForm) {
                    const mobileBtnEquivalent = activeStepForm.querySelector('.sud-mobile-nav-container .sud-next-btn');
                    if (mobileBtnEquivalent && !mobileBtnEquivalent.disabled) {
                        showLoader();
                        activeStepForm.requestSubmit();
                    } else {
                        e.preventDefault();
                    }
                } else {
                    e.preventDefault();
                }
            } else {
                e.preventDefault();
            }
        });
    }

    const activeStepDot = document.querySelector('.sud-step-dot.active');
    if (activeStepDot) {
        const dotElement = activeStepDot.querySelector('.dot');
        if (dotElement) {
            const stepIndexAttr = activeStepDot.getAttribute('data-step');
            if (stepIndexAttr !== null) {
                const stepNumber = parseInt(stepIndexAttr, 10);
                const iconClasses = ['fa-search', 'fa-money-bill-wave', 'fa-university', 'fa-hand-holding-usd', 'fa-heart', 'fa-heartbeat', 'fa-briefcase', 'fa-users', 'fa-users', 'fa-smoking', 'fa-glass-martini', 'fa-file-alt', 'fa-user-check', 'fa-camera'];
                if (!isNaN(stepNumber) && stepNumber >= 0 && stepNumber < iconClasses.length) {
                    const icon = document.createElement('i');
                    icon.className = 'fas ' + iconClasses[stepNumber];
                    dotElement.parentNode.replaceChild(icon, dotElement);
                }
            }
        }
    }

    const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
    const mobileMenuClose = document.querySelector('.mobile-menu-close');
    const navLinks = document.querySelector('.nav-links');
    const menuOverlay = document.querySelector('.menu-overlay');
    const userDropdown = document.querySelector('.user-dropdown');
    const dropdownToggle = userDropdown ? userDropdown.querySelector('.dropdown-toggle') : null;
    
    const isMobileView = () => window.innerWidth <= 768;
    
    // Mobile menu functionality
    if (mobileMenuToggle && navLinks) {
        mobileMenuToggle.addEventListener('click', function() {
            navLinks.classList.add('active');
            if (menuOverlay) menuOverlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        });
    }
    
    if (mobileMenuClose && navLinks) {
        mobileMenuClose.addEventListener('click', function() {
            navLinks.classList.remove('active');
            if (menuOverlay) menuOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }
    
    if (menuOverlay) {
        menuOverlay.addEventListener('click', function() {
            navLinks.classList.remove('active');
            menuOverlay.classList.remove('active');
            document.body.style.overflow = '';
        });
    }

    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && navLinks && navLinks.classList.contains('active')) {
            navLinks.classList.remove('active');
            if (menuOverlay) menuOverlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    // Handle desktop dropdown menu
    if (userDropdown && dropdownToggle && !isMobileView()) {
        let closeTimeout;
        
        dropdownToggle.addEventListener('click', function(e) {
            if (isMobileView()) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            clearTimeout(closeTimeout);
            userDropdown.classList.toggle('active');

            const expanded = userDropdown.classList.contains('active');
            dropdownToggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        });

        userDropdown.addEventListener('mouseenter', function() {
            if (isMobileView()) return;
            
            clearTimeout(closeTimeout);
            userDropdown.classList.add('active');
            dropdownToggle.setAttribute('aria-expanded', 'true');
        });
        
        userDropdown.addEventListener('mouseleave', function() {
            if (isMobileView()) return;
            
            clearTimeout(closeTimeout);
            closeTimeout = setTimeout(() => {
                userDropdown.classList.remove('active');
                dropdownToggle.setAttribute('aria-expanded', 'false');
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (isMobileView()) return;
            
            if (!userDropdown.contains(e.target)) {
                userDropdown.classList.remove('active');
                dropdownToggle.setAttribute('aria-expanded', 'false');
            }
        });
    }
}); 

function validateEmail(input) {
    if (!input) return false;
    const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const errorMsg = input.parentElement.querySelector('.sud-error-message'); 
    if (!errorMsg) return true; 

    if (!input.value.trim()) {
        errorMsg.textContent = 'Email address is required';
        errorMsg.style.display = 'block';
        return false;
    } else if (!emailPattern.test(input.value)) {
        errorMsg.textContent = 'Please enter a valid email address';
        errorMsg.style.display = 'block';
        return false;
    } else {
        errorMsg.style.display = 'none';
        return true;
    }
}

function validatePassword(input) {
    if (!input) return false;
    const errorMsg = input.parentElement.querySelector('.sud-error-message'); 
    if (!errorMsg) return true;

    if (!input.value) {
        errorMsg.textContent = 'Password is required';
        errorMsg.style.display = 'block';
        return false;
    } else if (input.value.length < 6) {
        errorMsg.textContent = 'Password must be at least 6 characters long';
        errorMsg.style.display = 'block';
        return false;
    } else if (!/[A-Z]/.test(input.value)) { 
        errorMsg.textContent = 'Password must include at least one uppercase letter';
        errorMsg.style.display = 'block';
        return false;
    } 
    else {
        errorMsg.style.display = 'none';
        return true;
    }
}

function validatePasswordMatch(passwordInput, retypeInput) {
    if (!passwordInput || !retypeInput) return false;
    const errorMsg = retypeInput.parentElement.querySelector('.sud-error-message'); 
    if (!errorMsg) return true;

    if (!retypeInput.value) {
        errorMsg.textContent = 'Please retype your password';
        errorMsg.style.display = 'block';
        return false;
    } else if (passwordInput.value !== retypeInput.value) {
        errorMsg.textContent = 'Passwords do not match';
        errorMsg.style.display = 'block';
        return false;
    } else {
        errorMsg.style.display = 'none';
        return true;
    }
}