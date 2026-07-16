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
            $period = $_GET['period'] ?? 'monthly'; // 'daily', 'monthly', 'yearly'
            
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

            // Calculate this month's financials
            $thisMonthStr = date('Y-m');
            
            $thisMonthSales = 0;
            $thisMonthCogs = 0;
            foreach ($orders as $o) {
                if (strpos($o['order_date'], $thisMonthStr) === 0) {
                    $thisMonthSales += (float)$o['net_total'];
                    $oId = (int)$o['order_id'];
                    $thisMonthCogs += (float)($orderCogs[$oId] ?? 0);
                }
            }
            
            $thisMonthRestocks = 0;
            foreach ($restocks as $r) {
                if (strpos($r['order_date'], $thisMonthStr) === 0) {
                    $thisMonthRestocks += (float)$r['total_cost'];
                }
            }
            
            $thisMonthSalaries = $this->getSalaryExpenseForMonth($thisMonthStr, $employees);
            $thisMonthGrossProfit = $thisMonthSales - $thisMonthCogs;
            $thisMonthProfit = $thisMonthGrossProfit - $thisMonthSalaries; // Net Profit

            // Generate chart data based on period
            $chartData = [];
            
            if ($period === 'daily') {
                // Last 30 days
                for ($i = 29; $i >= 0; $i--) {
                    $d = date('Y-m-d', strtotime("-$i days"));
                    $sales = 0;
                    $cogs = 0;
                    foreach ($orders as $o) {
                        if (date('Y-m-d', strtotime($o['order_date'])) === $d) {
                            $sales += (float)$o['net_total'];
                            $oId = (int)$o['order_id'];
                            $cogs += (float)($orderCogs[$oId] ?? 0);
                        }
                    }
                    $restock = 0;
                    foreach ($restocks as $r) {
                        if (date('Y-m-d', strtotime($r['order_date'])) === $d) {
                            $restock += (float)$r['total_cost'];
                        }
                    }
                    // Salary calculated daily based on check-ins
                    $salary = $this->getSalaryExpenseForDate($d, $employees);
                    $grossProfit = $sales - $cogs;
                    $profit = $grossProfit - $salary; // Net Profit
                    
                    $label = date('d M', strtotime($d));
                    
                    $chartData[] = [
                        'label' => $label,
                        'sales' => $sales,
                        'cogs' => $cogs,
                        'expenses' => $cogs + $salary,
                        'restock' => $restock,
                        'salary' => $salary,
                        'gross_profit' => $grossProfit,
                        'profit' => $profit
                    ];
                }
            } elseif ($period === 'yearly') {
                // Last 5 years
                $currentYear = (int)date('Y');
                for ($i = 4; $i >= 0; $i--) {
                    $yr = $currentYear - $i;
                    $sales = 0;
                    $cogs = 0;
                    foreach ($orders as $o) {
                        if (date('Y', strtotime($o['order_date'])) == $yr) {
                            $sales += (float)$o['net_total'];
                            $oId = (int)$o['order_id'];
                            $cogs += (float)($orderCogs[$oId] ?? 0);
                        }
                    }
                    $restock = 0;
                    foreach ($restocks as $r) {
                        if (date('Y', strtotime($r['order_date'])) == $yr) {
                            $restock += (float)$r['total_cost'];
                        }
                    }
                    $salary = $this->getSalaryExpenseForYear($yr, $employees);
                    $grossProfit = $sales - $cogs;
                    $profit = $grossProfit - $salary;
                    
                    $chartData[] = [
                        'label' => (string)$yr,
                        'sales' => $sales,
                        'cogs' => $cogs,
                        'expenses' => $cogs + $salary,
                        'restock' => $restock,
                        'salary' => $salary,
                        'gross_profit' => $grossProfit,
                        'profit' => $profit
                    ];
                }
            } else {
                // Monthly: last 12 months
                $firstDayOfThisMonth = date('Y-m-01');
                for ($i = 11; $i >= 0; $i--) {
                    $m = date('Y-m', strtotime("-$i months", strtotime($firstDayOfThisMonth)));
                    $sales = 0;
                    $cogs = 0;
                    foreach ($orders as $o) {
                        if (strpos($o['order_date'], $m) === 0) {
                            $sales += (float)$o['net_total'];
                            $oId = (int)$o['order_id'];
                            $cogs += (float)($orderCogs[$oId] ?? 0);
                        }
                    }
                    $restock = 0;
                    foreach ($restocks as $r) {
                        if (strpos($r['order_date'], $m) === 0) {
                            $restock += (float)$r['total_cost'];
                        }
                    }
                    $salary = $this->getSalaryExpenseForMonth($m, $employees);
                    $grossProfit = $sales - $cogs;
                    $profit = $grossProfit - $salary;
                    
                    $label = date('M Y', strtotime($m . '-01'));
                    
                    $chartData[] = [
                        'label' => $label,
                        'sales' => $sales,
                        'cogs' => $cogs,
                        'expenses' => $cogs + $salary,
                        'restock' => $restock,
                        'salary' => $salary,
                        'gross_profit' => $grossProfit,
                        'profit' => $profit
                    ];
                }
            }
 
            $response = [
                'this_month' => [
                    'sales' => $thisMonthSales,
                    'restock' => $thisMonthRestocks,
                    'cogs' => $thisMonthCogs,
                    'salary' => $thisMonthSalaries,
                    'gross_profit' => $thisMonthGrossProfit,
                    'profit' => $thisMonthProfit
                ],
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
            } elseif ($freq === 'Weekly') {
                $totalSalary += $base * 4;
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
            } elseif ($freq === 'Weekly') {
                $totalSalary += ($base * 4) / 30.0;
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
