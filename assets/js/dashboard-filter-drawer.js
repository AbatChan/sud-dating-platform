document.addEventListener('DOMContentLoaded', function() {
    createMobileFilterElements();

    const filterToggleElement = document.querySelector('.mobile-filter-toggle');
    if (filterToggleElement) {
        makeElementDraggable(filterToggleElement);
    }

    initMobileFilterDrawer();
    initDrawerDrag();
    initOriginalDashboardFilter();
});

function createMobileFilterElements() {
    const filterToggle = document.createElement('div');
    filterToggle.className = 'mobile-filter-toggle';
    filterToggle.innerHTML = '<i class="fas fa-filter"></i>';
    document.body.appendChild(filterToggle);

    const filterOverlay = document.createElement('div');
    filterOverlay.className = 'mobile-filter-overlay';
    document.body.appendChild(filterOverlay);

    const filterDrawer = document.createElement('div');
    filterDrawer.className = 'mobile-filter-drawer';
    filterDrawer.innerHTML = `
        <div class="drawer-drag-handle"></div>
        <div class="mobile-filter-drawer-header">
            <h2>Filter Members</h2>
            <button class="mobile-filter-drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="mobile-filter-drawer-content">
            <!-- Filter content will be cloned here -->
        </div>
        <div class="mobile-filter-drawer-actions">
            <button type="button" class="btn-primary mobile-apply-filters">Apply Filters</button>
            <button type="button" class="btn-secondary mobile-reset-filters">Reset Filters</button>
        </div>
    `;
    document.body.appendChild(filterDrawer);

    const desktopFilterForm = document.querySelector('#dashboard-filter-form');
    if (desktopFilterForm) {
        const clonedContent = desktopFilterForm.cloneNode(true);
        clonedContent.id = 'mobile-dashboard-filter-form';
        document.querySelector('.mobile-filter-drawer-content').appendChild(clonedContent);
    }
}

function initMobileFilterDrawer() {
    const filterToggle = document.querySelector('.mobile-filter-toggle');
    const filterDrawer = document.querySelector('.mobile-filter-drawer');
    const filterOverlay = document.querySelector('.mobile-filter-overlay');
    const closeButton = document.querySelector('.mobile-filter-drawer-close');

    if (filterToggle) {
        filterToggle.addEventListener('click', function() {
            // Add delay to ensure config is loaded
            setTimeout(function() {
                if (window.sud_config && !window.sud_config.is_premium_user) {

                    const dashboardPremiumModal = document.getElementById('dashboard-premium-modal');
                    if (dashboardPremiumModal) {
                        dashboardPremiumModal.classList.add('show');
                    }
                    return;
                }
                toggleFilterDrawer();
            }, 10);
        });
    }

    if (filterOverlay) {
        filterOverlay.addEventListener('click', function() {
            closeFilterDrawer();
        });
    }

    if (closeButton) {
        closeButton.addEventListener('click', function() {
            closeFilterDrawer();
        });
    }

    const applyButton = document.querySelector('.mobile-apply-filters');
    if (applyButton) {
        applyButton.addEventListener('click', function() {

            syncFormValues('mobile-dashboard-filter-form', 'dashboard-filter-form');

            const desktopApplyButton = document.getElementById('apply-dashboard-filters');
            if (desktopApplyButton) {
                desktopApplyButton.click();
            }

            closeFilterDrawer();
        });
    }

    const resetButton = document.querySelector('.mobile-reset-filters');
    if (resetButton) {
        resetButton.addEventListener('click', function() {
            resetFilters();
        });
    }

    const mobileCheckboxes = document.querySelectorAll('#mobile-dashboard-filter-form .checkbox-label input[type="checkbox"]');
    mobileCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const label = this.closest('.checkbox-label');
            if (label) {
                label.classList.toggle('selected', this.checked);
            }
        });
    });
}

