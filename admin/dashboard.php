<?php
// admin/dashboard.php - FIXED
require_once '../includes/functions.php';
requireAdmin();

$pdo = getDB();

// Auto-expire online tickets past their week window
autoExpireOnlineTickets();

// Auto-expire past screenings (use full datetime comparison)
$pdo->prepare("UPDATE screenings SET status = 'expired' WHERE CONCAT(show_date, ' ', show_time) < NOW() AND status != 'expired'")->execute();

// Auto-expire past online schedules
$pdo->prepare("UPDATE online_schedule SET status = 'expired' WHERE show_date < CURDATE() AND status = 'scheduled'")->execute();

// ── STATS ────────────────────────────────────────────────────────────────────
$stats = [
    'total_users'          => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'total_admins'         => $pdo->query("SELECT COUNT(*) FROM users WHERE account_type = 'admin'")->fetchColumn(),
    'total_staff'          => $pdo->query("SELECT COUNT(*) FROM users WHERE account_type = 'staff'")->fetchColumn(),
    'total_customers'      => $pdo->query("SELECT COUNT(*) FROM users WHERE account_type IN ('adult','teen','kid')")->fetchColumn(),
    'total_movies'         => $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn(),
    'total_cinemas'        => $pdo->query("SELECT COUNT(*) FROM cinemas")->fetchColumn(),
    'total_screenings'     => $pdo->query("SELECT COUNT(*) FROM screenings WHERE show_date >= CURDATE()")->fetchColumn(),
    'total_online'         => $pdo->query("SELECT COUNT(*) FROM online_schedule WHERE show_date >= CURDATE() AND status='scheduled'")->fetchColumn(),
    'total_tickets'        => $pdo->query("SELECT COUNT(*) FROM tickets")->fetchColumn(),
    'cinema_tickets'       => $pdo->query("SELECT COUNT(*) FROM tickets WHERE ticket_type='cinema'")->fetchColumn(),
    'online_tickets'       => $pdo->query("SELECT COUNT(*) FROM tickets WHERE ticket_type='online'")->fetchColumn(),
    'paid_tickets'         => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='paid'")->fetchColumn(),
    'pending_tickets'      => $pdo->query("SELECT COUNT(*) FROM tickets WHERE status='pending'")->fetchColumn(),
    'total_revenue'        => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM tickets WHERE status='paid'")->fetchColumn(),
    'cinema_revenue'       => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM tickets WHERE status='paid' AND ticket_type='cinema'")->fetchColumn(),
    'online_revenue'       => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM tickets WHERE status='paid' AND ticket_type='online'")->fetchColumn(),
    'today_revenue'        => $pdo->query("SELECT COALESCE(SUM(total_price),0) FROM tickets WHERE DATE(purchase_date)=CURDATE() AND status='paid'")->fetchColumn(),
    'pending_payments'     => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status='pending'")->fetchColumn(),
    'completed_payments'   => $pdo->query("SELECT COUNT(*) FROM payments WHERE payment_status='completed'")->fetchColumn(),
];

