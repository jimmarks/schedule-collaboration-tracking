/**
 * Summer Regiment Tracker JavaScript
 */

(function($) {
    'use strict';
    
    let timeBlockIndex = 0;
    let flightIndex = 0;
    
    $(document).ready(function() {
        // Initialize counters based on existing items
        timeBlockIndex = $('#srt-time-blocks .srt-time-block').length;
        flightIndex = $('#srt-flights .srt-flight').length;
        
        // Add time block
        $('#srt-add-time-block').on('click', function() {
            const blockHtml = `
                <div class="srt-time-block">
                    <select name="time_blocks[${timeBlockIndex}][block_type]" required>
                        <option value="">Select Type</option>
                        <option value="practice">Practice</option>
                        <option value="travel">Travel</option>
                        <option value="admin">Admin</option>
                        <option value="performance">Performance</option>
                        <option value="meal">Meal</option>
                        <option value="other">Other</option>
                    </select>
                    <input type="time" name="time_blocks[${timeBlockIndex}][start_time]" required>
                    <input type="time" name="time_blocks[${timeBlockIndex}][end_time]" required>
                    <input type="text" name="time_blocks[${timeBlockIndex}][notes]" placeholder="Notes">
                    <button type="button" class="srt-remove-block">Remove</button>
                </div>
            `;
            $('#srt-time-blocks').append(blockHtml);
            timeBlockIndex++;
        });
        
        // Remove time block
        $(document).on('click', '.srt-remove-block', function() {
            $(this).closest('.srt-time-block').remove();
        });
        
        // Add flight leg
        $('#srt-add-flight').on('click', function() {
            const legNumber = flightIndex + 1;
            const flightHtml = `
                <div class="srt-flight">
                    <h5>Leg ${legNumber}</h5>
                    <input type="hidden" name="flights[${flightIndex}][leg_number]" value="${legNumber}">
                    <input type="text" name="flights[${flightIndex}][departure_airport]" 
                           placeholder="Departure Airport (e.g., ORD)" required>
                    <input type="text" name="flights[${flightIndex}][arrival_airport]" 
                           placeholder="Arrival Airport (e.g., LAX)" required>
                    <input type="datetime-local" name="flights[${flightIndex}][departure_time]" required>
                    <input type="datetime-local" name="flights[${flightIndex}][arrival_time]" required>
                    <label>
                        <input type="checkbox" name="flights[${flightIndex}][is_booked]" value="1">
                        Booked
                    </label>
                    <input type="text" name="flights[${flightIndex}][booking_reference]" 
                           placeholder="Booking Reference">
                    <button type="button" class="srt-remove-flight">Remove</button>
                </div>
            `;
            $('#srt-flights').append(flightHtml);
            flightIndex++;
        });
        
        // Remove flight
        $(document).on('click', '.srt-remove-flight', function() {
            $(this).closest('.srt-flight').remove();
            // Renumber remaining flights
            $('#srt-flights .srt-flight').each(function(index) {
                $(this).find('h5').text('Leg ' + (index + 1));
                $(this).find('input[name*="leg_number"]').val(index + 1);
            });
        });
        
        // Save event form
        $('#srt-event-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = $(this).serialize() + '&action=srt_save_event&nonce=' + srtAjax.nonce;
            
            $.ajax({
                url: srtAjax.ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    const messageDiv = $('#srt-form-message');
                    if (response.success) {
                        messageDiv.removeClass('error').addClass('success').text(response.data.message).show();
                        // Update hidden event_id if it was a new event
                        if (response.data.event_id) {
                            $('input[name="event_id"]').val(response.data.event_id);
                        }
                    } else {
                        messageDiv.removeClass('success').addClass('error').text(response.data.message).show();
                    }
                    
                    // Scroll to message
                    $('html, body').animate({
                        scrollTop: messageDiv.offset().top - 100
                    }, 500);
                },
                error: function() {
                    $('#srt-form-message').removeClass('success').addClass('error')
                        .text('An error occurred. Please try again.').show();
                }
            });
        });
        
        // Delete event
        $('#srt-delete-event').on('click', function() {
            if (!confirm('Are you sure you want to delete this event? This action cannot be undone.')) {
                return;
            }
            
            const eventId = $(this).data('event-id');
            
            $.ajax({
                url: srtAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'srt_delete_event',
                    event_id: eventId,
                    nonce: srtAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        window.location.href = window.location.href.split('?')[0];
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
        
        // Calendar navigation
        $(document).on('click', '.srt-prev-month', function() {
            let month = parseInt($(this).data('month'));
            let year = parseInt($(this).data('year'));
            
            month--;
            if (month < 1) {
                month = 12;
                year--;
            }
            
            loadCalendarMonth(month, year);
        });
        
        $(document).on('click', '.srt-next-month', function() {
            let month = parseInt($(this).data('month'));
            let year = parseInt($(this).data('year'));
            
            month++;
            if (month > 12) {
                month = 1;
                year++;
            }
            
            loadCalendarMonth(month, year);
        });
        
        function loadCalendarMonth(month, year) {
            $.ajax({
                url: srtAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'srt_get_calendar_month',
                    month: month,
                    year: year,
                    nonce: srtAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('.srt-calendar-wrapper').html(response.data.html);
                    }
                }
            });
        }
        
        // Mark flight as booked
        $(document).on('click', '.srt-mark-booked', function() {
            const button = $(this);
            const flightId = button.data('flight-id');
            
            $.ajax({
                url: srtAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'srt_mark_flight_booked',
                    flight_id: flightId,
                    nonce: srtAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        button.closest('tr').fadeOut(function() {
                            $(this).remove();
                            // Reload page if no more unbooked flights
                            if ($('.srt-mark-booked').length === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                }
            });
        });
        
        // Event indicator click - could be extended to show event details
        $(document).on('click', '.srt-event-indicator', function() {
            const eventId = $(this).data('event-id');
            // You could implement a modal or redirect to event edit page
            console.log('Event clicked:', eventId);
        });
    });
})(jQuery);
