document.addEventListener('DOMContentLoaded', function() {
    createMobileFilterElements();
    
    const filterToggleElement = document.querySelector('.mobile-filter-toggle');
    if (filterToggleElement) {
        makeElementDraggable(filterToggleElement);
    }

    initMobileFilterDrawer();
    initDrawerDrag();
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
            <h2>Search Filters</h2>
            <button class="mobile-filter-drawer-close"><i class="fas fa-times"></i></button>
        </div>
        <div class="mobile-filter-drawer-content">
            <!-- Filter content will be cloned here -->
        </div>
        <div class="mobile-filter-drawer-actions">
            <button type="submit" class="btn-primary" form="mobile-search-form">Search</button>
            <button type="reset" class="btn-secondary" form="mobile-search-form">Reset</button>
        </div>
    `;
    document.body.appendChild(filterDrawer);

    const desktopSidebar = document.querySelector('.sidebar-container');
    if (desktopSidebar) {
        const filterContent = desktopSidebar.querySelector('form');
        if (filterContent) {
            const clonedContent = filterContent.cloneNode(true);
            clonedContent.id = 'mobile-search-form';

            const originalActions = clonedContent.querySelector('.filter-actions');
            if (originalActions) {
                originalActions.remove();
            }

            document.querySelector('.mobile-filter-drawer-content').appendChild(clonedContent);

            initMobileAgeSlider();
        }
    }
}

function initMobileFilterDrawer() {
    const filterToggle = document.querySelector('.mobile-filter-toggle');
    const filterDrawer = document.querySelector('.mobile-filter-drawer');
    const filterOverlay = document.querySelector('.mobile-filter-overlay');
    const closeButton = document.querySelector('.mobile-filter-drawer-close');

    filterToggle.addEventListener('click', function() {
        // Add delay to ensure config is loaded
        setTimeout(function() {
            // Check if user is premium
            if (window.sud_config && !window.sud_config.is_premium_user) {
                // Show premium modal instead of filter drawer
                const searchPremiumModal = document.getElementById('search-premium-modal');
                if (searchPremiumModal) {
                    searchPremiumModal.classList.add('show');
                }
                return;
            }
            toggleFilterDrawer();
        }, 10);
    });

    filterOverlay.addEventListener('click', function() {
        closeFilterDrawer();
    });

    closeButton.addEventListener('click', function() {
        closeFilterDrawer();
    });

    const mobileSearchForm = document.getElementById('mobile-search-form');
    if (mobileSearchForm) {
        mobileSearchForm.addEventListener('submit', function() {
            closeFilterDrawer();
        });

        const resetButton = mobileSearchForm.querySelector('button[type="reset"]');
        if (resetButton) {
            resetButton.addEventListener('click', function(e) {
                e.preventDefault();
                resetFilters();
            });
        }
    }

    const resetActionButton = document.querySelector('.mobile-filter-drawer-actions .btn-secondary');
    if (resetActionButton) {
        resetActionButton.addEventListener('click', function(e) {
            e.preventDefault();
            resetFilters();
        });
    }
}

function toggleFilterDrawer() {
    const filterToggle = document.querySelector('.mobile-filter-toggle');
    const filterDrawer = document.querySelector('.mobile-filter-drawer');
    const filterOverlay = document.querySelector('.mobile-filter-overlay');

    filterToggle.classList.toggle('active');
    filterDrawer.classList.toggle('open');
    filterOverlay.style.display = filterDrawer.classList.contains('open') ? 'block' : 'none';

    if (filterDrawer.classList.contains('open')) {
        setTimeout(function() {
            filterOverlay.classList.add('show');
        }, 10);
        document.body.classList.add('body-no-scroll');
    } else {
        filterOverlay.classList.remove('show');
        document.body.classList.remove('body-no-scroll');
    }
}

function closeFilterDrawer() {
    const filterToggle = document.querySelector('.mobile-filter-toggle');
    const filterDrawer = document.querySelector('.mobile-filter-drawer');
    const filterOverlay = document.querySelector('.mobile-filter-overlay');

    filterToggle.classList.remove('active');
    filterDrawer.classList.remove('open');
    filterOverlay.classList.remove('show');

    setTimeout(function() {
        filterOverlay.style.display = 'none';
    }, 300);
    document.body.classList.remove('body-no-scroll');
}

function resetFilters() {
    const mobileSearchForm = document.getElementById('mobile-search-form');

    if (mobileSearchForm) {
        mobileSearchForm.reset();
        resetMobileAgeSlider();
        mobileSearchForm.querySelectorAll('.filter-options label.selected').forEach(label => {
            label.classList.remove('selected');
        });
    }

    const desktopForm = document.getElementById('search-form');
    if (desktopForm) {
        desktopForm.reset();

        const ageMinInput = document.getElementById('min-age');
        const ageMaxInput = document.getElementById('max-age');

        if (ageMinInput && ageMaxInput) {
            ageMinInput.value = 18;
            ageMaxInput.value = 70;

            const ageDisplay = document.getElementById('age-display');
            if (ageDisplay) {
                ageDisplay.textContent = '18 - 70 yo';
            }

            const minHandle = document.querySelector('.sud-slider-handle-min');
            const maxHandle = document.querySelector('.sud-slider-handle-max');
            const connectElement = document.querySelector('.sud-slider-connect');

            if (minHandle && maxHandle && connectElement) {
                minHandle.style.left = '0%';
                maxHandle.style.left = '100%';
                connectElement.style.left = '0%';
                connectElement.style.width = '100%';
            }
        }

        desktopForm.querySelectorAll('.filter-options label.selected').forEach(label => {
            label.classList.remove('selected');
        });
    }
    window.location.href = window.location.pathname;
}

function initMobileAgeSlider() {
    const ageSlider = document.querySelector('#mobile-search-form .sud-age-slider');
    if (ageSlider) {
        if (ageSlider.querySelector('.sud-slider-handle') === null) {
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

            const ageMinInput = document.querySelector('#mobile-search-form #min-age');
            const ageMaxInput = document.querySelector('#mobile-search-form #max-age');
            const ageDisplay = document.querySelector('#mobile-search-form #age-display');

            let ageMin = parseInt(ageMinInput.value) || 18;
            let ageMax = parseInt(ageMaxInput.value) || 70;
            const MIN_AGE = 18;
            const MAX_AGE = 70;
            const RANGE = MAX_AGE - MIN_AGE;

            function positionFromAge(age) {
                return ((Math.max(MIN_AGE, Math.min(MAX_AGE, age)) - MIN_AGE) / RANGE) * 100;
            }

            function ageFromPosition(position) {
                return Math.max(MIN_AGE, Math.min(MAX_AGE, Math.round(MIN_AGE + (position / 100) * RANGE)));
            }

            function updateHandlePositions() {
                const minPos = positionFromAge(ageMin);
                const maxPos = positionFromAge(ageMax);

                minHandle.style.left = minPos + '%';
                maxHandle.style.left = maxPos + '%';
                connectElement.style.left = minPos + '%';
                connectElement.style.width = (maxPos - minPos) + '%';

                if (ageDisplay) {
                    ageDisplay.textContent = ageMin + ' - ' + ageMax + ' yo';
                }
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
                document.addEventListener('touchmove', drag, { passive: false });
                document.addEventListener('touchend', stopDrag);
                document.body.style.userSelect = 'none';
            }

            function drag(e) {
                if (!isDragging) return;

                let clientX = e.touches ? e.touches[0].clientX : e.clientX;
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

            minHandle.addEventListener('mousedown', (e) => startDrag(e, minHandle));
            maxHandle.addEventListener('mousedown', (e) => startDrag(e, maxHandle));
            minHandle.addEventListener('touchstart', (e) => startDrag(e, minHandle));
            maxHandle.addEventListener('touchstart', (e) => startDrag(e, maxHandle));
        }
    }
}

function resetMobileAgeSlider() {
    const minInput = document.querySelector('#mobile-search-form #min-age');
    const maxInput = document.querySelector('#mobile-search-form #max-age');

    if (minInput && maxInput) {
        minInput.value = 18;
        maxInput.value = 70;

        const ageDisplay = document.querySelector('#mobile-search-form #age-display');
        if (ageDisplay) {
            ageDisplay.textContent = '18 - 70 yo';
        }

        const minHandle = document.querySelector('#mobile-search-form .sud-slider-handle-min');
        const maxHandle = document.querySelector('#mobile-search-form .sud-slider-handle-max');
        const connectElement = document.querySelector('#mobile-search-form .sud-slider-connect');

        if (minHandle && maxHandle && connectElement) {
            const MIN_AGE = 18;
            const MAX_AGE = 70;
            const RANGE = MAX_AGE - MIN_AGE;

            const minPos = 0;
            const maxPos = 100;

            minHandle.style.left = minPos + '%';
            maxHandle.style.left = maxPos + '%';
            connectElement.style.left = minPos + '%';
            connectElement.style.width = (maxPos - minPos) + '%';
        }
    }
}

function initDrawerDrag() {
    const dragHandle = document.querySelector('.drawer-drag-handle');
    const drawer = document.querySelector('.mobile-filter-drawer');
    let startY, startTransform, currentTranslate = 0;

    if (!dragHandle || !drawer) return;

    dragHandle.addEventListener('touchstart', dragStart);
    dragHandle.addEventListener('touchmove', dragMove);
    dragHandle.addEventListener('touchend', dragEnd);
    dragHandle.addEventListener('mousedown', dragStart);
    document.addEventListener('mousemove', dragMove);
    document.addEventListener('mouseup', dragEnd);

    function dragStart(e) {
        if (e.type === 'touchstart') {
            startY = e.touches[0].clientY;
        } else {
            startY = e.clientY;
            document.body.style.userSelect = 'none';
        }

        const style = window.getComputedStyle(drawer);
        const transform = style.getPropertyValue('transform');
        if (transform !== 'none') {
            const matrix = new DOMMatrix(transform);
            startTransform = matrix.m42; 
        } else {
            startTransform = 0;
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
        currentTranslate = Math.max(0, deltaY);
        const dragResistance = 0.5;
        const resistedTranslate = currentTranslate * dragResistance;

        drawer.style.transform = `translateY(${resistedTranslate}px)`;

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