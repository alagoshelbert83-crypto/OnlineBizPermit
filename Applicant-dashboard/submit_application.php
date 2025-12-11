<?php
// Page-specific variables
$page_title = 'Submit Application';
$current_page = 'submit-application';

// Include Header
require_once __DIR__ . '/applicant_header.php';

$message = '';

// Include Sidebar
require_once __DIR__ . '/applicant_sidebar.php';
?>

<!-- Main Content -->
<div class="main">
    <div class="form-container">
        <header class="header">
            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px;">
                <a href="applicant_dashboard.php" class="btn" style="padding: 8px 12px;"><i class="fas fa-arrow-left"></i> Back</a>
            </div>
            
            <!-- Official Form Header -->
            <div class="official-header">
                <div class="header-logo">
                    <div class="logo-container">
                        <img src="San Miguel.png" alt="Municipality of San Miguel, Catanduanes Logo" class="municipal-logo" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                       
                    </div>
                </div>
                <div class="header-content">
                    <h1 class="official-title">APPLICATION FORM FOR BUSINESS PERMIT</h1>
                    <div class="form-info">
                        <div class="info-row">
                            <span class="label">TAX YEAR:</span>
                            <span class="value">2025</span>
                        </div>
                        <div class="info-row">
                            <span class="label">CITY / MUNICIPALITY:</span>
                            <span class="value">San Miguel, Catanduanes</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Instructions -->
            <div class="instructions">
                <p class="instruction-item">1. Provide accurate information and print legibly to avoid delays. Incomplete application form will be returned to the applicant.</p>
                <p class="instruction-item">2. Ensure that all documents attached to this form (if any) are complete and properly filled out.</p>
            </div>
        </header>

        <?php if ($message) echo $message; ?>

        <form method="POST" action="process_business_permit.php" class="business-permit-form" enctype="multipart/form-data">
            
            <!-- Section I: APPLICANT SECTION -->
            <div class="form-section">
                <h2>I. APPLICANT SECTION</h2>

                <!-- 1. BASIC INFORMATION -->
                <section>
                    <h3>1. BASIC INFORMATION</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Application Type:</label>
                            <div class="radio-options">
                                <input type="radio" id="new" name="application_type" value="New" required> 
                                <label for="new">New</label>
                                <input type="radio" id="renewal" name="application_type" value="Renewal"> 
                                <label for="renewal">Renewal</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Mode of Payment:</label>
                            <div class="radio-options">
                                <input type="radio" id="annually" name="mode_of_payment" value="Annually" required> 
                                <label for="annually">Annually</label>
                                <input type="radio" id="semi-annually" name="mode_of_payment" value="Semi-Annually"> 
                                <label for="semi-annually">Semi-Annually</label>
                                <input type="radio" id="quarterly" name="mode_of_payment" value="Quarterly"> 
                                <label for="quarterly">Quarterly</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="date_of_application">Date of Application:</label>
                            <input type="date" id="date_of_application" name="date_of_application" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="tin_no">TIN No.:</label>
                            <input type="text" id="tin_no" name="tin_no" placeholder="e.g., 123-456-789-000">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="dti_reg_no">DTI/SCC/CDA Registration No.:</label>
                            <input type="text" id="dti_reg_no" name="dti_reg_no" placeholder="Enter your registration number">
                        </div>
                        <div class="form-group">
                            <label for="dti_reg_date">DTI/SCC/CDA Date of Registration:</label>
                            <input type="date" id="dti_reg_date" name="dti_reg_date" placeholder="Select registration date">
                        </div>
                    </div>

                    <!-- Type of Business Table -->
                    <div class="business-type-section">
                        <label>Type of Business:</label>
                        <table class="business-type-table">
                            <thead>
                                <tr>
                                    <th>Business</th>
                                    <th>Amendment: From</th>
                                    <th>Amendment: To</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="checkbox-group">
                                            <input type="radio" name="type_of_business" value="Single" id="single"> 
                                            <label for="single">Single</label>
                                        </div>
                                    </td>
                                    <td><input type="text" name="amendment_from" placeholder="e.g., Single"></td>
                                    <td><input type="text" name="amendment_to" placeholder="e.g., Partnership"></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="checkbox-group">
                                            <input type="radio" name="type_of_business" value="Partnership" id="partnership"> 
                                            <label for="partnership">Partnership</label>
                                        </div>
                                    </td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="checkbox-group">
                                            <input type="radio" name="type_of_business" value="Corporation" id="corporation"> 
                                            <label for="corporation">Corporation</label>
                                        </div>
                                    </td>
                                    <td></td>
                                    <td></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="checkbox-group">
                                            <input type="radio" name="type_of_business" value="Cooperative" id="cooperative"> 
                                            <label for="cooperative">Cooperative</label>
                                        </div>
                                    </td>
                                    <td></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Are you enjoying tax incentive from any government Entity?</label>
                            <div class="radio-options">
                                <input type="radio" name="tax_incentive" value="Yes" id="tax_yes"> 
                                <label for="tax_yes">Yes</label>
                                <input type="radio" name="tax_incentive" value="No" id="tax_no"> 
                                <label for="tax_no">No</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="incentive_entity">Please specify the entity:</label>
                            <input type="text" id="incentive_entity" name="incentive_entity" placeholder="e.g., PEZA, BOI">
                        </div>
                    </div>

                    <h4>Name of Taxpayer/Registrant</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="last_name">Last Name:</label>
                            <input type="text" id="last_name" name="last_name" placeholder="e.g., Dela Cruz" required>
                        </div>
                        <div class="form-group">
                            <label for="first_name">First Name:</label>
                            <input type="text" id="first_name" name="first_name" placeholder="e.g., Juan" required>
                        </div>
                        <div class="form-group">
                            <label for="middle_name">Middle Name:</label>
                            <input type="text" id="middle_name" name="middle_name" placeholder="e.g., Santos">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="business_name">Business Name:</label>
                            <input type="text" id="business_name" name="business_name" placeholder="e.g., Juan's Sari-Sari Store" required>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="trade_name">Trade Name/Franchise:</label>
                            <input type="text" id="trade_name" name="trade_name" placeholder="e.g., The Corner Store">
                        </div>
                    </div>
                </section>

                <!-- 2. OTHER INFORMATION -->
                <section>
                    <h3>2. OTHER INFORMATION</h3>
                    <p class="notes">Note: For Renewal Applications; do not fill up this section unless certain information have changed</p>

                    <div class="form-row">
                        <div class="form-group" style="flex: 3;">
                            <label for="business_address">Business Address:</label>
                            <input type="text" id="business_address" name="business_address" placeholder="House No., Street, Barangay, Municipality">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="b_postal_code">Postal Code:</label>
                            <input type="text" id="b_postal_code" name="b_postal_code" placeholder="e.g., 4800">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="b_email">Email Address:</label>
                            <input type="email" id="b_email" name="b_email" placeholder="e.g., business@example.com">
                        </div>
                        <div class="form-group">
                            <label for="b_telephone">Telephone No:</label>
                            <input type="text" id="b_telephone" name="b_telephone" placeholder="e.g., (02) 8123-4567">
                        </div>
                        <div class="form-group">
                            <label for="b_mobile">Mobile No.:</label>
                            <input type="text" id="b_mobile" name="b_mobile" placeholder="e.g., 0917-123-4567">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group" style="flex: 3;">
                            <label for="owner_address">Owner's Address:</label>
                            <input type="text" id="owner_address" name="owner_address" placeholder="House No., Street, Barangay, Municipality">
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="o_postal_code">Postal Code:</label>
                            <input type="text" id="o_postal_code" name="o_postal_code" placeholder="e.g., 4800">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="o_email">Email Address:</label>
                            <input type="email" id="o_email" name="o_email" placeholder="e.g., owner@example.com">
                        </div>
                        <div class="form-group">
                            <label for="o_telephone">Telephone No:</label>
                            <input type="text" id="o_telephone" name="o_telephone" placeholder="e.g., (02) 8123-4567">
                        </div>
                        <div class="form-group">
                            <label for="o_mobile">Mobile No.:</label>
                            <input type="text" id="o_mobile" name="o_mobile" placeholder="e.g., 0917-123-4567">
                        </div>
                    </div>

                    <h4>Emergency Contact</h4>
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="emergency_contact_name">In Case of emergency, provide name of contact person:</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" placeholder="e.g., Maria Santos">
                        </div>
                        <div class="form-group">
                            <label for="emergency_tel">Telephone No/Mobile No.:</label>
                            <input type="text" id="emergency_tel" name="emergency_tel" placeholder="e.g., 0918-765-4321">
                        </div>
                        <div class="form-group">
                            <label for="emergency_email">Email Address:</label>
                            <input type="email" id="emergency_email" name="emergency_email" placeholder="e.g., emergency.contact@example.com">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="business_area">Business Area (in sq m.):</label>
                            <input type="text" id="business_area" name="business_area" placeholder="e.g., 50">
            </div>
                        <div class="form-group">
                            <label for="total_employees">Total No. of Employee in Establishment:</label>
                            <input type="number" id="total_employees" name="total_employees" placeholder="e.g., 5">
                        </div>
                        <div class="form-group">
                            <label for="lgu_employees">NO. of Employees Residing in LGU:</label>
                            <input type="number" id="lgu_employees" name="lgu_employees" placeholder="e.g., 3">
                        </div>
                    </div>

                    <h4>Lessor's Information (Fill Up Only if Business Place is Rented)</h4>
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="lessor_name">Lessor's Full Name:</label>
                            <input type="text" id="lessor_name" name="lessor_name" placeholder="e.g., Pedro Penduko">
            </div>
                        <div class="form-group" style="flex: 2;">
                            <label for="lessor_address">Lessor's Full Address:</label>
                            <input type="text" id="lessor_address" name="lessor_address" placeholder="Lessor's complete address">
            </div>
            </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lessor_tel">Lessor's Full Telephone/Mobile No.:</label>
                            <input type="text" id="lessor_tel" name="lessor_tel" placeholder="e.g., 0919-111-2222">
        </div>
                        <div class="form-group">
                            <label for="lessor_email">Lessor's Email Address:</label>
                            <input type="email" id="lessor_email" name="lessor_email" placeholder="e.g., lessor@example.com">
                        </div>
                        <div class="form-group">
                            <label for="monthly_rental">Monthly Rental:</label>
                            <input type="text" id="monthly_rental" name="monthly_rental" placeholder="e.g., 5000">
                        </div>
                    </div>
                </section>

                <!-- 3. BUSINESS ACTIVITY -->
                <section>
                    <h3>3. BUSINESS ACTIVITY</h3>
                    <div class="business-activity-table">
                        <table class="official-table">
                            <thead>
                                <tr>
                                    <th>Line of Business</th>
                                    <th>No. of Units</th>
                                    <th colspan="2">Capitalization (for New Business)</th>
                                    <th colspan="2">Gross Sales/Receipts (for Renewal)</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th>Essential</th>
                                    <th>Non-Essential</th>
                                    <th>Essential</th>
                                    <th>Non-Essential</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><input type="text" name="lob_1" placeholder="e.g., Retail Store"></td>
                                    <td><input type="number" name="units_1" min="1" value="1"></td>
                                    <td><input type="text" name="cap_essential_1" placeholder="e.g., 100000"></td>
                                    <td><input type="text" name="cap_nonessential_1" placeholder="e.g., 50000"></td>
                                    <td><input type="text" name="sales_essential_1" placeholder="For renewals only"></td>
                                    <td><input type="text" name="sales_nonessential_1" placeholder="For renewals only"></td>
                                </tr>
                                <tr>
                                    <td><input type="text" name="lob_2" placeholder="Another Line of Business (if any)"></td>
                                    <td><input type="number" name="units_2" min="0"></td>
                                    <td><input type="text" name="cap_essential_2" placeholder="e.g., 20000"></td>
                                    <td><input type="text" name="cap_nonessential_2" placeholder="e.g., 10000"></td>
                                    <td><input type="text" name="sales_essential_2" placeholder="For renewals only"></td>
                                    <td><input type="text" name="sales_nonessential_2" placeholder="For renewals only"></td>
                                </tr>
                                <tr>
                                    <td><input type="text" name="lob_3" placeholder="Additional Line of Business"></td>
                                    <td><input type="number" name="units_3" min="0"></td>
                                    <td><input type="text" name="cap_essential_3" placeholder="e.g., 15000"></td>
                                    <td><input type="text" name="cap_nonessential_3" placeholder="e.g., 5000"></td>
                                    <td><input type="text" name="sales_essential_3" placeholder="For renewals only"></td>
                                    <td><input type="text" name="sales_nonessential_3" placeholder="For renewals only"></td>
                                </tr>
                                <tr>
                                    <td><input type="text" name="lob_4" placeholder="Additional Line of Business"></td>
                                    <td><input type="number" name="units_4" min="0"></td>
                                    <td><input type="text" name="cap_essential_4" placeholder="e.g., 10000"></td>
                                    <td><input type="text" name="cap_nonessential_4" placeholder="e.g., 2500"></td>
                                    <td><input type="text" name="sales_essential_4" placeholder="For renewals only"></td>
                                    <td><input type="text" name="sales_nonessential_4" placeholder="For renewals only"></td>
                                </tr>
                            </tbody>
                        </table>
                </div>
                </section>

                <!-- Document Upload Section -->
                <section>
                    <h3>4. REQUIRED DOCUMENTS</h3>
                    <p class="notes">Please upload all required documents. All files must be in PDF, JPG, or PNG format and under 100MB each.</p>
                    
                    <div class="document-upload-section">
                        <div class="document-item">
                            <label for="dti_registration"><strong>DTI Registration Certificate:</strong></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="dti_registration" name="documents[dti_registration]" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label for="dti_registration" class="file-label">
                                    <i class="fas fa-upload"></i> Choose File
                                </label>
                                <span class="file-name">No file selected</span>
                            </div>
                        </div>

                        <div class="document-item">
                            <label for="bir_registration"><strong>BIR Registration Certificate:</strong></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="bir_registration" name="documents[bir_registration]" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label for="bir_registration" class="file-label">
                                    <i class="fas fa-upload"></i> Choose File
                                </label>
                                <span class="file-name">No file selected</span>
                            </div>
                        </div>

                        <div class="document-item">
                            <label for="barangay_clearance"><strong>Barangay Clearance:</strong></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="barangay_clearance" name="documents[barangay_clearance]" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label for="barangay_clearance" class="file-label">
                                    <i class="fas fa-upload"></i> Choose File
                                </label>
                                <span class="file-name">No file selected</span>
                            </div>
                        </div>

                        <div class="document-item">
                            <label for="fire_safety_certificate"><strong>Fire Safety Certificate:</strong></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="fire_safety_certificate" name="documents[fire_safety_certificate]" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label for="fire_safety_certificate" class="file-label">
                                    <i class="fas fa-upload"></i> Choose File
                                </label>
                                <span class="file-name">No file selected</span>
                            </div>
                        </div>

                        <div class="document-item">
                            <label for="sanitary_permit"><strong>Sanitary Permit:</strong></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="sanitary_permit" name="documents[sanitary_permit]" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label for="sanitary_permit" class="file-label">
                                    <i class="fas fa-upload"></i> Choose File
                                </label>
                                <span class="file-name">No file selected</span>
                            </div>
                        </div>

                        <div class="document-item">
                            <label for="health_inspection"><strong>Health Inspection Certificate:</strong></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="health_inspection" name="documents[health_inspection]" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label for="health_inspection" class="file-label">
                                    <i class="fas fa-upload"></i> Choose File
                                </label>
                                <span class="file-name">No file selected</span>
                            </div>
                        </div>

                        <div class="document-item">
                            <label for="building_permit"><strong>Building Permit:</strong></label>
                            <div class="file-upload-wrapper">
                                <input type="file" id="building_permit" name="documents[building_permit]" class="file-input" accept=".pdf,.jpg,.jpeg,.png" required>
                                <label for="building_permit" class="file-label">
                                    <i class="fas fa-upload"></i> Choose File
                                </label>
                                <span class="file-name">No file selected</span>
                            </div>
                        </div>
                    </div>

                    <!-- Declaration -->
                    <div class="declaration-section">
                        <p class="declaration">
                            I DECLARE UNDER PENALTY OF PERJURY that the foregoing information are true based on my personal knowledge and authentic records. Further, I agree to comply with the regulatory requirement and other deficiencies within 30 days from release of the business permit.
                        </p>
                </div>
                </section>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Submit Application
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Custom Styles for Business Permit Form -->
<style>
    .form-container {
        max-width: 1100px;
        margin: auto;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 10px 30px rgba(0, 86, 179, 0.1);
        border: 2px solid #e2e8f0;
        overflow: hidden;
        position: relative;
    }

    .form-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #0056b3 0%, #3b82f6 50%, #0056b3 100%);
    }

    /* Official Header Styling */
    .official-header {
        display: flex;
        align-items: center;
        gap: 30px;
        margin-bottom: 30px;
        padding: 25px;
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 12px;
        border: 3px solid #0056b3;
        box-shadow: 0 4px 15px rgba(0, 86, 179, 0.1);
    }

    .header-logo {
        flex-shrink: 0;
    }

    /* Logo Container */
    .logo-container {
        position: relative;
        width: 140px;
        height: 140px;
    }

    .municipal-logo {
        width: 140px;
        height: 140px;
        border-radius: 50%;
        object-fit: cover;
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.4), inset 0 2px 10px rgba(255, 255, 255, 0.1);
        transition: all 0.3s ease;
    }

    .municipal-logo:hover {
        transform: scale(1.05);
        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5), inset 0 2px 10px rgba(255, 255, 255, 0.2);
    }

    .fallback-logo {
        display: none;
    }




    .header-content {
        flex: 1;
    }

    .official-title {
        font-size: 1.8rem;
        font-weight: 700;
        color: #0056b3;
        text-align: center;
        margin: 0 0 15px 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .form-info {
        display: flex;
        gap: 30px;
        justify-content: center;
    }

    .info-row {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .info-row .label {
        font-weight: bold;
        color: #333;
    }

    .info-row .value {
        font-weight: bold;
        color: #0056b3;
        font-size: 16px;
    }

    /* Instructions */
    .instructions {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 30px;
    }

    .instruction-item {
        margin: 0 0 10px 0;
        font-size: 14px;
        color: #856404;
        line-height: 1.5;
    }

    .instruction-item:last-child {
        margin-bottom: 0;
    }

    .form-section {
        border: 2px solid #e2e8f0;
        padding: 25px;
        margin-bottom: 25px;
        border-radius: 12px;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        box-shadow: 0 4px 12px rgba(0, 86, 179, 0.05);
        transition: all 0.3s ease;
        color: #070707ff;
    }

    .form-section:hover {
        box-shadow: 0 6px 20px rgba(0, 86, 179, 0.1);
        transform: translateY(-2px);
    }

    .form-section h2 {
        color: #0c0c0cff;
        border-bottom: 3px solid #3b82f6;
        padding-bottom: 10px;
        margin-bottom: 25px;
        font-size: 1.4rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        font-weight: 700;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .form-section h3 {
        color: #0a0b0bff;
        border-bottom: 2px solid #3b82f6;
        padding-bottom: 8px;
        margin-top: 30px;
        margin-bottom: 20px;
        font-size: 1.2rem;
        font-weight: 600;
        position: relative;
    }

    .form-section h3::before {
        content: '';
        position: absolute;
        left: -10px;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 20px;
        background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
        border-radius: 2px;
        color: #0b0b0bff;
    }

    .form-section h4 {
        color: #0c0c0cff;
        margin-top: 25px;
        margin-bottom: 15px;
        font-size: 1.1rem;
        font-weight: 600;
        padding-left: 15px;
        border-left: 3px solid #3b82f6;
        background: linear-gradient(90deg, rgba(59, 130, 246, 0.1) 0%, transparent 100%);
        padding: 10px 15px;
        border-radius: 0 8px 8px 0;
    }

    .form-row {
        display: flex;
        flex-wrap: wrap;
        margin-bottom: 15px;
        gap: 20px;
    }

    .form-group {
        flex: 1;
        min-width: 250px;
    }

    .form-group label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
        color: #050505ff;
    }

    .business-permit-form input[type="text"],
    .business-permit-form input[type="email"],
    .business-permit-form input[type="date"],
    .business-permit-form input[type="number"],
    .business-permit-form textarea {
        color: #080808ff;
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        box-sizing: border-box;
        font-size: 14px;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .business-permit-form input:focus,
    .business-permit-form textarea:focus {
        border-color: #3b82f6;
        outline: none;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        background: #ffffff;
        transform: translateY(-1px);
    }

    .business-permit-form input:hover,
    .business-permit-form textarea:hover {
        border-color: #94a3b8;
    }

    .radio-options {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        margin-top: 5px;
    }

    .radio-options input[type="radio"] {
        margin-right: 5px;
    }

    .radio-options label {
        font-weight: normal;
        display: inline-block;
        margin-right: 15px;
        cursor: pointer;
    }

    /* Business Type Table */
    .business-type-section {
        margin: 20px 0;
    }

    .business-type-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        margin-top: 10px;
    }

    .business-type-table th,
    .business-type-table td {
        padding: 12px;
        text-align: left;
        border: 1px solid #ddd;
    }

    .business-type-table th {
        background-color: #f8f9fa;
        font-weight: bold;
        color: #333;
        font-size: 12px;
    }

    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .checkbox-group input[type="radio"] {
        margin: 0;
    }

    .checkbox-group label {
        margin: 0;
        font-weight: normal;
        cursor: pointer;
    }

    /* Business Activity Table */
    .business-activity-table {
        overflow-x: auto;
        margin-top: 15px;
    }

    .official-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .official-table th,
    .official-table td {
        padding: 12px;
        text-align: left;
        border: 1px solid #ddd;
    }

    .official-table th {
        background-color: #f8f9fa;
        font-weight: bold;
        color: #333;
        font-size: 12px;
    }

    .official-table td input {
        width: 100%;
        border: none;
        padding: 8px;
        background: transparent;
    }

    .official-table td input:focus {
        background: #f8f9fa;
        border: 1px solid #0056b3;
    }

    .declaration {
        font-style: italic;
        color: #666;
        margin: 20px 0;
        padding: 15px;
        background: #f8f9fa;
        border-left: 4px solid #0056b3;
        border-radius: 4px;
        line-height: 1.6;
    }

    /* Document Upload Section */
    .document-upload-section {
        margin: 20px 0;
    }

    .document-item {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }

    .document-item label {
        font-weight: bold;
        color: #333;
        margin-bottom: 10px;
        display: block;
    }

    .file-upload-wrapper {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .file-input {
        position: absolute;
        width: 0.1px;
        height: 0.1px;
        opacity: 0;
        overflow: hidden;
        z-index: -1;
    }

    .file-label {
        display: inline-block;
        padding: 10px 20px;
        background-color: #0056b3;
        color: #fff;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        transition: background-color 0.2s ease;
        margin: 0;
    }

    .file-label:hover {
        background-color: #004494;
    }

    .file-label i {
        margin-right: 8px;
    }

    .file-name {
        flex-grow: 1;
        padding: 10px;
        border: 1px solid #ced4da;
        border-radius: 6px;
        background-color: #fff;
        color: #5a6a7b;
        font-style: italic;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .declaration-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    .form-actions {
        text-align: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }

    .btn-primary {
        background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        color: white;
        border: none;
        padding: 16px 32px;
        border-radius: 12px;
        cursor: pointer;
        font-size: 16px;
        font-weight: 700;
        display: inline-flex;
        align-items: center;
        gap: 12px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        position: relative;
        overflow: hidden;
    }

    .btn-primary::before {
        content: '';
        position: absolute;
        top: 0;
        left: -100%;
        width: 100%;
        height: 100%;
        background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
        transition: left 0.5s;
    }

    .btn-primary:hover::before {
        left: 100%;
    }

    .btn-primary:hover {
        background: linear-gradient(135deg, #047857 0%, #059669 100%);
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
    }

    .btn-primary:active {
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
    }

    .notes {
        font-style: italic;
        color: #666;
        margin-bottom: 15px;
        font-size: 14px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
        .form-container {
            padding: 20px;
        }

        .official-header {
            flex-direction: column;
        text-align: center;
        }

        .form-info {
            flex-direction: column;
            gap: 10px;
        }

        .form-row {
            flex-direction: column;
        }

        .form-group {
            min-width: 100%;
        }

        .radio-options {
            flex-direction: column;
            gap: 8px;
        }

        .business-activity-table,
        .business-type-table {
            font-size: 12px;
        }

        .business-activity-table th,
        .business-activity-table td,
        .business-type-table th,
        .business-type-table td {
            padding: 8px;
        }
    }
</style>

<?php
// Include Footer
require_once __DIR__ . '/applicant_footer.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('.business-permit-form');
    const applicationTypeRadios = document.querySelectorAll('input[name="application_type"]');
    const taxIncentiveRadios = document.querySelectorAll('input[name="tax_incentive"]');
    const incentiveEntityField = document.getElementById('incentive_entity');
    
    // Show/hide incentive entity field based on tax incentive selection
    function toggleIncentiveEntity() {
        const isYes = document.querySelector('input[name="tax_incentive"][value="Yes"]').checked;
        incentiveEntityField.parentElement.style.display = isYes ? 'block' : 'none';
        if (!isYes) {
            incentiveEntityField.value = '';
        }
    }
    
    // Initial check
    toggleIncentiveEntity();
    
    // Add event listeners
    taxIncentiveRadios.forEach(radio => {
        radio.addEventListener('change', toggleIncentiveEntity);
    });
    
    // File input name display
    document.querySelectorAll('.file-input').forEach(input => {
        input.addEventListener('change', function() {
            const fileName = this.files.length > 0 ? this.files[0].name : 'No file selected';
            this.closest('.file-upload-wrapper').querySelector('.file-name').textContent = fileName;
            
            // Validate file size (5MB limit)
            if (this.files.length > 0) {
                const fileSize = this.files[0].size;
                const maxSize = 50 * 1024 * 1024; // 50MB in bytes
                
                if (fileSize > maxSize) {
                    alert('File size must be less than 50MB');
                    this.value = '';
                    this.closest('.file-upload-wrapper').querySelector('.file-name').textContent = 'No file selected';
                }
            }
        });
    });

    // Form validation
    form.addEventListener('submit', function(e) {
        let isValid = true;
        let firstErrorField = null;
        
        // Check required fields
        const requiredFields = form.querySelectorAll('input[required], select[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('error');
                if (!firstErrorField) {
                    firstErrorField = field;
                }
            } else {
                field.classList.remove('error');
            }
        });
        
        // Check radio button groups
        const radioGroups = ['application_type', 'mode_of_payment', 'type_of_business', 'tax_incentive'];
        radioGroups.forEach(groupName => {
            const radios = document.querySelectorAll(`input[name="${groupName}"]`);
            const isChecked = Array.from(radios).some(radio => radio.checked);
            if (!isChecked) {
                isValid = false;
                radios.forEach(radio => radio.classList.add('error'));
                if (!firstErrorField) {
                    firstErrorField = radios[0];
                }
            } else {
                radios.forEach(radio => radio.classList.remove('error'));
            }
        });
        
        // Validate email fields
        const emailFields = form.querySelectorAll('input[type="email"]');
        emailFields.forEach(field => {
            if (field.value && !isValidEmail(field.value)) {
                isValid = false;
                field.classList.add('error');
                if (!firstErrorField) {
                    firstErrorField = field;
                }
            }
        });
        
        // Validate file uploads
        const fileInputs = form.querySelectorAll('input[type="file"][required]');
        fileInputs.forEach(input => {
            if (!input.files || input.files.length === 0) {
                isValid = false;
                input.classList.add('error');
                if (!firstErrorField) {
                    firstErrorField = input;
                }
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert('Please fill in all required fields correctly.');
            if (firstErrorField) {
                firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstErrorField.focus();
            }
        }
    });
    
    // Email validation function
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    // Real-time validation
    form.addEventListener('input', function(e) {
        if (e.target.classList.contains('error') && e.target.value.trim()) {
            e.target.classList.remove('error');
        }
    });
    
    // Auto-format phone numbers
    const phoneFields = form.querySelectorAll('input[name*="telephone"], input[name*="mobile"]');
    phoneFields.forEach(field => {
        field.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 4) {
                    value = value;
                } else if (value.length <= 7) {
                    value = value.slice(0, 4) + '-' + value.slice(4);
                } else if (value.length <= 11) {
                    value = value.slice(0, 4) + '-' + value.slice(4, 7) + '-' + value.slice(7);
                } else {
                    value = value.slice(0, 4) + '-' + value.slice(4, 7) + '-' + value.slice(7, 11);
                }
            }
            this.value = value;
        });
    });
    
    // Auto-format TIN number
    const tinField = document.getElementById('tin_no');
    if (tinField) {
        tinField.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            if (value.length > 0) {
                if (value.length <= 3) {
                    value = value;
                } else if (value.length <= 6) {
                    value = value.slice(0, 3) + '-' + value.slice(3);
                } else if (value.length <= 9) {
                    value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6);
                } else {
                    value = value.slice(0, 3) + '-' + value.slice(3, 6) + '-' + value.slice(6, 9) + '-' + value.slice(9, 12);
                }
            }
            this.value = value;
        });
    }
    
    // Auto-format currency fields
    const currencyFields = form.querySelectorAll('input[name*="rental"], input[name*="cap_"], input[name*="sales_"]');
    currencyFields.forEach(field => {
        field.addEventListener('input', function() {
            let value = this.value.replace(/[^\d.]/g, '');
            if (value && !isNaN(value)) {
                this.value = parseFloat(value).toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }
        });
    });
    
    // Show loading state on form submission
    form.addEventListener('submit', function() {
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
        submitBtn.disabled = true;
    });
});

// Add error styling
const style = document.createElement('style');
style.textContent = `
    .business-permit-form input.error,
    .business-permit-form select.error {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
    
    .business-permit-form input.error:focus,
    .business-permit-form select.error:focus {
        border-color: #dc3545 !important;
        box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25) !important;
    }
`;
document.head.appendChild(style);
</script>