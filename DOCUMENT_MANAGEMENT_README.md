# Advanced Document Management System

## Overview

This implementation adds comprehensive TOR (Transcript of Records), Certificates, Diploma, and Documents management to the TESDA Auto Mechanic Training System with advanced features including version control, verification, audit trails, role-based access control, and automated workflows.

## New Database Tables

Run the SQL migration file: `database_advanced_document_management.sql`

### Core Tables Added

- `document_categories` - Document classification system
- `documents` - Central document repository with versioning
- `document_versions` - Historical versions of documents
- `document_permissions` - Fine-grained access controls
- `document_access_logs` - Comprehensive audit trail
- `document_requests` - Student document request workflow
- `document_request_notes` - Internal notes on requests
- `document_templates` - Document templates with variables
- `staff_department_assignments` - Staff department-based permissions
- `transcripts` - Transcript of Records with GPA calculation
- `transcript_grades` - Individual course grades per transcript
- `transcript_history` - Change tracking for transcripts
- `certificates` - Certificate issuance and management
- `certificate_competencies` - Competencies included in each certificate
- `certificate_history` - Certificate audit trail
- `diplomas` - Diploma records with honors
- `diploma_modules` - Modules included in diploma
- `diploma_history` - Diploma change tracking
- `notifications` - System-wide notifications
- `email_queue` - Outgoing email management

## Admin Features

### 1. Transcript Management (`admin/manage_transcripts.php`)

- Create new transcripts from enrollment data
- Add individual course/module grades
- Auto-calculate GPA
- Bulk operations (issue, approve, archive)
- Version history tracking
- PDF generation placeholder (integrate TCPDF/dompdf)
- Verification code generation
- Audit trail view
- Export to CSV
- Advanced filtering (status, program, batch, date, verification)
- Revocation/recall functionality

### 2. Certificate Management (`admin/manage_certificates.php`)

- Issue competency, NC, and completion certificates
- Select included modules/competencies
- Set validity periods
- Auto-generation of verification codes
- Bulk issue and approval
- Renewal workflow (creates replacement records)
- Revocation with reason tracking
- Optional honors designation
- Filter by type, NC level, program, status, expiry
- Export CSV

### 3. Diploma Management (`admin/manage_diplomas.php`)

- Create diploma records from graduate data
- Honors tracking (Cum Laude, Magna, Summa)
- Batch processing capabilities
- Multi-step workflow: Draft → Approved → Printed → Awarded → Conferred
- Convocation ceremony management
- Replacement tracking (counts replacements)
- PDF generation support
- Module listing per diploma
- Batch approve/print/confer
- GPA and units calculation

### 4. Document Repository (`admin/manage_documents.php`)

- Centralized file storage with categories
- Version control with rollback capability
- Granular permissions (View/Download/Edit/Delete/Share/Admin)
- Confidentiality levels (Public/Internal/Confidential/Restricted)
- Department-based access restrictions for staff
- Audit logs for every access
- Full-text search by title, tags
- Bulk operations (approve, archive, restrict, delete)
- Tagging system
- Expiry date tracking
- Student association (optional)

### 5. Document Request Processing (`staff/staff_document_requests.php`)

- View assigned document requests by department
- Priority-based queue (Urgent → High → Normal → Low)
- Status workflow (Pending → Processing → Ready → Delivered)
- Internal notes (private vs. student-visible)
- Reassignment to other staff
- Quick action buttons

### 6. Reports & Analytics (`admin/reports_documents.php`)

- Date-range filtering
- Issuance trends (daily chart data)
- Certificates by type breakdown
- Diplomas by honors distribution
- Top programs ranking
- Multi-certificate student identification
- Document request status overview
- Aging report for stale drafts
- Expiring certificates alert (90-day window)
- Export to Excel per category

## Student Features

### 1. My Transcripts (`student/transcripts.php`)

