<?php
// Set a global variable to indicate we're in the index context
$GLOBALS['in_index_context'] = true;
include 'header.php';
?>

<!-- Main content area -->
<div class="flex flex-col flex-1 min-w-0">
    <!-- Header -->
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-bl-xl">
        <div class="flex items-center">
            <!-- Hamburger menu for small screens -->
            <button id="mobileMenuToggle" class="text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-2 lg:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Mobile Menu Test</h2>
        </div>
        <div class="flex items-center space-x-4">
            <!-- Notification bell -->
            <button class="relative p-2 text-gray-500 hover:text-primary-600 rounded-full focus:outline-none focus:ring-2 focus:ring-primary-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="absolute top-0 right-0 flex h-3 w-3">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-3 w-3 bg-primary-600"></span>
                </span>
            </button>
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
        <div class="max-w-4xl mx-auto">
            <h1 class="text-3xl font-bold text-gray-800 mb-6">Mobile Menu Test Page</h1>
            
            <div class="bg-white rounded-xl shadow-lg p-6 mb-8">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Testing Mobile Menu Toggle</h2>
                <p class="text-gray-600 mb-4">
                    This page is for testing the mobile menu toggle functionality. On mobile devices (screen width less than 1024px), 
                    you should see a hamburger menu icon (☰) in the top-left corner of the main header. Clicking this icon should toggle 
                    the visibility of the sidebar menu.
                </p>
                <p class="text-gray-600 mb-4">
                    On desktop devices (screen width 1024px or greater), the sidebar should be permanently visible on the left side.
                </p>
                
                <div class="bg-primary-50 border border-primary-200 rounded-lg p-4 mt-6">
                    <h3 class="text-lg font-semibold text-primary-800 mb-2">How to Test</h3>
                    <ul class="list-disc pl-5 text-primary-700 space-y-2">
                        <li>Resize your browser window to less than 1024px wide to simulate mobile view</li>
                        <li>Look for the hamburger menu icon (☰) in the top-left corner of the main header</li>
                        <li>Click the hamburger icon to toggle the sidebar visibility</li>
                        <li>Click outside the sidebar to close it when open</li>
                        <li>Resize back to desktop view to see the permanent sidebar</li>
                    </ul>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Test Section 1</h3>
                    <p class="text-gray-600">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam in dui mauris. 
                        Vivamus hendrerit arcu sed erat molestie vehicula.
                    </p>
                </div>
                
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h3 class="text-xl font-semibold text-gray-800 mb-3">Test Section 2</h3>
                    <p class="text-gray-600">
                        Sed auctor neque eu tellus rhoncus ut eleifend nibh porttitor. Ut in nulla enim. 
                        Phasellus molestie magna non est bibendum.
                    </p>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Mobile sidebar toggle functionality for this test page
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    
    // Toggle sidebar on hamburger menu click (mobile)
    if (mobileMenuToggle && sidebar) {
        mobileMenuToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
        });
    }
    
    // Hide sidebar when clicking outside on mobile
    document.addEventListener('click', (event) => {
        if (window.innerWidth < 1024 && sidebar && !sidebar.contains(event.target) && 
            mobileMenuToggle && !mobileMenuToggle.contains(event.target) && 
            !sidebar.classList.contains('-translate-x-full')) {
            sidebar.classList.add('-translate-x-full');
        }
    });
});
</script>
</body>
</html>