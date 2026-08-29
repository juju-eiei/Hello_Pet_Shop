<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class DashboardController {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function getFinancials() {
        try {
            $period = $_GET['period'] ?? 'monthly'; // 'daily', 'weekly', 'monthly', 'yearly'
            $selectedDate = $_GET['date'] ?? date('Y-m-d');
            $selectedMonth = $_GET['month'] ?? date('Y-m'); // format YYYY-MM
            $selectedYear = (int)($_GET['year'] ?? date('Y'));
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;

            // Normalize weekly dates if not provided
            if ($period === 'weekly' && (!$startDate || !$endDate)) {
                $ts = strtotime($selectedDate);
                // Monday as start of week, Sunday as end of week
                $dayOfWeek = (int)date('N', $ts); // 1 (Mon) to 7 (Sun)
                $startDate = date('Y-m-d', strtotime('-' . ($dayOfWeek - 1) . ' days', $ts));
                $endDate = date('Y-m-d', strtotime('+' . (7 - $dayOfWeek) . ' days', $ts));
            }
            
            // 1. Fetch all orders (excluding cancelled/status = 5)
            $qOrders = "SELECT order_id, net_total, order_date, status FROM orders WHERE status != 5";
            $stmtOrders = $this->db->query($qOrders);
            $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);
 
            // 2. Fetch all restock orders
            $qRestocks = "SELECT total_cost, order_date FROM restock_orders";
            $stmtRestocks = $this->db->query($qRestocks);
            $restocks = $stmtRestocks->fetchAll(PDO::FETCH_ASSOC);
 
            // 3. Fetch COGS (Cost of Goods Sold) per order from order_details
            $qCogs = "SELECT order_id, SUM(quantity * unit_cost) as total_cogs FROM order_details GROUP BY order_id";
            $stmtCogs = $this->db->query($qCogs);
            $orderCogs = $stmtCogs->fetchAll(PDO::FETCH_KEY_PAIR); // returns [order_id => total_cogs]
 
            // 4. Fetch all active employees
            $employees = $this->db->query("SELECT employee_id, base_salary, payment_frequency FROM employees")->fetchAll(PDO::FETCH_ASSOC);
 
            // Calculate summary financials based on selected period
            $summarySales = 0;
            $summaryCogs = 0;
            $summaryRestocks = 0;
            $summarySalaries = 0;

            if ($period === 'daily') {
                foreach ($orders as $o) {
                    if (date('Y-m-d', strtotime($o['order_date'])) === $selectedDate) {
                        $summarySales += (float)$o['net_total'];
                        $oId = (int)$o['order_id'];
                        $summaryCogs += (float)($orderCogs[$oId] ?? 0);
                    }
                }
                foreach ($restocks as $r) {
                    if (date('Y-m-d', strtotime($r['order_date'])) === $selectedDate) {
                        $summaryRestocks += (float)$r['total_cost'];
                    }
                }
                $summarySalaries = $this->getSalaryExpenseForDate($selectedDate, $employees);
            } elseif ($period === 'weekly') {
                foreach ($orders as $o) {
                    $od = date('Y-m-d', strtotime($o['order_date']));
                    if ($od >= $startDate && $od <= $endDate) {
                        $summarySales += (float)$o['net_total'];
                        $oId = (int)$o['order_id'];
                        $summaryCogs += (float)($orderCogs[$oId] ?? 0);
                    }
                }
                foreach ($restocks as $r) {
                    $rd = date('Y-m-d', strtotime($r['order_date']));
                    if ($rd >= $startDate && $rd <= $endDate) {
                        $summaryRestocks += (float)$r['total_cost'];
                    }
                }
                $cur = strtotime($startDate);
                $endTs = strtotime($endDate);
                while ($cur <= $endTs) {
                    $summarySalaries += $this->getSalaryExpenseForDate(date('Y-m-d', $cur), $employees);
                    $cur = strtotime('+1 day', $cur);
                }
            } elseif ($period === 'yearly') {
                foreach ($orders as $o) {
                    if ((int)date('Y', strtotime($o['order_date'])) === $selectedYear) {
                        $summarySales += (float)$o['net_total'];
                        $oId = (int)$o['order_id'];
                        $summaryCogs += (float)($orderCogs[$oId] ?? 0);
                    }
                }
                foreach ($restocks as $r) {
                    if ((int)date('Y', strtotime($r['order_date'])) === $selectedYear) {
                        $summaryRestocks += (float)$r['total_cost'];
                    }
                }
                $summarySalaries = $this->getSalaryExpenseForYear($selectedYear, $employees);
            } else { // monthly (default)
                foreach ($orders as $o) {
                    if (strpos($o['order_date'], $selectedMonth) === 0) {
                        $summarySales += (float)$o['net_total'];
                        $oId = (int)$o['order_id'];
                        $summaryCogs += (float)($orderCogs[$oId] ?? 0);
                    }
                }
                foreach ($restocks as $r) {
                    if (strpos($r['order_date'], $selectedMonth) === 0) {
                        $summaryRestocks += (float)$r['total_cost'];
                    }
                }
                $summarySalaries = $this->getSalaryExpenseForMonth($selectedMonth, $employees);
            }
            
            $summaryGrossProfit = $summarySales - $summaryCogs;
            $summaryProfit = $summaryGrossProfit - $summarySalaries; // Net Profit
 
            // Generate chart data based on period
            $chartData = [];
            $thaiMonthsShort = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
            $thaiDaysShort = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
            
            if ($period === 'daily') {
                // Hourly breakdown for all 24 hours (00:00 to 23:00)
                $dayOrders = [];
                foreach ($orders as $o) {
                    if (date('Y-m-d', strtotime($o['order_date'])) === $selectedDate) {
                        $h = (int)date('H', strtotime($o['order_date']));
                        if (!isset($dayOrders[$h])) {
                            $dayOrders[$h] = ['sales' => 0, 'cogs' => 0];
                        }
                        $dayOrders[$h]['sales'] += (float)$o['net_total'];
                        $oId = (int)$o['order_id'];
                        $dayOrders[$h]['cogs'] += (float)($orderCogs[$oId] ?? 0);
                    }
                }

                $hourlySalary = $summarySalaries > 0 ? ($summarySalaries / 24.0) : 0;
                for ($hour = 0; $hour <= 23; $hour++) {
                    $sales = $dayOrders[$hour]['sales'] ?? 0;
                    $cogs = $dayOrders[$hour]['cogs'] ?? 0;
                    $gross = $sales - $cogs;
                    $profit = $gross - ($sales > 0 ? $hourlySalary : 0);
                    $chartData[] = [
                        'label' => sprintf('%02d:00', $hour),
                        'sales' => $sales,
                        'cogs' => $cogs,
                        'gross_profit' => $gross,
                        'profit' => $profit
                    ];
                }
            } elseif ($period === 'weekly') {
                // 7 days of the week (Monday to Sunday)
                $cur = strtotime($startDate);
                $endTs = strtotime($endDate);
                while ($cur <= $endTs) {
                    $d = date('Y-m-d', $cur);
                    $dayNum = (int)date('w', $cur); // 0 (Sun) to 6 (Sat)
                    $dayLabel = $thaiDaysShort[$dayNum] . ' ' . (int)date('d', $cur) . ' ' . ($thaiMonthsShort[(int)date('n', $cur)] ?? '');
                    
                    $sales = 0;
                    $cogs = 0;
                    foreach ($orders as $o) {
                        if (date('Y-m-d', strtotime($o['order_date'])) === $d) {
                            $sales += (float)$o['net_total'];
                            $oId = (int)$o['order_id'];
                            $cogs += (float)($orderCogs[$oId] ?? 0);
                        }
                    }
                    $salary = $this->getSalaryExpenseForDate($d, $employees);
                    $gross = $sales - $cogs;
                    $profit = $gross - $salary;
                    
                    $chartData[] = [
                        'label' => $dayLabel,
                        'date' => $d,
                        'sales' => $sales,
                        'cogs' => $cogs,
                        'gross_profit' => $gross,
                        'profit' => $profit
                    ];
                    $cur = strtotime('+1 day', $cur);
                }
            } elseif ($period === 'yearly') {
                // 12 months of $selectedYear
                for ($m = 1; $m <= 12; $m++) {
                    $mStr = sprintf('%04d-%02d', $selectedYear, $m);
                    $mLabel = ($thaiMonthsShort[$m] ?? '') . ' ' . ($selectedYear + 543);
                    $sales = 0;
                    $cogs = 0;
                    foreach ($orders as $o) {
                        if (strpos($o['order_date'], $mStr) === 0) {
                            $sales += (float)$o['net_total'];
                            $oId = (int)$o['order_id'];
                            $cogs += (float)($orderCogs[$oId] ?? 0);
                        }
                    }
                    $salary = $this->getSalaryExpenseForMonth($mStr, $employees);
                    $gross = $sales - $cogs;
                    $profit = $gross - $salary;

                    $chartData[] = [
                        'label' => $mLabel,
                        'month' => $mStr,
                        'sales' => $sales,
                        'cogs' => $cogs,
                        'gross_profit' => $gross,
                        'profit' => $profit
                    ];
                }
            } else { // monthly (default)
                // All days of $selectedMonth
                $selectedMonthDate = strtotime("$selectedMonth-01");
                $numDays = (int)date('t', $selectedMonthDate);
                $mNum = (int)date('n', $selectedMonthDate);
                $shortMonthName = $thaiMonthsShort[$mNum] ?? '';

                for ($day = 1; $day <= $numDays; $day++) {
                    $d = sprintf('%s-%02d', $selectedMonth, $day);
                    $sales = 0;
                    $cogs = 0;
                    foreach ($orders as $o) {
                        if (date('Y-m-d', strtotime($o['order_date'])) === $d) {
                            $sales += (float)$o['net_total'];
                            $oId = (int)$o['order_id'];
                            $cogs += (float)($orderCogs[$oId] ?? 0);
                        }
                    }
                    $salary = $this->getSalaryExpenseForDate($d, $employees);
                    $gross = $sales - $cogs;
                    $profit = $gross - $salary;

                    $chartData[] = [
                        'label' => "$day $shortMonthName",
                        'date' => $d,
                        'sales' => $sales,
                        'cogs' => $cogs,
                        'gross_profit' => $gross,
                        'profit' => $profit
                    ];
                }
            }
  
            $summaryPayload = [
                'sales' => $summarySales,
                'restock' => $summaryRestocks,
                'cogs' => $summaryCogs,
                'salary' => $summarySalaries,
                'gross_profit' => $summaryGrossProfit,
                'profit' => $summaryProfit
            ];

            $response = [
                'period' => $period,
                'date' => $selectedDate,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'month' => $selectedMonth,
                'year' => $selectedYear,
                'summary' => $summaryPayload,
                'this_month' => $summaryPayload, // Backward-compatibility
                'chart' => $chartData
            ];
  
            Response::json(200, "Success", $response);
        } catch (Exception $e) {
            Response::json(500, "Error fetching financial data", ["error" => $e->getMessage()]);
        }
    }

    private function getSalaryExpenseForMonth($monthStr, $employees) {
        $stmt = $this->db->prepare("SELECT employee_id, COUNT(*) as days_worked FROM attendance_logs WHERE DATE_FORMAT(work_date, '%Y-%m') = :month GROUP BY employee_id");
        $stmt->execute([':month' => $monthStr]);
        $attendance = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        $totalSalary = 0;
        foreach ($employees as $e) {
            $base = (float)($e['base_salary'] ?: 0);
            $freq = $e['payment_frequency'] ?: 'Monthly';
            
            if ($freq === 'Daily') {
                $days = (int)($attendance[$e['employee_id']] ?? 0);
                $totalSalary += $base * $days;
            } else {
                $totalSalary += $base;
            }
        }
        return $totalSalary;
    }

    private function getSalaryExpenseForDate($dateStr, $employees) {
        $stmt = $this->db->prepare("SELECT employee_id FROM attendance_logs WHERE work_date = :date");
        $stmt->execute([':date' => $dateStr]);
        $worked = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $workedSet = array_flip($worked);
        
        $totalSalary = 0;
        foreach ($employees as $e) {
            $base = (float)($e['base_salary'] ?: 0);
            $freq = $e['payment_frequency'] ?: 'Monthly';
            
            if ($freq === 'Daily') {
                if (isset($workedSet[$e['employee_id']])) {
                    $totalSalary += $base;
                }
            } else {
                $totalSalary += $base / 30.0;
            }
        }
        return $totalSalary;
    }

    private function getSalaryExpenseForYear($yearVal, $employees) {
        $totalSalary = 0;
        for ($m = 1; $m <= 12; $m++) {
            $monthStr = sprintf('%04d-%02d', $yearVal, $m);
            $totalSalary += $this->getSalaryExpenseForMonth($monthStr, $employees);
        }
        return $totalSalary;
    }
}
