<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction History</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .main-content-flex {
            display: flex;
            justify-content: center;
            padding: 2rem;
            background-color: #f3f4f6;
        }

        .content-container {
            width: 100%;
            max-width: 1400px;
        }

        .animate-fadeIn {
            animation: fadeIn 0.5s ease-out forwards;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .card-hover:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }
        
        .transition-transform {
            transition-property: transform;
            transition-duration: 0.3s;
            transition-timing-function: ease-out;
        }
    </style>
</head>
<body>

<div class="main-content-flex">
    <div class="content-container">
        <div id="historySection" class="bg-white p-8 rounded-2xl shadow-xl w-full mx-auto transition-transform duration-300 card-hover animate-fadeIn">
            <h3 class="text-3xl font-extrabold text-gray-800 mb-2 text-center">Transaction History 📜</h3>
            <p class="text-gray-500 text-center mb-10">View and analyze your past financial transactions and forecasts.</p>

            <div class="bg-gray-50 p-6 rounded-xl shadow-inner mb-8">
                <h4 class="text-xl font-semibold text-gray-800 mb-4 border-b pb-3">Filters</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div>
                        <label for="categoryFilter" class="block text-gray-700 font-semibold mb-2">Category</label>
                        <select id="categoryFilter" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-blue-500 focus:border-blue-500 transition duration-300 shadow-sm">
                            <option value="">All Categories</option>
                            <option value="Administrative costs">1. Administrative costs</option>
                            <option value="Operational support costs">2. Operational support costs</option>
                            <option value="Consortium Activities">3. Consortium Activities</option>
                            <option value="Targeting new CSOs">4. Targeting new CSOs</option>
                            <option value="Contingency">5. Contingency</option>
                        </select>
                    </div>
                    <div>
                        <label for="quarterFilter" class="block text-gray-700 font-semibold mb-2">Period (Quarter)</label>
                        <select id="quarterFilter" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-blue-500 focus:border-blue-500 transition duration-300 shadow-sm">
                            <option value="">All Quarters</option>
                            <option value="Q1">Q1</option>
                            <option value="Q2">Q2</option>
                            <option value="Q3">Q3</option>
                            <option value="Q4">Q4</option>
                        </select>
                    </div>
                    <div>
                        <label for="yearFilter" class="block text-gray-700 font-semibold mb-2">Year</label>
                        <select id="yearFilter" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-4 focus:ring-blue-500 focus:border-blue-500 transition duration-300 shadow-sm">
                            <option value="">All Years</option>
                            <option value="2024">2024</option>
                            <option value="2025">2025</option>
                            <option value="2026">2026</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button class="w-full bg-blue-600 text-white py-3 px-6 rounded-xl hover:bg-blue-700 focus:outline-none focus:ring-4 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-300 shadow-lg font-bold">
                            Apply Filters
                        </button>
                    </div>
                </div>
            </div>

            <div class="mt-8 animate-fadeIn overflow-x-auto">
                <h4 class="text-2xl font-semibold text-gray-800 mb-6 border-b pb-4">Forecast Budget 2025</h4>
                <div class="overflow-x-auto rounded-lg shadow-lg">
   <table class="w-full border-collapse border border-gray-300 text-sm">
  <thead class="bg-gray-200 text-gray-700">
    <tr>
      <th class="border border-gray-300 px-3 py-2 text-left">Category</th>
      <th class="border border-gray-300 px-3 py-2 text-left">Period</th>
      <th class="border border-gray-300 px-3 py-2 text-right">Budget <i class="fas fa-money-bill-wave text-green-600 ml-1"></i></th>
      <th class="border border-gray-300 px-3 py-2 text-right">Actual <i class="fas fa-money-bill-wave text-green-600 ml-1"></i></th>
      <th class="border border-gray-300 px-3 py-2 text-right">Forecast <i class="fas fa-money-bill-wave text-green-600 ml-1"></i></th>
      <th class="border border-gray-300 px-3 py-2 text-right">Actual + Forecast <i class="fas fa-money-bill-wave text-green-600 ml-1"></i></th>
      <th class="border border-gray-300 px-3 py-2 text-right">Variance (%)</th>
    </tr>
  </thead>
  <tbody>
    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2" rowspan="5">
        1. Administrative Costs <br>
        <span class="text-xs text-gray-500">Date: 01/01/2025</span>
      </td>
      <td class="border border-gray-300 px-3 py-2">Q1</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,812.90</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q2</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q3</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q4</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2">Annual Total</td>
      <td class="border border-gray-300 px-3 py-2 text-right">10,996.97</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>

    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2" rowspan="5">
        2. Operational Support Costs <br>
        <span class="text-xs text-gray-500">Date: 01/01/2025</span>
      </td>
      <td class="border border-gray-300 px-3 py-2">Q1</td>
      <td class="border border-gray-300 px-3 py-2 text-right">13,704.93</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q2</td>
      <td class="border border-gray-300 px-3 py-2 text-right">13,284.93</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q3</td>
      <td class="border border-gray-300 px-3 py-2 text-right">13,494.93</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q4</td>
      <td class="border border-gray-300 px-3 py-2 text-right">13,494.93</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2">Annual Total</td>
      <td class="border border-gray-300 px-3 py-2 text-right">40,484.79</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>

    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2" rowspan="5">
        3. Consortium Activities <br>
        <span class="text-xs text-gray-500">Date: 01/01/2025</span>
      </td>
      <td class="border border-gray-300 px-3 py-2">Q1</td>
      <td class="border border-gray-300 px-3 py-2 text-right">19,358.72</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q2</td>
      <td class="border border-gray-300 px-3 py-2 text-right">13,800.28</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q3</td>
      <td class="border border-gray-300 px-3 py-2 text-right">25,845.28</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q4</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2">Annual Total</td>
      <td class="border border-gray-300 px-3 py-2 text-right">59,004.29</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>

    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2" rowspan="5">
        4. Targeting New CSOs <br>
        <span class="text-xs text-gray-500">Date: 01/01/2025</span>
      </td>
      <td class="border border-gray-300 px-3 py-2">Q1</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q2</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q3</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q4</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2">Annual Total</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>

    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2" rowspan="5">
        5. Contingency <br>
        <span class="text-xs text-gray-500">Date: 01/01/2025</span>
      </td>
      <td class="border border-gray-300 px-3 py-2">Q1</td>
      <td class="border border-gray-300 px-3 py-2 text-right">701.92</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q2</td>
      <td class="border border-gray-300 px-3 py-2 text-right">701.92</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q3</td>
      <td class="border border-gray-300 px-3 py-2 text-right">701.92</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr>
      <td class="border border-gray-300 px-3 py-2">Q4</td>
      <td class="border border-gray-300 px-3 py-2 text-right">701.92</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
    <tr class="bg-gray-100 font-semibold">
      <td class="border border-gray-300 px-3 py-2">Annual Total</td>
      <td class="border border-gray-300 px-3 py-2 text-right">2,105.76</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>

    <tr class="bg-gray-200 font-bold">
      <td colspan="2" class="border border-gray-300 px-3 py-2">Total</td>
      <td class="border border-gray-300 px-3 py-2 text-right">112,591.82</td>
      <td class="border border-gray-300 px-3 py-2 text-right">---</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">3,592.04</td>
      <td class="border border-gray-300 px-3 py-2 text-right">0%</td>
    </tr>
  </tbody>
</table>

                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>