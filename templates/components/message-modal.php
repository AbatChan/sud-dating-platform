<div id="message-modal" class="modal">
    <div class="sud-modal-content">
        <span class="close-modal">×</span>
        <h3 id="message-modal-title">Message Title</h3>
        <div id="message-modal-body">
            <p>This is the message content.</p>
        </div>
        <div class="modal-actions" id="message-modal-actions">
            <button type="button" class="btn btn-primary close-modal-btn" id="message-modal-confirm-btn">OK</button>
            <button type="button" class="btn btn-secondary close-modal-btn" id="message-modal-cancel-btn" style="display: none;">Cancel</button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        // Message modal handling
        $('.message-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name');
            
            $('#recipient-name').text(userName);
            $('#receiver-id').val(userId);
            $('#message-modal').css('display', 'flex');
        });
        
        // Close modal on X click
        $('.close').on('click', function() {
            $('#message-modal').hide();
        });
        
        // Close modal when clicking outside
        $(window).on('click', function(e) {
            if ($(e.target).is('#message-modal')) {
                $('#message-modal').hide();
            }
        });
        
        // Handle form submission via AJAX
        $('#message-form').on('submit', function(e) {
            e.preventDefault();
            
            const receiverId = $('#receiver-id').val();
            const messageText = $('#message-text').val().trim();
            
            if (!messageText) {
                return;
            }
            
            // Show loading state
            const submitBtn = $(this).find('button[type="submit"]');
            const originalText = submitBtn.text();
            submitBtn.prop('disabled', true).text('Sending...');
            
            // Send message via AJAX
            $.ajax({
                url: $(this).attr('action'),
                type: 'POST',
                data: {
                    receiver_id: receiverId,
                    message_text: messageText
                },
                success: function(response) {
                    // Clear form and hide modal
                    $('#message-text').val('');
                    $('#message-modal').hide();
                    
                    // Show success message
                    if (response.success) {
                        // Create toast notification
                        const toast = $('<div class="toast-notification"><div class="toast-icon"><i class="fas fa-check"></i></div><div class="toast-content"><div class="toast-title">Message Sent!</div><div class="toast-message">Your message has been sent successfully.</div></div><div class="toast-close">&times;</div></div>');
                        $('body').append(toast);
                        
                        setTimeout(function() {
                            toast.addClass('show');
                        }, 100);
                        
                        setTimeout(function() {
                            toast.removeClass('show');
                            setTimeout(function() {
                                toast.remove();
                            }, 300);
                        }, 5000);
                        
                        // If response contains a redirect URL, navigate there
                        if (response.redirect) {
                            window.location.href = response.redirect;
                        }
                    } else {
                        alert('Error sending message: ' + (response.message || 'Please try again.'));
                    }
                },
                error: function() {
                    alert('Failed to send message. Please try again.');
                },
                complete: function() {
                    // Restore button state
                    submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Handle toast close button
        $(document).on('click', '.toast-close', function() {
            const toast = $(this).closest('.toast-notification');
            toast.removeClass('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        });
    });
</script>