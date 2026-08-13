<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

class TransactionController
{
    private $db;

    public function __construct()
    {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    private function getUserId()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return $_SESSION['user_id'] ?? null;
    }

    public function index()
    {
        try {
            $type = $_GET['type'] ?? 'all';
            $category = $_GET['category'] ?? '';
            $startDate = $_GET['start_date'] ?? '';
            $endDate = $_GET['end_date'] ?? '';
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int)($_GET['page'] ?? 1));
            $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $whereClauses = ["1=1"];
            $params = [];

            if ($type === 'income' || $type === 'expense') {
                $whereClauses[] = "type = :type";
                $params[':type'] = $type;
            }

            if (!empty($category)) {
                $whereClauses[] = "category = :category";
                $params[':category'] = $category;
            }

            if (!empty($startDate)) {
                $whereClauses[] = "transaction_date >= :start_date";
                $params[':start_date'] = $startDate;
            }

            if (!empty($endDate)) {
                $whereClauses[] = "transaction_date <= :end_date";
                $params[':end_date'] = $endDate;
            }

            if (!empty($search)) {
                $whereClauses[] = "(title LIKE :search OR description LIKE :search OR category LIKE :search)";
                $params[':search'] = "%{$search}%";
            }

            $whereSql = implode(" AND ", $whereClauses);

