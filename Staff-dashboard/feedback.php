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
    .feedback-card { background: var(--card-bg-color); border-radius: var(--border-radius); box-shadow: var(--shadow); padding: 25px; }
    .feedback-header { display: flex; align-items: center; gap: 15px; margin-bottom: 15px; }
    .user-avatar { width: 48px; height: 48px; border-radius: 50%; background: var(--primary-color); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 600; }
    .user-info h3 { font-size: 1.1rem; font-weight: 600; }
    .user-info p { color: var(--text-secondary-color); font-size: 0.9rem; }
    .feedback-body .message { line-height: 1.7; color: var(--text-color); }
    .feedback-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--border-color); }
    .rating .stars { color: #ffc107; }
    .rating .stars .far { color: var(--border-color); }
    .feedback-footer .time { font-size: 0.85rem; color: var(--text-secondary-color); }
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
                <div class="user-avatar"><span><?= strtoupper(substr($feedback['name'], 0, 1)) ?></span></div>
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