function toggleFilterDrawer() {
    const filterToggle = document.querySelector('.mobile-filter-toggle');
    const filterDrawer = document.querySelector('.mobile-filter-drawer');
    const filterOverlay = document.querySelector('.mobile-filter-overlay');

    if (filterToggle && filterDrawer && filterOverlay) {
        filterToggle.classList.toggle('active');
        filterDrawer.classList.toggle('open');
        filterOverlay.style.display = filterDrawer.classList.contains('open') ? 'block' : 'none';

        if (filterDrawer.classList.contains('open')) {
            setTimeout(function() {
                filterOverlay.classList.add('show');
            }, 10);
            document.body.classList.add('body-no-scroll');

            syncFormValues('dashboard-filter-form', 'mobile-dashboard-filter-form');
        } else {
            filterOverlay.classList.remove('show');
            document.body.classList.remove('body-no-scroll');
        }
    }
}

function closeFilterDrawer() {
    const filterToggle = document.querySelector('.mobile-filter-toggle');
    const filterDrawer = document.querySelector('.mobile-filter-drawer');
    const filterOverlay = document.querySelector('.mobile-filter-overlay');

    if (filterToggle && filterDrawer && filterOverlay) {
        filterToggle.classList.remove('active');
        filterDrawer.classList.remove('open');
        filterOverlay.classList.remove('show');

        setTimeout(function() {
            filterOverlay.style.display = 'none';
        }, 300);

        document.body.classList.remove('body-no-scroll');
    }
}

function resetFilters() {
    const mobileForm = document.getElementById('mobile-dashboard-filter-form');
    if (mobileForm) {
        mobileForm.reset();

        mobileForm.querySelectorAll('.checkbox-label').forEach(label => {
            label.classList.remove('selected');
        });
    }

    const desktopForm = document.getElementById('dashboard-filter-form');
    if (desktopForm) {
        desktopForm.reset();

        desktopForm.querySelectorAll('.checkbox-label').forEach(label => {
            label.classList.remove('selected');
        });
    }

    window.location.href = window.location.pathname;
}

function syncFormValues(sourceFormId, targetFormId) {
    const sourceForm = document.getElementById(sourceFormId);
    const targetForm = document.getElementById(targetFormId);

    if (!sourceForm || !targetForm) return;

    sourceForm.querySelectorAll('input, select').forEach(input => {
        const targetInput = targetForm.querySelector(`[name="${input.name}"]`);
        if (targetInput) {
            if (input.type === 'checkbox' || input.type === 'radio') {
                targetInput.checked = input.checked;

                if (input.type === 'checkbox') {
                    const sourceLabel = input.closest('.checkbox-label');
                    const targetLabel = targetInput.closest('.checkbox-label');

                    if (sourceLabel && targetLabel) {
                        targetLabel.classList.toggle('selected', input.checked);
                    }
                }
            } else {
                targetInput.value = input.value;
            }
        }
    });
}

function initDrawerDrag() {
    const dragHandle = document.querySelector('.drawer-drag-handle');
    const drawer = document.querySelector('.mobile-filter-drawer');
    let startY, currentTranslate = 0;

    if (!dragHandle || !drawer) return;

    dragHandle.addEventListener('touchstart', dragStart);
    dragHandle.addEventListener('touchmove', dragMove);
    dragHandle.addEventListener('touchend', dragEnd);

    dragHandle.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', dragMove);
    document.addEventListener('mouseup', dragEnd);

    function dragStart(e) {
        if (!drawer.classList.contains('open')) return;

        if (e.type === 'touchstart') {
            startY = e.touches[0].clientY;
        } else {
            startY = e.clientY;
            document.body.style.userSelect = 'none';
        }

        dragHandle.classList.add('dragging');
    }

    function dragMove(e) {
        if (!dragHandle.classList.contains('dragging')) return;

        let currentY;
        if (e.type === 'touchmove') {
            currentY = e.touches[0].clientY;
        } else {
            currentY = e.clientY;
        }

        const deltaY = currentY - startY;

        if (deltaY < 0) return;

        const dragResistance = 0.5;
        currentTranslate = deltaY * dragResistance;

        drawer.style.transform = `translateY(${currentTranslate}px)`;

        if (currentTranslate > 150) {
            closeFilterDrawer();
            dragHandle.classList.remove('dragging');
            resetDrawerPosition();
        }
    }

    function dragEnd() {
        if (!dragHandle.classList.contains('dragging')) return;

        dragHandle.classList.remove('dragging');
        document.body.style.userSelect = '';

        resetDrawerPosition();
    }

    function resetDrawerPosition() {
        drawer.style.transform = '';
        currentTranslate = 0;
    }
}

