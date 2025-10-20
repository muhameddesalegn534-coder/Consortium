<?php
// message_system.php

// Display success message if set
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear the message after displaying
}

// Display error message if set
$error_message = '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']); // Clear the message after displaying
}
?>
<!-- Floating Action Button for Messages -->
<div class="fixed right-6 bottom-6 z-40">
    <button id="messageBarToggle"
        class="flex items-center justify-center w-14 h-14 bg-primary-600 text-white rounded-full shadow-lg hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 transition-all duration-300 hover:shadow-xl">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
        </svg>
    </button>
</div>

<!-- Message Panel (slides in from right) -->
<div id="messagePanel"
    class="fixed inset-y-0 right-0 z-50 w-96 bg-white shadow-xl transform translate-x-full transition-transform duration-300 ease-in-out p-6 flex flex-col rounded-l-xl border-l border-gray-200">
    <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-200">
        <h3 class="text-xl font-bold text-gray-800">Compose Message</h3>
        <button id="closeMessagePanelBtn"
            class="text-gray-500 hover:text-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-300 rounded-full p-1 transition duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                xmlns="http://www.w3.org/2000/svg">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
    
    <div class="mb-4">
        <label class="block text-gray-700 text-sm font-semibold mb-2">Subject:</label>
        <input type="text" id="messageSubject" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-200 shadow-sm" placeholder="Message subject">
    </div>
    
    <div class="mb-4 flex-1">
        <label class="block text-gray-700 text-sm font-semibold mb-2">Message:</label>
        <textarea id="messageTextarea"
            class="w-full h-48 p-4 border border-gray-300 rounded-lg resize-none focus:ring-2 focus:ring-primary-500 focus:border-transparent transition duration-200 shadow-sm"
            placeholder="Write your message here..."></textarea>
    </div>
    
    <div class="flex space-x-3">
        <button class="flex-1 border border-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-50 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-sm">
            Save Draft
        </button>
        <button id="sendMessageBtn"
            class="flex-1 bg-primary-600 text-white py-2 px-4 rounded-lg hover:bg-primary-700 transition duration-300 focus:outline-none focus:ring-2 focus:ring-primary-500 shadow-md font-medium">
            Send Message
        </button>
    </div>
</div>

<!-- Custom Message Box for alerts -->
<div id="customMessageBox" class="fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full text-center">
        <h4 id="messageBoxTitle" class="text-xl font-semibold text-gray-800 mb-4"></h4>
        <p id="messageBoxContent" class="text-gray-700 mb-6"></p>
        <button id="messageBoxCloseBtn" class="bg-primary-600 text-white py-2 px-5 rounded-lg hover:bg-primary-700 transition duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500">
            OK
        </button>
    </div>
</div>

<script>
// Message system functionality
const messageBarToggle = document.getElementById('messageBarToggle');
const messagePanel = document.getElementById('messagePanel');
const closeMessagePanelBtn = document.getElementById('closeMessagePanelBtn');
const messageTextarea = document.getElementById('messageTextarea');
const sendMessageBtn = document.getElementById('sendMessageBtn');
const messageSubject = document.getElementById('messageSubject');

// Custom Message Box elements
const customMessageBox = document.getElementById('customMessageBox');
const messageBoxTitle = document.getElementById('messageBoxTitle');
const messageBoxContent = document.getElementById('messageBoxContent');
const messageBoxCloseBtn = document.getElementById('messageBoxCloseBtn');

/**
 * Displays a custom message box instead of the native alert.
 * @param {string} title - The title of the message box.
 * @param {string} message - The content message.
 */
function showCustomMessageBox(title, message) {
    messageBoxTitle.textContent = title;
    messageBoxContent.textContent = message;
    customMessageBox.classList.remove('hidden');
}

// Event listener for closing the custom message box
messageBoxCloseBtn.addEventListener('click', () => {
    customMessageBox.classList.add('hidden');
});

// Toggle Message Panel
messageBarToggle.addEventListener('click', () => {
    messagePanel.classList.toggle('translate-x-full');
});

closeMessagePanelBtn.addEventListener('click', () => {
    messagePanel.classList.add('translate-x-full');
});

// Handle Send Message
sendMessageBtn.addEventListener('click', () => {
    const message = messageTextarea.value.trim();
    const subject = messageSubject.value.trim();
    
    if (message) {
        // Show loading state
        const originalText = sendMessageBtn.textContent;
        sendMessageBtn.innerHTML = '<span class="flex items-center justify-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Sending...</span>';
        sendMessageBtn.disabled = true;
        
        // Send message via AJAX
        const formData = new FormData();
        formData.append('subject', subject);
        formData.append('message', message);
        
        fetch('send_message.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showCustomMessageBox('Message Sent!', data.message);
                // Clear form
                messageTextarea.value = '';
                messageSubject.value = '';
                messagePanel.classList.add('translate-x-full');
            } else {
                showCustomMessageBox('Error', data.message);
            }
            
            // Reset button
            sendMessageBtn.textContent = originalText;
            sendMessageBtn.disabled = false;
        })
        .catch(error => {
            showCustomMessageBox('Error', 'Failed to send message: ' + error.message);
            
            // Reset button
            sendMessageBtn.textContent = originalText;
            sendMessageBtn.disabled = false;
        });
    } else {
        showCustomMessageBox('Error', 'Message cannot be empty.');
    }
});

// Display success or error messages on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!empty($success_message)): ?>
        showCustomMessageBox('Success', '<?php echo addslashes($success_message); ?>');
    <?php endif; ?>
    
    <?php if (!empty($error_message)): ?>
        showCustomMessageBox('Error', '<?php echo addslashes($error_message); ?>');
    <?php endif; ?>
});
</script>