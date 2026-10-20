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
    let clusterGroup = null; // Leaflet.markercluster group
    let googleClusterer = null; // Google Maps MarkerClusterer instance
    let isDefaultView = true; // true on initial page load; false once a search is performed
    let mapSearchToken = 0;  // incremented on each new search to discard stale async responses

    // Guards the Leaflet map background-click handler from the browser's synthetic
    // click (~300-500ms after touchend) that follows a marker tap on mobile.
    let leafletMarkerTapped = false;
    let leafletTapTimer = null;

    // Active search tab mode — set by tab clicks, read by performSearch().
    let activeSearchMode = 'zip';
    // Lazy-load flags for typeahead data — populated once on first tab activation.
    let statesLoaded = false;
    let countriesLoaded = false;
    // Typeahead data arrays.
    let stateItems = [];  // { code, name, group }
    let countryItems = []; // { code, name }

    /**
     * Initialize on DOM ready
     */
    document.addEventListener('DOMContentLoaded', function () {
        const configEl = document.getElementById('tdl-config');
        if (!configEl) return;

        config = JSON.parse(configEl.textContent);
        mapProvider = config.mapProvider || 'google';

        initSearchTabs();
        initMobileToggle();
        initQuoteModal();
        loadDefault();

        // Wire "Request Quote" buttons to the quote modal.
        // tdl:quote-requested is still dispatched so M8/M9 can hook into it downstream.
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.tdl-request-quote');
            if (!btn) return;
            e.stopPropagation();
            const distributorId   = parseInt(btn.dataset.distributorId, 10);
            const distributorName = btn.dataset.distributorName || '';
            document.dispatchEvent(new CustomEvent('tdl:quote-requested', {
                bubbles: false,
                detail: { distributorId: distributorId }
            }));
            openQuoteModal(distributorId, distributorName);
        });

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

    // Last-executed search state — used by fetchPage() to re-run the same
    // search on a different page without re-reading the input.
    let savedQuery = '';
    let savedComponents = {};

    /**
     * Wire up the four search-mode tabs and their inputs/dropdowns.
     * Replaces the previous single text input + autocomplete approach.
     */
    function initSearchTabs() {
        const btn = document.getElementById('tdl-search-btn');
        if (!btn) return;

        // Tab clicks
        document.querySelectorAll('.tdl-search-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                switchSearchTab(tab.dataset.mode);
            });
        });

        // Search button
        btn.addEventListener('click', performSearch);

        // Enter key on text inputs (zip, city, typeahead inputs)
        ['tdl-zip-input', 'tdl-city-input', 'tdl-state-input', 'tdl-country-input'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    // Only trigger search if the typeahead dropdown is closed
                    var dropdown = el.closest('.tdl-typeahead-wrap');
                    var dd = dropdown ? dropdown.querySelector('.tdl-typeahead-dropdown') : null;
                    if (!dd || dd.hidden) {
                        performSearch();
                    }
                }
            });
        });

        // Initialize typeaheads
        initTypeahead('tdl-state-input', 'tdl-state-dropdown', 'tdl-state-value', loadStatesData, 2);
        initTypeahead('tdl-country-input', 'tdl-country-dropdown', 'tdl-country-value', loadCountriesData, 2);
    }

    /**
     * Activate the given search tab, lazy-loading its dropdown if needed.
     */
    function switchSearchTab(mode) {
        activeSearchMode = mode;

        document.querySelectorAll('.tdl-search-tab').forEach(function (tab) {
            const active = tab.dataset.mode === mode;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        document.querySelectorAll('.tdl-tab-panel').forEach(function (panel) {
            const active = panel.id === 'tdl-panel-' + mode;
            panel.hidden = !active;
            panel.classList.toggle('active', active);
        });

        // Clear all inputs, hidden values, and close typeahead dropdowns
        // so stale values from a previous search don't carry over.
        document.querySelectorAll('.tdl-tab-panel input').forEach(function (el) { el.value = ''; });
        document.querySelectorAll('.tdl-typeahead-dropdown').forEach(function (el) { el.hidden = true; });

        if (mode === 'state' && !statesLoaded) loadStatesData();
        if (mode === 'country' && !countriesLoaded) loadCountriesData();

        var focusEl = document.querySelector('#tdl-panel-' + mode + ' input:not([type="hidden"])');
        if (focusEl) focusEl.focus();
    }

    /**
     * Fetch state/province data from /states for US, CA, MX in parallel.
     * Returns a Promise that resolves once stateItems[] is populated.
     */
    function loadStatesData() {
        if (statesLoaded) return Promise.resolve();

        var base = config.restUrl + 'states?country=';
        return Promise.all([
            fetch(base + 'US').then(function (r) { return r.json(); }),
            fetch(base + 'CA').then(function (r) { return r.json(); }),
            fetch(base + 'MX').then(function (r) { return r.json(); }),
        ]).then(function (results) {
            var groups = [
                { label: 'United States', states: results[0].states || [] },
                { label: 'Canada',        states: results[1].states || [] },
                { label: 'Mexico',        states: results[2].states || [] },
            ];
            stateItems = [];
            groups.forEach(function (g) {
                g.states.forEach(function (s) {
                    stateItems.push({ code: s.code, name: s.name, group: g.label });
                });
            });
            statesLoaded = true;
        }).catch(function () {
            stateItems = [];
        });
    }

    /**
     * Fetch country data from /countries endpoint.
     * Returns a Promise that resolves once countryItems[] is populated.
     */
    function loadCountriesData() {
        if (countriesLoaded) return Promise.resolve();

        return fetch(config.restUrl + 'countries')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                countryItems = (data.countries || []).map(function (c) {
                    return { code: c.code, name: c.name };
                });
                countriesLoaded = true;
            }).catch(function () {
                countryItems = [];
            });
    }

    /**
     * Generic typeahead: wires an input + hidden value + dropdown panel.
     * Shows filtered suggestions after minChars characters are typed.
     */
    function initTypeahead(inputId, dropdownId, hiddenId, dataLoader, minChars) {
        var input    = document.getElementById(inputId);
        var dropdown = document.getElementById(dropdownId);
        var hidden   = document.getElementById(hiddenId);
        if (!input || !dropdown || !hidden) return;

        var activeIdx = -1;
        var lastMatches = [];

        function getItems() {
            if (inputId.indexOf('state') !== -1) return stateItems;
            return countryItems;
        }

        function filter(q) {
            var lq = q.toLowerCase();
            return getItems().filter(function (item) {
                return item.name.toLowerCase().indexOf(lq) === 0 ||
                       item.code.toLowerCase().indexOf(lq) === 0;
            }).slice(0, 12);
        }

        function render(matches) {
            lastMatches = matches;
            if (!matches.length) {
                dropdown.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                return;
            }
            var html = '';
            matches.forEach(function (item, i) {
                var cls = i === activeIdx ? 'tdl-ta-item tdl-ta-active' : 'tdl-ta-item';
                var groupHtml = item.group
                    ? ' <span class="tdl-ta-group">' + escapeHtml(item.group) + '</span>'
                    : '';
                html += '<div class="' + cls + '" role="option" data-code="' +
                    escapeAttr(item.code) + '" data-index="' + i + '">' +
                    escapeHtml(item.name) +
                    ' <span class="tdl-ta-code">' + escapeHtml(item.code) + '</span>' +
                    groupHtml + '</div>';
            });
            dropdown.innerHTML = html;
            dropdown.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }

        function selectItem(item) {
            input.value  = item.name;
            hidden.value = item.code;
            dropdown.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            activeIdx = -1;
        }

        input.addEventListener('focus', function () { dataLoader(); });

        input.addEventListener('input', function () {
            hidden.value = '';
            activeIdx = -1;
            var q = input.value.trim();
            if (q.length < minChars) {
                dropdown.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                return;
            }
            dataLoader().then(function () { render(filter(q)); });
        });

        input.addEventListener('keydown', function (e) {
            if (dropdown.hidden || !lastMatches.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIdx = Math.min(activeIdx + 1, lastMatches.length - 1);
                render(lastMatches);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIdx = Math.max(activeIdx - 1, 0);
                render(lastMatches);
            } else if (e.key === 'Enter') {
                if (activeIdx >= 0 && lastMatches[activeIdx]) {
                    e.preventDefault();
                    selectItem(lastMatches[activeIdx]);
                }
            } else if (e.key === 'Escape') {
                dropdown.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                activeIdx = -1;
            }
        });

        dropdown.addEventListener('click', function (e) {
            var el = e.target.closest('.tdl-ta-item');
            if (!el) return;
            var code = el.dataset.code;
            var match = getItems().find(function (i) { return i.code === code; });
            if (match) selectItem(match);
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('#' + inputId) && !e.target.closest('#' + dropdownId)) {
                dropdown.hidden = true;
                input.setAttribute('aria-expanded', 'false');
                activeIdx = -1;
            }
        });
    }

    /**
     * Pan the Leaflet map so the given LatLng lands centered horizontally and
     * near the bottom of the map viewport (≈85% down). This gives the popup —
     * which Leaflet anchors above the marker — the most visible vertical space
     * without requiring the user to manually drag the map on mobile.
     * Only called on mobile (≤960px) where the map height is constrained.
     */
    function panMarkerToBottom(latlng) {
        if (!map) return;
        const size     = map.getSize();
        const markerPx = map.latLngToContainerPoint(latlng);
        const targetPx = L.point(size.x / 2, size.y * 0.88);
        map.panBy(markerPx.subtract(targetPx), { animate: true, duration: 0.25 });
    }

    /**
     * Mark that a Leaflet marker was just tapped so the map background-click
     * handler ignores the browser's synthetic click that follows on mobile.
     */
    function flagLeafletMarkerTap() {
        leafletMarkerTapped = true;
        clearTimeout(leafletTapTimer);
        leafletTapTimer = setTimeout(function () {
            leafletMarkerTapped = false;
        }, 600);
    }

    /**
     * Mobile map/list toggle — button clicks and swipe gestures.
     * Only rendered in the DOM when both map and list are shown (split view).
     * On desktop the toggle buttons are hidden via CSS; the panel classes are
     * harmless because the desktop grid ignores them.
     */
    function initMobileToggle() {
        const btnMap  = document.getElementById('tdl-toggle-map');
        const btnList = document.getElementById('tdl-toggle-list');
        const content = document.querySelector('.tdl-content.tdl-split');

        if (!btnMap || !btnList || !content) return;

        // Start in map-visible state.
        content.classList.add('tdl-show-map');

        function showMap() {
            content.classList.replace('tdl-show-list', 'tdl-show-map') ||
                content.classList.add('tdl-show-map');
            btnMap.classList.add('active');
            btnList.classList.remove('active');
            btnMap.setAttribute('aria-pressed', 'true');
            btnList.setAttribute('aria-pressed', 'false');

            // Scroll the locator into view so the map is visible after switching
            // from a list that the user may have scrolled down to read.
            const locator = document.getElementById('tdl-locator');
            if (locator) {
                locator.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }

            // Leaflet must recalculate its size after the container is un-hidden.
            if (map && mapProvider === 'openstreetmap') {
                setTimeout(function () { map.invalidateSize(); }, 50);
            } else if (map && mapProvider === 'google' && typeof google !== 'undefined') {
                google.maps.event.trigger(map, 'resize');
            }
        }

        function showList() {
            content.classList.replace('tdl-show-map', 'tdl-show-list') ||
                content.classList.add('tdl-show-list');
            btnList.classList.add('active');
            btnMap.classList.remove('active');
            btnList.setAttribute('aria-pressed', 'true');
            btnMap.setAttribute('aria-pressed', 'false');
        }

        btnMap.addEventListener('click', showMap);
        btnList.addEventListener('click', showList);

        // Swipe gesture on the content area: horizontal swipe > 50px toggles panels.
        var swipeStartX = null;
        var swipeStartY = null;

        content.addEventListener('touchstart', function (e) {
            swipeStartX = e.touches[0].clientX;
            swipeStartY = e.touches[0].clientY;
        }, { passive: true });

        content.addEventListener('touchend', function (e) {
            if (swipeStartX === null) return;

            var dx = e.changedTouches[0].clientX - swipeStartX;
            var dy = e.changedTouches[0].clientY - swipeStartY;

            // Only act on primarily horizontal swipes (avoids interfering with scroll).
            if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy) * 1.5) {
                if (dx < 0) {
                    showList(); // swipe left → list
                } else {
                    showMap();  // swipe right → map
                }
            }

            swipeStartX = null;
            swipeStartY = null;
        }, { passive: true });
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

        // Initialize MarkerClusterer if the library loaded.
        if (typeof markerClusterer !== 'undefined' && markerClusterer.MarkerClusterer) {
            googleClusterer = new markerClusterer.MarkerClusterer({
                map: map,
                markers: [],
                renderer: {
                    render: function (cluster, stats) {
                        var count = cluster.count;
                        var size = count < 10 ? 32 : count < 100 ? 38 : 44;
                        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' + size + '" height="' + size + '" viewBox="0 0 ' + size + ' ' + size + '">' +
                            '<circle cx="' + (size / 2) + '" cy="' + (size / 2) + '" r="' + (size / 2 - 1) + '" fill="rgba(17,24,39,0.82)" stroke="rgba(255,255,255,0.7)" stroke-width="2"/>' +
                            '<text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" fill="#fff" font-size="12" font-weight="700" font-family="-apple-system,BlinkMacSystemFont,sans-serif">' + count + '</text>' +
                            '</svg>';
                        return new google.maps.marker.AdvancedMarkerElement({
                            position: cluster.position,
                            content: domFromString(svg),
                            zIndex: Number(google.maps.Marker.MAX_ZINDEX) + count,
                        });
                    }
                }
            });
        }

        if (isDefaultView) {
            fetchAllMarkers();
        }
    }

    /**
     * Parse an HTML/SVG string into a DOM element.
     */
    function domFromString(html) {
        var tpl = document.createElement('template');
        tpl.innerHTML = html.trim();
        return tpl.content.firstChild;
    }

    /**
     * Build a circle-dot marker element for Google Maps AdvancedMarkerElement,
     * visually identical to the Leaflet .tdl-marker-pin.
     */
    function createGooglePinElement() {
        var el = document.createElement('div');
        el.className = 'tdl-marker-pin';
        return el;
    }

    /**
     * Build a Leaflet marker cluster group with the shared custom icon options.
     * Extracted so both initLeafletMap() and clearMarkers() use identical config,
     * preventing the cluster style regression that occurred when clearMarkers()
     * called L.markerClusterGroup() without options.
     */
    function createClusterGroup() {
        return L.markerClusterGroup({
            iconCreateFunction: function (cluster) {
                const n = cluster.getChildCount();
                const size = n < 10 ? 32 : n < 100 ? 38 : 44;
                return L.divIcon({
                    html: '<div class="tdl-cluster"><span>' + n + '</span></div>',
                    className: 'tdl-cluster-icon',
                    iconSize: L.point(size, size),
                });
            },
            maxClusterRadius: 50,
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: true,
            zoomToBoundsOnClick: true,
        });
    }

    /**
     * Initialize Leaflet Map
     */
    function initLeafletMap(mapEl) {
        if (typeof L === 'undefined') {
            console.error('Leaflet not loaded');
            return;
        }

        // closePopupOnClick:false prevents Leaflet's map click handler from
        // immediately dismissing a popup that a marker tap just opened on mobile.
        map = L.map(mapEl, { closePopupOnClick: false }).setView([config.centerLat, config.centerLng], config.zoom);

        // CartoDB Positron — neutral, minimal tile layer that doesn't compete with the UI
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
            subdomains: 'abcd',
            maxZoom: 19,
        }).addTo(map);

        // Close popup on genuine background taps. The leafletMarkerTapped flag
        // blocks this handler for 600ms after a marker tap so the browser's
        // synthetic click (~300-500ms post-touchend) doesn't immediately close
        // the popup that the marker tap just opened.
        map.on('click', function () {
            if (leafletMarkerTapped) return;
            map.closePopup();
        });

        if (typeof L.markerClusterGroup === 'function') {
            clusterGroup = createClusterGroup();
            map.addLayer(clusterGroup);
        }

        if (isDefaultView) {
            fetchAllMarkers();
        }
    }

    /**
     * Start a new search from the active tab's input or select.
     * Sends explicit REST params (?zip=, ?state=, ?country=, ?city=) instead of
     * the free-text ?q= param, so the server routes directly to the correct
     * search method without heuristic detection.
     */
    function performSearch() {
        isDefaultView = false;

        // Reset — never send ?q= from tab-mode searches.
        savedQuery = '';
        savedComponents = { city: '', state: '', zip: '', country: '' };

        switch (activeSearchMode) {
            case 'zip': {
                const val = (document.getElementById('tdl-zip-input') || {}).value.trim();
                if (!/^\d{5}$/.test(val)) {
                    showStatus('Please enter a valid 5-digit ZIP code.', 'error');
                    return;
                }
                savedComponents.zip = val;
                break;
            }
            case 'state': {
                const val = (document.getElementById('tdl-state-value') || {}).value || '';
                if (!val) {
                    showStatus('Please select a state or province.', 'error');
                    return;
                }
                savedComponents.state = val;
                break;
            }
            case 'country': {
                const val = (document.getElementById('tdl-country-value') || {}).value || '';
                if (!val) {
                    showStatus('Please select a country.', 'error');
                    return;
                }
                savedComponents.country = val;
                break;
            }
            case 'city': {
                const val = (document.getElementById('tdl-city-input') || {}).value.trim();
                if (!val) {
                    showStatus('Please enter a city or region.', 'error');
                    return;
                }
                savedComponents.city = val;
                break;
            }
        }

        currentPage = 1;
        fetchPage(1);
        fetchMapForSearch();
    }

    /**
     * Load all distributors — used as the default page-load state.
     * The map is populated separately via fetchAllMarkers() once the map initialises.
     */
    function loadDefault() {
        isDefaultView = true;
        savedQuery = '';
        savedComponents = {};
        currentPage = 1;
        fetchPage(1);
    }

    /**
     * Execute the saved search for a given page number.
     * Pagination calls this directly so it never resets to page 1.
     */
    function fetchPage(page) {
        currentPage = page;
        showStatus(config.i18n.searching, 'loading');

        let url = config.restUrl + 'search?page=' + page + '&per_page=' + config.resultsPerPage;
        if (savedQuery) {
            url += '&q=' + encodeURIComponent(savedQuery);
        }
        if (savedComponents.city)    url += '&city='    + encodeURIComponent(savedComponents.city);
        if (savedComponents.state)   url += '&state='   + encodeURIComponent(savedComponents.state);
        if (savedComponents.zip)     url += '&zip='     + encodeURIComponent(savedComponents.zip);
        if (savedComponents.country) url += '&country=' + encodeURIComponent(savedComponents.country);

        fetch(url)
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data.success) {
                    currentResults = data.results;
                    showStatus(config.i18n.distributorsFound.replace('%d', data.total), 'success');
                    renderResults(data);
                    // Map is driven independently:
                    //   default view → fetchAllMarkers() called from map init
                    //   search view  → fetchMapForSearch() called from performSearch()
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
                if (e.target.closest('.tdl-accordion-toggle')) return;
                if (e.target.closest('.tdl-request-quote')) return;

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
            '<div class="tdl-result-content">' +
            '<h3 class="tdl-distributor-name">' + escapeHtml(distributor.name) + '</h3>' +
            primaryLocationHtml +
            '<div class="tdl-contact">' + contactHtml + '</div>' +
            serviceAreaHtml +
            additionalLocationsHtml +
            '<div class="tdl-card-actions">' +
            '<button class="tdl-request-quote" data-distributor-id="' + distributor.id + '" data-distributor-name="' + escapeAttr(distributor.name) + '">' +
            (config.i18n.requestQuote || 'Request Quote') +
            '</button>' +
            '</div>' +
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
                fetchPage(parseInt(this.dataset.page, 10));
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
     * Fetch ALL distributors matching the current saved search and render them on the
     * map. Called once per search (not per page-turn) so the map always shows the full
     * result set regardless of how the list is paginated.
     */
    function fetchMapForSearch() {
        if (!map) return;

        const token = ++mapSearchToken;

        // per_page=500 covers the full distributor catalogue; server cap allows this.
        let url = config.restUrl + 'search?page=1&per_page=500';
        if (savedQuery)              url += '&q='       + encodeURIComponent(savedQuery);
        if (savedComponents.city)    url += '&city='    + encodeURIComponent(savedComponents.city);
        if (savedComponents.state)   url += '&state='   + encodeURIComponent(savedComponents.state);
        if (savedComponents.zip)     url += '&zip='     + encodeURIComponent(savedComponents.zip);
        if (savedComponents.country) url += '&country=' + encodeURIComponent(savedComponents.country);

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (token !== mapSearchToken) return; // superseded by a newer search
                if (data.success) {
                    updateMap(data.results);
                }
            })
            .catch(function (err) {
                console.error('fetchMapForSearch error:', err);
            });
    }

    /**
     * Fetch lightweight pin data for all distributors and render them on the map.
     * Only called during the default view — not triggered by search results.
     */
    function fetchAllMarkers() {
        if (!map) return;

        fetch(config.restUrl + 'markers')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success && data.markers) {
                    updateMapFromMarkers(data.markers);
                }
            })
            .catch(function (err) {
                console.error('fetchAllMarkers error:', err);
            });
    }

    /**
     * Render lightweight markers on the map.
     * Unlike updateMap(), this does NOT fitBounds — the default center/zoom is preserved
     * so the user sees the full continent view rather than jumping to a single cluster.
     * The /markers endpoint now returns all popup-required fields so createInfoWindowContent()
     * renders the full M3 info window without an extra round-trip per click.
     */
    function updateMapFromMarkers(markerData) {
        if (!map) return;

        clearMarkers();

        markerData.forEach(function (item) {
            if (!item.lat || !item.lng) return;

            // Build the same distributor/location shape that createInfoWindowContent() expects,
            // sourced from the enriched /markers payload.
            const distributor = {
                id:      item.id,
                name:    item.name,
                phone:   item.phone   || '',
                website: item.website || '',
                emails:  item.emails  || {},
            };
            const location = {
                name:      item.location_name || '',
                address:   item.address   || '',
                address_2: item.address_2 || '',
                address_3: item.address_3 || '',
                city:      item.city  || '',
                state:     item.state || '',
                zip:       item.zip   || '',
                phone:     item.loc_phone || '',
                hours:     item.hours || '',
                lat:       item.lat,
                lng:       item.lng,
            };

            if (mapProvider === 'google') {
                const position = { lat: item.lat, lng: item.lng };
                var markerOpts = {
                    position: position,
                    title: item.name,
                    content: createGooglePinElement(),
                };
                if (!googleClusterer) markerOpts.map = map;

                const marker = new google.maps.marker.AdvancedMarkerElement(markerOpts);

                const infoWindow = new google.maps.InfoWindow({
                    content: createInfoWindowContent(distributor, location),
                });

                marker.addListener('gmp-click', function (e) {
                    closeAllInfoWindows();
                    infoWindow.open(map, marker);
                    if (e && e.domEvent) e.domEvent.stopPropagation();
                });

                marker.infoWindow = infoWindow;
                markers.push(marker);
            } else {
                const latLng = [item.lat, item.lng];
                const myIcon = L.divIcon({
                    className: 'tdl-leaflet-marker',
                    html: '<div class="tdl-marker-pin"></div>',
                    iconSize: [20, 20],
                    iconAnchor: [10, 10],
                    popupAnchor: [0, -14],
                });

                const marker = L.marker(latLng, { icon: myIcon });
                marker.bindPopup(createInfoWindowContent(distributor, location), {
                    // On mobile the map is short — autopan can move the popup
                    // partially out of view. Disable it; overflow:visible on the
                    // container and the max-height scroll cap handle visibility.
                    autoPan: window.innerWidth > 960,
                    autoPanPadding: [10, 10],
                });

                // Stop the tap from reaching the map's background click handler.
                // flagLeafletMarkerTap() guards against the browser's synthetic
                // click (~300-500ms post-touch) that bypasses stopPropagation.
                marker.on('click', function (e) {
                    flagLeafletMarkerTap();
                    L.DomEvent.stopPropagation(e);
                    if (window.innerWidth <= 960) {
                        panMarkerToBottom(marker.getLatLng());
                    }
                });

                markers.push(marker);

                if (clusterGroup) {
                    clusterGroup.addLayer(marker);
                } else {
                    marker.addTo(map);
                }
            }
        });

        if (mapProvider === 'google' && googleClusterer) {
            googleClusterer.addMarkers(markers);
        }
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

                    var markerOpts = {
                        position: position,
                        title: distributor.name + (location.name ? ' - ' + location.name : ''),
                        content: createGooglePinElement(),
                    };
                    if (!googleClusterer) markerOpts.map = map;

                    const marker = new google.maps.marker.AdvancedMarkerElement(markerOpts);

                    const infoContent = createInfoWindowContent(distributor, location);
                    const infoWindow = new google.maps.InfoWindow({
                        content: infoContent,
                    });

                    marker.addListener('gmp-click', function (e) {
                        closeAllInfoWindows();
                        infoWindow.open(map, marker);
                        highlightCard(index);
                        if (e && e.domEvent) e.domEvent.stopPropagation();
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

        if (googleClusterer) {
            googleClusterer.addMarkers(markers);
        }

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

                    const myIcon = L.divIcon({
                        className: 'tdl-leaflet-marker',
                        html: '<div class="tdl-marker-pin"></div>',
                        iconSize: [20, 20],
                        iconAnchor: [10, 10],
                        popupAnchor: [0, -14]
                    });

                    const marker = L.marker(latLng, { icon: myIcon });

                    const infoContent = createInfoWindowContent(distributor, location);
                    marker.bindPopup(infoContent, {
                        autoPan: window.innerWidth > 960,
                        autoPanPadding: [10, 10],
                    });

                    marker.on('click', function (e) {
                        flagLeafletMarkerTap();
                        L.DomEvent.stopPropagation(e);
                        highlightCard(index);
                        if (window.innerWidth <= 960) {
                            panMarkerToBottom(marker.getLatLng());
                        }
                    });

                    marker.distributorIndex = index;
                    markers.push(marker);

                    if (clusterGroup) {
                        clusterGroup.addLayer(marker);
                    } else {
                        marker.addTo(map);
                    }

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
     * Create info window content (Shared — Google popup / Leaflet bindPopup).
     * All M3 fields are preserved; layout is condensed to minimise popup height:
     *   - phone + website share one inline row
     *   - section padding is reduced
     *   - email rows use a compact single-line format
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

        // Prefer location-level phone; fall back to distributor-level.
        const phone = location.phone || distributor.phone;

        let html = '<div class="tdl-info-window">';

        // ── Header ─────────────────────────────────────────────────────────
        html += '<div class="tdl-iw-header">';
        html += '<h4>' + escapeHtml(distributor.name) + '</h4>';
        html += '</div>';

        // ── Address + Hours ────────────────────────────────────────────────
        html += '<div class="tdl-iw-section">';
        html += '<p class="tdl-iw-address">' + escapeHtml(address) + '</p>';
        if (location.hours) {
            html += '<p class="tdl-iw-hours">' + escapeHtml(location.hours).replace(/\n/g, '<br>') + '</p>';
        }
        html += '</div>';

        // ── Phone + Website — single inline row ────────────────────────────
        if (phone || distributor.website) {
            html += '<div class="tdl-iw-section tdl-iw-contact-row">';
            if (phone) {
                html += '<a href="tel:' + escapeHtml(phone) + '" class="tdl-iw-contact-item">' +
                    escapeHtml(phone) + '</a>';
            }
            if (phone && distributor.website) {
                html += '<span class="tdl-iw-sep" aria-hidden="true">·</span>';
            }
            if (distributor.website) {
                html += '<a href="' + escapeHtml(distributor.website) + '" target="_blank" rel="noopener" class="tdl-iw-contact-item">' +
                    escapeHtml(config.i18n.visitWebsite || 'Visit Website') + '</a>';
            }
            html += '</div>';
        }

        // ── Emails ────────────────────────────────────────────────────────
        if (distributor.emails) {
            const emailLabels = {
                'main':          config.i18n.emailMain     || 'Main',
                'sales':         config.i18n.emailSales    || 'Sales',
                'parts':         config.i18n.emailParts    || 'Parts',
                'service':       config.i18n.emailService  || 'Service',
                'installations': config.i18n.emailInstalls || 'Installations',
            };
            let emailHtml = '';
            Object.keys(emailLabels).forEach(function (key) {
                if (distributor.emails[key]) {
                    emailHtml += '<p class="tdl-iw-email">' +
                        '<span class="tdl-iw-email-label">' + emailLabels[key] + ':</span> ' +
                        '<a href="mailto:' + escapeHtml(distributor.emails[key]) + '">' +
                        escapeHtml(distributor.emails[key]) + '</a></p>';
                }
            });
            if (distributor.emails.other && Array.isArray(distributor.emails.other)) {
                distributor.emails.other.forEach(function (item) {
                    if (item.email && item.label) {
                        emailHtml += '<p class="tdl-iw-email">' +
                            '<span class="tdl-iw-email-label">' + escapeHtml(item.label) + ':</span> ' +
                            '<a href="mailto:' + escapeHtml(item.email) + '">' +
                            escapeHtml(item.email) + '</a></p>';
                    }
                });
            }
            if (emailHtml) {
                html += '<div class="tdl-iw-section">' + emailHtml + '</div>';
            }
        }

        // ── Actions: directions + request quote ────────────────────────────
        html += '<div class="tdl-iw-actions">';
        if (location.lat && location.lng) {
            const directionsUrl = 'https://www.google.com/maps/dir/?api=1&destination=' + location.lat + ',' + location.lng;
            html += '<a href="' + directionsUrl + '" target="_blank" rel="noopener" class="tdl-iw-directions">' +
                escapeHtml(config.i18n.getDirections) + '</a>';
        }
        html += '<button class="tdl-request-quote tdl-request-quote--infowindow" data-distributor-id="' + distributor.id + '" data-distributor-name="' + escapeAttr(distributor.name) + '">' +
            escapeHtml(config.i18n.requestQuote || 'Request Quote') +
            '</button>';
        html += '</div>';

        html += '</div>';
        return html;
    }

    /**
     * Clear all markers
     */
    function clearMarkers() {
        if (mapProvider === 'google') {
            if (googleClusterer) {
                googleClusterer.clearMarkers();
            } else {
                markers.forEach(function (marker) { marker.map = null; });
            }
        } else {
            if (clusterGroup) {
                // Remove and recreate the group to guarantee no stale cluster icons
                map.removeLayer(clusterGroup);
                clusterGroup = createClusterGroup();
                map.addLayer(clusterGroup);
            } else {
                markers.forEach(function (marker) { map.removeLayer(marker); });
            }
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
     * Escape HTML entities for text content
     */
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Escape a string for safe use in an HTML attribute value (double-quote delimited).
     * Handles chars that would break the attribute or the surrounding HTML.
     */
    function escapeAttr(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    // ── Quote Request Modal ──────────────────────────────────────────────────

    var originalFormHtml = null; // Cached on first init for post-submission reset
    var formNeedsReset   = false;

    /**
     * Set up modal close handlers, focus trap, and GF confirmation observer.
     * Called once on DOMContentLoaded; does nothing if the modal isn't present.
     */
    function initQuoteModal() {
        var modal = document.getElementById('tdl-quote-modal');
        if (!modal) return;

        var modalBody = modal.querySelector('.tdl-modal-body');
        if (modalBody) {
            originalFormHtml = modalBody.innerHTML;
        }

        modal.querySelector('.tdl-modal-backdrop').addEventListener('click', closeQuoteModal);
        modal.querySelector('.tdl-modal-close').addEventListener('click', closeQuoteModal);

        // Close on Escape
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hasAttribute('hidden')) {
                closeQuoteModal();
            }
        });

        // Trap Tab focus inside the dialog
        modal.addEventListener('keydown', function (e) {
            if (e.key !== 'Tab') return;
            var focusable = Array.from(modal.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), ' +
                'select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
            ));
            if (focusable.length === 0) return;
            var first = focusable[0];
            var last  = focusable[focusable.length - 1];
            if (e.shiftKey && document.activeElement === first) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && document.activeElement === last) {
                e.preventDefault();
                first.focus();
            }
        });

        setupFormObserver();
    }

    /**
     * Watch for GF's AJAX confirmation and auto-close the modal after 2.5 s.
     * Re-called after every form reset so the observer is always active.
     */
    function setupFormObserver() {
        var modal = document.getElementById('tdl-quote-modal');
        if (!modal || !config.gfFormId) return;

        var modalBody = modal.querySelector('.tdl-modal-body');
        if (!modalBody) return;

        new MutationObserver(function (mutations, obs) {
            if (document.getElementById('gform_confirmation_wrapper_' + config.gfFormId)) {
                formNeedsReset = true;
                setTimeout(closeQuoteModal, 2500);
                obs.disconnect();
            }
        }).observe(modalBody, { childList: true, subtree: true });
    }

    /**
     * Open the quote modal and populate the hidden distributor context fields.
     * If the form was previously submitted, restore its original HTML first.
     */
    function openQuoteModal(distributorId, distributorName) {
        var modal = document.getElementById('tdl-quote-modal');
        if (!modal) return;

        // Reset the form if a previous submission replaced it with confirmation
        if (formNeedsReset && originalFormHtml) {
            var modalBody = modal.querySelector('.tdl-modal-body');
            if (modalBody) {
                modalBody.innerHTML = originalFormHtml;

                // Re-execute inline scripts so GF re-binds its AJAX submission handler
                modalBody.querySelectorAll('script').forEach(function (old) {
                    var fresh = document.createElement('script');
                    if (old.src) {
                        fresh.src = old.src;
                    } else {
                        fresh.textContent = old.textContent;
                    }
                    old.parentNode.replaceChild(fresh, old);
                });

                // Trigger GF's post-render event for conditional logic and formatting
                if (window.jQuery) {
                    window.jQuery(document).trigger('gform_post_render', [config.gfFormId, 0]);
                }
            }
            formNeedsReset = false;
            setupFormObserver();
        }

        // Populate hidden GF fields before the form is shown
        if (config.gfFormId) {
            var fid = config.gfFormId;
            if (config.gfDistributorFieldId) {
                var idInput = document.getElementById('input_' + fid + '_' + config.gfDistributorFieldId);
                if (idInput) idInput.value = distributorId;
            }
            if (config.gfDistributorNameFieldId) {
                var nameInput = document.getElementById('input_' + fid + '_' + config.gfDistributorNameFieldId);
                if (nameInput) nameInput.value = distributorName || '';
            }
        }

        modal.removeAttribute('hidden');
        document.body.classList.add('tdl-modal-open');

        var closeBtn = modal.querySelector('.tdl-modal-close');
        if (closeBtn) closeBtn.focus();
    }

    function closeQuoteModal() {
        var modal = document.getElementById('tdl-quote-modal');
        if (!modal) return;
        modal.setAttribute('hidden', '');
        document.body.classList.remove('tdl-modal-open');
    }

    // Exposed globally so any external script can trigger a close if needed.
    window.tdlCloseQuoteModal = closeQuoteModal;

})();