function makeElementDraggable(element) {
    if (!element) return;

    let isDragging = false; 
    let didMove = false;   
    let startX, startY, initialLeft, initialTop;
    const storageKey = 'filterTogglePosition';
    const dragThreshold = 5; 

    function touchStart(e) {
        if (e.touches.length !== 1) return;
        const touch = e.touches[0];
        startX = touch.clientX;
        startY = touch.clientY;
        startInteraction(e);
    }

    function touchMove(e) {
        if (!isDragging) return;

        const touch = e.touches[0];
        const currentX = touch.clientX;
        const currentY = touch.clientY;

        if (!didMove) {
            const deltaX = Math.abs(currentX - startX);
            const deltaY = Math.abs(currentY - startY);
            if (deltaX > dragThreshold || deltaY > dragThreshold) {
                didMove = true; 
                startDragVisuals();
            }
        }

        if (didMove) {
            moveElement(currentX, currentY, e);
        }
    }

    function touchEnd(e) {
        endInteraction(e);
    }

    function mouseDown(e) {
        if (e.button !== 0) return;
        startX = e.clientX;
        startY = e.clientY;
        startInteraction(e);
    }

    function mouseMove(e) {
        if (!isDragging) return;

        const currentX = e.clientX;
        const currentY = e.clientY;

        if (!didMove) {
           const deltaX = Math.abs(currentX - startX);
           const deltaY = Math.abs(currentY - startY);
            if (deltaX > dragThreshold || deltaY > dragThreshold) {
                didMove = true; 
                startDragVisuals();
            }
        }

        if (didMove) {
            moveElement(currentX, currentY, e);
        }
    }

    function mouseUp(e) {
        endInteraction(e);
    }

    function startInteraction(e) {
        isDragging = true; 
        didMove = false;   

        const rect = element.getBoundingClientRect();
        initialLeft = rect.left;
        initialTop = rect.top;

        document.addEventListener('touchmove', touchMove, { passive: false }); 
        document.addEventListener('touchend', touchEnd);
        document.addEventListener('mousemove', mouseMove);
        document.addEventListener('mouseup', mouseUp);
    }

     function startDragVisuals() {
        element.classList.add('dragging');
     }

    function moveElement(currentX, currentY, e) {
        e.preventDefault();

        const deltaX = currentX - startX;
        const deltaY = currentY - startY;

        let newLeft = initialLeft + deltaX;
        let newTop = initialTop + deltaY;

        const elementWidth = element.offsetWidth;
        const elementHeight = element.offsetHeight;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        newLeft = Math.max(0, Math.min(newLeft, viewportWidth - elementWidth));
        newTop = Math.max(0, Math.min(newTop, viewportHeight - elementHeight));

        element.style.left = `${newLeft}px`;
        element.style.top = `${newTop}px`;
        element.style.bottom = 'auto';
        element.style.right = 'auto';
    }

    function endInteraction(e) {
        if (!isDragging) return;

        document.removeEventListener('touchmove', touchMove);
        document.removeEventListener('touchend', touchEnd);
        document.removeEventListener('mousemove', mouseMove);
        document.removeEventListener('mouseup', mouseUp);

        if (didMove) {
            element.classList.remove('dragging');

            try {
                const finalTop = parseFloat(element.style.top);
                const finalLeft = parseFloat(element.style.left);
                 if (!isNaN(finalTop) && !isNaN(finalLeft)) {
                     localStorage.setItem(storageKey, JSON.stringify({ top: finalTop, left: finalLeft }));
                 }
            } catch (err) {
                console.warn('Could not save filter toggle position to localStorage:', err);
            }
        } else {
            toggleFilterDrawer(); 
        }

        isDragging = false;
        didMove = false;
    }

    function setInitialPosition() {
        let initialPos = null;
        try {
            const savedPosition = localStorage.getItem(storageKey);
            if (savedPosition) {
                initialPos = JSON.parse(savedPosition);
            }
        } catch (err) {
            console.warn('Could not load filter toggle position from localStorage:', err);
        }

        const elementWidth = element.offsetWidth || 50;
        const elementHeight = element.offsetHeight || 50;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        let targetLeft, targetTop;

        if (initialPos && typeof initialPos.top === 'number' && typeof initialPos.left === 'number') {
            targetLeft = Math.max(0, Math.min(initialPos.left, viewportWidth - elementWidth));
            targetTop = Math.max(0, Math.min(initialPos.top, viewportHeight - elementHeight));
        } else {
            targetLeft = viewportWidth - elementWidth - 20; 
            targetTop = viewportHeight - elementHeight - 20; 
        }
        element.style.left = `${targetLeft}px`;
        element.style.top = `${targetTop}px`;
        element.style.bottom = 'auto';
        element.style.right = 'auto';
    }

    setTimeout(setInitialPosition, 0);

    window.addEventListener('resize', () => {
        const currentTop = parseFloat(element.style.top);
        const currentLeft = parseFloat(element.style.left);
        const elementWidth = element.offsetWidth || 50;
        const elementHeight = element.offsetHeight || 50;
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;

        if (isNaN(currentLeft) || isNaN(currentTop) || currentLeft > viewportWidth - elementWidth || currentTop > viewportHeight - elementHeight) {
            setTimeout(setInitialPosition, 0);
        } else {
            element.style.left = `${Math.max(0, Math.min(currentLeft, viewportWidth - elementWidth))}px`;
            element.style.top = `${Math.max(0, Math.min(currentTop, viewportHeight - elementHeight))}px`;
        }

    });

    element.addEventListener('touchstart', touchStart, { passive: true }); 
    element.addEventListener('mousedown', mouseDown);
}

