(function($) {
  'use strict';

  let currentSection = '';
  let modalAutocompleteInstance;
  const MAX_VISIBLE_PHOTOS = 4;

  const attributeOptions = {
    gender: { 'Man': 'Man', 'Woman': 'Woman', 'LGBTQ+': 'LGBTQ+' },
    looking_for: { 'Man': 'Man', 'Woman': 'Woman', 'LGBTQ+': 'LGBTQ+' },
    smoke: { 'non_smoker': 'Non-Smoker', 'light_smoker': 'Light Smoker', 'heavy_smoker': 'Heavy Smoker' },
    drink: { 'non_drinker': 'Non-Drinker', 'social_drinker': 'Social Drinker', 'heavy_drinker': 'Heavy Drinker' },
    relationship_status: { 'single': 'Single', 'in_relationship': 'In a Relationship', 'married_looking': 'Married but Looking', 'separated': 'Separated', 'divorced': 'Divorced', 'widowed': 'Widowed' },
    ethnicity: { 'african': 'African', 'asian': 'Asian', 'caucasian': 'Caucasian', 'hispanic': 'Hispanic', 'middle_eastern': 'Middle Eastern', 'latino': 'Latino', 'native_american': 'Native American', 'pacific_islander': 'Pacific Islander', 'multiracial': 'Multiracial', 'other': 'Other' },
    race: { 'american': 'American', 'australian': 'Australian', 'austrian': 'Austrian', 'british': 'British', 'bulgarian': 'Bulgarian', 'canadian': 'Canadian', 'croatian': 'Croatian', 'czech': 'Czech', 'danish': 'Danish', 'dutch': 'Dutch', 'european': 'European', 'finnish': 'Finnish', 'french': 'French', 'german': 'German', 'greek': 'Greek', 'hungarian': 'Hungarian', 'irish': 'Irish', 'italian': 'Italian', 'new_zealander': 'New Zealander', 'norwegian': 'Norwegian', 'polish': 'Polish', 'portuguese': 'Portuguese', 'romanian': 'Romanian', 'russian': 'Russian', 'scottish': 'Scottish', 'serbian': 'Serbian', 'slovak': 'Slovak', 'spanish': 'Spanish', 'swedish': 'Swedish', 'swiss': 'Swiss', 'ukrainian': 'Ukrainian', 'welsh': 'Welsh' },
    dating_style: { 'animal_lovers': 'Animal Lovers', 'arts_culture': 'Arts & Culture Dates', 'beach_days': 'Beach Days', 'brunch_dates': 'Brunch Dates', 'clubbing': 'Clubbing & Partying', 'coffee_dates': 'Coffee Dates', 'comedy_night': 'Comedy Night', 'cooking_classes': 'Cooking Classes', 'crafting': 'Crafting Workshops', 'dinner_dates': 'Dinner Dates', 'drinks': 'Drinks', 'fitness_dates': 'Fitness Dates', 'foodie_dates': 'Foodie Dates', 'gaming': 'Gaming', 'lunch_dates': 'Lunch Dates', 'luxury': 'Luxury High-Tea', 'meet_tonight': 'Meet Tonight', 'movies': 'Movies & Chill', 'music_festivals': 'Music Festivals', 'nature': 'Nature & Outdoors', 'sailing': 'Sailing & Water Sports', 'shopping': 'Shopping', 'shows': 'Shows & Concerts', 'spiritual': 'Spiritual Journeys', 'travel': 'Travel', 'wine_tasting': 'Wine Tasting' },
    interests: { 'friends_benefits': 'Friends with Benefits', 'exclusive': 'Exclusive', 'ma': 'Monthly Allowance (MA)', 'ppm': 'Pay Per Meet (PPM)', 'dtf': 'DTF Tonight/Right Now', 'discreet': 'Discreet', 'high_net_worth': 'Access to High Net Worth Individuals', 'all_ethnicities': 'All Ethnicities', 'hookups': 'Hookups', 'in_relationship': 'In a Relationship', 'lgbtq': 'LGBTQ Friendly', 'marriage': 'Marriage', 'mentorship': 'Mentorship', 'no_strings': 'No Strings Attached', 'open_relationship': 'Open Relationship', 'passport_ready': 'Passport Ready', 'platonic': 'Platonic', 'serious': 'Serious Relationship', 'transgender': 'Transgender Friendly', 'travel_companion': 'Travel Companion', 'travel_to_you': 'Travel To You', 'weekly_allowance': 'Weekly Allowance (WA)' },
    annual_income: { '50000': '$50,000', '75000': '$75,000', '100000': '$100,000', '125000': '$125,000', '150000': '$150,000', '200000': '$200,000', '350000': '$350,000', '400000': '$400,000', '500000': '$500,000', '1000000': '$1,000,000', '1000000plus': '$1,000,000+' },
    net_worth: { '100000': '$100,000', '250000': '$250,000', '500000': '$500,000', '750000': '$750,000', '1000000': '$1,000,000', '2000000': '$2,000,000', '5000000': '$5,000,000', '10000000': '$10,000,000', '50000000': '$50,000,000', '100000000': '$100,000,000', '100000000plus': '$100,000,000+' },
    dating_budget: { '300-1000': '$300-$1,000', '1000-3000': '$1,000-$3,000', '3000-5000': '$3,000-$5,000', '5000-9000': '$5,000-$9,000', '9000-20000': '$9,000-$20,000', '20000plus': '$20,000+' },
    body_type: { 'athletic': 'Athletic', 'average': 'Average', 'slim': 'Slim', 'curvy': 'Curvy', 'muscular': 'Muscular', 'full_figured': 'Full Figured', 'plus_size': 'Plus Size' },
    eye_color: { 'brown': 'Brown', 'blue': 'Blue', 'green': 'Green', 'hazel': 'Hazel', 'black': 'Black', 'gray': 'Gray', 'other': 'Other' },
    hair_color: { 'black': 'Black', 'brown': 'Brown', 'blonde': 'Blonde', 'red': 'Red', 'gray': 'Gray', 'white': 'White', 'other': 'Other' },
    industry: { 'accounting': 'Accounting & Finance', 'admin': 'Administration & Office Support', 'advertising': 'Advertising & Marketing', 'agriculture': 'Agriculture & Farming', 'arts': 'Arts & Entertainment', 'banking': 'Banking & Financial Services', 'construction': 'Construction', 'consulting': 'Consulting', 'education': 'Education & Training', 'engineering': 'Engineering', 'healthcare': 'Healthcare', 'hospitality': 'Hospitality & Tourism', 'hr': 'Human Resources', 'it': 'Information Technology', 'legal': 'Legal', 'manufacturing': 'Manufacturing', 'media': 'Media & Communications', 'military': 'Military', 'nonprofit': 'Nonprofit & NGO', 'real_estate': 'Real Estate', 'retail': 'Retail & Sales', 'science': 'Science & Research', 'sports': 'Sports & Recreation', 'telecommunications': 'Telecommunications', 'transport': 'Transport & Logistics', 'other': 'Other' },
    education: { 'high_school': 'High School', 'some_college': 'Some College', 'associates': 'Associates Degree', 'bachelors': 'Bachelor\'s Degree', 'masters': 'Master\'s Degree', 'phd': 'PhD/Doctorate', 'trade_school': 'Trade School', 'other': 'Other' }
  };

  function init() {
    initAccordion();
    initModal();
    initEditButtons();
    initPhotoGallery();
  }

  function initAccordion() {
    $('.accordion-header').on('click', function() {
      const $item = $(this).parent('.accordion-item');
      $item.toggleClass('active');
      if ($item.hasClass('active')) {
        $('.accordion-item').not($item).removeClass('active');
      }
    });
  }

  function escapeHtml(text) {
    if (typeof text !== 'string') {
      return text;
    }
    const map = {
      '&': '&',
      '<': '<',
      '>': '>',
      '"': '"',
      "'": "'"
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
  }

  function initModal() {
    $('.modal-close').on('click', closeModal);
    $('.modal-overlay').on('click', function(e) {
      if ($(e.target).hasClass('modal-overlay')) {
        closeModal();
      }
    });
    $('.save-btn').on('click', function() {
      saveCurrentSection();
    });
    $('.cancel-btn').on('click', closeModal);
  }

  function openModal(section) {
    currentSection = section;
    $('.modal-title').text(getSectionTitle(section));
    loadSectionForm(section);
    $('.modal-overlay').addClass('active');
    $('body').css('overflow', 'hidden');
  }

  function closeModal() {
    $('.modal-overlay').removeClass('active');
    $('body').css('overflow', '');
    currentSection = '';
    setTimeout(function() {
      $('.modal-body').empty();
    }, 300);
  }

  function initEditButtons() {
    $('.edit-btn').on('click', function(e) {
      e.preventDefault();
      e.stopPropagation();
      const section = $(this).data('section');
      openModal(section);
    });
  }

  function getSectionTitle(section) {
    const titles = {
      'basic': 'Basic Information',
      'terms': 'Terms of Relationship',
      'dating': 'Dating Styles',
      'financial': 'Financial Information',
      'location': 'Location',
      'appearance': 'Appearance',
      'personal': 'Personal Information',
      'about': 'About Me',
      'looking': 'Looking For',
      'photos': 'Photos'
    };
    return titles[section] || 'Edit Profile';
  }

  function loadSectionForm(section) {
    const $modalBody = $('.modal-body');
    $modalBody.empty();

    switch(section) {
      case 'basic':
        loadBasicForm($modalBody);
        break;
      case 'terms':
        loadTermsForm($modalBody);
        break;
      case 'dating':
        loadDatingStylesForm($modalBody);
        break;
      case 'financial':
        loadFinancialForm($modalBody);
        break;
      case 'location':
        loadLocationForm($modalBody);
        break;
      case 'appearance':
        loadAppearanceForm($modalBody);
        break;
      case 'personal':
        loadPersonalForm($modalBody);
        break;
      case 'about':
        loadAboutForm($modalBody);
        break;
      case 'looking':
        loadLookingForForm($modalBody);
        break;
      case 'photos':
        loadPhotosForm($modalBody);
        break;
      default:
        $modalBody.html('<p>Form not available</p>');
    }
  }

  function loadBasicForm($container) {
    const email = $('#user-email').text();
    const displayName = $('#user-display-name-summary').text();

    const html = `
      <div class="form-group">
        <label for="modal-email">Email</label>
        <input type="email" id="modal-email" class="form-control" value="${email}" readonly>
        <small>Email cannot be changed</small>
      </div>
      <div class="form-group">
        <label for="modal-display-name">Display Name</label>
        <input type="text" id="modal-display-name" class="form-control" value="${displayName}">
        <div class="validation-error" id="name-error">Display name is required</div>
      </div>
      <div class="form-group">
        <label for="modal-age">Age</label>
        <input type="text" id="modal-age" class="form-control" value="${$('#user-age').text()}" readonly>
        <small>Age is calculated from your date of birth</small>
      </div>
    `;

    $container.html(html);

    $('#modal-display-name').on('input', function() {
      const name = $(this).val().trim();
      if (!name) {
        $('#name-error').addClass('active');
        $('.save-btn').prop('disabled', true);
      } else {
        $('#name-error').removeClass('active');
        $('.save-btn').prop('disabled', false);
      }
    });

    if (!displayName.trim()) {
      $('#name-error').addClass('active');
      $('.save-btn').prop('disabled', true);
    }
  }

  function loadTermsForm($container) {
    const selectedTerms = [];
    $('#terms-summary .tag').each(function() {
      const text = $(this).text().trim();
      const value = getTermValueFromText(text);
      if (value) {
        selectedTerms.push(value);
      }
    });

    const termDescriptions = {
      'ppm': 'Pay Per Meet (PPM): Financial support provided on a per-meeting basis',
      'ma': 'Monthly Allowance (MA): Monthly financial support',
      'dtf': 'DTF Tonight/Right Now: Looking for immediate intimate connection',
      'discreet': 'Discreet: Privacy and discretion are important',
      'high_net_worth': 'Access to High Net Worth Individuals: Connect with financially successful people',
      'all_ethnicities': 'All Ethnicities: Open to all backgrounds',
      'exclusive': 'Exclusive: Seeking a monogamous arrangement',
      'friends_benefits': 'Friends with Benefits: Friendship with physical intimacy',
      'hookups': 'Hookups: Casual physical encounters',
      'in_relationship': 'In a Relationship: Already committed elsewhere',
      'lgbtq': 'LGBTQ Friendly: Welcoming to all identities',
      'marriage': 'Marriage: Looking for a lifelong partnership',
      'mentorship': 'Mentorship: Offering guidance and career advice',
      'no_strings': 'No Strings Attached: Without emotional commitment',
      'open_relationship': 'Open Relationship: Non-exclusive relationships',
      'passport_ready': 'Passport Ready: Available for international travel',
      'platonic': 'Platonic: Non-physical relationship based on companionship',
      'serious': 'Serious Relationship: Seeking long-term commitment',
      'transgender': 'Transgender Friendly: Open to dating transgender individuals',
      'travel_companion': 'Travel Companion: Looking for someone to travel with',
      'travel_to_you': 'Travel To You: Willing to visit your location',
      'weekly_allowance': 'Weekly Allowance: Weekly financial support'
    };

    let tagsHtml = '';

    for (const [key, description] of Object.entries(termDescriptions)) {
      const selected = selectedTerms.includes(key) ? 'selected' : '';
      const [title] = description.split(':');

      tagsHtml += `
        <div class="selectable-tag ${selected}" data-value="${key}" data-description="${description}">
          ${title}
        </div>
      `;
    }

    const html = `
      <div id="term-description" class="instruction-box">
        ${selectedTerms.length > 0 && termDescriptions[selectedTerms[0]] ? 
          termDescriptions[selectedTerms[0]] : 'Select terms to see descriptions'}
      </div>

      <div class="tag-selection">
        ${tagsHtml}
      </div>

      <div class="selection-counter">
        <span id="terms-counter">${selectedTerms.length}/5 Selected</span>
        <span>(Minimum 3 required)</span>
      </div>
      <div class="validation-error" id="terms-error">Please select at least 3 terms</div>
      <input type="hidden" id="terms-input" name="relationship_terms" value="${selectedTerms.join(',')}">
    `;

    $container.html(html);

    $('.selectable-tag').on('click', function() {
      const $this = $(this);
      const value = $this.data('value');
      const selectedTerms = $('#terms-input').val().split(',').filter(Boolean);

      if ($this.hasClass('selected')) {
        $this.removeClass('selected');
        const index = selectedTerms.indexOf(value);
        if (index > -1) {
          selectedTerms.splice(index, 1);
        }
      } else if (selectedTerms.length < 5) {
        $this.addClass('selected');
        selectedTerms.push(value);
        $('#term-description').html($this.data('description'));
      }

      $('#terms-input').val(selectedTerms.join(','));
      $('#terms-counter').text(`${selectedTerms.length}/5 Selected`);

      if (selectedTerms.length < 3) {
        $('#terms-error').addClass('active');
        $('.save-btn').prop('disabled', true);
      } else {
        $('#terms-error').removeClass('active');
        $('.save-btn').prop('disabled', false);
      }
    });

    if (selectedTerms.length < 3) {
      $('#terms-error').addClass('active');
      $('.save-btn').prop('disabled', true);
    } else {
      $('.save-btn').prop('disabled', false);
    }
  }

  function getTermValueFromText(text) {

    text = text.replace(/[^\w\s]/g, '').trim().toLowerCase();

    const termMap = {
      'pay per meet': 'ppm',
      'monthly allowance': 'ma',
      'dtf tonight': 'dtf',
      'discreet': 'discreet',
      'high net worth': 'high_net_worth',
      'all ethnicities': 'all_ethnicities',
      'exclusive': 'exclusive',
      'friends with benefits': 'friends_benefits',
      'hookups': 'hookups',
      'in a relationship': 'in_relationship',
      'lgbtq friendly': 'lgbtq',
      'marriage': 'marriage',
      'mentorship': 'mentorship',
      'no strings attached': 'no_strings',
      'open relationship': 'open_relationship',
      'passport ready': 'passport_ready',
      'platonic': 'platonic',
      'serious relationship': 'serious',
      'transgender friendly': 'transgender',
      'travel companion': 'travel_companion',
      'travel to you': 'travel_to_you',
      'weekly allowance': 'weekly_allowance'
    };

    for (const [displayText, value] of Object.entries(termMap)) {
      if (text.includes(displayText) || displayText.includes(text)) {
        return value;
      }
    }
    return null;
  }

  function loadDatingStylesForm($container) {

    const selectedStyles = [];
    $('#dating-summary .tag').each(function() {
      const text = $(this).text().trim();
      const value = getStyleValueFromText(text);
      if (value) {
        selectedStyles.push(value);
      }
    });

    const styleOptions = {
      'animal_lovers': '<i class="fas fa-paw"></i> Animal Lovers',
      'arts_culture': '<i class="fas fa-palette"></i> Arts & Culture Dates',
      'beach_days': '<i class="fas fa-umbrella-beach"></i> Beach Days',
      'brunch_dates': '<i class="fas fa-coffee"></i> Brunch Dates',
      'clubbing': '<i class="fas fa-music"></i> Clubbing & Partying',
      'coffee_dates': '<i class="fas fa-mug-hot"></i> Coffee Dates',
      'comedy_night': '<i class="fas fa-laugh"></i> Comedy Night',
      'cooking_classes': '<i class="fas fa-utensils"></i> Cooking Classes',
      'crafting': '<i class="fas fa-cut"></i> Crafting Workshops',
      'dinner_dates': '<i class="fas fa-utensils"></i> Dinner Dates',
      'drinks': '<i class="fas fa-glass-martini"></i> Drinks',
      'fitness_dates': '<i class="fas fa-dumbbell"></i> Fitness Dates',
      'foodie_dates': '<i class="fas fa-hamburger"></i> Foodie Dates',
      'gaming': '<i class="fas fa-gamepad"></i> Gaming',
      'lunch_dates': '<i class="fas fa-utensils"></i> Lunch Dates',
      'luxury': '<i class="fas fa-gem"></i> Luxury High-Tea',
      'meet_tonight': '<i class="fas fa-calendar-day"></i> Meet Tonight',
      'movies': '<i class="fas fa-film"></i> Movies & Chill',
      'music_festivals': '<i class="fas fa-music"></i> Music Festivals',
      'nature': '<i class="fas fa-leaf"></i> Nature & Outdoors',
      'sailing': '<i class="fas fa-ship"></i> Sailing & Water Sports',
      'shopping': '<i class="fas fa-shopping-bag"></i> Shopping',
      'shows': '<i class="fas fa-ticket-alt"></i> Shows & Concerts',
      'spiritual': '<i class="fas fa-pray"></i> Spiritual Journeys',
      'travel': '<i class="fas fa-plane"></i> Travel',
      'wine_tasting': '<i class="fas fa-wine-glass-alt"></i> Wine Tasting'
    };

    let tagsHtml = '';

    for (const [key, label] of Object.entries(styleOptions)) {
      const selected = selectedStyles.includes(key) ? 'selected' : '';
      tagsHtml += `
        <div class="selectable-tag ${selected}" data-value="${key}">
          ${label}
        </div>
      `;
    }

    const html = `
      <p>Express your dating styles. Select your favorite dating activities.</p>
      <div class="tag-selection">
        ${tagsHtml}
      </div>
      <div class="selection-counter">
        <span id="styles-counter">${selectedStyles.length}/5 Selected</span>
      </div>
      <input type="hidden" id="styles-input" name="dating_style" value="${selectedStyles.join(',')}">
    `;

    $container.html(html);

    $('.selectable-tag').on('click', function() {
      const $this = $(this);
      const value = $this.data('value');
      const selectedStyles = $('#styles-input').val().split(',').filter(Boolean);
      if ($this.hasClass('selected')) {
        $this.removeClass('selected');
        const index = selectedStyles.indexOf(value);
        if (index > -1) {
          selectedStyles.splice(index, 1);
        }
      } else if (selectedStyles.length < 5) {
        $this.addClass('selected');
        selectedStyles.push(value);
      }

      $('#styles-input').val(selectedStyles.join(','));
      $('#styles-counter').text(`${selectedStyles.length}/5 Selected`);
    });
  }

  function getStyleValueFromText(text) {
    text = text.replace(/[^\w\s]/g, '').trim().toLowerCase();

    const styleMap = {
      'animal lovers': 'animal_lovers',
      'arts & culture': 'arts_culture',
      'beach days': 'beach_days',
      'brunch dates': 'brunch_dates',
      'clubbing': 'clubbing',
      'coffee dates': 'coffee_dates',
      'comedy night': 'comedy_night',
      'cooking classes': 'cooking_classes',
      'crafting': 'crafting',
      'dinner dates': 'dinner_dates',
      'drinks': 'drinks',
      'fitness dates': 'fitness_dates',
      'foodie dates': 'foodie_dates',
      'gaming': 'gaming',
      'lunch dates': 'lunch_dates',
      'luxury high-tea': 'luxury',
      'meet tonight': 'meet_tonight',
      'movies & chill': 'movies',
      'music festivals': 'music_festivals',
      'nature & outdoors': 'nature',
      'sailing': 'sailing',
      'shopping': 'shopping',
      'shows & concerts': 'shows',
      'spiritual journeys': 'spiritual',
      'travel': 'travel',
      'wine tasting': 'wine_tasting'
    };

    for (const [displayText, value] of Object.entries(styleMap)) {
      if (text.includes(displayText) || displayText.includes(text)) {
        return value;
      }
    }
    return null;
  }

  function loadFinancialForm($container) {

    const incomeValue = $('#financial-income').data('value') || '';
    const netWorthValue = $('#financial-networth').data('value') || '';
    const budgetValue = $('#financial-budget').data('value') || ''; 

    function generateOptions(options, selectedValue) {
        let html = `<option value="">Select ${options === attributeOptions.annual_income ? 'Annual Income' : (options === attributeOptions.net_worth ? 'Net Worth' : 'Dating Budget')}</option>`;
        for (const [key, label] of Object.entries(options)) {
            html += `<option value="${key}" ${selectedValue == key ? 'selected' : ''}>${label}</option>`;
        }
        return html;
    }

    const html = `
      <div class="form-group">
        <label for="modal-income">Annual Income</label>
        <select id="modal-income" name="annual_income" class="form-select">
          ${generateOptions(attributeOptions.annual_income, incomeValue)}
        </select>
      </div>
      <div class="form-group">
        <label for="modal-networth">Net Worth</label>
        <select id="modal-networth" name="net_worth" class="form-select">
          ${generateOptions(attributeOptions.net_worth, netWorthValue)}
        </select>
      </div>
      <div class="form-group">
        <label for="modal-budget">Dating Budget</label>
        <select id="modal-budget" name="dating_budget" class="form-select">
          ${generateOptions(attributeOptions.dating_budget, budgetValue)}
        </select>
      </div>
    `;
    $container.html(html);

  }

  function loadAboutForm($container) {
    const aboutText = $('#about-summary').text();

    const html = `
      <div class="form-group">
        <label for="modal-about">Tell potential matches about yourself</label>
        <textarea id="modal-about" name="about_me" class="form-textarea" rows="6">${aboutText}</textarea>
        <small>This will appear on your profile. Be authentic and share what makes you unique.</small>
      </div>
    `;
    $container.html(html);
  }

  function loadLocationForm($container) {
    const currentLocation = $('#location-formatted').text();
    const initialInputValue = (currentLocation !== 'Not specified') ? currentLocation : '';
    const initialLat = $('#current-latitude').val() || '';
    const initialLng = $('#current-longitude').val() || '';
    const initialCity = $('#current-city').val() || '';
    const initialRegion = $('#current-region').val() || '';
    const initialCountry = $('#current-country').val() || '';

    const html = `
      <p class="modal-instructions">Start typing your city/address and select from the suggestions.</p>
      <div class="form-group">
        <label for="modal-location-autocomplete">Enter City / Address <span class="sud-required">*</span></label>
        <input type="text" id="modal-location-autocomplete" class="form-control" placeholder="Start typing..." value="${escapeHtml(initialInputValue)}">
        <div class="validation-error" id="modal-location-error">Please select a valid location from suggestions.</div>
      </div>
      <!-- Hidden fields for modal -->
      <input type="hidden" id="modal-latitude" name="latitude" value="${escapeHtml(initialLat)}">
      <input type="hidden" id="modal-longitude" name="longitude" value="${escapeHtml(initialLng)}">
      <input type="hidden" id="modal-city_google" name="city_google" value="${escapeHtml(initialCity)}">
      <input type="hidden" id="modal-region_google" name="region_google" value="${escapeHtml(initialRegion)}">
      <input type="hidden" id="modal-country_google" name="country_google" value="${escapeHtml(initialCountry)}">
      <input type="hidden" id="modal-accuracy" name="accuracy" value="google_places_edit">
    `;
    $container.html(html);

    initModalAutocomplete();

    if (!initialLat || !initialLng) {
      $('.save-btn').prop('disabled', true);
    } else {
      $('.save-btn').prop('disabled', false);
    }
  }

  function updateLocationViaAjax(lat, lng) {
    $.ajax({
      url: sud_config.sud_url + '/ajax/update-location.php',
      type: 'POST',
      data: { latitude: lat, longitude: lng },
      success: function(response) {
        if (response.success) {
          if (response.city) $('#modal-city').val(response.city);
          if (response.region) $('#modal-region').val(response.region);
          if (response.country) $('#modal-country').val(response.country);
          showToast('Location updated successfully', 'success');
        } else {
          showToast('Failed to update location', 'error');
        }
        $('#update-location-btn').html('<i class="fas fa-map-marker-alt"></i> Update My Location').prop('disabled', false);
      },
      error: function() {
        showToast('Error updating location', 'error');
        $('#update-location-btn').html('<i class="fas fa-map-marker-alt"></i> Update My Location').prop('disabled', false);
      }
    });
  }

  function initModalAutocomplete() {
    const modalInput = document.getElementById('modal-location-autocomplete');
    if (!modalInput) { console.error("Modal location input not found"); return; }

    if (typeof google === 'undefined' || !google.maps || !google.maps.places) {
      console.error("Google Maps Places API not loaded.");
      $('#modal-location-error').text('Location service unavailable. Please contact support.').addClass('active').show();
      $(modalInput).prop('disabled', true);
      $('.save-btn').prop('disabled', true);
      return;
    }

    try {
      if (modalAutocompleteInstance) {
          google.maps.event.clearInstanceListeners(modalAutocompleteInstance);
      }

      modalAutocompleteInstance = new google.maps.places.Autocomplete(modalInput, {
        types: ['geocode'],
        fields: ["address_components", "geometry.location", "name"]
      });

      modalAutocompleteInstance.addListener('place_changed', function() {
        const place = modalAutocompleteInstance.getPlace();

        $('#modal-latitude').val(''); $('#modal-longitude').val(''); $('#modal-city_google').val(''); $('#modal-region_google').val(''); $('#modal-country_google').val('');
        $('#modal-location-error').removeClass('active').hide();
        $('.save-btn').prop('disabled', true);

        if (!place || !place.geometry || !place.geometry.location) {
          console.warn("Invalid place selected in modal.");
          $('#modal-location-error').addClass('active').show();
          return;
        }

        $('#modal-latitude').val(place.geometry.location.lat());
        $('#modal-longitude').val(place.geometry.location.lng());

        let city = '', region = '', country = '';
        if (place.address_components) {
          for (const component of place.address_components) {
            const types = component.types;
            if (types.includes('locality')) city = component.long_name;
            else if (types.includes('administrative_area_level_1')) region = component.short_name;
            else if (types.includes('country')) country = component.long_name;
          }
          if (!city) { /* Fallbacks if needed */ }
          if (!city && place.name && place.name !== country) city = place.name;
        } else {
          if (place.name) city = place.name;
        }

        $('#modal-city_google').val(city);
        $('#modal-region_google').val(region);
        $('#modal-country_google').val(country);

        $('.save-btn').prop('disabled', false);
      });

      $(modalInput).on('keydown', function(e) { if (e.key === 'Enter') e.preventDefault(); });

      $(modalInput).on('input', function() {
          $('#modal-latitude').val('');
          $('#modal-longitude').val('');

          $('#modal-city_google').val('');
          $('#modal-region_google').val('');
          $('#modal-country_google').val('');

          $('.save-btn').prop('disabled', true);
          $('#modal-location-error').removeClass('active').hide();
      });

    } catch (error) {
      console.error("Error initializing modal autocomplete:", error);
      $('#modal-location-error').text('Error initializing location search.').addClass('active').show();
      $(modalInput).prop('disabled', true);
      $('.save-btn').prop('disabled', true);
    }
  }

  function loadAppearanceForm($container) {
    const height = $('#appearance-height').text().replace(/\D/g, '');
    const bodyType = getValueFromText($('#appearance-body').text(), 'body_type');
    const ethnicity = getValueFromText($('#appearance-ethnicity').text(), 'ethnicity');
    const race = getValueFromText($('#appearance-race').text(), 'race'); 
    const eyeColor = getValueFromText($('#appearance-eye').text(), 'eye_color');
    const hairColor = getValueFromText($('#appearance-hair').text(), 'hair_color');

    const html = `
      <div class="form-group">
        <label for="modal-height">Height (cm)</label>
        <select id="modal-height" name="height" class="form-select">
          <option value="">Select Height</option>
          ${generateHeightOptions(height)}
        </select>
      </div>

      <div class="form-group">
        <label for="modal-body-type">Body Type</label>
        <select id="modal-body-type" name="body_type" class="form-select">
          <option value="">Select Body Type</option>
          <option value="athletic" ${bodyType === 'athletic' ? 'selected' : ''}>Athletic</option>
          <option value="average" ${bodyType === 'average' ? 'selected' : ''}>Average</option>
          <option value="slim" ${bodyType === 'slim' ? 'selected' : ''}>Slim</option>
          <option value="curvy" ${bodyType === 'curvy' ? 'selected' : ''}>Curvy</option>
          <option value="muscular" ${bodyType === 'muscular' ? 'selected' : ''}>Muscular</option>
          <option value="full_figured" ${bodyType === 'full_figured' ? 'selected' : ''}>Full Figured</option>
          <option value="plus_size" ${bodyType === 'plus_size' ? 'selected' : ''}>Plus Size</option>
        </select>
      </div>

      <div class="form-group">
        <label for="modal-ethnicity">Ethnicity</label>
        <select id="modal-ethnicity" name="ethnicity" class="form-select">
          <option value="">Select Ethnicity</option>
          <option value="african" ${ethnicity === 'african' ? 'selected' : ''}>African</option>
          <option value="asian" ${ethnicity === 'asian' ? 'selected' : ''}>Asian</option>
          <option value="caucasian" ${ethnicity === 'caucasian' ? 'selected' : ''}>Caucasian</option>
          <option value="hispanic" ${ethnicity === 'hispanic' ? 'selected' : ''}>Hispanic</option>
          <option value="middle_eastern" ${ethnicity === 'middle_eastern' ? 'selected' : ''}>Middle Eastern</option>
          <option value="latino" ${ethnicity === 'latino' ? 'selected' : ''}>Latino</option>
          <option value="native_american" ${ethnicity === 'native_american' ? 'selected' : ''}>Native American</option>
          <option value="pacific_islander" ${ethnicity === 'pacific_islander' ? 'selected' : ''}>Pacific Islander</option>
          <option value="multiracial" ${ethnicity === 'multiracial' ? 'selected' : ''}>Multiracial</option>
          <option value="other" ${ethnicity === 'other' ? 'selected' : ''}>Other</option>
        </select>
      </div>

      <div class="form-group">
        <label for="modal-race">Race</label>
        <select id="modal-race" name="race" class="form-select">
          <option value="">Select Race</option>
          ${generateRaceOptions(race)}
        </select>
      </div>

      <div class="form-group">
        <label for="modal-eye-color">Eye Color</label>
        <select id="modal-eye-color" name="eye_color" class="form-select">
          <option value="">Select Eye Color</option>
          <option value="brown" ${eyeColor === 'brown' ? 'selected' : ''}>Brown</option>
          <option value="blue" ${eyeColor === 'blue' ? 'selected' : ''}>Blue</option>
          <option value="green" ${eyeColor === 'green' ? 'selected' : ''}>Green</option>
          <option value="hazel" ${eyeColor === 'hazel' ? 'selected' : ''}>Hazel</option>
          <option value="black" ${eyeColor === 'black' ? 'selected' : ''}>Black</option>
          <option value="gray" ${eyeColor === 'gray' ? 'selected' : ''}>Gray</option>
          <option value="other" ${eyeColor === 'other' ? 'selected' : ''}>Other</option>
        </select>
      </div>

      <div class="form-group">
        <label for="modal-hair-color">Hair Color</label>
        <select id="modal-hair-color" name="hair_color" class="form-select">
          <option value="">Select Hair Color</option>
          <option value="black" ${hairColor === 'black' ? 'selected' : ''}>Black</option>
          <option value="brown" ${hairColor === 'brown' ? 'selected' : ''}>Brown</option>
          <option value="blonde" ${hairColor === 'blonde' ? 'selected' : ''}>Blonde</option>
          <option value="red" ${hairColor === 'red' ? 'selected' : ''}>Red</option>
          <option value="gray" ${hairColor === 'gray' ? 'selected' : ''}>Gray</option>
          <option value="white" ${hairColor === 'white' ? 'selected' : ''}>White</option>
          <option value="other" ${hairColor === 'other' ? 'selected' : ''}>Other</option>
        </select>
      </div>
    `;

    $container.html(html);
  }

  function generateHeightOptions(currentHeight) {
    let options = '';
    for (let i = 145; i <= 210; i += 5) {
      options += `<option value="${i}" ${parseInt(currentHeight) === i ? 'selected' : ''}>${i} cm</option>`;
    }
    return options;
  }

  function generateRaceOptions(currentRace) {
    const races = {
      'american': 'American',
      'australian': 'Australian',
      'austrian': 'Austrian',
      'british': 'British',
      'bulgarian': 'Bulgarian',
      'canadian': 'Canadian',
      'croatian': 'Croatian',
      'czech': 'Czech',
      'danish': 'Danish',
      'dutch': 'Dutch',
      'european': 'European',
      'finnish': 'Finnish',
      'french': 'French',
      'german': 'German',
      'greek': 'Greek',
      'hungarian': 'Hungarian',
      'irish': 'Irish',
      'italian': 'Italian',
      'new_zealander': 'New Zealander',
      'norwegian': 'Norwegian',
      'polish': 'Polish',
      'portuguese': 'Portuguese',
      'romanian': 'Romanian',
      'russian': 'Russian',
      'scottish': 'Scottish',
      'serbian': 'Serbian',
      'slovak': 'Slovak',
      'spanish': 'Spanish',
      'swedish': 'Swedish',
      'swiss': 'Swiss',
      'ukrainian': 'Ukrainian',
      'welsh': 'Welsh'
    };

    let options = '';
    for (const [value, label] of Object.entries(races)) {
      options += `<option value="${value}" ${currentRace === value ? 'selected' : ''}>${label}</option>`;
    }
    return options;
  }

  function getKeyFromLabel(optionsSubObject, labelToFind) {
    if (!optionsSubObject || !labelToFind) return null;
    labelToFind = labelToFind.trim();
    for (const [key, label] of Object.entries(optionsSubObject)) {
        if (label.trim() === labelToFind) return key;
    }
    if (optionsSubObject.hasOwnProperty(labelToFind)) return labelToFind; 
    return null;
  }

  function getLabelFromValue(optionsSubObject, valueToFind, fallback = 'Not specified') {
     if (!optionsSubObject || typeof valueToFind === 'undefined' || valueToFind === null || valueToFind === '' || !optionsSubObject[valueToFind]) {
         return fallback;
     }
     return optionsSubObject[valueToFind];
  }

  function loadPersonalForm($container) {

    const occupation = $('#personal-occupation').text().trim();
    const industryValue = $('#personal-industry').data('value') || '';
    const educationValue = $('#personal-education').data('value') || '';
    const relationshipValue = $('#personal-relationship').data('value') || '';
    const smokeValue = $('#personal-smoke').data('value') || '';
    const drinkValue = $('#personal-drink').data('value') || '';

    function generateOptions(options, selectedValue, placeholder) {
        let html = `<option value="">${placeholder}</option>`;
        for (const [key, label] of Object.entries(options)) {
            html += `<option value="${key}" ${selectedValue == key ? 'selected' : ''}>${label}</option>`;
        }
        return html;
    }

    const html = `
      <div class="form-group">
        <label for="modal-occupation">Occupation</label>
        <input type="text" id="modal-occupation" name="occupation" class="form-control" value="${occupation !== 'Not specified' ? occupation : ''}">
      </div>
      <div class="form-group">
        <label for="modal-industry">Industry</label>
        <select id="modal-industry" name="industry" class="form-select">
          ${generateOptions(attributeOptions.industry, industryValue, 'Select Industry')}
        </select>
      </div>
      <div class="form-group">
        <label for="modal-education">Education</label>
        <select id="modal-education" name="education" class="form-select">
          ${generateOptions(attributeOptions.education, educationValue, 'Select Education Level')}
        </select>
      </div>
      <div class="form-group">
        <label for="modal-relationship">Relationship Status</label>
        <select id="modal-relationship" name="relationship_status" class="form-select">
          ${generateOptions(attributeOptions.relationship_status, relationshipValue, 'Select Relationship Status')}
        </select>
      </div>
      <div class="form-group">
        <label for="modal-smoke">Smoking</label>
        <select id="modal-smoke" name="smoke" class="form-select">
          ${generateOptions(attributeOptions.smoke, smokeValue, 'Select Smoking Preference')}
        </select>
      </div>
      <div class="form-group">
        <label for="modal-drink">Drinking</label>
        <select id="modal-drink" name="drink" class="form-select">
          ${generateOptions(attributeOptions.drink, drinkValue, 'Select Drinking Preference')}
        </select>
      </div>
    `;
    $container.html(html);
  }

  function generateIndustryOptions(currentIndustry) {
    const industries = {
      'accounting': 'Accounting & Finance',
      'admin': 'Administration & Office Support',
      'advertising': 'Advertising & Marketing',
      'agriculture': 'Agriculture & Farming',
      'arts': 'Arts & Entertainment',
      'banking': 'Banking & Financial Services',
      'construction': 'Construction',
      'consulting': 'Consulting',
      'education': 'Education & Training',
      'engineering': 'Engineering',
      'healthcare': 'Healthcare',
      'hospitality': 'Hospitality & Tourism',
      'hr': 'Human Resources',
      'it': 'Information Technology',
      'legal': 'Legal',
      'manufacturing': 'Manufacturing',
      'media': 'Media & Communications',
      'military': 'Military',
      'nonprofit': 'Nonprofit & NGO',
      'real_estate': 'Real Estate',
      'retail': 'Retail & Sales',
      'science': 'Science & Research',
      'sports': 'Sports & Recreation',
      'telecommunications': 'Telecommunications',
      'transport': 'Transport & Logistics',
      'other': 'Other'
    };

    let options = '';
    for (const [value, label] of Object.entries(industries)) {
      options += `<option value="${value}" ${currentIndustry === value ? 'selected' : ''}>${label}</option>`;
    }
    return options;
  }

  function loadLookingForForm($container) {
    const rawValues = $('#looking-ethnicities').data('raw-values') || '';
    const currentSelections = rawValues ? rawValues.split(',').filter(Boolean) : [];
    const isOpenToAny = currentSelections.length === 1 && currentSelections[0] === 'any_ethnicity';
    const specificSelections = isOpenToAny ? [] : currentSelections;

    let ethnicityOptionsHtml = '<option value="">Select Ethnicity</option>';
    for (const [value, label] of Object.entries(attributeOptions.ethnicity)) {
        ethnicityOptionsHtml += `<option value="${value}">${label}</option>`;
    }

    let raceOptionsHtml = '<option value="">Select Race (Optional)</option>';
     for (const [value, label] of Object.entries(attributeOptions.race)) {
        raceOptionsHtml += `<option value="${value}">${label}</option>`;
    }

    let selectedTagsHtml = '';
    specificSelections.forEach(comboVal => {
        const [ethnicityVal, raceVal] = comboVal.split('|');
        const ethLabel = getLabelFromValue(attributeOptions.ethnicity, ethnicityVal, ethnicityVal);
        const raceLabel = (raceVal && raceVal.trim() !== '') ? getLabelFromValue(attributeOptions.race, raceVal, raceVal) : '';
        const displayLabel = `${escapeHtml(ethLabel)}${(raceLabel && raceLabel !== 'Not specified') ? ', ' + escapeHtml(raceLabel) : ''}`;
        selectedTagsHtml += `
            <span class="tag sud-tag" data-value="${comboVal}">
                ${displayLabel}
                <button type="button" class="sud-tag-remove" aria-label="Remove preference">×</button>
            </span>`;
    });

    const ageText = $('#looking-age').text();
    const ageMatch = ageText.match(/(\d+)\s*-\s*(\d+)/);
    let ageMin = ageMatch ? parseInt(ageMatch[1]) : 18;
    let ageMax = ageMatch ? parseInt(ageMatch[2]) : 70;
    ageMin = Math.max(18, ageMin);
    ageMax = Math.min(70, ageMax);
    if (ageMin >= ageMax) ageMin = Math.max(18, ageMax - 1);

    const html = `
        <div class="form-group">
          <label for="age_min">Age Range</label>
          <div class="range-slider">
            <div class="range-input-visual-track"></div>
            <input type="range" id="age_min" name="looking_for_age_min" min="18" max="70" value="${ageMin}" data-target-display="age_min_display">
            <input type="range" id="age_max" name="looking_for_age_max" min="18" max="70" value="${ageMax}" data-target-display="age_max_display">
            <div class="range-track-highlight"></div>
            <div class="range-display">
              <span id="age_min_display">${ageMin}</span> -
              <span id="age_max_display">${ageMax}</span> years old
            </div>
          </div>
        </div>
        <div class="form-group">
             <label>Ethnicity & Race Preferences</label>
             <div class="sud-checkbox" style="margin-bottom: 15px;">
                 <input type="checkbox" id="looking-any-ethnicity" class="sud-checkbox-input" ${isOpenToAny ? 'checked' : ''}>
                 <label for="looking-any-ethnicity" class="sud-checkbox-label" style="color:#333;">Open to Any Ethnicity</label>
             </div>
             <div id="specific-ethnicity-ui" class="${isOpenToAny ? 'sud-hidden' : ''}">
                 <div class="sud-dropdown-container" style="margin-bottom: 10px;">
                     <label for="modal-looking-ethnicity" style="display:none;">Ethnicity</label>
                     <select id="modal-looking-ethnicity" class="form-select" aria-label="Select Ethnicity Preference">
                         ${ethnicityOptionsHtml}
                     </select>
                 </div>
                 <div class="sud-dropdown-container" style="margin-bottom: 10px;">
                     <label for="modal-looking-race" style="display:none;">Race</label>
                     <select id="modal-looking-race" class="form-select" aria-label="Select Race Preference (Optional)">
                         ${raceOptionsHtml}
                     </select>
                 </div>
                 <button type="button" id="add-ethnicity-preference-btn" class="edit-btn" style="margin-bottom: 15px; display: inline-flex; align-items: center; gap: 5px;">
                     <i class="fas fa-plus"></i> Add Preference
                 </button>
                 <div class="sud-selected-tags" id="selected-preferences-list" aria-live="polite">
                     ${selectedTagsHtml}
                 </div>
                 <div class="sud-dropdown-hint" style="color: #666; font-size: 13px; margin-top: 5px;">Select an ethnicity (race is optional) and click 'Add Preference'. Max 5 preferences.</div>
             </div>
        </div>
        <input type="hidden" id="looking-ethnicities-input" name="looking_for_ethnicities" value="${rawValues}">
    `;
    $container.html(html);

    const $ageMinSlider = $('#age_min');
    const $ageMaxSlider = $('#age_max');
    
    function updateAgeDisplay(event) {
      const $ageMinSlider = $('#age_min');
      const $ageMaxSlider = $('#age_max');
      const $ageMinDisplay = $('#age_min_display');
      const $ageMaxDisplay = $('#age_max_display');
      const $rangeTrackHighlight = $('.range-track-highlight'); 

      let minVal = parseInt($ageMinSlider.val());
      let maxVal = parseInt($ageMaxSlider.val());
      const sliderChanged = event ? $(event.target) : null;
      const MIN_AGE = 18;
      const MAX_AGE = 70;
      const MIN_GAP = 1; 

      if (sliderChanged) {
        if (sliderChanged.attr('id') === 'age_min') {
          if (minVal >= maxVal - MIN_GAP) {
            maxVal = Math.min(MAX_AGE, minVal + MIN_GAP);
            $ageMaxSlider.val(maxVal);

            maxVal = parseInt($ageMaxSlider.val());

            if (minVal >= maxVal - MIN_GAP) {
              minVal = maxVal - MIN_GAP;
              $ageMinSlider.val(minVal);
            }
          }
        } else { 
          if (maxVal <= minVal + MIN_GAP) {
            minVal = Math.max(MIN_AGE, maxVal - MIN_GAP);
            $ageMinSlider.val(minVal);

            minVal = parseInt($ageMinSlider.val());

            if (maxVal <= minVal + MIN_GAP) {
              maxVal = minVal + MIN_GAP;
              $ageMaxSlider.val(maxVal);
            }
          }
        }
      } else {
        if (minVal >= maxVal) {
          minVal = Math.max(MIN_AGE, maxVal - MIN_GAP);
          $ageMinSlider.val(minVal);
        }
      }

      const finalMinVal = parseInt($ageMinSlider.val());
      const finalMaxVal = parseInt($ageMaxSlider.val());

      $ageMinDisplay.text(finalMinVal);
      $ageMaxDisplay.text(finalMaxVal);

      if ($rangeTrackHighlight.length) {
        const range = MAX_AGE - MIN_AGE;
        if (range > 0) { 
          const minPercent = ((finalMinVal - MIN_AGE) / range) * 100;
          const maxPercent = ((finalMaxVal - MIN_AGE) / range) * 100;
          const widthPercent = Math.max(0, maxPercent - minPercent); 

          $rangeTrackHighlight.css({
            'left': minPercent + '%',
            'width': widthPercent + '%'
          });
        }
      }
    }

    $ageMinSlider.on('input', updateAgeDisplay);
    $ageMaxSlider.on('input', updateAgeDisplay);
    updateAgeDisplay(); 

    function updateHiddenEthnicityInput() {
      const isOpenToAny = $('#looking-any-ethnicity').is(':checked');
      let finalValue = '';
      if (isOpenToAny) {
        finalValue = 'any_ethnicity';
        $('#selected-preferences-list').empty();
      } else {
        const selectedValues = $('#selected-preferences-list .sud-tag')
                                  .map(function() { return $(this).data('value'); })
                                  .get();
        finalValue = selectedValues.join(',');
      }
      $('#looking-ethnicities-input').val(finalValue);
    }

    $('#looking-any-ethnicity').on('change', function() {
      $('#specific-ethnicity-ui').toggleClass('sud-hidden', $(this).is(':checked'));
      updateHiddenEthnicityInput();
    });

    $('#add-ethnicity-preference-btn').on('click', function() {
      const ethnicityVal = $('#modal-looking-ethnicity').val();
      const raceVal = $('#modal-looking-race').val();
      if (!ethnicityVal) {
        showToast('Please select an ethnicity first.', 'warning'); return;
      }
      const comboVal = raceVal ? `${ethnicityVal}|${raceVal}` : ethnicityVal;
      const $tagList = $('#selected-preferences-list');
      const currentVals = $tagList.find('.sud-tag').map(function() { return $(this).data('value'); }).get();

      if (currentVals.length >= 5) {
        showToast('You can add a maximum of 5 preferences.', 'warning'); return;
      }
      if (!currentVals.includes(comboVal)) {
        const ethLabel = getLabelFromValue(attributeOptions.ethnicity, ethnicityVal, ethnicityVal);
        const raceLabel = raceVal ? getLabelFromValue(attributeOptions.race, raceVal, raceVal) : '';
        const displayLabel = `${escapeHtml(ethLabel)}${(raceLabel && raceLabel !== 'Not specified') ? ', ' + escapeHtml(raceLabel) : ''}`;
        const newTagHtml = `<span class="tag sud-tag" data-value="${comboVal}">${displayLabel}<button type="button" class="sud-tag-remove" aria-label="Remove preference">×</button></span>`;
        $tagList.append(newTagHtml);
        updateHiddenEthnicityInput();
        $('#modal-looking-ethnicity').val('');
        $('#modal-looking-race').val('');
      } else {
        showToast('This preference has already been added.', 'info');
      }
    });

    $container.on('click', '.sud-tag-remove', function() {
      $(this).closest('.sud-tag').remove();
      updateHiddenEthnicityInput();
    });
  }

  function updateSectionCompletionStatus(sectionKey) {

    let isComplete;
    if (sectionKey === 'photos') {
      isComplete = $('#photos-summary-grid .photo-item').length > 0 || $('#photos-grid .photo-item').length > 0;
    } else {

      console.warn(`updateSectionCompletionStatus called directly for ${sectionKey}, completion check might be inaccurate without savedData.`);

      isComplete = checkSectionCompletion(sectionKey, {}); 
    }

    const $header = $('.accordion-header[data-section-completion="' + sectionKey + '"]');
    const $successIcon = $header.find('.completion-icon-success-' + sectionKey);
    const $incompleteIcon = $header.find('.completion-icon-incomplete-' + sectionKey);

    if (isComplete) {
      $successIcon.show();
      $incompleteIcon.hide();
    } else {
      $successIcon.hide();
      $incompleteIcon.show();
    }
  }

  function fetchAndUpdateCompletionPercentage() {
    $.ajax({
      url: sud_config.sud_url + '/ajax/get-completion.php', 
      type: 'GET',
      dataType: 'json',
      success: function(compResponse) {
          if (compResponse && typeof compResponse.completion_percentage !== 'undefined') {
            $('.completion-progress').css('width', compResponse.completion_percentage + '%');
            $('.completion-text').text(compResponse.completion_percentage + '% Complete');
          }
      },
      error: function() { console.error("Could not fetch completion percentage."); }
    });
  }

  function loadPhotosForm($container) {
    const photosData = [];
    $('#photos-summary-grid .photo-item').each(function() {
      const $item = $(this);
      const photoId = $item.data('photo-id');
      const photoUrl = $item.find('img').attr('src'); 
      const isProfile = $item.hasClass('is-profile-pic');
      if (photoId && photoUrl) {
        photosData.push({ id: photoId, url: photoUrl, isProfile: isProfile });
      }
    });

    const html = `
      <p>Upload photos to show potential matches who you are. Your first photo will be your profile picture.</p>
      <div class="photos-grid" id="photos-grid">
        ${generatePhotoItems(photosData)}
        <div class="photo-upload">
          <input type="file" name="new_photo" id="new_photo" accept="image/jpeg,image/png,image/gif" style="display: none;">
          <label for="new_photo" class="upload-label">
            <i class="fas fa-plus"></i>
            <span>Add Photo</span>
          </label>
        </div>
      </div>
      <div class="upload-progress">
        <div class="progress-bar"><div class="progress-fill"></div></div>
        <div class="progress-text">Uploading: 0%</div>
      </div>
      <div class="form-help" style="margin-top: 15px; font-size: 13px; color: #666;">
        <p>Max file size: 5MB. Formats: JPG, PNG, GIF.</p>
      </div>
    `;
    $container.html(html);

    $('#new_photo').on('change', function() {
      if (this.files.length > 0) {
        const file = this.files[0];
        const maxSize = 5 * 1024 * 1024; 
        const validTypes = ['image/jpeg', 'image/png', 'image/gif'];
    
        if (file.size > maxSize) { 
          showToast('File size exceeds 5MB limit', 'error'); 
          this.value = ''; 
          return; 
        }
        
        if (!validTypes.includes(file.type)) { 
          showToast('Please upload JPG, PNG or GIF files only', 'error'); 
          this.value = ''; 
          return; 
        }
    
        $('.upload-progress').show();
        $('.progress-fill').css('width', '0%');
        $('.progress-text').text('Uploading: 0%');
    
        const formData = new FormData();
        formData.append('photo', file);
        formData.append('nonce', sud_config.ajax_nonce);
    
        $.ajax({
          url: sud_vars.sud_url + '/ajax/upload-photo.php', 
          type: 'POST', 
          data: formData, 
          processData: false, 
          contentType: false, 
          dataType: 'json',
          xhr: function() {
            const xhr = new window.XMLHttpRequest();
            xhr.upload.addEventListener('progress', function(e) {
              if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                $('.progress-fill').css('width', percent + '%');
                $('.progress-text').text('Uploading: ' + percent + '%');
              }
            }, false);
            return xhr;
          },
          success: function(response) {
            $('.upload-progress').hide();
            if (response.success && response.data && response.data.image_url && response.data.attachment_id) {
              $('#new_photo').val('');
              showToast('Photo uploaded successfully', 'success');
              const photoId = response.data.attachment_id;
              const photoUrl = response.data.image_url; 
              const isProfilePic = response.is_profile_picture || ($('#photos-grid .photo-item').length === 0); 
    
              const newModalHtml = generatePhotoItems([{ id: photoId, url: photoUrl, isProfile: isProfilePic }]); 
              $('#photos-grid .photo-upload').before(newModalHtml);
    
              setTimeout(() => {
                $('#photos-grid .photo-item').last().addClass('fade-in-up');
              }, 10);
    
              const $summaryGrid = $('#photos-summary-grid');
              const summaryPhotoUrl = response.data.image_url;
              const newSummaryHtml = `
              <div class="photo-item ${isProfilePic ? 'is-profile-pic' : ''}" data-photo-id="${photoId}">
                <div class="photo-item-image-wrapper">
                  <img src="${summaryPhotoUrl}" alt="User photo" loading="lazy" onerror="this.style.display='none';">
                </div>
                ${isProfilePic ? '<span class="profile-badge-photo-summary"><i class="fas fa-star"></i></span>' : ''}
              </div>`;
              
              // If summary was empty, replace the empty text
              if ($summaryGrid.find('.empty-text').length > 0) {
                $summaryGrid.html(newSummaryHtml);
              } else {
                $summaryGrid.append(newSummaryHtml);
              }
    
              updateSectionCompletionStatus('photos');
              updatePhotoSummaryDisplay();
              fetchAndUpdateCompletionPercentage();
            } else { 
              showToast(response.message || 'Upload failed', 'error'); 
            }
          },
          error: function() { 
            $('.upload-progress').hide(); 
            showToast('Error uploading photo', 'error'); 
          }
        });
      }
    });
    
    $container.off('click', '.make-profile-btn').on('click', '.make-profile-btn', function(e) {
      e.preventDefault(); 
      e.stopPropagation();
      const photoId = $(this).data('id');
      const $button = $(this);
      $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
      $.ajax({
        url: sud_vars.sud_url + '/ajax/update-profile.php', 
        type: 'POST',
        data: { 
          action: 'set_profile_picture', 
          photo_id: photoId,
          nonce: sud_config.ajax_nonce
        }, 
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            showToast('Profile picture updated', 'success');
            const profilePicSelector = `.photo-item[data-photo-id="${photoId}"]`;
            const prevProfilePicSelector = '.photo-item.is-profile-pic';

            const $prevModalItem = $('#photos-grid').find(prevProfilePicSelector);
            const $prevSummaryItem = $('#photos-summary-grid').find(prevProfilePicSelector);
            const prevPhotoId = $prevModalItem.data('photo-id');
    
            $prevModalItem.removeClass('is-profile-pic');
            $prevModalItem.find('.photo-overlay .profile-badge-photo').remove();
            if ($prevModalItem.find('.photo-overlay .make-profile-btn').length === 0 && prevPhotoId) {
              $prevModalItem.find('.photo-overlay').prepend(
                `<button type="button" class="make-profile-btn" data-id="${prevPhotoId}" aria-label="Set as profile picture"><i class="fas fa-star"></i> Set Profile</button>`
              );
            }
            
            $prevSummaryItem.removeClass('is-profile-pic');
            $prevSummaryItem.find('.profile-badge-photo-summary').remove();
    
            const $newModalItem = $('#photos-grid').find(profilePicSelector);
            $newModalItem.addClass('is-profile-pic');
            $newModalItem.find('.photo-overlay .make-profile-btn').remove();
            if($newModalItem.find('.photo-overlay .profile-badge-photo').length === 0) {
              $newModalItem.find('.photo-overlay').prepend('<span class="profile-badge-photo"><i class="fas fa-star"></i> Profile</span>');
            }
            
            const $newSummaryItem = $('#photos-summary-grid').find(profilePicSelector);
            $newSummaryItem.addClass('is-profile-pic');
            
            if ($newSummaryItem.find('.profile-badge-photo-summary').length === 0) {
              $newSummaryItem.append('<span class="profile-badge-photo-summary"><i class="fas fa-star"></i></span>');
            }
    
            const newProfilePicUrl = $newModalItem.first().find('img').attr('src');
            if (newProfilePicUrl) $('.profile-avatar img').attr('src', newProfilePicUrl);
    
            if (typeof response.completion_percentage !== 'undefined') {
              $('.completion-progress').css('width', response.completion_percentage + '%');
              $('.completion-text').text(response.completion_percentage + '% Complete');
            }
            
            $newModalItem.addClass('pulse-animation');
            setTimeout(() => {
              $newModalItem.removeClass('pulse-animation');
            }, 800);
            
          } else { 
            showToast(response.message || 'Failed to update profile picture', 'error'); 
          }
        },
        error: function() { 
          showToast('Error updating profile picture', 'error'); 
        },
        complete: function() { 
          $button.prop('disabled', false).html('<i class="fas fa-star"></i> Set Profile'); 
        } 
      });
    });
    
    $container.off('click', '.delete-photo-btn').on('click', '.delete-photo-btn', function(e) {
      e.preventDefault(); 
      e.stopPropagation();
      const $button = $(this);
      if (!confirm('Are you sure you want to delete this photo?')) return;
      
      const photoId = $button.data('id');
      $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
    
      $.ajax({
        url: sud_vars.sud_url + '/ajax/update-profile.php', 
        type: 'POST',
        data: { 
          action: 'delete_photo', 
          photo_id: photoId,
          nonce: sud_config.ajax_nonce
        }, 
        dataType: 'json',
        success: function(response) {
          if (response.success) {
            showToast('Photo deleted', 'success');
            $(`.photo-item[data-photo-id="${photoId}"]`).addClass('fade-out');
            
            setTimeout(() => {
              $(`.photo-item[data-photo-id="${photoId}"]`).remove();
              
              updatePhotoSummaryDisplay();
            }, 300);
    
            if (response.profile_pic_cleared) {
              const defaultAvatar = window.sud_vars.default_avatar_url || '<?php echo SUD_IMG_URL; ?>/default-profile.jpg'; 
              $('.profile-avatar img').attr('src', defaultAvatar);
            }
    
            updateSectionCompletionStatus('photos');
            if (typeof response.completion_percentage !== 'undefined') {
              $('.completion-progress').css('width', response.completion_percentage + '%');
              $('.completion-text').text(response.completion_percentage + '% Complete');
            } else { 
              fetchAndUpdateCompletionPercentage(); 
            }
          } else {
            showToast(response.message || 'Failed to delete photo', 'error');
            $button.prop('disabled', false).html('<i class="fas fa-trash"></i>');
          }
        },
        error: function() {
          showToast('Error deleting photo', 'error');
          $button.prop('disabled', false).html('<i class="fas fa-trash"></i>');
        }
      });
    });
  }

  function updatePhotoSummaryDisplay() {
    const $photoGrid = $('#photos-summary-grid');
    if (!$photoGrid.length) return;

    const $photoItems = $photoGrid.find('.photo-item');

    $photoItems.removeClass('is-more-indicator').removeAttr('data-more-count');
    $photoItems.find('.photo-item-image-wrapper').css('filter', '');
    $photoItems.find('.more-indicator-overlay').remove();
    const totalPhotos = $photoItems.length;

    if (totalPhotos <= MAX_VISIBLE_PHOTOS) {
      $photoItems.show();
    } else {
      const extraCount = totalPhotos - MAX_VISIBLE_PHOTOS;
      $photoItems.each((index, item) => {
        const $item = $(item);
        if (index < MAX_VISIBLE_PHOTOS - 1) {
          $item.show();
        } else if (index === MAX_VISIBLE_PHOTOS - 1) {
          $item.show();
          $item.addClass('is-more-indicator');
          $item.attr('data-more-count', extraCount);

          $item.find('.photo-item-image-wrapper').css('filter', 'blur(3px)');

          const overlayHtml = `<div class="more-indicator-overlay"><span>+${extraCount}</span></div>`;
          $item.append(overlayHtml);
        } else {
          $item.hide();
        }
      });
    }
    $photoGrid.find('.photo-more-counter').remove();
  }
  
  function initPhotoGallery() {
    if ($('.photo-gallery-modal').length === 0) {
      const galleryHtml = `
        <div class="photo-gallery-modal">
          <button class="gallery-close">×</button>
          <div class="gallery-container">
            <button class="gallery-nav gallery-prev"><i class="fas fa-chevron-left"></i></button>
            <img class="gallery-prev-preview" src="" alt="">

            <div class="gallery-image-wrapper">
              <img src="" alt="Gallery photo" class="gallery-image">
              <div class="gallery-controls-container">
                <div class="gallery-counter">1 / 10</div>
                <div class="gallery-image-controls">
                  <button class="btn-primary gallery-make-profile-btn"><i class="fas fa-star"></i> Set as Profile</button>
                  <button class="btn-danger gallery-delete-btn"><i class="fas fa-trash"></i> Delete</button>
                </div>
              </div>
            </div>

            <img class="gallery-next-preview" src="" alt="">
            <button class="gallery-nav gallery-next"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
      `;
      $('body').append(galleryHtml);
      
      $('.gallery-close').on('click', closePhotoGallery);
      $('.gallery-prev').on('click', function() { navigateGallery('prev'); });
      $('.gallery-next').on('click', function() { navigateGallery('next'); });

      $('.gallery-make-profile-btn').on('click', function() {
        const photos = $('.photo-gallery-modal').data('photos');
        const currentIndex = $('.photo-gallery-modal').data('currentIndex');
        const currentPhoto = photos[currentIndex];
        
        if (currentPhoto && currentPhoto.id) {
          setProfilePhoto(currentPhoto.id);
        }
      });
      
      $('.gallery-delete-btn').on('click', function() {
        const photos = $('.photo-gallery-modal').data('photos');
        const currentIndex = $('.photo-gallery-modal').data('currentIndex');
        const currentPhoto = photos[currentIndex];
        
        if (currentPhoto && currentPhoto.id) {
          deletePhoto(currentPhoto.id);
        }
      });

      $(document).on('keydown', function(e) {
        if ($('.photo-gallery-modal.active').length === 0) return;
        
        switch(e.key) {
          case 'ArrowLeft':
            navigateGallery('prev');
            break;
          case 'ArrowRight':
            navigateGallery('next');
            break;
          case 'Escape':
            closePhotoGallery();
            break;
        }
      });

      $('.photo-gallery-modal').on('click', function(e) {
        if (e.target === this) {
          closePhotoGallery();
        }
      });
      
      let touchStartX = 0;
      let touchEndX = 0;
      
      $('.photo-gallery-modal').on('touchstart', function(e) {
        touchStartX = e.originalEvent.touches[0].clientX;
      });
      
      $('.photo-gallery-modal').on('touchend', function(e) {
        touchEndX = e.originalEvent.changedTouches[0].clientX;
        handleSwipe();
      });
      
      function handleSwipe() {
        if (touchEndX < touchStartX - 50) {
          navigateGallery('next');
        } else if (touchEndX > touchStartX + 50) {
          navigateGallery('prev');
        }
      }
    }

    $(document).on('click', '.photo-more-counter, #photos-summary-grid .photo-item.is-more-indicator', function() {
      const allPhotos = collectAllPhotoData();
      let startIndex = 0;
      if ($(this).hasClass('is-more-indicator')) {
        startIndex = MAX_VISIBLE_PHOTOS -1; 
      }
      openPhotoGallery(allPhotos, startIndex); 
    });

    $(document).on('click', '#photos-summary-grid .photo-item:not(.is-more-indicator)', function() {
      const allPhotos = collectAllPhotoData();
      const photoId = $(this).data('photo-id');
      const photoIndex = allPhotos.findIndex(p => p.id === photoId);
      if (photoIndex !== -1) {
       openPhotoGallery(allPhotos, photoIndex);
      }
    });
    updatePhotoSummaryDisplay();
  }
  
  function collectAllPhotoData() {
    const photoData = [];
    $('#photos-summary-grid .photo-item').each(function() {
      const $item = $(this);
      if (!$item.hasClass('photo-more-counter')) {
        const photoId = $item.data('photo-id');
        const photoUrl = $item.find('img').attr('src');
        const isProfile = $item.hasClass('is-profile-pic');
        
        if (photoId && photoUrl) {
          photoData.push({ id: photoId, url: photoUrl, isProfile: isProfile });
        }
      }
    });
    return photoData;
  }
  
  function openPhotoGallery(photos, startIndex) {
    if (photos.length === 0) return;
    
    const $modal = $('.photo-gallery-modal');
    const $image = $modal.find('.gallery-image');
    const $counter = $modal.find('.gallery-counter');
    
    $modal.data('photos', photos);
    $modal.data('currentIndex', startIndex);
    
    $image.attr('src', photos[startIndex].url);
    $counter.text(`${startIndex + 1} / ${photos.length}`);
    
    const prevIndex = (startIndex - 1 + photos.length) % photos.length;
    const nextIndex = (startIndex + 1) % photos.length;
    $modal.find('.gallery-prev-preview').attr('src', photos[prevIndex].url);
    $modal.find('.gallery-next-preview').attr('src', photos[nextIndex].url);
    
    updateGalleryControlButtons(photos[startIndex]);
    
    $modal.addClass('active');
    $('body').css('overflow', 'hidden');
  }
  
  function navigateGallery(direction) {
    const $modal = $('.photo-gallery-modal');
    const photos = $modal.data('photos');
    let currentIndex = $modal.data('currentIndex');
    
    if (direction === 'next') {
      currentIndex = (currentIndex + 1) % photos.length;
    } else {
      currentIndex = (currentIndex - 1 + photos.length) % photos.length;
    }
    
    $modal.data('currentIndex', currentIndex);
    $modal.find('.gallery-image').attr('src', photos[currentIndex].url);
    $modal.find('.gallery-counter').text(`${currentIndex + 1} / ${photos.length}`);
    
    const prevIndex = (currentIndex - 1 + photos.length) % photos.length;
    const nextIndex = (currentIndex + 1) % photos.length;
    $modal.find('.gallery-prev-preview').attr('src', photos[prevIndex].url);
    $modal.find('.gallery-next-preview').attr('src', photos[nextIndex].url);
    
    updateGalleryControlButtons(photos[currentIndex]);
  }
  
  function updateGalleryControlButtons(photo) {
    const $makeProfileBtn = $('.gallery-make-profile-btn');
    
    if (photo.isProfile) {
      $makeProfileBtn.addClass('is-profile').text('Profile Picture');
      $makeProfileBtn.prop('disabled', true);
    } else {
      $makeProfileBtn.removeClass('is-profile').html('<i class="fas fa-star"></i> Set as Profile');
      $makeProfileBtn.prop('disabled', false);
    }
  }
  
  function closePhotoGallery() {
    $('.photo-gallery-modal').removeClass('active');
    $('body').css('overflow', '');
  }

  function setProfilePhoto(photoId) {
    if (!photoId) return;
    
    const $button = $('.gallery-make-profile-btn');
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  
    $.ajax({
      url: sud_vars.sud_url + '/ajax/update-profile.php', 
      type: 'POST',
      data: { 
        action: 'set_profile_picture', 
        photo_id: photoId,
        nonce: sud_config.ajax_nonce
      }, 
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          showToast('Profile picture updated', 'success');
          
          const $modal = $('.photo-gallery-modal');
          const photos = $modal.data('photos');
          const currentIndex = $modal.data('currentIndex');
          
          for (let i = 0; i < photos.length; i++) {
            if (photos[i].isProfile) {
              photos[i].isProfile = false;
            }
          }
          
          photos[currentIndex].isProfile = true;
          $modal.data('photos', photos);
          
          updateGalleryControlButtons(photos[currentIndex]);
          updateDOMAfterProfileChange(photoId);
          
          if (typeof response.completion_percentage !== 'undefined') {
            $('.completion-progress').css('width', response.completion_percentage + '%');
            $('.completion-text').text(response.completion_percentage + '% Complete');
          }
        } else { 
          showToast(response.message || 'Failed to update profile picture', 'error'); 
          $button.prop('disabled', false).html('<i class="fas fa-star"></i> Set as Profile');
        }
      },
      error: function() { 
        showToast('Error updating profile picture', 'error'); 
        $button.prop('disabled', false).html('<i class="fas fa-star"></i> Set as Profile');
      }
    });
  }
  
  function deletePhoto(photoId) {
    if (!photoId || !confirm('Are you sure you want to delete this photo?')) return;
    
    const $button = $('.gallery-delete-btn');
    $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
  
    $.ajax({
      url: sud_vars.sud_url + '/ajax/update-profile.php', 
      type: 'POST',
      data: { 
        action: 'delete_photo', 
        photo_id: photoId,
        nonce: sud_config.ajax_nonce
      }, 
      dataType: 'json',
      success: function(response) {
        if (response.success) {
          showToast('Photo deleted', 'success');
          
          $button.prop('disabled', false).html('<i class="fas fa-trash"></i> Delete');

          const $modal = $('.photo-gallery-modal');
          let photos = $modal.data('photos');
          const currentIndex = $modal.data('currentIndex');
          
          photos = photos.filter(p => p.id !== photoId);
          $modal.data('photos', photos);
          
          if (photos.length > 0) {
            const newIndex = currentIndex >= photos.length ? photos.length - 1 : currentIndex;
            $modal.data('currentIndex', newIndex);
            
            $modal.find('.gallery-image').attr('src', photos[newIndex].url);
            $modal.find('.gallery-counter').text(`${newIndex + 1} / ${photos.length}`);
            updateGalleryControlButtons(photos[newIndex]);
          } else {
            closePhotoGallery();
          }
          
          $(`.photo-item[data-photo-id="${photoId}"]`).fadeOut(300, function() { 
            $(this).remove();
            updatePhotoSummaryDisplay();
          });
          
          if (response.profile_pic_cleared) {
            const defaultAvatar = window.sud_vars.default_avatar_url || '<?php echo SUD_IMG_URL; ?>/default-profile.jpg'; 
            $('.profile-avatar img').attr('src', defaultAvatar);
          }
          
          updateSectionCompletionStatus('photos');
          if (typeof response.completion_percentage !== 'undefined') {
            $('.completion-progress').css('width', response.completion_percentage + '%');
            $('.completion-text').text(response.completion_percentage + '% Complete');
          } else { 
            fetchAndUpdateCompletionPercentage(); 
          }
        } else {
          showToast(response.message || 'Failed to delete photo', 'error');
          $button.prop('disabled', false).html('<i class="fas fa-trash"></i> Delete');
        }
      },
      error: function() {
        showToast('Error deleting photo', 'error');
        $button.prop('disabled', false).html('<i class="fas fa-trash"></i> Delete');
      }
    });
  }

  function updateDOMAfterProfileChange(photoId) {
    const profilePicSelector = `.photo-item[data-photo-id="${photoId}"]`;
    const prevProfilePicSelector = '.photo-item.is-profile-pic';
  
    const $prevModalItem = $('#photos-grid').find(prevProfilePicSelector);
    const $prevSummaryItem = $('#photos-summary-grid').find(prevProfilePicSelector);
    const prevPhotoId = $prevModalItem.data('photo-id');
  
    $prevModalItem.removeClass('is-profile-pic');
    $prevModalItem.find('.photo-overlay .profile-badge-photo').remove();
    if ($prevModalItem.find('.photo-overlay .make-profile-btn').length === 0 && prevPhotoId) {
      $prevModalItem.find('.photo-overlay').prepend(
        `<button type="button" class="make-profile-btn" data-id="${prevPhotoId}" aria-label="Set as profile picture"><i class="fas fa-star"></i> Set Profile</button>`
      );
    }
    
    $prevSummaryItem.removeClass('is-profile-pic');
    $prevSummaryItem.find('.profile-badge-photo-summary').remove();
  
    const $newModalItem = $('#photos-grid').find(profilePicSelector);
    $newModalItem.addClass('is-profile-pic');
    $newModalItem.find('.photo-overlay .make-profile-btn').remove();
    if($newModalItem.find('.photo-overlay .profile-badge-photo').length === 0) {
      $newModalItem.find('.photo-overlay').prepend('<span class="profile-badge-photo"><i class="fas fa-star"></i> Profile</span>');
    }
    
    const $newSummaryItem = $('#photos-summary-grid').find(profilePicSelector);
    $newSummaryItem.addClass('is-profile-pic');

    if ($newSummaryItem.find('.profile-badge-photo-summary').length === 0) {
      $newSummaryItem.append('<span class="profile-badge-photo-summary"><i class="fas fa-star"></i></span>');
    }
  
    const newProfilePicUrl = $newModalItem.first().find('img').attr('src');
    if (newProfilePicUrl) $('.profile-avatar img').attr('src', newProfilePicUrl);
  }

  function generatePhotoItems(photosData) {
    if (!photosData || photosData.length === 0) {
        return ''; 
    }
    let html = '';
    photosData.forEach((photo) => {
        const photoId = photo.id || '';
        html += `
          <div class="photo-item ${photo.isProfile ? 'is-profile-pic' : ''}" data-photo-id="${photoId}">
            <img src="${photo.url}" alt="User photo" loading="lazy" onerror="this.style.display='none';">
            <div class="photo-overlay">  <?php // Ensure overlay is here ?>
              ${photo.isProfile ?
                  `<span class="profile-badge-photo">Profile</span>`
                : `<button type="button" class="make-profile-btn" data-id="${photoId}" aria-label="Set as profile picture">Set Profile</button>`
              }
              <?php // Ensure delete button is here with class 'delete-photo-btn' ?>
              <button type="button" class="delete-photo-btn" data-id="${photoId}" aria-label="Delete photo"><i class="fas fa-trash"></i></button>
            </div>
          </div>
        `;
    });
    return html;
  }

  function getValueFromText(text, field) {
    if (text === 'Not specified') return '';

    text = text.toLowerCase().trim();

    if (field === 'body_type') {
      if (text.includes('athletic')) return 'athletic';
      if (text.includes('average')) return 'average';
      if (text.includes('slim')) return 'slim';
      if (text.includes('curvy')) return 'curvy';
      if (text.includes('muscular')) return 'muscular';
      if (text.includes('full')) return 'full_figured';
      if (text.includes('plus')) return 'plus_size';
    }
    else if (field === 'ethnicity') {
      if (text.includes('african')) return 'african';
      if (text.includes('asian')) return 'asian';
      if (text.includes('caucasian')) return 'caucasian';
      if (text.includes('hispanic')) return 'hispanic';
      if (text.includes('middle')) return 'middle_eastern';
      if (text.includes('latino')) return 'latino';
      if (text.includes('native')) return 'native_american';
      if (text.includes('pacific')) return 'pacific_islander';
      if (text.includes('multi')) return 'multiracial';
    }
    else if (field === 'relationship_status') {
      if (text.includes('single')) return 'single';
      if (text.includes('relationship')) return 'in_relationship';
      if (text.includes('married')) return 'married_looking';
      if (text.includes('separated')) return 'separated';
      if (text.includes('divorced')) return 'divorced';
      if (text.includes('widowed')) return 'widowed';
    }
    else if (field === 'smoke') {
      if (text.includes('non')) return 'non_smoker';
      if (text.includes('light')) return 'light_smoker';
      if (text.includes('heavy')) return 'heavy_smoker';
    }
    else if (field === 'drink') {
      if (text.includes('non')) return 'non_drinker';
      if (text.includes('social')) return 'social_drinker';
      if (text.includes('heavy')) return 'heavy_drinker';
    }

    return text.replace(/\s+/g, '_').replace(/[^a-z0-9_]/g, '');
  }

  function saveCurrentSection() {
    const formData = new FormData();
    let formDataObj = {};
    let isDataValid = true;

    switch(currentSection) {
      case 'basic':
        formDataObj = collectBasicFormData(formData);
        break;
      case 'terms':
        formDataObj = collectTermsFormData(formData);
        break;
      case 'dating':
        formDataObj = collectDatingStylesFormData(formData);
        break;
      case 'financial':
        formDataObj = collectFinancialFormData(formData);
        break;
      case 'location':
        formDataObj = collectLocationFormData();
        if (!formDataObj) {
          isDataValid = false;
        }
        break;
      case 'appearance':
        formDataObj = collectAppearanceFormData(formData);
        break;
      case 'personal':
        formDataObj = collectPersonalFormData(formData);
        break;
      case 'about':
        formDataObj = collectAboutFormData(formData);
        break;
      case 'looking':
        formDataObj = collectLookingForFormData(formData);
        break;
      case 'photos':
        closeModal();
        return;
    }

    if (!isDataValid) {
      return;
    }

    formData.append('field', currentSection);

    $.ajax({

      url: sud_config.sud_url + '/ajax/update-profile.php', 
      type: 'POST',
      data: {
        action: 'update_profile', 
        field: currentSection,
        nonce: sud_config.ajax_nonce,
        ...formDataObj 
      },
      dataType: 'json',
      success: function(response) {
        if (response && response.success) {
          updateSectionDisplay(currentSection, formDataObj);

          if (typeof response.completion_percentage !== 'undefined') {
            $('.completion-progress').css('width', response.completion_percentage + '%');
            $('.completion-text').text(response.completion_percentage + '% Complete');
          }
          showToast('Settings saved successfully', 'success');
          closeModal();
        } else {
          showToast(response?.message || 'Failed to save settings', 'error');
        }
      },
      error: function(xhr) {
        console.error("AJAX Save Error:", xhr.status, xhr.responseText);
        showToast('Error saving settings. Please check connection and try again.', 'error');
      }
    });
  }

  function collectBasicFormData() {
    return {
      display_name: $('#modal-display-name').val(),
    };
  }

  function collectTermsFormData() {
    const terms = $('#terms-input').val();
    return {
      relationship_terms: terms,
    };
  }

  function collectDatingStylesFormData() {
    const styles = $('#styles-input').val();
    return {
      dating_styles: styles
    };
  }

  function collectFinancialFormData() {
    const income = $('#modal-income').val();
    const netWorth = $('#modal-networth').val();
    const budget = $('#modal-budget').val();
    return {
      annual_income: income,
      net_worth: netWorth,
      dating_budget: budget
    };
  }

  function collectLocationFormData() {
    const lat = $('#modal-latitude').val();
    const lng = $('#modal-longitude').val();

    if (!lat || !lng || isNaN(parseFloat(lat)) || isNaN(parseFloat(lng))) {
        console.error("Attempted to collect invalid location data from modal.");
        showToast('Please select a valid location from the suggestions before saving.', 'error');
        return null;
    }

    return {
      latitude: lat,
      longitude: lng,
      city_google: $('#modal-city_google').val(),
      region_google: $('#modal-region_google').val(),
      country_google: $('#modal-country_google').val(),
      accuracy: $('#modal-accuracy').val() || 'google_places_edit'
    };
  }

  function collectAppearanceFormData() {
    const height = $('#modal-height').val();
    const bodyType = $('#modal-body-type').val();
    const ethnicity = $('#modal-ethnicity').val();
    const race = $('#modal-race').val();
    const eyeColor = $('#modal-eye-color').val();
    const hairColor = $('#modal-hair-color').val();

    return {
      height: height,
      body_type: bodyType,
      ethnicity: ethnicity,
      race: race,
      eye_color: eyeColor,
      hair_color: hairColor
    };
  }

  function collectPersonalFormData() {
    const occupation = $('#modal-occupation').val();
    const industry = $('#modal-industry').val();
    const education = $('#modal-education').val();
    const relationship = $('#modal-relationship').val();
    const smoke = $('#modal-smoke').val();
    const drink = $('#modal-drink').val();

    return {
      occupation: occupation,
      industry: industry,
      education: education,
      relationship_status: relationship,
      smoke: smoke,
      drink: drink
    };
  }

  function collectAboutFormData() {
    const aboutMe = $('#modal-about').val();

    return {
      about_me: aboutMe
    };
  }

  function collectLookingForFormData() {
    const ageMin = $('#age_min').val();
    const ageMax = $('#age_max').val();
    const ethnicities = $('#looking-ethnicities-input').val();

    return {
      looking_for_age_min: ageMin,
      looking_for_age_max: ageMax,
      looking_for_ethnicities: ethnicities
    };
  }

  function checkSectionCompletion(section, savedData) {
    let isComplete = false;
    const functionalRole = window.sud_vars.userFunctionalRole || 'provider'; 

    switch(section) {
      case 'basic':
        const ageTextCheck = $('#user-age').text().trim();
        isComplete = !!savedData.display_name && ageTextCheck !== 'Not set' && ageTextCheck !== '';
        break;
      case 'terms':
        isComplete = savedData.relationship_terms && savedData.relationship_terms.split(',').filter(Boolean).length >= 3;
        break;
      case 'dating':
        isComplete = savedData.dating_styles && savedData.dating_styles.split(',').filter(Boolean).length > 0;
        break;
      case 'financial':
        if (functionalRole !== 'receiver') {
          isComplete = !!savedData.annual_income && !!savedData.net_worth && !!savedData.dating_budget;
        } else {
          isComplete = true; 
        }
        break;
      case 'location':
        isComplete = !!savedData.city && !!savedData.country;
        break;
      case 'appearance':
        isComplete = !!savedData.height && !!savedData.body_type && !!savedData.ethnicity && !!savedData.race;
        break;
      case 'personal':
        isComplete = !!savedData.occupation && !!savedData.relationship_status && !!savedData.smoke && !!savedData.drink;
        break;
      case 'about':
        isComplete = !!savedData.about_me && savedData.about_me.trim().length > 0;
        break;
      case 'looking':

        isComplete = (!!savedData.looking_for_age_min && !!savedData.looking_for_age_max) &&
                     (!!savedData.looking_for_ethnicities && savedData.looking_for_ethnicities.trim().length > 0) ;
        break;
      case 'photos':

        let photoCount = $('#photos-summary-grid .photo-item').length;
        if ($('.modal-overlay.active #photos-grid').length > 0) {
             photoCount = Math.max(photoCount, $('#photos-grid .photo-item').length);
        }
        isComplete = photoCount > 0;
        break;
      default:
        isComplete = false;
    }

    return isComplete;
  }

  function updateSectionDisplay(section, savedData) {
    switch(section) {
      case 'basic':
        $('#user-display-name-summary').text(savedData.display_name);
        $('.profile-header .profile-name').text(savedData.display_name);
        break;
      case 'terms':
          updateTagsSummary('#terms-summary', savedData.relationship_terms, attributeOptions.interests, 'No terms selected yet (Min 3 required)');
          break;
      case 'dating':
          updateTagsSummary('#dating-summary', savedData.dating_styles, attributeOptions.dating_style, 'No dating styles selected yet');
          break;
      case 'financial':
          if ($('#financial-income').length) {
              $('#financial-income')
                  .text(getLabelFromValue(attributeOptions.annual_income, savedData.annual_income))
                  .data('value', savedData.annual_income);
              $('#financial-networth')
                  .text(getLabelFromValue(attributeOptions.net_worth, savedData.net_worth))
                  .data('value', savedData.net_worth);
              $('#financial-budget')
                  .text(getLabelFromValue(attributeOptions.dating_budget, savedData.dating_budget))
                  .data('value', savedData.dating_budget);
          }
          break;
      case 'location':
        let formatted = [savedData.city_google, savedData.region_google, savedData.country_google]
                          .filter(Boolean).join(', ');
        $('#location-formatted').text(formatted || 'Not specified');
        $('#current-city').val(savedData.city_google || '');
        $('#current-region').val(savedData.region_google || '');
        $('#current-country').val(savedData.country_google || '');
        $('#current-latitude').val(savedData.latitude || '');
        $('#current-longitude').val(savedData.longitude || '');
        break;
      case 'appearance':
        $('#appearance-height')
            .text(savedData.height ? savedData.height + ' cm' : 'Not specified')
            .data('value', savedData.height);
        $('#appearance-body')
            .text(getLabelFromValue(attributeOptions.body_type, savedData.body_type))
            .data('value', savedData.body_type);
        $('#appearance-ethnicity')
            .text(getLabelFromValue(attributeOptions.ethnicity, savedData.ethnicity))
            .data('value', savedData.ethnicity);
        $('#appearance-race')
            .text(getLabelFromValue(attributeOptions.race, savedData.race))
            .data('value', savedData.race);
        $('#appearance-eye')
            .text(getLabelFromValue(attributeOptions.eye_color, savedData.eye_color))
            .data('value', savedData.eye_color);
        $('#appearance-hair')
            .text(getLabelFromValue(attributeOptions.hair_color, savedData.hair_color))
            .data('value', savedData.hair_color);
        break;

      case 'personal':
        $('#personal-occupation')
            .text(savedData.occupation || 'Not specified');
        $('#personal-industry')
            .text(getLabelFromValue(attributeOptions.industry, savedData.industry))
            .data('value', savedData.industry);
        $('#personal-education')
            .text(getLabelFromValue(attributeOptions.education, savedData.education))
            .data('value', savedData.education);
        $('#personal-relationship')
            .text(getLabelFromValue(attributeOptions.relationship_status, savedData.relationship_status))
            .data('value', savedData.relationship_status);
        $('#personal-smoke')
            .text(getLabelFromValue(attributeOptions.smoke, savedData.smoke))
            .data('value', savedData.smoke);
        $('#personal-drink')
            .text(getLabelFromValue(attributeOptions.drink, savedData.drink))
            .data('value', savedData.drink);
        break;
      case 'about':
        const aboutText = savedData.about_me ? savedData.about_me.trim() : '';
        const aboutHtml = aboutText ? escapeHtml(aboutText).replace(/\n/g, '<br>') : '<span class="empty-text"></span>';
        $('#about-summary').html(aboutHtml);
        break;
      case 'looking':
        $('#looking-age').text(`${savedData.looking_for_age_min} - ${savedData.looking_for_age_max} years`);
        updateLookingForTagsSummary('#looking-ethnicities', savedData.looking_for_ethnicities);
        $('#looking-ethnicities').data('raw-values', savedData.looking_for_ethnicities);
        break;
      case 'photos':
        break;
    }

    const isNowComplete = checkSectionCompletion(section, savedData);
    const $header = $('.accordion-header[data-section-completion="' + section + '"]');
    const $successIcon = $header.find('.completion-icon-success-' + section);
    const $incompleteIcon = $header.find('.completion-icon-incomplete-' + section);

    if (isNowComplete) { $successIcon.show(); $incompleteIcon.hide(); }
    else { $successIcon.hide(); $incompleteIcon.show(); }
  }

  function updateTagsSummary(summarySelector, commaSeparatedValues, optionsSubObject, emptyText) {
    const $summary = $(summarySelector);
    const selectedValues = commaSeparatedValues ? commaSeparatedValues.split(',') : [];
    if (selectedValues.length > 0) {
        let tagsHtml = '';
        selectedValues.forEach(value => {
            const label = getLabelFromValue(optionsSubObject, value, value);
            tagsHtml += `<span class="tag">${escapeHtml(label)}</span>`;
        });
        $summary.html(tagsHtml);
    } else {
        $summary.html(`<span class="empty-text">${emptyText}</span>`);
    }
  }

  function updateLookingForTagsSummary(summarySelector, commaSeparatedCombos) {
    const $summary = $(summarySelector);
    const selectedCombos = commaSeparatedCombos ? commaSeparatedCombos.split(',').filter(Boolean) : [];

    if (selectedCombos.length === 1 && selectedCombos[0] === 'any_ethnicity') {
        $summary.html('<span class="tag">Open to Any Ethnicity</span>');
    } else if (selectedCombos.length > 0 && selectedCombos[0] !== 'any_ethnicity') {
        let tagsHtml = '';
        selectedCombos.forEach(comboVal => {
          const [ethnicityVal, raceVal] = comboVal.split('|');
          const ethLabel = getLabelFromValue(attributeOptions.ethnicity, ethnicityVal, ethnicityVal);
          const raceLabel = (raceVal && raceVal.trim() !== '') ? getLabelFromValue(attributeOptions.race, raceVal, raceVal) : '';
          const displayLabel = `${escapeHtml(ethLabel)}${(raceLabel && raceLabel !== 'Not specified') ? ', ' + escapeHtml(raceLabel) : ''}`;
          tagsHtml += `<span class="tag">${displayLabel}</span>`;
        });
        $summary.html(tagsHtml);
    } else {
      $summary.html('<span class="empty-text">No preferences set</span>');
    }
  }

  function showToast(message, type = 'info') {
    if (typeof SUD !== 'undefined' && SUD.showToast) {
      const toastType = type === 'success' ? 'success' : (type === 'error' ? 'error' : 'info');
      const title = type === 'success' ? 'Success' : (type === 'error' ? 'Error' : 'Notice');
      SUD.showToast(toastType, title, message);
    } else {
      console.warn(`Toast [${type}]: ${message}`);
    }
  }
  $(document).ready(function() {
    init();
  });

})(jQuery);