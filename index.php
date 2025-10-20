<script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM Elements
        const sidebar = document.getElementById('sidebar');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mainContentTitle = document.getElementById('mainContentTitle');
        const mainContentArea = document.getElementById('mainContentArea'); // Get the main content area

        // Custom Message Box elements
        const customMessageBox = document.getElementById('customMessageBox');
        const messageBoxTitle = document.getElementById('messageBoxTitle');
        const messageBoxContent = document.getElementById('messageBoxContent');
        const messageBoxCloseBtn = document.getElementById('messageBoxCloseBtn');

        // Message Panel Elements
        const messageBarToggle = document.getElementById('messageBarToggle');
        const messagePanel = document.getElementById('messagePanel');
        const closeMessagePanelBtn = document.getElementById('closeMessagePanelBtn');
        const messageTextarea = document.getElementById('messageTextarea');
        const sendMessageBtn = document.getElementById('sendMessageBtn');
        const messageSubject = document.querySelector('#messagePanel input[type="text"]');
        const recipientType = document.querySelector('#messagePanel select');

        // Upload Form Elements
        const uploadForm = document.getElementById('uploadForm');
        const documentNameSelect = document.getElementById('documentNameSelect');
        const customDocumentNameContainer = document.getElementById('customDocumentNameContainer');
        const customDocumentNameInput = document.getElementById('customDocumentNameInput');
        const reportFileInput = document.getElementById('reportFile');
        const selectedFileNameSpan = document.getElementById('selectedFileName');


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

        /**
         * Hides all content sections within the main content area.
         * It looks for direct child divs of the mainContentArea that have an ID.
         */
        function hideAllSections() {
            if (mainContentArea) {
                const sections = mainContentArea.querySelectorAll(':scope > div[id]'); // Selects direct child divs with an ID
                sections.forEach(section => {
                    section.classList.add('hidden');
                });
            }
        }

        // Initial state: show upload section
        hideAllSections(); // Hide all first
        // You'll need to know the ID of your initial section (e.g., 'uploadReportSection')
        const initialSection = document.getElementById('uploadReportSection');
        if (initialSection) initialSection.classList.remove('hidden');
        if (mainContentTitle) mainContentTitle.textContent = 'Upload Project Reports';

        // Universal Sidebar Navigation Logic
        // This is the core change: It handles all sidebar buttons with 'data-target-section'
        const sidebarButtons = document.querySelectorAll('#sidebar button[data-target-section]');

        sidebarButtons.forEach(button => {
            button.addEventListener('click', () => {
                const targetSectionId = button.dataset.targetSection;
                let titleText = button.querySelector('span').textContent; // Get title from button text

                hideAllSections(); // Hides all existing sections

                // Special handling for the main Financial Report button (if it combines sections)
                // The ID 'financialReportSections' is a custom one you defined for this button.
                if (targetSectionId === 'financialReportSections') {
                    // Directly reference the IDs of the sections it should show
                    const transactionListingSection = document.getElementById('transactionListingSection');
                    const forecastBudgetTableSection = document.getElementById('forecastBudgetTableSection');
                    
                    if (transactionListingSection) transactionListingSection.classList.remove('hidden');
                    if (forecastBudgetTableSection) forecastBudgetTableSection.classList.remove('hidden');
                    titleText = 'Financial Report Overview'; // Specific title for this combined view
                } else {
                    // For all other singular sections, directly get the element by ID
                    const targetSection = document.getElementById(targetSectionId);
                    if (targetSection) {
                        targetSection.classList.remove('hidden');
                    }
                }

                if (mainContentTitle) mainContentTitle.textContent = titleText;

                // Close sidebar on mobile after selection
                if (window.innerWidth < 1024 && sidebar) {
                    sidebar.classList.add('-translate-x-full');
                }
            });
        });

        // Toggle sidebar on hamburger menu click (mobile)
        if (mobileMenuToggle && sidebar) {
            mobileMenuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        // Hide sidebar when clicking outside on mobile (optional, but good UX)
        document.addEventListener('click', (event) => {
            if (window.innerWidth < 1024 && sidebar && !sidebar.contains(event.target) && 
                mobileMenuToggle && !mobileMenuToggle.contains(event.target) && 
                !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('-translate-x-full');
            }
        });
        
        // Handle file input change to display selected file name
        if (reportFileInput) {
            reportFileInput.addEventListener('change', () => {
                if (reportFileInput.files.length > 0) {
                    if (selectedFileNameSpan) {
                        selectedFileNameSpan.textContent = `Selected file: ${reportFileInput.files[0].name}`;
                        selectedFileNameSpan.classList.remove('hidden');
                    }
                } else {
                    if (selectedFileNameSpan) {
                        selectedFileNameSpan.textContent = '';
                        selectedFileNameSpan.classList.add('hidden');
                    }
                }
            });
        }

        // --- Document Name Dropdown Logic ---
        if (documentNameSelect) {
            documentNameSelect.addEventListener('change', () => {
                if (documentNameSelect.value === 'Other') {
                    if (customDocumentNameContainer) customDocumentNameContainer.classList.remove('hidden');
                    if (customDocumentNameInput) customDocumentNameInput.setAttribute('required', 'true'); 
                } else {
                    if (customDocumentNameContainer) customDocumentNameContainer.classList.add('hidden');
                    if (customDocumentNameInput) {
                        customDocumentNameInput.removeAttribute('required'); 
                        customDocumentNameInput.value = ''; 
                    }
                }
            });
        }
        // --- End Document Name Dropdown Logic ---

        // --- Drag and Drop Functionality ---
        const dropZone = document.getElementById('dropZoneLabel');

        if (dropZone) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
                document.body.addEventListener(eventName, preventDefaults, false); 
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => {
                    dropZone.classList.add('border-primary-500', 'bg-primary-100'); 
                }, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, () => {
                    dropZone.classList.remove('border-primary-500', 'bg-primary-100'); 
                }, false);
            });

            dropZone.addEventListener('drop', (e) => {
                const files = e.dataTransfer.files;
                if (reportFileInput) reportFileInput.files = files;
                const event = new Event('change');
                if (reportFileInput) reportFileInput.dispatchEvent(event);
            }, false);
        }
        // --- End Drag and Drop Functionality ---

        // Handle Report Upload Form Submission
        if (uploadForm) {
            uploadForm.addEventListener('submit', (e) => {
                e.preventDefault(); 
                let documentName = documentNameSelect ? documentNameSelect.value : '';
                if (documentName === 'Other' && customDocumentNameInput) {
                    documentName = customDocumentNameInput.value.trim();
                }
                const selectedFile = reportFileInput && reportFileInput.files.length > 0 ? reportFileInput.files[0] : null;

                if (documentName && selectedFile) {
                    const submitBtn = uploadForm.querySelector('button[type="submit"]');
                    const originalText = submitBtn ? submitBtn.textContent : '';
                    if (submitBtn) {
                        submitBtn.innerHTML = '<span class="flex items-center justify-center"><svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Uploading...</span>';
                        submitBtn.disabled = true;
                    }
                    
                    setTimeout(() => {
                        showCustomMessageBox('Upload Successful!', `Successfully uploaded "${documentName}" with file: ${selectedFile.name}`);
                        if (documentNameSelect) documentNameSelect.value = 'Financial Report'; 
                        if (customDocumentNameInput) customDocumentNameInput.value = '';
                        if (customDocumentNameContainer) customDocumentNameContainer.classList.add('hidden'); 
                        if (reportFileInput) reportFileInput.value = '';
                        if (selectedFileNameSpan) selectedFileNameSpan.classList.add('hidden');
                        if (submitBtn) {
                            submitBtn.textContent = originalText;
                            submitBtn.disabled = false;
                        }
                    }, 1500);
                } else if (!documentName) {
                    showCustomMessageBox('Error', 'Please enter a document name.');
                } else if (!selectedFile) {
                    showCustomMessageBox('Error', 'Please select a file to upload.');
                }
            });
        }

        // Toggle Message Panel
        if (messageBarToggle) {
            messageBarToggle.addEventListener('click', () => {
                if (messagePanel) messagePanel.classList.toggle('translate-x-full');
            });
        }

        if (closeMessagePanelBtn) {
            closeMessagePanelBtn.addEventListener('click', () => {
                if (messagePanel) messagePanel.classList.add('translate-x-full');
            });
        }

        // Handle Send Message
        if (sendMessageBtn) {
            sendMessageBtn.addEventListener('click', () => {
                const message = messageTextarea ? messageTextarea.value.trim() : '';
                const subject = messageSubject ? messageSubject.value.trim() : '';
                const recipient = recipientType ? recipientType.value : 'all';
                
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
                            if (messageTextarea) messageTextarea.value = '';
                            if (messageSubject) messageSubject.value = '';
                            if (messagePanel) messagePanel.classList.add('translate-x-full');
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
        }
    });
</script>