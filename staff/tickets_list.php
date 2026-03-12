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
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --black: #0a0a0a;
            --deep-gray: #1a1a1a;
            --medium-gray: #2a2a2a;
            --light-gray: #333333;
            --red: #e50914;
            --red-dark: #b2070f;
            --red-glow: 0 0 20px rgba(229, 9, 20, 0.3);
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --glass-bg: rgba(26, 26, 26, 0.7);
            --glass-border: rgba(255, 255, 255, 0.05);
            --card-gradient: linear-gradient(135deg, rgba(26, 26, 26, 0.9) 0%, rgba(20, 20, 20, 0.95) 100%);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--black);
            color: var(--text-primary);
            font-family: 'Inter', sans-serif;
            font-weight: 400;
            line-height: 1.6;
            min-height: 100vh;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 50%, rgba(229, 9, 20, 0.03) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(229, 9, 20, 0.03) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }
        
        /* Navigation */
        .navbar {
            background: rgba(10, 10, 10, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(229, 9, 20, 0.2);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }
        
        .logo {
            color: var(--red);
            font-size: 1.8rem;
            font-weight: 800;
            font-family: 'Montserrat', sans-serif;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 2px;
            position: relative;
            transition: all 0.3s;
        }
        
        .logo:hover {
            text-shadow: var(--red-glow);
        }
        
        .logo::before {
            content: "🎬";
            margin-right: 10px;
            font-size: 1.5rem;
            filter: drop-shadow(0 0 5px var(--red));
        }
        
        .nav-links {
            display: flex;
            gap: 25px;
            align-items: center;
        }
        
        .nav-links a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        
        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 2px;
            background: var(--red);
            transition: width 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--red);
        }
        
        .nav-links a:hover::after {
            width: 60%;
        }
        
        .nav-links a.active {
            color: var(--red);
        }
        
        .nav-links a.active::after {
            width: 60%;
        }
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }
        
        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, var(--red) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-print {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-print:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .btn-export {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn-export:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        .btn-back {
            background: transparent;
            border: 1px solid rgba(229, 9, 20, 0.3);
            color: var(--text-primary);
            padding: 12px 25px;
            border-radius: 40px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: inline-block;
        }
        
        .btn-back:hover {
            border-color: var(--red);
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
        }
        
        /* Screening Info */
        .screening-info {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 24px;
            padding: 25px;
            margin-bottom: 30px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .screening-info::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            animation: slideBorder 3s infinite;
        }
        
        @keyframes slideBorder {
            0% { transform: translateX(-100%); }
            50% { transform: translateX(100%); }
            100% { transform: translateX(100%); }
        }
        
        .info-item {
            text-align: center;
        }
        
        .info-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-value {
            color: var(--red);
            font-size: 1.2rem;
            font-weight: 700;
            font-family: 'Montserrat', sans-serif;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            transform: translateX(-100%);
            animation: slideBorder 3s infinite;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(229, 9, 20, 0.3);
            box-shadow: 0 20px 40px rgba(229, 9, 20, 0.15);
        }
        
        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--red);
            font-family: 'Montserrat', sans-serif;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Tickets Table */
        .table-container {
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.1);
            border-radius: 24px;
            overflow: hidden;
            margin-top: 30px;
            padding: 5px;
        }
        
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .tickets-table th {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-family: 'Montserrat', sans-serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.8rem;
        }
        
        .tickets-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(229, 9, 20, 0.1);
            color: var(--text-secondary);
        }
        
        .tickets-table tr:hover {
            background: rgba(229, 9, 20, 0.05);
        }
        
        .tickets-table tr:last-child td {
            border-bottom: none;
        }
        
        .tickets-table td code {
            color: var(--red);
            background: rgba(229, 9, 20, 0.1);
            padding: 4px 10px;
            border-radius: 30px;
            font-family: 'Monaco', monospace;
            font-size: 0.85rem;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-paid {
            background: rgba(68, 255, 68, 0.15);
            color: #44ff44;
            border: 1px solid #44ff44;
        }
        
        .status-used {
            background: rgba(229, 9, 20, 0.15);
            color: var(--red);
            border: 1px solid var(--red);
        }
        
        .amount-highlight {
            color: var(--red);
            font-weight: 600;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: var(--card-gradient);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(229, 9, 20, 0.2);
            border-radius: 32px;
            position: relative;
            overflow: hidden;
        }
        
        .empty-state::before {
            content: '🎟️';
            position: absolute;
            bottom: 20px;
            right: 20px;
            font-size: 6rem;
            opacity: 0.03;
            pointer-events: none;
        }
        
        .empty-state p {
            color: var(--text-secondary);
            font-size: 1.2rem;
        }
        
        /* Cinema Strip Divider */
        .cinema-strip {
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--red), transparent);
            margin: 20px 0 30px;
            opacity: 0.3;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .table-container {
                overflow-x: auto;
            }
            
            .tickets-table {
                min-width: 1000px;
            }
        }
        
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .btn-print, .btn-export, .btn-back {
                flex: 1;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="../index.php" class="logo">CINEMA TICKET STAFF</a>
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
            <div class="header-actions">
                <button class="btn-print" onclick="window.print()">🖨️ Print List</button>
                <button class="btn-export" onclick="exportToCSV()">📥 Export CSV</button>
                <a href="screenings.php" class="btn-back">← Back</a>
            </div>
        </div>
        
        <!-- Cinema Strip Divider -->
        <div class="cinema-strip"></div>
        
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
                <div class="stat-label">Total Sold</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $paid_tickets; ?></div>
                <div class="stat-label">Not Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $used_tickets; ?></div>
                <div class="stat-label">Used</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">$<?php echo number_format($total_revenue, 2); ?></div>
                <div class="stat-label">Revenue</div>
            </div>
        </div>
        
        <!-- Tickets Table -->
        <?php if (empty($tickets)): ?>
            <div class="empty-state">
                <p>No tickets sold for this screening.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <table class="tickets-table" id="ticketsTable">
                    <thead>
                        <tr>
                            <th>Ticket Code</th>
                            <th>Customer</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Seats</th>
                            <th>Qty</th>
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
                                <td class="amount-highlight">$<?php echo number_format($ticket['total_price'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $ticket['status']; ?>">
                                        <?php echo strtoupper($ticket['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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
                    let text = td.textContent.replace(/,/g, ';');
                    rowData.push('"' + text.trim() + '"');
                });
                if (rowData.length > 0) {
                    csv.push(rowData.join(','));
                }
            });
            
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