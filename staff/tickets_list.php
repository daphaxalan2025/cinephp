<?php
// staff/tickets_list.php
require_once '../includes/functions.php';
requireStaff();

$pdo = getDB();
$user = getCurrentUser();

$screening_id = $_GET['screening_id'] ?? 0;

// Get screening details
$stmt = $pdo->prepare("
    SELECT s.*, m.title, c.name as cinema_name
    FROM screenings s
    JOIN movies m ON s.movie_id = m.id
    JOIN cinemas c ON s.cinema_id = c.id
    WHERE s.id = ?
");
$stmt->execute([$screening_id]);
$screening = $stmt->fetch();

if (!$screening) {
    setFlash('Screening not found', 'error');
    header('Location: screenings.php');
    exit;
}

// Get tickets for this screening
$stmt = $pdo->prepare("
    SELECT t.*, u.first_name, u.last_name, u.email, u.phone
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    WHERE t.screening_id = ? AND t.status IN ('paid', 'used')
    ORDER BY t.seat_numbers
");
$stmt->execute([$screening_id]);
$tickets = $stmt->fetchAll();

// Calculate statistics
$total_tickets = count($tickets);
$total_revenue = array_sum(array_column($tickets, 'total_price'));
$used_tickets = count(array_filter($tickets, fn($t) => $t['status'] == 'used'));
$paid_tickets = $total_tickets - $used_tickets;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets List - Staff</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        .screening-info {
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        .info-item {
            text-align: center;
        }
        .info-label {
            color: #888;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        .info-value {
            color: #00ffff;
            font-size: 1.2rem;
            font-weight: bold;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #1a1a1a;
            border: 1px solid #00ffff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 2rem;
            color: #00ffff;
            font-weight: bold;
        }
        .stat-label {
            color: #888;
            font-size: 0.9rem;
        }
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
            overflow: hidden;
        }
        .tickets-table th {
            background: #000;
            color: #00ffff;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid #00ffff;
        }
        .tickets-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #333;
            color: #888;
        }
        .tickets-table tr:hover {
            background: #000;
        }
        .tickets-table td code {
            color: #00ffff;
            background: #000;
            padding: 3px 8px;
            border-radius: 4px;
        }
        .status-badge {
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: bold;
        }
        .status-paid {
            background: rgba(68,255,68,0.2);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        .status-used {
            background: rgba(0,255,255,0.2);
            color: #00ffff;
            border: 1px solid #00ffff;
        }
        .print-btn {
            padding: 10px 20px;
            background: #00ffff;
            color: #000;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        .print-btn:hover {
            box-shadow: 0 0 20px #00ffff;
        }
        .export-btn {
            padding: 10px 20px;
            background: transparent;
            color: #00ffff;
            border: 1px solid #00ffff;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-left: 10px;
        }
        .export-btn:hover {
            background: #00ffff;
            color: #000;
        }
        .no-tickets {
            text-align: center;
            padding: 60px;
            background: #1a1a1a;
            border: 2px solid #00ffff;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">🎬 CinemaTicket Staff</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="cinemas.php">Cinemas</a>
                <a href="screenings.php">Screenings</a>
                <a href="verify.php">Verify</a>
                <a href="scan.php">Scan QR</a>
                <a href="sales.php">Sales</a>
                <a href="profile.php">Profile</a>
                <a href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <main class="container">
        <div class="header">
            <h1>Tickets List</h1>
            <div>
                <button class="print-btn" onclick="window.print()">🖨️ Print List</button>
                <button class="export-btn" onclick="exportToCSV()">📥 Export CSV</button>
                <a href="screenings.php" class="btn" style="margin-left:10px;">← Back</a>
            </div>
        </div>
        
        <!-- Screening Info -->
        <div class="screening-info">
            <div class="info-item">
                <div class="info-label">Movie</div>
                <div class="info-value"><?php echo htmlspecialchars($screening['title']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Cinema</div>
                <div class="info-value"><?php echo htmlspecialchars($screening['cinema_name']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Screen</div>
                <div class="info-value"><?php echo $screening['screen_number']; ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Date</div>
                <div class="info-value"><?php echo date('M d, Y', strtotime($screening['show_date'])); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Time</div>
                <div class="info-value"><?php echo date('h:i A', strtotime($screening['show_time'])); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Price</div>
                <div class="info-value">$<?php echo number_format($screening['price'], 2); ?></div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_tickets; ?></div>
                <div class="stat-label">Total Tickets Sold</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $paid_tickets; ?></div>
                <div class="stat-label">Not Yet Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $used_tickets; ?></div>
                <div class="stat-label">Already Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Total Revenue</div>
            </div>
        </div>
        
        <!-- Tickets Table -->
        <?php if (empty($tickets)): ?>
            <div class="no-tickets">
                <p style="color:#888; font-size:1.2rem;">No tickets sold for this screening.</p>
            </div>
        <?php else: ?>
            <table class="tickets-table" id="ticketsTable">
                <thead>
                    <tr>
                        <th>Ticket Code</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Seats</th>
                        <th>Quantity</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                        <tr>
                            <td><code><?php echo $ticket['ticket_code']; ?></code></td>
                            <td><?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['email']); ?></td>
                            <td><?php echo htmlspecialchars($ticket['phone']); ?></td>
                            <td><?php echo $ticket['seat_numbers'] ?: 'N/A'; ?></td>
                            <td><?php echo $ticket['quantity']; ?></td>
                            <td>$<?php echo number_format($ticket['total_price'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                    <?php echo strtoupper($ticket['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    
    <script>
        function exportToCSV() {
            const table = document.getElementById('ticketsTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];
            
            // Get headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent);
            });
            csv.push(headers.join(','));
            
            // Get data
            rows.forEach(row => {
                const rowData = [];
                row.querySelectorAll('td').forEach(td => {
                    // Clean the text (remove HTML)
                    let text = td.textContent.replace(/,/g, ';'); // Replace commas to avoid CSV issues
                    rowData.push('"' + text.trim() + '"');
                });
                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            });
            
            // Download
            const blob = new Blob([csv.join('\n')], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'tickets_screening_<?php echo $screening_id; ?>.csv';
            a.click();
        }
    </script>
    
    <script src="../assets/js/script.js"></script>
</body>
</html>