<?php
// Test Edit Functionality
require_once 'Applicant-dashboard/db.php';

echo "<h2>Testing Edit Functionality</h2>";

// Check if we have applications to test with
try {
    $stmt = $conn->prepare("SELECT id, business_name, business_address, type_of_business, status FROM applications LIMIT 3");
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Test query error: ' . $e->getMessage());
    $applications = [];
}

if (empty($applications)) {
    echo "<p>❌ No applications found to test with. Please create some applications first.</p>";
    exit;
}

echo "<h3>Available Applications for Testing:</h3>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
foreach ($applications as $app) {
    echo "<div style='margin-bottom: 15px; padding: 15px; background: #fff; border-radius: 8px; border: 1px solid #e9ecef;'>";
    echo "<h4>Application #{$app['id']}: {$app['business_name']}</h4>";
    echo "<p><strong>Address:</strong> {$app['business_address']}</p>";
    echo "<p><strong>Type:</strong> {$app['type_of_business']}</p>";
    echo "<p><strong>Status:</strong> {$app['status']}</p>";
    echo "</div>";
}
echo "</div>";

echo "<h3>Edit Functionality Test Links:</h3>";
echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";

echo "<h4>For Applicants (Test with Maria Garcia or David Chen):</h4>";
echo "<ul>";
echo "<li><a href='Applicant-dashboard/applicant_dashboard.php' target='_blank'>Applicant Dashboard</a> - View applications and click 'Edit' buttons</li>";
echo "<li><a href='Applicant-dashboard/view_my_application.php?id={$applications[0]['id']}' target='_blank'>View Application #{$applications[0]['id']}</a> - Click 'Edit Application' button</li>";
echo "<li><a href='Applicant-dashboard/edit_application.php?id={$applications[0]['id']}' target='_blank'>Direct Edit Link</a> - Test direct edit access</li>";
echo "</ul>";

echo "<h4>For Staff:</h4>";
echo "<ul>";
echo "<li><a href='Staff-dashboard/applicants.php' target='_blank'>Staff Applicants Page</a> - View applications and click 'View' buttons</li>";
echo "<li><a href='Staff-dashboard/view_application.php?id={$applications[0]['id']}' target='_blank'>Staff View Application #{$applications[0]['id']}</a> - Test staff edit functionality</li>";
echo "</ul>";

echo "<h4>For Admins:</h4>";
echo "<ul>";
echo "<li><a href='Admin-dashboard/pending_applications.php' target='_blank'>Admin Applications Page</a> - View applications</li>";
echo "<li><a href='Admin-dashboard/view_application.php?id={$applications[0]['id']}' target='_blank'>Admin View Application #{$applications[0]['id']}</a> - Test admin edit functionality</li>";
echo "</ul>";

echo "</div>";

echo "<h3>What to Test:</h3>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<ol>";
echo "<li><strong>Edit Access:</strong> Verify that edit buttons appear in all application views</li>";
echo "<li><strong>Form Functionality:</strong> Test editing business name, address, and type of business</li>";
echo "<li><strong>Status Updates:</strong> For staff/admin, test changing application status</li>";
echo "<li><strong>Validation:</strong> Try submitting empty fields to test validation</li>";
echo "<li><strong>Success Messages:</strong> Verify success messages appear after updates</li>";
echo "<li><strong>Notifications:</strong> Check if status change notifications are sent to users</li>";
echo "<li><strong>Security:</strong> Verify users can only edit their own applications</li>";
echo "</ol>";
echo "</div>";

echo "<h3>Test Credentials:</h3>";
echo "<div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<p><strong>Maria Garcia:</strong> maria.garcia@example.com / password</p>";
echo "<p><strong>David Chen:</strong> david.chen@example.com / password</p>";
echo "<p><strong>Admin:</strong> Use your admin credentials</p>";
echo "<p><strong>Staff:</strong> Use your staff credentials</p>";
echo "</div>";

echo "<h3>Expected Results:</h3>";
echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<ul>";
echo "<li>✅ Edit buttons visible in all application views</li>";
echo "<li>✅ Forms load with current application data</li>";
echo "<li>✅ Updates save successfully with success messages</li>";
echo "<li>✅ Status changes trigger notifications to users</li>";
echo "<li>✅ Security prevents unauthorized access</li>";
echo "<li>✅ Form validation works properly</li>";
echo "</ul>";
echo "</div>";

$conn->close();
?>
