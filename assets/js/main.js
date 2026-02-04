/**
 * Main JavaScript for Summer Regiment Tracker
 *
 * @package Summer_Regiment_Tracker
 */

(function($) {
    'use strict';
    
    const SRT = {
        
        /**
         * Initialize
         */
        init: function() {
            this.initCalendar();
            this.initEventForm();
            this.initDashboard();
            this.initEventList();
        },
        
        /**
         * Initialize calendar
         */
        initCalendar: function() {
            const calendarEl = document.getElementById('srt-calendar');
            if (!calendarEl) return;
            
            // Check if FullCalendar is available
            if (typeof FullCalendar === 'undefined') {
                console.error('FullCalendar library not loaded');
                return;
            }
            
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                height: 'auto',
                contentHeight: 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                events: function(info, successCallback, failureCallback) {
                    SRT.fetchEvents(info.startStr, info.endStr)
                        .then(events => {
                            const calendarEvents = events.map(event => {
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
                    SRT.showEventModal(info.event.extendedProps);
                },
                eventDidMount: function(info) {
                    // Add color coding based on event type
                    const eventType = info.event.extendedProps.event_type;
                    info.el.classList.add('srt-event-type-' + eventType);
                    
                    // Add member name on separate line if present
                    if (info.event.extendedProps.member_name) {
                        const titleEl = info.el.querySelector('.fc-event-title');
                        if (titleEl) {
                            const memberName = document.createElement('div');
                            memberName.className = 'srt-calendar-member-name';
                            memberName.textContent = info.event.extendedProps.member_name;
                            titleEl.insertBefore(memberName, titleEl.firstChild);
                        }
                    }
                }
            });
            
            calendar.render();
            
            // Store calendar instance for re-rendering
            this.calendar = calendar;
            
            // Handle member selector change
            const memberSelector = document.getElementById('srt-calendar-member');
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
         * Initialize event form
         */
        initEventForm: function() {
            const form = document.getElementById('srt-event-form');
            if (!form) return;
            
            // Get event ID from URL if editing
            const urlParams = new URLSearchParams(window.location.search);
            const eventId = urlParams.get('event_id');
            
            if (eventId) {
                this.loadEventForEdit(eventId);
            }
            
            // Add time block button
            $('#srt-add-time-block').on('click', function(e) {
                e.preventDefault();
                SRT.addTimeBlock();
            });
            
            // Add travel leg button
            $('#srt-add-travel-leg').on('click', function(e) {
                e.preventDefault();
                SRT.addTravelLeg();
            });
            
            // Auto-expand travel section for flight_only events
            $('#event_type').on('change', function() {
                const isFlight = $(this).val() === 'flight_only';
                if (isFlight) {
                    $('#srt-travel-section').show();
                    // Add a travel leg if none exist
                    if ($('#srt-travel-legs-container .srt-travel-leg').length === 0) {
                        SRT.addTravelLeg({ mode: 'fly' });
                    }
                }
            });
            
            // Travel needed checkbox
            $('#travel_needed').on('change', function() {
                $('.srt-travel-section').toggle(this.checked);
            });
            
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
            
            // Flight needed checkbox
            $('#flight_needed').on('change', function() {
                $('.srt-flight-section').toggle(this.checked);
            });
            
            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                SRT.submitEventForm(eventId);
            });
            
            // Delete button
            $('#srt-delete-event').on('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to delete this event?')) {
                    SRT.deleteEvent(eventId);
                }
            });
        },
        
        /**
         * Initialize dashboard
         */
        initDashboard: function() {
            const dashboard = document.getElementById('srt-dashboard');
            if (!dashboard) return;
            
            this.loadDashboardData();
        },
        
        /**
         * Initialize event list
         */
        initEventList: function() {
            const eventList = document.getElementById('srt-event-list');
            if (!eventList) return;
            
            // Event list is rendered server-side, but we can add interactivity here
            $('.srt-event-item').on('click', function() {
                const eventId = $(this).data('event-id');
                if (eventId) {
                    SRT.fetchEvent(eventId).then(event => {
                        SRT.showEventModal(event);
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
            const memberSelector = document.getElementById('srt-calendar-member');
            if (memberSelector && memberSelector.value) {
                params.append('member_id', memberSelector.value);
            }
            
            return fetch(srtData.restUrl + 'events?' + params.toString(), {
                headers: {
                    'X-WP-Nonce': srtData.nonce
                }
            })
            .then(response => response.json());
        },
        
        /**
         * Fetch single event
         */
        fetchEvent: function(eventId) {
            return fetch(srtData.restUrl + 'events/' + eventId, {
                headers: {
                    'X-WP-Nonce': srtData.nonce
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
                $('#event_type').val(event.event_type);
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
                            .appendTo('#srt-event-form');
                    }
                    if (event.location_longitude) {
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('id', 'location_longitude')
                            .attr('name', 'location_longitude')
                            .val(event.location_longitude)
                            .appendTo('#srt-event-form');
                    }
                }
                
                $('#notes').val(event.notes);
                $('#travel_needed').prop('checked', event.travel_needed).trigger('change');
                $('#travel_mode').val(event.travel_mode);
                $('#flight_needed').prop('checked', event.flight_needed).trigger('change');
                
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
                }
                
                // Show delete button
                $('#srt-delete-event').show();
            }).catch(error => {
                console.error('Error loading event:', error);
                alert('Error loading event for editing.');
            });
        },
        
        /**
         * Submit event form
         */
        submitEventForm: function(eventId) {
            const formData = {
                title: $('#event_title').val(),
                start_datetime: $('#start_datetime').val(),
                end_datetime: $('#end_datetime').val(),
                all_day: $('#all_day').is(':checked'),
                timezone: $('#timezone').val(),
                event_type: $('#event_type').val(),
                location_name: $('#location_name').val(),
                location_address: $('#location_address').val(),
                notes: $('#notes').val(),
                travel_needed: $('#travel_needed').is(':checked'),
                travel_mode: $('#travel_mode').val(),
                flight_needed: $('#flight_needed').is(':checked'),
                time_blocks: JSON.stringify(this.collectTimeBlocks()),
                travel_legs: JSON.stringify(this.collectTravelLegs())
            };
            
            // Validate
            if (!formData.title || !formData.start_datetime || !formData.end_datetime) {
                alert('Please fill in all required fields.');
                return;
            }
            
            const method = eventId ? 'PUT' : 'POST';
            const url = eventId ? srtData.restUrl + 'events/' + eventId : srtData.restUrl + 'events';
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': srtData.nonce
                },
                body: JSON.stringify(formData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.id) {
                    alert('Event saved successfully!');
                    // Redirect or reset form
                    if (!eventId) {
                        document.getElementById('srt-event-form').reset();
                        $('#srt-time-blocks-container').empty();
                        $('#srt-travel-legs-container').empty();
                    }
                } else {
                    alert('Error saving event.');
                }
            })
            .catch(error => {
                console.error('Error saving event:', error);
                alert('Error saving event.');
            });
        },
        
        /**
         * Delete event
         */
        deleteEvent: function(eventId) {
            fetch(srtData.restUrl + 'events/' + eventId, {
                method: 'DELETE',
                headers: {
                    'X-WP-Nonce': srtData.nonce
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
            const container = $('#srt-time-blocks-container');
            const index = container.children().length;
            
            const html = `
                <div class="srt-time-block" data-index="${index}">
                    <h4>Time Block ${index + 1} <button type="button" class="srt-remove-block" data-target="time-block">Remove</button></h4>
                    <div class="srt-form-row">
                        <div class="srt-form-field">
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
                        <div class="srt-form-field">
                            <label>Title</label>
                            <input type="text" name="time_blocks[${index}][title]" value="${data ? data.title : ''}" required>
                        </div>
                    </div>
                    <div class="srt-form-row">
                        <div class="srt-form-field">
                            <label>Start Time</label>
                            <input type="datetime-local" name="time_blocks[${index}][start_datetime]" value="${data ? data.start_datetime.slice(0, 16) : ''}" required>
                        </div>
                        <div class="srt-form-field">
                            <label>End Time</label>
                            <input type="datetime-local" name="time_blocks[${index}][end_datetime]" value="${data ? data.end_datetime.slice(0, 16) : ''}" required>
                        </div>
                    </div>
                    <div class="srt-form-field">
                        <label>Notes</label>
                        <textarea name="time_blocks[${index}][notes]" rows="2">${data ? data.notes : ''}</textarea>
                    </div>
                </div>
            `;
            
            container.append(html);
            
            // Add remove handler
            container.find('.srt-remove-block').last().on('click', function() {
                $(this).closest('.srt-time-block').remove();
            });
        },
        
        /**
         * Add travel leg
         */
        addTravelLeg: function(data) {
            const container = $('#srt-travel-legs-container');
            const index = container.children().length;
            
            // Airport lookup function
            const airportLookup = {
                'BDL': 'Hartford, CT', 'BWI': 'Baltimore, MD', 'ORD': 'Chicago, IL', 'LAX': 'Los Angeles, CA',
                'DFW': 'Dallas, TX', 'ATL': 'Atlanta, GA', 'DEN': 'Denver, CO', 'SFO': 'San Francisco, CA',
                'SEA': 'Seattle, WA', 'MSP': 'Minneapolis, MN', 'DTW': 'Detroit, MI', 'PHX': 'Phoenix, AZ',
                'IAH': 'Houston, TX', 'MIA': 'Miami, FL', 'MCO': 'Orlando, FL', 'LAS': 'Las Vegas, NV',
                'CLT': 'Charlotte, NC', 'BOS': 'Boston, MA', 'JFK': 'New York, NY', 'EWR': 'Newark, NJ',
                'SLC': 'Salt Lake City, UT', 'PDX': 'Portland, OR', 'PHL': 'Philadelphia, PA'
            };
            
            const html = `
                <div class="srt-travel-leg" data-index="${index}">
                    <div class="srt-leg-header">
                        <h4>Travel Leg ${index + 1}</h4>
                        <button type="button" class="srt-remove-block" data-target="travel-leg">✕ Remove</button>
                    </div>
                    
                    <div class="srt-form-row">
                        <div class="srt-form-field">
                            <label>Leg Type</label>
                            <select name="travel_legs[${index}][mode]" class="srt-leg-mode">
                                <option value="fly" ${data && data.mode === 'fly' ? 'selected' : ''}>✈️ Flight</option>
                                <option value="drive" ${data && data.mode === 'drive' ? 'selected' : ''}>🚗 Drive</option>
                                <option value="bus" ${data && data.mode === 'bus' ? 'selected' : ''}>🚌 Bus</option>
                                <option value="shuttle" ${data && data.mode === 'shuttle' ? 'selected' : ''}>🚐 Shuttle</option>
                                <option value="other" ${data && data.mode === 'other' ? 'selected' : ''}>Other</option>
                            </select>
                        </div>
                        <div class="srt-form-field srt-flight-only">
                            <label>
                                <input type="checkbox" name="travel_legs[${index}][is_round_trip]" class="srt-round-trip-toggle" value="1" ${data && data.is_round_trip ? 'checked' : ''}>
                                Round-Trip Flight
                            </label>
                            <small class="description">Automatically creates return flight</small>
                        </div>
                    </div>
                    
                    <div class="srt-form-row">
                        <div class="srt-form-field srt-flight-only">
                            <label>From Airport (IATA Code) *</label>
                            <input type="text" name="travel_legs[${index}][depart_airport]" class="srt-airport-code" value="${data ? data.depart_airport : ''}" maxlength="3" placeholder="e.g., BDL" style="text-transform: uppercase;">
                            <small class="srt-airport-name"></small>
                        </div>
                        <div class="srt-form-field srt-non-flight">
                            <label>From Location</label>
                            <input type="text" name="travel_legs[${index}][depart_location]" value="${data ? data.depart_location : ''}" placeholder="e.g., Hartford, CT">
                        </div>
                        <div class="srt-form-field srt-flight-only">
                            <label>To Airport (IATA Code) *</label>
                            <input type="text" name="travel_legs[${index}][arrive_airport]" class="srt-airport-code" value="${data ? data.arrive_airport : ''}" maxlength="3" placeholder="e.g., BWI" style="text-transform: uppercase;">
                            <small class="srt-airport-name"></small>
                        </div>
                        <div class="srt-form-field srt-non-flight">
                            <label>To Location</label>
                            <input type="text" name="travel_legs[${index}][arrive_location]" value="${data ? data.arrive_location : ''}" placeholder="e.g., Baltimore, MD">
                        </div>
                    </div>
                    
                    <div class="srt-form-row">
                        <div class="srt-form-field">
                            <label>Date *</label>
                            <input type="date" name="travel_legs[${index}][depart_date]" value="${data && data.depart_date ? data.depart_date : ''}" required>
                        </div>
                        <div class="srt-form-field">
                            <label>Time of Day</label>
                            <select name="travel_legs[${index}][depart_time_of_day]">
                                <option value="">Any Time</option>
                                <option value="morning" ${data && data.depart_time_of_day === 'morning' ? 'selected' : ''}>Morning</option>
                                <option value="midday" ${data && data.depart_time_of_day === 'midday' ? 'selected' : ''}>Mid-Day</option>
                                <option value="afternoon" ${data && data.depart_time_of_day === 'afternoon' ? 'selected' : ''}>Afternoon</option>
                                <option value="night" ${data && data.depart_time_of_day === 'night' ? 'selected' : ''}>Night</option>
                            </select>
                        </div>
                        <div class="srt-form-field srt-round-trip-return" style="display: none;">
                            <label>Return Date *</label>
                            <input type="date" name="travel_legs[${index}][return_date]" value="${data && data.return_date ? data.return_date : ''}">
                        </div>
                        <div class="srt-form-field srt-round-trip-return" style="display: none;">
                            <label>Return Time of Day</label>
                            <select name="travel_legs[${index}][return_time_of_day]">
                                <option value="">Any Time</option>
                                <option value="morning" ${data && data.return_time_of_day === 'morning' ? 'selected' : ''}>Morning</option>
                                <option value="midday" ${data && data.return_time_of_day === 'midday' ? 'selected' : ''}>Mid-Day</option>
                                <option value="afternoon" ${data && data.return_time_of_day === 'afternoon' ? 'selected' : ''}>Afternoon</option>
                                <option value="night" ${data && data.return_time_of_day === 'night' ? 'selected' : ''}>Night</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="srt-booking-section srt-flight-only">
                        <div class="srt-form-field">
                            <label class="srt-booking-toggle">
                                <input type="checkbox" name="travel_legs[${index}][booked]" value="1" class="srt-booked-checkbox" ${data && data.booked ? 'checked' : ''}>
                                ✓ Already Booked
                            </label>
                        </div>
                        
                        <div class="srt-booking-details" style="display: ${data && data.booked ? 'block' : 'none'};">
                            <div class="srt-form-row">
                                <div class="srt-form-field">
                                    <label>Airline</label>
                                    <input type="text" name="travel_legs[${index}][airline]" value="${data ? data.airline : ''}" placeholder="Southwest">
                                </div>
                                <div class="srt-form-field">
                                    <label>Flight Number</label>
                                    <input type="text" name="travel_legs[${index}][flight_number]" value="${data ? data.flight_number : ''}" placeholder="WN 420">
                                </div>
                                <div class="srt-form-field">
                                    <label>Confirmation #</label>
                                    <input type="text" name="travel_legs[${index}][confirmation]" value="${data ? data.confirmation : ''}" placeholder="ABC123">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="srt-form-field srt-flight-only">
                        <label>Baggage (select all that apply)</label>
                        <div class="srt-checkbox-group">
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="carry_on" ${data && data.baggage && data.baggage.includes('carry_on') ? 'checked' : ''}> Carry-On</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="checked" ${data && data.baggage && data.baggage.includes('checked') ? 'checked' : ''}> Checked Bag</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="instrument" ${data && data.baggage && data.baggage.includes('instrument') ? 'checked' : ''}> Instrument</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="color_guard_equipment" ${data && data.baggage && data.baggage.includes('color_guard_equipment') ? 'checked' : ''}> Color Guard Equip</label>
                            <label><input type="checkbox" name="travel_legs[${index}][baggage][]" value="oversize" ${data && data.baggage && data.baggage.includes('oversize') ? 'checked' : ''}> Oversize</label>
                        </div>
                    </div>
                    
                    <div class="srt-form-field">
                        <label>Notes</label>
                        <textarea name="travel_legs[${index}][notes]" rows="2">${data && data.notes ? data.notes : ''}</textarea>
                    </div>
                </div>
            `;
            
            container.append(html);
            
            const $newLeg = container.find('.srt-travel-leg').last();
            
            // Add remove handler
            $newLeg.find('.srt-remove-block').on('click', function() {
                $(this).closest('.srt-travel-leg').remove();
            });
            
            // Toggle flight-specific vs non-flight fields
            $newLeg.find('.srt-leg-mode').on('change', function() {
                const isFlight = $(this).val() === 'fly';
                $newLeg.find('.srt-flight-only').toggle(isFlight);
                $newLeg.find('.srt-non-flight').toggle(!isFlight);
            }).trigger('change');
            
            // Toggle booking details
            $newLeg.find('.srt-booked-checkbox').on('change', function() {
                $newLeg.find('.srt-booking-details').toggle(this.checked);
            });
            
            // Toggle round-trip fields and auto-create return leg
            $newLeg.find('.srt-round-trip-toggle').on('change', function() {
                const isRoundTrip = this.checked;
                $newLeg.find('.srt-round-trip-return').toggle(isRoundTrip);
                
                if (isRoundTrip) {
                    // Auto-create return leg
                    const outbound = {
                        mode: 'fly',
                        depart_airport: $newLeg.find('[name*="[arrive_airport]"]').val(),
                        arrive_airport: $newLeg.find('[name*="[depart_airport]"]').val(),
                        depart_date: $newLeg.find('[name*="[return_date]"]').val(),
                        depart_time: $newLeg.find('[name*="[return_time]"]').val(),
                        baggage: []
                    };
                    
                    // Check if return leg already exists
                    const nextLeg = $newLeg.next('.srt-travel-leg');
                    if (nextLeg.length === 0 || !nextLeg.data('is-return')) {
                        const $returnLeg = $(SRT.addTravelLeg(outbound));
                        $returnLeg.data('is-return', true);
                        $returnLeg.find('.srt-leg-header h4').append(' <span style="color:#666;">(Return)</span>');
                    }
                }
            }).trigger('change');
            
            // Airport code lookup
            $newLeg.find('.srt-airport-code').on('input', function() {
                const code = $(this).val().toUpperCase();
                const $nameField = $(this).siblings('.srt-airport-name');
                if (airportLookup[code]) {
                    $nameField.text(airportLookup[code]).css('color', '#666');
                } else if (code.length === 3) {
                    $nameField.text('Unknown airport code').css('color', '#c00');
                } else {
                    $nameField.text('');
                }
            }).trigger('input');
        },
        
        /**
         * Collect time blocks from form
         */
        collectTimeBlocks: function() {
            const blocks = [];
            $('.srt-time-block').each(function() {
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
            $('.srt-travel-leg').each(function() {
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
            if (document.getElementById('srt-user-preferences-form')) {
                this.loadUserPreferences();
            }
            
            // Load price alerts
            if (document.getElementById('srt-user-alerts')) {
                this.loadUserAlerts();
            }
            
            // Load linked flights
            if (document.getElementById('srt-linked-flights')) {
                this.loadLinkedFlights();
            }
            
            fetch(srtData.restUrl + 'dashboard', {
                headers: {
                    'X-WP-Nonce': srtData.nonce
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
                if ($('#srt-user-alerts').length) {
                    this.loadUserAlerts();
                }
            })
            .catch(error => {
                console.error('Error loading dashboard:', error);
                // Clear loading spinners on error
                $('#srt-flights-needed').html('<p class="srt-error">Error loading data. Please refresh the page.</p>');
                $('#srt-not-booked').html('<p class="srt-error">Error loading data. Please refresh the page.</p>');
                $('#srt-upcoming-travel').html('<p class="srt-error">Error loading data. Please refresh the page.</p>');
            });
        },
        
        /**
         * Load user preferences
         */
        loadUserPreferences: function() {
            console.log('🔧 Loading user preferences...');
            
            $.ajax({
                url: srtData.restUrl + 'user-preferences',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
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
            $('#srt-user-preferences-form').on('submit', function(e) {
                e.preventDefault();
                
                const preferences = {
                    home_airport: $('#home_airport').val(),
                    timezone: $('#timezone').val()
                };
                
                console.log('💾 Saving preferences:', preferences);
                
                $.ajax({
                    url: srtData.restUrl + 'user-preferences',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                    },
                    data: JSON.stringify(preferences),
                    contentType: 'application/json',
                    success: function(response) {
                        console.log('✅ Preferences saved:', response);
                        $('#srt-preferences-message')
                            .removeClass('error')
                            .addClass('success')
                            .text('✓ Preferences saved!')
                            .fadeIn()
                            .delay(3000)
                            .fadeOut();
                    },
                    error: function(xhr) {
                        console.error('❌ Failed to save preferences:', xhr);
                        $('#srt-preferences-message')
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
            const container = $('#srt-user-alerts');
            
            $.ajax({
                url: srtData.restUrl + 'my-alerts',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
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
                            'daily_digest': '📧 Daily Digest (2am)'
                        }[alert.alert_type] || alert.alert_type;
                        
                        const statusIcon = alert.is_active ? '✓ Active' : '✗ Inactive';
                        const statusColor = alert.is_active ? 'green' : '#999';
                        
                        html += '<tr>';
                        html += '<td>' + SRT.escapeHtml(alert.event_title) + '</td>';
                        html += '<td>' + SRT.escapeHtml(alert.route) + '</td>';
                        html += '<td>' + (alert.depart_date || 'Flexible') + '</td>';
                        html += '<td>' + alertTypeLabel + '</td>';
                        html += '<td style="color: ' + statusColor + ';">' + statusIcon + '</td>';
                        html += '<td><button class="button button-small srt-delete-alert" data-alert-id="' + alert.id + '">Delete</button></td>';
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                    container.html(html);
                    
                    // Attach delete handlers
                    $('.srt-delete-alert').on('click', function() {
                        const alertId = $(this).data('alert-id');
                        if (confirm('Delete this price alert?')) {
                            SRT.deleteAlert(alertId);
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
                url: srtData.restUrl + 'price-alerts/' + alertId,
                method: 'DELETE',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                success: function() {
                    // Reload alerts
                    SRT.loadUserAlerts();
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
            const container = $(`#srt-${sectionId}`);
            container.empty();
            
            if (events.length === 0) {
                container.append('<p class="srt-no-events">No events to display.</p>');
                return;
            }
            
            const list = $('<div class="srt-dashboard-list"></div>');
            events.forEach(event => {
                const memberInfo = event.member_name ? `<div class="srt-member-name">${this.escapeHtml(event.member_name)}</div>` : '';
                const item = $(`
                    <div class="srt-dashboard-item" data-event-id="${event.id}">
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
            const modal = $('<div class="srt-modal"><div class="srt-modal-content"></div></div>');
            
            // Get pretty event type name
            const eventTypePretty = this.getEventTypePrettyName(event.event_type);
            
            let html = `
                <span class="srt-modal-close">&times;</span>
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
                html += '<p class="srt-help-text" style="font-size: 13px; color: #666; margin-bottom: 15px;">💡 Click flight search buttons below to compare prices and set up price alerts</p>';
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
                        <div class="srt-travel-leg-details">
                            <h4>${this.escapeHtml(leg.leg_name || 'Travel Leg')}</h4>
                            <p><strong>Mode:</strong> ${leg.mode}</p>
                            <p><strong>Depart:</strong> ${departInfo}</p>
                            <p><strong>From:</strong> ${this.escapeHtml(leg.depart_location || 'TBD')} ${leg.depart_airport ? '(' + leg.depart_airport + ')' : ''}</p>
                            <p><strong>Arrive:</strong> ${arriveInfo}</p>
                            <p><strong>To:</strong> ${this.escapeHtml(leg.arrive_location || 'TBD')} ${leg.arrive_airport ? '(' + leg.arrive_airport + ')' : ''}</p>
                            ${leg.airline ? '<p><strong>Airline:</strong> ' + this.escapeHtml(leg.airline) + ' ' + this.escapeHtml(leg.flight_number) + '</p>' : ''}
                            ${leg.booked ? '<p class="srt-booked">✓ Booked</p>' : '<p class="srt-not-booked">✗ Not Booked</p>'}
                            ${needsAirports ? '<div class="srt-missing-airports" style="background: #fff3cd; border: 1px solid #ffc107; padding: 10px; margin: 10px 0; border-radius: 4px;"><p style="margin: 0; color: #856404;"><strong>⚠️ Missing Airport Codes</strong><br>Add 3-letter airport codes (e.g., BDL, BWI) to search flights and track prices. <a href="' + (srtData.eventFormUrl ? srtData.eventFormUrl + (srtData.eventFormUrl.includes('?') ? '&' : '?') + 'event_id=' + event.id : '#') + '" style="color: #0073aa;">Edit Event</a></p></div>' : ''}
                            ${canSearch ? this.generateFlightSearchLinks(leg, event, departDate, legIndex) : ''}
                            ${canSearch ? '<div class="srt-price-info" id="srt-price-info-' + legIndex + '"></div>' : ''}
                        </div>
                    `;
                });
            }
            
            if (srtData.isAdmin) {
                const eventFormUrl = srtData.eventFormUrl || '';
                if (eventFormUrl) {
                    const separator = eventFormUrl.includes('?') ? '&' : '?';
                    html += `<p><a href="${eventFormUrl}${separator}event_id=${event.id}" class="button">Edit Event</a></p>`;
                }
            }
            
            modal.find('.srt-modal-content').html(html);
            $('body').append(modal);
            modal.fadeIn();
            
            modal.find('.srt-modal-close').on('click', function() {
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
            modal.find('.srt-track-price').on('click', function() {
                const $btn = $(this);
                const eventId = $btn.data('event-id');
                const legIndex = $btn.data('leg-index');
                const origin = $btn.data('origin');
                const destination = $btn.data('destination');
                const date = $btn.data('date');
                
                SRT.showPriceTrackingModal(eventId, legIndex, origin, destination, date);
            });
            
            // Handle check price now button
            modal.find('.srt-check-price-now').on('click', function() {
                const $btn = $(this);
                const eventId = $btn.data('event-id');
                const legIndex = $btn.data('leg-index');
                
                SRT.checkPriceNow(eventId, legIndex, modal);
            });
            
            // Load price history for each flight leg
            modal.find('.srt-check-price-now').each(function() {
                const eventId = $(this).data('event-id');
                const legIndex = $(this).data('leg-index');
                SRT.loadPriceHistory(eventId, legIndex, modal);
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
            const modal = $('<div class="srt-modal"><div class="srt-modal-content"></div></div>');
            
            const dateNote = date ? ` on ${date}` : ' (flexible dates)';
            
            const html = `
                <span class="srt-modal-close">&times;</span>
                <h2>Track Flight Prices</h2>
                <p><strong>Route:</strong> ${origin} → ${destination}${dateNote}</p>
                
                <form id="srt-price-alert-form">
                    <div class="srt-form-field">
                        <label>Alert Type</label>
                        <select name="alert_type" id="alert_type" required>
                            <option value="">Select alert type...</option>
                            <option value="price_drop">Drop Below Price</option>
                            <option value="percent_drop">Drop By Percentage</option>
                            <option value="good_deal">Good Deal Alert (15% below average)</option>
                            <option value="daily_digest">📧 Daily Price Digest (2am email)</option>
                        </select>
                    </div>
                    
                    <div class="srt-form-field" id="threshold_price_field" style="display: none;">
                        <label>Target Price ($)</label>
                        <input type="number" name="threshold_price" id="threshold_price" min="0" step="0.01" placeholder="e.g., 250.00">
                        <p class="description">Alert me when price drops to this amount or below</p>
                    </div>
                    
                    <div class="srt-form-field" id="threshold_percent_field" style="display: none;">
                        <label>Percentage Drop (%)</label>
                        <input type="number" name="threshold_percent" id="threshold_percent" min="1" max="100" placeholder="e.g., 20">
                        <p class="description">Alert me when price drops by this percentage</p>
                    </div>
                    
                    <div class="srt-form-actions">
                        <button type="submit" class="button button-primary">Create Alert</button>
                        <button type="button" class="button srt-modal-close">Cancel</button>
                    </div>
                </form>
                
                <div id="srt-alert-message" style="display: none; margin-top: 15px;"></div>
            `;
            
            modal.find('.srt-modal-content').html(html);
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
            modal.find('#srt-price-alert-form').on('submit', function(e) {
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
                    url: srtData.restUrl + 'price-alerts',
                    method: 'POST',
                    beforeSend: function(xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                    },
                    data: JSON.stringify(payload),
                    contentType: 'application/json',
                    success: function(response) {
                        modal.find('#srt-alert-message')
                            .html('<p style="color: green;">✓ Price alert created! You\'ll receive an email when prices meet your criteria.</p>')
                            .show();
                        
                        setTimeout(function() {
                            modal.fadeOut(function() {
                                modal.remove();
                            });
                        }, 2000);
                    },
                    error: function(xhr) {
                        let errorMsg = 'Failed to create alert. Please try again.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMsg = xhr.responseJSON.message;
                        }
                        modal.find('#srt-alert-message')
                            .html('<p style="color: red;">✗ ' + errorMsg + '</p>')
                            .show();
                    }
                });
            });
            
            // Close handlers
            modal.find('.srt-modal-close').on('click', function() {
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
            
            const provider = srtData.geocodingProvider || 'none';
            
            if (provider === 'none') {
                console.log('Address autocomplete disabled.');
                return;
            }
            
            if (provider === 'google' && srtData.googlePlacesApiKey) {
                this.initGooglePlacesAutocomplete(locationInput);
                return;
            }
            
            if (provider === 'mapbox' && srtData.mapboxApiKey) {
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
            resultsContainer = $('<div class="srt-autocomplete-results"></div>');
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
                    SRT.searchMapboxPlaces(query, resultsContainer);
                }, 300);
            });
            
            // Hide results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#location_name, .srt-autocomplete-results').length) {
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
                script.src = `https://maps.googleapis.com/maps/api/js?key=${srtData.googlePlacesApiKey}&libraries=places&callback=initGoogleAutocomplete`;
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
                
                window.initGoogleAutocomplete = () => {
                    SRT.setupGoogleAutocomplete(locationInput);
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
                        .appendTo('#srt-event-form');
                    
                    $('<input>')
                        .attr('type', 'hidden')
                        .attr('id', 'location_longitude')
                        .attr('name', 'location_longitude')
                        .val(place.geometry.location.lng())
                        .appendTo('#srt-event-form');
                }
            });
        },
        
        /**
         * Search Mapbox places
         */
        searchMapboxPlaces: function(query, resultsContainer) {
            const apiKey = srtData.mapboxApiKey;
            // Remove type restrictions to allow searching for any location (schools, venues, etc.)
            const url = `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(query)}.json?access_token=${apiKey}&limit=8`;
            
            console.log('Searching Mapbox for:', query);
            
            $.ajax({
                url: url,
                method: 'GET',
                success: function(data) {
                    console.log('Mapbox results:', data.features.length, 'found');
                    SRT.displayMapboxResults(data.features, resultsContainer);
                },
                error: function(xhr, status, error) {
                    console.error('Mapbox API error:', error, xhr.responseText);
                    resultsContainer.html('<div class="srt-autocomplete-item" style="color: #dc3232;">Search error. Check API key.</div>').show();
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
                const item = $('<div class="srt-autocomplete-item"></div>');
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
                            .appendTo('#srt-event-form');
                        
                        $('<input>')
                            .attr('type', 'hidden')
                            .attr('id', 'location_latitude')
                            .attr('name', 'location_latitude')
                            .val(feature.geometry.coordinates[1])
                            .appendTo('#srt-event-form');
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
                baggageInfoHtml = `<p class="srt-baggage-note" style="font-size: 12px; color: #666; margin: 5px 0;">Baggage: ${baggageCount} checked bag(s)${baggageNote}</p>`;
            }
            
            return `
                <div class="srt-flight-search-links">
                    <p><strong>Search Flights:</strong></p>
                    ${baggageInfoHtml}
                    <div class="srt-flight-buttons">
                        <a href="${googleFlightsUrl}" target="_blank" rel="noopener" class="button button-small">Google Flights</a>
                        <a href="${kayakUrl}" target="_blank" rel="noopener" class="button button-small">Kayak</a>
                        <a href="${southwestUrl}" target="_blank" rel="noopener" class="button button-small">Southwest</a>
                        <button class="button button-small button-primary srt-check-price-now" data-event-id="${event.id}" data-leg-index="${legIndex}">
                            💰 Check Price Now
                        </button>
                        <button class="button button-small button-secondary srt-track-price" data-event-id="${event.id}" data-leg-index="${legIndex}" data-origin="${origin}" data-destination="${destination}" data-date="${date || ''}">
                            <span class="dashicons dashicons-bell" style="font-size: 14px; margin-top: 2px;"></span> Track Price
                        </button>
                    </div>
                    <div class="srt-price-info" id="srt-price-info-${legIndex}"></div>
                </div>
            `;
        },
        
        /**
         * Check price now
         */
        checkPriceNow: function(eventId, legIndex, modal) {
            const $container = modal.find('#srt-price-info-' + legIndex);
            $container.html('<p style="color: #666;">⏳ Checking current prices...</p>');
            
            const requestData = {
                event_id: eventId,
                leg_index: legIndex
            };
            
            console.log('🔍 SRT Price Check Request:', requestData);
            
            $.ajax({
                url: srtData.restUrl + 'check-price',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
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
                    const checked = new Date(response.checked_at).toLocaleString();
                    
                    if (!price || isNaN(price)) {
                        $container.html('<p style="color: red;">✗ Unable to parse price data</p>');
                        return;
                    }
                    
                    console.log('🔗 Google Flights URL:', response.google_flights_url);
                    console.log('🎫 Trip Type:', response.trip_type);
                    
                    let html = '<div style="background: #f0f9ff; border: 1px solid #0891b2; padding: 10px; margin: 10px 0; border-radius: 4px;">';
                    html += '<strong style="color: #0891b2; font-size: 1.2em;">$' + price.toFixed(2) + '</strong> ';
                    html += '<span style="color: #666; font-size: 0.9em;">as of ' + checked + '</span>';
                    
                    if (response.trip_type) {
                        html += '<span style="background: #e0f2fe; color: #0369a1; padding: 2px 6px; border-radius: 3px; font-size: 0.8em; margin-left: 10px;">' + response.trip_type + '</span>';
                    }
                    
                    if (response.google_flights_url) {
                        html += '<div style="margin-top: 8px;"><a href="' + response.google_flights_url + '" target="_blank" style="color: #0891b2; font-size: 0.9em; text-decoration: underline;">🔍 Verify on Google Flights →</a></div>';
                    }
                    
                    html += '</div>';
                    $container.html(html);
                    
                    // Reload price history
                    SRT.loadPriceHistory(eventId, legIndex, modal);
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
            $.ajax({
                url: srtData.restUrl + 'price-history',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                data: {
                    event_id: eventId,
                    leg_index: legIndex
                },
                success: function(response) {
                    if (response.prices && response.prices.length > 0) {
                        SRT.renderPriceHistory(eventId, legIndex, response, modal);
                    }
                },
                error: function() {
                    // Silent fail - history is optional
                }
            });
        },
        
        /**
         * Render price history
         */
        renderPriceHistory: function(eventId, legIndex, data, modal) {
            const $container = modal.find('#srt-price-info-' + legIndex);
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
            
            let html = `
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
                html += '<div style="margin-top: 10px;">' + SRT.renderMiniChart(data.prices, stats) + '</div>';
            }
            
            html += '</div>';
            
            // Append or replace
            const existing = $container.find('> div:last-child');
            if (existing.length) {
                existing.replaceWith(html);
            } else {
                $container.append(html);
            }
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
                url: srtData.restUrl + 'flight-groups',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                success: function(groups) {
                    self.renderLinkedFlights(groups);
                },
                error: function(xhr) {
                    console.error('Failed to load linked flights:', xhr);
                    $('#srt-linked-flights').html('<p>Unable to load flight groups.</p>');
                }
            });
        },
        
        /**
         * Render linked flights with pricing comparison
         */
        renderLinkedFlights: function(groups) {
            const $container = $('#srt-linked-flights');
            
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
                    <div class="srt-linked-flight-group">
                        <div class="srt-linked-flight-header">
                            <div class="srt-linked-flight-title">
                                ${legs[0].leg.depart_airport} ↔️ ${legs[0].leg.arrive_airport}
                                <span class="srt-flight-group-badge">Group ${group.group_id.substring(3, 8)}</span>
                            </div>
                        </div>
                        
                        <div class="srt-flight-legs">
                            ${legs.map((legData, idx) => `
                                <div class="srt-flight-leg-badge">
                                    ${idx === 0 ? '✈️ Outbound' : '🔄 Return'}: ${legData.leg.depart_date}
                                    <small>(${legData.event_title})</small>
                                </div>
                            `).join('')}
                        </div>
                        
                        <div class="srt-price-comparison">
                            <div class="srt-price-row">
                                <span class="srt-price-label">Individual One-Ways:</span>
                                <span class="srt-price-value">
                                    $${individualTotal.toFixed(2)}
                                    ${bestOption === 'individual' ? '<span class="srt-price-better">✓ Best Deal</span>' : ''}
                                </span>
                            </div>
                            ${roundTripPrice > 0 ? `
                                <div class="srt-price-row">
                                    <span class="srt-price-label">Round-Trip:</span>
                                    <span class="srt-price-value">
                                        $${roundTripPrice.toFixed(2)}
                                        ${bestOption === 'roundtrip' ? '<span class="srt-price-better">✓ Best Deal</span>' : ''}
                                    </span>
                                </div>
                            ` : ''}
                            ${savings > 0 ? `
                                <div class="srt-price-row">
                                    <span class="srt-price-label">Potential Savings:</span>
                                    <span class="srt-price-value srt-price-savings">
                                        💰 $${savings.toFixed(2)}
                                    </span>
                                </div>
                            ` : ''}
                        </div>
                        
                        <button class="srt-unlink-flight-btn" data-group-id="${group.group_id}">
                            Unlink Flights
                        </button>
                    </div>
                `;
            });
            
            $container.html(html);
            
            // Attach unlink handlers
            $container.find('.srt-unlink-flight-btn').on('click', function() {
                const groupId = $(this).data('group-id');
                if (confirm('Are you sure you want to unlink these flights?')) {
                    SRT.unlinkFlightGroup(groupId);
                }
            });
        },
        
        /**
         * Link two flights together
         */
        linkFlights: function(eventIds, legIndices) {
            const self = this;
            
            $.ajax({
                url: srtData.restUrl + 'link-flights',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    event_ids: eventIds,
                    leg_indices: legIndices
                }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
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
                url: srtData.restUrl + 'flight-group/' + groupId,
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                success: function(group) {
                    // Unlink each leg
                    const promises = group.legs.map(legData => {
                        return $.ajax({
                            url: srtData.restUrl + 'unlink-flight',
                            method: 'POST',
                            contentType: 'application/json',
                            data: JSON.stringify({
                                event_id: legData.event_id,
                                leg_index: legData.leg_index
                            }),
                            beforeSend: function(xhr) {
                                xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
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
            const container = document.getElementById('srt-invitations-list');
            const memberCodeEl = document.getElementById('srt-member-code');
            if (!container) return;
            
            $.ajax({
                url: srtData.restUrl + 'invitations',
                method: 'GET',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                success: function(data) {
                    console.log('Invitations loaded:', data);
                    
                    // Display member code
                    if (memberCodeEl && data.member_code) {
                        memberCodeEl.textContent = data.member_code;
                    }
                    
                    // Render invitations list
                    if (Object.keys(data.invitations).length === 0) {
                        container.innerHTML = '<p class="srt-no-invitations">No invitations yet. Generate a code to invite your parents.</p>';
                    } else {
                        let html = '<h4>Your Invitations</h4><div class="srt-invitations-grid">';
                        
                        for (const [code, invite] of Object.entries(data.invitations)) {
                            const statusClass = 'srt-invite-' + invite.status;
                            const statusIcon = invite.status === 'accepted' ? '✓' : 
                                             invite.status === 'pending' ? '⏳' : 
                                             invite.status === 'revoked' ? '🚫' : '❌';
                            
                            html += `
                                <div class="srt-invitation-card ${statusClass}">
                                    <div class="srt-invitation-header">
                                        <span class="srt-invitation-status">${statusIcon} ${invite.status.toUpperCase()}</span>
                                        <code class="srt-invitation-code">${code}</code>
                                    </div>
                                    <div class="srt-invitation-details">
                                        <p><strong>Created:</strong> ${new Date(invite.created_at).toLocaleDateString()}</p>
                            `;
                            
                            if (invite.status === 'accepted' && invite.parent_name) {
                                html += `<p><strong>Used by:</strong> ${invite.parent_name}</p>`;
                                html += `<p><small>${invite.parent_email}</small></p>`;
                            }
                            
                            if (invite.status === 'pending') {
                                html += `
                                    <button class="button button-small srt-revoke-invite" data-code="${code}">
                                        Revoke
                                    </button>
                                    <button class="button button-small srt-copy-code" data-code-value="${code}">
                                        <span class="dashicons dashicons-clipboard"></span> Copy
                                    </button>
                                `;
                            }
                            
                            html += '</div></div>';
                        }
                        
                        html += '</div>';
                        container.innerHTML = html;
                        
                        // Attach event handlers
                        $('.srt-revoke-invite').on('click', function() {
                            SRT.revokeInvitation($(this).data('code'));
                        });
                        $('.srt-copy-code[data-code-value]').on('click', function() {
                            SRT.copyToClipboard($(this).data('code-value'));
                        });
                    }
                },
                error: function(xhr) {
                    console.error('Failed to load invitations:', xhr);
                    container.innerHTML = '<p class="srt-error">Failed to load invitations.</p>';
                }
            });
        },
        
        /**
         * Generate new invitation
         */
        generateInvitation: function() {
            const button = $('#srt-generate-invite');
            button.prop('disabled', true).text('Generating...');
            
            $.ajax({
                url: srtData.restUrl + 'invite/generate',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                success: function(response) {
                    console.log('Invitation generated:', response);
                    
                    // Fetch registration page URL from backend
                    $.ajax({
                        url: srtData.restUrl + 'registration-url',
                        method: 'GET',
                        success: function(urlResponse) {
                            const code = response.invitation.code;
                            const registrationUrl = urlResponse.url + '?invite=' + code;
                            SRT.showInvitationModal(code, registrationUrl);
                            button.prop('disabled', false).html('<span class="dashicons dashicons-email"></span> Generate One-Time Invite Code');
                        },
                        error: function(xhr) {
                            console.error('Failed to get registration URL:', xhr);
                            // Fallback to current domain
                            const code = response.invitation.code;
                            const registrationUrl = window.location.origin + '/sc-register/?invite=' + code;
                            SRT.showInvitationModal(code, registrationUrl);
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
                <div id="srt-invite-modal" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); display: flex; align-items: center; justify-content: center; z-index: 999999;">
                    <div style="background: white; padding: 30px; border-radius: 12px; max-width: 600px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                        <h2 style="margin-top: 0; color: #1e3a8a;">✓ Invitation Code Generated!</h2>
                        <p style="color: #666; margin-bottom: 20px;">Share this link or code with your parent to register and link to your account.</p>
                        
                        <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Invitation Link:</label>
                            <input type="text" id="srt-invite-url" value="${registrationUrl}" readonly style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-family: monospace; font-size: 14px;">
                        </div>
                        
                        <div style="background: #f3f4f6; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px; color: #374151;">Code Only:</label>
                            <input type="text" id="srt-invite-code" value="${code}" readonly style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 6px; font-family: monospace; font-size: 18px; text-align: center; font-weight: 700;">
                        </div>
                        
                        <div style="background: #dbeafe; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #3b82f6;">
                            <p style="margin: 0; font-size: 14px; color: #1e40af;">
                                <strong>Note:</strong> This code can only be used once and expires in 7 days.
                            </p>
                        </div>
                        
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <button id="srt-copy-link" class="button button-primary" style="flex: 1;">
                                <span class="dashicons dashicons-clipboard"></span> Copy Link
                            </button>
                            <button id="srt-copy-code-only" class="button button-secondary" style="flex: 1;">
                                <span class="dashicons dashicons-clipboard"></span> Copy Code
                            </button>
                            <button id="srt-close-modal" class="button" style="flex: 1;">
                                Close
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            // Add modal to page
            $('body').append(modalHtml);
            
            // Copy link button
            $('#srt-copy-link').on('click', function() {
                const input = document.getElementById('srt-invite-url');
                input.select();
                document.execCommand('copy');
                $(this).html('<span class="dashicons dashicons-yes"></span> Copied!').prop('disabled', true);
                setTimeout(() => {
                    $(this).html('<span class="dashicons dashicons-clipboard"></span> Copy Link').prop('disabled', false);
                }, 2000);
            });
            
            // Copy code button
            $('#srt-copy-code-only').on('click', function() {
                const input = document.getElementById('srt-invite-code');
                input.select();
                document.execCommand('copy');
                $(this).html('<span class="dashicons dashicons-yes"></span> Copied!').prop('disabled', true);
                setTimeout(() => {
                    $(this).html('<span class="dashicons dashicons-clipboard"></span> Copy Code').prop('disabled', false);
                }, 2000);
            });
            
            // Close modal
            $('#srt-close-modal, #srt-invite-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#srt-invite-modal').remove();
                }
            });
            
            SRT.loadInvitations(); // Reload list
        },
        
        /**
         * Revoke invitation
         */
        revokeInvitation: function(code) {
            if (!confirm('Are you sure you want to revoke this invitation code?')) {
                return;
            }
            
            $.ajax({
                url: srtData.restUrl + 'invite/' + code + '/revoke',
                method: 'POST',
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                success: function(response) {
                    console.log('Invitation revoked:', response);
                    SRT.loadInvitations(); // Reload list
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
            const messageEl = $('#srt-code-message');
            messageEl.removeClass('success error').text('Linking...').show();
            
            $.ajax({
                url: srtData.restUrl + 'invite/accept',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ code: code }),
                beforeSend: function(xhr) {
                    xhr.setRequestHeader('X-WP-Nonce', srtData.nonce);
                },
                success: function(response) {
                    console.log('Code accepted:', response);
                    messageEl.removeClass('error').addClass('success').text('✓ Successfully linked to ' + response.member.name + '!');
                    $('#srt-parent-code-input').val('');
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
        SRT.init();
        
        // Debug: Check for invitation elements
        console.log('=== Invitation Elements Check ===');
        console.log('srt-invitations-list exists:', $('#srt-invitations-list').length > 0);
        console.log('srt-generate-invite button exists:', $('#srt-generate-invite').length > 0);
        console.log('srt-member-code exists:', $('#srt-member-code').length > 0);
        console.log('================================');
        
        // Initialize invitations if element exists
        if ($('#srt-invitations-list').length) {
            SRT.loadInvitations();
        }
        
        // Generate invitation button
        $(document).on('click', '#srt-generate-invite', function(e) {
            e.preventDefault();
            SRT.generateInvitation();
        });
        
        // Parent code form
        $(document).on('submit', '#srt-parent-code-form', function(e) {
            e.preventDefault();
            const code = $('#srt-parent-code-input').val().trim().toUpperCase();
            if (code) {
                SRT.submitParentCode(code);
            }
        });
        
        // Copy code buttons (permanent member code)
        $(document).on('click', '.srt-copy-code[data-code-target]', function() {
            const targetId = $(this).data('code-target');
            const codeText = $('#' + targetId).text();
            if (codeText && codeText !== '---') {
                SRT.copyToClipboard(codeText);
            }
        });
    });
    
})(jQuery);

