<?php

function get_complete_user_profile($user_id) {
    $profile = get_user_profile_data($user_id);
    if (!$profile) return null;

    $additional_meta_keys = [
        'about_me',
        'relationship_status',
        'occupation',
        'ethnicity',
        'race',
        'smoke',
        'drink',
        'height',
        'weight',
        'relationship_terms',
        'dating_styles',
        'annual_income',
        'net_worth',
        'dating_budget',
        'looking_for_age_min',
        'looking_for_age_max',
        'looking_for_ethnicities',
        'industry',
        'education',
        'body_type',
        'eye_color',
        'hair_color',
    ];

    foreach ($additional_meta_keys as $key) {
        $profile[$key] = get_user_meta($user_id, $key, true); 
    }

    $profile['looking_for_age_min'] = $profile['looking_for_age_min'] ?: 18;
    $profile['looking_for_age_max'] = $profile['looking_for_age_max'] ?: 70;
    $profile['relationship_terms'] = is_array($profile['relationship_terms'] ?? null) ? $profile['relationship_terms'] : [];
    $profile['dating_styles'] = is_array($profile['dating_styles'] ?? null) ? $profile['dating_styles'] : [];
    $profile['looking_for_ethnicities'] = is_array($profile['looking_for_ethnicities'] ?? null) ? $profile['looking_for_ethnicities'] : [];

    $functional_role = get_user_meta($user_id, 'functional_role', true);

    $profile_picture_id = get_user_meta($user_id, 'profile_picture', true);
    $user_photos_ids = get_user_meta($user_id, 'user_photos', true);
    if (!is_array($user_photos_ids)) {
        $user_photos_ids = [];
    }

    if ($profile_picture_id && !in_array($profile_picture_id, $user_photos_ids)) {
        array_unshift($user_photos_ids, $profile_picture_id);
    }
    $user_photos_ids = array_unique($user_photos_ids);
    $photo_urls = [];
    foreach ($user_photos_ids as $photo_id) {
        if (get_post_status($photo_id)) { 
             $large_url = wp_get_attachment_image_url($photo_id, 'large');
             $thumb_url = wp_get_attachment_image_url($photo_id, 'thumbnail');
             if ($large_url && $thumb_url) {
                $photo_urls[] = [
                     'id' => $photo_id,
                     'url' => $large_url,
                     'thumbnail' => $thumb_url,
                 ];
             }
         }
    }
    $profile['photos'] = $photo_urls;
    $viewers_meta = get_user_meta($user_id, 'profile_viewers', true);
    $profile['profile_viewers_count'] = is_array($viewers_meta) ? count($viewers_meta) : 0;
    return $profile;
}

