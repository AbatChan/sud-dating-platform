function sudGetUserLocation(userId, ajaxUrl, successCallback, errorCallback) {
    if (!navigator.geolocation) {
        if (typeof errorCallback === 'function') {
            errorCallback('notsupported', 'Geolocation is not supported by your browser.');
        }
        return;
    }

    navigator.geolocation.getCurrentPosition(
        (position) => {
            const lat = position.coords.latitude;
            const lng = position.coords.longitude;
            const acc = position.coords.accuracy;

            const formData = new FormData();
            formData.append('user_id', userId);
            formData.append('latitude', lat);
            formData.append('longitude', lng);
            formData.append('accuracy', acc || 'unknown');

            fetch(ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin', 
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP error ${response.status}: ${text || 'Server error'}`);
                    });
                }
                return response.json();
            })
            .then(result => {
                console.log('[SUD Location] AJAX Update Response:', result);
                if (result && result.success) {
                    if (typeof successCallback === 'function') {

                        successCallback({
                            latitude: result.latitude || lat,
                            longitude: result.longitude || lng,
                            city: result.city || '',
                            country: result.country || '',
                            location_string: result.location_string || 'Location Updated'
                        });
                    }
                } else {
                    if (typeof errorCallback === 'function') {
                        errorCallback('servererror', result ? result.message : 'Server update failed.');
                    }
                }
            })
            .catch(error => {
                console.error('[SUD Location] AJAX fetch error:', error);
                if (typeof errorCallback === 'function') {
                    errorCallback('fetcherror', 'Could not reach server to update location. ' + error.message);
                }
            });
        },
        (error) => {
            console.warn(`[SUD Location] Geolocation error: ${error.message} (Code: ${error.code})`);
            let errorType = 'unknown';
            let userMessage = 'Could not get your location.';
            switch(error.code) {
                case error.PERMISSION_DENIED: 
                    errorType = 'denied';
                    userMessage = 'Location access denied. Please enable it in your browser settings.';
                    break;
                case error.POSITION_UNAVAILABLE: 
                    errorType = 'unavailable';
                    userMessage = 'Location information is unavailable. Please try again, perhaps outdoors.'; 
                    break;
                case error.TIMEOUT: 
                    errorType = 'timeout';
                    userMessage = 'The request to get user location timed out. Check your connection and try again.'; 
                    break;
            }
            if (typeof errorCallback === 'function') {
                errorCallback(errorType, userMessage);
            }
        },
        {
            enableHighAccuracy: true, 
            timeout: 10000,          
            maximumAge: 0           
        }
    );
}