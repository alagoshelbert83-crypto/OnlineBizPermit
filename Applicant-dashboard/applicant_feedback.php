<?php
// Page-specific variables
$page_title = 'Submit Feedback';
$current_page = 'feedback';

// Include Header
require_once __DIR__ . '/applicant_header.php';

$message = '';

// --- Handle Rating Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $feedback_message = trim($_POST['feedback_message'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $message = '<div class="message error">Please provide a rating between 1 and 5 stars.</div>';
    } else {
        $conn->beginTransaction();
        try {
            // 1. Insert the feedback (rating required, message optional)
            $stmt = $conn->prepare("INSERT INTO feedback (user_id, message, rating) VALUES (?, ?, ?)");
            if (!$stmt->execute([$current_user_id, $feedback_message !== '' ? $feedback_message : null, $rating])) {
                $err = $stmt->errorInfo();
                throw new Exception('Failed to insert rating: ' . ($err[2] ?? 'Unknown'));
            }

            // 2. Create a notification for staff
            $notification_message = "New rating (" . $rating . "★) submitted by " . htmlspecialchars($current_user_name);
            $notification_link = "feedback.php";
            $notify_stmt = $conn->prepare("INSERT INTO notifications (user_id, message, link) VALUES (NULL, ?, ?)");
            if ($notify_stmt) { $notify_stmt->execute([$notification_message, $notification_link]); }

            $conn->commit();
            $message = '<div class="message success">Thank you! Your rating has been submitted successfully.</div>';
        } catch (Exception $e) {
            try { $conn->rollBack(); } catch (Exception $_) {}
            $message = '<div class="message error">An error occurred. Please try again.</div>';
            error_log('Rating submission error: ' . $e->getMessage());
        }
    }
}

// Include Sidebar
require_once __DIR__ . '/applicant_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <header class="header">
        <div class="header-left">
            <div>
                <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-comment-dots" style="color: var(--accent-color);"></i>
                    Submit Feedback
                </h1>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                    Share your thoughts and help us improve our service
                </p>
            </div>
        </div>
    </header>

    <div class="feedback-container">
        <h3>We value your opinion</h3>
        <p>Please let us know if you have any questions, suggestions, or if you've encountered an issue. Your feedback helps us improve our service.</p>
        
        <?php if ($message) echo $message; ?>

        <form action="applicant_feedback.php" method="POST">
            <div class="form-group">
                <label for="rating">Your Rating <span class="required">*</span></label>
                <div class="star-rating" role="radiogroup" aria-label="Rating">
                    <input type="radio" id="star5" name="rating" value="5"><label for="star5">★</label>
                    <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
                    <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
                    <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
                    <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
                </div>
            </div>
            <div class="form-group">
                <label for="feedback_message">Optional comment</label>
                <textarea id="feedback_message" name="feedback_message" rows="4" placeholder="Optional message..."></textarea>
            </div>
            <button type="submit" name="submit_feedback" class="btn">Submit Rating</button>
        </form> <!-- end rating form -->
        <style>
            .star-rating { display:flex; gap:6px; }
            .star-rating input { display:none; }
            .star-rating label { font-size: 1.8rem; cursor:pointer; color:#ddd; user-select:none; }
            .star-rating label.selected, .star-rating label.highlight { color:#f1c40f; }
            /* fallback visual hover */
            .star-rating input:checked ~ label { color:#f1c40f; }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.star-rating').forEach(function(ratingGroup){
                const inputs = Array.from(ratingGroup.querySelectorAll('input[type="radio"]'));
                const labels = Array.from(ratingGroup.querySelectorAll('label'));

                function updateSelected(){
                    const checked = ratingGroup.querySelector('input[type="radio"]:checked');
                    labels.forEach(l => { l.classList.remove('selected'); l.classList.remove('highlight'); });
                    if (checked) {
                        const val = parseInt(checked.value, 10);
                        labels.forEach(function(l){
                            const idx = parseInt(l.htmlFor.replace('star',''),10);
                            if (idx <= val) l.classList.add('selected');
                        });
                    }
                }

                labels.forEach(function(lbl, idx){
                    lbl.addEventListener('click', function(e){
                        const id = lbl.htmlFor;
                        const input = document.getElementById(id);
                        if (input) {
                            input.checked = true;
                            updateSelected();
                        }
                    });
                    lbl.addEventListener('mouseenter', function(){
                        labels.forEach(function(l, j){ if (j <= idx) l.classList.add('highlight'); else l.classList.remove('highlight'); });
                    });
                    lbl.addEventListener('mouseleave', function(){ labels.forEach(l => l.classList.remove('highlight')); });
                });

                // Keyboard accessibility: allow left/right arrows
                ratingGroup.addEventListener('keydown', function(e){
                    const checked = ratingGroup.querySelector('input[type="radio"]:checked');
                    let index = inputs.findIndex(i => i === checked);
                    if (index === -1) index = inputs.length - 1;
                    if (e.key === 'ArrowLeft' && index > 0) { inputs[index-1].checked = true; updateSelected(); }
                    if (e.key === 'ArrowRight' && index < inputs.length -1) { inputs[index+1].checked = true; updateSelected(); }
                });

                // Initialize
                updateSelected();
            });
        });
        </script>
    </div>
</div>

<!-- Custom Styles for Feedback Page -->
<style>
    .feedback-container {
        max-width: 800px;
        margin: auto;
        background: #fff;
        padding: 30px 40px;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        border: 1px solid #e9ecef;
    }
    .feedback-container h3 { font-size: 1.5rem; color: #232a3b; margin-bottom: 10px; }
    .feedback-container p { font-size: 1rem; color: #5a6a7b; line-height: 1.6; margin-bottom: 25px; }
    .form-group label { display: block; font-weight: 600; color: #5a6a7b; margin-bottom: 8px; font-size: 14px; }
    .form-group textarea {
        width: 100%;
        padding: 12px;
        border: 1px solid #ced4da;
        border-radius: 8px;
        font-size: 1rem;
        color: #343a40;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        resize: vertical;
    }
    .form-group textarea:focus {
        border-color: #4a69bd;
        outline: none;
        box-shadow: 0 0 0 3px rgba(74, 105, 189, 0.2);
    }
    .feedback-container .btn { 
        margin-top: 10px; 
        padding: 12px 24px;
        background: var(--primary, #4a69bd);
        color: #fff;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.2s ease;
    }
    .feedback-container .btn:hover {
        background: var(--primary-light, #5a7acd);
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }
</style>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>