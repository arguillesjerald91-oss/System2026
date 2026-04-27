# Advanced Document Management System - Quick Start Guide

## Installation

1. **Import Database Schema**
   ```bash
   mysql -u root -p tesda_auto_mechanic < database_advanced_document_management.sql
   ```
   Or use phpMyAdmin to import the SQL file.

2. **Create Upload Directories**
   ```bash
   mkdir -p uploads/documents
   mkdir -p uploads/transcripts
   mkdir -p uploads/certificates
   mkdir -p uploads/diplomas
   ```

3. **Set Permissions** (Linux/Unix)
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/*/
   ```

4. **No Composer Dependencies** - Pure PHP/PDO. For PDF generation, optionally install:
   ```bash
   composer require dompdf/dompdf  # or tcdf/tcpdf
   ```

## Accessing the System

### Admin Portal
URL: `http://localhost/project/admin/admin_dashboard_new.php`

New menu items under **Academic Records**:
- 📄 Transcripts (TOR) → `manage_transcripts.php`
- 🏆 Certificates → `manage_certificates.php`
- 🎓 Diplomas → `manage_diplomas.php`
- 📁 Document Repository → `manage_documents.php`
- 📈 Reports & Analytics → `reports_documents.php`

### Staff Portal
Staff (support_staff, instructional_unit) automatically see:
- 📂 Document Requests → `staff_document_requests.php`
- (Limited based on department assignment)

### Student Portal
URL: `http://localhost/project/student/student_dashboard_new.php`

New menu items under **Personal Records**:
- 📄 Transcripts → `transcripts.php`
- 🏆 Certificates → `certificates.php`
- 🎓 Diplomas → `diplomas.php`
- 📁 Documents → `documents.php`
- 📤 Request Document → `request_document.php`

## Common Tasks for Admin

### Creating a Transcript (TOR)

1. Navigate: **Academic Records → Transcripts (TOR)**
2. Click **"Create Transcript"** button
3. Select Student → Enrollment → Program
4. Add grades using **"Add Grade"** button
5. GPA auto-calculates
6. Set honors (if any)
7. Click **"Create Transcript"**
8. From the actions column, click **PDF icon** to generate & issue

### Issuing a Certificate

1. Navigate: **Academic Records → Certificates**
2. Click **"Issue Certificate"**
3. Select Student, Certificate Type (NC Certificate, Competency, etc.)
4. Select NC Level (if applicable)
5. Choose included modules/competencies (checkboxes)
6. Set validity period (optional)
7. Click **"Save Certificate"**
8. From actions, click **PDF icon** → Generate & Issue

### Graduation Diploma

1. Navigate: **Academic Records → Diplomas**
2. Click **"Create Diploma"**
3. Select graduated student
4. Enter graduation & convocation dates
5. Set honors (Cum Laude, etc.)
6. General Average (GPA) auto-filled from enrollment
7. Save → Then **Print** → **Confer** in workflow

### Uploading General Documents

1. Navigate: **Academic Records → Document Repository**
2. Click **"Upload Document"**
3. Fill title, description, category
4. Select file (PDF, DOC, JPG, PNG ≤10MB)
5. Set confidentiality level
6. Optionally associate with a student
7. Add tags (comma-separated)
8. Submit

### Processing Student Requests

Staff view: **Document Requests** (automatically filtered by department)

- **Priority queue**: Urgent → High → Normal → Low
- Click **Eye icon** to view details
- Click **Edit icon** to update status
- Status flow: Pending → Processing → Ready for Pickup → Delivered

### Running Reports

Navigate: **Academic Records → Reports & Analytics**

- Select date range
- View issuance trends, certificate types, diploma honors distribution
- Export any section to CSV

## Verification URLs

After issuing documents, verification links are auto-generated:

- Transcript: `verify/transcript.php?code=VER-XXXXX`
- Certificate: `verify/certificate.php?code=CERT-XXXXX`
- Diploma: `verify/diploma.php?code=DIP-XXXXX`

Share these with employers, agencies, or post on student portfolios.

## Staff Department Assignment

To restrict staff access by department, assign them in the `staff_department_assignments` table:

```sql
INSERT INTO staff_department_assignments (user_id, department, assigned_by, is_active)
VALUES (5, 'Registrar', 1, 1);
```

Supported departments:
- `Registrar` — All academic records
- `Certification` — Certificates only
- `Academic` — Certificates + Diplomas
- `Admission` — IDs, Registration docs
- `Finance` — Scholarship, Registration
- `Records` — Transcripts, Diplomas

## Bulk Operations

All management pages support bulk actions:

1. Select items with checkboxes
2. Choose action from dropdown (Issue, Approve, Archive, etc.)
3. Click **Execute**

## Exporting Data

Every admin list has **Export CSV** button in header. Exports include:
- Transcripts: number, student, program, GPA, status, verification
- Certificates: number, student, type, issue date, expiry, honors
- Diplomas: number, student, graduation date, GPA, honors
- Documents: full metadata with access counts

## Audit Trail

All changes are logged:
- **Transcript History** — grade changes, status updates
- **Certificate History** — issue, revoke, renew
- **Diploma History** — approval, print, confer
- **Document Access Logs** — every view/download

View via **History** button in actions column.

## Notifications

When documents are issued/updated:
- In-app notification added to `notifications` table
- Email queued in `email_queue` (requires cron job to process)

Process email queue:
```php
// utils/notifications_helper.php - processEmailQueue($conn)
// Set up cron: */5 * * * * php /path/to/project/utils/process_emails.php
```

## Status Workflows

### Transcript
`Draft` → `Pending Approval` → `Approved` → `Issued` → `Delivered`
- Can be `Recalled` or `Superseded` after issue

### Certificate
`Draft` → `Pending` → `Approved` → `Issued` → `Active`
- Expires → `Expired`
- Can be `Revoked` or `Renewed` (creates replacement)

### Diploma
`Draft` → `Pending Approval` → `Approved` → `Printed` → `Awarded` → `Conferred`
- Can be `Replaced` (for lost/damaged)

### Document Request
`Pending` → `Processing` → `Ready for Pickup` → `Delivered`
- Can be `Cancelled` or `Rejected`

## Customization

### Change Verification URL

In `db.php` or config, set:
```php
$verificationBaseUrl = 'https://yourdomain.edu.ph/verify';
```

Update in all `verify/*.php` files accordingly.

### Add Document Category

```sql
INSERT INTO document_categories (category_name, category_code, requires_approval, allowed_user_types)
VALUES ('Medical Certificate', 'MEDICAL', 1, '["student","admin","support_staff"]');
```

### Modify Template

Edit `document_templates` table or create admin UI for template editing later.

## Security Notes

- All admin pages check: `in_array($userType, ['admin','support_staff','instructional_unit'])`
- Staff department-based filtering applies automatically
- Student can only access own records (`WHERE student_id = ?`)
- IP address logged in all history tables
- File uploads validate extension & MIME (basic)

## Troubleshooting

**"Table not found" error**
→ Ensure you ran the SQL migration: `database_advanced_document_management.sql`

**"Permission denied" on upload**
→ Create directories: `uploads/`, `uploads/documents/`, etc.
→ Set write permissions: `chmod 755 uploads/` (Linux) or ensure writeable (Windows)

**PDF not generating**
→ Placeholder uses `file_put_contents()`. Install TCPDF/dompdf and replace with actual PDF generation.

**Email not sending**
→ Email queue writes to `email_queue` but doesn't auto-send. Set up cron job to call `processEmailQueue()` or implement SMTP.

**Sidebar missing new items**
→ Clear browser cache. Verify you're using `sidebar_new.php` not legacy `sidebar.php`.

**Student can't see documents**
→ Document must have `status = 'Approved'` and `student_id` matching the logged-in student.

## Support

For issues, check:
1. PHP error log
2. Browser console (JS errors)
3. Database connection (`db.php`)

Codebase follows existing patterns from pre_enrollment_management.php and manage_applicants.php.

---

**Version:** 1.0  
**Created:** 2025  
**Compatibility:** PHP 7.4+, MySQL 5.7+, PDO extension
