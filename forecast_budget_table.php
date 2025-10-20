<!DOCTYPE html>
<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Include database configuration
if (!defined('INCLUDED_SETUP')) {
    define('INCLUDED_SETUP', true);
}
include 'setup_database.php';
include 'currency_functions.php';

// Get user role and cluster information
$userRole = $_SESSION['role'] ?? 'finance_officer';
$userCluster = $_SESSION['cluster_name'] ?? null;

// Set default year if not specified - use current year
$selectedYear = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Handle cluster selection for admins
$selectedCluster = null;
if ($userRole === 'admin') {
    // Admin can select any cluster or view all data
    $selectedCluster = isset($_GET['cluster']) ? $_GET['cluster'] : null;
} else {
    // Finance officers can only see their assigned cluster
    $selectedCluster = $userCluster;
}

// Handle currency selection
$selectedCurrency = 'ETB'; // Default for regular users
if ($userRole === 'admin') {
    // Admin can select currency preference
    if (isset($_GET['currency']) && isValidCurrency($_GET['currency'])) {
        $selectedCurrency = $_GET['currency'];
        $_SESSION['selected_currency'] = $selectedCurrency;
    } elseif (isset($_SESSION['selected_currency']) && isValidCurrency($_SESSION['selected_currency'])) {
        $selectedCurrency = $_SESSION['selected_currency'];
    } else {
        $selectedCurrency = 'USD'; // Default for admins
        $_SESSION['selected_currency'] = $selectedCurrency;
    }
} else {
    // Regular users always see ETB
    $selectedCurrency = 'ETB';
}

// Handle custom currency rates for admins
$customCurrencyRates = null;
if ($userRole === 'admin' && isset($_GET['use_custom_rates']) && $_GET['use_custom_rates'] == '1') {
    $customCurrencyRates = [
        'USD_to_ETB' => isset($_GET['usd_to_etb']) ? floatval($_GET['usd_to_etb']) : 300.0000,
        'EUR_to_ETB' => isset($_GET['eur_to_etb']) ? floatval($_GET['eur_to_etb']) : 320.0000,
        'USD_to_EUR' => isset($_GET['usd_to_eur']) ? floatval($_GET['usd_to_eur']) : 0.9375
    ];
    
    // Store custom rates in session for persistence
    $_SESSION['custom_currency_rates'] = $customCurrencyRates;
} else if ($userRole === 'admin' && isset($_SESSION['custom_currency_rates']) && (!isset($_GET['use_custom_rates']) || $_GET['use_custom_rates'] != '0')) {
    // Use session-stored custom rates if available and not explicitly disabled
    $customCurrencyRates = $_SESSION['custom_currency_rates'];
} else if (isset($_SESSION['custom_currency_rates'])) {
    // Clear session if custom rates are disabled
    unset($_SESSION['custom_currency_rates']);
}

// Map display years to database years
$displayToDatabaseYear = [
  1 => 1,
  2 => 2,
  3 => 3,
  4 => 4,
  5 => 5,
  6 => 6
];

// Map database years to display years
$databaseToDisplayYear = [
  1 => 1,
  2 => 2,
  3 => 3,
  4 => 4,
  5 => 5,
  6 => 6
];

// Get organization name based on year
$organizationNames = [
  1 => 'Consortium Hub Organization Year 1',
  2 => 'Consortium Hub Organization Year 2', 
  3 => 'Consortium Hub Organization Year 3',
  4 => 'Consortium Hub Organization Year 4',
  5 => 'Consortium Hub Organization Year 5',
  6 => 'Consortium Hub Organization Year 6'
];

// Convert display year to database year for queries
$databaseYear = isset($displayToDatabaseYear[$selectedYear]) ? $displayToDatabaseYear[$selectedYear] : 1;
$organizationName = isset($organizationNames[$selectedYear]) ? $organizationNames[$selectedYear] : 'Consortium Hub Organization';

// Get currency rates for the selected cluster or use custom rates
$currencyRates = null;
if ($customCurrencyRates) {
    // Use custom currency rates
    $currencyRates = $customCurrencyRates;
} else if ($selectedCluster) {
    // Get currency rates for the selected cluster
    $currencyRates = getCurrencyRatesByClusterNameMySQLi($conn, $selectedCluster);
} else {
    // If no cluster selected, use default rates
    $currencyRates = [
        'USD_to_ETB' => 300.0000,
        'EUR_to_ETB' => 320.0000,
        'USD_to_EUR' => 0.9375
    ];
}

// Fetch summary data for metrics cards with cluster filtering
$summaryQuery = "SELECT 
  SUM(CASE WHEN period_name = 'Overall' OR period_name = 'Grand Total' THEN budget ELSE 0 END) as total_budget,
  SUM(CASE WHEN period_name = 'Annual Total' THEN actual ELSE 0 END) as total_actual,
  SUM(CASE WHEN period_name = 'Annual Total' THEN actual_plus_forecast ELSE 0 END) as total_actual_forecast
FROM budget_data WHERE year = ?";

// Add cluster condition if a specific cluster is selected
if ($selectedCluster) {
    $summaryQuery .= " AND cluster = ?";
    $stmt = $conn->prepare($summaryQuery);
    $stmt->bind_param("is", $databaseYear, $selectedCluster);
} else {
    $stmt = $conn->prepare($summaryQuery);
    $stmt->bind_param("i", $databaseYear);
}

$stmt->execute();
$summaryResult = $stmt->get_result();
$summaryData = $summaryResult->fetch_assoc();

// Use calculated Grand Total values for more accurate metrics
$totalBudget = 0;
$totalActual = 0;
$totalActualForecast = 0;

// Calculate from Annual Total values of actual categories with cluster filtering
$metricsQuery = "SELECT * FROM budget_data WHERE year = ? AND period_name = 'Annual Total' AND category_name NOT LIKE '%total%' AND category_name NOT LIKE '%grand%'";

// Add cluster condition if a specific cluster is selected
if ($selectedCluster) {
    $metricsQuery .= " AND cluster = ?";
    $stmt = $conn->prepare($metricsQuery);
    $stmt->bind_param("is", $databaseYear, $selectedCluster);
} else {
    $stmt = $conn->prepare($metricsQuery);
    $stmt->bind_param("i", $databaseYear);
}

$stmt->execute();
$metricsResult = $stmt->get_result();

while ($row = $metricsResult->fetch_assoc()) {
  $totalBudget += floatval($row['budget'] ?? 0);
  $totalActual += floatval($row['actual'] ?? 0);
  $totalActualForecast += floatval($row['actual_plus_forecast'] ?? 0);
}
$utilizationPercentage = ($totalBudget > 0) ? round(($totalActual / $totalBudget) * 100, 1) : 0;
$remainingBudget = $totalBudget - $totalActual;

// Convert metrics to selected currency if needed
// Convert metrics to selected currency if needed
if ($currencyRates) {
    $originalCurrency = 'ETB'; // default fallback

    // Only try to get currency if there is at least one row
    if ($metricsResult->num_rows > 0) {
        $metricsResult->data_seek(0);
        $firstRow = $metricsResult->fetch_assoc();
        $originalCurrency = $firstRow['currency'] ?? 'ETB';
    }

    // Only convert if currencies are different
    if ($originalCurrency !== $selectedCurrency) {
        $totalBudget = convertCurrency($totalBudget, $originalCurrency, $selectedCurrency, $currencyRates);
        $totalActual = convertCurrency($totalActual, $originalCurrency, $selectedCurrency, $currencyRates);
        $totalActualForecast = convertCurrency($totalActualForecast, $originalCurrency, $selectedCurrency, $currencyRates);
        $remainingBudget = convertCurrency($remainingBudget, $originalCurrency, $selectedCurrency, $currencyRates);
    }
}

// Fetch data for Section 2 table with cluster filtering
$section2Query = "SELECT * FROM budget_data WHERE year = ? AND LOWER(category_name) != 'grand total' AND LOWER(category_name) != 'total'";

// Add cluster condition if a specific cluster is selected
if ($selectedCluster) {
    $section2Query .= " AND cluster = ?";
}

$section2Query .= " ORDER BY category_name, start_date";

// Prepare statement with cluster parameter if needed
if ($selectedCluster) {
    $stmt = $conn->prepare($section2Query);
    $stmt->bind_param("is", $databaseYear, $selectedCluster);
} else {
    $stmt = $conn->prepare($section2Query);
    $stmt->bind_param("i", $databaseYear);
}

$stmt->execute();
$section2Result = $stmt->get_result();
$hasSection2Data = $section2Result && $section2Result->num_rows > 0;

// Group data by category for Section 2
$section2Data = [];
$seenPeriods = [];

while ($row = $section2Result->fetch_assoc()) {
    $categoryName = $row['category_name'];
    $periodName = $row['period_name'];
    
    // Create a unique key for category-period combination
    $key = $categoryName . '|' . $periodName;
    
    // Skip if we've already seen this category-period combination
    if (isset($seenPeriods[$key])) {
        continue;
    }
    
    $seenPeriods[$key] = true;
    
    // Group robustly without relying on sort order
    if (!isset($section2Data[$categoryName])) {
        $section2Data[$categoryName] = [];
    }
    $section2Data[$categoryName][] = $row;
}

// Process each category to ensure it has all required periods and Annual Total
foreach ($section2Data as $categoryName => &$periods) {
    // Skip pre-existing Total/Grand Total
    if (strtolower($categoryName) === 'total' || strtolower($categoryName) === 'grand total') continue;
    
    // Ensure we have all quarters (Q1, Q2, Q3, Q4)
    $quarters = ['Q1', 'Q2', 'Q3', 'Q4'];
    $existingQuarters = [];
    
    // Collect existing quarters
    foreach ($periods as $row) {
        if (in_array($row['period_name'], $quarters)) {
            $existingQuarters[] = $row['period_name'];
        }
    }
    
    // Add missing quarters with zero values
    foreach ($quarters as $quarter) {
        if (!in_array($quarter, $existingQuarters)) {
            $periods[] = [
                'category_name' => $categoryName,
                'period_name' => $quarter,
                'budget' => 0,
                'actual' => 0,
                'forecast' => 0,
                'actual_plus_forecast' => 0,
                'variance_percentage' => 0,
                'currency' => ($periods[0]['currency'] ?? 'ETB'),
                'start_date' => '', // Will be sorted properly later
            ];
        }
    }
    
    // Check if Annual Total already exists
    $hasAnnualTotal = false;
    foreach ($periods as $row) {
        if ($row['period_name'] === 'Annual Total') {
            $hasAnnualTotal = true;
            break;
        }
    }
    
    // Calculate Annual Total if missing
    if (!$hasAnnualTotal) {
        // Initialize sums
        $annualBudget = 0;
        $annualActual = 0;
        $annualForecast = 0;
        $annualActualPlusForecast = 0;
        
        // Sum from all quarters
        foreach ($periods as $row) {
            if (in_array($row['period_name'], ['Q1', 'Q2', 'Q3', 'Q4'])) {
                $annualBudget += floatval($row['budget'] ?? 0);
                $annualActual += floatval($row['actual'] ?? 0);
                $annualForecast += floatval($row['forecast'] ?? 0);
                $annualActualPlusForecast += floatval($row['actual_plus_forecast'] ?? 0);
            }
        }
        
        // Append calculated Annual Total row with variance based on Actual only
        // Variance (%) = (Budget − Actual) / Budget × 100
        $annualVariance = ($annualBudget != 0) ? round((($annualBudget - $annualActual) / abs($annualBudget)) * 100, 2) : 0;
        $periods[] = [
            'category_name' => $categoryName,
            'period_name' => 'Annual Total',
            'budget' => $annualBudget,
            'actual' => $annualActual,
            'forecast' => $annualForecast,
            'actual_plus_forecast' => $annualActualPlusForecast,
            'variance_percentage' => $annualVariance,
            'currency' => ($periods[0]['currency'] ?? 'ETB'),
        ];
    }
    
    // Sort periods by start_date to ensure chronological order
    usort($periods, function($a, $b) {
        // Define order for periods
        $order = [
            'Q3' => 1,
            'Q4' => 2,
            'Q1' => 3,
            'Q2' => 4,
            'Annual Total' => 5
        ];
        
        $periodA = $a['period_name'];
        $periodB = $b['period_name'];
        
        // If both periods are in our defined order
        if (isset($order[$periodA]) && isset($order[$periodB])) {
            return $order[$periodA] - $order[$periodB];
        }
        
        // If one is in our defined order and the other isn't
        if (isset($order[$periodA])) return -1;
        if (isset($order[$periodB])) return 1;
        
        // Sort by start_date for chronological order
        $dateA = strtotime($a['start_date'] ?? '');
        $dateB = strtotime($b['start_date'] ?? '');
        
        if ($dateA === false && $dateB === false) return 0;
        if ($dateA === false) return 1;
        if ($dateB === false) return -1;
        
        return $dateA <=> $dateB;
    });
}
// Break reference to avoid accidental mutations in later loops/output
unset($periods);

// Rest of Grand Total and currency conversion (unchanged from my previous response)
$grandTotalCalculated = [
  'budget' => 0, 'actual' => 0, 'forecast' => 0, 'actual_plus_forecast' => 0, 'variance_percentage' => 0
];

if (isset($section2Data['Total'])) unset($section2Data['Total']);
if (isset($section2Data['Grand Total'])) unset($section2Data['Grand Total']);

foreach ($section2Data as $categoryName => $periods) {
  if (strtolower($categoryName) === 'total' || strtolower($categoryName) === 'grand total') continue;
  foreach ($periods as $row) {
    if ($row['period_name'] === 'Annual Total') {
      $grandTotalCalculated['budget'] += floatval($row['budget'] ?? 0);
      $grandTotalCalculated['actual'] += floatval($row['actual'] ?? 0);
      $grandTotalCalculated['forecast'] += floatval($row['forecast'] ?? 0);
      $grandTotalCalculated['actual_plus_forecast'] += floatval($row['actual_plus_forecast'] ?? 0);
      break;
    }
  }
}

if ($grandTotalCalculated['budget'] != 0) {
  // Variance (%) = (Budget − Actual) / Budget × 100
  $grandTotalCalculated['variance_percentage'] = round((($grandTotalCalculated['budget'] - $grandTotalCalculated['actual']) / abs($grandTotalCalculated['budget'])) * 100, 2);
}

$section2Data['Grand Total'] = [[
  'category_name' => 'Grand Total',
  'period_name' => 'Grand Total',
  'budget' => $grandTotalCalculated['budget'],
  'actual' => $grandTotalCalculated['actual'],
  'forecast' => $grandTotalCalculated['forecast'],
  'actual_plus_forecast' => $grandTotalCalculated['actual_plus_forecast'],
  'variance_percentage' => $grandTotalCalculated['variance_percentage']
]];

