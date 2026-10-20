(function () {
    'use strict';
    var lastReverseRequestTime = 0;

    function showMessage(element, message, type) {
        if (!element) return;
        element.textContent = message;
        element.className = 'location-picker-message mt-2 small ' + (type || '');
    }

    function coordinateValue(value) {
        var parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function reverseGeocode(latitude, longitude, addressInput, messageElement) {
        if (Date.now() - lastReverseRequestTime < 1100) return;
        lastReverseRequestTime = Date.now();
        var url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=' + encodeURIComponent(latitude) + '&lon=' + encodeURIComponent(longitude) + '&zoom=16&addressdetails=0';
        fetch(url, { headers: { Accept: 'application/json' } })
            .then(function (response) { return response.ok ? response.json() : null; })
            .then(function (result) {
                if (result && result.display_name && addressInput) {
                    addressInput.value = result.display_name;
                }
            })
            .catch(function () {
                showMessage(messageElement, 'Coordinates were selected. You can enter an approximate address manually.', 'text-warning');
            });
    }

    function initializePicker(container) {
        if (!window.L) {
            showMessage(container.querySelector('[data-location-message]'), 'Unable to load the map. Please try again.', 'text-danger');
            return;
        }

        var latitudeInput = container.querySelector('[data-location-latitude]');
        var longitudeInput = container.querySelector('[data-location-longitude]');
        var addressInput = container.querySelector('[data-location-address]');
        var searchInput = container.querySelector('[data-location-search]');
        var messageElement = container.querySelector('[data-location-message]');
        var searchButton = container.querySelector('[data-location-search-button]');
        var currentButton = container.querySelector('[data-location-current-button]');
        var defaultLatitude = coordinateValue(container.dataset.defaultLatitude) || 20.5937;
        var defaultLongitude = coordinateValue(container.dataset.defaultLongitude) || 78.9629;
        var savedLatitude = coordinateValue(latitudeInput && latitudeInput.value);
        var savedLongitude = coordinateValue(longitudeInput && longitudeInput.value);
        var hasSavedLocation = savedLatitude !== null && savedLongitude !== null;
        var map = L.map(container.querySelector('[data-location-map]'), { scrollWheelZoom: false }).setView(
            hasSavedLocation ? [savedLatitude, savedLongitude] : [defaultLatitude, defaultLongitude],
            hasSavedLocation ? 14 : 5
        );
        var marker = null;
        var lastRequestTime = 0;

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        function setLocation(latitude, longitude, pan) {
            latitude = coordinateValue(latitude);
            longitude = coordinateValue(longitude);
            if (latitude === null || longitude === null || latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
                showMessage(messageElement, 'Please select valid coordinates on the map.', 'text-danger');
                return;
            }

            latitudeInput.value = latitude.toFixed(6);
            longitudeInput.value = longitude.toFixed(6);
            if (!marker) {
                marker = L.marker([latitude, longitude], { draggable: true }).addTo(map);
                marker.on('dragend', function () {
                    var point = marker.getLatLng();
                    setLocation(point.lat, point.lng, false);
                    reverseGeocode(point.lat, point.lng, addressInput, messageElement);
                });
            } else {
                marker.setLatLng([latitude, longitude]);
            }
            if (pan) map.setView([latitude, longitude], 14);
            showMessage(messageElement, 'Location selected. Confirm by saving the form.', 'text-success');
        }

        if (hasSavedLocation) setLocation(savedLatitude, savedLongitude, false);

        map.on('click', function (event) {
            setLocation(event.latlng.lat, event.latlng.lng, false);
            reverseGeocode(event.latlng.lat, event.latlng.lng, addressInput, messageElement);
        });

        function searchLocation() {
            var query = searchInput ? searchInput.value.trim() : '';
            if (query.length < 2) {
                showMessage(messageElement, 'Enter a location name, then select Search.', 'text-warning');
                return;
            }
            if (Date.now() - lastRequestTime < 1100) {
                showMessage(messageElement, 'Please wait a moment before searching again.', 'text-warning');
                return;
            }
            lastRequestTime = Date.now();
            showMessage(messageElement, 'Searching location...', '');
            fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(query), { headers: { Accept: 'application/json' } })
                .then(function (response) { return response.ok ? response.json() : []; })
                .then(function (results) {
                    if (!results.length) {
                        showMessage(messageElement, 'Location not found. Please select a location directly on the map.', 'text-warning');
                        return;
                    }
                    var result = results[0];
                    setLocation(result.lat, result.lon, true);
                    if (addressInput) addressInput.value = result.display_name || query;
                })
                .catch(function () {
                    showMessage(messageElement, 'Location search failed. Please select a location directly on the map.', 'text-danger');
                });
        }

        if (searchButton) searchButton.addEventListener('click', searchLocation);
        if (searchInput) searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchLocation();
            }
        });
        if (currentButton) currentButton.addEventListener('click', function () {
            if (!navigator.geolocation) {
                showMessage(messageElement, 'Your browser does not support current-location selection. Please select on the map.', 'text-warning');
                return;
            }
            showMessage(messageElement, 'Waiting for location permission...', '');
            navigator.geolocation.getCurrentPosition(function (position) {
                setLocation(position.coords.latitude, position.coords.longitude, true);
                reverseGeocode(position.coords.latitude, position.coords.longitude, addressInput, messageElement);
            }, function () {
                showMessage(messageElement, 'Location permission was denied. You can manually select your location on the map.', 'text-warning');
            }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 300000 });
        });

        window.setTimeout(function () { map.invalidateSize(); }, 150);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-location-picker]').forEach(initializePicker);
        document.querySelectorAll('[data-item-location-map]').forEach(function (mapElement) {
            if (!window.L) return;
            var latitude = coordinateValue(mapElement.dataset.latitude);
            var longitude = coordinateValue(mapElement.dataset.longitude);
            if (latitude === null || longitude === null) return;
            var itemMap = L.map(mapElement, { scrollWheelZoom: false, dragging: false, zoomControl: true }).setView([latitude, longitude], 13);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(itemMap);
            L.marker([latitude, longitude]).addTo(itemMap).bindPopup('Approximate pickup area');
            window.setTimeout(function () { itemMap.invalidateSize(); }, 150);
        });
    });
}());
