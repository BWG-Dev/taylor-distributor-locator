/**
 * Taylor Distributor Locator - Frontend JavaScript
 */
(function () {
    'use strict';

    // Configuration and state
    let config = {};
    let map = null;
    let markers = []; // Array of markers (Google or Leaflet)
    let activeMarker = null;
    let currentResults = [];
    let currentPage = 1;
    let mapsReady = false; // Only used for Google Maps callback
    let mapProvider = 'google'; // 'google' or 'openstreetmap'

    // Autocomplete state
    let debounceTimer = null;

    /**
     * Initialize on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function () {
        const configEl = document.getElementById('tdl-config');
        if (!configEl) return;

        config = JSON.parse(configEl.textContent);
        mapProvider = config.mapProvider || 'google';

        initSearch();

        if (mapProvider === 'google') {
            // Check if map is already ready (race condition fix)
            if (mapsReady) {
                initMap();
            }
        } else if (mapProvider === 'openstreetmap') {
            // Leaflet should be ready if enqueued correctly
            initMap();
        }
    });

    /**
     * Global callback for Google Maps API
     */
    window.tdlInitMap = function () {
        mapsReady = true;
        if (mapProvider === 'google') {
            initMap();
        }
    };

    // Search state
    let searchComponents = {
        city: '',
        state: '',
        zip: '',
        country: ''
    };

    /**
     * Initialize search functionality
     */
    function initSearch() {
        const input = document.getElementById('tdl-search-input');
        const btn = document.getElementById('tdl-search-btn');

        if (!input || !btn) return;

        // Initialize Autocomplete
        if (mapProvider === 'google') {
            initGoogleAutocomplete(input);
        } else {
            initNominatimAutocomplete(input);
        }

        btn.addEventListener('click', function () {
            // Close autocomplete results if open
            closeAutocomplete();
            performSearch();
        });

        input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                closeAutocomplete();
                performSearch();
            }
        });

        // If default country is set, search on load
        if (config.defaultCountry) {
            input.value = config.defaultCountry;
            performSearch();
        }
    }

    /**
     * Initialize Google Autocomplete
     */
    function initGoogleAutocomplete(input) {
        if (typeof google === 'undefined' || !google.maps || !google.maps.places || !google.maps.places.Autocomplete) {
            return;
        }

        const autocomplete = new google.maps.places.Autocomplete(input, {
            fields: ['address_components', 'geometry', 'name'],
            types: ['(cities)'],
        });

        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();

            // Reset components
            searchComponents = { city: '', state: '', zip: '', country: '' };

            if (place.address_components) {
                place.address_components.forEach(function (component) {
                    const types = component.types;
                    if (types.includes('locality')) {
                        searchComponents.city = component.long_name;
                    }
                    if (types.includes('administrative_area_level_1')) {
                        searchComponents.state = component.short_name;
                    }
                    if (types.includes('postal_code')) {
                        searchComponents.zip = component.long_name;
                    }
                    if (types.includes('country')) {
                        searchComponents.country = component.short_name;
                    }
                });
            }

            // Auto-search on selection
            performSearch();
        });

        // Clear structured data on manual input change to fallback to text search
        input.addEventListener('input', function () {
            // We don't clear immediately to allow minor edits
        });
    }

    /**
     * Initialize Nominatim (OSM) Autocomplete
     */
    function initNominatimAutocomplete(input) {
        // Create results container
        let resultsContainer = document.querySelector('.tdl-autocomplete-results');
        if (!resultsContainer) {
            resultsContainer = document.createElement('div');
            resultsContainer.className = 'tdl-autocomplete-results';
            input.parentNode.appendChild(resultsContainer);
        }

        input.addEventListener('input', function () {
            const query = this.value.trim();

            // Clear structured data on manual input change to fallback to text search
            searchComponents = { city: '', state: '', zip: '', country: '' };

            if (query.length < 3) {
                resultsContainer.style.display = 'none';
                return;
            }

            // Skip autocomplete for numeric input (zipcodes)
            // Only offer suggestions when user starts typing a city or state name
            if (/^\d/.test(query)) {
                resultsContainer.style.display = 'none';
                return;
            }

            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                fetchNominatimSuggestions(query, resultsContainer, input);
            }, 300);
        });

        // Hide results on outside click
        document.addEventListener('click', function (e) {
            if (e.target !== input && e.target !== resultsContainer) {
                resultsContainer.style.display = 'none';
            }
        });
    }

    /**
     * Fetch suggestions from Nominatim
     */
    function fetchNominatimSuggestions(query, container, input) {
        const url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&addressdetails=1&limit=5';

        fetch(url, {
            headers: {
                'Accept': 'application/json'
            }
        })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                renderNominatimSuggestions(data, container, input);
            })
            .catch(function (err) {
                console.error('Autocomplete error:', err);
            });
    }

    /**
     * Render Nominatim suggestions
     */
    function renderNominatimSuggestions(data, container, input) {
        if (!data || data.length === 0) {
            container.style.display = 'none';
            return;
        }

        container.innerHTML = '';
        container.style.display = 'block';

        data.forEach(function (item) {
            const addr = item.address || {};
            const city = addr.city || addr.town || addr.village || addr.suburb || addr.municipality || '';
            const state = addr.state || '';
            const country = addr.country || '';

            // Reconstruct display name without county
            let displayParts = [];
            if (city) displayParts.push(city);
            if (state) displayParts.push(state);
            if (country) displayParts.push(country);

            const cleanDisplay = displayParts.join(', ') || item.display_name;

            const div = document.createElement('div');
            div.className = 'tdl-autocomplete-item';
            div.textContent = cleanDisplay;

            div.addEventListener('click', function () {
                input.value = cleanDisplay;
                container.style.display = 'none';

                // Parse address details
                searchComponents = { city: '', state: '', zip: '', country: '' };

                if (item.address) {
                    searchComponents.city = city;
                    searchComponents.state = state;
                    if (item.address.postcode) {
                        searchComponents.zip = item.address.postcode;
                    }
                    if (item.address.country_code) {
                        searchComponents.country = item.address.country_code.toUpperCase();
                    }
                }

                performSearch();
            });

            container.appendChild(div);
        });
    }

    /**
     * Close autocomplete
     */
    function closeAutocomplete() {
        const container = document.querySelector('.tdl-autocomplete-results');
        if (container) {
            container.style.display = 'none';
        }
    }

    /**
     * Initialize Map (Dispatcher)
     */
    function initMap() {
        if (!config.showMap) return;

        const mapEl = document.getElementById('tdl-map');
        if (!mapEl) return;

        if (mapProvider === 'google') {
            initGoogleMap(mapEl);
        } else {
            initLeafletMap(mapEl);
        }
    }

    /**
     * Initialize Google Map
     */
    function initGoogleMap(mapEl) {
        if (typeof google === 'undefined' || typeof google.maps === 'undefined') return;

        map = new google.maps.Map(mapEl, {
            center: { lat: config.centerLat, lng: config.centerLng },
            zoom: config.zoom,
            mapId: config.mapId || 'DEMO_MAP_ID',
            mapTypeControl: false,
            streetViewControl: false,
        });

        // Re-init search to attach Autocomplete if it wasn't ready before
        initSearch();
    }

    /**
     * Initialize Leaflet Map
     */
    function initLeafletMap(mapEl) {
        if (typeof L === 'undefined') {
            console.error('Leaflet not loaded');
            return;
        }

        map = L.map(mapEl).setView([config.centerLat, config.centerLng], config.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
    }

    /**
     * Perform search
     */
    function performSearch(queryOverride) {
        let query = '';

        if (typeof queryOverride === 'string') {
            query = queryOverride;
        } else {
            const input = document.getElementById('tdl-search-input');
            if (input) query = input.value.trim();
        }

        if (!query) return;

        showStatus(config.i18n.searching, 'loading');
        currentPage = 1;

        // Build query string
        let url = config.restUrl + 'search?q=' + encodeURIComponent(query) + '&page=' + currentPage + '&per_page=' + config.resultsPerPage;

        // Add structured data if available
        if (searchComponents.city || searchComponents.state || searchComponents.zip || searchComponents.country) {
            if (searchComponents.city) url += '&city=' + encodeURIComponent(searchComponents.city);
            if (searchComponents.state) url += '&state=' + encodeURIComponent(searchComponents.state);
            if (searchComponents.zip) url += '&zip=' + encodeURIComponent(searchComponents.zip);
            if (searchComponents.country) url += '&country=' + encodeURIComponent(searchComponents.country);
        }

        fetch(url)
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    currentResults = data.results;
                    showStatus(config.i18n.distributorsFound.replace('%d', data.total), 'success');
                    renderResults(data);
                    updateMap(data.results);
                } else {
                    showStatus(data.message || config.i18n.noResults, 'error');
                    clearResults();
                }
            })
            .catch(function (error) {
                console.error('Search error:', error);
                showStatus(config.i18n.error, 'error');
            });
    }

    /**
     * Show status message
     */
    function showStatus(message, type) {
        const statusEl = document.getElementById('tdl-status');
        if (!statusEl) return;

        statusEl.textContent = message;
        statusEl.className = 'tdl-status tdl-status-' + type;
    }

    /**
     * Render search results
     */
    function renderResults(data) {
        const container = document.getElementById('tdl-results');
        if (!container) return;

        if (!data.results || data.results.length === 0) {
            container.innerHTML = '<p class="tdl-no-results">' + config.i18n.noResults + '</p>';
            return;
        }

        let html = '';
        data.results.forEach(function (distributor, index) {
            html += renderDistributorCard(distributor, index);
        });

        container.innerHTML = html;

        // Add click handlers
        container.querySelectorAll('.tdl-result-card').forEach(function (card) {
            card.addEventListener('click', function (e) {
                // Don't trigger if clicking accordion toggle
                if (e.target.closest('.tdl-accordion-toggle')) return;

                const idx = parseInt(this.dataset.index, 10);
                highlightCard(idx);
                centerMapOnDistributor(idx);
            });
        });

        // Add accordion handlers
        container.querySelectorAll('.tdl-accordion-toggle').forEach(function (toggle) {
            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                const content = this.nextElementSibling;
                const isExpanded = this.getAttribute('aria-expanded') === 'true';

                this.setAttribute('aria-expanded', !isExpanded);
                content.classList.toggle('active');

                const icon = this.querySelector('.tdl-toggle-icon');
                if (icon) {
                    icon.textContent = isExpanded ? '▼' : '▲';
                }
            });
        });

        // Render pagination
        renderPagination(data);
    }

    /**
     * Render a single distributor card
     */
    function renderDistributorCard(distributor, index) {
        const primaryLocation = distributor.locations.find(function (l) { return l.is_primary; }) || distributor.locations[0];
        const additionalCount = distributor.locations.length - 1;

        let primaryLocationHtml = '';
        if (primaryLocation) {
            const address = [
                primaryLocation.address,
                primaryLocation.address_2,
                primaryLocation.address_3,
                primaryLocation.city,
                primaryLocation.state,
                primaryLocation.zip
            ].filter(Boolean).join(', ');

            primaryLocationHtml = '<div class="tdl-location">';
            if (primaryLocation.name) {
                primaryLocationHtml += '<strong>' + escapeHtml(primaryLocation.name) + '</strong><br>';
            }
            primaryLocationHtml += escapeHtml(address);
            if (primaryLocation.hours) {
                primaryLocationHtml += '<div class="tdl-hours"><strong>' + config.i18n.hours + ':</strong> ' + escapeHtml(primaryLocation.hours).replace(/\n/g, '<br>') + '</div>';
            }
            primaryLocationHtml += '</div>';
        }

        // Contact Info (Phone, Website, Emails)
        let contactHtml = '';
        if (distributor.phone) {
            contactHtml += '<div class="tdl-contact-item"><a href="tel:' + escapeHtml(distributor.phone) + '" class="tdl-phone">' + escapeHtml(distributor.phone) + '</a></div>';
        }
        if (distributor.website) {
            contactHtml += '<div class="tdl-contact-item"><a href="' + escapeHtml(distributor.website) + '" target="_blank" rel="noopener" class="tdl-website">' + config.i18n.visitWebsite + '</a></div>';
        }

        if (distributor.emails) {
            const emailLabels = {
                'main': config.i18n.emailMain || 'Main',
                'sales': config.i18n.emailSales || 'Sales',
                'parts': config.i18n.emailParts || 'Parts',
                'service': config.i18n.emailService || 'Service',
                'installations': config.i18n.emailInstalls || 'Installations'
            };

            Object.keys(emailLabels).forEach(function (key) {
                if (distributor.emails[key]) {
                    contactHtml += '<div class="tdl-contact-item tdl-email">';
                    contactHtml += '<span class="tdl-label">' + emailLabels[key] + ': </span>';
                    contactHtml += '<a href="mailto:' + escapeHtml(distributor.emails[key]) + '">' + escapeHtml(distributor.emails[key]) + '</a>';
                    contactHtml += '</div>';
                }
            });

            if (distributor.emails.other && Array.isArray(distributor.emails.other)) {
                distributor.emails.other.forEach(function (item) {
                    if (item.email && item.label) {
                        contactHtml += '<div class="tdl-contact-item tdl-email">';
                        contactHtml += '<span class="tdl-label">' + escapeHtml(item.label) + ': </span>';
                        contactHtml += '<a href="mailto:' + escapeHtml(item.email) + '">' + escapeHtml(item.email) + '</a>';
                        contactHtml += '</div>';
                    }
                });
            }
        }

        // Service Area Notes
        let serviceAreaHtml = '';
        if (distributor.service_area_notes) {
            serviceAreaHtml = '<div class="tdl-service-area">';
            serviceAreaHtml += '<strong>' + config.i18n.serviceArea + ':</strong> ';
            serviceAreaHtml += escapeHtml(distributor.service_area_notes);
            serviceAreaHtml += '</div>';
        }

        // Additional Locations (Accordion)
        let additionalLocationsHtml = '';
        if (additionalCount > 0) {
            additionalLocationsHtml += '<div class="tdl-accordion-wrap">';
            additionalLocationsHtml += '<button class="tdl-accordion-toggle" aria-expanded="false">' +
                config.i18n.additionalLocations.replace('%d', additionalCount) +
                ' <span class="tdl-toggle-icon">▼</span></button>';
            additionalLocationsHtml += '<div class="tdl-accordion-content">';

            distributor.locations.forEach(function (loc) {
                if (loc.is_primary) return;

                const locAddress = [
                    loc.address,
                    loc.address_2,
                    loc.address_3,
                    loc.city,
                    loc.state,
                    loc.zip
                ].filter(Boolean).join(', ');

                additionalLocationsHtml += '<div class="tdl-additional-location">';
                if (loc.name) {
                    additionalLocationsHtml += '<strong>' + escapeHtml(loc.name) + '</strong><br>';
                }
                additionalLocationsHtml += escapeHtml(locAddress);
                if (loc.hours) {
                    additionalLocationsHtml += '<div class="tdl-hours"><strong>' + config.i18n.hours + ':</strong> ' + escapeHtml(loc.hours).replace(/\n/g, '<br>') + '</div>';
                }
                additionalLocationsHtml += '</div>';
            });

            additionalLocationsHtml += '</div>';
            additionalLocationsHtml += '</div>';
        }

        return '<div class="tdl-result-card" data-index="' + index + '" data-id="' + distributor.id + '">' +
            '<div class="tdl-result-pin-number">' + (index + 1) + '</div>' +
            '<div class="tdl-result-content">' +
            '<h3 class="tdl-distributor-name">' + escapeHtml(distributor.name) + '</h3>' +
            primaryLocationHtml +
            '<div class="tdl-contact">' + contactHtml + '</div>' +
            serviceAreaHtml +
            additionalLocationsHtml +
            '</div>' +
            '</div>';
    }

    /**
     * Render pagination
     */
    function renderPagination(data) {
        const container = document.getElementById('tdl-pagination');
        if (!container) return;

        const totalPages = Math.ceil(data.total / config.resultsPerPage);
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';
        for (let i = 1; i <= totalPages; i++) {
            const activeClass = i === data.page ? ' active' : '';
            html += '<button class="tdl-page-btn' + activeClass + '" data-page="' + i + '">' + i + '</button>';
        }

        container.innerHTML = html;

        container.querySelectorAll('.tdl-page-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                currentPage = parseInt(this.dataset.page, 10);
                performSearch();
            });
        });
    }

    /**
     * Clear results
     */
    function clearResults() {
        const container = document.getElementById('tdl-results');
        if (container) container.innerHTML = '';

        const pagination = document.getElementById('tdl-pagination');
        if (pagination) pagination.innerHTML = '';

        clearMarkers();
    }

    /**
     * Update map markers (Dispatcher)
     */
    function updateMap(distributors) {
        if (!map) return;

        clearMarkers();

        if (mapProvider === 'google') {
            updateGoogleMarkers(distributors);
        } else {
            updateLeafletMarkers(distributors);
        }
    }

    /**
     * Update Google Markers
     */
    function updateGoogleMarkers(distributors) {
        const bounds = new google.maps.LatLngBounds();
        let hasValidCoords = false;
        let validLocationCount = 0;

        distributors.forEach(function (distributor, index) {
            distributor.locations.forEach(function (location) {
                if (location.lat && location.lng) {
                    const position = { lat: location.lat, lng: location.lng };

                    const pin = new google.maps.marker.PinElement({
                        glyphText: String(index + 1),
                        glyphColor: '#ffffff',
                        background: '#ea4335',
                        borderColor: '#c5221f',
                    });

                    const marker = new google.maps.marker.AdvancedMarkerElement({
                        position: position,
                        map: map,
                        title: distributor.name + (location.name ? ' - ' + location.name : ''),
                        content: pin,
                    });

                    // Info window
                    const infoContent = createInfoWindowContent(distributor, location);
                    const infoWindow = new google.maps.InfoWindow({
                        content: infoContent,
                    });

                    marker.addListener('gmp-click', function () {
                        closeAllInfoWindows();
                        infoWindow.open(map, marker);
                        highlightCard(index);
                    });

                    marker.infoWindow = infoWindow;
                    marker.distributorIndex = index;
                    markers.push(marker);

                    bounds.extend(position);
                    hasValidCoords = true;
                    validLocationCount++;
                }
            });
        });

        if (hasValidCoords) {
            if (validLocationCount > 1) {
                map.fitBounds(bounds);
            } else {
                map.setCenter(bounds.getCenter());
                map.setZoom(12);
            }
        }
    }

    /**
     * Update Leaflet Markers
     */
    function updateLeafletMarkers(distributors) {
        const bounds = L.latLngBounds();
        let hasValidCoords = false;
        let validLocationCount = 0;

        distributors.forEach(function (distributor, index) {
            distributor.locations.forEach(function (location) {
                if (location.lat && location.lng) {
                    const latLng = [location.lat, location.lng];

                    // Custom icon with number
                    const myIcon = L.divIcon({
                        className: 'tdl-leaflet-marker',
                        html: '<div class="tdl-marker-pin">' + (index + 1) + '</div>',
                        iconSize: [30, 30],
                        iconAnchor: [15, 15],
                        popupAnchor: [0, -15]
                    });

                    const marker = L.marker(latLng, { icon: myIcon }).addTo(map);

                    const infoContent = createInfoWindowContent(distributor, location);
                    marker.bindPopup(infoContent);

                    marker.on('click', function () {
                        highlightCard(index);
                    });

                    marker.distributorIndex = index;
                    markers.push(marker);

                    bounds.extend(latLng);
                    hasValidCoords = true;
                    validLocationCount++;
                }
            });
        });

        if (hasValidCoords) {
            if (validLocationCount > 1) {
                map.fitBounds(bounds, { padding: [50, 50] });
            } else {
                map.setView(bounds.getCenter(), 12);
            }
        }
    }

    /**
     * Create info window content (Shared)
     */
    function createInfoWindowContent(distributor, location) {
        const address = [
            location.address,
            location.address_2,
            location.address_3,
            location.city,
            location.state,
            location.zip
        ].filter(Boolean).join(', ');

        let html = '<div class="tdl-info-window">';
        html += '<h4>' + escapeHtml(distributor.name) + '</h4>';
        if (location.name) {
            html += '<p><strong>' + escapeHtml(location.name) + '</strong></p>';
        }
        html += '<p>' + escapeHtml(address) + '</p>';
        if (location.hours) {
            html += '<div class="tdl-info-hours"><strong>' + config.i18n.hours + ':</strong> ' + escapeHtml(location.hours).replace(/\n/g, '<br>') + '</div>';
        }
        if (distributor.phone) {
            html += '<p><a href="tel:' + escapeHtml(distributor.phone) + '">' + escapeHtml(distributor.phone) + '</a></p>';
        }
        if (location.lat && location.lng) {
            const directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + location.lat + ',' + location.lng;
            html += '<p><a href="' + directionsUrl + '" target="_blank" rel="noopener">' + config.i18n.getDirections + '</a></p>';
        }
        html += '</div>';

        return html;
    }

    /**
     * Clear all markers
     */
    function clearMarkers() {
        if (mapProvider === 'google') {
            markers.forEach(function (marker) {
                marker.map = null;
            });
        } else {
            markers.forEach(function (marker) {
                map.removeLayer(marker);
            });
        }
        markers = [];
    }

    /**
     * Close all info windows/popups
     */
    function closeAllInfoWindows() {
        if (mapProvider === 'google') {
            markers.forEach(function (marker) {
                if (marker.infoWindow) {
                    marker.infoWindow.close();
                }
            });
        } else {
            map.closePopup();
        }
    }

    /**
     * Highlight a result card
     */
    function highlightCard(index) {
        document.querySelectorAll('.tdl-result-card').forEach(function (card) {
            card.classList.remove('active');
        });

        const card = document.querySelector('.tdl-result-card[data-index="' + index + '"]');
        if (card) {
            card.classList.add('active');
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    /**
     * Center map on distributor
     */
    function centerMapOnDistributor(index) {
        if (!map || !currentResults[index]) return;

        const distributor = currentResults[index];
        const location = distributor.locations.find(function (l) { return l.is_primary; }) || distributor.locations[0];

        if (location && location.lat && location.lng) {
            if (mapProvider === 'google') {
                map.panTo({ lat: location.lat, lng: location.lng });
                map.setZoom(14);

                const marker = markers.find(function (m) { return m.distributorIndex === index; });
                if (marker && marker.infoWindow) {
                    closeAllInfoWindows();
                    marker.infoWindow.open(map, marker);
                }
            } else {
                map.setView([location.lat, location.lng], 14);

                const marker = markers.find(function (m) { return m.distributorIndex === index; });
                if (marker) {
                    marker.openPopup();
                }
            }
        }
    }

    /**
     * Escape HTML entities
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