if ($currencyRates) {
    foreach ($section2Data as $categoryName => &$periods) {
        foreach ($periods as &$row) {
            $originalCurrency = $row['currency'] ?? 'ETB';
            if ($originalCurrency !== $selectedCurrency) {
                $row['budget'] = convertCurrency($row['budget'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['actual'] = convertCurrency($row['actual'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['forecast'] = convertCurrency($row['forecast'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['actual_plus_forecast'] = convertCurrency($row['actual_plus_forecast'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['currency'] = $selectedCurrency;
            }
        }
        // Break inner reference before next iteration
        unset($row);
    }
    // Break outer reference to avoid affecting later foreach loops and output
    unset($periods);
}

// Fetch data for Section 3 table (consolidated view by category with all quarters) with cluster filtering
$section3Query = "SELECT * FROM budget_data WHERE year = ? AND period_name IN ('Q1', 'Q2', 'Q3', 'Q4', 'Annual Total', 'Total') AND LOWER(category_name) != 'grand total'";

// Add cluster condition if a specific cluster is selected
if ($selectedCluster) {
    $section3Query .= " AND cluster = ?";
}

$section3Query .= " ORDER BY 
  category_name,
  CASE 
    WHEN period_name = 'Annual Total' THEN 1
    WHEN period_name = 'Total' THEN 2
    ELSE 3
  END";

// Prepare statement with cluster parameter if needed
if ($selectedCluster) {
    $stmt = $conn->prepare($section3Query);
    $stmt->bind_param("is", $databaseYear, $selectedCluster);
} else {
    $stmt = $conn->prepare($section3Query);
    $stmt->bind_param("i", $databaseYear);
}

$stmt->execute();
$section3Result = $stmt->get_result();

// Process data for Section 3 (pivot format)
$section3Categories = [];
$categoryTotals = [];
$seenPeriods = [];

while ($row = $section3Result->fetch_assoc()) {
  $categoryName = $row['category_name'];
  $periodName = $row['period_name'];
  
  // Create a unique key for category-period combination
  $key = $categoryName . '|' . $periodName;
  
  // Skip if we've already seen this category-period combination
  if (isset($seenPeriods[$key])) {
    continue;
  }
  
  $seenPeriods[$key] = true;
  
  if ($periodName == 'Annual Total' || $periodName == 'Total') {
    $categoryTotals[$categoryName][$periodName] = $row;
  } else {
    if (!isset($section3Categories[$categoryName])) {
      $section3Categories[$categoryName] = [
        'Q1' => null,
        'Q2' => null,
        'Q3' => null,
        'Q4' => null
      ];
    }
    $section3Categories[$categoryName][$periodName] = $row;
  }
}

// Calculate Grand Total for Section 3 from Annual Total values
$section3GrandTotal = [
  'budget' => 0,
  'actual' => 0,
  'actual_plus_forecast' => 0,
  'variance_percentage' => 0,
  // Initialize quarter totals for Grand Total row
  'q1_budget' => 0,
  'q1_actual' => 0,
  'q2_budget' => 0,
  'q2_actual' => 0,
  'q3_budget' => 0,
  'q3_forecast' => 0,
  'q4_budget' => 0,
  'q4_forecast' => 0
];

// Remove any existing Total category to avoid confusion


// Sum all Annual Total values for Section 3 from actual categories (not existing Total rows)
foreach ($categoryTotals as $categoryName => $totals) {
  // Skip any category that might be named 'Total' or similar
  if (strtolower($categoryName) === 'total' || strtolower($categoryName) === 'grand total') continue;
  
  if (isset($totals['Annual Total'])) {
    $annualTotal = $totals['Annual Total'];
    $section3GrandTotal['budget'] += floatval($annualTotal['budget'] ?? 0);
    $section3GrandTotal['actual'] += floatval($annualTotal['actual'] ?? 0);
    $section3GrandTotal['actual_plus_forecast'] += floatval($annualTotal['actual_plus_forecast'] ?? 0);
  }
}

// Calculate Grand Total variance for Section 3 based on Actual only
if ($section3GrandTotal['budget'] != 0) {
  $section3GrandTotal['variance_percentage'] = round((($section3GrandTotal['budget'] - $section3GrandTotal['actual']) / abs($section3GrandTotal['budget'])) * 100, 2);
}

// Add the Grand Total category with calculated values for Section 3
$categoryTotals['Grand Total']['Annual Total'] = [
  'category_name' => 'Grand Total',
  'period_name' => 'Annual Total',
  'budget' => $section3GrandTotal['budget'],
  'actual' => $section3GrandTotal['actual'],
  'forecast' => 0,
  'actual_plus_forecast' => $section3GrandTotal['actual_plus_forecast'],
  'variance_percentage' => $section3GrandTotal['variance_percentage']
];
$section3Categories['Grand Total'] = ['Q1' => null, 'Q2' => null, 'Q3' => null, 'Q4' => null];

// Convert currency for section3Data if needed
if ($currencyRates) {
    // Convert category totals
    foreach ($categoryTotals as $categoryName => &$totals) {
        foreach ($totals as $periodName => &$row) {
            $originalCurrency = $row['currency'] ?? 'ETB';
            // Only convert if currencies are different
            if ($originalCurrency !== $selectedCurrency) {
                $row['budget'] = convertCurrency($row['budget'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['actual'] = convertCurrency($row['actual'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['forecast'] = convertCurrency($row['forecast'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['actual_plus_forecast'] = convertCurrency($row['actual_plus_forecast'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                $row['currency'] = $selectedCurrency;
            }
        }
    }
    
    // Convert section3 categories
    foreach ($section3Categories as $categoryName => &$quarters) {
        foreach ($quarters as $quarter => &$row) {
            if ($row !== null) {
                $originalCurrency = $row['currency'] ?? 'ETB';
                // Only convert if currencies are different
                if ($originalCurrency !== $selectedCurrency) {
                    $row['budget'] = convertCurrency($row['budget'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                    $row['actual'] = convertCurrency($row['actual'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                    $row['forecast'] = convertCurrency($row['forecast'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                    $row['actual_plus_forecast'] = convertCurrency($row['actual_plus_forecast'] ?? 0, $originalCurrency, $selectedCurrency, $currencyRates);
                    $row['currency'] = $selectedCurrency;
                }
            }
        }
    }
}

// --- Dynamic quarter ordering and Section 3 totals based on project start ---
// Determine quarter order by earliest start_date across the selected year's data
$baseQuarterOrder = ['Q1', 'Q2', 'Q3', 'Q4'];
$quarterEarliestStart = [];
foreach ($section3Categories as $categoryName => $quarters) {
    if (strtolower($categoryName) === 'grand total') { continue; }
    foreach ($baseQuarterOrder as $q) {
        if (isset($quarters[$q]) && !empty($quarters[$q]['start_date'])) {
            $ts = strtotime($quarters[$q]['start_date']);
            if ($ts !== false) {
                if (!isset($quarterEarliestStart[$q]) || $ts < $quarterEarliestStart[$q]) {
                    $quarterEarliestStart[$q] = $ts;
                }
            }
        }
    }
}

// Choose the first quarter as the one with the minimum start date; fallback to Q1
if (!empty($quarterEarliestStart)) {
    $firstQuarter = array_keys($quarterEarliestStart, min($quarterEarliestStart))[0];
} else {
    $firstQuarter = 'Q1';
}

// Rotate base order to start from firstQuarter
$firstIndex = array_search($firstQuarter, $baseQuarterOrder, true);
if ($firstIndex === false) { $firstIndex = 0; }
$orderedQuarters = array_merge(
    array_slice($baseQuarterOrder, $firstIndex),
    array_slice($baseQuarterOrder, 0, $firstIndex)
);

// Determine display rule dynamically: only the past quarter shows Actual; current and future show Forecast
// Use earliest start dates (already computed) to detect the current quarter relative to today
$quarterStarts = [];
foreach ($orderedQuarters as $q) {
    if (isset($quarterEarliestStart[$q])) {
        $quarterStarts[$q] = $quarterEarliestStart[$q];
    }
}

$todayTs = time();
$currentIndex = null;
foreach ($orderedQuarters as $idx => $q) {
    $startTs = $quarterStarts[$q] ?? null;
    if ($startTs !== null && $startTs <= $todayTs) {
        // Keep advancing to the latest quarter that has started
        $currentIndex = $idx;
    }
}
// If none have started yet, default to the first quarter as the current frame
if ($currentIndex === null) { $currentIndex = 0; }

// The past quarter is the one immediately before the current quarter in the ordered list
$pastIndex = ($currentIndex - 1 + count($orderedQuarters)) % count($orderedQuarters);
$actualDisplayQuarters = [$orderedQuarters[$pastIndex]];

// Recompute Section 3 category annual totals using the dynamic quarter ordering
$recomputedCategoryTotals = [];
foreach ($section3Categories as $categoryName => $quarters) {
    if (strtolower($categoryName) === 'grand total') { continue; }
    $annualBudget = 0.0;
    $annualActual = 0.0;  // Only sum Actual values from past quarters
    $annualActualPlusForecast = 0.0;

    foreach ($orderedQuarters as $q) {
        $row = $quarters[$q] ?? null;
        if ($row === null) { continue; }
        $annualBudget += floatval($row['budget'] ?? 0);
        
        // For Annual Actual: only sum Actual values from past quarters
        if (in_array($q, $actualDisplayQuarters, true)) {
            $annualActual += floatval($row['actual'] ?? 0);
        }
        
        // Preserve display of Actual+Forecast using the new rule: only past quarter counts as Actual
        if (in_array($q, $actualDisplayQuarters, true)) {
            $annualActualPlusForecast += floatval($row['actual'] ?? 0);
        } else {
            $annualActualPlusForecast += floatval($row['forecast'] ?? 0);
        }
    }

    // Variance (%) = (Annual Budget − Annual Actual) / Annual Budget × 100
    // Using only Actual values from past quarters for variance calculation
    $variance = ($annualBudget != 0) ? round((($annualBudget - $annualActual) / abs($annualBudget)) * 100, 2) : 0;
    $recomputedCategoryTotals[$categoryName]['Annual Total'] = [
        'category_name' => $categoryName,
        'period_name' => 'Annual Total',
        'budget' => $annualBudget,
        'actual' => $annualActual,  // Only Actual values from past quarters
        'forecast' => 0,
        'actual_plus_forecast' => $annualActualPlusForecast,
        'variance_percentage' => $variance,
        'currency' => $selectedCurrency,
    ];
}

// Compute Grand Total using recomputed per-category totals
$grandBudget = 0.0;
$grandActual = 0.0;  // Only sum Actual values from past quarters
$grandActualPlusForecast = 0.0;
foreach ($recomputedCategoryTotals as $cat => $totals) {
    $grandBudget += floatval($totals['Annual Total']['budget'] ?? 0);
    $grandActual += floatval($totals['Annual Total']['actual'] ?? 0);  // Only Actual values from past quarters
    $grandActualPlusForecast += floatval($totals['Annual Total']['actual_plus_forecast'] ?? 0);
}
// Variance (%) = (Annual Budget − Annual Actual) / Annual Budget × 100
// Using only Actual values from past quarters for variance calculation
$grandVariance = ($grandBudget != 0) ? round((($grandBudget - $grandActual) / abs($grandBudget)) * 100, 2) : 0;

// Overwrite categoryTotals to use recomputed values and add Grand Total
$categoryTotals = $recomputedCategoryTotals;
$categoryTotals['Grand Total']['Annual Total'] = [
    'category_name' => 'Grand Total',
    'period_name' => 'Annual Total',
    'budget' => $grandBudget,
    'actual' => $grandActual,  // Only Actual values from past quarters
    'forecast' => 0,
    'actual_plus_forecast' => $grandActualPlusForecast,
    'variance_percentage' => $grandVariance,
    'currency' => $selectedCurrency,
];

// Get all clusters for admin dropdown
$clusters = [];
if ($userRole === 'admin') {
    $clustersQuery = "SELECT cluster_name FROM clusters WHERE is_active = 1 ORDER BY cluster_name";
    $clustersResult = $conn->query($clustersQuery);
    if ($clustersResult && $clustersResult->num_rows > 0) {
        while ($row = $clustersResult->fetch_assoc()) {
            $clusters[] = $row['cluster_name'];
        }
    }
}
?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forecast Budget Report</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" type="text/css" href="forecast_budget.css">
  <style>
    :root {
      --primary: #6366f1;
      --primary-dark: #4f46e5;
      --secondary: #6c757d;
      --success: #10b981;
      --success-light: #d1fae5;
      --light: #f8fafc;
      --dark: #1e293b;
      --border: #e2e8f0;
      --card-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      --hover-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
      --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      --gradient-success: linear-gradient(135deg, #10b981 0%, #059669 100%);
      --gradient-background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    }

   html, body {
  height: 100%;
  overflow-x: visible;
  overflow-y: auto;
  background: #f8fafc;
  margin: 0;
  padding: 0;
  width: 100%;
}
.container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: #f8fafc;
  position: relative;
  overflow-y: visible;
  width: 100%;
  max-width: 100%;
}

@media (max-width: 767px) {
  .container {
    max-width: none;
    padding-left: 0;
    padding-right: 0;
  }
}
.container {
  display: flex;
  flex-direction: column;
  min-height: 100vh;
  background: #f8fafc;
  position: relative;
  overflow-y: visible;
  width: 100%;
  max-width: 100%;
}

    .container::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 200px;
      background: var(--gradient-primary);
      z-index: -1;
      border-radius: 0 0 50px 50px;
    }

   .main-content {
  flex: 1;
  padding: 15px 0; /* Zero horizontal padding on mobile */
  position: relative;
  overflow-y: auto;
  background: #f8fafc;
  width: 100%;
  max-width: 100%;
}

@media (min-width: 768px) {
  .main-content {
    padding: 15px; /* Restore original padding on tablets */
  }
}
/* Full edge-to-edge on mobile */
@media (max-width: 767px) {
  .metrics-grid {
    margin: 0 !important;
    padding: 0 10px !important; /* Small padding for breathing room */
  }
  .metric-card-mini {
    padding: 10px !important; /* Reduce padding on mobile */
    border-radius: 0 !important; /* Optional: remove rounded corners */
  }
      .card-body {
        padding: 15px 0; /* Keep top/bottom padding, remove left/right */
    }
    .table-container {
        padding: 0;
        margin: 0;
    }
    .vertical-table {
        border-radius: 0; /* Optional: removes rounded corners for true edge-to-edge */
    }
}
/* Full edge-to-edge on mobile for forecast budget table */

header {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(20px);
  color: var(--dark);
  padding: 25px 30px;
  /* border-radius: 20px; */ /* Replace this line */
  border-radius: 0 0 20px 20px; /* Rounds only bottom-left and bottom-right corners */
  margin-bottom: 15px;
  box-shadow: var(--card-shadow);
  border: 1px solid rgba(255, 255, 255, 0.3);
  animation: slideInDown 0.8s ease-out;
}
/* Full edge-to-edge on mobile for forecast budget table */

/* Make Table 1 fill container width on all screens, especially mobile */
#section2-table .table-container {
    width: 100%;
    max-width: none; /* Remove any max-width constraint */
    margin: 0; /* Remove default margins */
    padding: 0; /* Remove default padding if any */
}

/* Ensure the table itself is full width */
#section2-table .vertical-table {
    width: 100%;
    min-width: 100%; /* Forces table to be at least full container width */
    table-layout: auto; /* Allows columns to size based on content */
}

/* On very small screens, ensure the container has no horizontal padding */
@media (max-width: 767px) {
    #section2-table .card-body {
        padding-left: 0;
        padding-right: 0;
    }
    #section2-table .table-container {
        padding-left: 0;
        padding-right: 0;
    }
    
}
/* Make Table 2 fill container width on all screens, especially mobile */
#section3-table .table-container {
    width: 100%;
    max-width: none; /* Remove any max-width constraint */
    margin: 0;
    padding: 0;
}

/* Ensure the table itself is full width */
#section3-table .vertical-table {
    width: 100%;
    min-width: 100%; /* Forces table to be at least full container width */
    table-layout: auto; /* Allows columns to size based on content */
}

/* On very small screens, ensure the container has no horizontal padding */
@media (max-width: 767px) {
    #section3-table .card-body {
        padding-left: 0;
        padding-right: 0;
    }
    #section3-table .table-container {
        padding-left: 0;
        padding-right: 0;
    }
}
    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .header-content {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .logo {
      font-size: 28px;
      font-weight: 700;
      display: flex;
      align-items: center;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .logo i {
      margin-right: 12px;
      font-size: 32px;
      color: var(--primary);
    }

    .page-title {
      font-size: 32px;
      font-weight: 700;
      margin: 10px 0;
      text-align: center;
      background: var(--gradient-primary);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .controls {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 15px;
      margin-bottom: 20px;
      animation: fadeInUp 0.8s ease-out 0.2s both;
    }

    .filter-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 25px;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      border: 1px solid rgba(255, 255, 255, 0.3);
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
    }

    .filter-section::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 2px;
      background: var(--gradient-primary);
      transition: left 0.5s ease;
    }

    .filter-section:hover::before {
      left: 0;
    }

    .filter-section:hover {
      transform: translateY(-5px);
      box-shadow: var(--hover-shadow);
    }

    .filter-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 15px;
      color: var(--dark);
      display: flex;
      align-items: center;
    }

    .filter-title i {
      margin-right: 10px;
      color: var(--primary);
      font-size: 20px;
    }

    button {
      padding: 12px 20px;
      border: none;
      border-radius: 12px;
      cursor: pointer;
      font-weight: 600;
      font-size: 14px;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px; /* Add space between icon and text */
    }

    button::before {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      width: 0;
      height: 0;
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      transform: translate(-50%, -50%);
      transition: width 0.4s ease, height 0.4s ease;
    }

    button:hover::before {
      width: 200px;
      height: 200px;
    }

    .btn-primary {
      background: var(--gradient-primary);
      color: white;
      box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
      border: 2px solid transparent; /* Add transparent border to maintain consistent sizing */
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(99, 102, 241, 0.4);
    }

    .btn-success {
      background: var(--gradient-success);
      color: white;
      box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
      border: 2px solid transparent; /* Add transparent border to maintain consistent sizing */
    }

    .btn-success:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-outline {
      background: transparent;
      border: 2px solid var(--primary);
      color: var(--primary);
      padding: 10px 18px; /* Adjust padding to account for border */
    }

    .btn-outline:hover {
      background: var(--primary);
      color: white;
      transform: translateY(-2px);
    }

    /* Switch toggle styles */
    .switch {
      position: relative;
      display: inline-block;
      width: 60px;
      height: 34px;
    }

    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }

    .slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      -webkit-transition: .4s;
      transition: .4s;
    }

    .slider:before {
      position: absolute;
      content: "";
      height: 26px;
      width: 26px;
      left: 4px;
      bottom: 4px;
      background-color: white;
      -webkit-transition: .4s;
      transition: .4s;
    }

    input:checked + .slider {
      background-color: #2196F3;
    }

    input:focus + .slider {
      box-shadow: 0 0 1px #2196F3;
    }

    input:checked + .slider:before {
      -webkit-transform: translateX(26px);
      -ms-transform: translateX(26px);
      transform: translateX(26px);
    }

    /* Rounded sliders */
    .slider.round {
      border-radius: 34px;
    }

    .slider.round:before {
      border-radius: 50%;
    }

    .print-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 20px;
      box-shadow: var(--card-shadow);
      margin-bottom: 30px;
      overflow: hidden;
      border: 1px solid rgba(255, 255, 255, 0.3);
      animation: fadeInUp 0.8s ease-out 0.4s both;
      transition: all 0.3s ease;
    }

    .print-section:hover {
      transform: translateY(-3px);
      box-shadow: var(--hover-shadow);
    }

    .card-header {
      background: #1e40af;
      color: white;
      padding: 20px 30px;
      font-weight: 600;
      font-size: 18px;
      position: relative;
    }

    .card-header::after {
      content: '';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: rgba(255, 255, 255, 0.3);
    }

    .card-body {
      padding: 30px;
    }

    .table-container {
      overflow-x: auto;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
    }

    .vertical-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
    }
    
    .vertical-table th,
    .vertical-table td {
      padding: 15px 20px;
      text-align: left;
      border-bottom: 1px solid var(--border);
      transition: all 0.3s ease;
    }
    
    .vertical-table th {
      background: #2563eb;
      color: white;
      font-weight: 600;
      font-size: 14px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    .vertical-table tr:nth-child(even) {
      background-color: #f8fafc;
    }
    
    /* Row hover effects - keeps category column highlighted when hovering any cell in the row */
    .vertical-table tr:hover {
      background-color: #f8fafc;
    }
    
    .vertical-table tr:hover td:first-child {
      background-color: #e2e8f0;
      font-weight: 600;
      border-left: 3px solid #2563eb;
    }
    
    /* Variance styling */
    .variance-positive {
      color: #10b981; /* green for underspent */
      font-weight: 600;
    }
    
    .variance-negative {
      color: #ef4444; /* red for overspent */
      font-weight: 600;
    }
    
    .variance-zero {
      color: #64748b; /* gray for zero variance */
      font-weight: 600;
    }
    
    
    .vertical-table tr.summary-section {
      background: #f1f5f9;
      font-weight: 600;
      border-top: 2px solid #2563eb;
    }
    
    /* Annual Total row hover effects */
    .vertical-table tr.summary-section:hover {
      background-color: #dbeafe;
    }
    
    .vertical-table tr.summary-section:hover td:first-child {
      background-color: #1e40af;
      color: white;
      font-weight: 700;
      border-left: 4px solid #f59e0b;
    }
    
    .vertical-table .category-header {
      background: #e2e8f0;
      font-weight: 600;
      color: var(--dark);
      border-left: 4px solid #2563eb;
    }
    
    /* Category header hover effects */
    .vertical-table tr.category-header:hover {
      background-color: #f1f5f9;
    }
    
    .vertical-table tr.category-header:hover td:first-child {
      background-color: #475569;
      color: white;
      font-weight: 600;
      border-left: 4px solid #10b981;
    }
    
    /* Native category highlight - matches category header hover exactly */
    .vertical-table td.category-native-highlight {
      background-color: #475569 !important;
      color: white !important;
      font-weight: 600 !important;
      border-left: 4px solid #10b981 !important;
    }
    
    /* Annual Total row highlight - professional light blue background with higher specificity */
    .vertical-table tr.annual-total-row-highlight,
    .vertical-table tr.summary-section.annual-total-row-highlight {
      background-color: #dbeafe !important;
      border-left: 3px solid #2563eb !important;
    }
    
    /* Override native Annual Total hover when our class is applied */
    .vertical-table tr.summary-section.annual-total-row-highlight:hover {
      background-color: #dbeafe !important;
    }
    
    /* Q1 row highlight - professional light green background */
    .vertical-table tr.q1-row-highlight {
      background-color: #d1fae5 !important;
      border-left: 3px solid #059669 !important;
    }
    
    /* Annual Total text field highlight - matches native hover dark blue */
    .vertical-table td.annual-total-text-highlight {
      background-color: #1e40af !important;
      color: white !important;
      font-weight: 700 !important;
      border-left: 4px solid #f59e0b !important;
    }

    /* Grand Total row styling - permanent highlighting without hover */
    .vertical-table tr.grand-total-section {
      background: linear-gradient(135deg, #1e40af 0%, #1d4ed8 100%) !important;
      color: white !important;
      font-weight: 700 !important;
      border: 2px solid #1e40af !important;
      box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3) !important;
    }
    
    .vertical-table tr.grand-total-section td {
      background: transparent !important;
      color: white !important;
      font-weight: 700 !important;
      border-color: rgba(255, 255, 255, 0.2) !important;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    }
    
    .vertical-table tr.grand-total-section:hover {
      background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 100%) !important;
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(30, 64, 175, 0.4) !important;
    }
    
    .vertical-table tr.grand-total-section td.grand-total-label {
      font-size: 16px !important;
      letter-spacing: 0.5px !important;
      text-transform: uppercase !important;
    }

    .certification-section {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      padding: 30px;
      border-radius: 20px;
      box-shadow: var(--card-shadow);
      margin-top: 30px;
      border: 1px solid rgba(255, 255, 255, 0.3);
      animation: fadeInUp 0.8s ease-out 0.6s both;
    }

    .certification-header {
      background: var(--gradient-success);
      color: white;
      padding: 20px 30px;
      margin: -30px -30px 30px -30px;
      font-weight: 600;
      font-size: 20px;
      border-radius: 20px 20px 0 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .file-upload {
      border: 3px dashed var(--primary);
      border-radius: 12px;
      padding: 40px 20px;
      text-align: center;
      cursor: pointer;
      transition: all 0.3s ease;
      background: rgba(99, 102, 241, 0.05);
      position: relative;
      overflow: hidden;
    }

    .file-upload::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.5s ease;
    }

    .file-upload:hover::before {
      left: 100%;
    }

    .file-upload:hover {
      border-color: var(--primary-dark);
      background: rgba(99, 102, 241, 0.1);
      transform: scale(1.02);
    }

    .file-upload.drag-over {
      border-color: var(--success);
      background: rgba(16, 185, 129, 0.1);
      transform: scale(1.05);
    }

    .uploaded-file {
      background: var(--success-light);
      border: 2px solid var(--success);
      border-radius: 8px;
      padding: 15px;
      margin-top: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideInUp 0.5s ease;
    }

    @keyframes slideInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
      color: var(--dark);
    }

    .form-group input,
    .form-group textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid var(--border);
      border-radius: 8px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: rgba(255, 255, 255, 0.8);
    }

    .form-group input:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
      background: white;
    }

    .signature-box {
      height: 60px;
      border: 2px dashed var(--border);
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #64748b;
      font-style: italic;
      background: rgba(248, 250, 252, 0.5);
    }

    .signature-box::after {
      content: 'Signature Line';
    }

    /* Metric Cards Styling */
    .metric-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    }

    .metrics-container {
      backdrop-filter: blur(10px);
    }




  </style>
</head>
<body>
<?php 
// Check if this file is being included or accessed directly
$included = defined('INCLUDED_FROM_INDEX') || isset($GLOBALS['in_index_context']);
if (!$included): 
    $GLOBALS['in_index_context'] = true;
    include 'header.php';
?>
<!-- Main content area -->
<div class="flex flex-col flex-1 min-w-0">
    <!-- Header -->
    <header class="flex items-center justify-between h-20 px-8 bg-white border-b border-gray-200 shadow-sm rounded-b-xl">
        <div class="flex items-center">
            <!-- Hamburger menu for small screens -->

        <!-- Variance Formula section removed as per requirements -->

            <button id="mobileMenuToggle"
                class="text-gray-500 focus:outline-none focus:ring-2 focus:ring-primary-500 rounded-md p-2 lg:hidden">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                    xmlns="http://www.w3.org/2000/svg">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2"
                        d="M4 6h16M4 12h16M4 18h16">
                    </path>
                </svg>
            </button>
            <h2 class="ml-4 text-2xl font-semibold text-gray-800">Forecast Budget Report</h2>
        </div>
        <div class="flex items-center space-x-4">
            <!-- Notification bell -->
      
        </div>
    </header>

    <!-- Content Area -->
    <main class="flex-1 p-8 overflow-y-auto overflow-x-auto bg-gray-50">
<?php else: ?>
    <?php include 'header.php'; ?>
<?php endif; ?>

<div class="container">
    <div class="main-content" style="width: 100%; max-width: 100%; overflow-x: visible;">
           <header style="border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.3); margin-top: -20px; padding: 20px 30px; margin-bottom: 10px; overflow: visible;">
        <div class="header-content" style="padding: 5px 0;">
          <div class="logo" style="font-size: 28px;">
            <i class="fas fa-chart-line"></i>
            Financial Report 
          </div>
          <div class="user-info no-print" style="font-size: 16px;">
            <i class="fas fa-user-circle"></i> Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?>
          </div>
        </div>
        <h1 class="page-title" style="font-size: 32px; margin: 10px 0;">
          <?php 
            echo 'Year ' . $selectedYear;
            if ($selectedCluster) {
              echo ' - ' . htmlspecialchars($selectedCluster);
            } elseif ($userRole === 'admin') {
              echo ' - All Clusters';
            } elseif ($userCluster) {
              echo ' - ' . htmlspecialchars($userCluster);
            }
          ?> 
          Forecast Budget Report
        </h1>
      </header>

<br>

 <div class="metrics-grid no-print" style="margin-bottom: 15px;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px;">
          <div class="metric-card-mini" style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); border-left: 3px solid #2563eb; transition: all 0.3s ease;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <div style="background: #2563eb; color: white; padding: 6px; border-radius: 6px; font-size: 12px;">
                <i class="fas fa-chart-pie"></i>
              </div>
              <div>
                <div style="font-size: 10px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">Total Budget</div>
                <div style="font-size: 16px; font-weight: 700; color: #1e293b;"><i class="fas fa-money-bill-wave text-green-600 mr-1"></i><?php echo formatCurrency($totalBudget, $selectedCurrency); ?></div>
              </div>
            </div>
          </div>
          
          <div class="metric-card-mini" style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); border-left: 3px solid #10b981; transition: all 0.3s ease;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <div style="background: #10b981; color: white; padding: 6px; border-radius: 6px; font-size: 12px;">
                <i class="fas fa-money-bill-wave"></i>
              </div>
              <div>
                <div style="font-size: 10px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">Total Actual</div>
                <div style="font-size: 16px; font-weight: 700; color: #1e293b;"><i class="fas fa-money-bill-wave text-green-600 mr-1"></i><?php echo formatCurrency($totalActual, $selectedCurrency); ?></div>
              </div>
            </div>
          </div>
          
          <div class="metric-card-mini" style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); border-left: 3px solid #f59e0b; transition: all 0.3s ease;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <div style="background: #f59e0b; color: white; padding: 6px; border-radius: 6px; font-size: 12px;">
                <i class="fas fa-chart-line"></i>
              </div>
              <div>
                <div style="font-size: 10px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">Utilization</div>
                <div style="font-size: 16px; font-weight: 700; color: #1e293b;"><?php echo $utilizationPercentage; ?>%</div>
              </div>
            </div>
          </div>
          
          <div class="metric-card-mini" style="background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05); border-left: 3px solid #8b5cf6; transition: all 0.3s ease;">
            <div style="display: flex; align-items: center; gap: 10px;">
              <div style="background: #8b5cf6; color: white; padding: 6px; border-radius: 6px; font-size: 12px;">
                <i class="fas fa-piggy-bank"></i>
              </div>
              <div>
                <div style="font-size: 10px; color: #64748b; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">Remaining</div>
                <div style="font-size: 16px; font-weight: 700; color: #1e293b;"><i class="fas fa-money-bill-wave text-green-600 mr-1"></i><?php echo formatCurrency($remainingBudget, $selectedCurrency); ?></div>
              </div>
            </div>
          </div>
          

        </div>
      </div>





      <!-- Filter + Export Controls -->
      <div class="controls no-print">
          <div class="filter-section">
          <div class="filter-title">
            <i class="fas fa-calendar-alt"></i> Filter by Year
          </div>
          <div class="relative">
            <form id="yearFilterForm" method="get">
              <!-- Include cluster in form if selected -->
              <?php if ($userRole === 'admin' && $selectedCluster): ?>
                <input type="hidden" name="cluster" value="<?php echo htmlspecialchars($selectedCluster); ?>">
              <?php endif; ?>
              <select id="yearFilter" name="year" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out shadow-sm" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; font-weight: 500;" onchange="this.form.submit()">
        
                
                <option value="1" <?php echo ($selectedYear == 1) ? 'selected' : ''; ?>>Year 1</option>
                <option value="2" <?php echo ($selectedYear == 2) ? 'selected' : ''; ?>>Year 2</option>
                <option value="3" <?php echo ($selectedYear == 3) ? 'selected' : ''; ?>>Year 3</option>
                <option value="4" <?php echo ($selectedYear == 4) ? 'selected' : ''; ?>>Year 4</option>
                <option value="5" <?php echo ($selectedYear == 5) ? 'selected' : ''; ?>>Year 5</option>
                <option value="6" <?php echo ($selectedYear == 6) ? 'selected' : ''; ?>>Year 6</option>
              </select>
            </form>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500" style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); pointer-events: none;">
              <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 18px; height: 18px; color: #6366f1;">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
        </div>

        <?php if ($userRole === 'admin'): ?>
        <div class="filter-section">
          <div class="filter-title">
            <i class="fas fa-building"></i> Filter by Cluster
          </div>
          <div class="cluster-filter">
            <form id="clusterFilterForm" method="get">
              <!-- Include year in form -->
              <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
              
              <select id="clusterFilter" name="cluster" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out" onchange="this.form.submit()">
                <option value="">All Clusters</option>
                <?php foreach ($clusters as $cluster): ?>
                  <option value="<?php echo htmlspecialchars($cluster); ?>" <?php echo ($selectedCluster === $cluster) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cluster); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($userRole === 'admin'): ?>
        <div class="filter-section">
          <div class="filter-title">
            <i class="fas fa-dollar-sign"></i> Currency Display
          </div>
          <div class="currency-filter">
            <form id="currencyFilterForm" method="get">
              <!-- Include year and cluster in form -->
              <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
              <?php if ($selectedCluster): ?>
                <input type="hidden" name="cluster" value="<?php echo htmlspecialchars($selectedCluster); ?>">
              <?php endif; ?>
              
              <select id="currencyFilter" name="currency" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out" onchange="this.form.submit()">
                <option value="USD" <?php echo ($selectedCurrency === 'USD') ? 'selected' : ''; ?>>USD - US Dollar</option>
                <option value="EUR" <?php echo ($selectedCurrency === 'EUR') ? 'selected' : ''; ?>>EUR - Euro</option>
                <option value="ETB" <?php echo ($selectedCurrency === 'ETB') ? 'selected' : ''; ?>>ETB - Ethiopian Birr</option>
              </select>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- Custom Currency Rates Section for Admins -->
        <?php if ($userRole === 'admin'): ?>
        <div class="filter-section">
          <div class="filter-title">
            <i class="fas fa-exchange-alt"></i> Custom Currency Rates
          </div>
          <div class="custom-currency-rates">
            <form id="customCurrencyRatesForm" method="get">
              <!-- Include all necessary parameters to maintain state -->
              <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
              <?php if ($selectedCluster): ?>
                <input type="hidden" name="cluster" value="<?php echo htmlspecialchars($selectedCluster); ?>">
              <?php endif; ?>
              <input type="hidden" name="currency" value="<?php echo $selectedCurrency; ?>">
              
              <div style="display: flex; flex-wrap: wrap; gap: 15px; margin-bottom: 15px;">
                <div>
                  <label for="usdToEtb" style="display: block; margin-bottom: 5px; font-weight: 500;">USD to ETB:</label>
                  <input type="number" id="usdToEtb" name="usd_to_etb" step="0.0001" min="0" 
                         value="<?php echo isset($_GET['usd_to_etb']) ? htmlspecialchars($_GET['usd_to_etb']) : (isset($customCurrencyRates['USD_to_ETB']) ? $customCurrencyRates['USD_to_ETB'] : '300.0000'); ?>" 
                         placeholder="300.0000" 
                         style="width: 120px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
                
                <div>
                  <label for="eurToEtb" style="display: block; margin-bottom: 5px; font-weight: 500;">EUR to ETB:</label>
                  <input type="number" id="eurToEtb" name="eur_to_etb" step="0.0001" min="0" 
                         value="<?php echo isset($_GET['eur_to_etb']) ? htmlspecialchars($_GET['eur_to_etb']) : (isset($customCurrencyRates['EUR_to_ETB']) ? $customCurrencyRates['EUR_to_ETB'] : '320.0000'); ?>" 
                         placeholder="320.0000" 
                         style="width: 120px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
                
                <div>
                  <label for="usdToEur" style="display: block; margin-bottom: 5px; font-weight: 500;">USD to EUR:</label>
                  <input type="number" id="usdToEur" name="usd_to_eur" step="0.0001" min="0" 
                         value="<?php echo isset($_GET['usd_to_eur']) ? htmlspecialchars($_GET['usd_to_eur']) : (isset($customCurrencyRates['USD_to_EUR']) ? $customCurrencyRates['USD_to_EUR'] : '0.9375'); ?>" 
                         placeholder="0.9375" 
                         style="width: 120px; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
                </div>
              </div>
              
              <div style="display: flex; align-items: center; gap: 10px;">
                <label style="display: flex; align-items: center; cursor: pointer;">
                  <input type="checkbox" id="useCustomRates" name="use_custom_rates" value="1" 
                         <?php echo (isset($_GET['use_custom_rates']) && $_GET['use_custom_rates'] == '1') || (isset($customCurrencyRates) && $customCurrencyRates) ? 'checked' : ''; ?>
                         style="margin-right: 8px;">
                  <span>Use Custom Rates</span>
                </label>
                
                <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 14px;">
                  <i class="fas fa-sync-alt"></i> Apply Rates
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <div class="filter-section">
          <div class="filter-title">
            <i class="fas fa-tools"></i> Actions
          </div>
          <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button class="btn-outline" id="exportExcelBtn" style="border-color: #059669; color: #059669;"><i class="fas fa-file-archive"></i> Export ZIP (2 CSV Files)</button>
          
            <button class="btn-primary" id="printBtn"><i class="fas fa-print"></i> Print Report</button>
          </div>
        </div>

        <div class="filter-section">
          <div class="filter-title">
            <i class="fas fa-table"></i> Select Table View
          </div>
          <div class="relative">
            <select id="tableSelection" class="w-full appearance-none bg-white border border-gray-300 rounded-lg py-3 pl-4 pr-10 text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-300 focus:border-indigo-500 transition duration-150 ease-in-out" style="width: 100%; padding: 8px 12px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 14px;">
              <option value="section2">Table 1 Budget</option>
              <option value="section3">Table 2 Budget</option>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-3 text-gray-500" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); pointer-events: none;">
              <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 16px; height: 16px;">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" />
              </svg>
            </div>
          </div>
        </div>

        <div class="filter-section" id="varianceToggleSection" style="display: none;">
          <div class="filter-title">
            <i class="fas fa-calculator"></i> Variance Calculation
          </div>
          <div style="display: flex; align-items: center; gap: 15px;">
            <div>
              <label class="switch">
                <input type="checkbox" id="varianceToggle">
                <span class="slider round"></span>
              </label>
            </div>
            <div>
              <span id="varianceToggleLabel">Budget vs Actual</span>
            </div>
            <button class="btn-primary" id="showVarianceModalBtn" style="padding: 8px 16px; font-size: 14px;">
              <i class="fas fa-eye"></i> Show Values
            </button>
          </div>
        </div>
      </div>

      <!-- Financial Metrics Cards Grid -->
     

      <!-- Forecast Budget -->
      <div id="print-area">

        <!-- Section 2: Forecast Budget -->
      <!-- Section 2: Forecast Budget -->
<div class="print-section" id="section2-table">
  <div class="card-header">
    Forecast Budget Year <?php echo htmlspecialchars($selectedYear); ?>
  </div>
  <div class="card-body">
    <?php if (empty($section2Data) || count($section2Data) === 1 && isset($section2Data['Grand Total'])): ?>
      <p style="text-align: center; color: #666; font-style: italic; padding: 20px;">
        No budget data available for Year <?php echo htmlspecialchars($selectedYear); ?>
        <?php if ($selectedCluster): ?> in cluster <?php echo htmlspecialchars($selectedCluster); ?><?php endif; ?>.
      </p>
    <?php else: ?>
      <div class="table-container">
        <table class="vertical-table" border="1" cellspacing="0" cellpadding="5">
          <thead>
            <tr>
              <th>Category</th>
              <th>Period</th>
              <th>Budget</th>
              <th>Actual</th>
              <th>Forecast</th>
              <th>Actual + Forecast</th>
              <th>Variance (%)</th>
            </tr>
          </thead>
          <tbody>
            <?php 
            $grandTotalDisplayed = false;
            foreach ($section2Data as $categoryName => $periods):
              
              if (strtolower($categoryName) === 'grand total' && $grandTotalDisplayed) continue;
              $isGrandTotal = (strtolower($categoryName) === 'grand total');
              if ($isGrandTotal) $grandTotalDisplayed = true;

              // Skip empty categories (shouldn't happen, but safe)
              if (empty($periods)) continue;

              if ($isGrandTotal):
                echo '<tr class="grand-total-section">';
                echo '<td class="grand-total-label">Grand Total</td>';
                echo '<td></td>';
                echo '<td>' . formatCurrency($grandTotalCalculated['budget'], $selectedCurrency) . '</td>';
                echo '<td>' . formatCurrency($grandTotalCalculated['actual'], $selectedCurrency) . '</td>';
                echo '<td>' . formatCurrency($grandTotalCalculated['forecast'], $selectedCurrency) . '</td>';
                echo '<td>' . formatCurrency($grandTotalCalculated['actual_plus_forecast'], $selectedCurrency) . '</td>';
                // Default display uses Budget vs Actual; JS toggle will override dynamically
                $grandTotalVariance = ($grandTotalCalculated['budget'] != 0) ? round((($grandTotalCalculated['budget'] - $grandTotalCalculated['actual']) / abs($grandTotalCalculated['budget'])) * 100, 2) : 0;
                $varianceClass = $grandTotalVariance > 0 ? 'variance-positive' : ($grandTotalVariance < 0 ? 'variance-negative' : 'variance-zero');
                echo '<td class="' . $varianceClass . '">' . $grandTotalVariance . '%</td>';
                echo '</tr>';
              else:
                // Regular category
                // Filter out any 'Annual Total' rows to avoid duplication
                $filteredPeriods = array_filter($periods, function($row) {
                  return $row['period_name'] !== 'Annual Total';
                });
                
                // Use the filtered periods directly since we've already ensured uniqueness in data processing
                $orderedPeriods = array_values($filteredPeriods);
                
                // Sort periods by start_date to ensure chronological order in display
                usort($orderedPeriods, function($a, $b) {
                    // Sort by start_date for chronological order
                    $dateA = strtotime($a['start_date'] ?? '');
                    $dateB = strtotime($b['start_date'] ?? '');
                    
                    if ($dateA === false && $dateB === false) return 0;
                    if ($dateA === false) return 1;
                    if ($dateB === false) return -1;
                    
                    return $dateA <=> $dateB;
                });
                
                $categoryRowspan = count($orderedPeriods) + 1; // +1 for Annual Total row
                $firstPeriod = true;

                // Pre-calculate annual totals from quarters
                $annualBudget = $annualActual = $annualForecast = $annualActualForecast = 0;
                foreach ($orderedPeriods as $row) {
                  if (in_array($row['period_name'], ['Q1','Q2','Q3','Q4'])) {
                    $annualBudget += floatval($row['budget'] ?? 0);
                    $annualActual += floatval($row['actual'] ?? 0);
                    $annualForecast += floatval($row['forecast'] ?? 0);
                    $annualActualForecast += floatval($row['actual_plus_forecast'] ?? 0);
                  }
                }
                $annualVariance = ($annualBudget != 0) ? round((($annualBudget - $annualActual) / abs($annualBudget)) * 100, 2) : 0;

                // Display each period
                foreach ($orderedPeriods as $row):
                  echo '<tr class="' . ($firstPeriod ? 'category-header' : '') . '">';
                  if ($firstPeriod):
                    echo '<td rowspan="' . $categoryRowspan . '">' . htmlspecialchars($categoryName) . '</td>';
                  endif;
                  echo '<td>' . htmlspecialchars($row['period_name']) . '</td>';
                  echo '<td>' . (isset($row['budget']) ? formatCurrency($row['budget'], $selectedCurrency) : '-') . '</td>';
                  echo '<td>' . (isset($row['actual']) ? formatCurrency($row['actual'], $selectedCurrency) : '-') . '</td>';
                  echo '<td>' . (isset($row['forecast']) ? formatCurrency($row['forecast'], $selectedCurrency) : '-') . '</td>';
                  echo '<td>' . (isset($row['actual_plus_forecast']) ? formatCurrency($row['actual_plus_forecast'], $selectedCurrency) : '-') . '</td>';
                  $rowVariance = floatval($row['variance_percentage'] ?? 0);
                  $varianceClass = $rowVariance > 0 ? 'variance-positive' : ($rowVariance < 0 ? 'variance-negative' : 'variance-zero');
                  echo '<td class="' . $varianceClass . '">' . $rowVariance . '%</td>';
                  echo '</tr>';
                  $firstPeriod = false;
                endforeach;

                // Always display Annual Total row
                echo '<tr class="summary-section">';
                echo '<td>Annual Total</td>';
                echo '<td>' . number_format($annualBudget, 2) . '</td>';
                echo '<td>' . number_format($annualActual, 2) . '</td>';
                echo '<td>' . number_format($annualForecast, 2) . '</td>';
                echo '<td>' . number_format($annualActualForecast, 2) . '</td>';
                $varianceClass = $annualVariance > 0 ? 'variance-positive' : ($annualVariance < 0 ? 'variance-negative' : 'variance-zero');
                echo '<td class="' . $varianceClass . '">' . $annualVariance . '%</td>';
                echo '</tr>';
              endif;
            endforeach;
            ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

        <!-- Section 3: Forecast 2025 -->
       <!-- Section 3: Forecast Year -->
<div class="print-section" id="section3-table" style="display: none;">
  <div class="card-header">
    Forecast Year <?php echo htmlspecialchars($selectedYear); ?>
  </div>
  <div class="card-body">
    <?php if (empty($section3Categories) || (count($section3Categories) === 1 && isset($section3Categories['Grand Total']))): ?>
      <p style="text-align: center; color: #666; font-style: italic; padding: 20px;">
        No consolidated budget data available for Year <?php echo htmlspecialchars($selectedYear); ?>
        <?php if ($selectedCluster): ?> in cluster <?php echo htmlspecialchars($selectedCluster); ?><?php endif; ?>.
      </p>
    <?php else: ?>
      <div class="table-container">
        <table class="vertical-table" border="1" cellspacing="0" cellpadding="5">
          <thead>
            <tr>
              <th rowspan="2">Category</th>
              <?php foreach ($orderedQuarters as $q): ?>
                <th colspan="2"><?php echo $q; ?></th>
              <?php endforeach; ?>
              <th colspan="3">Annual totals</th>
            </tr>
            <tr>
              <?php foreach ($orderedQuarters as $q): ?>
                <th>Budget</th>
                <?php if (in_array($q, $actualDisplayQuarters, true)): ?>
                  <th>Actual</th>
                <?php else: ?>
                  <th>Forecast</th>
                <?php endif; ?>
              <?php endforeach; ?>
              <th>Budget</th>
              <th>Actual + Forecast</th>
              <th>Variance (%)</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $displayed = [];
            foreach ($section3Categories as $categoryName => $quarters):
              if (strtolower($categoryName) === 'total' || in_array($categoryName, $displayed)) continue;
              $displayed[] = $categoryName;
              $isGrandTotal = (strtolower($categoryName) === 'grand total');
              $annualTotal = $categoryTotals[$categoryName]['Annual Total'] ?? null;
              $rowClass = $isGrandTotal ? 'grand-total-section' : 'category-header';
            ?>

<tr class="<?php echo $rowClass; ?>">
    <td data-label="Category"><?php echo htmlspecialchars($categoryName); ?></td>
    <?php foreach ($orderedQuarters as $q): ?>
      <td data-label="<?php echo $q . ' Budget'; ?>">
        <?php echo isset($quarters[$q]) && isset($quarters[$q]['budget']) ? formatCurrency($quarters[$q]['budget'], $selectedCurrency) : '-'; ?>
      </td>
      <?php if (in_array($q, $actualDisplayQuarters, true)): ?>
        <td data-label="<?php echo $q . ' Actual'; ?>">
          <?php echo isset($quarters[$q]) && isset($quarters[$q]['actual']) ? formatCurrency($quarters[$q]['actual'], $selectedCurrency) : '-'; ?>
        </td>
      <?php else: ?>
        <td data-label="<?php echo $q . ' Forecast'; ?>">
          <?php echo isset($quarters[$q]) && isset($quarters[$q]['forecast']) ? formatCurrency($quarters[$q]['forecast'], $selectedCurrency) : '-'; ?>
        </td>
      <?php endif; ?>
    <?php endforeach; ?>
    <td data-label="Annual Budget"><?php echo $annualTotal && isset($annualTotal['budget']) ? formatCurrency($annualTotal['budget'], $selectedCurrency) : '-'; ?></td>
    <td data-label="Actual + Forecast"><?php echo $annualTotal && isset($annualTotal['actual_plus_forecast']) ? formatCurrency($annualTotal['actual_plus_forecast'], $selectedCurrency) : '-'; ?></td>
    <td data-label="Variance">
        <?php
        $variance = $annualTotal && isset($annualTotal['variance_percentage']) ? $annualTotal['variance_percentage'] : 0;
        $varianceClass = $variance > 0 ? 'variance-positive' : ($variance < 0 ? 'variance-negative' : 'variance-zero');
        echo '<span class="' . $varianceClass . '">' . ($annualTotal ? ($variance . '%') : '0%') . '</span>';
        ?>
    </td>
</tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

        <!-- Certification Section -->
        <div class="certification-section print-section">
          <div class="certification-header">
            Certify
            <span class="certified-badge no-print"><i class="fas fa-check-circle"></i> Certify</span>
          </div>

          <div class="certification-form">
            <div class="form-group">
              <label for="granteeName">Grantee Name</label>
              <input type="text" id="granteeName" value="<?php echo htmlspecialchars($organizationName); ?>">
            </div>

            <div class="form-group">
              <label for="reportDate">Report Date</label>
              <input type="date" id="reportDate">
            </div>

            <div class="form-group full-width">
              <label>Certification Statement</label>
              <textarea>The undersigned certify that this financial report has been prepared from the books and records...</textarea>
            </div>

            <div class="form-group">
              <label>Name</label>
              <input type="text" value="John Smith">
            </div>

            <div class="form-group">
              <label>Authorized Signature</label>
              <div class="signature-box"> <!-- Will print as line --> </div>
            </div>

            <div class="form-group">
              <label>Date Submitted</label>
              <input type="date" value="2023-10-15">
            </div>

            <div class="form-group">
              <label>MMI Technical Program Reviewer</label>
              <input type="text" value="Sarah Johnson">
            </div>

            <div class="form-group">
              <label>Signature</label>
              <div class="signature-box"> <!-- Will print as line --> </div>
            </div>

            <div class="form-group full-width no-print">
              <label>Upload Signed Document</label>
              <div class="file-upload" id="fileUpload">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Click to upload signed document or drag and drop</p>
                <p>PDF, JPG, PNG (Max 5MB)</p>
              </div>
              <input type="file" id="fileInput" style="display: none;" accept=".pdf,.jpg,.jpeg,.png">
              <div id="uploadedFileContainer" style="display: none;" class="uploaded-file">
                <i class="fas fa-check-circle"></i>
                <span id="uploadedFileName">document.pdf</span>
              </div>
            </div>
          </div>

          <div class="action-buttons no-print">
            <button class="btn-outline" id="printBtn"><i class="fas fa-print"></i> Print Certificate</button>
            <button class="btn-primary" id="uploadBtn"><i class="fas fa-upload"></i> Upload Signed Copy</button>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <script>
    // Inject cluster-specific currency rates and context from PHP
    window.selectedCluster = <?php echo json_encode($selectedCluster); ?>;
    window.selectedCurrency = <?php echo json_encode($selectedCurrency); ?>;
    window.currencyRates = <?php echo json_encode($currencyRates ?: []); ?>;
    window.customCurrencyRates = <?php echo json_encode($customCurrencyRates ?: []); ?>;
    window.useCustomRates = <?php echo json_encode(isset($_GET['use_custom_rates']) && $_GET['use_custom_rates'] == '1'); ?>;
  </script>
  <script>
    // Handle custom currency rates form submission
    document.addEventListener('DOMContentLoaded', function() {
      const customCurrencyForm = document.getElementById('customCurrencyRatesForm');
      const useCustomRatesCheckbox = document.getElementById('useCustomRates');
      
      if (customCurrencyForm && useCustomRatesCheckbox) {
        // When the checkbox state changes, update the form submission behavior
        useCustomRatesCheckbox.addEventListener('change', function() {
          // If checkbox is unchecked, we might want to clear custom rates from session
          if (!this.checked) {
            // Add a hidden field to indicate that custom rates should be disabled
            let disableField = document.querySelector('input[name="use_custom_rates"][value="0"]');
            if (!disableField) {
              disableField = document.createElement('input');
              disableField.type = 'hidden';
              disableField.name = 'use_custom_rates';
              disableField.value = '0';
              customCurrencyForm.appendChild(disableField);
            }
          } else {
            // Remove the disable field if it exists
            const disableFields = customCurrencyForm.querySelectorAll('input[name="use_custom_rates"][value="0"]');
            disableFields.forEach(field => field.remove());
          }
        });
        
        // Handle form submission
        customCurrencyForm.addEventListener('submit', function(e) {
          // If the checkbox is checked, ensure the form includes the use_custom_rates parameter
          if (useCustomRatesCheckbox.checked) {
            let useCustomField = customCurrencyForm.querySelector('input[name="use_custom_rates"][value="1"]');
            if (!useCustomField) {
              useCustomField = document.createElement('input');
              useCustomField.type = 'hidden';
              useCustomField.name = 'use_custom_rates';
              useCustomField.value = '1';
              customCurrencyForm.appendChild(useCustomField);
            }
          }
        });
      }
    });
    
    // Mobile sidebar toggle functionality for standalone page
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
    document.getElementById('reportDate').valueAsDate = new Date();

    // Simple print functionality - navigate to dedicated print page
    document.addEventListener('DOMContentLoaded', function() {
      const printButtons = document.querySelectorAll('#printBtn');
      
      printButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          
          // Get current year and cluster
          const yearSelect = document.getElementById('yearFilter');
          const clusterSelect = document.getElementById('clusterFilter');
          const selectedYear = yearSelect ? yearSelect.value : '1';
          const selectedCluster = clusterSelect ? clusterSelect.value : '';
          
          // Get certification form data
          const granteeName = document.getElementById('granteeName') ? document.getElementById('granteeName').value : '';
          const reportDate = document.getElementById('reportDate') ? document.getElementById('reportDate').value : '';
          const certStatement = document.querySelector('textarea') ? document.querySelector('textarea').value : '';
          const nameField = document.querySelector('input[value="John Smith"]') ? document.querySelector('input[value="John Smith"]').value : 'John Smith';
          const dateSubmitted = document.querySelector('input[value="2023-10-15"]') ? document.querySelector('input[value="2023-10-15"]').value : '10/15/2023';
          const reviewer = document.querySelector('input[value="Sarah Johnson"]') ? document.querySelector('input[value="Sarah Johnson"]').value : 'Sarah Johnson';
          
          // Build URL with parameters
          let printUrl = `print_forecast_budget.php?year=${selectedYear}`;
          
          // Add cluster parameter if selected
          if (selectedCluster) {
            printUrl += `&cluster=${encodeURIComponent(selectedCluster)}`;
          }
          
          // Add certification form data
          printUrl += `&grantee_name=${encodeURIComponent(granteeName)}&report_date=${encodeURIComponent(reportDate)}&cert_statement=${encodeURIComponent(certStatement)}&name=${encodeURIComponent(nameField)}&date_submitted=${encodeURIComponent(dateSubmitted)}&reviewer=${encodeURIComponent(reviewer)}`;
          
          // Open print page in new window
          window.open(printUrl, '_blank');
        });
      });
    });

    // Table selection functionality
    const tableSelectionDropdown = document.getElementById('tableSelection');
    const section2Table = document.getElementById('section2-table');
    const section3Table = document.getElementById('section3-table');

    if (tableSelectionDropdown) {
      tableSelectionDropdown.addEventListener('change', function() {
        if (this.value === 'section2') {
          section2Table.style.display = 'block';
          section3Table.style.display = 'none';
        } else if (this.value === 'section3') {
          section2Table.style.display = 'none';
          section3Table.style.display = 'block';
        }
      });
    }

    // Year filter functionality
    const yearFilterDropdown = document.getElementById('yearFilter');
    if (yearFilterDropdown) {
      yearFilterDropdown.addEventListener('change', function() {
        const selectedYear = this.value;
        updatePageTitle(selectedYear);
      });
    }

    function updatePageTitle(year) {
      const pageTitle = document.querySelector('.page-title');
      const section2Header = document.querySelector('#section2-table .card-header');
      const section3Header = document.querySelector('#section3-table .card-header');
      
      if (year) {
        pageTitle.textContent = `Year ${year} Forecast Budget Report`;
        section2Header.textContent = `Forecast Budget Year ${year}`;
        section3Header.textContent = `Forecast Year ${year}`;
      } else {
        pageTitle.textContent = 'Year 1 Forecast Budget Report';
        section2Header.textContent = 'Forecast Budget Year 1';
        section3Header.textContent = 'Forecast Year 1';
      }
    }

    // Export Excel functionality - Combined Budget and Transactions as ZIP with CSV files
    const exportExcelBtn = document.getElementById('exportExcelBtn');
    if (exportExcelBtn) {
      exportExcelBtn.addEventListener('click', function() {
        exportCombinedToExcel();
      });
    }

    function exportCombinedToExcel() {
      try {
      // Get filters for consistent export
      const year = yearFilterDropdown.value || '1';
      const clusterSelect = document.getElementById('clusterFilter');
      const selectedCluster = clusterSelect ? clusterSelect.value : '';

      // Map display year to actual year for transaction export
      // Based on current year and selected year display value
      const currentYear = new Date().getFullYear();
      let actualYear;
      
      // For display years 1-6, map to current year and next few years
      switch(year) {
        case '1':
          actualYear = currentYear;
          break;
        case '2':
          actualYear = currentYear + 1;
          break;
        case '3':
          actualYear = currentYear + 2;
          break;
        case '4':
          actualYear = currentYear + 3;
          break;
        case '5':
          actualYear = currentYear + 4;
          break;
        case '6':
          actualYear = currentYear + 5;
          break;
        default:
          actualYear = currentYear;
      }

      exportExcelBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating ZIP File...';
      exportExcelBtn.disabled = true;

      // Currency conversion helpers (via ETB bridge)
      // Read live values from the Custom Currency Rates form if present to ensure latest settings are used
      const formEl = document.getElementById('customCurrencyRatesForm');
      const useCustomRatesEl = document.getElementById('useCustomRates');
      const usdToEtbEl = document.getElementById('usdToEtb');
      const eurToEtbEl = document.getElementById('eurToEtb');
      const usdToEurEl = document.getElementById('usdToEur');
      const effectiveUseCustom = !!(useCustomRatesEl && useCustomRatesEl.checked);
      const effectiveRates = effectiveUseCustom ? {
        'USD_to_ETB': usdToEtbEl && parseFloat(usdToEtbEl.value) > 0 ? parseFloat(usdToEtbEl.value) : (window.customCurrencyRates && window.customCurrencyRates['USD_to_ETB']) || (window.currencyRates && window.currencyRates['USD_to_ETB']) || 300.0,
        'EUR_to_ETB': eurToEtbEl && parseFloat(eurToEtbEl.value) > 0 ? parseFloat(eurToEtbEl.value) : (window.customCurrencyRates && window.customCurrencyRates['EUR_to_ETB']) || (window.currencyRates && window.currencyRates['EUR_to_ETB']) || 320.0,
        'USD_to_EUR': usdToEurEl && parseFloat(usdToEurEl.value) > 0 ? parseFloat(usdToEurEl.value) : (window.customCurrencyRates && window.customCurrencyRates['USD_to_EUR']) || 0.9375
      } : (window.currencyRates || {});
      const rates = effectiveRates;
      const getEtbPer = (code) => {
        if (code === 'USD') return parseFloat(rates['USD_to_ETB'] || 300.0);
        if (code === 'EUR') return parseFloat(rates['EUR_to_ETB'] || 320.0);
        if (code === 'ETB') return 1.0;
        return 1.0;
      };
      const convertAmount = (amount, from, to) => {
        if (!isFinite(amount)) return 0;
        if (from === to) return amount;
        const etbPerFrom = getEtbPer(from);
        const etbAmount = (from === 'ETB') ? amount : amount * etbPerFrom;
        const etbPerTo = getEtbPer(to);
        return (to === 'ETB') ? etbAmount : (etbAmount / etbPerTo);
      };
      const parseNumber = (text) => {
        if (!text) return 0;
        return parseFloat(String(text).replace(/[^0-9.-]/g, '')) || 0;
      };

      // 1. Build Table 2 CSVs (ETB, USD, EUR) from frontend
      const table = document.querySelector('#section3-table .vertical-table');
      if (!table) {
        if (typeof showNotification === 'function') showNotification('Table 2 not found!', 'error');
        exportExcelBtn.innerHTML = '<i class="fas fa-file-archive"></i> Export ZIP (6 CSV Files)';
        exportExcelBtn.disabled = false;
        return;
      }

      if (typeof JSZip === 'undefined') {
        if (typeof showNotification === 'function') showNotification('JSZip library not loaded.', 'error');
        exportExcelBtn.innerHTML = '<i class="fas fa-file-archive"></i> Export ZIP (6 CSV Files)';
        exportExcelBtn.disabled = false;
        return;
      }

      // Ensure Grand Total is calculated and present
      if (!table.querySelector('tr.grand-total-section')) {
        if (typeof calculateTable2GrandTotal === 'function') calculateTable2GrandTotal();
      }

      // Get all categories from the table (excluding any existing Grand Total rows)
      const categories = [];
      const categoryCells = table.querySelectorAll('tbody tr td:first-child');
      categoryCells.forEach(cell => {
        const categoryName = cell.textContent.trim();
        // Skip any rows that contain 'Grand Total' or variations
        if (!categories.includes(categoryName) && 
            categoryName !== '' && 
            !categoryName.toLowerCase().includes('grand total') &&
            !categoryName.toLowerCase().includes('grand tot')) {
          categories.push(categoryName);
        }
      });

      // Build multi-currency rows (ETB, USD, EUR) per category
      function buildTable2MultiCurrencyRows() {
        // Determine the current quarter based on the current date
        const currentMonth = new Date().getMonth() + 1; // 1-12
        const currentQuarter = Math.ceil(currentMonth / 3); // 1, 2, 3, or 4
        const pastQuarter = ((currentQuarter - 2 + 4) % 4) + 1; // previous quarter (1, 2, 3, or 4)
        
        // Get variance calculation mode from toggle
        const varianceToggle = document.getElementById('varianceToggle');
        const varianceMode = varianceToggle && varianceToggle.checked ? 'budget_actual_forecast' : 'budget_actual';
        
        // Create quarter order (display order used in the table)
        // Default to Q3, Q4, Q1, Q2; adjust labels dynamically below
        const quarterOrder = ['Q3', 'Q4', 'Q1', 'Q2'];
        
        // Create header rows with correct column structure
        // Calculate total number of columns: 1 (Category) + 2*quarters + 3 (Annual) + 1 (Currency)
        const totalColumns = 1 + (quarterOrder.length * 2) + 3 + 1;
        
        // Header1: Section title in first column, rest empty to span all columns
        const header1 = ['Section 2: Forecast ' + actualYear];
        while (header1.length < totalColumns) {
            header1.push('');
        }
        
        // Header2: Category label, then quarter labels, then annual totals label, then currency label
        const header2 = [''];
        quarterOrder.forEach(quarter => {
            header2.push(quarter, '');
        });
        header2.push('Annual totals', '', '', ''); // Annual totals label + 3 empty cells
        
        // Header3: Data type labels (only the past quarter shows Actual)
        const header3 = [''];
        quarterOrder.forEach(quarter => {
            if (parseInt(quarter.replace('Q','')) === pastQuarter) {
                header3.push('Budget', 'Actual');
            } else {
                header3.push('Budget', 'Forecast');
            }
        });
        header3.push('Budget', 'Actual + Forecast', 'Variance (%)', 'Currency');

        let rows = [header1, header2, header3];
        const totalsByCur = {
          ETB: {q1b:0,q1a:0,q1f:0,q2b:0,q2a:0,q2f:0,q3b:0,q3a:0,q3f:0,q4b:0,q4a:0,q4f:0,ab:0,aa:0,af:0,aaf:0},
          USD: {q1b:0,q1a:0,q1f:0,q2b:0,q2a:0,q2f:0,q3b:0,q3a:0,q3f:0,q4b:0,q4a:0,q4f:0,ab:0,aa:0,af:0,aaf:0},
          EUR: {q1b:0,q1a:0,q1f:0,q2b:0,q2a:0,q2f:0,q3b:0,q3a:0,q3f:0,q4b:0,q4a:0,q4f:0,ab:0,aa:0,af:0,aaf:0}
        };

        categories.forEach(category => {
          const categoryRows = Array.from(table.querySelectorAll('tbody tr')).filter(row => 
            row.querySelector('td:first-child') && 
            row.querySelector('td:first-child').textContent.trim() === category
          );
          if (categoryRows.length === 0) return;

          const annualRow = categoryRows.find(row => {
            const cells = row.querySelectorAll('td');
            return cells.length >= 12 && cells[9] && cells[9].getAttribute('data-label') === 'Annual Budget';
          });

          // Extract data for all quarters
          let q3b=0,q3a=0,q3f=0,q4b=0,q4a=0,q4f=0,q1b=0,q1a=0,q1f=0,q2b=0,q2a=0,q2f=0,ab=0,aa=0,af=0,aaf=0,varianceText='';
          if (annualRow) {
            const cells = annualRow.querySelectorAll('td');
            // Get all quarterly data
            q3b = parseNumber(cells[1]?.textContent);
            q3a = parseNumber(cells[2]?.textContent);
            q4b = parseNumber(cells[3]?.textContent);
            q4a = parseNumber(cells[4]?.textContent);
            q1b = parseNumber(cells[5]?.textContent);
            q1f = parseNumber(cells[6]?.textContent);
            q2b = parseNumber(cells[7]?.textContent);
            q2f = parseNumber(cells[8]?.textContent);
            // Annual totals
            ab = parseNumber(cells[9]?.textContent); // Annual Budget
            aaf = parseNumber(cells[10]?.textContent); // Actual + Forecast
            varianceText = (cells[11]?.textContent || '').trim();
            
            // Calculate Annual Actual based on past quarter
            if (pastQuarter === 3) {
              aa = q3a;
            } else if (pastQuarter === 4) {
              aa = q4a;
            } else if (pastQuarter === 1) {
              aa = q1a;
            } else if (pastQuarter === 2) {
              aa = q2a;
            }
            
            // Calculate Annual Forecast (for the new formula)
            af = aaf - aa;
          }

          const fromC = window.selectedCurrency || 'ETB';
          ['ETB','USD','EUR'].forEach(cur => {
            const cq3b = convertAmount(q3b, fromC, cur);
            const cq3a = convertAmount(q3a, fromC, cur);
            const cq3f = convertAmount(q3f || 0, fromC, cur);
            const cq4b = convertAmount(q4b, fromC, cur);
            const cq4a = convertAmount(q4a, fromC, cur);
            const cq4f = convertAmount(q4f || 0, fromC, cur);
            const cq1b = convertAmount(q1b, fromC, cur);
            const cq1a = convertAmount(q1a || 0, fromC, cur);
            const cq1f = convertAmount(q1f, fromC, cur);
            const cq2b = convertAmount(q2b, fromC, cur);
            const cq2a = convertAmount(q2a || 0, fromC, cur);
            const cq2f = convertAmount(q2f, fromC, cur);
            const cab = convertAmount(ab, fromC, cur);
            const caa = convertAmount(aa, fromC, cur);
            const caf = convertAmount(af, fromC, cur);
            const caaf = convertAmount(aaf, fromC, cur);

            // Update totals
            totalsByCur[cur].q3b += cq3b;
            if (pastQuarter === 3) {
              totalsByCur[cur].q3a += cq3a;
            } else {
              totalsByCur[cur].q3f += cq3f;
            }
            totalsByCur[cur].q4b += cq4b;
            if (pastQuarter === 4) {
              totalsByCur[cur].q4a += cq4a;
            } else {
              totalsByCur[cur].q4f += cq4f;
            }
            totalsByCur[cur].q1b += cq1b;
            if (pastQuarter === 1) {
              totalsByCur[cur].q1a += cq1a;
            } else {
              totalsByCur[cur].q1f += cq1f;
            }
            totalsByCur[cur].q2b += cq2b;
            if (pastQuarter === 2) {
              totalsByCur[cur].q2a += cq2a;
            } else {
              totalsByCur[cur].q2f += cq2f;
            }
            totalsByCur[cur].ab += cab;
            totalsByCur[cur].aa += caa;
            totalsByCur[cur].af += caf;
            totalsByCur[cur].aaf += caaf;

            // Build row data dynamically based on quarter order
            const rowData = [category];
            quarterOrder.forEach(quarter => {
                switch(quarter) {
                    case 'Q3':
                        rowData.push(
                            cq3b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                            (pastQuarter === 3 ? 
                              cq3a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                              cq3f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                            )
                        );
                        break;
                    case 'Q4':
                        rowData.push(
                            cq4b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                            (pastQuarter === 4 ? 
                              cq4a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                              cq4f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                            )
                        );
                        break;
                    case 'Q1':
                        rowData.push(
                            cq1b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                            (pastQuarter === 1 ? 
                              cq1a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                              cq1f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                            )
                        );
                        break;
                    case 'Q2':
                        rowData.push(
                            cq2b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                            (pastQuarter === 2 ? 
                              cq2a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                              cq2f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                            )
                        );
                        break;
                }
            });
            
            // Add annual totals (3 columns)
            rowData.push(
                cab.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                caaf.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                varianceText
            );
            
            // Add currency (1 column)
            rowData.push(cur);
            
            rows.push(rowData);
          });
        });

        // Calculate variance based on selected mode
        ['ETB','USD','EUR'].forEach(cur => {
          let v = 0;
          if (totalsByCur[cur].ab !== 0) {
            if (varianceMode === 'budget_actual_forecast') {
              // New formula: (Annual Budget - (Annual Actual + Forecast)) / Annual Budget × 100
              v = ((totalsByCur[cur].ab - totalsByCur[cur].aaf) / totalsByCur[cur].ab) * 100;
            } else {
              // Original formula: (Annual Budget - Annual Actual) / Annual Budget × 100
              v = ((totalsByCur[cur].ab - totalsByCur[cur].aa) / totalsByCur[cur].ab) * 100;
            }
          }
          
          // Build grand total row data dynamically based on quarter order
          const grandTotalRow = ['Grand Total'];
          quarterOrder.forEach(quarter => {
              switch(quarter) {
                  case 'Q3':
                      grandTotalRow.push(
                          totalsByCur[cur].q3b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                          (pastQuarter === 3 ? 
                            totalsByCur[cur].q3a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                            totalsByCur[cur].q3f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                          )
                      );
                      break;
                  case 'Q4':
                      grandTotalRow.push(
                          totalsByCur[cur].q4b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                          (pastQuarter === 4 ? 
                            totalsByCur[cur].q4a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                            totalsByCur[cur].q4f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                          )
                      );
                      break;
                  case 'Q1':
                      grandTotalRow.push(
                          totalsByCur[cur].q1b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                          (pastQuarter === 1 ? 
                            totalsByCur[cur].q1a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                            totalsByCur[cur].q1f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                          )
                      );
                      break;
                  case 'Q2':
                      grandTotalRow.push(
                          totalsByCur[cur].q2b.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
                          (pastQuarter === 2 ? 
                            totalsByCur[cur].q2a.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}) :
                            totalsByCur[cur].q2f.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2})
                          )
                      );
                      break;
              }
          });
          
          // Add annual totals (3 columns)
          grandTotalRow.push(
              totalsByCur[cur].ab.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
              totalsByCur[cur].aaf.toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:2}),
              v.toFixed(2) + '%'
          );
          
          // Add currency (1 column)
          grandTotalRow.push(cur);
          
          rows.push(grandTotalRow);
        });

        return rows;
      }

      // Build multi-currency rows for a single CSV
      const rowsETB = undefined; // deprecated
      const rowsUSD = undefined; // deprecated
      const rowsEUR = undefined; // deprecated
      const rowsMulti = buildTable2MultiCurrencyRows();
      
      // Convert 2D array to CSV string with proper quoting
      const toCsvString = (rows) => rows.map(row => {
        return row.map(cell => {
          if (cell && (typeof cell === 'string') && (cell.includes(',') || cell.includes('"') || cell.includes('\n'))) {
            return '"' + cell.replace(/"/g, '""') + '"';
          }
          return cell;
        }).join(',');
      }).join('\r\n');

      const budgetCSV_MULTI = toCsvString(rowsMulti);

      // 2. Fetch transactions CSVs from backend using actual year and selected cluster, per currency
      let baseTxUrl = `ajax_handler.php?action=export_transactions_multi_csv&year=${actualYear}`;
      // Always pass the selected cluster for filtering consistency
      if (selectedCluster) {
        baseTxUrl += `&cluster=${encodeURIComponent(selectedCluster)}`;
      }
      
      // Pass custom currency rates if they are being used
      if (effectiveUseCustom) {
        baseTxUrl += `&use_custom_rates=1`;
        baseTxUrl += `&usd_to_etb=${encodeURIComponent(rates['USD_to_ETB'] || 300.0)}`;
        baseTxUrl += `&eur_to_etb=${encodeURIComponent(rates['EUR_to_ETB'] || 320.0)}`;
        baseTxUrl += `&usd_to_eur=${encodeURIComponent(rates['USD_to_EUR'] || 0.9375)}`;
      }
      const txUrlMulti = baseTxUrl;

      // 3. Fetch transactions CSVs in parallel with timeouts
      const withTimeout = (promise, ms) => new Promise((resolve, reject) => {
        const id = setTimeout(() => reject(new Error('Request timed out')), ms);
        promise.then((res) => { clearTimeout(id); resolve(res); }, (err) => { clearTimeout(id); reject(err); });
      });

      withTimeout(fetch(txUrlMulti).then(r => r.text()), 30000)
        .then((transactionsMulti) => {
          // 4. Create ZIP file with all six CSVs
          const zip = new JSZip();
          zip.file('Budget_Data_Table2_MultiCurrency.csv', budgetCSV_MULTI);
          zip.file('Transactions_MultiCurrency.csv', transactionsMulti);

          zip.generateAsync({ type: 'blob' }).then(function(content) {
            const url = URL.createObjectURL(content);
            const a = document.createElement('a');
            a.href = url;
            // Include cluster information in the filename if it exists
            const clusterSuffix = selectedCluster ? `_${selectedCluster}` : '';
            a.download = `Budget_Report_Year_${year}${clusterSuffix}.zip`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);

            exportExcelBtn.innerHTML = '<i class="fas fa-file-archive"></i> Export ZIP (2 CSV Files)';
            exportExcelBtn.disabled = false;
            const clusterText = selectedCluster ? ` - ${selectedCluster}` : '';
            showNotification(`ZIP file exported successfully for Year ${year}${clusterText}!`, 'success');
          });
        })
        .catch(error => {
          exportExcelBtn.innerHTML = '<i class="fas fa-file-archive"></i> Export ZIP (6 CSV Files)';
          exportExcelBtn.disabled = false;
          if (typeof showNotification === 'function') showNotification('Export failed: ' + error.message, 'error');
        });
      } catch (err) {
        exportExcelBtn.innerHTML = '<i class="fas fa-file-archive"></i> Export ZIP (6 CSV Files)';
        exportExcelBtn.disabled = false;
        if (typeof showNotification === 'function') showNotification('Export failed: ' + (err && err.message ? err.message : err), 'error');
      }
    }

    // File Upload Functionality with Drag & Drop
    const fileUpload = document.getElementById('fileUpload');
    const fileInput = document.getElementById('fileInput');
    const uploadedFileContainer = document.getElementById('uploadedFileContainer');
    const uploadedFileName = document.getElementById('uploadedFileName');
    const uploadBtn = document.getElementById('uploadBtn');

    // Click to upload
    fileUpload.addEventListener('click', () => {
      fileInput.click();
    });

    // File input change event
    fileInput.addEventListener('change', (e) => {
      handleFileSelect(e.target.files[0]);
    });

    // Drag and drop events
    fileUpload.addEventListener('dragover', (e) => {
      e.preventDefault();
      fileUpload.classList.add('drag-over');
    });

    fileUpload.addEventListener('dragleave', (e) => {
      e.preventDefault();
      fileUpload.classList.remove('drag-over');
    });

    fileUpload.addEventListener('drop', (e) => {
      e.preventDefault();
      fileUpload.classList.remove('drag-over');
      const files = e.dataTransfer.files;
      if (files.length > 0) {
        handleFileSelect(files[0]);
      }
    });

    function handleFileSelect(file) {
      if (!file) return;
      
      // Validate file type
      const allowedTypes = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
      if (!allowedTypes.includes(file.type)) {
        showNotification('Please select a PDF, JPG, or PNG file.', 'error');
        return;
      }
      
      // Validate file size (5MB limit)
      const maxSize = 5 * 1024 * 1024; // 5MB in bytes
      if (file.size > maxSize) {
        showNotification('File size must be less than 5MB.', 'error');
        return;
      }
      
      // Show uploaded file
      uploadedFileName.textContent = file.name;
      uploadedFileContainer.style.display = 'flex';
      fileUpload.style.display = 'none';
      
      // Store file reference for upload
      uploadBtn.dataset.file = file.name;
      
      showNotification(`File "${file.name}" selected successfully!`, 'success');
    }

    // Upload button functionality
    uploadBtn.addEventListener('click', () => {
      const fileName = uploadBtn.dataset.file;
      if (fileName) {
        // Get the actual file from the file input
        const fileInputElement = document.getElementById('fileInput');
        if (fileInputElement && fileInputElement.files.length > 0) {
          const file = fileInputElement.files[0];
          const currentYear = document.getElementById('yearFilter').value || '1';
          
          // Create FormData for file upload
          const formData = new FormData();
          formData.append('action', 'upload_certificate');
          formData.append('certificate', file);
          formData.append('year', currentYear);
          
          // Show upload progress
          uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
          uploadBtn.disabled = true;
          
          // Send AJAX request
          fetch('ajax_handler.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              uploadBtn.innerHTML = '<i class="fas fa-check"></i> Uploaded Successfully!';
              uploadBtn.classList.remove('btn-primary');
              uploadBtn.classList.add('btn-success');
              
              showNotification(`Certificate uploaded successfully for Year ${currentYear}! Budget data marked as certified.`, 'success');
              
              // Reset after delay
              setTimeout(() => {
                uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Signed Copy';
                uploadBtn.disabled = false;

                uploadBtn.classList.remove('btn-success');
                uploadBtn.classList.add('btn-primary');
                
                // Clear file selection
                uploadedFileContainer.style.display = 'none';
                fileUpload.style.display = 'block';
                fileInputElement.value = '';
                delete uploadBtn.dataset.file;
              }, 3000);
            } else {
              uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Signed Copy';
              uploadBtn.disabled = false;
              showNotification(data.message || 'Certificate upload failed!', 'error');
            }
          })
          .catch(error => {
            uploadBtn.innerHTML = '<i class="fas fa-upload"></i> Upload Signed Copy';
            uploadBtn.disabled = false;
            showNotification('Upload failed: ' + error.message, 'error');
          });
        } else {
          showNotification('No file selected!', 'error');
        }
      } else {
        showNotification('Please select a file first.', 'error');
      }
    });

    // Remove uploaded file functionality
    uploadedFileContainer.addEventListener('click', () => {
      uploadedFileContainer.style.display = 'none';
      fileUpload.style.display = 'block';
      fileInput.value = '';
      delete uploadBtn.dataset.file;
      showNotification('File removed successfully.', 'info');
    });

    // Certify Report button functionality
    const certifyBtn = document.getElementById('certifyBtn');
    if (certifyBtn) {
      certifyBtn.addEventListener('click', () => {
       
        // Create a file input for certificate upload
        const certificateInput = document.createElement('input');
        certificateInput.type = 'file';
        certificateInput.accept = '.pdf';
        certificateInput.style.display = 'none';
        
        certificateInput.addEventListener('change', (e) => {
          const file = e.target.files[0];
          if (file) {
            const currentYear = document.getElementById('yearFilter').value || '1';
            
            // Validate file type
            if (file.type !== 'application/pdf') {
              showNotification('Please select a PDF file only.', 'error');
              return;
            }
            
            // Validate file size (10MB limit)
            const maxSize = 10 * 1024 * 1024; // 10MB
            if (file.size > maxSize) {
              showNotification('Certificate file size must be less than 10MB.', 'error');
              return;
            }
            
            // Create FormData for file upload
            const formData = new FormData();
            formData.append('action', 'upload_certificate');
            formData.append('certificate', file);
            formData.append('year', currentYear);
            
            // Show upload progress
            certifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading Certificate...';
            certifyBtn.disabled = true;
            
            // Send AJAX request
            fetch('ajax_handler.php', {
              method: 'POST',
              body: formData
            })
            .then(response => response.json())
            .then(data => {
              if (data.success) {
                certifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Certified Successfully!';
                certifyBtn.classList.remove('btn-success');
                certifyBtn.classList.add('btn-primary');
                
                showNotification(`Certificate uploaded successfully for Year ${currentYear}! Budget data marked as certified.`, 'success');
                
                // Reset after delay
                setTimeout(() => {
                  certifyBtn.innerHTML = '<i class="fas fa-certificate"></i> Certify Report';
                  certifyBtn.disabled = false;
                  certifyBtn.classList.remove('btn-primary');
                  certifyBtn.classList.add('btn-success');
                }, 3000);
              } else {
                certifyBtn.innerHTML = '<i class="fas fa-certificate"></i> Certify Report';
                certifyBtn.disabled = false;
                showNotification(data.message || 'Certificate upload failed!', 'error');
              }
            })
            .catch(error => {

              certifyBtn.innerHTML = '<i class="fas fa-certificate"></i> Certify Report';
              certifyBtn.disabled = false;
              showNotification('Upload failed: ' + error.message, 'error');
            });
          }
          
          // Clean up the input element
          document.body.removeChild(certificateInput);
        });
        
        // Add to DOM and trigger click
        document.body.appendChild(certificateInput);
        certificateInput.click();
      });
    }

    // Notification system
   

    // Enhanced hover effects for quarterly data cells
    document.addEventListener('DOMContentLoaded', function() {
      const tables = document.querySelectorAll('.vertical-table');
      
      tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach((row, rowIndex) => {
          const cells = row.querySelectorAll('td');
          
          cells.forEach((cell, cellIndex) => {
            cell.addEventListener('mouseenter', function() {
              // Find the category cell for this row (could be in current row or previous rows due to rowspan)
              let categoryCell = null;
              let categoryHeaderRow = null;
              let annualTotalRow = null;
              let q1Row = null;
              
              // Check if current row has category cell
              if (row.querySelector('td[rowspan]')) {
                categoryCell = row.querySelector('td[rowspan]');
                categoryHeaderRow = row;
              } else {
                // Look backwards to find the category header row
                for (let i = rowIndex - 1; i >= 0; i--) {
                  const prevRow = rows[i];
                  const prevRowCategoryCell = prevRow.querySelector('td[rowspan]');
                  if (prevRowCategoryCell) {
                    categoryCell = prevRowCategoryCell;
                    categoryHeaderRow = prevRow;
                    break;
                  }
                }
              }
              
              // Find the Annual Total row and Q1 row for this category group
              if ( categoryHeaderRow) {
                const categoryRowspan = categoryCell ? parseInt(categoryCell.getAttribute('rowspan')) : 0;
                const categoryStartIndex = Array.from(rows).indexOf(categoryHeaderRow);
                const annualTotalIndex = categoryStartIndex + categoryRowspan - 1;
                
                // Find Annual Total row
                if (annualTotalIndex < rows.length && rows[annualTotalIndex].classList.contains('summary-section')) {
                  annualTotalRow = rows[annualTotalIndex];
                }
                
                // Find Q1 row (first row after category header)
                if (categoryStartIndex + 1 < rows.length) {
                  q1Row = rows[categoryStartIndex];
                }
              }
              
              // Apply highlights - now include when hovering on category cell itself
              const isHoveringCategoryCell = (categoryCell && cell === categoryCell);
              
              // Always highlight category cell (unless hovering on it directly, to avoid double native+JS highlighting)
              if (categoryCell && !isHoveringCategoryCell) {
                categoryCell.classList.add('category-native-highlight');
              }
              
              // Highlight the entire Annual Total row with professional background
              // Include when hovering on category cell
              if (annualTotalRow) {
                annualTotalRow.classList.add('annual-total-row-highlight');
                // Also highlight the Annual Total text field (first column) with dark blue
                const annualTotalFirstCell = annualTotalRow.querySelector('td:first-child');
                if (annualTotalFirstCell) {
                  annualTotalFirstCell.classList.add('annual-total-text-highlight');
                }
              }
              
              // Highlight Q1 row with professional background
              // Include when hovering on category cell
              if (q1Row) {
                q1Row.classList.add('q1-row-highlight');
              }
            });
            
            cell.addEventListener('mouseleave', function() {
              // Remove all highlights from all rows
              rows.forEach(r => {
                const allCells = r.querySelectorAll('td');
                allCells.forEach(c => {
                  c.classList.remove('category-native-highlight', 'annual-total-text-highlight');
                });
                r.classList.remove('annual-total-row-highlight', 'q1-row-highlight');
              });
            });
          });
        });
      });
    });

    // Initialize with default year
    updatePageTitle('2025');

    // Variance Formula toggle state
    let varianceFormula = 'budget_actual'; // 'budget_actual' (default) or 'budget_actual_forecast'

    // Initialize from any pre-selected radio input if present
    (function initializeVarianceFormulaFromUI() {
      const selectedRadio = document.querySelector('input[name="varianceFormula"]:checked');
      if (selectedRadio && (selectedRadio.value === 'budget_actual' || selectedRadio.value === 'budget_actual_forecast')) {
        varianceFormula = selectedRadio.value;
      }
    })();

    // Hook up toggle
    document.querySelectorAll('input[name="varianceFormula"]').forEach(r => {
      r.addEventListener('change', () => {
        varianceFormula = r.value;
        // Do not auto-recalculate here; Apply button handles Section 3 updates
      });
    });

    // Apply button to recalculate ONLY Section 3 (Table 2)
    document.addEventListener('DOMContentLoaded', function() {
      const applyVarianceBtn = document.getElementById('applyVarianceBtn');
      if (applyVarianceBtn) {
        applyVarianceBtn.addEventListener('click', function() {
          if (typeof window.fillTable2AnnualTotals === 'function') window.fillTable2AnnualTotals();
          if (typeof window.calculateTable2GrandTotal === 'function') window.calculateTable2GrandTotal();
        });
      }
    });

    // Front-end Grand Total calculation for Table 2
    document.addEventListener('DOMContentLoaded', function() {
      // Expose function globally so other listeners (like variance toggle) can call it
      window.calculateTable2GrandTotal = function() {
        const table = document.querySelector('#section3-table .vertical-table');
        if (!table) return;

        // Initialize totals using displayed columns only
        let q3BudgetTotal = 0, q4BudgetTotal = 0, q1BudgetTotal = 0, q2BudgetTotal = 0;
        let annualActualTotal = 0, displayedAFTotal = 0;
        let annualBudgetTotal = 0;

        // Determine the current quarter based on the current date
        const currentMonth = new Date().getMonth() + 1; // 1-12
        const currentQuarter = Math.ceil(currentMonth / 3); // 1, 2, 3, or 4
        const pastQuarter = ((currentQuarter - 2 + 4) % 4) + 1; // previous quarter (1, 2, 3, or 4)
        
        // Get variance calculation mode from toggle
        const varianceToggle = document.getElementById('varianceToggle');
        const varianceMode = varianceToggle && varianceToggle.checked ? 'budget_actual_forecast' : 'budget_actual';

        // Find all rows except the header and Grand Total
        const rows = table.querySelectorAll('tbody tr:not(.grand-total-section)');
        rows.forEach(row => {
          const q3Budget = parseFloat((row.querySelector('td[data-label="Q3 Budget"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q3Actual = parseFloat((row.querySelector('td[data-label="Q3 Actual"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q3Forecast = parseFloat((row.querySelector('td[data-label="Q3 Forecast"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q4Budget = parseFloat((row.querySelector('td[data-label="Q4 Budget"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q4Actual = parseFloat((row.querySelector('td[data-label="Q4 Actual"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q4Forecast = parseFloat((row.querySelector('td[data-label="Q4 Forecast"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q1Budget = parseFloat((row.querySelector('td[data-label="Q1 Budget"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q1Actual = parseFloat((row.querySelector('td[data-label="Q1 Actual"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q1Forecast = parseFloat((row.querySelector('td[data-label="Q1 Forecast"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q2Budget = parseFloat((row.querySelector('td[data-label="Q2 Budget"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q2Actual = parseFloat((row.querySelector('td[data-label="Q2 Actual"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const q2Forecast = parseFloat((row.querySelector('td[data-label="Q2 Forecast"]')?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          
          q3BudgetTotal += q3Budget; 
          q4BudgetTotal += q4Budget; 
          q1BudgetTotal += q1Budget; 
          q2BudgetTotal += q2Budget;
          annualBudgetTotal += (q3Budget + q4Budget + q1Budget + q2Budget);

          // For Annual Actual: only sum Actual values from past quarters
          if (pastQuarter === 3) {
            annualActualTotal += q3Actual;
          } else if (pastQuarter === 4) {
            annualActualTotal += q4Actual;
          } else if (pastQuarter === 1) {
            annualActualTotal += q1Actual;
          } else if (pastQuarter === 2) {
            annualActualTotal += q2Actual;
          }
          
          // Calculate Actual + Forecast based on display logic
          // Past quarter shows Actual
          if (pastQuarter === 3) {
            displayedAFTotal += q3Actual;
          } else if (pastQuarter === 4) {
            displayedAFTotal += q4Actual;
          } else if (pastQuarter === 1) {
            displayedAFTotal += q1Actual;
          } else if (pastQuarter === 2) {
            displayedAFTotal += q2Actual;
          }
          // Current and future quarters show Forecast
          if (pastQuarter !== 3) {
            displayedAFTotal += q3Forecast;
          }
          if (pastQuarter !== 4) {
            displayedAFTotal += q4Forecast;
          }
          if (pastQuarter !== 1) {
            displayedAFTotal += q1Forecast;
          }
          if (pastQuarter !== 2) {
            displayedAFTotal += q2Forecast;
          }
        });

        // Calculate variance based on selected mode
        let variance = 0;
        if (annualBudgetTotal !== 0) {
          if (varianceMode === 'budget_actual_forecast') {
            // New formula: (Annual Budget - (Annual Actual + Forecast)) / Annual Budget × 100
            variance = ((annualBudgetTotal - displayedAFTotal) / annualBudgetTotal) * 100;
          } else {
            // Original formula: (Annual Budget - Annual Actual) / Annual Budget × 100
            variance = ((annualBudgetTotal - annualActualTotal) / annualBudgetTotal) * 100;
          }
        }

        // Determine variance class based on global financial standards
        let varianceClass = 'variance-zero';
        if (variance > 0) {
          varianceClass = 'variance-positive'; // Overspent - red
        } else if (variance < 0) {
          varianceClass = 'variance-negative'; // Underspent - green
        }

        // Remove existing Grand Total row if present
        const oldGrandTotal = table.querySelector('tr.grand-total-section');
        if (oldGrandTotal) oldGrandTotal.remove();

        // Add Grand Total row with correct column count (13 columns total)
        const grandTotalRow = document.createElement('tr');
        grandTotalRow.className = 'grand-total-section';
        grandTotalRow.innerHTML = `
          <td class="grand-total-label">Grand Total</td>
          <td>${q3BudgetTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${(function(){
            // Sum displayed value for Q3 second column (Actual if past quarter else Forecast)
            let sum = 0; 
            rows.forEach(row => {
              if (pastQuarter === 3) {
                sum += parseFloat((row.querySelector('td[data-label="Q3 Actual"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              } else {
                sum += parseFloat((row.querySelector('td[data-label="Q3 Forecast"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              }
            }); 
            return sum;
          })().toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${q4BudgetTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${(function(){ 
            let sum = 0; 
            rows.forEach(row => {
              if (pastQuarter === 4) {
                sum += parseFloat((row.querySelector('td[data-label="Q4 Actual"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              } else {
                sum += parseFloat((row.querySelector('td[data-label="Q4 Forecast"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              }
            }); 
            return sum; 
          })().toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${q1BudgetTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${(function(){ 
            let sum = 0; 
            rows.forEach(row => {
              if (pastQuarter === 1) {
                sum += parseFloat((row.querySelector('td[data-label="Q1 Actual"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              } else {
                sum += parseFloat((row.querySelector('td[data-label="Q1 Forecast"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              }
            }); 
            return sum; 
          })().toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${q2BudgetTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${(function(){ 
            let sum = 0; 
            rows.forEach(row => {
              if (pastQuarter === 2) {
                sum += parseFloat((row.querySelector('td[data-label="Q2 Actual"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              } else {
                sum += parseFloat((row.querySelector('td[data-label="Q2 Forecast"]')?.textContent.replace(/[^0-9.-]/g,'') || 0)) || 0;
              }
            }); 
            return sum; 
          })().toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${annualBudgetTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${displayedAFTotal.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td><span class="${varianceClass}">${variance.toFixed(2)}%</span></td>
        `;
        table.querySelector('tbody').appendChild(grandTotalRow);
      }

      // Run on load and when switching tables

      window.calculateTable2GrandTotal();
      document.getElementById('tableSelection').addEventListener('change', function() {
        if (this.value === 'section3') {
          // Ensure per-row totals are refreshed before grand total when switching to Section 3
          setTimeout(function() {
            if (typeof window.fillTable2AnnualTotals === 'function') window.fillTable2AnnualTotals();
            if (typeof window.calculateTable2GrandTotal === 'function') window.calculateTable2GrandTotal();
          }, 100);
        }
      });
      
      // Also recalculate when year changes
      document.getElementById('yearFilter').addEventListener('change', function() {
        if (document.getElementById('tableSelection').value === 'section3') {
          setTimeout(window.calculateTable2GrandTotal, 100);
        }
      });
    });

    // Add after Table 1 rendering (inside <script> tag)
    document.addEventListener('DOMContentLoaded', function() {
      // Expose function globally so other listeners (like variance toggle) can call it
      window.calculateTable1GrandTotal = function() {
        const table = document.querySelector('#section2-table .vertical-table');
        if (!table) return;

        let grandBudget = 0, grandActual = 0, grandForecast = 0, grandActualForecast = 0;

        // Only sum Annual Total rows
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
          // Skip the dynamic Grand Total row
          if (row.classList.contains('grand-total-section')) return;

          const cells = Array.from(row.querySelectorAll('td'));
          if (cells.length === 0) return;

          // If the row contains the category cell (rowspan), period is at index 1 (2nd col).
          // Otherwise period is at index 0 (1st col).
          const hasCategoryCell = (cells.length === 7); // Category + Period + 5 numeric + variance
          const periodCellIndex = hasCategoryCell ? 1 : 0;
          const budgetIndex = hasCategoryCell ? 2 : 1;
          const actualIndex = hasCategoryCell ? 3 : 2;
          const forecastIndex = hasCategoryCell ? 4 : 3;
          const actualForecastIndex = hasCategoryCell ? 5 : 4;

          const period = cells[periodCellIndex].textContent.trim();
          if (period === 'Annual Total') {
            grandBudget += parseFloat((cells[budgetIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
            grandActual += parseFloat((cells[actualIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
            grandForecast += parseFloat((cells[forecastIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
            grandActualForecast += parseFloat((cells[actualForecastIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          }
        });

        // Calculate variance using selected formula
        let variance = 0;
        if (grandBudget != 0) {
          // Use Budget vs Actual formula (default)
          const compareAgainst = grandActual;
          variance = (((grandBudget - compareAgainst) / grandBudget) * 100);
        }
        let varianceClass = 'variance-zero';
        if (variance > 0) varianceClass = 'variance-positive';
        else if (variance < 0) varianceClass = 'variance-negative';

        // Remove existing Grand Total row if present
        const existingGrandTotal = table.querySelector('tr.grand-total-section');
        if (existingGrandTotal) existingGrandTotal.remove();

        // Add Grand Total row
        const grandTotalRow = document.createElement('tr');
        grandTotalRow.className = 'grand-total-section';
        grandTotalRow.innerHTML = `
          <td class="grand-total-label">Grand Total</td>
          <td></td>
          <td>${grandBudget.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${grandActual.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${grandForecast.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td>${grandActualForecast.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2})}</td>
          <td><span class="${varianceClass}">${variance.toFixed(2)}%</span></td>
        `;
        table.querySelector('tbody').appendChild(grandTotalRow);
      }

      window.calculateTable1GrandTotal();
      // Recalculate if table changes (add listeners if needed)
    });

    // Initialize with default year
    updatePageTitle('2025');

    // Front-end Grand Total calculation for Table 2
    document.addEventListener('DOMContentLoaded', function() {
      // Expose function globally to respond to variance toggle
      window.recalculateTable1RowVariances = function() {
        const table = document.querySelector('#section2-table .vertical-table');
        if (!table) return;

        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
          // Skip dynamically injected Grand Total row
          if (row.classList.contains('grand-total-section')) return;

          const cells = Array.from(row.querySelectorAll('td'));
          if (cells.length === 0) return;

          const hasCategoryCell = (cells.length === 7); // Category + Period + 4 metrics + Variance
          const budgetIndex = hasCategoryCell ? 2 : 1;
          const actualIndex = hasCategoryCell ? 3 : 2;
          const forecastIndex = hasCategoryCell ? 4 : 3;
          const varianceIndex = hasCategoryCell ? 6 : 5;

          const budget = parseFloat((cells[budgetIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const actual = parseFloat((cells[actualIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          const forecast = parseFloat((cells[forecastIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;

          let variance = 0;
          if (budget !== 0) {
            // Use Budget vs Actual formula (default)
            const compareAgainst = actual;
            variance = ((budget - compareAgainst) / budget) * 100;
          }
          // Removed varianceFormula check - always use Budget vs Actual formula


          let varianceClass = 'variance-zero';
          if (variance > 0) varianceClass = 'variance-positive';
          else if (variance < 0) varianceClass = 'variance-negative';

          cells[varianceIndex].innerHTML = `<span class="${varianceClass}">${variance.toFixed(2)}%</span>`;
        });

        // After updating per-row variances, refresh dynamic Grand Total
        if (typeof window.calculateTable1GrandTotal === 'function') window.calculateTable1GrandTotal();
      };

      // Run once on load to ensure backend variance values are overridden by front-end calculation
      if (typeof window.recalculateTable1RowVariances === 'function') window.recalculateTable1RowVariances();
    });

    function getTable2ExportData() {
      const table = document.querySelector('#section3-table .vertical-table');
      if (!table) return [];
      const rows = table.querySelectorAll('tbody tr:not(.grand-total-section)');
      let totals = {
        q3Budget: 0, q3Actual: 0,
        q4Budget: 0, q4Actual: 0,
        q1Budget: 0, q1Forecast: 0,
        q2Budget: 0, q2Forecast: 0,
        annualBudget: 0, actualForecast: 0
      };
      rows.forEach(row => {
        totals.q3Budget += parseFloat(row.querySelector('td[data-label="Q3 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.q3Actual += parseFloat(row.querySelector('td[data-label="Q3 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.q4Budget += parseFloat(row.querySelector('td[data-label="Q4 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.q4Actual += parseFloat(row.querySelector('td[data-label="Q4 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.q1Budget += parseFloat(row.querySelector('td[data-label="Q1 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.q1Forecast += parseFloat(row.querySelector('td[data-label="Q1 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.q2Budget += parseFloat(row.querySelector('td[data-label="Q2 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.q2Forecast += parseFloat(row.querySelector('td[data-label="Q2 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.annualBudget += parseFloat(row.querySelector('td[data-label="Annual Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
        totals.actualForecast += parseFloat(row.querySelector('td[data-label="Actual + Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      });
      // Calculate variance using currently selected formula
      let variance = 0;
      if (totals.annualBudget !== 0) {
        const actualOnly = totals.q3Actual + totals.q4Actual;
        const actualPlusForecast = totals.q3Actual + totals.q4Actual + totals.q1Forecast + totals.q2Forecast;
        // Use Budget vs Actual formula (default)
        const compareAgainst = actualOnly;
        variance = (((totals.annualBudget - compareAgainst) / totals.annualBudget) * 100);
      }
      return [
        'Grand Total',
        totals.q3Budget, totals.q3Actual,
        totals.q4Budget, totals.q4Actual,
        totals.q1Budget, totals.q1Forecast,
        totals.q2Budget, totals.q2Forecast,
        totals.annualBudget, totals.actualForecast,
        variance.toFixed(2) + '%'
      ];
    }

    function exportTable2ToCSV() {
  const table = document.querySelector('#section3-table .vertical-table');
  if (!table) return;

  // Ensure Grand Total is calculated and present with correct variance based on toggle state
  if (typeof calculateTable2GrandTotal === 'function') calculateTable2GrandTotal();

  let csvRows = [];
  // Get headers
  const headers = Array.from(table.querySelectorAll('thead tr')).map(tr =>
    Array.from(tr.querySelectorAll('th')).map(th => th.textContent.trim())
  );
  headers.forEach(headerRow => csvRows.push(headerRow.join(',')));

  // Get all rows including Grand Total
  const rows = table.querySelectorAll('tbody tr');
  rows.forEach(row => {
    const cells = Array.from(row.querySelectorAll('td')).map(td => {
      // Remove commas from cell text to avoid CSV issues
      return td.textContent.replace(/,/g, '');
    });
    csvRows.push(cells.join(','));
  });

  // Create CSV string
  const csvString = csvRows.join('\r\n');

  // Download CSV
  const blob = new Blob([csvString], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'Budget_Data_Table2.csv';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

    // Update metric cards based on Table 1 data
    function updateMetricCardsFromTable1() {
      const table = document.querySelector('#section2-table .vertical-table');
      if (!table) return;

      let totalBudget = 0, totalActual = 0;

      // Only sum Annual Total rows
      const rows = table.querySelectorAll('tbody tr');
      rows.forEach(row => {
        // Skip the dynamic Grand Total row
        if (row.classList.contains('grand-total-section')) return;

        const cells = Array.from(row.querySelectorAll('td'));
        if (cells.length === 0) return;

        const hasCategoryCell = (cells.length === 7);
        const periodCellIndex = hasCategoryCell ? 1 : 0;
        const budgetIndex = hasCategoryCell ? 2 : 1;
        const actualIndex = hasCategoryCell ? 3 : 2;

        const period = cells[periodCellIndex].textContent.trim();
        if (period === 'Annual Total') {
          totalBudget += parseFloat((cells[budgetIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
          totalActual += parseFloat((cells[actualIndex]?.textContent || '').replace(/[^0-9.-]/g, '')) || 0;
        }
      });

      // Utilization and Remaining
      const utilization = (totalBudget > 0) ? ((totalActual / totalBudget) * 100).toFixed(1) : '0';
      const remaining = (totalBudget - totalActual).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});

      // Update cards
      document.querySelectorAll('.metric-card-mini')[0].querySelector('div > div:nth-child(2) > div:nth-child(2)').textContent = totalBudget.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      document.querySelectorAll('.metric-card-mini')[1].querySelector('div > div:nth-child(2) > div:nth-child(2)').textContent = totalActual.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      document.querySelectorAll('.metric-card-mini')[2].querySelector('div > div:nth-child(2) > div:nth-child(2)').textContent = utilization + '%';
      document.querySelectorAll('.metric-card-mini')[3].querySelector('div > div:nth-child(2) > div:nth-child(2)').textContent = remaining;
    }

    // Run after Table 1 is rendered
    document.addEventListener('DOMContentLoaded', function() {
      updateMetricCardsFromTable1();
      // If Table 1 changes, call again
    });

    document.addEventListener('DOMContentLoaded', function() {
  // Expose function globally so other listeners (like variance toggle) can call it
  window.fillTable2AnnualTotals = function() {
    const table = document.querySelector('#section3-table .vertical-table');
    if (!table) return;

    // Determine the current quarter based on the current date
    const currentMonth = new Date().getMonth() + 1; // 1-12
    const currentQuarter = Math.ceil(currentMonth / 3); // 1, 2, 3, or 4
    const pastQuarter = ((currentQuarter - 2 + 4) % 4) + 1; // previous quarter (1, 2, 3, or 4)
    
    // Get variance calculation mode from toggle
    const varianceToggle = document.getElementById('varianceToggle');
    const varianceMode = varianceToggle && varianceToggle.checked ? 'budget_actual_forecast' : 'budget_actual';

    const rows = table.querySelectorAll('tbody tr:not(.grand-total-section)');
    rows.forEach(row => {
      // Get quarterly cells
      const q3Budget = parseFloat(row.querySelector('td[data-label="Q3 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q3Actual = parseFloat(row.querySelector('td[data-label="Q3 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q3Forecast = parseFloat(row.querySelector('td[data-label="Q3 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q4Budget = parseFloat(row.querySelector('td[data-label="Q4 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q4Actual = parseFloat(row.querySelector('td[data-label="Q4 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q4Forecast = parseFloat(row.querySelector('td[data-label="Q4 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q1Budget = parseFloat(row.querySelector('td[data-label="Q1 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q1Actual = parseFloat(row.querySelector('td[data-label="Q1 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q1Forecast = parseFloat(row.querySelector('td[data-label="Q1 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q2Budget = parseFloat(row.querySelector('td[data-label="Q2 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q2Actual = parseFloat(row.querySelector('td[data-label="Q2 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q2Forecast = parseFloat(row.querySelector('td[data-label="Q2 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;

      // Calculate annual totals
      const annualBudget = q3Budget + q4Budget + q1Budget + q2Budget;
      
      // Calculate Annual Actual: only sum Actual values from past quarters
      let annualActual = 0;
      if (pastQuarter === 3) {
        annualActual = q3Actual;
      } else if (pastQuarter === 4) {
        annualActual = q4Actual;
      } else if (pastQuarter === 1) {
        annualActual = q1Actual;
      } else if (pastQuarter === 2) {
        annualActual = q2Actual;
      }
      
      // Calculate Actual + Forecast based on display logic
      let actualForecast = 0;
      // Past quarter shows Actual
      if (pastQuarter === 3) {
        actualForecast += q3Actual;
      } else if (pastQuarter === 4) {
        actualForecast += q4Actual;
      } else if (pastQuarter === 1) {
        actualForecast += q1Actual;
      } else if (pastQuarter === 2) {
        actualForecast += q2Actual;
      }
      // Current and future quarters show Forecast
      if (pastQuarter !== 3) {
        actualForecast += q3Forecast;
      }
      if (pastQuarter !== 4) {
        actualForecast += q4Forecast;
      }
      if (pastQuarter !== 1) {
        actualForecast += q1Forecast;
      }
      if (pastQuarter !== 2) {
        actualForecast += q2Forecast;
      }

      // Calculate variance based on selected mode
      let variance = 0;
      if (annualBudget !== 0) {
        if (varianceMode === 'budget_actual_forecast') {
          // New formula: (Annual Budget - (Annual Actual + Forecast)) / Annual Budget × 100
          variance = ((annualBudget - actualForecast) / annualBudget) * 100;
        } else {
          // Original formula: (Annual Budget - Annual Actual) / Annual Budget × 100
          variance = ((annualBudget - annualActual) / annualBudget) * 100;
        }
      }
      let varianceClass = 'variance-zero';
      if (variance > 0) varianceClass = 'variance-positive';
      else if (variance < 0) varianceClass = 'variance-negative';

      // Fill annual totals columns
      row.querySelector('td[data-label="Annual Budget"]').textContent = annualBudget.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      row.querySelector('td[data-label="Actual + Forecast"]').textContent = actualForecast.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
      row.querySelector('td[data-label="Variance"]').innerHTML = `<span class="${varianceClass}">${variance.toFixed(2)}%</span>`;
    });
  }
  function getTable2ExportData() {
    const table = document.querySelector('#section3-table .vertical-table');
    if (!table) return [];
    
    // Determine the current quarter based on the current date
    const currentMonth = new Date().getMonth() + 1; // 1-12
    const currentQuarter = Math.ceil(currentMonth / 3); // 1, 2, 3, or 4
    const pastQuarter = ((currentQuarter - 2 + 4) % 4) + 1; // previous quarter (1, 2, 3, or 4)
    
    // Get variance calculation mode from toggle
    const varianceToggle = document.getElementById('varianceToggle');
    const varianceMode = varianceToggle && varianceToggle.checked ? 'budget_actual_forecast' : 'budget_actual';
    
    const rows = table.querySelectorAll('tbody tr:not(.grand-total-section)');
    let totals = {
      q3Budget: 0, q3Actual: 0, q3Forecast: 0,
      q4Budget: 0, q4Actual: 0, q4Forecast: 0,
      q1Budget: 0, q1Actual: 0, q1Forecast: 0,
      q2Budget: 0, q2Actual: 0, q2Forecast: 0,
      annualBudget: 0, annualActual: 0, actualForecast: 0
    };
    rows.forEach(row => {
      // Get all quarterly data
      const q3Budget = parseFloat(row.querySelector('td[data-label="Q3 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q3Actual = parseFloat(row.querySelector('td[data-label="Q3 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q3Forecast = parseFloat(row.querySelector('td[data-label="Q3 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q4Budget = parseFloat(row.querySelector('td[data-label="Q4 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q4Actual = parseFloat(row.querySelector('td[data-label="Q4 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q4Forecast = parseFloat(row.querySelector('td[data-label="Q4 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q1Budget = parseFloat(row.querySelector('td[data-label="Q1 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q1Actual = parseFloat(row.querySelector('td[data-label="Q1 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q1Forecast = parseFloat(row.querySelector('td[data-label="Q1 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q2Budget = parseFloat(row.querySelector('td[data-label="Q2 Budget"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q2Actual = parseFloat(row.querySelector('td[data-label="Q2 Actual"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      const q2Forecast = parseFloat(row.querySelector('td[data-label="Q2 Forecast"]').textContent.replace(/[^0-9.-]/g, '')) || 0;
      
      // Add to totals
      totals.q3Budget += q3Budget;
      totals.q4Budget += q4Budget;
      totals.q1Budget += q1Budget;
      totals.q2Budget += q2Budget;
      totals.annualBudget += (q3Budget + q4Budget + q1Budget + q2Budget);
      
      // For Annual Actual: only sum Actual values from past quarters
      if (pastQuarter === 3) {
        totals.annualActual += q3Actual;
      } else if (pastQuarter === 4) {
        totals.annualActual += q4Actual;
      } else if (pastQuarter === 1) {
        totals.annualActual += q1Actual;
      } else if (pastQuarter === 2) {
        totals.annualActual += q2Actual;
      }
      
      // Calculate Actual + Forecast based on display logic
      // Past quarter shows Actual
      if (pastQuarter === 3) {
        totals.actualForecast += q3Actual;
      } else if (pastQuarter === 4) {
        totals.actualForecast += q4Actual;
      } else if (pastQuarter === 1) {
        totals.actualForecast += q1Actual;
      } else if (pastQuarter === 2) {
        totals.actualForecast += q2Actual;
      }
      // Current and future quarters show Forecast
      if (pastQuarter !== 3) {
        totals.actualForecast += q3Forecast;
      }
      if (pastQuarter !== 4) {
        totals.actualForecast += q4Forecast;
      }
      if (pastQuarter !== 1) {
        totals.actualForecast += q1Forecast;
      }
      if (pastQuarter !== 2) {
        totals.actualForecast += q2Forecast;
      }
    });
    // Calculate variance based on selected mode
    let variance = 0;
    if (totals.annualBudget !== 0) {
      if (varianceMode === 'budget_actual_forecast') {
        // New formula: (Annual Budget - (Annual Actual + Forecast)) / Annual Budget × 100
        variance = ((totals.annualBudget - totals.actualForecast) / totals.annualBudget) * 100;
      } else {
        // Original formula: (Annual Budget - Annual Actual) / Annual Budget × 100
        variance = ((totals.annualBudget - totals.annualActual) / totals.annualBudget) * 100;
      }
    }
    return [
      'Grand Total',
      totals.q3Budget, (pastQuarter === 3 ? totals.q3Actual : totals.q3Forecast),
      totals.q4Budget, (pastQuarter === 4 ? totals.q4Actual : totals.q4Forecast),
      totals.q1Budget, (pastQuarter === 1 ? totals.q1Actual : totals.q1Forecast),
      totals.q2Budget, (pastQuarter === 2 ? totals.q2Actual : totals.q2Forecast),
      totals.annualBudget, totals.actualForecast,
      variance.toFixed(2) + '%'
    ];
  }

  window.fillTable2AnnualTotals();
  if (typeof window.calculateTable2GrandTotal === 'function') window.calculateTable2GrandTotal();
  // Recalculate if table changes (add listeners if needed)
});
  </script>
  
  <!-- Variance Calculation Modal -->
  <div id="varianceModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5);">
    <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 800px; border-radius: 10px; box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2), 0 6px 20px 0 rgba(0,0,0,0.19);">
      <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 15px; border-bottom: 1px solid #eee;">
        <h2 style="margin: 0;">Variance Calculation Results</h2>
        <span class="close" id="closeVarianceModal" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
      </div>
      <div class="modal-body" style="padding: 20px 0;">
        <div id="varianceResults" style="max-height: 400px; overflow-y: auto;">
          <table class="vertical-table" style="width: 100%;">
            <thead>
              <tr>
                <th>Category</th>
                <th>Current Formula</th>
                <th>New Formula</th>
                <th>Difference</th>
              </tr>
            </thead>
            <tbody id="varianceResultsBody">
              <!-- Results will be populated here -->
            </tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer" style="padding-top: 15px; border-top: 1px solid #eee; text-align: right;">
        <button class="btn-primary" id="closeVarianceModalBtn" style="padding: 10px 20px;">Close</button>
      </div>
    </div>
  </div>
  
  <script>
    // Variance calculation toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
      const varianceToggleSection = document.getElementById('varianceToggleSection');
      const tableSelection = document.getElementById('tableSelection');
      const varianceToggle = document.getElementById('varianceToggle');
      const varianceToggleLabel = document.getElementById('varianceToggleLabel');
      const showVarianceModalBtn = document.getElementById('showVarianceModalBtn');
      const varianceModal = document.getElementById('varianceModal');
      const closeVarianceModal = document.getElementById('closeVarianceModal');
      const closeVarianceModalBtn = document.getElementById('closeVarianceModalBtn');
      
      // Track variance calculation mode
      let varianceMode = 'budget_actual'; // 'budget_actual' or 'budget_actual_forecast'
      
      // Show/hide variance toggle based on table selection
      tableSelection.addEventListener('change', function() {
        if (this.value === 'section3') {
          varianceToggleSection.style.display = 'block';
        } else {
          varianceToggleSection.style.display = 'none';
        }
      });
      
      // Initialize toggle visibility
      if (tableSelection.value === 'section3') {
        varianceToggleSection.style.display = 'block';
      }
      
      // Toggle variance calculation mode
      varianceToggle.addEventListener('change', function() {
        if (this.checked) {
          varianceMode = 'budget_actual_forecast';
          varianceToggleLabel.textContent = 'Budget vs Actual + Forecast';
        } else {
          varianceMode = 'budget_actual';
          varianceToggleLabel.textContent = 'Budget vs Actual';
        }
        
        // Recalculate variances
        recalculateSection3Variances();
      });
      
      // Show variance modal
      showVarianceModalBtn.addEventListener('click', function() {
        showVarianceModal();
      });
      
      // Close modal
      closeVarianceModal.addEventListener('click', function() {
        varianceModal.style.display = 'none';
      });
      
      closeVarianceModalBtn.addEventListener('click', function() {
        varianceModal.style.display = 'none';
      });
      
      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        if (event.target === varianceModal) {
          varianceModal.style.display = 'none';
        }
      });
      
      // Function to recalculate section 3 variances
      function recalculateSection3Variances() {
        const table = document.querySelector('#section3-table .vertical-table');
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr:not(.grand-total-section)');
        rows.forEach(row => {
          // Get annual totals
          const annualBudgetCell = row.querySelector('td[data-label="Annual Budget"]');
          const actualForecastCell = row.querySelector('td[data-label="Actual + Forecast"]');
          const varianceCell = row.querySelector('td[data-label="Variance"]');
          
          if (annualBudgetCell && actualForecastCell && varianceCell) {
            const annualBudget = parseFloat(annualBudgetCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
            const actualForecast = parseFloat(actualForecastCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
            
            let variance = 0;
            if (annualBudget !== 0) {
              if (varianceMode === 'budget_actual_forecast') {
                // New formula: (Annual Budget - (Annual Actual + Forecast)) / Annual Budget × 100
                variance = ((annualBudget - actualForecast) / annualBudget) * 100;
              } else {
                // Original formula: (Annual Budget - Annual Actual) / Annual Budget × 100
                // For this we need to calculate Annual Actual from the quarterly data
                const q3Actual = parseFloat(row.querySelector('td[data-label="Q3 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
                const q4Actual = parseFloat(row.querySelector('td[data-label="Q4 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
                const q1Actual = parseFloat(row.querySelector('td[data-label="Q1 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
                const q2Actual = parseFloat(row.querySelector('td[data-label="Q2 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
                
                // Determine past quarter based on current date
                const currentMonth = new Date().getMonth() + 1;
                const currentQuarter = Math.ceil(currentMonth / 3);
                const pastQuarter = ((currentQuarter - 2 + 4) % 4) + 1;
                
                let annualActual = 0;
                if (pastQuarter === 3) annualActual = q3Actual;
                else if (pastQuarter === 4) annualActual = q4Actual;
                else if (pastQuarter === 1) annualActual = q1Actual;
                else if (pastQuarter === 2) annualActual = q2Actual;
                
                variance = ((annualBudget - annualActual) / annualBudget) * 100;
              }
            }
            
            // Apply variance class
            let varianceClass = 'variance-zero';
            if (variance > 0) varianceClass = 'variance-positive';
            else if (variance < 0) varianceClass = 'variance-negative';
            
            varianceCell.innerHTML = `<span class="${varianceClass}">${variance.toFixed(2)}%</span>`;
          }
        });
        
        // Recalculate grand total
        if (typeof window.calculateTable2GrandTotal === 'function') {
          window.calculateTable2GrandTotal();
        }
      }
      
      // Function to show variance modal
      function showVarianceModal() {
        const table = document.querySelector('#section3-table .vertical-table');
        if (!table) return;
        
        const resultsBody = document.getElementById('varianceResultsBody');
        resultsBody.innerHTML = '';
        
        const rows = table.querySelectorAll('tbody tr:not(.grand-total-section)');
        rows.forEach(row => {
          const categoryCell = row.querySelector('td[data-label="Category"]');
          if (!categoryCell) return;
          
          const categoryName = categoryCell.textContent.trim();
          
          // Get current values with original formula
          const annualBudgetCell = row.querySelector('td[data-label="Annual Budget"]');
          const actualForecastCell = row.querySelector('td[data-label="Actual + Forecast"]');
          const varianceCell = row.querySelector('td[data-label="Variance"]');
          
          if (annualBudgetCell && actualForecastCell && varianceCell) {
            const annualBudget = parseFloat(annualBudgetCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
            const actualForecast = parseFloat(actualForecastCell.textContent.replace(/[^0-9.-]/g, '')) || 0;
            
            // Calculate with original formula (Budget vs Actual)
            const q3Actual = parseFloat(row.querySelector('td[data-label="Q3 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
            const q4Actual = parseFloat(row.querySelector('td[data-label="Q4 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
            const q1Actual = parseFloat(row.querySelector('td[data-label="Q1 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
            const q2Actual = parseFloat(row.querySelector('td[data-label="Q2 Actual"]')?.textContent.replace(/[^0-9.-]/g, '') || 0);
            
            // Determine past quarter based on current date
            const currentMonth = new Date().getMonth() + 1;
            const currentQuarter = Math.ceil(currentMonth / 3);
            const pastQuarter = ((currentQuarter - 2 + 4) % 4) + 1;
            
            let annualActual = 0;
            if (pastQuarter === 3) annualActual = q3Actual;
            else if (pastQuarter === 4) annualActual = q4Actual;
            else if (pastQuarter === 1) annualActual = q1Actual;
            else if (pastQuarter === 2) annualActual = q2Actual;
            
            const originalVariance = annualBudget !== 0 ? ((annualBudget - annualActual) / annualBudget) * 100 : 0;
            
            // Calculate with new formula (Budget vs Actual + Forecast)
            const newVariance = annualBudget !== 0 ? ((annualBudget - actualForecast) / annualBudget) * 100 : 0;
            
            // Calculate difference
            const difference = newVariance - originalVariance;
            
            // Add row to modal
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${categoryName}</td>
              <td>${originalVariance.toFixed(2)}%</td>
              <td>${newVariance.toFixed(2)}%</td>
              <td>${difference.toFixed(2)}%</td>
            `;
            resultsBody.appendChild(tr);
          }
        });
        
        // Show modal
        varianceModal.style.display = 'block';
      }
    });
  </script>
</body>
</html>