function get_attribute_options($attribute) {
    $all_attribute_options = [
        'gender' => [
            'Man'    => 'Man',
            'Woman'  => 'Woman',
            'LGBTQ+' => 'LGBTQ+',
        ],
        'role' => [
            'Sugar Daddy/Mommy' => 'Sugar Daddy/Mommy',
            'Sugar Baby'        => 'Sugar Baby',
        ],
        'looking_for' => [
            'Sugar Daddy/Mommy' => 'Sugar Daddy/Mommy',
            'Sugar Baby'        => 'Sugar Baby',
            'Gay'               => 'Gay Partner',
            'Lesbian'           => 'Lesbian Partner',
        ],
        'smoke' => [
            'non_smoker' => 'Non-Smoker',
            'light_smoker' => 'Light Smoker',
            'heavy_smoker' => 'Heavy Smoker'
        ],
        'drink' => [
            'non_drinker' => 'Non-Drinker',
            'social_drinker' => 'Social Drinker',
            'heavy_drinker' => 'Heavy Drinker'
        ],
        'relationship_status' => [
            'single' => 'Single',
            'in_relationship' => 'In a Relationship',
            'married_looking' => 'Married but Looking',
            'separated' => 'Separated',
            'divorced' => 'Divorced',
            'widowed' => 'Widowed'
        ],
        'ethnicity' => [
            'african' => 'African', 'asian' => 'Asian', 'caucasian' => 'Caucasian',
            'hispanic' => 'Hispanic', 'middle_eastern' => 'Middle Eastern', 'latino' => 'Latino',
            'native_american' => 'Native American', 'pacific_islander' => 'Pacific Islander',
            'multiracial' => 'Multiracial', 'other' => 'Other'
        ],
        'race' => [
            'american' => 'American', 'australian' => 'Australian', 'austrian' => 'Austrian',
            'british' => 'British', 'bulgarian' => 'Bulgarian', 'canadian' => 'Canadian',
            'croatian' => 'Croatian', 'czech' => 'Czech', 'danish' => 'Danish', 'dutch' => 'Dutch',
            'european' => 'European', 'finnish' => 'Finnish', 'french' => 'French', 'german' => 'German',
            'greek' => 'Greek', 'hungarian' => 'Hungarian', 'irish' => 'Irish', 'italian' => 'Italian',
            'new_zealander' => 'New Zealander', 'norwegian' => 'Norwegian', 'polish' => 'Polish',
            'portuguese' => 'Portuguese', 'romanian' => 'Romanian', 'russian' => 'Russian',
            'scottish' => 'Scottish', 'serbian' => 'Serbian', 'slovak' => 'Slovak',
            'spanish' => 'Spanish', 'swedish' => 'Swedish', 'swiss' => 'Swiss',
            'ukrainian' => 'Ukrainian', 'welsh' => 'Welsh'
        ],
        'dating_style' => [
            'animal_lovers' => 'Animal Lovers', 'arts_culture' => 'Arts & Culture Dates', 'beach_days' => 'Beach Days',
            'brunch_dates' => 'Brunch Dates', 'clubbing' => 'Clubbing & Partying', 'coffee_dates' => 'Coffee Dates',
            'comedy_night' => 'Comedy Night', 'cooking_classes' => 'Cooking Classes', 'crafting' => 'Crafting Workshops',
            'dinner_dates' => 'Dinner Dates', 'drinks' => 'Drinks', 'fitness_dates' => 'Fitness Dates',
            'foodie_dates' => 'Foodie Dates', 'gaming' => 'Gaming', 'lunch_dates' => 'Lunch Dates',
            'luxury' => 'Luxury High-Tea', 'meet_tonight' => 'Meet Tonight', 'movies' => 'Movies & Chill',
            'music_festivals' => 'Music Festivals', 'nature' => 'Nature & Outdoors', 'sailing' => 'Sailing & Water Sports',
            'shopping' => 'Shopping', 'shows' => 'Shows & Concerts', 'spiritual' => 'Spiritual Journeys',
            'travel' => 'Travel', 'wine_tasting' => 'Wine Tasting'
        ],
        'interests' => [ 
            'friends_benefits' => 'Friends with Benefits', 'exclusive' => 'Exclusive', 'ma' => 'Monthly Allowance (MA)', 
            'ppm' => 'Pay Per Meet (PPM)', 
            'dtf' => 'DTF Tonight/Right Now', 'discreet' => 'Discreet',
            'high_net_worth' => 'Access to High Net Worth Individuals', 'all_ethnicities' => 'All Ethnicities',
            'hookups' => 'Hookups', 'in_relationship' => 'In a Relationship', 'lgbtq' => 'LGBTQ Friendly',
            'marriage' => 'Marriage', 'mentorship' => 'Mentorship', 'no_strings' => 'No Strings Attached',
            'open_relationship' => 'Open Relationship', 'passport_ready' => 'Passport Ready', 'platonic' => 'Platonic',
            'serious' => 'Serious Relationship', 'transgender' => 'Transgender Friendly',
            'travel_companion' => 'Travel Companion', 'travel_to_you' => 'Travel To You',
            'weekly_allowance' => 'Weekly Allowance (WA)' 
        ],
         'annual_income' => [ 
            '50000' => '$50,000',
            '75000' => '$75,000',
            '100000' => '$100,000',
            '125000' => '$125,000',
            '150000' => '$150,000',
            '200000' => '$200,000',
            '350000' => '$350,000',
            '400000' => '$400,000', 
            '500000' => '$500,000',
            '1000000' => '$1,000,000',
            '1000000plus' => '$1,000,000+'
        ],
        'net_worth' => [ 
            '100000' => '$100,000',
            '250000' => '$250,000',
            '500000' => '$500,000',
            '750000' => '$750,000',
            '1000000' => '$1,000,000',
            '2000000' => '$2,000,000',
            '5000000' => '$5,000,000',
            '10000000' => '$10,000,000',
            '50000000' => '$50,000,000',
            '100000000' => '$100,000,000', 
            '100000000plus' => '$100,000,000+'
        ],
        'dating_budget' => [ 
             '300-1000' => '$300-$1,000',
             '1000-3000' => '$1,000-$3,000',
             '3000-5000' => '$3,000-$5,000',
             '5000-9000' => '$5,000-$9,000',
             '9000-20000' => '$9,000-$20,000',
             '20000plus' => '$20,000+'
        ],
         'body_type' => [ 
            'athletic' => 'Athletic',
            'average' => 'Average',
            'slim' => 'Slim',
            'curvy' => 'Curvy',
            'muscular' => 'Muscular',
            'full_figured' => 'Full Figured',
            'plus_size' => 'Plus Size',
         ],
         'eye_color' => [ 
             'brown' => 'Brown',
             'blue' => 'Blue',
             'green' => 'Green',
             'hazel' => 'Hazel',
             'black' => 'Black',
             'gray' => 'Gray',
             'other' => 'Other',
         ],
         'hair_color' => [ 
             'black' => 'Black',
             'brown' => 'Brown',
             'blonde' => 'Blonde',
             'red' => 'Red',
             'gray' => 'Gray',
             'white' => 'White',
             'other' => 'Other',
         ],
         'industry' => [ 
            'accounting' => 'Accounting & Finance',
            'admin' => 'Administration & Office Support',
            'advertising' => 'Advertising & Marketing',
            'agriculture' => 'Agriculture & Farming',
            'arts' => 'Arts & Entertainment',
            'banking' => 'Banking & Financial Services',
            'construction' => 'Construction',
            'consulting' => 'Consulting',
            'education' => 'Education & Training',
            'engineering' => 'Engineering',
            'healthcare' => 'Healthcare',
            'hospitality' => 'Hospitality & Tourism',
            'hr' => 'Human Resources',
            'it' => 'Information Technology',
            'legal' => 'Legal',
            'manufacturing' => 'Manufacturing',
            'media' => 'Media & Communications',
            'military' => 'Military',
            'nonprofit' => 'Nonprofit & NGO',
            'real_estate' => 'Real Estate',
            'retail' => 'Retail & Sales',
            'science' => 'Science & Research',
            'sports' => 'Sports & Recreation',
            'telecommunications' => 'Telecommunications',
            'transport' => 'Transport & Logistics',
            'other' => 'Other'
         ],
         'education' => [ 
            'high_school' => 'High School',
            'some_college' => 'Some College',
            'associates' => 'Associates Degree',
            'bachelors' => 'Bachelor\'s Degree',
            'masters' => 'Master\'s Degree',
            'phd' => 'PhD/Doctorate',
            'trade_school' => 'Trade School',
            'other' => 'Other',
         ],

    ];

    if (isset($all_attribute_options[$attribute])) {
        return $all_attribute_options[$attribute];
    }
    return [];
}