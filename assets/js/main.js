/**
 * Main JavaScript for Family Travel Tracker
 *
 * @package Family_Travel_Tracker
 */

(function($) {
    'use strict';
    
    const FTT = {
        
        // Airport data loaded from JSON
        airportData: {},
        
        /**
         * Escape HTML to prevent XSS and show proper text
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        /**
         * Initialize
         */
        init: function() {
            this.loadAirportData();
            this.initCalendar();
            this.initEventForm();
            this.initDashboard();
            this.initEventList();
        },
        
        /**
         * Load airport data from JSON file
         */
        loadAirportData: function() {
            const self = this;
            $.ajax({
                url: fttData.pluginUrl + '/assets/js/airports.json',
                dataType: 'json',
                async: false, // Load synchronously to ensure data is available
                success: function(data) {
                    self.airportData = data;
                },
                error: function() {
                    console.warn('Could not load airport data, using fallback list');
                    // Fallback to basic list
                    self.airportData = {
                        'BDL': 'Hartford, CT', 'BWI': 'Baltimore, MD', 'ORD': 'Chicago, IL',
                        'LAX': 'Los Angeles, CA', 'DFW': 'Dallas, TX', 'ATL': 'Atlanta, GA',
                        'DEN': 'Denver, CO', 'SFO': 'San Francisco, CA', 'SEA': 'Seattle, WA',
                        'BNA': 'Nashville, TN', 'PHX': 'Phoenix, AZ', 'BOS': 'Boston, MA'
                    };
                }
            });
        },
        
        /**
         * Initialize calendar
         */
        initCalendar: function() {
            const calendarEl = document.getElementById('ftt-calendar');
            if (!calendarEl) return;

            // Check if FullCalendar is available
            if (typeof FullCalendar === 'undefined') {
                console.error('FullCalendar library not loaded');
                return;
            }
            
            // Load children first via REST API, then render calendar
            this.loadChildrenFilter().then(() => {
                this.renderCalendar(calendarEl);
            }).catch(error => {
                console.error('Error loading children:', error);
                // Render calendar anyway even if children load fails
                this.renderCalendar(calendarEl);
            });
        },
        
        /**
         * Load children via REST API and populate filter
         */
        loadChildrenFilter: function() {
            return fetch(fttData.restUrl + 'children', {
                headers: {
                    'X-WP-Nonce': fttData.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                const children = data.children || [];
                
                if (children.length === 0) {
                    // No children - hide the filter
                    return;
                }
                
                // Show and populate the child filter
                const filterEl = document.getElementById('ftt-child-filter');
                const listEl = document.getElementById('ftt-child-filter-list');
                
                if (!filterEl || !listEl) return;
                
                filterEl.style.display = 'block';
                listEl.innerHTML = '';
                
                children.forEach(child => {
                    const label = document.createElement('label');
                    label.className = 'ftt-filter-item';
                    
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.className = 'ftt-child-toggle';
                    checkbox.dataset.childId = child.id;
                    checkbox.checked = true;
                    
                    const colorSpan = document.createElement('span');
                    colorSpan.className = 'ftt-color-indicator';
                    colorSpan.style.backgroundColor = child.color || '#2196F3';
                    
                    const nameSpan = document.createElement('span');
                    nameSpan.className = 'ftt-child-name';
                    nameSpan.textContent = child.name;
                    
                    label.appendChild(checkbox);
                    label.appendChild(colorSpan);
                    label.appendChild(nameSpan);
                    listEl.appendChild(label);
                });
                
                console.log('Loaded ' + children.length + ' children via REST API');
            });
        },
        
        /**
         * Render calendar (extracted from initCalendar)
         */
        renderCalendar: function(calendarEl) {
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'listWeek',
                timeZone: fttData.userTimezone || 'local',
                height: 'auto',
                contentHeight: 'auto',
                views: {
                    listWeek: {
                        titleFormat: window.innerWidth <= 768
                            ? { month: 'short', day: 'numeric' }
                            : undefined
                    }
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                events: function(info, successCallback, failureCallback) {
                    FTT.fetchEvents(info.startStr, info.endStr)
                        .then(events => {
                            // Filter events based on visible children
                            let filteredEvents = events;
                            if (FTT.visibleChildren && FTT.visibleChildren.size > 0) {
                                filteredEvents = events.filter(event => {
                                    // If no member_id, show the event
                                    if (!event.member_id) return true;
                                    // Show if this child is visible
                                    return FTT.visibleChildren.has(String(event.member_id));
                                });
                            }
                            
                            const calendarEvents = filteredEvents.map(event => {
                                // Use title as-is, formatting handled in eventDidMount
                                return {
                                    id: event.id,
                                    title: event.title,
                                    start: event.start_datetime,
                                    end: event.end_datetime,
                                    extendedProps: event
                                };
                            });
                            successCallback(calendarEvents);
                        })
                        .catch(error => {
                            console.error('Error loading events:', error);
                            failureCallback(error);
                        });
                },
                eventClick: function(info) {
                    FTT.showEventModal(info.event.extendedProps);
                },
                eventDidMount: function(info) {
                    // Add color coding based on event type
                    const eventType = info.event.extendedProps.event_type;
                    info.el.classList.add('ftt-event-type-' + eventType);
                    
                    // Add child color if present
                    const childColor = info.event.extendedProps.color;
                    const textColor = info.event.extendedProps.textColor;
                    const className = info.event.extendedProps.className;
                    
                    if (childColor) {
                        info.el.style.backgroundColor = childColor;
                        info.el.style.borderColor = childColor;
                        if (textColor) {
                            info.el.style.color = textColor;
                        }
                        if (className) {
                            info.el.classList.add(className);
                        }
                    }
                    
                    // Add member name on separate line if present
                    if (info.event.extendedProps.member_name) {
                        const titleEl = info.el.querySelector('.fc-event-title');
                        if (titleEl) {
                            const memberName = document.createElement('div');
                            memberName.className = 'ftt-calendar-member-name';
                            memberName.textContent = info.event.extendedProps.member_name;
                            titleEl.insertBefore(memberName, titleEl.firstChild);
                        }
                    }
                }
            });

            // Add external iCal feeds as a single additional event source
            if (fttData.externalCalendars && fttData.externalCalendars.length > 0) {
                calendar.addEventSource({
                    events: function(info, successCallback, failureCallback) {
                        fetch(fttData.restUrl + 'external-events', {
                            headers: {
                                'X-WP-Nonce': fttData.nonce,
                            },
                        })
                        .then(function(r) {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(successCallback)
                        .catch(function(err) {
                            console.warn('FTT: external calendar fetch failed', err);
                            failureCallback(err);
                        });
                    },
                    editable: false,
                });
            }
            
            calendar.render();
            
            // Store calendar instance for re-rendering
            this.calendar = calendar;
            
            // Child filter functionality
            const childToggles = document.querySelectorAll('.ftt-child-toggle');
            if (childToggles.length > 0) {
                // Track which children are visible
                this.visibleChildren = new Set();
                childToggles.forEach(toggle => {
                    if (toggle.checked) {
                        this.visibleChildren.add(toggle.dataset.childId);
                    }
                    
                    toggle.addEventListener('change', (e) => {
                        const childId = e.target.dataset.childId;
                        if (e.target.checked) {
                            this.visibleChildren.add(childId);
                        } else {
                            this.visibleChildren.delete(childId);
                        }
                        calendar.refetchEvents();
                    });
                });
            }
            
            // Handle member selector change
            const memberSelector = document.getElementById('ftt-calendar-member');
            if (memberSelector) {
                memberSelector.addEventListener('change', () => {
                    calendar.refetchEvents();
                });
            }
            
            // Check if there's an event_id in the URL to display
            const urlParams = new URLSearchParams(window.location.search);
            const eventId = urlParams.get('event_id');
            
            if (eventId) {
                // Load and show the specific event
                this.fetchEvent(eventId).then(event => {
                    if (event) {
                        this.showEventModal(event);
                    }
                }).catch(error => {
                    console.error('Error loading event from URL:', error);
                });
            }
        },
        
        /**
         * Load event form data from REST APIs
         * Dynamically populates children and groups without direct PHP calls
         */
        loadEventFormData: function() {
            // Load children data
            fetch(fttData.restUrl + '/ftt/v1/children', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': fttData.nonce
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Failed to load children');
                return response.json();
            })
            .then(data => {
                const memberContainer = document.getElementById('ftt-member-selector-container');
                const memberCheckboxes = document.getElementById('ftt-member-checkboxes');
                
                if (data.is_member) {
                    // Member creating their own event
                    memberCheckboxes.innerHTML = '<input type="hidden" id="member_id" name="member_id" value="' + data.user_id + '">';
                } else if (data.children && data.children.length > 0) {
                    // Parent - show children
                    memberContainer.style.display = 'block';
                    
                    let html = '';
                    
                    // Add "Family Event" option if multiple children
                    if (data.children.length > 1) {
                        html += '<label class="ftt-member-check ftt-member-check--family">';
                        html += '  <input type="checkbox" id="ftt-family-event" value="family">';
                        html += '  <span>Family Event (all children)</span>';
                        html += '</label>';
                        html += '<hr class="ftt-member-divider">';
                    }
                    
                    // Add individual child checkboxes
                    data.children.forEach(child => {
                        html += '<label class="ftt-member-check">';
                        html += '  <input type="checkbox" class="ftt-child-checkbox" value="' + child.id + '">';
                        html += '  <span>' + child.display_name + '</span>';
                        html += '</label>';
                    });
                    
                    memberCheckboxes.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading children:', error);
                document.getElementById('ftt-member-checkboxes').innerHTML = 
                    '<div class="ftt-error">Error loading children. Please refresh the page.</div>';
            });
            
            // Load groups data
            fetch(fttData.restUrl + '/ftt/v1/groups', {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': fttData.nonce
                }
            })
            .then(response => {
                if (!response.ok) throw new Error('Failed to load groups');
                return response.json();
            })
            .then(data => {
                const groupContainer = document.getElementById('ftt-group-selector-container');
                const groupSelect = document.getElementById('group_id');
                
                if (data.groups && data.groups.length > 0) {
                    groupContainer.style.display = 'block';
                    
                    let html = '';
                    data.groups.forEach(group => {
                        const selected = group.is_primary ? ' selected' : '';
                        const primaryLabel = group.is_primary ? ' (Primary)' : '';
                        html += '<option value="' + group.id + '"' + selected + '>';
                        html += group.name + primaryLabel;
                        html += '</option>';
                    });
                    
                    groupSelect.innerHTML = html;
                }
            })
            .catch(error => {
                console.error('Error loading groups:', error);
                document.getElementById('group_id').innerHTML = 
                    '<option value="">Error loading groups. Please refresh the page.</option>';
            });
        },
        
        /**
         * Initialize event form
         */
        initEventForm: function() {
            const form = document.getElementById('ftt-event-form');
            if (!form) return;
            
            // Load form data from REST APIs
            this.loadEventFormData();
            
            // Get event ID from URL if editing
            const urlParams = new URLSearchParams(window.location.search);
            const eventId = urlParams.get('event_id');
            
            if (eventId) {
                this.loadEventForEdit(eventId);
            }

            // Family Event checkbox: when checked, disable individual child checkboxes
            $(document).on('change', '#ftt-family-event', function() {
                if ($(this).is(':checked')) {
                    $('.ftt-child-checkbox').prop('checked', false).prop('disabled', true);
                } else {
                    $('.ftt-child-checkbox').prop('disabled', false);
                }
            });

            // Individual child checkbox: if any are checked, uncheck Family Event
            $(document).on('change', '.ftt-child-checkbox', function() {
                if ($(this).is(':checked')) {
                    $('#ftt-family-event').prop('checked', false);
                }
            });
            
            // Add time block button
            $('#ftt-add-time-block').on('click', function(e) {
                e.preventDefault();
                FTT.addTimeBlock();
            });
            
            // Add travel leg button
            $('#ftt-add-travel-leg').on('click', function(e) {
                e.preventDefault();
                FTT.addTravelLeg();
            });
            
            // Auto-expand travel section for flight_only events
            $('#event_type').on('change', function() {
                const isFlight = $(this).val() === 'flight_only';
                if (isFlight) {
                    $('#ftt-travel-section').show();
                    // Add a travel leg if none exist
                    if ($('#ftt-travel-legs-container .ftt-travel-leg').length === 0) {
                        FTT.addTravelLeg({ mode: 'fly' });
                    }
                }
            });

            // Event type combobox
            (function() {
                const $search  = $('#event_type_search');
                const $hidden  = $('#event_type');
                const $list    = $('#event_type_list');
                const etTypes  = window.fttEventTypes || {};

                function buildList(filter) {
                    $list.empty();
                    const q = (filter || '').toLowerCase().trim();
                    $.each(etTypes, function(key, label) {
                        if (!q || label.toLowerCase().indexOf(q) !== -1 || key.toLowerCase().indexOf(q) !== -1) {
                            $('<li>').attr({ 'data-value': key, role: 'option' })
                                .text(label).appendTo($list);
                        }
                    });
                    // Always append an "Other" entry
                    var otherText = q ? 'Other: "' + filter + '"' : 'Other (enter custom type)';
                    $('<li>').attr({ 'data-value': '__other__', role: 'option' })
                        .addClass('ftt-combobox-other').text(otherText).appendTo($list);
                    $list.show();
                }

                $search.on('input focus', function() {
                    buildList($(this).val());
                });

                $list.on('mousedown', 'li', function(e) {
                    e.preventDefault();
                    var val   = $(this).data('value');
                    var typed = $search.val().trim();
                    if (val === '__other__') {
                        var custom = typed || 'other';
                        $hidden.val(custom).trigger('change');
                        $search.val(typed || 'Other');
                    } else {
                        $hidden.val(val).trigger('change');
                        $search.val(etTypes[val] || val);
                    }
                    $list.hide();
                });

                $search.on('blur', function() {
                    // Allow mousedown on list to fire first
                    setTimeout(function() { $list.hide(); }, 150);
                });

                $(document).on('click.etCombobox', function(e) {
                    if (!$(e.target).closest('#ftt-event-type-combobox').length) {
                        $list.hide();
                    }
                });
            }());
            
            // travel_needed / travel_mode / flight_needed are no longer manual fields.
            // They are derived from the travel legs array at submit time.
            
            // All day checkbox
            $('#all_day').on('change', function() {
                const $startInput = $('#start_datetime');
                const $endInput = $('#end_datetime');
                
                if (this.checked) {
                    // Convert to date-only inputs
                    $startInput.attr('type', 'date');
                    $endInput.attr('type', 'date');
                    
                    // Auto-populate end date from start date if end is empty
                    if (!$endInput.val() && $startInput.val()) {
                        $endInput.val($startInput.val());
                    }
                } else {
                    // Convert back to datetime inputs
                    $startInput.attr('type', 'datetime-local');
                    $endInput.attr('type', 'datetime-local');
                }
            });
            
            // Auto-populate end date when start date changes for all-day events
            $('#start_datetime').on('change', function() {
                const $endInput = $('#end_datetime');
                if ($('#all_day').is(':checked') && !$endInput.val()) {
                    $endInput.val($(this).val());
                }
            });
            
            // Initialize Mapbox Autocomplete
            this.initMapboxAutocomplete();
            
            // (flight_needed derived from legs — no checkbox handler needed)
            
            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                FTT.submitEventForm(eventId);
            });
            
            // Delete button
            $('#ftt-delete-event').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this event?')) {
                    FTT.deleteEvent(eventId);
                }
            });
        },
        
        /**
         * Initialize dashboard
         */
        initDashboard: function() {
            const dashboard = document.getElementById('ftt-dashboard');
            if (!dashboard) return;
            
            this.loadDashboardData();
        },
        
        /**
         * Initialize event list
         */
        initEventList: function() {
            const eventList = document.getElementById('ftt-event-list');
            if (!eventList) return;
            
            // Event list is rendered server-side, but we can add interactivity here
            $('.ftt-event-item').on('click', function() {
                const eventId = $(this).data('event-id');
                if (eventId) {
                    FTT.fetchEvent(eventId).then(event => {
                        FTT.showEventModal(event);
                    });
                }
            });
        },
        
        /**
         * Fetch events from API
         */
        fetchEvents: function(startDate, endDate) {
            const params = new URLSearchParams();
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            
            // Add member filter if selector exists and has value
            const memberSelector = document.getElementById('ftt-calendar-member');
            if (memberSelector && memberSelector.value) {
                params.append('member_id', memberSelector.value);
            }
            
            return fetch(fttData.restUrl + 'events?' + params.toString(), {
                headers: {
                    'X-WP-Nonce': fttData.nonce
                }
            })
            .then(response => response.json());
        },
        
        /**
         * Fetch single event
         */
        fetchEvent: function(eventId) {
            return fetch(fttData.restUrl + 'events/' + eventId, {
                headers: {
                    'X-WP-Nonce': fttData.nonce
                }
            })
            .then(response => response.json());
        },
        
        /**
         * Load event for editing
         */
        loadEventForEdit: function(eventId) {
            this.fetchEvent(eventId).then(event => {
                // Populate form fields
                $('#event_title').val(event.title);
                
                // IMPORTANT: Set all_day checkbox FIRST to switch input types
                $('#all_day').prop('checked', event.all_day || false).trigger('change');
                
                // THEN set date/time values based on all_day flag
                if (event.all_day) {
                    // For all-day events, convert to date-only format (YYYY-MM-DD)
                    const startDate = event.start_datetime ? event.start_datetime.split('T')[0] : '';
                    const endDate = event.end_datetime ? event.end_datetime.split('T')[0] : '';
                    $('#start_datetime').val(startDate);
                    $('#end_datetime').val(endDate);
                } else {
                    // For timed events, use full datetime
                    $('#start_datetime').val(event.start_datetime);
                    $('#end_datetime').val(event.end_datetime);
                }
                
                $('#timezone').val(event.timezone);
                // Set hidden value and also update the visible combobox text
                $('#event_type').val(event.event_type);
                (function() {
                    var et = event.event_type || '';
                    var etTypes = window.fttEventTypes || {};
                    $('#event_type_search').val(etTypes[et] || et.replace(/_/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); }));
                }());
                $('#location_name').val(event.location_name);
                $('#location_address').val(event.location_address);
                
                // Load coordinates if they exist
                if (event.location_latitude || event.location_longitude) {
                    // Remove any existing hidden inputs
                    $('#location_latitude, #location_longitude').remove();
                    
                    // Add hidden inputs with coordinates
                    if (event.location_latitude) {
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('id', 'location_latitude')
                            .attr('name', 'location_latitude')
                            .val(event.location_latitude)
                            .appendTo('#ftt-event-form');
                    }
                    if (event.location_longitude) {
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('id', 'location_longitude')
                            .attr('name', 'location_longitude')
                            .val(event.location_longitude)
                            .appendTo('#ftt-event-form');
                    }
                }
                
                $('#notes').val(event.notes);
                // travel_needed / travel_mode / flight_needed are derived from legs at submit;
                // nothing to restore here.
                
                // Populate child checkboxes (new multi-child UI)
                if ($('#ftt-member-checkboxes').length) {
                    // Uncheck everything first
                    $('#ftt-family-event').prop('checked', false);
                    $('.ftt-child-checkbox').prop('checked', false);
                    if (event.member_id) {
                        // Pre-check the specific child
                        $('.ftt-child-checkbox[value="' + event.member_id + '"]').prop('checked', true);
                    } else {
                        // No member_id = family event
                        $('#ftt-family-event').prop('checked', true);
                        $('.ftt-child-checkbox').prop('disabled', true);
                    }
                } else if ($('#member_id').length) {
                    // Legacy hidden field (member creating own event)
                    if (event.member_id) $('#member_id').val(event.member_id);
                }
                
                // Load time blocks
                if (event.time_blocks && event.time_blocks.length > 0) {
                    event.time_blocks.forEach(block => {
                        this.addTimeBlock(block);
                    });
                }
                
                // Load travel legs
                if (event.travel_legs && event.travel_legs.length > 0) {
                    event.travel_legs.forEach(leg => {
                        this.addTravelLeg(leg);
                    });
                    // Check for flight link suggestions after loading legs
                    setTimeout(() => this.checkFlightSuggestions(), 500);
                }
                
                // Show delete button
                $('#ftt-delete-event').show();
            }).catch(error => {
                console.error('Error loading event:', error);
                alert('Error loading event for editing.');
            });
        },
        
        /**
         * Submit event form
         */
        submitEventForm: function(eventId) {
            // Determine which member IDs to save against.
            // - Family event ("all children" checkbox or no checkbox UI): member_id = '' (shows for everyone)
            // - One child selected: single API call with that member_id
            // - Multiple children selected: one API call per child (cloned events)
            let memberIds = [];
            const isFamilyEvent = $('#ftt-family-event').is(':checked');
            if ($('#ftt-member-checkboxes').length && !isFamilyEvent) {
                $('.ftt-child-checkbox:checked').each(function() {
                    memberIds.push($(this).val());
                });
                if (memberIds.length === 0) {
                    alert('Please select at least one child, or check "Family Event".');
                    return;
                }
            }
            // isFamilyEvent or legacy hidden field → memberIds stays empty, member_id sent as ''

            const baseData = {
                member_id: (memberIds.length === 1) ? memberIds[0] : ($('#member_id').val() || ''),
                title: $('#event_title').val(),
                start_datetime: $('#start_datetime').val(),
                end_datetime: $('#end_datetime').val(),
                all_day: $('#all_day').is(':checked'),
                timezone: $('#timezone').val(),
                event_type: $('#event_type').val(),
                location_name: $('#location_name').val(),
                location_address: $('#location_address').val(),
                notes: $('#notes').val(),
                // Derive travel flags from the legs array automatically
                travel_needed: ($('#ftt-travel-legs-container .ftt-travel-leg').length > 0),
                travel_mode: (function() {
                    const firstMode = $('#ftt-travel-legs-container .ftt-leg-mode').first();
                    return firstMode.length ? (firstMode.val() || 'drive') : '';
                })(),
                flight_needed: ($('#ftt-travel-legs-container .ftt-leg-mode').filter(function(){ return $(this).val() === 'fly'; }).length > 0),
                time_blocks: JSON.stringify(this.collectTimeBlocks()),
                travel_legs: JSON.stringify(this.collectTravelLegs())
            };

            // Validate required fields
            if (!baseData.title || !baseData.start_datetime || !baseData.end_datetime) {
                alert('Please fill in all required fields.');
                return;
            }

            const self = this;
            const dashboardUrl = fttData.dashboardUrl || window.location.origin;

            // For editing an existing event OR single/family submit → one request
            if (eventId || memberIds.length <= 1) {
                const method = eventId ? 'PUT' : 'POST';
                const url = eventId ? fttData.restUrl + 'events/' + eventId : fttData.restUrl + 'events';
                fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': fttData.nonce },
                    body: JSON.stringify(baseData)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.id) {
                        alert('Event saved successfully!');
                        window.location.href = dashboardUrl;
                    } else {
                        alert('Error saving event.');
                    }
                })
                .catch(err => { console.error('Error saving event:', err); alert('Error saving event.'); });

            } else {
                // Multiple children selected — create one event per child in parallel
                const requests = memberIds.map(mid => {
                    const payload = Object.assign({}, baseData, { member_id: mid });
                    return fetch(fttData.restUrl + 'events', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': fttData.nonce },
                        body: JSON.stringify(payload)
                    }).then(r => r.json());
                });
                Promise.all(requests)
                .then(results => {
                    const allOk = results.every(d => d.id);
                    if (allOk) {
                        alert('Event saved for ' + memberIds.length + ' children!');
                        window.location.href = dashboardUrl;
                    } else {
                        alert('Some events may not have saved. Please check the calendar.');
                    }
                })
                .catch(err => { console.error('Error saving events:', err); alert('Error saving events.'); });
            }
        },
        
        /**
         * Delete event
         */
        deleteEvent: function(eventId) {
            fetch(fttData.restUrl + 'events/' + eventId, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': fttData.nonce
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.deleted) {
                    alert('Event deleted successfully!');
                    window.location.href = window.location.pathname;
                } else {
                    alert('Error deleting event.');
                }
            })
            .catch(error => {
                console.error('Error deleting event:', error);
                alert('Error deleting event.');
            });
        },
        
        /**
         * Add time block
         */
        addTimeBlock: function(data) {
            const container = $('#ftt-time-blocks-container');
            const index = container.children().length;

            // When adding a new block (no existing data), seed dates from the event fields
            if (!data) {
                const eventStart = $('#start_datetime').val();
                const eventEnd   = $('#end_datetime').val();
                const isAllDay   = $('#all_day').is(':checked');
                if (eventStart) {
                    // For all-day events the input is date-only; add T00:00 so datetime-local is valid
                    data = {
                        start_datetime: isAllDay ? eventStart + 'T00:00' : eventStart,
                        end_datetime:   isAllDay ? (eventEnd || eventStart) + 'T00:00' : (eventEnd || eventStart),
                        block_type: '',
                        title: '',
                        notes: ''
                    };
                }
            }
            
            const html = `
                <div class="ftt-time-block" data-index="${index}">
                    <h4>Time Block ${index + 1} <button type="button" class="ftt-remove-block" data-target="time-block">Remove</button></h4>
                    <div class="ftt-form-row">
                        <div class="ftt-form-field">
                            <label>Block Type</label>
                            <select name="time_blocks[${index}][block_type]" required>
                                <option value="">Select type...</option>
                                <option value="practice" ${data && data.block_type === 'practice' ? 'selected' : ''}>Practice</option>
                                <option value="travel" ${data && data.block_type === 'travel' ? 'selected' : ''}>Travel</option>
                                <option value="admin" ${data && data.block_type === 'admin' ? 'selected' : ''}>Admin</option>
                                <option value="meal" ${data && data.block_type === 'meal' ? 'selected' : ''}>Meal</option>
                                <option value="medical" ${data && data.block_type === 'medical' ? 'selected' : ''}>Medical</option>
                                <option value="performance" ${data && data.block_type === 'performance' ? 'selected' : ''}>Performance</option>
                                <option value="other" ${data && data.block_type === 'other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="ftt-form-field">
                            <label>Title</label>
                            <input type="text" name="time_blocks[${index}][title]" value="${data ? data.title : ''}" required>
                        </div>
                    </div>
                    <div class="ftt-form-row">
                        <div class="ftt-form-field">
                            <label>Start Time</label>
                            <input type="datetime-local" name="time_blocks[${index}][start_datetime]" value="${data && data.start_datetime ? data.start_datetime.slice(0, 16) : ''}" required>
                        </div>
                        <div class="ftt-form-field">
                            <label>End Time</label>
                            <input type="datetime-local" name="time_blocks[${index}][end_datetime]" value="${data && data.end_datetime ? data.end_datetime.slice(0, 16) : ''}" required>
                        </div>
                    </div>
                    <div class="ftt-form-field">
                        <label>Notes</label>
                        <textarea name="time_blocks[${index}][notes]" rows="2">${data ? data.notes : ''}</textarea>
                    </div>
                </div>
            `;
            
            container.append(html);
            
            // Add remove handler
            container.find('.ftt-remove-block').last().on('click', function() {
                $(this).closest('.ftt-time-block').remove();
            });
        },
        
        /**
         * Add travel leg
         */
        addTravelLeg: function(data) {
            const container = $('#ftt-travel-legs-container');
            const index = container.children().length;
            const self = this;
            const isNewLeg = !data;

            // When adding a new leg (no existing data), seed depart/return from the event fields
            if (!data) {
                const eventStart = $('#start_datetime').val();
                const eventEnd   = $('#end_datetime').val();
                // substring(0,10) reliably extracts YYYY-MM-DD from both
                // 'datetime-local' (2026-06-15T08:00) and 'date' (2026-06-15) values
                const toDate = val => val ? val.substring(0, 10) : '';
                data = {
                    depart_date: toDate(eventStart),
                    return_date: toDate(eventEnd)
                };
            }

            // Pre-compute display values for airport pickers
            const airportDisplay = code => code
                ? (self.airportData[code] ? self.airportData[code] + ' (' + code + ')' : code)
                : '';
            const departDisplay = airportDisplay((data && data.depart_airport) || '');
            const arriveDisplay = airportDisplay((data && data.arrive_airport) || '');
            
            const html = `
                <div class="ftt-travel-leg" data-index="${index}">
                    <div class="ftt-leg-header">
                        <h4>Travel Leg ${index + 1}</h4>
                        <button type="button" class="ftt-remove-block" data-target="travel-leg">✕ Remove</button>
                    </div>
                    
                    <div class="ftt-form-row">
                        <div class="ftt-form-field">
                            <label>Leg Type</label>
                            <select name="travel_legs[${index}][mode]" class="ftt-leg-mode">
                                <option value="fly" ${data && data.mode === 'fly' ? 'selected' : ''}>✈️ Flight</option>
                                <option value="drive" ${data && data.mode === 'drive' ? 'selected' : ''}>🚗 Drive</option>
                                <option value="bus" ${data && data.mode === 'bus' ? 'selected' : ''}>🚌 Bus</option>
                                <option value="shuttle" ${data && data.mode === 'shuttle' ? 'selected' : ''}>🚐 Shuttle</option>
                                <option value="other" ${data && data.mode === 'other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="ftt-form-row">
                        <div class="ftt-form-field ftt-flight-only">
                            <label>From Airport</label>
                            <div class="ftt-airport-picker">
                                <input type="text" class="ftt-airport-search-input ftt-from-airport-text" placeholder="City or airport code…" autocomplete="off" value="${departDisplay}">
                                <input type="hidden" name="travel_legs[${index}][depart_airport]" class="ftt-from-airport" value="${(data && data.depart_airport) || ''}">
                                <ul class="ftt-airport-list" role="listbox"></ul>
                            </div>
                        </div>
                        <div class="ftt-form-field ftt-non-flight">
                            <label>From Location</label>
                            <input type="text" name="travel_legs[${index}][depart_location]" value="${(data && data.depart_location) || ''}" placeholder="e.g., Hartford, CT">
                        </div>
                        <div class="ftt-form-field ftt-flight-only">
                            <label>To Airport</label>
                            <div class="ftt-airport-picker">
                                <input type="text" class="ftt-airport-search-input ftt-to-airport-text" placeholder="City or airport code…" autocomplete="off" value="${arriveDisplay}">
                                <input type="hidden" name="travel_legs[${index}][arrive_airport]" class="ftt-to-airport" value="${(data && data.arrive_airport) || ''}">
                                <ul class="ftt-airport-list" role="listbox"></ul>
                            </div>
                        </div>
                        <div class="ftt-form-field ftt-non-flight">
                            <label>To Location</label>
                            <input type="text" name="travel_legs[${index}][arrive_location]" value="${(data && data.arrive_location) || ''}" placeholder="e.g., Baltimore, MD">
                        </div>
                    </div>
                    
                    <div class="ftt-flight-dates-section">
                        <div class="ftt-date-group ftt-departure-group">
                            <h5 class="ftt-date-group-label">✈️ Departure</h5>
                            <div class="ftt-form-row">
                                <div class="ftt-form-field">
                                    <label>Date *</label>
                                    <input type="date" name="travel_legs[${index}][depart_date]" value="${data && data.depart_date ? data.depart_date : ''}" required>
                                </div>
                                <div class="ftt-form-field">
                                    <label>Time of Day</label>
                                    <select name="travel_legs[${index}][depart_time_of_day]">
                                        <option value="">Any Time</option>
                                        <option value="morning" ${data && data.depart_time_of_day === 'morning' ? 'selected' : ''}>Morning</option>
                                        <option value="midday" ${data && data.depart_time_of_day === 'midday' ? 'selected' : ''}>Mid-Day</option>
                                        <option value="afternoon" ${data && data.depart_time_of_day === 'afternoon' ? 'selected' : ''}>Afternoon</option>
                                        <option value="night" ${data && data.depart_time_of_day === 'night' ? 'selected' : ''}>Night</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                    
                    <div class="ftt-booking-section ftt-flight-only">
                        <div class="ftt-form-field">
                            <label class="ftt-booking-toggle">
                                <input type="checkbox" name="travel_legs[${index}][booked]" value="1" class="ftt-booked-checkbox" ${data && data.booked ? 'checked' : ''}>
                                ✓ Already Booked
                            </label>
                        </div>
                        
                        <div class="ftt-booking-details" style="display: ${data && data.booked ? 'block' : 'none'};">
                            <div class="ftt-form-row">
                                <div class="ftt-form-field">
                                    <label>Airline</label>
                                    <input type="text" name="travel_legs[${index}][airline]" value="${(data && data.airline) || ''}" placeholder="Southwest">
                                </div>
                                <div class="ftt-form-field">
                                    <label>Flight Number</label>
                                    <input type="text" name="travel_legs[${index}][flight_number]" value="${(data && data.flight_number) || ''}" placeholder="WN 420">
                                </div>
                                <div class="ftt-form-field">
                                    <label>Confirmation #</label>
                                    <input type="text" name="travel_legs[${index}][confirmation]" value="${(data && data.confirmation) || ''}" placeholder="ABC123">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ftt-form-field ftt-flight-only">
                        <label>Baggage (select all that apply)</label>
                        <div class="ftt-checkbox-group">
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="carry_on" ${data && data.baggage && data.baggage.includes('carry_on') ? 'checked' : ''}> Carry-On</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="checked" ${data && data.baggage && data.baggage.includes('checked') ? 'checked' : ''}> Checked Bag</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="instrument" ${data && data.baggage && data.baggage.includes('instrument') ? 'checked' : ''}> Instrument</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="color_guard_equipment" ${data && data.baggage && data.baggage.includes('color_guard_equipment') ? 'checked' : ''}> Color Guard Equip</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="oversize" ${data && data.baggage && data.baggage.includes('oversize') ? 'checked' : ''}> Oversize</label>
                        </div>
                    </div>
                    
                    <div class="ftt-form-field">
                        <label>Notes</label>
                        <textarea name="travel_legs[${index}][notes]" rows="2">${data && data.notes ? data.notes : ''}</textarea>
                    </div>
                </div>
            `;
            
            container.append(html);
            
            const $newLeg = container.find('.ftt-travel-leg').last();
            
            // Pre-fill from airport with previous leg's to airport
            if (isNewLeg && index > 0) {
                const $prevLeg = container.find('.ftt-travel-leg').eq(index - 1);
                const prevToCode = $prevLeg.find('.ftt-to-airport').val();
                if (prevToCode) {
                    $newLeg.find('.ftt-from-airport').val(prevToCode);
                    $newLeg.find('.ftt-from-airport-text').val(airportDisplay(prevToCode));
                }
            }

            // Init airport pickers
            self.initAirportPicker(
                $newLeg.find('.ftt-from-airport-text'),
                $newLeg.find('.ftt-from-airport'),
                function() { self.checkFlightSuggestions(); }
            );
            self.initAirportPicker(
                $newLeg.find('.ftt-to-airport-text'),
                $newLeg.find('.ftt-to-airport'),
                function() { self.checkFlightSuggestions(); }
            );

            // Explicitly set date values via jQuery (belt-and-suspenders, avoids HTML attribute quirks)
            if (data.depart_date) {
                $newLeg.find('[name*="[depart_date]"]').val(data.depart_date);
            }
            if (data.return_date) {
                $newLeg.find('[name*="[return_date]"]').val(data.return_date);
            }

            // Add remove handler
            $newLeg.find('.ftt-remove-block').on('click', function() {
                $(this).closest('.ftt-travel-leg').remove();
            });
            
            // Toggle flight-specific vs non-flight fields
            $newLeg.find('.ftt-leg-mode').on('change', function() {
                const isFlight = $(this).val() === 'fly';
                $newLeg.find('.ftt-flight-only').toggle(isFlight);
                $newLeg.find('.ftt-non-flight').toggle(!isFlight);
            }).trigger('change');
            
            // Toggle booking details
            $newLeg.find('.ftt-booked-checkbox').on('change', function() {
                $newLeg.find('.ftt-booking-details').toggle(this.checked);
            });
            


        },
        
        /**
         * Airport picker autocomplete
         * $textInput  — visible search field
         * $hiddenInput — hidden field storing the IATA code
         * onSelect    — optional callback after a selection
         */
        initAirportPicker: function($textInput, $hiddenInput, onSelect) {
            const self = this;
            const $list = $textInput.closest('.ftt-airport-picker').find('.ftt-airport-list');

            function buildList(query) {
                $list.empty();
                const q = (query || '').toLowerCase().trim();
                if (!q) { $list.hide(); return; }
                let count = 0;
                $.each(self.airportData, function(code, city) {
                    if (count >= 10) return false;
                    if (code.toLowerCase().indexOf(q) !== -1 || city.toLowerCase().indexOf(q) !== -1) {
                        $('<li>').attr({ 'data-code': code, role: 'option' })
                            .html('<strong>' + code + '</strong> &ndash; ' + city)
                            .appendTo($list);
                        count++;
                    }
                });
                $list.toggle(count > 0);
            }

            $textInput.on('input focus', function() {
                buildList($(this).val());
            });

            $list.on('mousedown', 'li', function(e) {
                e.preventDefault();
                const code = $(this).data('code');
                const city = self.airportData[code] || '';
                $hiddenInput.val(code).trigger('change');
                $textInput.val(city + ' (' + code + ')');
                $list.hide();
                if (typeof onSelect === 'function') onSelect(code);
            });

            $textInput.on('blur', function() {
                // If the user typed a bare 3-letter IATA code and nothing was
                // selected from the dropdown, accept the raw value so it is
                // never silently dropped.  A mousedown selection has already
                // written the code to $hiddenInput, so that case is safe too.
                const raw = $(this).val().trim().toUpperCase();
                if (/^[A-Z]{3}$/.test(raw)) {
                    $hiddenInput.val(raw);
                    const city = self.airportData[raw] || '';
                    $textInput.val(city ? city + ' (' + raw + ')' : raw);
                } else if (raw === '') {
                    $hiddenInput.val('');
                }
                setTimeout(function() { $list.hide(); }, 150);
            });
        },

        /**
         * Check for flight link suggestions
         */
        checkFlightSuggestions: function() {
            const legs = [];
            $('.ftt-travel-leg').each(function() {
                const $leg = $(this);
                const index = $leg.data('index');
                const mode = $leg.find('[name*=\"[mode]\"]').val();
                if (mode === 'fly') {
                    legs.push({
                        index: index,
                        from: $leg.find('.ftt-from-airport').val(),
                        to: $leg.find('.ftt-to-airport').val(),
                        date: $leg.find('[name*=\"[depart_date]\"]').val()
                    });
                }
            });
            
            // Look for round-trip patterns (same airports reversed)
            const suggestions = [];
            for (let i = 0; i < legs.length; i++) {
                for (let j = i + 1; j < legs.length; j++) {
                    if (legs[i].from && legs[i].to && legs[j].from && legs[j].to) {
                        // Check if reversed
                        if (legs[i].from === legs[j].to && legs[i].to === legs[j].from) {
                            suggestions.push({
                                outbound: legs[i],
                                return: legs[j],
                                message: `✓ Legs ${legs[i].index + 1} and ${legs[j].index + 1} form a round-trip (${legs[i].from}↔${legs[j].from}). Round-trip pricing will be searched automatically.`
                            });
                        }
                    }
                }
            }
            
            // Display suggestions
            const $suggestionsContainer = $('#ftt-flight-suggestions');
            const $suggestionsList = $('#ftt-suggestions-list');
            
            if (suggestions.length > 0) {
                $suggestionsList.empty();
                suggestions.forEach(function(suggestion) {
                    $suggestionsList.append(`
                        <div class="ftt-suggestion" style="padding: 10px; margin: 5px 0; background: #edf7ed; border-left: 3px solid #4caf50; border-radius: 3px;">
                            ${suggestion.message}
                        </div>
                    `);
                });
                $suggestionsContainer.slideDown();
            } else {
                $suggestionsContainer.slideUp();
            }
        },
        
        /**
         * Collect time blocks from form
         */
        collectTimeBlocks: function() {
            const blocks = [];
            $('.ftt-time-block').each(function() {
                const $block = $(this);
                const index = $block.data('index');
                blocks.push({
                    block_type: $(`[name="time_blocks[${index}][block_type]"]`).val(),
                    title: $(`[name="time_blocks[${index}][title]"]`).val(),
                    start_datetime: $(`[name="time_blocks[${index}][start_datetime]"]`).val(),
                    end_datetime: $(`[name="time_blocks[${index}][end_datetime]"]`).val(),
                    notes: $(`[name="time_blocks[${index}][notes]"]`).val()
                });
            });
            return blocks;
        },
        
        /**
         * Collect travel legs from form
         */
        collectTravelLegs: function() {
            const legs = [];
            $('.ftt-travel-leg').each(function() {
                const $leg = $(this);
                const index = $leg.data('index');
                
                // Collect baggage
                const baggage = [];
                $(`[name="travel_legs[${index}][baggage][]"]:checked`).each(function() {
                    baggage.push($(this).val());
                });
                
                const leg = {
                    leg_name: $(`[name="travel_legs[${index}][leg_name]"]`).val(),
                    mode: $(`[name="travel_legs[${index}][mode]"]`).val(),
                    depart_location: $(`[name="travel_legs[${index}][depart_location]"]`).val(),
                    depart_airport: $(`[name="travel_legs[${index}][depart_airport]"]`).val(),
                    arrive_location: $(`[name="travel_legs[${index}][arrive_location]"]`).val(),
                    arrive_airport: $(`[name="travel_legs[${index}][arrive_airport]"]`).val(),
                    depart_date: $(`[name="travel_legs[${index}][depart_date]"]`).val(),
                    depart_time_of_day: $(`[name="travel_legs[${index}][depart_time_of_day]"]`).val(),
                    arrive_date: $(`[name="travel_legs[${index}][arrive_date]"]`).val(),
                    arrive_time_of_day: $(`[name="travel_legs[${index}][arrive_time_of_day]"]`).val(),
                    depart_datetime: $(`[name="travel_legs[${index}][depart_datetime]"]`).val(),
                    arrive_datetime: $(`[name="travel_legs[${index}][arrive_datetime]"]`).val(),
                    airline: $(`[name="travel_legs[${index}][airline]"]`).val(),
                    flight_number: $(`[name="travel_legs[${index}][flight_number]"]`).val(),
                    booked: $(`[name="travel_legs[${index}][booked]"]`).is(':checked'),
                    confirmation: $(`[name="travel_legs[${index}][confirmation]"]`).val(),
                    baggage: baggage,
                    pickup_plan: $(`[name="travel_legs[${index}][pickup_plan]"]`).val(),
                    notes: $(`[name="travel_legs[${index}][notes]"]`).val()
                };
                
                console.log('Collecting travel leg', index, ':', leg);
                console.log('  - depart_airport field exists:', $(`[name="travel_legs[${index}][depart_airport]"]`).length);
                console.log('  - depart_airport is visible:', $(`[name="travel_legs[${index}][depart_airport]"]`).is(':visible'));
                console.log('  - depart_airport value:', leg.depart_airport);
                console.log('  - arrive_airport value:', leg.arrive_airport);
                
                legs.push(leg);
            });
            
            console.log('Final collected travel legs:', legs);
            return legs;
        },
        
        /**
         * Load dashboard data
         */
        loadDashboardData: function() {
            // Load user preferences
            if (document.getElementById('ftt-user-preferences-form')) {
                this.loadUserPreferences();
            }
            
            // Load price alerts
            if (document.getElementById('ftt-user-alerts')) {
                this.loadUserAlerts();
            }
            
            // Load linked flights
            if (document.getElementById('ftt-linked-flights')) {
                this.loadLinkedFlights();
            }
            
            // Build URL with optional group_id parameter (v2.1)
            let dashboardUrl = fttData.restUrl + 'dashboard';
            if (typeof fttSelectedGroupId !== 'undefined' && fttSelectedGroupId) {
                dashboardUrl += '?group_id=' + fttSelectedGroupId;
                console.log('Loading dashboard for group ID:', fttSelectedGroupId);
            }
            
            fetch(dashboardUrl, {
                headers: {
                    'X-WP-Nonce': fttData.nonce
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Dashboard data received:', data);
                console.log('=== DEBUG INFO ===');
                console.log('Debug data:', data.debug);
                console.log('==================');
                this.renderDashboardSection('flights-needed', data.flights_needed || []);
                this.renderDashboardSection('upcoming-travel', data.upcoming_travel || []);
                
                // Load price alerts for members
                if ($('#ftt-user-alerts').length) {
                    this.loadUserAlerts();
                }
            })
            .catch(error => {
                console.error('Error loading dashboard:', error);
                // Clear loading spinners on error
                $('#ftt-flights-needed').html('<p class="ftt-error">Error loading data. Please refresh the page.</p>');
                $('#ftt-not-booked').html('<p class="ftt-error">Error loading data. Please refresh the page.</p>');
                $('#ftt-upcoming-travel').html('<p class="ftt-error">Error loading data. Please refresh the page.</p>');
            });
        },
        
        /**
         * Load user preferences
         */
        loadUserPreferences: function() {
            console.log('🔧 Loading user preferences...');
            
            $.ajax({
                url: fttData.restUrl + 'user-preferences',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(data) {
                    console.log('✅ Preferences loaded:', data);
                    $('#home_airport').val(data.home_airport || '');
                    $('#timezone').val(data.timezone || '');
                },
                error: function(xhr) {
                    console.error('❌ Failed to load user preferences:', xhr);
                }
            });
            
            // Handle form submission
            $('#ftt-user-preferences-form').on('submit', function(e) {
                e.preventDefault();
                
                const preferences = {
                    home_airport: $('#home_airport').val(),
                    timezone: $('#timezone').val()
                };
                
                console.log('💾 Saving preferences:', preferences);
                
                $.ajax({
                    url: fttData.restUrl + 'user-preferences',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                    },
                    data: JSON.stringify(preferences),
                    contentType: 'application/json',
                    success: function(response) {
                        console.log('✅ Preferences saved:', response);
                        $('#ftt-preferences-message')
                            .removeClass('error')
                            .addClass('success')
                            .text('✓ Preferences saved!')
                            .fadeIn()
                            .delay(3000)
                            .fadeOut();
                    },
                    error: function(xhr) {
                        console.error('❌ Failed to save preferences:', xhr);
                        $('#ftt-preferences-message')
                            .removeClass('success')
                            .addClass('error')
                            .text('✗ Failed to save preferences')
                            .fadeIn();
                    }
                });
            });
        },
        
        /**
         * Load user alerts
         */
        loadUserAlerts: function() {
            const container = $('#ftt-user-alerts');
            
            $.ajax({
                url: fttData.restUrl + 'my-alerts',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(alerts) {
                    if (alerts.length === 0) {
                        container.html('<p style="color: #666;">No active price alerts. Create alerts from event flight details.</p>');
                        return;
                    }
                    
                    let html = '<table class="widefat" style="width: 100%;"><thead><tr>';
                    html += '<th>Event</th><th>Route</th><th>Date</th><th>Alert Type</th><th>Status</th><th>Actions</th>';
                    html += '</tr></thead><tbody>';
                    
                    alerts.forEach(function(alert) {
                        const alertTypeLabel = {
                            'price_drop': 'Drop Below $' + (alert.threshold_price || '?'),
                            'percent_drop': 'Drop By ' + (alert.threshold_percent || '?') + '%',
                            'good_deal': 'Good Deal (15% below avg)',
                            'daily_digest': '📧 Daily Digest'
                        }[alert.alert_type] || alert.alert_type;
                        
                        const statusIcon = alert.is_active ? '✓ Active' : '✗ Inactive';
                        const statusColor = alert.is_active ? 'green' : '#999';
                        
                        html += '<tr>';
                        html += '<td>' + FTT.escapeHtml(alert.event_title) + '</td>';
                        html += '<td>' + FTT.escapeHtml(alert.route) + '</td>';
                        html += '<td>' + (alert.depart_date || 'Flexible') + '</td>';
                        html += '<td>' + alertTypeLabel + '</td>';
                        html += '<td style="color: ' + statusColor + ';">' + statusIcon + '</td>';
                        html += '<td><button class="button button-small ftt-delete-alert" data-alert-id="' + alert.id + '">Delete</button></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    container.html(html);
                    
                    // Attach delete handlers
                    $('.ftt-delete-alert').on('click', function() {
                        const alertId = $(this).data('alert-id');
                        if (confirm('Delete this price alert?')) {
                            FTT.deleteAlert(alertId);
                        }
                    });
                },
                error: function() {
                    container.html('<p style="color: red;">Failed to load price alerts.</p>');
                }
            });
        },
        
        /**
         * Delete alert
         */
        deleteAlert: function(alertId) {
            $.ajax({
                url: fttData.restUrl + 'price-alerts/' + alertId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function() {
                    // Reload alerts
                    FTT.loadUserAlerts();
                },
                error: function(xhr) {
                    alert('Failed to delete alert: ' + (xhr.responseJSON?.message || 'Unknown error'));
                }
            });
        },
        
        /**
         * Render dashboard section
         */
        renderDashboardSection: function(sectionId, events) {
            const container = $(`#ftt-${sectionId}`);
            container.empty();
            
            if (events.length === 0) {
                container.append('<p class="ftt-no-events">No events to display.</p>');
                return;
            }
            
            const list = $('<div class="ftt-dashboard-list"></div>');
            events.forEach(event => {
                const memberInfo = event.member_name ? `<div class="ftt-member-name">${this.escapeHtml(event.member_name)}</div>` : '';
                const item = $(`
                    <div class="ftt-dashboard-item" data-event-id="${event.id}">
                        ${memberInfo}
                        <h4>${this.escapeHtml(event.title)}</h4>
                        <p><strong>Date:</strong> ${this.formatDate(event.start_datetime)}</p>
                        <p><strong>Location:</strong> ${this.escapeHtml(event.location_name || 'TBD')}</p>
                        ${event.travel_legs && event.travel_legs.length > 0 ? '<p><strong>Travel Legs:</strong> ' + event.travel_legs.length + '</p>' : ''}
                    </div>
                `);
                
                item.on('click', () => {
                    this.showEventModal(event);
                });
                
                list.append(item);
            });
            
            container.append(list);
        },
        
        /**
         * Show event modal
         */
        showEventModal: function(event) {
            const modal = $('<div class="ftt-modal"><div class="ftt-modal-content"></div></div>');
            
            // Get pretty event type name
            const eventTypePretty = this.getEventTypePrettyName(event.event_type);
            
            let html = `
                <span class="ftt-modal-close">&times;</span>
                <h2>${this.escapeHtml(event.title)}</h2>
                <p><strong>Type:</strong> ${this.escapeHtml(eventTypePretty)}</p>
                <p><strong>Start:</strong> ${this.formatDate(event.start_datetime)}</p>
                <p><strong>End:</strong> ${this.formatDate(event.end_datetime)}</p>
                ${event.location_name ? '<p><strong>Location:</strong> ' + this.escapeHtml(event.location_name) + '</p>' : ''}
                ${event.location_address ? '<p><strong>Address:</strong> ' + this.escapeHtml(event.location_address) + '</p>' : ''}
                ${event.notes ? '<div><strong>Notes:</strong><br>' + this.escapeHtml(event.notes) + '</div>' : ''}
            `;
            
            if (event.time_blocks && event.time_blocks.length > 0) {
                html += '<h3>Time Blocks</h3><ul>';
                event.time_blocks.forEach(block => {
                    html += `<li><strong>${this.escapeHtml(block.title)}</strong> (${block.block_type})<br>${this.formatDate(block.start_datetime)} - ${this.formatDate(block.end_datetime)}</li>`;
                });
                html += '</ul>';
            }
            
            if (event.travel_legs && event.travel_legs.length > 0) {
                html += '<h3>Travel</h3>';
                html += '<p class="ftt-help-text" style="font-size: 13px; color: #666; margin-bottom: 15px;">💡 Click flight search buttons below to compare prices and set up price alerts</p>';
                event.travel_legs.forEach((leg, legIndex) => {
                    // Backward compatibility: handle both old datetime and new date fields
                    let departInfo = 'TBD';
                    let arriveInfo = 'TBD';
                    let departDate = leg.depart_date;
                    
                    if (leg.depart_date) {
                        departInfo = `${leg.depart_date}${leg.depart_time_of_day ? ' (' + leg.depart_time_of_day + ')' : ''}`;
                    } else if (leg.depart_datetime) {
                        // Old format - extract date
                        departInfo = this.formatDate(leg.depart_datetime);
                        departDate = leg.depart_datetime.split('T')[0];
                    }
                    
                    if (leg.arrive_date) {
                        arriveInfo = `${leg.arrive_date}${leg.arrive_time_of_day ? ' (' + leg.arrive_time_of_day + ')' : ''}`;
                    } else if (leg.arrive_datetime) {
                        // Old format
                        arriveInfo = this.formatDate(leg.arrive_datetime);
                    }
                    
                    // Show flight search if it's a flight and not booked
                    const isFlight = leg.mode === 'fly';
                    const hasAirports = leg.depart_airport && leg.arrive_airport;
                    const canSearch = !leg.booked && isFlight && hasAirports;
                    const needsAirports = !leg.booked && isFlight && !hasAirports;
                    
                    console.log('Flight leg debug:', {
                        legIndex,
                        mode: leg.mode,
                        depart_airport: leg.depart_airport,
                        arrive_airport: leg.arrive_airport,
                        booked: leg.booked,
                        canSearch,
                        needsAirports
                    });
                    
                    html += `
                        <div class="ftt-travel-leg-details">
                            <h4>${this.escapeHtml(leg.leg_name || 'Travel Leg')}</h4>
                            <p><strong>Mode:</strong> ${leg.mode}</p>
                            <p><strong>Depart:</strong> ${departInfo}</p>
                            <p><strong>From:</strong> ${leg.depart_airport ? leg.depart_airport : this.escapeHtml(leg.depart_location || 'TBD')}</p>
                            <p><strong>Arrive:</strong> ${arriveInfo}</p>
                            <p><strong>To:</strong> ${leg.arrive_airport ? leg.arrive_airport : this.escapeHtml(leg.arrive_location || 'TBD')}</p>
                            ${leg.airline ? '<p><strong>Airline:</strong> ' + this.escapeHtml(leg.airline) + ' ' + this.escapeHtml(leg.flight_number) + '</p>' : ''}
                            ${leg.booked ? '<p class="ftt-booked">✓ Booked</p>' : '<p class="ftt-not-booked">✗ Not Booked</p>'}
                            ${needsAirports ? '<div class="ftt-missing-airports" style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 4px;"><p style="margin: 0; color: #856404;"><strong>⚠️ Missing Airport Codes</strong><br>Add 3-letter airport codes (e.g., BDL, BWI) to search flights and track prices. <a href="' + (fttData.eventFormUrl ? fttData.eventFormUrl + (fttData.eventFormUrl.includes('?') ? '&' : '?') + 'event_id=' + event.id : '#') + '" style="color: #0073aa;">Edit Event</a></p></div>' : ''}
                            ${canSearch ? this.generateFlightSearchLinks(leg, event, departDate, legIndex) : ''}
                            ${canSearch ? '<div class="ftt-price-info" id="ftt-price-info-' + legIndex + '"></div>' : ''}
                        </div>
                    `;
                });
            }
            
            if (fttData.isAdmin) {
                const eventFormUrl = fttData.eventFormUrl || '';
                if (eventFormUrl) {
                    const separator = eventFormUrl.includes('?') ? '&' : '?';
                    html += `<p><a href="${eventFormUrl}${separator}event_id=${event.id}" class="button">Edit Event</a></p>`;
                }
            }
            
            modal.find('.ftt-modal-content').html(html);
            $('body').append(modal);
            modal.fadeIn();
            
            modal.find('.ftt-modal-close').on('click', function() {
                modal.fadeOut(function() {
                    modal.remove();
                });
            });
            
            modal.on('click', function(e) {
                if (e.target === modal[0]) {
                    modal.fadeOut(function() {
                        modal.remove();
                    });
                }
            });
            
            // Handle track price button
            modal.find('.ftt-track-price').on('click', function() {
                const $btn = $(this);
                const eventId = $btn.data('event-id');
                const legIndex = $btn.data('leg-index');
                const origin = $btn.data('origin');
                const destination = $btn.data('destination');
                const date = $btn.data('date');
                
                FTT.showPriceTrackingModal(eventId, legIndex, origin, destination, date);
            });
            
            // Handle check price now button
            modal.find('.ftt-check-price-now').on('click', function() {
                const $btn = $(this);
                const eventId = $btn.data('event-id');
                const legIndex = $btn.data('leg-index');
                
                FTT.checkPriceNow(eventId, legIndex, modal);
            });
            
            // Load price history for each flight leg
            modal.find('.ftt-check-price-now').each(function() {
                const eventId = $(this).data('event-id');
                const legIndex = $(this).data('leg-index');
                FTT.loadPriceHistory(eventId, legIndex, modal);
            });
        },
        
        /**
         * Format date
         */
        formatDate: function(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleString();
        },

        /**
         * Fill the event form from an AI parse-event response.
         * Populates every field it can map, then surfaces clarifications.
         *
         * @param {Object} res  The parsed response from POST /ai/parse-event
         */
        fillFormFromAI: function(res) {
            var self = this;
            var TOD_TIMES = { morning: '09:00', midday: '12:00', afternoon: '15:00', night: '20:00' };

            // ── Basic fields ──────────────────────────────────────────────
            if (res.title)            $('#event_title').val(res.title);
            if (res.notes)            $('#notes').val(res.notes);
            if (res.destination)      $('#location_name').val(res.destination);
            if (res.location_name)    $('#location_name').val(res.location_name);
            if (res.location_address) $('#location_address').val(res.location_address);

            // ── Dates ─────────────────────────────────────────────────────
            // Form uses either date-only (all_day) or datetime-local inputs.
            var allDay = $('#all_day').is(':checked');
            if (res.start_date) {
                $('#start_datetime').val(allDay ? res.start_date : res.start_date + 'T00:00');
            }
            if (res.end_date) {
                $('#end_datetime').val(allDay ? res.end_date : res.end_date + 'T23:59');
            }

            // ── Event type ────────────────────────────────────────────────
            if (res.event_type) {
                var et      = res.event_type;
                var etTypes = window.fttEventTypes || {};
                $('#event_type').val(et);
                $('#event_type_search').val(
                    etTypes[et] || et.replace(/_/g, ' ').replace(/\b\w/g, function(l){ return l.toUpperCase(); })
                );
            }

            // ── Member / traveler ─────────────────────────────────────────
            if ($('#ftt-member-checkboxes').length) {
                $('#ftt-family-event').prop('checked', false);
                $('.ftt-child-checkbox').prop('checked', false).prop('disabled', false);
                if (res.member_id) {
                    var $cb = $('.ftt-child-checkbox[value="' + res.member_id + '"]');
                    if ($cb.length) {
                        $cb.prop('checked', true);
                    }
                }
            } else if ($('#member_id').length && res.member_id) {
                $('#member_id').val(res.member_id);
            }

            // ── Travel legs ───────────────────────────────────────────────
            if (res.travel_legs && res.travel_legs.length > 0) {
                // Clear any existing legs first.
                $('#ftt-travel-legs-container').empty();
                res.travel_legs.forEach(function(leg) {
                    var legData = $.extend({}, leg);
                    self.addTravelLeg(legData);

                    // After the leg DOM is built, tick baggage checkboxes.
                    if (leg.baggage && leg.baggage.length > 0) {
                        var $leg = $('#ftt-travel-legs-container .ftt-travel-leg').last();
                        leg.baggage.forEach(function(b) {
                            $leg.find('input[type="checkbox"][value="' + b + '"]').prop('checked', true);
                        });
                    }
                });
                setTimeout(function(){ self.checkFlightSuggestions(); }, 300);
            }

            // ── Time blocks ───────────────────────────────────────────────
            if (res.time_blocks && res.time_blocks.length > 0) {
                $('#ftt-time-blocks-container').empty();
                res.time_blocks.forEach(function(block) {
                    self.addTimeBlock(block);
                });
            }

            // ── Show/hide the AI assistant panel ──────────────────────────
            // Keep it open so the user can see clarifications.

        },

        /**
         * Get pretty event type name
         */
        getEventTypePrettyName: function(eventType) {
            const typeMap = {
                'move_in': 'Move In',
                'move_out': 'Move Out',
                'camp_weekend': 'Camp Weekend',
                'rehearsal_block': 'Rehearsal Block',
                'travel_day': 'Travel Day',
                'performance_day': 'Performance Day',
                'housing_checkin': 'Housing Check-In',
                'medical': 'Medical',
                'uniform_fitting': 'Uniform Fitting',
                'admin_deadline': 'Admin Deadline',
                'other': 'Other'
            };
            return typeMap[eventType] || eventType.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
        },
        
        /**
         * Show price tracking modal
         */
        showPriceTrackingModal: function(eventId, legIndex, origin, destination, date) {
            const modal = $('<div class="ftt-modal"><div class="ftt-modal-content"></div></div>');
            
            const dateNote = date ? ` on ${date}` : ' (flexible dates)';
            
            const html = `
                <span class="ftt-modal-close">&times;</span>
                <h2>Track Flight Prices</h2>
                <p><strong>Route:</strong> ${origin} → ${destination}${dateNote}</p>
                
                <form id="ftt-price-alert-form">
                    <div class="ftt-form-field">
                        <label>Alert Type</label>
                        <select name="alert_type" id="alert_type" required>
                            <option value="">Select alert type...</option>
                            <option value="price_drop">Drop Below Price</option>
                            <option value="percent_drop">Drop By Percentage</option>
                            <option value="good_deal">Good Deal Alert (15% below average)</option>
                            <option value="daily_digest">📧 Daily Price Digest</option>
                        </select>
                    </div>
                    
                    <div class="ftt-form-field" id="threshold_price_field" style="display: none;">
                        <label>Target Price ($)</label>
                        <input type="number" name="threshold_price" id="threshold_price" min="0" step="0.01" placeholder="e.g., 250.00">
                        <p class="description">Alert me when price drops to this amount or below</p>
                    </div>
                    
                    <div class="ftt-form-field" id="threshold_percent_field" style="display: none;">
                        <label>Percentage Drop (%)</label>
                        <input type="number" name="threshold_percent" id="threshold_percent" min="1" max="100" placeholder="e.g., 20">
                        <p class="description">Alert me when price drops by this percentage</p>
                    </div>
                    
                    <div class="ftt-form-actions">
                        <button type="submit" class="button button-primary">Create Alert</button>
                        <button type="button" class="button ftt-modal-close">Cancel</button>
                    </div>
                </form>
                
                <div id="ftt-alert-message" style="display: none; margin-top: 15px;"></div>
            `;
            
            modal.find('.ftt-modal-content').html(html);
            $('body').append(modal);
            modal.fadeIn();
            
            // Show/hide threshold fields based on alert type
            modal.find('#alert_type').on('change', function() {
                const alertType = $(this).val();
                modal.find('#threshold_price_field, #threshold_percent_field').hide();
                
                if (alertType === 'price_drop') {
                    modal.find('#threshold_price_field').show();
                } else if (alertType === 'percent_drop') {
                    modal.find('#threshold_percent_field').show();
                }
                // good_deal and daily_digest don't need thresholds
            });
            
            // Handle form submission
            modal.find('#ftt-price-alert-form').on('submit', function(e) {
                e.preventDefault();
                
                const alertType = modal.find('#alert_type').val();
                const thresholdPrice = modal.find('#threshold_price').val();
                const thresholdPercent = modal.find('#threshold_percent').val();
                
                // Build payload - only include threshold values if they're provided
                const payload = {
                    event_id: eventId,
                    leg_index: legIndex,
                    alert_type: alertType
                };
                
                if (thresholdPrice) {
                    payload.threshold_price = parseFloat(thresholdPrice);
                }
                if (thresholdPercent) {
                    payload.threshold_percent = parseInt(thresholdPercent);
                }
                
                // Create alert via REST API
                $.ajax({
                    url: fttData.restUrl + 'price-alerts',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                    },
                    data: JSON.stringify(payload),
                    contentType: 'application/json',
                    success: function(response) {
                        const subjectLine = response.email_subject ? 
                            `<p style="color: #047857; margin: 10px 0 0 0; font-size: 12px; background: #ecfdf5; padding: 8px; border-radius: 4px;">
                                🔍 <strong>Subject:</strong> <code style="background: white; padding: 2px 6px; border-radius: 3px; font-size: 11px;">${response.email_subject}</code>
                            </p>` : '';
                        
                        const message = `
                            <div style="background: #d1fae5; border: 2px solid #10b981; padding: 15px; border-radius: 6px;">
                                <p style="color: #065f46; margin: 0 0 10px 0; font-weight: 600;">
                                    ✓ Price Alert Created Successfully!
                                </p>
                                <p style="color: #065f46; margin: 0 0 10px 0; font-size: 14px;">
                                    📧 <strong>Check your email</strong> for a confirmation message.
                                </p>
                                <p style="color: #047857; margin: 0; font-size: 13px;">
                                    <em>Important:</em> Make sure to add us to your safe senders list to ensure you receive price alerts!
                                </p>
                                ${subjectLine}
                            </div>
                        `;
                        modal.find('#ftt-alert-message').html(message).show();
                        
                        setTimeout(function() {
                            modal.fadeOut(function() {
                                modal.remove();
                            });
                        }, 5000); // Increased to 5 seconds so they can read the message
                    },
                    error: function(xhr) {
                        let errorMsg = 'Failed to create alert. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        modal.find('#ftt-alert-message')
                            .html('<p style="color: red;">✗ ' + errorMsg + '</p>')
                            .show();
                    }
                });
            });
            
            // Close handlers
            modal.find('.ftt-modal-close').on('click', function() {
                modal.fadeOut(function() {
                    modal.remove();
                });
            });
            
            modal.on('click', function(e) {
                if (e.target === modal[0]) {
                    modal.fadeOut(function() {
                        modal.remove();
                    });
                }
            });
        },
        
        /**
         * Escape HTML
         */
        escapeHtml: function(text) {
            if (!text) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.toString().replace(/[&<>"']/g, m => map[m]);
        },
        
        /**
         * Initialize Mapbox Autocomplete
         */
        initMapboxAutocomplete: function() {
            const locationInput = document.getElementById('location_name');
            if (!locationInput) return;
            
            const provider = fttData.geocodingProvider || 'none';
            
            if (provider === 'none') {
                console.log('Address autocomplete disabled.');
                return;
            }
            
            if (provider === 'google' && fttData.googlePlacesApiKey) {
                this.initGooglePlacesAutocomplete(locationInput);
                return;
            }
            
            if (provider === 'mapbox' && fttData.mapboxApiKey) {
                this.initMapboxSearch(locationInput);
                return;
            }
            
            console.log('Geocoding provider configured but API key missing.');
        },
        
        /**
         * Initialize Mapbox Search
         */
        initMapboxSearch: function(locationInput) {
            let debounceTimer;
            let resultsContainer;
            
            // Create results container with proper positioning
            resultsContainer = $('<div class="ftt-autocomplete-results"></div>');
            $(locationInput).parent().css('position', 'relative');
            $(locationInput).after(resultsContainer);
            
            // Handle input
            $(locationInput).on('input', function() {
                const query = this.value.trim();
                
                clearTimeout(debounceTimer);
                
                if (query.length < 3) {
                    resultsContainer.hide().empty();
                    return;
                }
                
                debounceTimer = setTimeout(() => {
                    FTT.searchMapboxPlaces(query, resultsContainer);
                }, 300);
            });
            
            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#location_name, .ftt-autocomplete-results').length) {
                    resultsContainer.hide();
                }
            });
        },
        
        /**
         * Initialize Google Places Autocomplete
         */
        initGooglePlacesAutocomplete: function(locationInput) {
            // Load Google Places library
            if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                const script = document.createElement('script');
                script.src = `https://maps.googleapis.com/maps/api/js?key=${fttData.googlePlacesApiKey}&libraries=places&callback=initGoogleAutocomplete`;
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
                
                window.initGoogleAutocomplete = () => {
                    FTT.setupGoogleAutocomplete(locationInput);
                };
            } else {
                this.setupGoogleAutocomplete(locationInput);
            }
        },
        
        /**
         * Setup Google Autocomplete
         */
        setupGoogleAutocomplete: function(locationInput) {
            const autocomplete = new google.maps.places.Autocomplete(locationInput, {
                types: ['establishment'],
                fields: ['name', 'formatted_address', 'geometry', 'place_id']
            });
            
            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                
                if (!place.geometry) {
                    console.log('No details available for:', place.name);
                    return;
                }
                
                // Set location name
                $('#location_name').val(place.name || '');
                
                // Set address
                $('#location_address').val(place.formatted_address || '');
                
                // Set coordinates (remove existing first)
                $('#location_latitude, #location_longitude').remove();
                
                if (place.geometry.location) {
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('id', 'location_latitude')
                        .attr('name', 'location_latitude')
                        .val(place.geometry.location.lat())
                        .appendTo('#ftt-event-form');
                    
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('id', 'location_longitude')
                        .attr('name', 'location_longitude')
                        .val(place.geometry.location.lng())
                        .appendTo('#ftt-event-form');
                }

                // Track usage for billing/cost monitoring
                if ( fttData && fttData.restUrl && fttData.nonce ) {
                    $.ajax({
                        url: fttData.restUrl + 'track-api-call',
                        method: 'POST',
                        headers: { 'X-WP-Nonce': fttData.nonce },
                        contentType: 'application/json',
                        data: JSON.stringify({ api: 'google_places', success: true })
                    });
                }
            });
        },
        
        /**
         * Search Mapbox places
         */
        searchMapboxPlaces: function(query, resultsContainer) {
            const apiKey = fttData.mapboxApiKey;
            // Remove type restrictions to allow searching for any location (schools, venues, etc.)
            const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${apiKey}&limit=8`;
            
            console.log('Searching Mapbox for:', query);
            
            $.ajax({
                url: url,
                method: 'GET',
                success: function(data) {
                    console.log('Mapbox results:', data.features.length, 'found');
                    FTT.displayMapboxResults(data.features, resultsContainer);
                },
                error: function(xhr, status, error) {
                    console.error('Mapbox API error:', error, xhr.responseText);
                    resultsContainer.html('<div class="ftt-autocomplete-item" style="color: #dc3232;">Search error. Check API key.</div>').show();
                }
            });
        },
        
        /**
         * Display Mapbox results
         */
        displayMapboxResults: function(features, resultsContainer) {
            resultsContainer.empty();
            
            if (!features || features.length === 0) {
                resultsContainer.hide();
                return;
            }
            
            features.forEach(feature => {
                const item = $('<div class="ftt-autocomplete-item"></div>');
                item.text(feature.place_name);
                
                item.on('click', function() {
                    // Set location name
                    $('#location_name').val(feature.text || feature.place_name);
                    
                    // Set address
                    $('#location_address').val(feature.place_name);
                    
                    // Set coordinates (remove existing first)
                    if (feature.geometry && feature.geometry.coordinates) {
                        $('#location_latitude, #location_longitude').remove();
                        
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('id', 'location_longitude')
                            .attr('name', 'location_longitude')
                            .val(feature.geometry.coordinates[0])
                            .appendTo('#ftt-event-form');
                        
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('id', 'location_latitude')
                            .attr('name', 'location_latitude')
                            .val(feature.geometry.coordinates[1])
                            .appendTo('#ftt-event-form');
                    }
                    
                    resultsContainer.hide();
                });
                
                resultsContainer.append(item);
            });
            
            resultsContainer.show();
        },
        
        /**
         * Generate flight search links for unbooked flights
         */
        generateFlightSearchLinks: function(leg, event, departDate, legIndex) {
            // Use provided departDate or fall back to leg data
            const date = departDate || leg.depart_date || '';
            const origin = leg.depart_airport || '';
            const destination = leg.arrive_airport || '';
            
            console.log('generateFlightSearchLinks called:', {
                legIndex,
                date,
                origin,
                destination,
                leg
            });
            
            if (!origin || !destination) {
                console.warn('Missing airport codes - cannot generate links');
                return '';
            }
            
            // Count baggage pieces (checked bags + instruments + equipment)
            let baggageCount = 0;
            let baggageNote = '';
            if (leg.baggage && leg.baggage.length > 0) {
                const checkedBags = leg.baggage.filter(b => b === 'checked' || b === 'instrument' || b === 'color_guard_equipment' || b === 'oversize').length;
                baggageCount = checkedBags;
                
                // Build descriptive note
                const types = [];
                if (leg.baggage.includes('instrument')) types.push('instrument');
                if (leg.baggage.includes('color_guard_equipment')) types.push('color guard equip');
                if (leg.baggage.includes('oversize')) types.push('oversize');
                if (types.length > 0) {
                    baggageNote = ` (includes ${types.join(', ')})`;
                }
            }
            
            let googleFlightsUrl, kayakUrl, southwestUrl;
            
            if (date) {
                // Convert date to MM/DD/YYYY format for Southwest
                let southwestDate = date;
                if (date.match(/^\d{4}-\d{2}-\d{2}$/)) {
                    // Convert YYYY-MM-DD to MM/DD/YYYY
                    const parts = date.split('-');
                    southwestDate = `${parts[1]}/${parts[2]}/${parts[0]}`;
                }
                
                // With specific date and baggage
                googleFlightsUrl = `https://www.google.com/travel/flights?q=flights%20from%20${origin}%20to%20${destination}%20on%20${date}`;
                kayakUrl = `https://www.kayak.com/flights/${origin}-${destination}/${date}?sort=bestflight_a&fs=bfc=${baggageCount}`;
                southwestUrl = `https://www.southwest.com/air/booking/select-depart.html?adultsCount=1&adultPassengersCount=1&destinationAirportCode=${destination}&departureDate=${southwestDate}&departureTimeOfDay=ALL_DAY&fareType=USD&int=HOMEQBOMAIR&originationAirportCode=${origin}&passengerType=ADULT&tripType=oneway`;
            } else {
                // Without specific date - flexible search
                googleFlightsUrl = `https://www.google.com/travel/flights?q=flights%20from%20${origin}%20to%20${destination}`;
                kayakUrl = `https://www.kayak.com/flights/${origin}-${destination}?sort=bestflight_a&fs=bfc=${baggageCount}`;
                southwestUrl = `https://www.southwest.com/air/booking/select-depart.html?adultsCount=1&adultPassengersCount=1&destinationAirportCode=${destination}&originationAirportCode=${origin}&passengerType=ADULT&tripType=oneway`;
            }
            
            let baggageInfoHtml = '';
            if (baggageCount > 0) {
                baggageInfoHtml = `<p class="ftt-baggage-note" style="font-size: 12px; color: #666; margin: 5px 0;">Baggage: ${baggageCount} checked bag(s)${baggageNote}</p>`;
            }
            
            return `
                <div class="ftt-flight-search-links">
                    <p><strong>Search Flights:</strong></p>
                    ${baggageInfoHtml}
                    <div class="ftt-flight-buttons">
                        <a href="${googleFlightsUrl}" target="_blank" rel="noopener" class="button button-small">Google Flights</a>
                        <a href="${kayakUrl}" target="_blank" rel="noopener" class="button button-small">Kayak</a>
                        <a href="${southwestUrl}" target="_blank" rel="noopener" class="button button-small">Southwest</a>
                        <button class="button button-small button-primary ftt-check-price-now" data-event-id="${event.id}" data-leg-index="${legIndex}">
                            💰 Check Price Now
                        </button>
                        <button class="button button-small button-secondary ftt-track-price" data-event-id="${event.id}" data-leg-index="${legIndex}" data-origin="${origin}" data-destination="${destination}" data-date="${date || ''}">
                            <span class="dashicons dashicons-bell" style="font-size: 14px;"></span> Track Price
                        </button>
                    </div>
                    <div class="ftt-price-info" id="ftt-price-info-${legIndex}"></div>
                </div>
            `;
        },
        
        /**
         * Check price now
         */
        checkPriceNow: function(eventId, legIndex, modal) {
            const $container = modal.find('#ftt-price-info-' + legIndex);
            $container.html('<p style="color: #666;">⏳ Checking current prices...</p>');
            
            const requestData = {
                event_id: eventId,
                leg_index: legIndex
            };
            
            console.log('🔍 SRT Price Check Request:', requestData);
            
            $.ajax({
                url: fttData.restUrl + 'check-price',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                data: JSON.stringify(requestData),
                contentType: 'application/json',
                success: function(response) {
                    console.log('✅ SRT Price Check Response:', response);
                    
                    // Log detailed debug info if available
                    if (response.debug) {
                        console.group('🔍 SerpAPI Debug Info');
                        console.log('Request:', response.debug.request);
                        console.log('Response:', response.debug.response);
                        if (response.debug.serpapi_error) {
                            console.error('SerpAPI Error:', response.debug.serpapi_error);
                        }
                        console.groupEnd();
                    }
                    
                    // Handle "no flights found" case
                    if (response.success === false || !response.price) {
                        let html = '<div style="background: #fef2f2; border: 1px solid #ef4444; padding: 10px; margin: 10px 0; border-radius: 4px;">';
                        html += '<p style="color: #991b1b; margin: 0 0 10px;"><strong>✗ No flights found</strong></p>';
                        
                        if (response.message) {
                            html += '<p style="color: #666; font-size: 0.9em; margin: 0 0 10px;">' + response.message + '</p>';
                        }
                        
                        if (response.suggestions && response.suggestions.length) {
                            html += '<p style="color: #666; font-size: 0.9em; margin: 0;"><strong>Try:</strong></p><ul style="margin: 5px 0 0 20px; color: #666; font-size: 0.9em;">';
                            response.suggestions.forEach(function(suggestion) {
                                html += '<li>' + suggestion + '</li>';
                            });
                            html += '</ul>';
                        }
                        
                        html += '</div>';
                        $container.html(html);
                        return;
                    }
                    
                    const price = parseFloat(response.price);

                    if (!price || isNaN(price)) {
                        $container.html('<p style="color: red;">✗ Unable to parse price data</p>');
                        return;
                    }
                    
                    console.log('🔗 Google Flights URL:', response.google_flights_url);
                    console.log('🎫 Trip Type:', response.trip_type);

                    // Stash trip_type and Google Flights URL on the container so
                    // renderPriceHistory can include them without a second render.
                    $container
                        .data('ftt-trip-type', response.trip_type || '')
                        .data('ftt-flights-url', response.google_flights_url || '');

                    // Show a brief loading state, then let renderPriceHistory do
                    // the single authoritative render (avoids duplicating the
                    // Insights + price cards that renderPriceHistory also draws).
                    $container.html('<div style="padding: 16px; text-align: center; color: #666;">🔄 Loading updated price…</div>');
                    FTT.loadPriceHistory(eventId, legIndex, modal);
                },
                error: function(xhr) {
                    console.error('❌ SRT Price Check Error:', {
                        status: xhr.status,
                        statusText: xhr.statusText,
                        response: xhr.responseJSON || xhr.responseText
                    });
                    
                    let errorMsg = 'Unable to fetch price';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    $container.html('<p style="color: red;">✗ ' + errorMsg + '</p>');
                }
            });
        },
        
        /**
         * Load price history
         */
        loadPriceHistory: function(eventId, legIndex, modal) {
            console.log('🔍 Loading price history:', { eventId, legIndex });
            
            $.ajax({
                url: fttData.restUrl + 'price-history',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                data: {
                    event_id: eventId,
                    leg_index: legIndex
                },
                success: function(response) {
                    console.log('📊 Price history response:', response);
                    if (response.prices && response.prices.length > 0) {
                        // Check if data is older than 4 hours
                        const latestPrice = response.prices[response.prices.length - 1];
                        const checkedAt = new Date(latestPrice.checked_at);
                        const now = new Date();
                        const hoursSinceCheck = (now - checkedAt) / (1000 * 60 * 60);
                        
                        console.log('⏰ Data freshness:', {
                            checkedAt: checkedAt.toLocaleString(),
                            hoursSinceCheck: hoursSinceCheck.toFixed(1),
                            needsRefresh: hoursSinceCheck > 4
                        });
                        
                        // If data is older than 4 hours, trigger auto-refresh
                        if (hoursSinceCheck > 4) {
                            console.log('🔄 Data is stale (>4 hours), auto-refreshing...');
                            const $button = modal.find('#ftt-check-price-' + legIndex);
                            if ($button.length) {
                                // Show message that we're auto-updating
                                const $container = modal.find('#ftt-price-info-' + legIndex);
                                $container.html('<div style="padding: 20px; text-align: center; color: #666;"><div style="margin-bottom: 10px;">🔄 Refreshing price data...</div><div style="font-size: 12px;">Last checked ' + hoursSinceCheck.toFixed(1) + ' hours ago</div></div>');
                                
                                // Trigger price check
                                setTimeout(function() {
                                    $button.click();
                                }, 500);
                                return;
                            }
                        }
                        
                        FTT.renderPriceHistory(eventId, legIndex, response, modal);
                    } else {
                        console.warn('⚠️ No price history found for event:', eventId, 'leg:', legIndex);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ Price history load error:', { xhr, status, error });
                }
            });
        },
        
        /**
         * Render price history
         */
        renderPriceHistory: function(eventId, legIndex, data, modal) {
            const $container = modal.find('#ftt-price-info-' + legIndex);
            const stats = data.stats;
            
            if (!stats || !data.prices.length) {
                return;
            }
            
            // Ensure all stats values are numbers
            const current = parseFloat(stats.current);
            const avg = parseFloat(stats.avg);
            const min = parseFloat(stats.min);
            const max = parseFloat(stats.max);
            const change = parseFloat(stats.change);
            const changePercent = parseFloat(stats.change_percent);
            
            if (isNaN(current) || isNaN(avg)) {
                return; // Invalid data
            }
            
            const trendIcon = stats.trend === 'down' ? '📉' : (stats.trend === 'up' ? '📈' : '➡️');
            const trendColor = stats.trend === 'down' ? 'green' : (stats.trend === 'up' ? 'red' : '#666');
            const changeSign = change >= 0 ? '+' : '';
            
            let html = '';
            
            // Get the latest price record to show checked_at time
            const latestPrice = data.prices[data.prices.length - 1];
            const checked = latestPrice ? new Date(latestPrice.checked_at).toLocaleString() : '';
            
            // Google Flights Price Insights (if available from latest check)
            if (data.google_insights) {
                const insights = data.google_insights;
                let priceLevel = null; // Declare at block scope so it's available to all nested ifs
                
                html += '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 4px; padding: 12px; margin: 10px 0;">';
                html += '<div style="display: flex; align-items: center; margin-bottom: 8px;">';
                html += '<svg style="width: 20px; height: 20px; margin-right: 8px; fill: white;" viewBox="0 0 24 24"><path d="M21,16V14L13,9V3.5A1.5,1.5 0 0,0 11.5,2A1.5,1.5 0 0,0 10,3.5V9L2,14V16L10,13.5V19L8,20.5V22L11.5,21L15,22V20.5L13,19V13.5L21,16Z" /></svg>';
                html += '<strong style="font-size: 0.95em;">Google Flights Price Insights</strong>';
                html += '</div>';
                
                if (insights.price_level) {
                    priceLevel = insights.price_level.toLowerCase();
                    let levelIcon = priceLevel === 'low' ? '💚' : (priceLevel === 'high' ? '🔴' : '🟡');
                    let levelText = priceLevel.charAt(0).toUpperCase() + priceLevel.slice(1);
                    html += `<div style="font-size: 1.15em; margin-bottom: 6px;">${levelIcon} <strong>Price is ${levelText}</strong></div>`;
                }
                
                if (insights.typical_price_range) {
                    let range = insights.typical_price_range;
                    let low = range[0];
                    let high = range[1];
                    html += `<div style="font-size: 0.9em; opacity: 0.95; margin-bottom: 4px;">`;
                    html += `Typical range: $${low}–${high}`;
                    
                    // Show how much cheaper if price_level is low
                    if (priceLevel === 'low' && high > current) {
                        let savings = Math.round(high - current);
                        if (savings > 0) {
                            html += ` <span style="background: rgba(255,255,255,0.25); padding: 2px 6px; border-radius: 3px; margin-left: 6px;">~$${savings} cheaper</span>`;
                        }
                    }
                    html += '</div>';
                }
                
                if (insights.lowest_price) {
                    html += `<div style="font-size: 0.85em; opacity: 0.9;">Lowest found: $${insights.lowest_price}</div>`;
                }
                
                html += '</div>';
            }
            
            // Pick up trip_type / verify URL stashed by the "Check Price Now" handler.
            const fttTripType    = $container.data('ftt-trip-type')  || '';
            const fttFlightsUrl  = $container.data('ftt-flights-url') || '';

            // Current price box (latest from history)
            html += '<div style="background: #f0f9ff; border: 1px solid #0891b2; padding: 10px; margin: 10px 0; border-radius: 4px;">';
            html += '<strong style="color: #0891b2; font-size: 1.2em;">$' + current.toFixed(2) + '</strong> ';
            html += '<span style="color: #666; font-size: 0.9em;">as of ' + checked + '</span>';
            if (fttTripType) {
                html += '<span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin-left: 10px;">' + fttTripType + '</span>';
            }
            if (fttFlightsUrl) {
                html += '<div style="margin-top: 8px;"><a href="' + fttFlightsUrl + '" target="_blank" style="color: #0891b2; font-size: 0.9em; text-decoration: underline;">🔍 Verify on Google Flights →</a></div>';
            }
            html += '</div>';
            
            // Our tracked price history
            html += `
                <div style="background: #f9fafb; border: 1px solid #e5e7eb; padding: 10px; margin: 10px 0; border-radius: 4px;">
                    <h4 style="margin: 0 0 10px 0;">Price History (${stats.count} checks)</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                        <div>
                            <div style="font-size: 0.8em; color: #666;">Current</div>
                            <div style="font-size: 1.1em; font-weight: bold;">$${current.toFixed(2)}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.8em; color: #666;">Average</div>
                            <div style="font-size: 1.1em;">$${avg.toFixed(2)}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.8em; color: #666;">Trend</div>
                            <div style="font-size: 1.1em; color: ${trendColor};">
                                ${trendIcon} ${changeSign}$${Math.abs(change).toFixed(2)} (${changeSign}${changePercent.toFixed(1)}%)
                            </div>
                        </div>
                    </div>
                    <div style="font-size: 0.85em; color: #666; border-top: 1px solid #e5e7eb; padding-top: 8px;">
                        <strong>Range:</strong> $${min.toFixed(2)} - $${max.toFixed(2)}
                    </div>
            `;
            
            // Add mini chart
            if (data.prices.length > 1) {
                html += '<div style="margin-top: 10px;">' + FTT.renderMiniChart(data.prices, stats) + '</div>';
            }
            
            html += '</div>';
            
            // Always replace the container's entire content so we never
            // accumulate duplicate insight/price blocks from successive renders.
            $container.html('<div class="ftt-price-history">' + html + '</div>');
        },
        
        /**
         * Render mini chart
         */
        renderMiniChart: function(prices, stats) {
            const maxPrice = parseFloat(stats.max);
            const minPrice = parseFloat(stats.min);
            const avg = parseFloat(stats.avg);
            const range = maxPrice - minPrice || 1;
            
            let html = '<div style="display: flex; align-items: flex-end; height: 60px; gap: 2px;">';
            
            prices.forEach((item, index) => {
                const price = parseFloat(item.price);
                if (isNaN(price)) return;
                
                const height = ((price - minPrice) / range) * 50 + 10;
                const color = price < avg ? '#10b981' : (price > avg ? '#ef4444' : '#6b7280');
                const isLast = index === prices.length - 1;
                
                html += `
                    <div style="flex: 1; background: ${color}; height: ${height}px; 
                        ${isLast ? 'border: 2px solid #0891b2;' : ''}" 
                        title="$${price.toFixed(2)} on ${new Date(item.checked_at).toLocaleDateString()}">
                    </div>
                `;
            });
            
            html += '</div>';
            html += '<div style="font-size: 0.75em; color: #666; margin-top: 5px; text-align: center;">Price trend over time (green = below avg, red = above avg)</div>';
            
            return html;
        },
        
        /**
         * Load linked flights with price comparison
         */
        loadLinkedFlights: function() {
            const self = this;
            
            $.ajax({
                url: fttData.restUrl + 'flight-groups',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(groups) {
                    self.renderLinkedFlights(groups);
                },
                error: function(xhr) {
                    console.error('Failed to load linked flights:', xhr);
                    $('#ftt-linked-flights').html('<p>Unable to load flight groups.</p>');
                }
            });
        },
        
        /**
         * Render linked flights with pricing comparison
         */
        renderLinkedFlights: function(groups) {
            const $container = $('#ftt-linked-flights');
            
            if (!groups || groups.length === 0) {
                $container.html('<p>No linked flights yet. Link outbound and return flights to compare pricing!</p>');
                return;
            }
            
            let html = '';
            
            groups.forEach(group => {
                const legs = group.legs || [];
                if (legs.length < 2) return;
                
                // Get pricing data
                const pricing = group.pricing || {};
                const individualTotal = pricing.total_individual || 0;
                const roundTripPrice = pricing.round_trip_price || 0;
                const savings = pricing.savings || 0;
                const bestOption = pricing.best_option || 'individual';
                
                html += `
                    <div class="ftt-linked-flight-group">
                        <div class="ftt-linked-flight-header">
                            <div class="ftt-linked-flight-title">
                                ${legs[0].leg.depart_airport} ↔️ ${legs[0].leg.arrive_airport}
                                <span class="ftt-flight-group-badge">Group ${group.group_id.substring(3, 8)}</span>
                            </div>
                        </div>
                        
                        <div class="ftt-flight-legs">
                            ${legs.map((legData, idx) => `
                                <div class="ftt-flight-leg-badge">
                                    ${idx === 0 ? '✈️ Outbound' : '🔄 Return'}: ${legData.leg.depart_date}
                                    <small>(${FTT.escapeHtml(legData.event_title)})</small>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="ftt-price-comparison">
                            <div class="ftt-price-row">
                                <span class="ftt-price-label">Individual One-Ways:</span>
                                <span class="ftt-price-value">
                                    $${individualTotal.toFixed(2)}
                                    ${bestOption === 'individual' ? '<span class="ftt-price-better">✓ Best Deal</span>' : ''}
                                </span>
                            </div>
                            ${roundTripPrice > 0 ? `
                                <div class="ftt-price-row">
                                    <span class="ftt-price-label">Round-Trip:</span>
                                    <span class="ftt-price-value">
                                        $${roundTripPrice.toFixed(2)}
                                        ${bestOption === 'roundtrip' ? '<span class="ftt-price-better">✓ Best Deal</span>' : ''}
                                    </span>
                                </div>
                            ` : ''}
                            ${savings > 0 ? `
                                <div class="ftt-price-row">
                                    <span class="ftt-price-label">Potential Savings:</span>
                                    <span class="ftt-price-value ftt-price-savings">
                                        💰 $${savings.toFixed(2)}
                                    </span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <button class="ftt-unlink-flight-btn" data-group-id="${group.group_id}">
                            Unlink Flights
                        </button>
                    </div>
                `;
            });
            
            $container.html(html);
            
            // Attach unlink handlers
            $container.find('.ftt-unlink-flight-btn').on('click', function() {
                const groupId = $(this).data('group-id');
                if (confirm('Are you sure you want to unlink these flights?')) {
                    FTT.unlinkFlightGroup(groupId);
                }
            });
        },
        
        /**
         * Link two flights together
         */
        linkFlights: function(eventIds, legIndices) {
            const self = this;
            
            $.ajax({
                url: fttData.restUrl + 'link-flights',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    event_ids: eventIds,
                    leg_indices: legIndices
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(response) {
                    console.log('Flights linked:', response);
                    alert('Flights linked successfully! Refreshing data...');
                    self.loadLinkedFlights();
                },
                error: function(xhr) {
                    console.error('Failed to link flights:', xhr);
                    alert('Failed to link flights. Please try again.');
                }
            });
        },
        
        /**
         * Unlink a flight group
         */
        unlinkFlightGroup: function(groupId) {
            const self = this;
            
            // Get all legs in the group first
            $.ajax({
                url: fttData.restUrl + 'flight-group/' + groupId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(group) {
                    // Unlink each leg
                    const promises = group.legs.map(legData => {
                        return $.ajax({
                            url: fttData.restUrl + 'unlink-flight',
                            method: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                event_id: legData.event_id,
                                leg_index: legData.leg_index
                            }),
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                            }
                        });
                    });
                    
                    Promise.all(promises).then(() => {
                        console.log('Flight group unlinked');
                        alert('Flights unlinked successfully!');
                        self.loadLinkedFlights();
                    }).catch(err => {
                        console.error('Failed to unlink some flights:', err);
                        alert('Some flights may not have been unlinked. Please refresh and try again.');
                    });
                },
                error: function(xhr) {
                    console.error('Failed to get group info:', xhr);
                    alert('Failed to unlink flights. Please try again.');
                }
            });
        },
        
        /**
         * Load invitations (for members)
         */
        loadInvitations: function() {
            const container = document.getElementById('ftt-invitations-list');
            const memberCodeEl = document.getElementById('ftt-member-code');
            if (!container) return;
            
            $.ajax({
                url: fttData.restUrl + 'invitations',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(data) {
                    console.log('Invitations loaded:', data);
                    
                    // Display member code
                    if (memberCodeEl && data.member_code) {
                        memberCodeEl.textContent = data.member_code;
                    }
                    
                    // Render invitations list
                    if (Object.keys(data.invitations).length === 0) {
                        container.innerHTML = '<p class="ftt-no-invitations">No invitations yet. Generate a code to invite your parents.</p>';
                    } else {
                        let html = '<h4>Your Invitations</h4><div class="ftt-invitations-grid">';
                        
                        for (const [code, invite] of Object.entries(data.invitations)) {
                            const statusClass = 'ftt-invite-' + invite.status;
                            const statusIcon = invite.status === 'accepted' ? '✓' : 
                                             invite.status === 'pending' ? '⏳' : 
                                             invite.status === 'revoked' ? '🚫' : '❌';
                            
                            html += `
                                <div class="ftt-invitation-card ${statusClass}">
                                    <div class="ftt-invitation-header">
                                        <span class="ftt-invitation-status">${statusIcon} ${invite.status.toUpperCase()}</span>
                                        <code class="ftt-invitation-code">${code}</code>
                                    </div>
                                    <div class="ftt-invitation-details">
                                        <p><strong>Created:</strong> ${new Date(invite.created_at).toLocaleDateString()}</p>
                            `;
                            
                            if (invite.status === 'accepted' && invite.parent_name) {
                                html += `<p><strong>Used by:</strong> ${FTT.escapeHtml(invite.parent_name)}</p>`;
                                html += `<p><small>${FTT.escapeHtml(invite.parent_email)}</small></p>`;
                            }
                            
                            if (invite.status === 'pending') {
                                html += `
                                    <button class="button button-small ftt-revoke-invite" data-code="${code}">
                                        Revoke
                                    </button>
                                    <button class="button button-small ftt-copy-code" data-code-value="${code}">
                                        <span class="dashicons dashicons-clipboard"></span> Copy
                                    </button>
                                `;
                            }
                            
                            html += '</div></div>';
                        }
                        
                        html += '</div>';
                        container.innerHTML = html;
                        
                        // Attach event handlers
                        $('.ftt-revoke-invite').on('click', function() {
                            FTT.revokeInvitation($(this).data('code'));
                        });
                        $('.ftt-copy-code[data-code-value]').on('click', function() {
                            FTT.copyToClipboard($(this).data('code-value'));
                        });
                    }
                },
                error: function(xhr) {
                    console.error('Failed to load invitations:', xhr);
                    container.innerHTML = '<p class="ftt-error">Failed to load invitations.</p>';
                }
            });
        },
        
        /**
         * Generate new invitation
         */
        generateInvitation: function() {
            const button = $('#ftt-generate-invite');
            button.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: fttData.restUrl + 'invite/generate',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(response) {
                    console.log('Invitation generated:', response);
                    
                    // Fetch registration page URL from backend
                    $.ajax({
                        url: fttData.restUrl + 'registration-url',
                        method: 'GET',
                        success: function(urlResponse) {
                            const code = response.invitation.code;
                            const registrationUrl = urlResponse.url + '?invite=' + code;
                            FTT.showInvitationModal(code, registrationUrl);
                            button.prop('disabled', false).html('<span class="dashicons dashicons-email"></span> Generate One-Time Invite Code');
                        },
                        error: function(xhr) {
                            console.error('Failed to get registration URL:', xhr);
                            // Fallback to current domain
                            const code = response.invitation.code;
                            const registrationUrl = window.location.origin + '/sc-register/?invite=' + code;
                            FTT.showInvitationModal(code, registrationUrl);
                            button.prop('disabled', false).html('<span class="dashicons dashicons-email"></span> Generate One-Time Invite Code');
                        }
                    });
                },
                error: function(xhr) {
                    console.error('Failed to generate invitation:', xhr);
                    alert('Failed to generate invitation code. Please try again.');
                    button.prop('disabled', false).html('<span class="dashicons dashicons-email"></span> Generate One-Time Invite Code');
                }
            });
        },
        
        /**
         * Show invitation modal
         */
        showInvitationModal: function(code, registrationUrl) {
            // Create modal HTML
            const modalHtml = `
                <div id="ftt-invite-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 999999;">
                    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                        <h2 style="margin-top: 0; color: #1e3a8a;">✓ Invitation Code Generated!</h2>
                        <p style="color: #666; margin-bottom: 20px;">Share this link or code with your parent to register and link to your account.</p>
                        
                        <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Invitation Link:</label>
                            <input type="text" id="ftt-invite-url" value="${registrationUrl}" readonly style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-family: monospace; font-size: 14px;">
                        </div>
                        
                        <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Code Only:</label>
                            <input type="text" id="ftt-invite-code" value="${code}" readonly style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-family: monospace; font-size: 18px; text-align: center; font-weight: 700;">
                        </div>
                        
                        <div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3b82f6;">
                            <p style="margin: 0; font-size: 14px; color: #1e40af;">
                                <strong>Note:</strong> This code can only be used once and expires in 7 days.
                            </p>
                        </div>
                        
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button id="ftt-copy-link" class="button button-primary" style="flex: 1;">
                                <span class="dashicons dashicons-clipboard"></span> Copy Link
                            </button>
                            <button id="ftt-copy-code-only" class="button button-secondary" style="flex: 1;">
                                <span class="dashicons dashicons-clipboard"></span> Copy Code
                            </button>
                            <button id="ftt-close-modal" class="button" style="flex: 1;">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Copy link button
            $('#ftt-copy-link').on('click', function() {
                const input = document.getElementById('ftt-invite-url');
                input.select();
                document.execCommand('copy');
                $(this).html('<span class="dashicons dashicons-yes"></span> Copied!').prop('disabled', true);
                setTimeout(() => {
                    $(this).html('<span class="dashicons dashicons-clipboard"></span> Copy Link').prop('disabled', false);
                }, 2000);
            });
            
            // Copy code button
            $('#ftt-copy-code-only').on('click', function() {
                const input = document.getElementById('ftt-invite-code');
                input.select();
                document.execCommand('copy');
                $(this).html('<span class="dashicons dashicons-yes"></span> Copied!').prop('disabled', true);
                setTimeout(() => {
                    $(this).html('<span class="dashicons dashicons-clipboard"></span> Copy Code').prop('disabled', false);
                }, 2000);
            });
            
            // Close modal
            $('#ftt-close-modal, #ftt-invite-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#ftt-invite-modal').remove();
                }
            });
            
            FTT.loadInvitations(); // Reload list
        },
        
        /**
         * Revoke invitation
         */
        revokeInvitation: function(code) {
            if (!confirm('Are you sure you want to revoke this invitation code?')) {
                return;
            }
            
            $.ajax({
                url: fttData.restUrl + 'invite/' + code + '/revoke',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(response) {
                    console.log('Invitation revoked:', response);
                    FTT.loadInvitations(); // Reload list
                },
                error: function(xhr) {
                    console.error('Failed to revoke invitation:', xhr);
                    alert('Failed to revoke invitation. Please try again.');
                }
            });
        },
        
        /**
         * Submit parent code (for parents linking to child)
         */
        submitParentCode: function(code) {
            const messageEl = $('#ftt-code-message');
            messageEl.removeClass('success error').text('Linking...').show();
            
            $.ajax({
                url: fttData.restUrl + 'invite/accept',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ code: code }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', fttData.nonce);
                },
                success: function(response) {
                    console.log('Code accepted:', response);
                    messageEl.removeClass('error').addClass('success').text('✓ Successfully linked to ' + response.member.name + '!');
                    $('#ftt-parent-code-input').val('');
                    // Reload page to show new child
                    setTimeout(function() {
                        location.reload();
                    }, 2000);
                },
                error: function(xhr) {
                    console.error('Failed to accept code:', xhr);
                    let errorMsg = 'Invalid or expired code. Please check and try again.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        errorMsg = xhr.responseJSON.message;
                    }
                    messageEl.removeClass('success').addClass('error').text('✗ ' + errorMsg);
                }
            });
        },
        
        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(() => {
                    alert('Code copied to clipboard: ' + text);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    this.fallbackCopyToClipboard(text);
                });
            } else {
                this.fallbackCopyToClipboard(text);
            }
        },
        
        /**
         * Fallback clipboard copy
         */
        fallbackCopyToClipboard: function(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-9999px';
            document.body.appendChild(textArea);
            textArea.select();
            try {
                document.execCommand('copy');
                alert('Code copied to clipboard: ' + text);
            } catch (err) {
                console.error('Failed to copy:', err);
                alert('Failed to copy code. Please copy manually: ' + text);
            }
            document.body.removeChild(textArea);
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        FTT.init();
        
        // Debug: Check for invitation elements
        console.log('=== Invitation Elements Check ===');
        console.log('ftt-invitations-list exists:', $('#ftt-invitations-list').length > 0);
        console.log('ftt-generate-invite button exists:', $('#ftt-generate-invite').length > 0);
        console.log('ftt-member-code exists:', $('#ftt-member-code').length > 0);
        console.log('================================');
        
        // Initialize invitations if element exists
        if ($('#ftt-invitations-list').length) {
            FTT.loadInvitations();
        }
        
        // Generate invitation button
        $(document).on('click', '#ftt-generate-invite', function(e) {
            e.preventDefault();
            FTT.generateInvitation();
        });
        
        // Parent code form
        $(document).on('submit', '#ftt-parent-code-form', function(e) {
            e.preventDefault();
            const code = $('#ftt-parent-code-input').val().trim().toUpperCase();
            if (code) {
                FTT.submitParentCode(code);
            }
        });
        
        // Copy code buttons (permanent member code)
        $(document).on('click', '.ftt-copy-code[data-code-target]', function() {
            const targetId = $(this).data('code-target');
            const codeText = $('#' + targetId).text();
            if (codeText && codeText !== '---') {
                FTT.copyToClipboard(codeText);
            }
        });

        // ── AI Event Assistant (conversational chat) ─────────────────────
        var fttAiHistory = [];   // [{role:'user',content:'...'}, {role:'assistant',content:'...'}]
        var fttAiParsed  = null; // last fill-mode response
        var fttAiDone    = false; // true after form has been filled

        function fttAiScrollChat() {
            var el = document.getElementById('ftt-ai-chat');
            if (el) el.scrollTop = el.scrollHeight;
        }

        // Append a plain text bubble to the chat window.
        function fttAiAppendBubble(role, text) {
            var cls = (role === 'user') ? 'ftt-ai-bubble-user' : 'ftt-ai-bubble-ai';
            var $b = $('<div>').addClass('ftt-ai-bubble ' + cls).text(text);
            $('#ftt-ai-chat').append($b);
            fttAiScrollChat();
            return $b;
        }

        // Append an AI bubble with Yes / No action buttons.
        function fttAiAppendActionBubble(text, yesLabel, noLabel, onYes, onNo) {
            var $b = $('<div>').addClass('ftt-ai-bubble ftt-ai-bubble-ai ftt-ai-bubble-action');
            $b.append($('<p>').text(text));
            var $yes = $('<button type="button">').addClass('ftt-btn ftt-btn-secondary ftt-btn-sm').text(yesLabel);
            var $no  = $('<button type="button">').addClass('ftt-btn-link').text(noLabel);
            function resolve() { $b.addClass('ftt-ai-bubble-resolved'); $yes.prop('disabled', true); $no.prop('disabled', true); }
            $yes.on('click', function() { resolve(); onYes(); });
            $no.on('click',  function() { resolve(); onNo();  });
            $b.append($yes).append(' ').append($no);
            $('#ftt-ai-chat').append($b);
            fttAiScrollChat();
        }

        // Toggle the chat panel open/closed.
        // Collapsing the form while chatting keeps focus on the conversation.
        function fttFormCollapse() {
            $('#ftt-form-fields').slideUp(250);
            $('#ftt-form-expand-bar').show().attr('aria-expanded', 'false');
        }
        function fttFormExpand() {
            $('#ftt-form-fields').slideDown(300);
            $('#ftt-form-expand-bar').hide().attr('aria-expanded', 'true');
        }

        $('#ftt-ai-toggle').on('click', function() {
            var $body = $('#ftt-ai-body');
            var open  = $body.is(':visible');
            $body.slideToggle(200);
            $(this).attr('aria-expanded', String(!open))
                   .text( open ? 'Chat ▾' : 'Hide ▴' );
            // Collapse the form when chat opens; restore when chat closes.
            if (!open) { fttFormCollapse(); } else { fttFormExpand(); }
        });

        // Manual expand/collapse of the form section.
        $('#ftt-form-expand-bar').on('click', function() {
            var expanded = $('#ftt-form-fields').is(':visible');
            if (expanded) { fttFormCollapse(); } else { fttFormExpand(); }
        });

        // Send the current textarea content to the AI.
        function fttAiSend() {
            if (fttAiDone) return;
            var prompt = $.trim($('#ftt-ai-prompt').val());
            if (!prompt) return;

            var $btn = $('#ftt-ai-parse-btn');
            $btn.prop('disabled', true);
            $('#ftt-ai-spinner').show();

            fttAiAppendBubble('user', prompt);
            $('#ftt-ai-prompt').val('');

            $.ajax({
                url:         fttData.restUrl + 'ai/parse-event',
                method:      'POST',
                beforeSend:  function(xhr) { xhr.setRequestHeader('X-WP-Nonce', fttData.nonce); },
                contentType: 'application/json',
                data:        JSON.stringify({ prompt: prompt, history: fttAiHistory }),
                success: function(res) {
                    // Record both sides of this turn for next request.
                    fttAiHistory.push({ role: 'user',      content: prompt });
                    fttAiHistory.push({ role: 'assistant', content: JSON.stringify(res) });

                    if (res.mode === 'chat') {
                        // AI has a follow-up question — show it and keep input active.
                        fttAiAppendBubble('ai', res.message);
                        $('#ftt-ai-prompt').focus();

                    } else {
                        // Fill mode: populate the form.
                        fttAiParsed = res;
                        FTT.fillFormFromAI(res);

                        // Confirmation bubble.
                        var confidence = res.confidence || 'medium';
                        var doneMsg = (confidence === 'high')
                            ? '✓ Done! I\'ve filled in the form for "' + (res.title || 'this event') + '". Give it a look and save when you\'re ready.'
                            : '⚠ I\'ve partially filled the form for "' + (res.title || 'this event') + '". A few items below need your attention.';
                        fttAiAppendBubble('ai', doneMsg);

                        // Clarifications as a follow-up bubble.
                        var clarifications = res.clarifications_needed || [];
                        if (clarifications.length > 0) {
                            fttAiAppendBubble('ai', 'A few things to double-check:\n• ' + clarifications.join('\n• '));
                        }

                        // Return flight question (inline buttons).
                        if (res.needs_return_clarification) {
                            var returnDate = res.end_date ? ' for ' + res.end_date : '';
                            fttAiAppendActionBubble(
                                'No return flight was included — should I add one' + returnDate + '?',
                                'Yes, add return flight',
                                'No return needed',
                                function() {
                                    var outbound = (fttAiParsed.travel_legs || [])[0] || {};
                                    FTT.addTravelLeg({
                                        leg_name:           'Return',
                                        mode:               'fly',
                                        depart_airport:     outbound.arrive_airport || '',
                                        arrive_airport:     outbound.depart_airport || '',
                                        depart_date:        fttAiParsed.end_date   || '',
                                        depart_time_of_day: '',
                                        arrive_date:        fttAiParsed.end_date   || '',
                                        booked:             false,
                                    });
                                    fttAiAppendBubble('ai', 'Return leg added!');
                                },
                                function() {}
                            );
                        }

                        // Airport save suggestion (inline buttons).
                        if (res.suggest_save_home_airport) {
                            var airportMsg = res.save_home_airport_confirmation ||
                                ('Save ' + res.suggest_save_home_airport + ' as your home airport?');
                            fttAiAppendActionBubble(
                                airportMsg,
                                'Yes, save it',
                                'No thanks',
                                function() {
                                    $.ajax({
                                        url:         fttData.restUrl + 'user-preferences',
                                        method:      'PUT',
                                        beforeSend:  function(xhr) { xhr.setRequestHeader('X-WP-Nonce', fttData.nonce); },
                                        contentType: 'application/json',
                                        data:        JSON.stringify({ home_airport: res.suggest_save_home_airport }),
                                    });
                                },
                                function() {}
                            );
                        }

                        // Lock input, show restart. Expand the form so the user can review.
                        fttAiDone = true;
                        $btn.text('Done').prop('disabled', true);
                        $('#ftt-ai-prompt').prop('disabled', true);
                        $('#ftt-ai-restart').show();

                        // Expand the form and scroll it into view.
                        fttFormExpand();
                        setTimeout(function() {
                            var $form = $('#ftt-form-wrap');
                            if ($form.length) {
                                $('html, body').animate({ scrollTop: $form.offset().top - 80 }, 400);
                            }
                        }, 350);
                    }
                },
                error: function(xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message)
                              ? xhr.responseJSON.message
                              : 'Something went wrong — try rephrasing.';
                    fttAiAppendBubble('ai', '⚠ ' + msg);
                },
                complete: function() {
                    if (!fttAiDone) { $btn.prop('disabled', false); }
                    $('#ftt-ai-spinner').hide();
                },
            });
        }

        $('#ftt-ai-parse-btn').on('click', fttAiSend);

        // Send on Enter (Shift+Enter = new line).
        $('#ftt-ai-prompt').on('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                fttAiSend();
            }
        });

        // Reset the whole conversation.
        $('#ftt-ai-restart').on('click', function() {
            fttAiHistory = [];
            fttAiParsed  = null;
            fttAiDone    = false;
            $('#ftt-ai-chat').html(
                '<div class="ftt-ai-bubble ftt-ai-bubble-ai">' +
                'Tell me about the trip — who\'s going, where to, and when?</div>'
            );
            $('#ftt-ai-prompt').val('').prop('disabled', false).focus();
            $('#ftt-ai-parse-btn').text('Send').prop('disabled', false);
            $(this).hide();
            // Re-collapse the form for the next chat session.
            fttFormCollapse();
        });

        // Calendar subscribe modal
        $(document).on('click', '#ftt-open-subscribe-modal', function() {
            $('#ftt-subscribe-modal').fadeIn(200);
        });
        $(document).on('click', '#ftt-close-subscribe-modal', function() {
            $('#ftt-subscribe-modal').fadeOut(200);
        });
        $(document).on('click', '#ftt-subscribe-modal', function(e) {
            if ($(e.target).is('#ftt-subscribe-modal')) {
                $('#ftt-subscribe-modal').fadeOut(200);
            }
        });

        // ── Home-airport reminder modal ──────────────────────────────────
        if (fttData.showAirportReminder) {
            // Small delay so it doesn't fire the instant the page loads.
            setTimeout(function() {
                $('#ftt-airport-reminder-modal').fadeIn(250);
            }, 1500);
        }

        // Auto-uppercase airport input.
        $(document).on('input', '#ftt-reminder-airport', function() {
            var pos = this.selectionStart;
            this.value = this.value.replace(/[^A-Za-z]/g, '').toUpperCase();
            this.setSelectionRange(pos, pos);
        });

        // Close on backdrop click or × button (just hides, does not dismiss permanently).
        $(document).on('click', '#ftt-airport-reminder-close, #ftt-airport-reminder-modal', function(e) {
            if ($(e.target).is('#ftt-airport-reminder-modal') || $(e.target).is('#ftt-airport-reminder-close')) {
                $('#ftt-airport-reminder-modal').fadeOut(200);
            }
        });

        // Save airport + timezone.
        $(document).on('click', '#ftt-reminder-save', function() {
            var $btn     = $(this);
            var $msg     = $('#ftt-reminder-msg');
            var airport  = $('#ftt-reminder-airport').val().replace(/[^A-Za-z]/g, '').toUpperCase();
            var timezone = $('#ftt-reminder-timezone').val();

            $btn.prop('disabled', true).text('Saving…');

            $.ajax({
                url:         fttData.restUrl + 'user-preferences',
                method:      'PUT',
                beforeSend:  function(xhr) { xhr.setRequestHeader('X-WP-Nonce', fttData.nonce); },
                contentType: 'application/json',
                data:        JSON.stringify({ home_airport: airport, timezone: timezone }),
                success: function() {
                    $('#ftt-airport-reminder-modal').fadeOut(300);
                },
                error: function() {
                    $msg.removeClass('ftt-msg-ok').addClass('ftt-msg-error')
                        .text('Could not save. Please try again.').show();
                    $btn.prop('disabled', false).text('Save');
                }
            });
        });

        // "Don't ask again" — sets a dismissed flag server-side via user-preferences.
        $(document).on('click', '#ftt-reminder-dismiss', function(e) {
            e.preventDefault();
            $('#ftt-airport-reminder-modal').fadeOut(200);
            $.ajax({
                url:         fttData.restUrl + 'user-preferences',
                method:      'PUT',
                beforeSend:  function(xhr) { xhr.setRequestHeader('X-WP-Nonce', fttData.nonce); },
                contentType: 'application/json',
                data:        JSON.stringify({ airport_reminder_dismissed: true })
            });
        });
    });
    
})(jQuery);

