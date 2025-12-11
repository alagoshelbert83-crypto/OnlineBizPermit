<?php
$current_page = 'applicants'; // Keep 'applicants' active in the sidebar
$page_title = 'View Application';
require_once './staff_header.php'; // Handles session, DB, auth, and starts HTML
require_once './email_functions.php'; // For any email functions needed

// --- Main Logic ---
$application = null;
$message = '';
$email_message = '';
$applicationId = $_GET['id'] ?? 0;
$lgu_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_application'])) {
    $applicationId = (int)$_POST['application_id'];
    
    try {
        $conn->beginTransaction();

        // Update timestamp (skip silently if the column doesn't exist)
        $updateTimestampStmt = $conn->prepare("UPDATE applications SET updated_at = NOW() WHERE id = ?");
        try {
            $updateTimestampStmt->execute([$applicationId]);
        } catch (PDOException $e) {
            error_log("Failed to update updated_at (column may be missing): " . $e->getMessage());
            // continue without failing the entire operation
        }

        // Now, handle the LGU Section data
        $lgu_form_data = [
            'verification' => $_POST['verification'] ?? [],
            'fees' => $_POST['fees'] ?? []
        ];
        $lgu_form_data_json = json_encode($lgu_form_data);

        // Use Postgres upsert (ON CONFLICT) instead of MySQL REPLACE
        $lgu_stmt = $conn->prepare("INSERT INTO staff_form_data (application_id, form_data) VALUES (?, ?) ON CONFLICT (application_id) DO UPDATE SET form_data = EXCLUDED.form_data");
        $lgu_stmt->execute([$applicationId, $lgu_form_data_json]);

        $conn->commit();
        $message = '<div class="message success">LGU data updated successfully!</div>';
    } catch (PDOException $e) {
        try { $conn->rollBack(); } catch (Exception $_) {}
        $message = '<div class="message error">Failed to update data: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }

    header("Location: view_application.php?id=" . $applicationId . "&status=updated");
    exit;
}

