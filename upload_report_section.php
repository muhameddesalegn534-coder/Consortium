<?php
// Check if this file is being included or accessed directly
$included = defined('INCLUDED_FROM_INDEX');
// For direct access, we need to check authentication BEFORE including header.php
if (!$included) {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
   
    // Only include header after authentication check
    include 'header.php';
?>
<style>
    textarea.form-control {
        resize: vertical;
        min-height: 100px;
        max-height: 500px;
    }
    .glass-card {
        background: rgba(255, 255, 255, 0.25);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
    }
    .animated-border {
        position: relative;
        overflow: hidden;
    }
    .animated-border::before {
        content: '';
        position: absolute;
        top: -2px;
        left: -2px;
        right: -2px;
        bottom: -2px;
        background: linear-gradient(45deg, #3b82f6, #8b5cf6, #06b6d4, #10b981, #f59e0b, #ef4444);
        border-radius: inherit;
        z-index: -1;
        animation: shimmer 2s linear infinite;
    }
    @keyframes shimmer {
        0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
        100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
    }
    .upload-zone:hover {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(139, 92, 246, 0.1));
        border-color: #3b82f6;
    }
    .section-title {
        background: linear-gradient(135deg, #3b82f6, #8b5cf6);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }
    .word-count {
        text-align: right;
        font-size: 0.875rem;
    }
    .word-count.warning {
        color: #f59e0b;
    }
    .word-count.exceeded {
        color: #ef4444;
    }
    .success-modal-enter {
        animation: successEnter 0.5s ease-out forwards;
    }
    @keyframes successEnter {
        from {
            opacity: 0;
            transform: scale(0.9) translateY(20px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    .success-icon-bounce {
        animation: bounce 0.6s ease-in-out;
    }
    @keyframes bounce {
        0%, 100% { transform: translateY(0); }
        50% { transform: translateY(-10px); }
    }
</style>
<div class="flex flex-col flex-1 min-w-0">
    <header class="flex items-center justify-between h-20 px-8 bg-gradient-to-r from-white to-blue-50/50 border-b border-blue-100 shadow-lg rounded-bl-xl backdrop-blur-sm">
        <div class="flex items-center">
            <button id="sidebarOpenBtn"
                class="text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-2 lg:hidden transition-all duration-200 hover:bg-blue-50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
            <h2 class="ml-4 text-2xl font-bold bg-gradient-to-r from-gray-800 to-gray-600 bg-clip-text text-transparent">Upload Report</h2>
        </div>
    </header>
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gradient-to-br from-gray-50 to-blue-50/20">
        <div class="bg-white/80 backdrop-blur-sm rounded-2xl shadow-2xl p-8 max-w-6xl mx-auto border border-white/20">
            <div class="text-center mb-12">
              
                <h3 class="text-4xl font-extrabold bg-gradient-to-r from-gray-800 via-blue-600 to-purple-600 bg-clip-text text-transparent mb-3">Upload Project Documents</h3>
                <p class="text-gray-600 text-xl font-light">Share your progress and achievements with our team in style</p>
            </div>
           
            <div class="mb-10 p-6 bg-gradient-to-r from-blue-50 to-indigo-50/80 rounded-2xl border border-blue-100 animated-border">
                <div class="flex items-center justify-center">
                    <div class="bg-gradient-to-br from-blue-500 to-purple-600 p-4 rounded-xl mr-6 shadow-lg">
                        <i class="fas fa-users text-white text-2xl"></i>
                    </div>
                    <div class="text-center md:text-left">
                        <p class="text-blue-800 font-semibold text-lg">
                            Uploading for Cluster: <span class="font-black text-transparent bg-clip-text bg-gradient-to-r from-blue-600 to-purple-600"><?php echo htmlspecialchars($_SESSION['cluster_name'] ?? 'No Cluster Assigned'); ?></span>
                        </p>
                        <p class="text-blue-600 text-base mt-1 font-medium">Your documents will be shared with cluster administrators securely</p>
                    </div>
                </div>
            </div>
           
            <form id="uploadForm" class="space-y-10">
                <div class="glass-card p-8 rounded-2xl shadow-xl">
                    <h4 class="text-2xl font-bold mb-6 section-title">Document Information</h4>
                    <div class="relative">
                        <label for="documentTypeSelect" class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                            <i class="fas fa-file-alt mr-3 text-primary-500 text-xl"></i>Document Type:
                        </label>
                        <select id="documentTypeSelect"
                            class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 shadow-lg form-control bg-white/60">
                            <option value="Progress Report">📊 Progress Report</option>
                            <option value="financial_report">💰 Financial Report</option>
                            <option value="Other">📄 Other Document Type</option>
                        </select>
                    </div>
                   
                    <div id="customDocumentNameContainer" class="hidden mt-6 transition-all duration-300">
                        <label for="customDocumentNameInput" class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                            <i class="fas fa-pen mr-3 text-primary-500 text-xl"></i>Custom Document Name:
                        </label>
                        <input type="text" id="customDocumentNameInput"
                            class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                            placeholder="e.g., Q3 Financial Report 2025">
                    </div>
                </div>
                <div id="dynamicContentSection">
                    <div id="progressReportSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-blue-50/30 to-white/30 backdrop-blur-sm">
                        <div class="flex items-center mb-8">
                            <div class="bg-gradient-to-br from-blue-500 to-blue-600 p-4 rounded-xl mr-6 shadow-lg">
                                <i class="fas fa-chart-line text-white text-2xl"></i>
                            </div>
                            <div>
                                <h4 id="progressSectionTitle" class="text-2xl font-bold text-gray-800">Progress Report</h4>
                                <p id="progressSectionDescription" class="text-gray-600 text-lg font-medium">Share updates on your project's progress with elegance</p>
                            </div>
                        </div>
                       
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                            <div class="relative">
                                <label for="progressTitle" class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-heading mr-3 text-primary-500 text-xl"></i>Report Title:
                                </label>
                                <input type="text" id="progressTitle"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    placeholder="Enter report title">
                            </div>
                           
                            <div class="relative">
                                <label for="progressDate" class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-calendar mr-3 text-primary-500 text-xl"></i>Report Date:
                                </label>
                                <input type="date" id="progressDate"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>
                    <div id="additionalProgressSections" class="space-y-8 hidden">
                        <div id="summaryOfAchievementsSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-emerald-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-emerald-500 to-green-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-star text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 1: Summary of Achievements</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="summaryAchievements" class="block text-gray-700 text-base font-semibold mb-3">
                                    1.1: Please summarize key achievements during the reporting period. (250 words max) *
                                </label>
                                <textarea id="summaryAchievements"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-emerald-500 focus:border-emerald-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="4" placeholder="Enter summary"></textarea>
                                <div id="summaryAchievements-count" class="word-count">Words: 0 / 250</div>
                                <p class="text-sm text-gray-500 mt-2 p-4 bg-gray-50 rounded-lg">In this section, summarize the objective that the project aims to achieve, the progress made in achieving/contributing to the realization of the outputs, outcomes as specified in the Results Matrix/Log frame, and the main activities that were implemented during the reporting period including key challenges encountered.</p>
                            </div>
                        </div>
                       
                        <div id="operatingContextSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-orange-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-orange-500 to-amber-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-globe text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 2: Operating Context</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="operatingContext" class="block text-gray-700 text-base font-semibold mb-3">
                                    2.1: Please reflect on your operating context. (350 words max)
                                </label>
                                <textarea id="operatingContext"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-orange-500 focus:border-orange-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="8" placeholder="Enter reflection"></textarea>
                                <div id="operatingContext-count" class="word-count">Words: 0 / 350</div>
                                <p class="text-sm text-gray-500 mt-2 p-4 bg-gray-50 rounded-lg">In this section, reflect on your operating environment and its impact on project implementation. You are encouraged to conduct a PESTLE analysis, which involves reflecting on the Political, Economic, Social, Technological, Legal, and Environmental considerations that impacted your context. This could include factors such as government policies, political stability, tax policies, trade restrictions, tariffs, inflation rates, interest rates, economic growth, exchange rates, conflict and economic stability, among others. For each item described, reflect on how it impacts the project and provide recommendations based on the analysis to mitigate risks and leverage opportunities. If none of the external factors have affected the environment in the reporting period, please leave this section blank.</p>
                            </div>
                        </div>
                        <div id="outcomesAndOutputsSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-blue-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-blue-500 to-cyan-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-check-circle text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 3: Outcomes and Outputs</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="outcomesAndOutputs" class="block text-gray-700 text-base font-semibold mb-3">
                                    3.1: Please outline progress made towards realizing outcomes and outputs. (800 words max) *
                                </label>
                                <textarea id="outcomesAndOutputs"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-blue-500 focus:border-blue-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="8" placeholder="Enter outline"></textarea>
                                <div id="outcomesAndOutputs-count" class="word-count">Words: 0 / 800</div>
                                <p class="text-sm text-gray-500 mt-2 p-4 bg-gray-50 rounded-lg">In this question, provide narrative text focused mainly on describing details of activity implementation by project output and outcome (as exactly stated in your project results framework), including strategy employed in carrying out the activities and cross-cutting issues such as gender. Also mention activities that were planned but not carried out during the reporting period and why. Describe how the strategy used, and the accomplishment of the activities, is leading to progress or realization of project outputs/outcomes/objectives.</p>
                            </div>
                            <div class="mt-8">
                                <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-table mr-3 text-primary-500 text-xl"></i>3.2: Upload your updated Results Framework. *
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="resultsFrameworkFile" id="resultsFrameworkDropZone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                        <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                            <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                            <p class="text-sm text-gray-500">PDF, DOCX (Max. 10MB)</p>
                                        </div>
                                        <input id="resultsFrameworkFile" type="file" class="hidden" accept=".pdf,.docx,.xlsx,.csv">
                                    </label>
                                </div>
                                <p id="selectedResultsFrameworkName" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                            </div>
                        </div>
                        <div id="challengesSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-red-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-red-500 to-pink-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-exclamation-triangle text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 4: Challenges and Risks</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="challengesDescription" class="block text-gray-700 text-base font-semibold mb-3">
                                    4.1: Explain the challenges faced in this quarter and how they were addressed. (250 words max) *
                                </label>
                                <textarea id="challengesDescription"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-red-500 focus:border-red-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="4" placeholder="Enter explanation"></textarea>
                                <div id="challengesDescription-count" class="word-count">Words: 0 / 250</div>
                            </div>
                            <div class="mt-8">
                                <label for="mitigationMeasures" class="block text-gray-700 text-base font-semibold mb-3">
                                    4.2: Highlight any mitigation measures taken. (250 words max) *
                                </label>
                                <textarea id="mitigationMeasures"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-red-500 focus:border-red-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="4" placeholder="Enter mitigation measures"></textarea>
                                <div id="mitigationMeasures-count" class="word-count">Words: 0 / 250</div>
                            </div>
                            <div class="mt-8">
                                <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-shield-alt mr-3 text-primary-500 text-xl"></i>4.3: Upload your updated risk matrix.
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="riskMatrixFile" id="riskMatrixDropZone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                        <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                            <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                            <p class="text-sm text-gray-500">PDF, DOCX (Max. 10MB)</p>
                                        </div>
                                        <input id="riskMatrixFile" type="file" class="hidden" accept=".pdf,.docx,.xlsx,.csv">
                                    </label>
                                </div>
                                <p id="selectedRiskMatrixName" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                            </div>
                        </div>
                        <div id="goodPracticesSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-yellow-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-yellow-500 to-amber-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-lightbulb text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 5: Good Practices, Lessons Learnt, and Recommendations</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="goodPractices" class="block text-gray-700 text-base font-semibold mb-3">
                                    5.1: Please outline any good practices, lessons learnt, and recommendations from this period. (350 words max) *
                                </label>
                                <textarea id="goodPractices"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-yellow-500 focus:border-yellow-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="6" placeholder="Enter outline"></textarea>
                                <div id="goodPractices-count" class="word-count">Words: 0 / 350</div>
                                <p class="text-sm text-gray-500 mt-2 p-4 bg-gray-50 rounded-lg">In this section, you will document the good practices, lessons learnt, and recommendations based on your project implementation experience. This information is crucial for continuous improvement and knowledge sharing.</p>
                            </div>
                        </div>
                        <div id="spotlightSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-purple-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-purple-500 to-pink-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-bullhorn text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 6: Bi-annual Spotlight and Communications</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="spotlightNarrative" class="block text-gray-700 text-base font-semibold mb-3">
                                    6.1: Share a change story or best practice narrative that was the highlight of the reporting period. (500 words max) *
                                </label>
                                <textarea id="spotlightNarrative"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-purple-500 focus:border-purple-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="6" placeholder="Enter narrative"></textarea>
                                <div id="spotlightNarrative-count" class="word-count">Words: 0 / 500</div>
                                <p class="text-sm text-gray-500 mt-2 p-4 bg-gray-50 rounded-lg">This can include project-related publications and/or press articles. Think of changes to peoples' lives, behaviour, organisational capacity, policy etc. It should be supported with photos and quotes where possible.</p>
                            </div>
                            <div class="mt-8">
                                <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-camera mr-3 text-primary-500 text-xl"></i>6.2: Upload pictures if relevant.
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="spotlightPhotos" id="spotlightPhotosDropZone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                        <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                            <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                            <p class="text-sm text-gray-500">JPG, PNG, GIF (Max. 5MB each)</p>
                                        </div>
                                        <input id="spotlightPhotos" type="file" class="hidden" multiple accept="image/jpeg,image/png,image/gif">
                                    </label>
                                </div>
                                <p id="selectedSpotlightPhotosNames" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                            </div>
                        </div>
                        <div id="coordinationSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-indigo-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-indigo-500 to-blue-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-handshake text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 7: Inter- and Intra-Consortium Coordination</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="coordinationReflection" class="block text-gray-700 text-base font-semibold mb-3">
                                    7.1: Reflect on instances of joint action within the consortium and within the operating environment in CSIF (350 words max) *
                                </label>
                                <textarea id="coordinationReflection"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-indigo-500 focus:border-indigo-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="6" placeholder="Enter reflection"></textarea>
                                <div id="coordinationReflection-count" class="word-count">Words: 0 / 350</div>
                                <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-500 font-medium">Please make sure you include:</p>
                                    <ul class="list-disc list-inside mt-2 space-y-1 text-sm text-gray-600">
                                        <li>highlight successes and impacts;</li>
                                        <li>outline future coordination plans, including strategies for sustainability and scaling efforts while addressing challenges;</li>
                                        <li>emphasise how coordination enhances the effectiveness and efficiency of your work.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div id="conflictSensitivitySection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-teal-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-teal-500 to-cyan-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-balance-scale text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 8: Conflict Sensitivity</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="conflictSensitivityAnalysis" class="block text-gray-700 text-base font-semibold mb-3">
                                    8.1: Give an analysis of your conflict-sensitive approach during the period (250 words max) *
                                </label>
                                <textarea id="conflictSensitivityAnalysis"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-teal-500 focus:border-teal-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="4" placeholder="Enter analysis"></textarea>
                                <div id="conflictSensitivityAnalysis-count" class="word-count">Words: 0 / 250</div>
                                <p class="text-sm text-gray-500 mt-2 p-4 bg-gray-50 rounded-lg">CSIF mainstreams a conflict-sensitive approach that adapts the programme's strategies and activities in a way that ensures minimising negative consequences and maximising positive effects.</p>
                            </div>
                        </div>
                        <div id="prioritiesSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-violet-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-violet-500 to-purple-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-tasks text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 9: Priorities for Next Reporting Period</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="prioritiesOutline" class="block text-gray-700 text-base font-semibold mb-3">
                                    9.1: Provide an outline or description of the key activities for this period and how implementation of these activities will build on previous achievements. Please highlight outputs and/or outcomes envisaged for the upcoming reporting period (500 words max) *
                                </label>
                                <textarea id="prioritiesOutline"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-violet-500 focus:border-violet-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="6" placeholder="Enter outline"></textarea>
                                <div id="prioritiesOutline-count" class="word-count">Words: 0 / 500</div>
                            </div>
                            <div class="mt-8">
                                <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-calendar-alt mr-3 text-primary-500 text-xl"></i>9.2: Upload your workplan for the next quarter. *
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="workplanFile" id="workplanDropZone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                        <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                            <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                            <p class="text-sm text-gray-500">PDF, DOCX, XLSX (Max. 10MB)</p>
                                        </div>
                                        <input id="workplanFile" type="file" class="hidden" accept=".pdf,.docx,.xlsx">
                                    </label>
                                </div>
                                <p id="selectedWorkplanName" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                            </div>
                        </div>
                        <div id="expenditureSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-green-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-green-500 to-emerald-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 10: Expenditure and Resource Utilisation</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-file-invoice mr-3 text-primary-500 text-xl"></i>10.1: Upload your financial report. *
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="financialReportFile" id="financialReportDropZone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                        <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                            <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                            <p class="text-sm text-gray-500">PDF, XLSX (Max. 10MB)</p>
                                        </div>
                                        <input id="financialReportFile" type="file" class="hidden" accept=".pdf,.xlsx">
                                    </label>
                                </div>
                                <p id="selectedFinancialReportName" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                            </div>
                            <div class="mt-8 relative">
                                <label for="expenditureIssues" class="block text-gray-700 text-base font-semibold mb-3">
                                   10.2: Provide a narrative description of any issues faced in budget execution, or technical issues related to the accounting and reconciliation of expenditures. (250 words max) *
                                </label>
                                <textarea id="expenditureIssues"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-green-500 focus:border-green-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="4" placeholder="Enter narrative"></textarea>
                                <div id="expenditureIssues-count" class="word-count">Words: 0 / 250</div>
                                <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                    <p class="text-sm text-gray-500 font-medium">These can be things such as:</p>
                                    <ul class="list-disc list-inside mt-2 space-y-1 text-sm text-gray-600">
                                        <li>variances in actual expenditure on a given budget line compared to the funds allocated for that purpose;</li>
                                        <li>exchange rate fluctuations affecting the amount of funding available to a project;</li>
                                        <li>the burn rate of project funds rising or falling dramatically as a result of unforeseen changes in the operating environment, such as renewed volatility in an insecure, post-conflict environment, or a sudden onset natural disaster in a climate-affected region.</li>
                                    </ul>
                                    <p class="text-sm text-gray-500 mt-2">These are additional examples of financial information that may not be conveyed through the financial report, which instead can be described here.</p>
                                </div>
                            </div>
                            <div class="mt-8">
                                <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-camera mr-3 text-primary-500 text-xl"></i>Upload supporting images for financial context if relevant.
                                </label>
                                <div class="flex items-center justify-center w-full">
                                    <label for="financialImages" id="financialImagesDropZone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                        <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                            <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                            </svg>
                                            <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                            <p class="text-sm text-gray-500">JPG, PNG, GIF (Max. 5MB each)</p>
                                        </div>
                                        <input id="financialImages" type="file" class="hidden" multiple accept="image/jpeg,image/png,image/gif">
                                    </label>
                                </div>
                                <p id="selectedFinancialImagesNames" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                            </div>
                        </div>
                        <div id="feedbackSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-rose-50/30 to-white/30 backdrop-blur-sm">
                            <div class="flex items-center mb-8">
                                <div class="bg-gradient-to-br from-rose-500 to-pink-600 p-4 rounded-xl mr-6 shadow-lg">
                                    <i class="fas fa-comment-dots text-white text-2xl"></i>
                                </div>
                                <div>
                                    <h4 class="text-2xl font-bold text-gray-800">Section 11: Feedback to the Manager</h4>
                                </div>
                            </div>
                            <div class="relative">
                                <label for="managerFeedback" class="block text-gray-700 text-base font-semibold mb-3">
                                    11.1: Do you have any feedback/suggestions/requests to the Fund Manager (150 words max)
                                </label>
                                <textarea id="managerFeedback"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-rose-500 focus:border-rose-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="3" placeholder="Enter feedback"></textarea>
                                <div id="managerFeedback-count" class="word-count">Words: 0 / 150</div>
                            </div>
                            <div class="mt-4 relative">
                                <label for="steeringTopics" class="block text-gray-700 text-base font-semibold mb-3">
                                    11.2: Do you have recommended topics to discuss at the forthcoming steering and governance board (150 words max)
                                </label>
                                <textarea id="steeringTopics"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-rose-500 focus:border-rose-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    rows="3" placeholder="Enter recommendations"></textarea>
                                <div id="steeringTopics-count" class="word-count">Words: 0 / 150</div>
                            </div>
                        </div>
                    </div>
                    <div id="financialReportSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-green-50/30 to-white/30 backdrop-blur-sm hidden">
                        <div class="flex items-center mb-8">
                            <div class="bg-gradient-to-br from-green-500 to-emerald-600 p-4 rounded-xl mr-6 shadow-lg">
                                <i class="fas fa-money-bill-wave text-white text-2xl"></i>
                            </div>
                            <div>
                                <h4 class="text-2xl font-bold text-gray-800">Section 1: Expenditure and Resource Utilisation</h4>
                            </div>
                        </div>
                        <div class="relative">
                            <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                <i class="fas fa-file-invoice mr-3 text-primary-500 text-xl"></i>1.1: Upload your financial report. *
                            </label>
                            <div class="flex items-center justify-center w-full">
                                <label for="financialReportFileStandalone" id="financialReportDropZoneStandalone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                    <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                        <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                        <p class="text-sm text-gray-500">PDF, XLSX (Max. 10MB)</p>
                                    </div>
                                    <input id="financialReportFileStandalone" type="file" class="hidden" accept=".pdf,.xlsx">
                                </label>
                            </div>
                            <p id="selectedFinancialReportNameStandalone" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                        </div>
                        <div class="mt-8 relative">
                            <label for="expenditureIssuesStandalone" class="block text-gray-700 text-base font-semibold mb-3">
                               1.2: Provide a narrative description of any issues faced in budget execution, or technical issues related to the accounting and reconciliation of expenditures. (250 words max) *
                            </label>
                            <textarea id="expenditureIssuesStandalone"
                                class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-green-500 focus:border-green-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                rows="4" placeholder="Enter narrative"></textarea>
                            <div id="expenditureIssuesStandalone-count" class="word-count">Words: 0 / 250</div>
                            <div class="mt-2 p-4 bg-gray-50 rounded-lg">
                                <p class="text-sm text-gray-500 font-medium">These can be things such as:</p>
                                <ul class="list-disc list-inside mt-2 space-y-1 text-sm text-gray-600">
                                    <li>variances in actual expenditure on a given budget line compared to the funds allocated for that purpose;</li>
                                    <li>exchange rate fluctuations affecting the amount of funding available to a project;</li>
                                    <li>the burn rate of project funds rising or falling dramatically as a result of unforeseen changes in the operating environment, such as renewed volatility in an insecure, post-conflict environment, or a sudden onset natural disaster in a climate-affected region.</li>
                                </ul>
                                <p class="text-sm text-gray-500 mt-2">These are additional examples of financial information that may not be conveyed through the financial report, which instead can be described here.</p>
                            </div>
                        </div>
                    </div>
                    <div id="otherDocumentSection" class="glass-card p-8 rounded-2xl shadow-xl bg-gradient-to-br from-gray-50/30 to-white/30 backdrop-blur-sm hidden">
                        <div class="flex items-center mb-8">
                            <div class="bg-gradient-to-br from-gray-500 to-gray-600 p-4 rounded-xl mr-6 shadow-lg">
                                <i class="fas fa-file text-white text-2xl"></i>
                            </div>
                            <div>
                                <h4 class="text-2xl font-bold text-gray-800">Other Document</h4>
                                <p class="text-gray-600 text-lg font-medium">Upload any other project-related documents</p>
                            </div>
                        </div>
                       
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mt-8">
                            <div class="relative">
                                <label for="otherTitle" class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-heading mr-3 text-primary-500 text-xl"></i>Document Title:
                                </label>
                                <input type="text" id="otherTitle"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    placeholder="e.g., Additional Project Notes">
                            </div>
                           
                            <div class="relative">
                                <label for="otherDate" class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                    <i class="fas fa-calendar mr-3 text-primary-500 text-xl"></i>Document Date:
                                </label>
                                <input type="date" id="otherDate"
                                    class="w-full px-5 py-4 border-2 border-gray-200 rounded-xl focus:ring-3 focus:ring-primary-500 focus:border-primary-500 transition-all duration-300 shadow-lg form-control bg-white/60"
                                    value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                       
                        <div class="mt-8">
                            <label class="block text-gray-700 text-base font-semibold mb-3 flex items-center">
                                <i class="fas fa-file-upload mr-3 text-primary-500 text-xl"></i>Upload Document:
                            </label>
                            <div class="flex items-center justify-center w-full">
                                <label for="otherFiles" id="otherDropZone" class="flex flex-col items-center justify-center w-full h-48 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-primary-400 hover:bg-primary-50/50 transition-all duration-300 upload-zone shadow-lg">
                                    <div class="flex flex-col items-center justify-center pt-6 pb-8">
                                        <svg class="w-12 h-12 mb-4 text-gray-400 transition-colors duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                        </svg>
                                        <p class="mb-2 text-base text-gray-500 font-semibold"><span class="font-bold">Click to upload</span> or drag and drop</p>
                                        <p class="text-sm text-gray-500">Any file type (Max. 10MB)</p>
                                    </div>
                                    <input id="otherFiles" type="file" class="hidden" multiple>
                                </label>
                            </div>
                            <p id="selectedOtherNames" class="mt-3 text-base text-primary-600 font-semibold hidden flex items-center justify-center"><i class="fas fa-check-circle mr-2 text-green-500"></i></p>
                        </div>
                    </div>
                </div>
                <div class="mt-12">
                    <button type="submit" id="submitBtn"
                        class="w-full bg-gradient-to-r from-primary-600 to-purple-600 text-white py-4 px-8 rounded-xl font-bold text-lg hover:from-primary-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-primary-500 focus:ring-offset-2 transition-all duration-300 flex items-center justify-center shadow-xl hover:shadow-2xl transform hover:-translate-y-1">
                        <i class="fas fa-upload mr-3 text-xl"></i> Upload Your Report
                    </button>
                </div>
            </form>
        </div>
    </main>
</div>

<!-- Success Modal -->
<div id="successMessage" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden z-50">
    <div class="glass-card p-8 rounded-2xl text-center max-w-md w-full mx-4 success-modal-enter">
        <div class="bg-gradient-to-br from-green-500 to-emerald-600 p-4 rounded-xl mb-6 mx-auto w-20 h-20 flex items-center justify-center success-icon-bounce">
            <i class="fas fa-check text-white text-3xl"></i>
        </div>
        <h3 class="text-2xl font-bold bg-gradient-to-r from-green-600 to-emerald-700 bg-clip-text text-transparent mb-4">Upload Successful!</h3>
        <p class="text-gray-600 mb-6 text-lg">Your report has been uploaded successfully. Thank you for sharing your progress with the team!</p>
        <button id="closeSuccess" class="bg-gradient-to-r from-green-600 to-emerald-700 text-white py-3 px-8 rounded-xl font-semibold hover:from-green-700 hover:to-emerald-800 focus:outline-none focus:ring-4 focus:ring-green-500 transition-all duration-300 shadow-lg transform hover:-translate-y-0.5">Close & Continue</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const documentTypeSelect = document.getElementById('documentTypeSelect');
        const customDocumentNameContainer = document.getElementById('customDocumentNameContainer');
        const progressReportSection = document.getElementById('progressReportSection');
        const additionalProgressSections = document.getElementById('additionalProgressSections');
        const financialReportSection = document.getElementById('financialReportSection');
        const otherDocumentSection = document.getElementById('otherDocumentSection');
        const uploadForm = document.getElementById('uploadForm');
        const successMessage = document.getElementById('successMessage');
        const closeSuccess = document.getElementById('closeSuccess');
        
        documentTypeSelect.addEventListener('change', function() {
            const selectedValue = this.value;
           
            progressReportSection.classList.add('hidden');
            additionalProgressSections.classList.add('hidden');
            financialReportSection.classList.add('hidden');
            otherDocumentSection.classList.add('hidden');
            customDocumentNameContainer.classList.add('hidden');
            if (selectedValue === 'Progress Report') {
                progressReportSection.classList.remove('hidden');
                additionalProgressSections.classList.remove('hidden');
            } else if (selectedValue === 'financial_report') {
                financialReportSection.classList.remove('hidden');
            } else if (selectedValue === 'Other') {
                otherDocumentSection.classList.remove('hidden');
                customDocumentNameContainer.classList.remove('hidden');
            }
        });
        documentTypeSelect.dispatchEvent(new Event('change'));
        
        function setupFileUpload(inputId, namesId) {
            const fileInput = document.getElementById(inputId);
            const selectedNames = document.getElementById(namesId);
            if (fileInput) {
                fileInput.addEventListener('change', function() {
                    const fileNames = Array.from(this.files).map(file => file.name).join(', ');
                    selectedNames.textContent = fileNames;
                    selectedNames.classList.toggle('hidden', this.files.length === 0);
                });
            }
        }
       
        setupFileUpload('resultsFrameworkFile', 'selectedResultsFrameworkName');
        setupFileUpload('riskMatrixFile', 'selectedRiskMatrixName');
        setupFileUpload('spotlightPhotos', 'selectedSpotlightPhotosNames');
        setupFileUpload('financialReportFile', 'selectedFinancialReportName');
        setupFileUpload('workplanFile', 'selectedWorkplanName');
        setupFileUpload('financialImages', 'selectedFinancialImagesNames');
        setupFileUpload('financialReportFileStandalone', 'selectedFinancialReportNameStandalone');
        setupFileUpload('otherFiles', 'selectedOtherNames');
        
        // Handle form submission
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Uploading...';
            submitBtn.disabled = true;
            
            // Create FormData object
            const formData = new FormData();
            
            // Add document type
            formData.append('documentType', documentTypeSelect.value);
            
            // Add custom document name if visible
            if (!customDocumentNameContainer.classList.contains('hidden')) {
                formData.append('customDocumentName', document.getElementById('customDocumentNameInput').value);
            }
            
            // Add progress report fields if visible
            if (!progressReportSection.classList.contains('hidden')) {
                formData.append('progressTitle', document.getElementById('progressTitle').value);
                formData.append('progressDate', document.getElementById('progressDate').value);
            }
            
            // Add additional progress report fields if visible
            if (!additionalProgressSections.classList.contains('hidden')) {
                formData.append('summaryAchievements', document.getElementById('summaryAchievements').value);
                formData.append('operatingContext', document.getElementById('operatingContext').value);
                formData.append('outcomesAndOutputs', document.getElementById('outcomesAndOutputs').value);
                formData.append('challengesDescription', document.getElementById('challengesDescription').value);
                formData.append('mitigationMeasures', document.getElementById('mitigationMeasures').value);
                formData.append('goodPractices', document.getElementById('goodPractices').value);
                formData.append('spotlightNarrative', document.getElementById('spotlightNarrative').value);
                formData.append('coordinationReflection', document.getElementById('coordinationReflection').value);
                formData.append('conflictSensitivityAnalysis', document.getElementById('conflictSensitivityAnalysis').value);
                formData.append('prioritiesOutline', document.getElementById('prioritiesOutline').value);
                formData.append('expenditureIssues', document.getElementById('expenditureIssues').value);
                formData.append('managerFeedback', document.getElementById('managerFeedback').value);
                formData.append('steeringTopics', document.getElementById('steeringTopics').value);
                
                // Add file uploads
                const resultsFrameworkFile = document.getElementById('resultsFrameworkFile');
                if (resultsFrameworkFile.files.length > 0) {
                    formData.append('resultsFrameworkFile', resultsFrameworkFile.files[0]);
                }
                
                const riskMatrixFile = document.getElementById('riskMatrixFile');
                if (riskMatrixFile.files.length > 0) {
                    formData.append('riskMatrixFile', riskMatrixFile.files[0]);
                }
                
                const spotlightPhotos = document.getElementById('spotlightPhotos');
                if (spotlightPhotos.files.length > 0) {
                    for (let i = 0; i < spotlightPhotos.files.length; i++) {
                        formData.append('spotlightPhotos[]', spotlightPhotos.files[i]);
                    }
                }

                const workplanFile = document.getElementById('workplanFile');
                if (workplanFile.files.length > 0) {
                    formData.append('workplanFile', workplanFile.files[0]);
                }

                const financialReportFile = document.getElementById('financialReportFile');
                if (financialReportFile.files.length > 0) {
                    formData.append('financialReportFile', financialReportFile.files[0]);
                }

                const financialImages = document.getElementById('financialImages');
                if (financialImages.files.length > 0) {
                    for (let i = 0; i < financialImages.files.length; i++) {
                        formData.append('financialImages[]', financialImages.files[i]);
                    }
                }
            }
            
            // Add financial report fields if visible
            if (!financialReportSection.classList.contains('hidden')) {
                formData.append('expenditureIssuesStandalone', document.getElementById('expenditureIssuesStandalone').value);
                
                // Add financial report file
                const financialReportFileStandalone = document.getElementById('financialReportFileStandalone');
                if (financialReportFileStandalone.files.length > 0) {
                    formData.append('financialReportFileStandalone', financialReportFileStandalone.files[0]);
                }
            }
            
            // Add other document fields if visible
            if (!otherDocumentSection.classList.contains('hidden')) {
                formData.append('otherTitle', document.getElementById('otherTitle').value);
                formData.append('otherDate', document.getElementById('otherDate').value);
                
                // Add other files
                const otherFiles = document.getElementById('otherFiles');
                if (otherFiles.files.length > 0) {
                    for (let i = 0; i < otherFiles.files.length; i++) {
                        formData.append('otherFiles[]', otherFiles.files[i]);
                    }
                }
            }
            
            // Send the form data using fetch
            fetch('document_upload_handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success modal
                    successMessage.classList.remove('hidden');
                    // Reset button state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                } else {
                    // Show error message
                    alert('Error: ' + data.message);
                    // Reset button state
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while uploading the report. Please try again.');
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        });

        // Close success modal
        closeSuccess.addEventListener('click', function() {
            successMessage.classList.add('hidden');
            // Reset form
            uploadForm.reset();
            // Reset file name displays
            document.querySelectorAll('[id$="Name"]').forEach(el => {
                el.classList.add('hidden');
                el.textContent = '';
            });
            // Reset section visibility
            documentTypeSelect.dispatchEvent(new Event('change'));
            // Reset word counts
            Object.keys(wordLimits).forEach(textareaId => {
                const countId = `${textareaId}-count`;
                const countElement = document.getElementById(countId);
                if (countElement) {
                    countElement.textContent = `Words: 0 / ${wordLimits[textareaId]}`;
                    countElement.className = 'word-count';
                }
            });
        });

        // Auto-hide modal after 5 seconds
        successMessage.addEventListener('transitionend', function() {
            if (!successMessage.classList.contains('hidden')) {
                setTimeout(() => {
                    closeSuccess.click();
                }, 5000);
            }
        });

        // Word count and limit functionality
        const wordLimits = {
            summaryAchievements: 250,
            operatingContext: 350,
            outcomesAndOutputs: 800,
            challengesDescription: 250,
            mitigationMeasures: 250,
            goodPractices: 350,
            spotlightNarrative: 500,
            coordinationReflection: 350,
            conflictSensitivityAnalysis: 250,
            prioritiesOutline: 500,
            expenditureIssues: 250,
            managerFeedback: 150,
            steeringTopics: 150,
            expenditureIssuesStandalone: 250
        };

        function countWords(text) {
            return text.trim() ? text.trim().split(/\s+/).filter(word => word.length > 0).length : 0;
        }

        function truncateToWords(text, maxWords) {
            const words = text.trim().split(/\s+/).filter(word => word.length > 0);
            if (words.length > maxWords) {
                return words.slice(0, maxWords).join(' ') + '...';
            }
            return text;
        }

        function updateWordCount(textareaId, countId, maxWords) {
            const textarea = document.getElementById(textareaId);
            const countElement = document.getElementById(countId);
            if (textarea && countElement) {
                const text = textarea.value;
                let wordCount = countWords(text);
                if (wordCount > maxWords) {
                    textarea.value = truncateToWords(text, maxWords);
                    wordCount = maxWords;
                }
                countElement.textContent = `Words: ${wordCount} / ${maxWords}`;
                countElement.className = 'word-count';
                if (wordCount > maxWords * 0.9) {
                    countElement.classList.add('warning');
                }
                if (wordCount > maxWords) {
                    countElement.classList.add('exceeded');
                }
            }
        }

        Object.keys(wordLimits).forEach(textareaId => {
            const countId = `${textareaId}-count`;
            const maxWords = wordLimits[textareaId];
            const textarea = document.getElementById(textareaId);
            if (textarea) {
                textarea.addEventListener('input', function() {
                    updateWordCount(textareaId, countId, maxWords);
                });
                // Initial count
                updateWordCount(textareaId, countId, maxWords);
            }
        });
    });
</script>
<?php
}
?>