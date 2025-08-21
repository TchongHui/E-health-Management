<?php
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /views/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$servername = "localhost";
$username = "root";
$password = "";
$database = "foreign_workers";
$conn = new mysqli($servername, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ========== OOP Classes ==========
abstract class PermitBase {
    protected $conn;
    protected $user_id;

    public function __construct($conn, $user_id) {
        $this->conn = $conn;
        $this->user_id = $user_id;
    }

    abstract public function getPermitData();
}

class PermitFetcher extends PermitBase {
    public function getPermitData() {
        $stmt = $this->conn->prepare("SELECT permit_id, status_updated_date, valid_until FROM forms WHERE user_id = ?");
        $stmt->bind_param("s", $this->user_id);
        $stmt->execute();
        return $stmt->get_result();
    }
}

class PrintablePermit extends PermitBase {
    private $permit_id;

    public function __construct($conn, $user_id, $permit_id) {
        parent::__construct($conn, $user_id);
        $this->permit_id = $permit_id;
    }

    public function getPermitData() {
        $sql = "SELECT f.permit_id, f.status_updated_date, f.valid_until, r.full_name, r.medical_id, r.email
                FROM forms f
                JOIN registration r ON f.user_id = r.user_id
                WHERE f.permit_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("s", $this->permit_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }
}

// ========== Logic & Print View ==========
try {
    if (isset($_GET['permit_id']) && !empty($_GET['permit_id'])) {
        $permitObj = new PrintablePermit($conn, $user_id, $_GET['permit_id']);
        $permit = $permitObj->getPermitData();

        if (!$permit) {
            throw new Exception("Permit not found.");
        }

        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Printable Permit</title>
            <style>
                body { font-family: Arial; padding: 30px; }
                h2 { text-align: center; }
                .permit-details { max-width: 600px; margin: auto; }
                button { margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class="permit-details">
                <h2>Permit Details</h2>
                <p><strong>Permit ID:</strong> <?= htmlspecialchars($permit['permit_id']) ?></p>
                <p><strong>Full Name:</strong> <?= htmlspecialchars($permit['full_name']) ?></p>
                <p><strong>Medical ID:</strong> <?= htmlspecialchars($permit['medical_id']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($permit['email']) ?></p>
                <p><strong>Status Updated:</strong> <?= htmlspecialchars($permit['status_updated_date']) ?></p>
                <p><strong>Valid Until:</strong> <?= htmlspecialchars($permit['valid_until']) ?></p>
                <button onclick="window.print()">🖨️ Print</button>
            </div>
        </body>
        </html>
        <?php
        $conn->close();
        exit();
    }

    // Normal user view
    $permitManager = new PermitFetcher($conn, $user_id);
    $permitList = $permitManager->getPermitData();

} catch (Exception $e) {
    echo "<script>alert('" . $e->getMessage() . "'); window.location.href='/pageFW/foreign-worker.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>Permit Status</title>
    <link rel="stylesheet" href="/pageFW/print-approval-status.css">
    <link rel="icon" type="image/png" href="/images/srw.png" sizes="32x32">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
<header class="header">
    <div class="left-section">
        <div class="logo"><img src="/images/srw.png" alt="Logo"></div>
        <div class="title">Print Approval Status</div>
    </div>
    <div class="right-section">
        <div class="profile-wrapper">
            <div class="profile-icon" onclick="toggleProfileDropdown()" title="User Profile">
                <img src="/images/profile-icon.png" alt="Profile" />
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <p>👤 User ID: <span id="username"><?= htmlspecialchars($user_id) ?></span></p>
            </div>
        </div>
    </div>
</header>

<div class="container foreign-worker-container">
    <h1>Check Records and Print Approval Status</h1>

    <table id="permitTable" border="1">
        <thead>
        <tr>
            <th>No</th>
            <th>Permit ID</th>
            <th>Status Updated</th>
            <th>Valid Until</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php
        $counter = 1;
        while ($row = $permitList->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $counter++ . "</td>";
            echo "<td>" . htmlspecialchars($row['permit_id']) . "</td>";
            echo "<td>" . htmlspecialchars($row['status_updated_date']) . "</td>";
            echo "<td>" . htmlspecialchars($row['valid_until']) . "</td>";
            echo "<td>";
            if (!empty($row['permit_id'])) {
                echo "<a href='?permit_id=" . urlencode($row['permit_id']) . "' target='_blank'><button>Print</button></a>";
            } else {
                echo "-";
            }
            echo "</td>";
            echo "</tr>";
        }
        ?>
        </tbody>
    </table>
</div>

<div class="back-to-main">
    <button onclick="window.location.href='/pageFW/foreign-worker.php'">Back to Main Page</button>
</div>

<footer class="footer">
    <p>© 2025 Sarawak E-health Management System. All rights reserved.</p>
</footer>

<script>
    function toggleProfileDropdown() {
        const dropdown = document.getElementById('profileDropdown');
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('click', function (e) {
        const profile = document.querySelector('.profile-icon');
        const dropdown = document.getElementById('profileDropdown');
        if (!profile.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.style.display = 'none';
        }
    });
</script>
</body>
</html>

<?php $conn->close(); ?>