- View all issued transcripts
- Download PDF versions
- Online verification button
- Status tracking
- Request new transcripts directly

### 2. My Certificates (`student/certificates.php`)

- View issued certificates
- Download PDFs
- Competency units overview
- Verification check
- Honors display

### 3. My Diplomas (`student/diplomas.php`)

- Diploma status tracking (Draft → Printed → Awarded → Conferred)
- Download awarded diplomas
- View honors and GPA
- Request replacement (for conferred only)
- Convocation details

### 4. Document Requests (`student/request_document.php`)

- Submit new document requests
- Select type, purpose, copies
- Choose collection method (Pickup/Mail/Email)
- Urgent processing toggle
- View past request history and status
- Automatic fee calculation

### 5. Documents Center (`student/documents.php`)

- View all uploaded documents
- Filter by category
- Download access
- Access count display

## Public Verification

- `verify/transcript.php?code=VER-XXXXX` - Independent transcript verification
- `verify/certificate.php?code=CERT-XXXXX` - Certificate authenticity check
- `verify/diploma.php?code=DIP-XXXXX` - Diploma verification portal

All verification pages display:
- Document details
- Student name
- Issue date
- GPA/Honors (where applicable)
- Official "VERIFIED" badge
- Timestamp of verification

## Security & Access Control

### Role-Based Access

- **Admin**: Full access to all modules
- **Registrar Department**: TOR, Diplomas, Document Requests
- **Certification Department**: Certificates only
- **Academic Department**: Certificates, Diplomas
- **Admission**: ID, Registration documents
- **Finance**: Scholarship & registration docs
- **Support Staff / Instructional Unit**: Limited access per assignment

### Permission System

- Document-level permissions via `document_permissions`
- User-type restrictions via `staff_department_assignments`
- Expiry-based access grants
- Active/inactive permission toggles

### Audit Trail

Every action creates a history record:
- `transcript_history` - grade changes, status updates
- `certificate_history` - issue, revoke, renew actions
- `diploma_history` - approval, printing, conferral
- `document_access_logs` - every view/download attempt

IP address and user agent captured.

## Workflows Implemented

### Transcript Workflow

1. Draft created (admin)
2. Grades entered
3. GPA calculated
4. Pending Approval (reviewer)
5. Approved
6. PDF Generated
7. Issued
8. Delivered to student
9. (Optional) Recalled / Superseded

### Certificate Workflow

1. Draft (select student, modules)
2. Filled with data
3. Submitted for Approval
4. Issued (PDF generated)
5. Active
6. Expiry (optional) → Expired
7. (Optional) Revoked or Renewed (creates new record)

### Diploma Workflow