if ($applicationId > 0) {
    $stmt = $conn->prepare("SELECT a.*, u.name as applicant_name, u.email as applicant_email FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE a.id = ?");
    $stmt->execute([$applicationId]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

if (!$application) {
    echo 'Application not found.';
    exit;
}

$form_details = json_decode($application['form_details'], true) ?? [];

// Document type labels mapping
$document_type_labels = [
    'dti_registration' => 'DTI Registration Certificate',
    'bir_registration' => 'BIR Registration Certificate',
    'barangay_clearance' => 'Barangay Clearance',
    'fire_safety_certificate' => 'Fire Safety Certificate',
    'sanitary_permit' => 'Sanitary Permit',
    'other' => 'Other Document',
    'health_inspection' => 'Health Inspection Certificate',
    'building_permit' => 'Building Permit'
];

// Fetch uploaded documents for this application
$documents = [];
try {
    $docs_stmt = $conn->prepare("SELECT * FROM documents WHERE application_id = ? ORDER BY document_type, upload_date");
    $docs_stmt->execute([$applicationId]);
    $documents = $docs_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Failed to fetch documents for application {$applicationId}: " . $e->getMessage());
    $documents = [];
}

// Fetch LGU/staff form data
$staff_form_data = [];
$stmt = $conn->prepare("SELECT form_data FROM staff_form_data WHERE application_id = ?");
try {
    $stmt->execute([$applicationId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $staff_form_data = json_decode($row['form_data'], true) ?? [];
    }
} catch (PDOException $e) {
    error_log("Failed to fetch staff_form_data: " . $e->getMessage());
    $staff_form_data = [];
}

// Display a success message if the URL contains the status parameter
if (isset($_GET['status']) && $_GET['status'] === 'updated') {
    $message = '<div class="message success">LGU Form data updated successfully!</div>';
}
?>
<?php require_once './staff_sidebar.php'; // Include the shared sidebar ?>
  <style>
    :root {
        --card-bg-color: #ffffff;
        --shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    /* Main Content */
    .main { flex: 1; padding: 30px; overflow-y: auto; transition: margin-left 0.3s ease; }
    .main-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .main-header h1 { font-size: 28px; font-weight: 700; color: var(--secondary-color); }

    .tab-nav { display: flex; border-bottom: 2px solid var(--border-color); margin-bottom: 20px; }

    .tab-link { padding: 10px 20px; cursor: pointer; background: transparent; border: none; font-size: 1rem; font-weight: 600; color: var(--text-secondary-color); position: relative; }
    .tab-link.active { color: var(--primary-color); }
    .tab-link.active::after { content: ''; position: absolute; bottom: -2px; left: 0; right: 0; height: 3px; background: var(--primary-color); border-radius: 3px 3px 0 0; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .details-card, .form-container { background: var(--card-bg-color); padding: 30px; border-radius: var(--border-radius); box-shadow: var(--shadow); }
    .details-card h3, .form-container h2 { font-size: 1.5rem; font-weight: 600; margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid var(--border-color); }
    .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .info-item label { display: block; font-weight: 600; color: var(--text-secondary-color); margin-bottom: 5px; }
    .info-item p { font-size: 1.1rem; }
    .info-item .badge { padding: 5px 10px; border-radius: 20px; font-weight: 600; font-size: 0.9rem; text-align: center; display: inline-block; }
    .info-item .badge-yes { background: rgba(40, 167, 69, 0.1); color: #28a745; }
    .section-divider { margin-top: 30px; padding-top: 25px; border-top: 1px solid var(--border-color); }

    table { width: 100%; border-collapse: collapse; margin-top: 15px; }

    th, td { border: 1px solid #444; padding: 6px; text-align: center; }
    th { background: #f2f2f2; }
    input, textarea, select { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 4px; }
    select {appearance:none;}


    .form-actions { display: flex; gap: 15px; justify-content: flex-start; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e9ecef; }
    .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: all 0.3s ease; }
    .btn-primary { background: #4a69bd; color: #fff; }
    .btn-secondary { background: #6c757d; color: #fff; }
    .btn-release { background: linear-gradient(45deg, #28a745, #218838); color: #fff; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3); }
    .btn-release:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4); }

    .status-badge { padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 0.9rem; color: #fff; text-transform: capitalize; }
    .status-approved, .status-complete { background-color: #28a745; }
    .status-pending, .status-in-review { background-color: #ffc107; color: #333; }
    .status-rejected { background-color: #dc3545; }

    /* Styles for the full edit form */
    .business-permit-form .form-section { border: 1px solid #e2e8f0; padding: 20px; margin-bottom: 20px; border-radius: 8px; }
    .business-permit-form .form-section h2 { font-size: 1.2rem; color: var(--primary-color); border-bottom: 2px solid var(--primary-color); padding-bottom: 8px; margin-bottom: 20px; }
    .business-permit-form .form-row { display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 15px; }
    .business-permit-form .form-group { flex: 1; min-width: 250px; }
    .business-permit-form .form-group label { font-weight: 600; color: var(--text-secondary-color); margin-bottom: 5px; display: block; }
    .business-permit-form input[type="text"], .business-permit-form input[type="email"], .business-permit-form input[type="date"], .business-permit-form input[type="number"], .business-permit-form textarea, .business-permit-form select { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; }
    .business-permit-form .radio-options { display: flex; gap: 15px; }
    .business-permit-form .radio-options label { font-weight: normal; }
    .message { padding: 15px; margin-bottom: 20px; border-radius: 8px; font-weight: 500; border: 1px solid transparent; }
    .message.success { background-color: #d4edda; color: #155724; border-color: #c3e6cb; }
    .message.error { background-color: #f8d7da; color: #721c24; border-color: #f5c6cb; }

  </style>

    <div class="main">
        <header class="header">
            <div class="header-left">
                <div>
                    <h1 style="margin: 0; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-file-alt" style="color: var(--accent-color);"></i>
                        Application #<?= $application['id'] ?>
                    </h1>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; margin-top: 4px; margin-left: 34px;">
                        Review application details and update status
                    </p>
                </div>
            </div>
        </header>

        <?= $message ?>
        <div class="tab-nav">
            <button class="tab-link active" data-tab="tab-1">View Application</button>
            <button class="tab-link" data-tab="tab-2">LGU Form Section</button>
        </div>


        <div id="tab-1" class="tab-content active">
            <div class="details-card">
                <?= $email_message ?>

                <h3>Applicant Information</h3>
                <div class="info-grid">

                    <div class="info-item"><label>Applicant Name</label><p><?= htmlspecialchars($application['applicant_name'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Email Address</label><p><?= htmlspecialchars($application['applicant_email'] ?? 'N/A') ?></p></div>

                </div>

                <h3 class="section-divider">Business Information</h3>
                <div class="info-grid">
                    <div class="info-item"><label>Business Name</label><p><?= htmlspecialchars($application['business_name'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Trade Name/Franchise</label><p><?= htmlspecialchars($form_details['trade_name'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Business Address</label><p><?= htmlspecialchars($application['business_address'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Business Email</label><p><?= htmlspecialchars($form_details['b_email'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Business Mobile</label><p><?= htmlspecialchars($form_details['b_mobile'] ?? 'N/A') ?></p></div>
                </div>

                <h3 class="section-divider">Owner Information</h3>
                <div class="info-grid">
                    <div class="info-item"><label>Last Name</label><p><?= htmlspecialchars($form_details['last_name'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>First Name</label><p><?= htmlspecialchars($form_details['first_name'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Middle Name</label><p><?= htmlspecialchars($form_details['middle_name'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Owner's Address</label><p><?= htmlspecialchars($form_details['owner_address'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Owner's Email</label><p><?= htmlspecialchars($form_details['o_email'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Owner's Mobile</label><p><?= htmlspecialchars($form_details['o_mobile'] ?? 'N/A') ?></p></div>
                </div>

                <h3 class="section-divider">Other Details</h3>
                <div class="info-grid">
                    <div class="info-item"><label>Application Type</label><p><?= htmlspecialchars($form_details['application_type'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Mode of Payment</label><p><?= htmlspecialchars($form_details['mode_of_payment'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>TIN Number</label><p><?= htmlspecialchars($form_details['tin_no'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>DTI Reg. No.</label><p><?= htmlspecialchars($form_details['dti_reg_no'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Business Area (sq m)</label><p><?= htmlspecialchars($form_details['business_area'] ?? 'N/A') ?></p></div>
                    <div class="info-item"><label>Total Employees</label><p><?= htmlspecialchars($form_details['total_employees'] ?? 'N/A') ?></p></div>
                </div>

                <h3 class="section-divider">Application Actions</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Submitted On</label>
                        <p><?= date('M d, Y, h:i A', strtotime($application['submitted_at'])) ?></p>
                    </div>
                    <?php if ($application['status'] === 'approved' || $application['status'] === 'complete'): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <label>Actions</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <a href="generate_permit.php?id=<?= $applicationId ?>" class="btn btn-secondary" target="_blank">
                                <i class="fas fa-print"></i> View/Print Permit
                            </a>
                            <a href="release_permit.php?id=<?= $applicationId ?>" class="btn btn-release" onclick="return confirm('Are you sure you want to release this permit and notify the applicant? This action cannot be undone.');">
                                <i class="fas fa-paper-plane"></i> Release & Notify Applicant
                            </a>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                 <h3 class="section-divider">Applicant Form Details</h3>
                <div class="info-grid">
                <?php

                foreach ($form_details as $key => $value) {
                    // Exclude keys that are already displayed separately
                    if (in_array($key, ['business_name', 'business_address', 'type_of_business', 'applicant_name', 'applicant_email'])) {
                        continue;
                    }

                    // Format the label
                    $label = ucwords(str_replace('_', ' ', $key));

                    // Display the key-value pair as a single item
                     echo '<div class="info-item">';
                     echo '<label>' . htmlspecialchars($label) . '</label>';
                    
                        echo '<p>' . htmlspecialchars($value) . '</p>';
                    
                      echo '</div>';
                }
                ?>

                </div>

                <h3 class="section-divider">II. UPLOADED DOCUMENTS</h3>
                <div class="document-list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; margin: 20px 0;">
                    <?php if (empty($documents)): ?>
                        <p style="grid-column: 1 / -1; color: var(--text-secondary);">No documents have been uploaded for this application.</p>
                    <?php else: ?>
                        <?php foreach ($documents as $doc): ?>
                            <?php
                            $doc_type = trim($doc['document_type'] ?? '');
                            // Handle null, empty, or 'Other' document_type - infer from filename if possible
                            if (empty($doc_type) || $doc_type === null || $doc_type === '') {
                                // Try to infer from filename
                                $filename_lower = strtolower($doc['document_name'] ?? '');
                                if (strpos($filename_lower, 'dti') !== false || strpos($filename_lower, 'registration') !== false) {
                                    $doc_type = 'dti_registration';
                                } elseif (strpos($filename_lower, 'bir') !== false) {
                                    $doc_type = 'bir_registration';
                                } elseif (strpos($filename_lower, 'barangay') !== false || strpos($filename_lower, 'clearance') !== false) {
                                    $doc_type = 'barangay_clearance';
                                } elseif (strpos($filename_lower, 'fire') !== false || strpos($filename_lower, 'safety') !== false) {
                                    $doc_type = 'fire_safety_certificate';
                                } elseif (strpos($filename_lower, 'sanitary') !== false) {
                                    $doc_type = 'sanitary_permit';
                                } elseif (strpos($filename_lower, 'health') !== false || strpos($filename_lower, 'inspection') !== false) {
                                    $doc_type = 'health_inspection';
                                } elseif (strpos($filename_lower, 'building') !== false) {
                                    $doc_type = 'building_permit';
                                } else {
                                    $doc_type = 'other';
                                }
                            }
                            // Normalize the doc_type key (lowercase, no spaces)
                            $doc_type_key = strtolower(str_replace([' ', '-'], '_', $doc_type));
                            $doc_label = isset($document_type_labels[$doc_type_key]) ? $document_type_labels[$doc_type_key] : ucfirst(str_replace('_', ' ', $doc_type));
                            $file_extension = strtolower(pathinfo($doc['document_name'], PATHINFO_EXTENSION));
                            // Use absolute path from root
                            $file_path = '/uploads/' . htmlspecialchars($doc['file_path']);
                            ?>
                            <div class="document-item" style="border: 1px solid #e2e8f0; border-radius: 12px; padding: 15px; background: #fff; display: flex; flex-direction: column; align-items: center; text-align: center; transition: all 0.2s ease;">
                                <div class="doc-preview" style="height: 100px; width: 100%; display: flex; align-items: center; justify-content: center; background: #f8fafc; border-radius: 8px; margin-bottom: 10px;">
                                    <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                        <a href="<?= $file_path ?>" target="_blank" title="View <?= htmlspecialchars($doc_label) ?>">
                                            <img src="<?= $file_path ?>" 
                                                 alt="<?= htmlspecialchars($doc_label) ?>" 
                                                 style="max-height: 100%; max-width: 100%; object-fit: cover; border-radius: 4px;"
                                                 onerror="this.onerror=null; this.src='data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'100\' height=\'100\'%3E%3Crect fill=\'%23f8fafc\' width=\'100\' height=\'100\'/%3E%3Ctext x=\'50%25\' y=\'50%25\' text-anchor=\'middle\' dy=\'.3em\' fill=\'%2364748b\' font-family=\'sans-serif\' font-size=\'14\'%3EImage%3C/text%3E%3C/svg%3E';">
                                        </a>
                                    <?php elseif ($file_extension === 'pdf'): ?>
                                        <a href="<?= $file_path ?>" target="_blank" title="View PDF: <?= htmlspecialchars($doc_label) ?>">
                                            <i class="fas fa-file-pdf" style="font-size: 2.5rem; color: #64748b;"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="<?= $file_path ?>" target="_blank" title="View Document: <?= htmlspecialchars($doc_label) ?>">
                                            <i class="fas fa-file-alt" style="font-size: 2.5rem; color: #64748b;"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                <div class="doc-info" style="width: 100%;">
                                    <p class="doc-type-label" style="font-weight: 600; font-size: 0.95rem; color: #1e293b; margin-bottom: 5px;"><strong><?= htmlspecialchars($doc_label) ?></strong></p>
                                    <p class="doc-filename" style="font-weight: 400; font-size: 0.8rem; color: #64748b; margin: 0; word-break: break-all; line-height: 1.3;" title="<?= htmlspecialchars($doc['document_name']) ?>"><?= htmlspecialchars($doc['document_name']) ?></p>
                                </div>
                                <a href="<?= $file_path ?>" class="btn btn-secondary" target="_blank" style="margin-top: 10px; padding: 6px 12px; font-size: 0.8rem; width: 100%;" title="Open <?= htmlspecialchars($doc_label) ?>">
                                    <i class="fas fa-eye"></i> View Document
                                </a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if (!empty($staff_form_data)): ?>
                <h3 class="section-divider">LGU Section Assessment</h3>
                <div class="form-section">
                    <h2>II. LGU SECTION (Read-Only)</h2>
                    <table>
                        <tr>
                            <th>Description</th>
                            <th>Office/Agency</th>
                            <th>Yes</th>
                            <th>No</th>
                            <th>Not Needed</th>
                        </tr>
                        <?php
                        $documents = [
                            "Occupancy Permit (For New)" => "Office of the Building Official",
                            "Barangay Clearance (For Renewal)" => "Barangay",
                            "Sanitary Permit / Health Clearance" => "City Health Office",
                            "City Environmental Certificate" => "City ENRO",
                            "Market Clearance (For Stall Holder)" => "City Market Administrator",
                            "Valid Fire Safety Inspection Certificate" => "Bureau of Fire Protection"
                        ];
                        foreach ($documents as $desc => $office) {
                            $status = $staff_form_data['verification'][$desc] ?? '';
                            echo "<tr>
                                <td>$desc</td>
                                <td>$office</td>
                                <td>" . ($status === 'Yes' ? '✔️' : '') . "</td>
                                <td>" . ($status === 'No' ? '✔️' : '') . "</td>
                                <td>" . ($status === 'Not Needed' ? '✔️' : '') . "</td>
                            </tr>";
                        }
                        ?>
                    </table>

                    <h3>2. Assessment of Applicable Fees</h3>
                    <table>
                        <tr>
                            <th>Local Taxes / Regulatory Fees</th>
                            <th>Amount Due</th>
                            <th>Penalty / Surcharge</th>
                            <th>Total</th>
                        </tr>
                        <?php
                        $fees = [
                            "Gross Sale Tax", "Tax on Delivery Vans/Trucks", "Tax on Storage for Combustible/Explosive Substances",
                            "Tax on Signboard/Billboards", "Mayor's Permit Fee", "Garbage Charges", "Delivery Trucks/Vans Permit Fee",
                            "Sanitary Inspection Fee", "Building Inspection Fee", "Electrical Inspection Fee", "Mechanical Inspection Fee",
                            "Plumbing Inspection Fee", "Signboard/Billboard Renewal Fee", "Signboard/Billboard and Permit Fee",
                            "Storage & Sale of Combustible/Explosive Substances", "Others"
                        ];
                        foreach ($fees as $fee) {
                            $amount = $staff_form_data['fees'][$fee]['amount'] ?? 0;
                            $penalty = $staff_form_data['fees'][$fee]['penalty'] ?? 0;
                            $total = $staff_form_data['fees'][$fee]['total'] ?? 0;
                            echo "<tr>
                                <td>$fee</td>
                                <td>₱ " . number_format((float)$amount, 2) . "</td>
                                <td>₱ " . number_format((float)$penalty, 2) . "</td>
                                <td>₱ " . number_format((float)$total, 2) . "</td>
                            </tr>";
                        }
                        ?>
                        <tr>
                            <td colspan="3"><strong>Total Fees for LGU</strong></td>
                            <td>
                                <?php
                                $totalLGU = $staff_form_data['fees']['Total Fees for LGU']['total'] ?? 0;
                                echo "₱ " . number_format((float)$totalLGU, 2);
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="3"><strong>Fire Safety Inspection Fee (10%)</strong></td>
                            <td>
                                <?php
                                $totalFSIF = $staff_form_data['fees']['FSIF']['total'] ?? 0;
                                echo "₱ " . number_format((float)$totalFSIF, 2);
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php else: ?>
                <h3 class="section-divider">LGU Section Assessment</h3>
                <p>The LGU Section form has not been filled out for this application yet.</p>
                <?php endif; ?>

            </div>

        </div>

        <div id="tab-2" class="tab-content">
            <div class="form-container">
                <form method="POST" action="view_application.php?id=<?= $applicationId ?>" class="business-permit-form">
                    <input type="hidden" name="application_id" value="<?= $applicationId ?>">
                    <input type="hidden" name="update_application" value="1">
                    <input type="hidden" name="old_status" value="<?= $application['status'] ?>">
                    <?= $lgu_message ?>
                    
                    <!-- LGU Section -->
                    <div class="form-section">
                        <h2>II. LGU Form</h2>
                        <table>
                            <tr>
                                <th>Description</th>
                                <th>Office/Agency</th>
                                <th>Yes</th>
                                <th>No</th>
                                <th>Not Needed</th>
                            </tr>
                            <?php
                            $documents = [
                                "Occupancy Permit (For New)" => "Office of the Building Official",
                                "Barangay Clearance (For Renewal)" => "Barangay",
                                "Sanitary Permit / Health Clearance" => "City Health Office",
                                "City Environmental Certificate" => "City ENRO",
                                "Market Clearance (For Stall Holder)" => "City Market Administrator",
                                "Valid Fire Safety Inspection Certificate" => "Bureau of Fire Protection"
                            ];
                            foreach ($documents as $desc => $office) {
                                echo "<tr>
                                    <td>$desc</td>
                                    <td>$office</td>
                                    <td><input type='radio' name='verification[$desc]' value='Yes' " . (($staff_form_data['verification'][$desc] ?? '') === 'Yes' ? 'checked' : '') . "></td>
                                    <td><input type='radio' name='verification[$desc]' value='No' " . (($staff_form_data['verification'][$desc] ?? '') === 'No' ? 'checked' : '') . "></td>
                                    <td><input type='radio' name='verification[$desc]' value='Not Needed' " . (($staff_form_data['verification'][$desc] ?? '') === 'Not Needed' ? 'checked' : '') . "></td>
                                </tr>";
                            }
                            ?>
                        </table>

                        <h3>2. Assessment of Applicable Fees</h3>
                        <table>
                            <tr>
                                <th>Local Taxes / Regulatory Fees</th>
                                <th>Amount Due</th>
                                <th>Penalty / Surcharge</th>
                                <th>Total</th>
                            </tr>
                            <?php
                            $fees = [
                                "Gross Sale Tax", "Tax on Delivery Vans/Trucks", "Tax on Storage for Combustible/Explosive Substances",
                                "Tax on Signboard/Billboards", "Mayor's Permit Fee", "Garbage Charges", "Delivery Trucks/Vans Permit Fee",
                                "Sanitary Inspection Fee", "Building Inspection Fee", "Electrical Inspection Fee", "Mechanical Inspection Fee",
                                "Plumbing Inspection Fee", "Signboard/Billboard Renewal Fee", "Signboard/Billboard and Permit Fee",
                                "Storage & Sale of Combustible/Explosive Substances", "Others"
                            ];
                            foreach ($fees as $fee) {
                                echo "<tr>
                                    <td>$fee</td>
                                    <td><input type='number' name='fees[$fee][amount]' step='0.01' value='" . htmlspecialchars($staff_form_data['fees'][$fee]['amount'] ?? '') . "'></td>
                                    <td><input type='number' name='fees[$fee][penalty]' step='0.01' value='" . htmlspecialchars($staff_form_data['fees'][$fee]['penalty'] ?? '') . "'></td>
                                    <td><input type='number' name='fees[$fee][total]' step='0.01' value='" . htmlspecialchars($staff_form_data['fees'][$fee]['total'] ?? '') . "'></td>
                                </tr>";
                            }
                            ?>
                            <tr>
                                <td colspan="3"><strong>Total Fees for LGU</strong></td>
                                <td><input type="number" name="fees[Total Fees for LGU][total]" step="0.01" value="<?= htmlspecialchars($staff_form_data['fees']['Total Fees for LGU']['total'] ?? '') ?>"></td>
                            </tr>
                            <tr>
                                <td colspan="3"><strong>Fire Safety Inspection Fee (10%)</strong></td>
                                <td><input type="number" name="fees[FSIF][total]" step="0.01" value="<?= htmlspecialchars($staff_form_data['fees']['FSIF']['total'] ?? '') ?>"></td>
                            </tr>
                        </table>
                    </div>

                    <div class="form-actions">
                        <button type="submit" name="update_application" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save LGU Form
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="document.querySelector('[data-tab=\'tab-1\']').click();">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
  <script>
    const tabs = document.querySelectorAll('.tab-link');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            const target = document.getElementById(tab.dataset.tab);

            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            tabContents.forEach(tc => tc.classList.remove('active'));
            target.classList.add('active');
        });
    });

    // --- LGU Form Fee Calculation Logic ---
    document.addEventListener('DOMContentLoaded', function() {
        const feesTable = document.querySelector('#tab-2 .business-permit-form table:last-of-type');
        if (!feesTable) return;

        const feeRows = feesTable.querySelectorAll('tbody tr');
        const totalLGUInput = document.querySelector('input[name="fees[Total Fees for LGU][total]"]');
        const fsifInput = document.querySelector('input[name="fees[FSIF][total]"]');

        // Function to calculate the total for a single row
        function calculateRowTotal(row) {
            const amountInput = row.querySelector('input[name*="[amount]"]');
            const penaltyInput = row.querySelector('input[name*="[penalty]"]');
            const totalInput = row.querySelector('input[name*="[total]"]');

            if (amountInput && penaltyInput && totalInput) {
                const amount = parseFloat(amountInput.value) || 0;
                const penalty = parseFloat(penaltyInput.value) || 0;
                totalInput.value = (amount + penalty).toFixed(2);
            }
        }

        // Function to calculate the grand totals
        function calculateGrandTotals() {
            let grandTotal = 0;
            const individualFeeRows = Array.from(feeRows).slice(0, -2); // Exclude the last two total rows

            individualFeeRows.forEach(row => {
                const totalInput = row.querySelector('input[name*="[total]"]');
                if (totalInput) {
                    grandTotal += parseFloat(totalInput.value) || 0;
                }
            });

            if (totalLGUInput) {
                totalLGUInput.value = grandTotal.toFixed(2);
            }

            if (fsifInput) {
                // Fire Safety Inspection Fee is 10% of the LGU total
                const fsif = grandTotal * 0.10;
                fsifInput.value = fsif.toFixed(2);
            }
        }

        // Add event listeners to all amount and penalty inputs
        feeRows.forEach(row => {
            const inputs = row.querySelectorAll('input[name*="[amount]"], input[name*="[penalty]"]');
            inputs.forEach(input => {
                input.addEventListener('input', () => {
                    calculateRowTotal(row);
                    calculateGrandTotals();
                });
            });
        });

        // Initial calculation on page load to populate totals if data exists
        const individualFeeRows = Array.from(feeRows).slice(0, -2);
        individualFeeRows.forEach(row => {
             calculateRowTotal(row);
        });
        calculateGrandTotals();
    });
  </script>

<?php require_once './staff_footer.php'; ?>