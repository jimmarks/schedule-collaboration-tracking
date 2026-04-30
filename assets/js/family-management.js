jQuery(document).ready(function($) {
    console.log('FTT FAMILY MANAGEMENT: jQuery ready');
    
    // Toggle pending invitations
    $(document).on('click', '.ftt-toggle-pending-invitations', function(e) {
        e.preventDefault();
        $(this).toggleClass('expanded');
        $('.ftt-invitations-list').slideToggle(200);
    });
    
    // Note: Category expand/collapse is handled in templates/family-management.php inline script
    
    // Add Child Modal
    $('#ftt-add-child-btn').on('click', function() {
        $('#ftt-child-modal-title').text('Add Child');
        $('#ftt-child-form')[0].reset();
        $('#ftt-child-id').val('');
        $('#ftt-child-form-message').removeClass('success error').text('');
        $('#ftt-child-modal').fadeIn();
    });
    
    // Submit Child Form
    $('#ftt-child-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Child form submitted');
        
        var childId = $('#ftt-child-id').val();
        var endpoint = childId ? '/wp-json/ftt/v1/edit-child' : '/wp-json/ftt/v1/add-child';
        var formData = {
            child_id: childId,
            first_name: $('#child-first-name').val(),
            last_name: $('#child-last-name').val(),
            email: $('#child-email').val(),
            age: $('#child-age').val(),
            grade: $('#child-grade').val(),
            school: $('#child-school').val(),
            color: $('#child-color').val()
        };
        
        // Add group_id if available (from family-management.php template)
        if (typeof fttGroupContext !== 'undefined' &&  fttGroupContext.groupId) {
            formData.group_id = fttGroupContext.groupId;
        }
        
        console.log('Submitting to:', endpoint, formData);
        
        $.ajax({
            url: endpoint,
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                console.log('Success:', response);
                $('#ftt-child-form-message')
                    .removeClass('error')
                    .addClass('success')
                    .text(response.message || 'Child saved successfully!');
                
                setTimeout(function() {
                    location.reload();
                }, 1500);
            },
            error: function(xhr, status, error) {
                console.error('Error:', xhr.responseJSON);
                var message = xhr.responseJSON?.message || 'Failed to save child';
                $('#ftt-child-form-message')
                    .removeClass('success')
                    .addClass('error')
                    .text(message);
            }
        });
    });
    
    // Edit Child
    $(document).on('click', '.ftt-edit-child', function() {
        var childId = $(this).data('child-id');
        console.log('Editing child:', childId);
        
        $('#ftt-child-modal-title').text('Edit Child');
        $('#ftt-child-id').val(childId);
        $('#ftt-child-form-message').removeClass('success error').text('');
        
        // Load child data via REST API
        $.ajax({
            url: '/wp-json/ftt/v1/get-family-members',
            method: 'GET',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            success: function(response) {
                var child = response.children.find(c => c.id == childId);
                if (child) {
                    $('#child-first-name').val(child.first_name);
                    $('#child-last-name').val(child.last_name);
                    $('#child-email').val(child.email);
                    $('#child-age').val(child.age);
                    $('#child-grade').val(child.grade);
                    $('#child-school').val(child.school);
                    $('#child-color').val(child.color || '#2196F3');
                }
                $('#ftt-child-modal').fadeIn();
            },
            error: function() {
                alert('Failed to load child data');
            }
        });
    });
    
    // Remove Child
    $(document).on('click', '.ftt-remove-child', function() {
        var childId = $(this).data('child-id');
        
        if (!confirm('Are you sure you want to remove this child from your account?')) {
            return;
        }
        
        console.log('Removing child:', childId);
        
        $.ajax({
            url: '/wp-json/ftt/v1/remove-child',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ child_id: childId }),
            success: function(response) {
                console.log('Child removed:', response);
                location.reload();
            },
            error: function(xhr) {
                console.error('Error removing child:', xhr.responseJSON);
                alert(xhr.responseJSON?.message || 'Failed to remove child');
            }
        });
    });
    
    // Invite Adult Modal
    $('#ftt-invite-adult-btn').on('click', function() {
        $('#ftt-invite-adult-form')[0].reset();
        $('#ftt-invite-adult-message').removeClass('success error').text('');
        $('#ftt-invite-adult-modal').fadeIn();
    });
    
    // Submit Invite Adult Form
    $('#ftt-invite-adult-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Adult invitation form submitted');
        
        var formData = {
            email: $('#adult-email').val(),
            relationship: $('#adult-relationship').val()
        };
        
        console.log('Sending invitation:', formData);
        
        $.ajax({
            url: '/wp-json/ftt/v1/invite-adult',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                console.log('Invitation sent:', response);
                $('#ftt-invite-adult-message')
                    .removeClass('error')
                    .addClass('success')
                    .html('Invitation sent! They can accept here:<br><input type="text" value="' + response.invite_url + '" readonly style="width:100%;margin-top:5px;">');
            },
            error: function(xhr) {
                console.error('Error sending invitation:', xhr.responseJSON);
                var message = xhr.responseJSON?.message || 'Failed to send invitation';
                $('#ftt-invite-adult-message')
                    .removeClass('success')
                    .addClass('error')
                    .text(message);
            }
        });
    });
    
    // Remove Adult
    $(document).on('click', '.ftt-remove-adult', function() {
        var adultId = $(this).data('adult-id');
        
        if (!confirm('Are you sure you want to revoke access for this adult?')) {
            return;
        }
        
        console.log('Removing adult:', adultId);
        
        $.ajax({
            url: '/wp-json/ftt/v1/remove-adult',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ adult_id: adultId }),
            success: function(response) {
                console.log('Adult removed:', response);
                location.reload();
            },
            error: function(xhr) {
                console.error('Error removing adult:', xhr.responseJSON);
                alert(xhr.responseJSON?.message || 'Failed to remove adult');
            }
        });
    });
    
    // Save Event Preferences
    $('#ftt-event-preferences-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Saving event preferences');
        
        var visibleCategories = [];
        $('input[name="visible_categories[]"]:checked').each(function() {
            visibleCategories.push($(this).val());
        });
        
        console.log('Visible categories:', visibleCategories);
        
        $.ajax({
            url: '/wp-json/ftt/v1/save-event-preferences',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ visible_categories: visibleCategories }),
            success: function(response) {
                console.log('Preferences saved:', response);
                $('#ftt-preferences-message')
                    .removeClass('error')
                    .addClass('success')
                    .text('Preferences saved successfully!')
                    .fadeIn();
                
                setTimeout(function() {
                    $('#ftt-preferences-message').fadeOut();
                }, 3000);
            },
            error: function(xhr) {
                console.error('Error saving preferences:', xhr.responseJSON);
                $('#ftt-preferences-message')
                    .removeClass('success')
                    .addClass('error')
                    .text('Failed to save preferences')
                    .fadeIn();
            }
        });
    });

    // Save User Settings (Home Airport and Timezone)
    $('#ftt-user-preferences-form').on('submit', function(e) {
        e.preventDefault();
        console.log('Saving user settings');
        
        var formData = {
            home_airport: $('#home_airport').val(),
            timezone: $('#timezone').val()
        };
        
        console.log('Form data:', formData);
        
        $.ajax({
            url: '/wp-json/ftt/v1/user-preferences',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify(formData),
            success: function(response) {
                console.log('Settings saved:', response);
                $('#ftt-settings-message')
                    .removeClass('error')
                    .addClass('success')
                    .text('Settings saved successfully!')
                    .fadeIn();
                
                setTimeout(function() {
                    $('#ftt-settings-message').fadeOut();
                }, 3000);
            },
            error: function(xhr) {
                console.error('Error saving settings:', xhr.responseJSON);
                $('#ftt-settings-message')
                    .removeClass('success')
                    .addClass('error')
                    .text('Failed to save settings')
                    .fadeIn();
            }
        });
    });
    
    // Cancel Invitation
    $(document).on('click', '.ftt-cancel-invite', function() {
        var inviteCode = $(this).data('invite-code');
        
        if (!confirm('Are you sure you want to cancel this invitation?')) {
            return;
        }
        
        console.log('Canceling invitation:', inviteCode);
        
        $.ajax({
            url: '/wp-json/ftt/v1/cancel-invitation',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ invite_code: inviteCode }),
            success: function(response) {
                console.log('Invitation cancelled:', response);
                location.reload();
            },
            error: function(xhr) {
                console.error('Error canceling invitation:', xhr.responseJSON);
                alert(xhr.responseJSON?.message || 'Failed to cancel invitation');
            }
        });
    });
    
    // Resend Invitation
    $(document).on('click', '.ftt-resend-invite', function() {
        var inviteCode = $(this).data('invite-code');
        var $button = $(this);
        var originalHtml = $button.html();
        
        console.log('Resending invitation:', inviteCode);
        
        // Show loading state
        $button.prop('disabled', true).html('<span class="dashicons dashicons-update spinning"></span> Sending...');
        
        $.ajax({
            url: '/wp-json/ftt/v1/resend-invitation',
            method: 'POST',
            beforeSend: function(xhr) {
                xhr.setRequestHeader('X-WP-Nonce', fttFamilyMgmt.nonce);
            },
            contentType: 'application/json',
            data: JSON.stringify({ invite_code: inviteCode }),
            success: function(response) {
                console.log('Invitation resent:', response);
                $button.html('<span class="dashicons dashicons-yes"></span> Sent!');
                
                setTimeout(function() {
                    $button.prop('disabled', false).html(originalHtml);
                }, 2000);
            },
            error: function(xhr) {
                console.error('Error resending invitation:', xhr.responseJSON);
                alert(xhr.responseJSON?.message || 'Failed to resend invitation');
                $button.prop('disabled', false).html(originalHtml);
            }
        });
    });
    
    // Close Modal (both X button and Cancel button)
    $('.ftt-modal-close, .ftt-modal-close-x').on('click', function() {
        $(this).closest('.ftt-modal').fadeOut();
    });
    
    // Close on outside click
    $('.ftt-modal').on('click', function(e) {
        if ($(e.target).hasClass('ftt-modal')) {
            $(this).fadeOut();
        }
    });
    
    console.log('FTT FAMILY MANAGEMENT: All event handlers attached');
});