            // Fetch summary stats
            $sumQuery = "SELECT 
                            SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END) as total_income,
                            SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END) as total_expense,
                            COUNT(*) as total_count
                         FROM financial_transactions 
                         WHERE {$whereSql}";
            
            $sumStmt = $this->db->prepare($sumQuery);
            $sumStmt->execute($params);
            $summary = $sumStmt->fetch(PDO::FETCH_ASSOC);

            $totalIncome = (float)($summary['total_income'] ?? 0);
            $totalExpense = (float)($summary['total_expense'] ?? 0);
            $totalCount = (int)($summary['total_count'] ?? 0);
            $netBalance = $totalIncome - $totalExpense;

            // Fetch paginated transactions
            $query = "SELECT t.*, u.username as created_by_name 
                      FROM financial_transactions t
                      LEFT JOIN users u ON t.created_by = u.user_id
                      WHERE {$whereSql}
                      ORDER BY t.transaction_date DESC, t.transaction_id DESC
                      LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch distinct categories for filtering dropdown
            $catStmt = $this->db->query("SELECT DISTINCT category FROM financial_transactions ORDER BY category ASC");
            $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

            Response::json(200, "Success", [
                "summary" => [
                    "total_income" => $totalIncome,
                    "total_expense" => $totalExpense,
                    "net_balance" => $netBalance,
                    "total_count" => $totalCount
                ],
                "categories" => $categories,
                "data" => $items,
                "pagination" => [
                    "current_page" => $page,
                    "per_page" => $limit,
                    "total_items" => $totalCount,
                    "total_pages" => ceil($totalCount / $limit)
                ]
            ]);

        } catch (Exception $e) {
            Response::json(500, "Error fetching transactions", ["error" => $e->getMessage()]);
        }
    }

    public function create()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data) {
                $data = $_POST;
            }

            $type = $data['type'] ?? '';
            $category = trim($data['category'] ?? '');
            $title = trim($data['title'] ?? '');
            $amount = (float)($data['amount'] ?? 0);
            $description = trim($data['description'] ?? '');
            $transactionDate = $data['transaction_date'] ?? date('Y-m-d');
            $referenceType = $data['reference_type'] ?? 'manual';
            $referenceId = isset($data['reference_id']) ? (int)$data['reference_id'] : null;

            if (!in_array($type, ['income', 'expense'])) {
                Response::json(400, "Invalid or missing transaction type");
                return;
            }

            if (empty($title)) {
                Response::json(400, "Transaction title is required");
                return;
            }

            if (empty($category)) {
                Response::json(400, "Category is required");
                return;
            }

            if ($amount <= 0) {
                Response::json(400, "Amount must be greater than zero");
                return;
            }

            $userId = $this->getUserId();

            $sql = "INSERT INTO financial_transactions 
                    (type, category, title, amount, description, transaction_date, reference_type, reference_id, created_by)
                    VALUES (:type, :category, :title, :amount, :description, :transaction_date, :reference_type, :reference_id, :created_by)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':type' => $type,
                ':category' => $category,
                ':title' => $title,
                ':amount' => $amount,
                ':description' => $description,
                ':transaction_date' => $transactionDate,
                ':reference_type' => $referenceType,
                ':reference_id' => $referenceId,
                ':created_by' => $userId
            ]);

            $newId = $this->db->lastInsertId();

            Response::json(201, "Transaction created successfully", ["id" => $newId]);

        } catch (Exception $e) {
            Response::json(500, "Error creating transaction", ["error" => $e->getMessage()]);
        }
    }

    public function delete()
    {
        try {
            $data = json_decode(file_get_contents("php://input"), true);
            $id = $data['id'] ?? $_GET['id'] ?? null;

            if (!$id) {
                Response::json(400, "Transaction ID is required");
                return;
            }

            $stmt = $this->db->prepare("DELETE FROM financial_transactions WHERE transaction_id = :id");
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() > 0) {
                Response::json(200, "Transaction deleted successfully");
            } else {
                Response::json(404, "Transaction not found");
            }

        } catch (Exception $e) {
            Response::json(500, "Error deleting transaction", ["error" => $e->getMessage()]);
        }
    }

    public function sync()
    {
        try {
            $syncedIncomeCount = 0;
            $syncedExpenseCount = 0;
            $userId = $this->getUserId();

            // 1. Sync Paid Orders -> Income
            $orderSql = "SELECT order_id, net_total, order_date 
                         FROM orders 
                         WHERE status != 'cancelled' AND status != 5";
            $stmtOrders = $this->db->query($orderSql);
            $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

            $stmtCheck = $this->db->prepare("SELECT COUNT(*) FROM financial_transactions WHERE reference_type = :ref_type AND reference_id = :ref_id");
            $stmtInsert = $this->db->prepare("INSERT INTO financial_transactions 
                (type, category, title, amount, description, transaction_date, reference_type, reference_id, created_by)
                VALUES (:type, :category, :title, :amount, :description, :transaction_date, :reference_type, :reference_id, :created_by)");

            foreach ($orders as $ord) {
                $refId = (int)$ord['order_id'];
                $stmtCheck->execute([':ref_type' => 'order', ':ref_id' => $refId]);
                if ($stmtCheck->fetchColumn() == 0) {
                    $orderDate = !empty($ord['order_date']) ? date('Y-m-d', strtotime($ord['order_date'])) : date('Y-m-d');
                    $stmtInsert->execute([
                        ':type' => 'income',
                        ':category' => 'สินค้าขายได้',
                        ':title' => "ยอดขายสินค้า (Order #{$refId})",
                        ':amount' => (float)$ord['net_total'],
                        ':description' => "รายรับจากการขายสินค้าในระบบ คำสั่งซื้อ #{$refId}",
                        ':transaction_date' => $orderDate,
                        ':reference_type' => 'order',
                        ':reference_id' => $refId,
                        ':created_by' => $userId
                    ]);
                    $syncedIncomeCount++;
                }
            }

            // 2. Sync Restock orders -> Expense
            try {
                $restockSql = "SELECT restock_id, total_cost, order_date FROM restock_orders";
                $stmtRestocks = $this->db->query($restockSql);
                $restocks = $stmtRestocks->fetchAll(PDO::FETCH_ASSOC);

                foreach ($restocks as $rst) {
                    $refId = (int)$rst['restock_id'];
                    $stmtCheck->execute([':ref_type' => 'restock', ':ref_id' => $refId]);
                    if ($stmtCheck->fetchColumn() == 0) {
                        $restockDate = !empty($rst['order_date']) ? date('Y-m-d', strtotime($rst['order_date'])) : date('Y-m-d');
                        $stmtInsert->execute([
                            ':type' => 'expense',
                            ':category' => 'สินค้าสั่งซื้อ',
                            ':title' => "สั่งซื้อสินค้าเข้าคลัง (Restock #{$refId})",
                            ':amount' => (float)$rst['total_cost'],
                            ':description' => "รายจ่ายจากการสั่งซื้อสินค้าพึ่งสั่งซื้อเข้าคลัง #{$refId}",
                            ':transaction_date' => $restockDate,
                            ':reference_type' => 'restock',
                            ':reference_id' => $refId,
                            ':created_by' => $userId
                        ]);
                        $syncedExpenseCount++;
                    }
                }
            } catch (Exception $ex) {
                // Table restock_orders might not exist or be empty
            }

            Response::json(200, "Synced system transactions successfully", [
                "synced_income" => $syncedIncomeCount,
                "synced_expense" => $syncedExpenseCount
            ]);

        } catch (Exception $e) {
            Response::json(500, "Error syncing transactions", ["error" => $e->getMessage()]);
        }
    }
}
?>