function initOriginalDashboardFilter() {
    const filterBtn = document.getElementById('dashboard-filter-btn');
    const filterModal = document.getElementById('dashboard-filter-modal');
    const closeModalBtns = document.querySelectorAll('.close-modal, .close-modal-btn');

    if (filterBtn) {
        filterBtn.addEventListener('click', function() {
            if (this.classList.contains('premium-only-btn')) {
                return;
            }

            if (window.matchMedia('(max-width: 992px)').matches) {
                toggleFilterDrawer();
            } else {
                if (filterModal) {
                    filterModal.classList.add('show');
                }
            }
        });
    }

    closeModalBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const parentModal = this.closest('.modal');
            if (parentModal) {
                parentModal.classList.remove('show');
            }
        });
    });

    const checkboxLabels = document.querySelectorAll('#dashboard-filter-form .checkbox-label');
    checkboxLabels.forEach(function(label) {
        const checkbox = label.querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.addEventListener('change', function() {
                label.classList.toggle('selected', this.checked);
            });
            label.classList.toggle('selected', checkbox.checked);
        }
    });

    window.addEventListener('click', function(e) {
        if (e.target === filterModal) {
            filterModal.classList.remove('show');
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (filterModal && filterModal.classList.contains('show')) {
                filterModal.classList.remove('show');
            }
            if (document.querySelector('.mobile-filter-drawer.open')) {
                closeFilterDrawer();
            }
        }
    });
}