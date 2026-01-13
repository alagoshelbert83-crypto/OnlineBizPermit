<?php
$current_page = 'feedback';
require_once './staff_header.php'; // Handles session, DB, and auth

$feedbacks = [];
// Try to select with rating column; if it doesn't exist, fall back to query without it
try {
  $sql = "SELECT f.id, u.name, u.email, f.message, f.created_at, f.rating 
      FROM feedback f 
      JOIN users u ON f.user_id = u.id 
      ORDER BY f.created_at DESC";
  $stmt = $conn->prepare($sql);
  $stmt->execute();
  $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  error_log('feedback.php query with rating failed: ' . $e->getMessage());
  try {
    $sql = "SELECT f.id, u.name, u.email, f.message, f.created_at 
        FROM feedback f 
        JOIN users u ON f.user_id = u.id 
        ORDER BY f.created_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Ensure rating key exists with null for compatibility
    foreach ($feedbacks as &$fb) {
      if (!array_key_exists('rating', $fb)) { $fb['rating'] = null; }
    }
    unset($fb);
  } catch (PDOException $e2) {
    error_log('feedback.php fallback query failed: ' . $e2->getMessage());
    $feedbacks = [];
  }
}

require_once './staff_sidebar.php';
?>
  <style>
    /* Main Content */
    .main { flex: 1; padding: 30px; overflow-y: auto; }
    .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
    .main-header h1 { font-size: 28px; font-weight: 700; color: var(--secondary-color); }

    /* Feedback Grid */
    .feedback-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 20px; }
    .feedback-card {
      background: #fff;
      border-radius: 20px;
      box-shadow: 0 6px 32px 0 rgba(60,72,88,0.13), 0 2px 8px 0 rgba(60,72,88,0.10);
      padding: 36px 30px 30px 30px;
      transition: box-shadow 0.18s, transform 0.18s;
      border: 1.5px solid #e8eaf0;
      position: relative;
      min-height: 260px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .feedback-card:hover {
      box-shadow: 0 12px 36px 0 rgba(60,72,88,0.20), 0 4px 16px 0 rgba(60,72,88,0.13);
      transform: translateY(-3px) scale(1.012);
      border-color: #c7d0e0;
    }
    .feedback-header {
      display: flex;
      align-items: center;
      gap: 20px;
      margin-bottom: 20px;
    }
    .user-avatar {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      background: linear-gradient(135deg, #4f8cff 0%, #38cfa6 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      font-weight: 700;
      box-shadow: 0 2px 8px 0 rgba(79,140,255,0.13);
      letter-spacing: 1.5px;
      text-transform: uppercase;
      border: 2.5px solid #fff;
    }
    .user-info h3 {
      font-size: 1.15rem;
      font-weight: 700;
      color: #1a2a3a;
      margin: 0 0 2px 0;
      letter-spacing: 0.2px;
    }
    .user-info p {
      color: #6a7a99;
      font-size: 0.97rem;
      margin: 0;
      font-weight: 500;
    }
    .feedback-body .message {
      line-height: 1.85;
      color: #2d3a4a;
      font-size: 1.08rem;
      margin-bottom: 10px;
      min-height: 48px;
      word-break: break-word;
    }
    .feedback-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 24px;
      padding-top: 14px;
      border-top: 1.5px solid #e8eaf0;
    }
    .rating .stars {
      color: #ffc107;
      font-size: 1.18rem;
      letter-spacing: 1.5px;
    }
    .rating .stars .far {
      color: #e0e7ef;
    }
    .feedback-footer .time {
      font-size: 0.97rem;
      color: #7a8ca3;
      font-weight: 600;
      letter-spacing: 0.6px;
    }
    @media (max-width: 600px) {
      .feedback-card { padding: 22px 10px 18px 10px; }
      .user-avatar { width: 44px; height: 44px; font-size: 1.1rem; }
      .feedback-header { gap: 10px; }
    }
  </style>

    <!-- Main Content -->
    <div class="main">
      <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-comment-dots" style="color: var(--accent-color);"></i>
                    User Feedback
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Review feedback and suggestions from applicants
                </p>
            </div>
        </div>
      </header>
      <div class="feedback-grid">
        <?php if (empty($feedbacks)): ?>
          <p>No feedback received yet.</p>
        <?php else: ?>
          <?php foreach ($feedbacks as $feedback): ?>
            <div class="feedback-card">
              <div class="feedback-header">
                <div class="user-avatar"><span>
                  <?php
                    $names = preg_split('/\s+/', trim($feedback['name']));
                    $initials = '';
                    foreach ($names as $n) {
                      if ($n !== '' && strlen($initials) < 2) $initials .= strtoupper($n[0]);
                    }
                    echo htmlspecialchars($initials);
                  ?>
                </span></div>
                <div class="user-info">
                  <h3><?= htmlspecialchars($feedback['name']) ?></h3>
                  <p><?= htmlspecialchars($feedback['email']) ?></p>
                </div>
              </div>
              <div class="feedback-body">
                <p class="message"><?= nl2br(htmlspecialchars($feedback['message'])) ?></p>
              </div>
              <div class="feedback-footer">
                <div class="rating">
                  <span class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?><i class="<?= $i <= ($feedback['rating'] ?? 0) ? 'fas' : 'far' ?> fa-star"></i><?php endfor; ?>
                  </span>
                </div>
                <div class="time"><?= date('M d, Y', strtotime($feedback['created_at'])) ?></div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

<?php require_once './staff_footer.php'; ?>