// ── RECENT TICKETS (FIXED: handles both cinema and online, no crash) ─────────
$recent_tickets = $pdo->query("
    SELECT t.*,
           u.username, u.first_name, u.last_name,
           CASE
               WHEN t.ticket_type = 'cinema' THEN (
                   SELECT m.title FROM screenings s JOIN movies m ON s.movie_id = m.id
                   WHERE s.id = t.screening_id
               )
               WHEN t.ticket_type = 'online' THEN (
                   SELECT m.title FROM online_schedule os JOIN movies m ON os.movie_id = m.id
                   WHERE os.id = t.online_schedule_id
               )
           END AS title
    FROM tickets t
    JOIN users u ON t.user_id = u.id
    ORDER BY t.purchase_date DESC
    LIMIT 10
")->fetchAll();

// ── RECENT PAYMENTS ───────────────────────────────────────────────────────────
$recent_payments = $pdo->query("
    SELECT p.*, u.username, u.first_name, u.last_name
    FROM payments p
    JOIN users u ON p.user_id = u.id
    ORDER BY p.payment_date DESC
    LIMIT 10
")->fetchAll();

// ── RECENT REGISTRATIONS ──────────────────────────────────────────────────────
$recent_users = $pdo->query("
    SELECT * FROM users ORDER BY created_at DESC LIMIT 10
")->fetchAll();

// ── UPCOMING ONLINE SCHEDULES ─────────────────────────────────────────────────
$upcoming_online = $pdo->query("
    SELECT os.*, m.title,
           (SELECT COUNT(*) FROM tickets WHERE online_schedule_id = os.id AND status = 'paid') AS current_viewers
    FROM online_schedule os
    JOIN movies m ON os.movie_id = m.id
    WHERE os.show_date >= CURDATE() AND os.status = 'scheduled'
    ORDER BY os.show_date, os.show_time
    LIMIT 5
")->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard — CinemaTicket Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;800;900&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root{
    --bg:#0a0a0a;--bg2:#111;--card:#161616;--border:rgba(229,9,20,.15);
    --red:#e50914;--red2:#b2070f;--text:#fff;--muted:#888;
    --radius:14px;--transition:.25s ease;
}
*{margin:0;padding:0;box-sizing:border-box}
body{background:var(--bg);color:var(--text);font-family:'Inter',sans-serif;min-height:100vh}

/* NAV */
.nav{background:rgba(10,10,10,.97);border-bottom:1px solid var(--border);padding:.75rem 0;position:sticky;top:0;z-index:999;backdrop-filter:blur(12px)}
.nav-inner{max-width:1600px;margin:0 auto;padding:0 24px;display:flex;justify-content:space-between;align-items:center;gap:16px}
.logo{color:var(--red);font-family:'Montserrat',sans-serif;font-weight:900;font-size:1.3rem;text-decoration:none;letter-spacing:1px}
.logo::before{content:"🎬 "}
.nav-links{display:flex;gap:2px;flex-wrap:wrap}
.nav-links a{color:var(--muted);text-decoration:none;padding:6px 11px;border-radius:8px;font-size:.75rem;font-weight:500;text-transform:uppercase;letter-spacing:.5px;transition:var(--transition)}
.nav-links a:hover,.nav-links a.active{color:var(--red);background:rgba(229,9,20,.08)}

/* LAYOUT */
.page{max-width:1600px;margin:0 auto;padding:28px 24px}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;flex-wrap:wrap;gap:12px}
.page-title{font-family:'Montserrat',sans-serif;font-weight:800;font-size:2rem;background:linear-gradient(135deg,#fff 0%,var(--red) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;text-transform:uppercase;letter-spacing:2px}

/* ALERT */
.alert{padding:14px 20px;border-radius:var(--radius);margin-bottom:20px;font-size:.9rem;border-left:3px solid var(--red);background:rgba(229,9,20,.07)}

/* STATS GRID */
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin-bottom:32px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:22px 20px;position:relative;overflow:hidden;transition:var(--transition)}
.stat-card:hover{border-color:rgba(229,9,20,.35);transform:translateY(-2px)}
.stat-card::after{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--red),transparent);transform:translateX(-100%);animation:shimmer 3s infinite}
@keyframes shimmer{to{transform:translateX(100%)}}
.stat-value{font-family:'Montserrat',sans-serif;font-weight:800;font-size:2.2rem;color:#fff;line-height:1.1}
.stat-label{font-size:.72rem;color:var(--muted);text-transform:uppercase;letter-spacing:1.5px;margin-top:6px}
.stat-sub{font-size:.75rem;color:var(--muted);margin-top:8px;padding-top:8px;border-top:1px solid var(--border)}
.stat-link{margin-top:10px}
.stat-link a{color:var(--red);font-size:.72rem;border:1px solid var(--red);padding:3px 10px;border-radius:20px;text-decoration:none;transition:var(--transition)}
.stat-link a:hover{background:var(--red);color:#fff}

/* QUICK ACTIONS */
.section-title{font-family:'Montserrat',sans-serif;font-size:1.2rem;font-weight:700;color:#fff;margin:32px 0 16px;position:relative;padding-bottom:10px}
.section-title::after{content:'';position:absolute;bottom:0;left:0;width:48px;height:2px;background:var(--red);border-radius:2px}
.actions-row{display:flex;gap:10px;flex-wrap:wrap}
.action-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;background:rgba(229,9,20,.08);border:1px solid rgba(229,9,20,.25);border-radius:40px;color:#fff;text-decoration:none;font-size:.82rem;font-weight:500;transition:var(--transition)}
.action-btn:hover{background:var(--red);border-color:var(--red)}

/* ONLINE SCHEDULE CARDS */
.schedule-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:16px;margin-bottom:32px}
.schedule-card{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);padding:18px;transition:var(--transition)}
.schedule-card:hover{border-color:rgba(229,9,20,.35);transform:translateY(-2px)}
.schedule-title{color:var(--red);font-weight:700;font-size:1.05rem;margin-bottom:12px}
.schedule-detail{color:var(--muted);font-size:.83rem;margin-bottom:6px;display:flex;gap:8px;align-items:center}
.viewers-bar{height:5px;background:rgba(255,255,255,.08);border-radius:3px;margin:10px 0}
.viewers-fill{height:100%;background:var(--red);border-radius:3px}
.schedule-price{color:var(--red);font-weight:700;font-size:1.15rem;margin:10px 0}
.btn-sm{display:inline-block;padding:5px 14px;border:1px solid rgba(229,9,20,.4);border-radius:20px;color:var(--red);font-size:.72rem;text-decoration:none;transition:var(--transition)}
.btn-sm:hover{background:var(--red);color:#fff;border-color:var(--red)}

/* TABLE */
.table-wrap{background:var(--card);border:1px solid var(--border);border-radius:var(--radius);overflow:hidden;margin-bottom:32px}
.data-table{width:100%;border-collapse:collapse;font-size:.85rem}
.data-table th{background:rgba(229,9,20,.1);color:var(--red);padding:14px 14px;text-align:left;font-size:.72rem;font-weight:600;text-transform:uppercase;letter-spacing:1px}
.data-table td{padding:12px 14px;border-bottom:1px solid var(--border);color:#ccc;vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}
.data-table tr:hover td{background:rgba(229,9,20,.04)}
.code-style{color:var(--red);font-family:monospace;font-size:.8rem}
.badge{display:inline-block;padding:3px 10px;border-radius:20px;font-size:.68rem;font-weight:600;text-transform:uppercase;letter-spacing:.5px}
.badge-paid,.badge-completed{background:rgba(68,255,68,.1);border:1px solid #44ff44;color:#44ff44}
.badge-pending{background:rgba(229,9,20,.1);border:1px solid var(--red);color:var(--red)}
.badge-used{background:rgba(136,136,136,.1);border:1px solid #888;color:#888}
.badge-cinema{background:rgba(229,9,20,.08);border:1px solid rgba(229,9,20,.3);color:var(--red)}
.badge-online{background:rgba(68,136,255,.08);border:1px solid rgba(68,136,255,.3);color:#6af}
.amount{color:var(--red);font-weight:700}

/* RESPONSIVE */
@media(max-width:768px){
    .stats-grid{grid-template-columns:1fr 1fr}
    .page-title{font-size:1.5rem}
    .table-wrap{overflow-x:auto}
    .data-table{min-width:700px}
}
</style>
</head>
<body>

<nav class="nav">
  <div class="nav-inner">
    <a href="../index.php" class="logo">CINEMA TICKET</a>
    <div class="nav-links">
      <a href="dashboard.php" class="active">Dashboard</a>
      <a href="movies.php">Movies</a>
      <a href="cinemas.php">Cinemas</a>
      <a href="screenings.php">Screenings</a>
      <a href="online_schedule.php">Online</a>
      <a href="users.php">Users</a>
      <a href="tickets.php">Tickets</a>
      <a href="payments.php">Payments</a>
      <a href="reports.php">Reports</a>
      <a href="profile.php">Profile</a>
      <a href="../auth/logout.php">Logout</a>
    </div>
  </div>
</nav>

<main class="page">
  <?php if($flash):?>
  <div class="alert"><?=htmlspecialchars($flash['message'])?></div>
  <?php endif;?>

  <div class="page-header">
    <h1 class="page-title">Dashboard</h1>
    <span style="color:var(--muted);font-size:.82rem"><?=date('l, F j, Y')?></span>
  </div>

  <!-- STATS -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-value"><?=$stats['total_users']?></div>
      <div class="stat-label">Total Users</div>
      <div class="stat-sub">Admins: <?=$stats['total_admins']?> &nbsp;·&nbsp; Staff: <?=$stats['total_staff']?> &nbsp;·&nbsp; Customers: <?=$stats['total_customers']?></div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?=$stats['total_movies']?></div>
      <div class="stat-label">Movies</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?=$stats['total_cinemas']?></div>
      <div class="stat-label">Cinemas</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?=$stats['total_screenings']?></div>
      <div class="stat-label">Upcoming Screenings</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?=$stats['total_online']?></div>
      <div class="stat-label">Online Slots</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?=$stats['total_tickets']?></div>
      <div class="stat-label">Tickets Sold</div>
      <div class="stat-sub">Cinema: <?=$stats['cinema_tickets']?> &nbsp;·&nbsp; Online: <?=$stats['online_tickets']?></div>
    </div>
    <div class="stat-card">
      <div class="stat-value" style="color:var(--red)">₱<?=number_format($stats['total_revenue'],2)?></div>
      <div class="stat-label">Total Revenue</div>
      <div class="stat-sub">Cinema: ₱<?=number_format($stats['cinema_revenue'],2)?> &nbsp;·&nbsp; Online: ₱<?=number_format($stats['online_revenue'],2)?></div>
    </div>
    <div class="stat-card">
      <div class="stat-value" style="color:var(--red)">₱<?=number_format($stats['today_revenue'],2)?></div>
      <div class="stat-label">Today's Revenue</div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?=$stats['pending_payments']?></div>
      <div class="stat-label">Pending Payments</div>
      <div class="stat-link"><a href="payments.php?status=pending">View →</a></div>
    </div>
    <div class="stat-card">
      <div class="stat-value"><?=$stats['completed_payments']?></div>
      <div class="stat-label">Completed Payments</div>
      <div class="stat-link"><a href="payments.php?status=completed">View →</a></div>
    </div>
  </div>

  <!-- QUICK ACTIONS -->
  <h2 class="section-title">Quick Actions</h2>
  <div class="actions-row" style="margin-bottom:32px">
    <a href="movies.php?action=add"         class="action-btn">➕ Add Movie</a>
    <a href="cinemas.php?action=add"        class="action-btn">➕ Add Cinema</a>
    <a href="screenings.php?action=add"     class="action-btn">➕ Add Screening</a>
    <a href="online_schedule.php?action=add" class="action-btn">🌐 Add Online Slot</a>
    <a href="users.php?action=add"          class="action-btn">👤 Add User</a>
    <a href="payments.php?status=pending"   class="action-btn">💰 Process Payments</a>
    <a href="reports.php"                   class="action-btn">📊 Reports</a>
  </div>

  <!-- UPCOMING ONLINE -->
  <?php if(!empty($upcoming_online)):?>
  <h2 class="section-title">Upcoming Online Screenings</h2>
  <div class="schedule-grid">
    <?php foreach($upcoming_online as $o):
      $pct = $o['max_viewers'] > 0 ? ($o['current_viewers']/$o['max_viewers'])*100 : 0;
    ?>
    <div class="schedule-card">
      <div class="schedule-title"><?=htmlspecialchars($o['title'])?></div>
      <div class="schedule-detail">📅 <?=date('M d, Y',strtotime($o['show_date']))?></div>
      <div class="schedule-detail">⏰ <?=date('h:i A',strtotime($o['show_time']))?></div>
      <div class="schedule-detail">👥 <?=$o['current_viewers']?>/<?=$o['max_viewers']?> viewers</div>
      <div class="viewers-bar"><div class="viewers-fill" style="width:<?=$pct?>%"></div></div>
      <div class="schedule-price">₱<?=number_format($o['price'],2)?></div>
      <a href="online_schedule.php?edit=<?=$o['id']?>" class="btn-sm">Manage</a>
    </div>
    <?php endforeach;?>
  </div>
  <?php endif;?>

  <!-- RECENT PAYMENTS -->
  <h2 class="section-title">Recent Payments</h2>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Transaction ID</th><th>Customer</th><th>Amount</th>
        <th>Method</th><th>Status</th><th>Date</th><th>Action</th>
      </tr></thead>
      <tbody>
        <?php foreach($recent_payments as $p):?>
        <tr>
          <td><span class="code-style"><?=htmlspecialchars($p['transaction_id'] ?? '—')?></span></td>
          <td><?=htmlspecialchars($p['first_name'].' '.$p['last_name'])?></td>
          <td><span class="amount">₱<?=number_format($p['amount'],2)?></span></td>
          <td><?=ucfirst(str_replace('_',' ',$p['payment_method']))?></td>
          <td><span class="badge badge-<?=$p['payment_status']?>"><?=ucfirst($p['payment_status'])?></span></td>
          <td><?=date('M d, Y H:i',strtotime($p['payment_date']))?></td>
          <td><a href="payments.php?view=<?=$p['id']?>" class="btn-sm">View</a></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>

  <!-- RECENT TICKETS -->
  <h2 class="section-title">Recent Ticket Purchases</h2>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>Ticket Code</th><th>Customer</th><th>Movie</th>
        <th>Type</th><th>Seats</th><th>Amount</th><th>Status</th><th>Date</th>
      </tr></thead>
      <tbody>
        <?php foreach($recent_tickets as $t):?>
        <tr>
          <td><span class="code-style"><?=htmlspecialchars($t['ticket_code'])?></span></td>
          <td><?=htmlspecialchars($t['first_name'].' '.$t['last_name'])?></td>
          <td><?=htmlspecialchars($t['title'] ?? 'N/A')?></td>
          <td><span class="badge badge-<?=$t['ticket_type']?>"><?=ucfirst($t['ticket_type'])?></span></td>
          <td><?=$t['ticket_type']==='online' ? '<span style="color:var(--muted)">Online</span>' : htmlspecialchars($t['seat_numbers'] ?? '—')?></td>
          <td><span class="amount">₱<?=number_format($t['total_price'],2)?></span></td>
          <td><span class="badge badge-<?=$t['status']?>"><?=strtoupper($t['status'])?></span></td>
          <td><?=date('M d, Y H:i',strtotime($t['purchase_date']))?></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>

  <!-- RECENT REGISTRATIONS -->
  <h2 class="section-title">Recent Registrations</h2>
  <div class="table-wrap">
    <table class="data-table">
      <thead><tr>
        <th>ID</th><th>Username</th><th>Name</th><th>Email</th><th>Type</th><th>Joined</th>
      </tr></thead>
      <tbody>
        <?php foreach($recent_users as $u):?>
        <tr>
          <td><span class="code-style">#<?=$u['id']?></span></td>
          <td style="color:var(--red)"><?=htmlspecialchars($u['username'])?></td>
          <td><?=htmlspecialchars($u['first_name'].' '.$u['last_name'])?></td>
          <td><?=htmlspecialchars($u['email'])?></td>
          <td><span class="badge badge-cinema"><?=strtoupper($u['account_type'])?></span></td>
          <td><?=date('M d, Y',strtotime($u['created_at']))?></td>
        </tr>
        <?php endforeach;?>
      </tbody>
    </table>
  </div>

</main>
</body>
</html>