1. Draft (from graduation list)
2. Pending Approval
3. Approved
4. Printed (PDF generated)
5. Awarded (ready for ceremony)
6. Conferred (officially awarded)
7. (Optional) Replaced (creates new diploma #)

### Document Request Workflow

1. Student submits request
2. Pending → Assigned to department
3. Processing (staff updates)
4. Ready for Pickup
5. Delivered
6. (Optional) Cancelled / Rejected

## File Structure

```
project/
├── admin/
│   ├── manage_transcripts.php
│   ├── manage_certificates.php
│   ├── manage_diplomas.php
│   ├── manage_documents.php
│   ├── reports_documents.php
│   └── ajax/
│       ├── get_enrollments.php
│       ├── view_transcript_ajax.php
│       ├── edit_transcript_ajax.php
│       ├── transcript_history_ajax.php
│       ├── view_certificate_ajax.php
│       ├── edit_certificate_ajax.php
│       ├── certificate_history_ajax.php
│       ├── view_diploma_ajax.php
│       ├── edit_diploma_ajax.php
│       └── diploma_history_ajax.php
├── student/
│   ├── transcripts.php
│   ├── certificates.php
│   ├── diplomas.php
│   ├── documents.php
│   └── request_document.php
├── staff/
│   └── staff_document_requests.php
├── verify/
│   ├── transcript.php
│   ├── certificate.php
│   └── diploma.php
├── utils/
│   └── notifications_helper.php
├── css/
│   └── advanced_document_styles.css
├── includes/
│   ├── unified_header.php (modified)
│   └── unified_sidebar.php (modified)
└── database_advanced_document_management.sql
```

## Integration Points

### PDF Generation (Future)

The code includes placeholders for integrating a PDF library:
- Install TCPDF or dompdf via Composer
- Replace file_put_contents($pdfPath, "content") with proper HTML-to-PDF conversion
- Include school logo, signatures, security features

### Email Notifications

The `notifications_helper.php` provides functions:
- `sendDocumentNotification()` - General notification
- `notifyDocumentRequestUpdate()` - Request status changes
- `notifyTranscriptIssued()` - Transcript ready
- `notifyCertificateIssued()` - Certificate ready
- `notifyDiplomaAwarded()` - Diploma awarded

These write to `notifications` and `email_queue` tables. The queue processor (cron job) should call `processEmailQueue()`.

### Existing Database Migration

The tables are additive - they don't modify existing tables. Ensure the database user has CREATE TABLE privileges.

### Session Variables Used

- `$_SESSION['user_id']` - Current user ID
- `$_SESSION['user_type']` - User role (admin, student, support_staff, etc.)
- `$_SESSION['userRole']` - Alternate role field
- `$_SESSION['first_name']`, `$_SESSION['last_name']` - User name

## CSS Classes Reference

### Status Badges

```html
<span class="status-badge status-issued">Issued</span>
<span class="status-badge status-pending">Pending</span>
<span class="status-badge status-approved">Approved</span>
```

### Generic Badges

```html
<span class="badge badge-success">Active</span>
<span class="badge badge-warning">Review</span>
```

### Form Groups

```html
<div class="form-group">
    <label>Label</label>
    <input type="text" name="field">
</div>

<div class="form-row">
    <div class="form-group">...</div>
    <div class="form-group">...</div>
</div>
```

### Cards

```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Title</h3>
        <span class="badge">12</span>
    </div>
    <div class="card-body">
        Content...
    </div>
</div>
```

## Sample Queries

### Count transcripts issued per month

```sql
SELECT DATE_FORMAT(issue_date, '%Y-%m') as month, COUNT(*) as total
FROM transcripts
WHERE status = 'Issued'
GROUP BY month
ORDER BY month DESC;
```

### Get students with no transcripts

```sql
SELECT s.StudID, s.FName, s.LName, s.SchoolID
FROM student s
LEFT JOIN transcripts t ON s.StudID = t.student_id
WHERE t.transcript_id IS NULL;
```

### List expiring certificates (next 90 days)

```sql
SELECT c.*, s.FName, s.LName
FROM certificates c
JOIN student s ON c.student_id = s.StudID
WHERE c.valid_until BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)
  AND c.status = 'Active'
ORDER BY c.valid_until;
```

### Find transcripts with GPA < 3.0

```sql
SELECT t.*, s.FName, s.LName
FROM transcripts t
JOIN student s ON t.student_id = s.StudID
WHERE t.gpa < 3.0 AND t.status != 'Archived';
```

## Future Enhancements

- **Bulk SMS notifications** when documents are ready
- **Digital signature integration** (e.g., DocuSign)
- **Watermarking** on downloaded PDFs
- **QR code embedding** on documents for instant verification
- **Mobile app** push notifications
- **Analytics dashboard** with charts (Chart.js integration)
- **Document templates WYSIWYG editor**
- **Batch print** with cover sheets
- **Integration with external registrars** (send/receive via API)
- **Auto-expiry** for time-limited documents
- **Document checklists** for graduation clearance

## Support

For questions, check the code comments or open an issue at the repository.

---

Generated by Kilo CLI - Advanced Document Management System v1.0
