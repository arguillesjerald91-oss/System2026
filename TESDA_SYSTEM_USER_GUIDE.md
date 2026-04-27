# TESDA Auto Mechanic Training Centre - Complete User Guide

## Table of Contents
1. [System Overview](#system-overview)
2. [Getting Started](#getting-started)
3. [User Types and Access Levels](#user-types-and-access-levels)
4. [Step-by-Step Navigation Guide](#step-by-step-navigation-guide)
5. [Feature-Specific Instructions](#feature-specific-instructions)
6. [Troubleshooting](#troubleshooting)

---

## System Overview

The TESDA Auto Mechanic Training Centre system is a comprehensive web-based platform designed to manage:
- Student enrollment and applications
- Training modules and assessments
- User authentication and access control
- Scholarship applications
- Administrative functions

**System Architecture:** PHP-based web application with MySQL database backend
**Main Entry Point:** `index.php` (Landing Page)

---

## Getting Started

### Prerequisites
- Modern web browser (Chrome, Firefox, Safari, Edge)
- Active internet connection
- Valid email address (for applications and 2FA)
- User credentials (for existing users)

### Accessing the System
1. Open your web browser
2. Navigate to the system URL (e.g., `http://localhost/tesda/` or your domain)
3. You will land on the main page (`index.php`)

---

## User Types and Access Levels

### 1. **Guest/New Applicant**
- **Access:** Landing page, pre-enrollment, scholarship application
- **Features:** Apply for enrollment, submit scholarship applications

### 2. **Student/Trainee**
- **Access:** Personal dashboard, courses, grades, training materials
- **Features:** View modules, submit assessments, track progress

### 3. **Instructor**
- **Access:** Course management, student progress, assessment tools
- **Features:** Create modules, grade students, manage class schedules

### 4. **Instructional Unit**
- **Access:** Program oversight, curriculum management
- **Features:** Supervise training programs, manage curriculum

### 5. **Support Staff**
- **Access:** Administrative functions, user management
- **Features:** Process applications, manage records

### 6. **Administrator**
- **Access:** Full system control
- **Features:** User management, system configuration, reports

---

## Step-by-Step Navigation Guide

### 🏠 **FROM LANDING PAGE (index.php)**

#### **For New Users/Applicants**

**Step 1: Pre-Enrollment Application**
1. On the landing page, click **"Start Pre-Enrollment"**
2. Fill out the application form:
   - Personal Information (name, birth date, contact details)
   - Address Information (complete address, barangay, city, province)
   - Educational Background (highest education, school attended)
   - Employment Details (status, income)
   - Training Preferences (schedule, start date)
   - Emergency Contact Information
3. Review all entered information
4. Click **"Submit Application"**
5. Save your application number for future reference
6. Wait for email confirmation/application status

**Step 2: Scholarship Application**
1. On the landing page, navigate to **Scholarship** section
2. Click **"Apply for Scholarship"**
3. Complete the scholarship form:
   - Personal details (auto-populated if logged in)
   - Financial information
   - Scholarship type preferences
   - Supporting documents upload
4. Submit and await review

**Step 3: Login to Portal**
1. On the landing page, click **"Login to Portal"**
2. Select your user type (Student, Trainee, Instructor, etc.)
3. Enter username/email and password
4. If 2FA is enabled, check email for verification code
5. Enter 6-digit code when prompted
6. You will be redirected to your specific dashboard

#### **For Existing Users**

**Step 1: Access Login**
1. Click **"Login to Portal"** on the landing page
2. Choose your user type from the tabs
3. Enter credentials
4. Complete 2FA if required
5. Access your dashboard

---

### 📊 **USER DASHBOARD NAVIGATION**

#### **Student/Trainee Dashboard**

**Main Features Access:**
1. **My Courses**
   - View enrolled courses
   - Access training materials
   - Track progress
   - View grades and certificates

2. **Learning Modules**
   - Browse available modules
   - Start new modules
   - Continue in-progress modules
   - Submit assessments

3. **Profile Management**
   - Update personal information
   - Change password
   - View enrollment history

4. **Messages/Notifications**
   - Check announcements
   - View instructor messages
   - Access system notifications

**Step-by-Step Course Access:**
1. From dashboard, click **"My Courses"**
2. Select desired course
3. Click **"Enter Course"**
4. Navigate through modules using the sidebar
5. Complete lessons in order
6. Take assessments when ready
7. Submit and wait for grading

#### **Instructor Dashboard**

**Main Features Access:**
1. **Course Management**
   - Create new courses
   - Edit existing courses
   - Upload materials
   - Set up assessments

2. **Student Management**
   - View enrolled students
   - Track student progress
   - Grade assessments
   - Send messages

3. **Schedule Management**
   - Set class schedules
   - Manage training sessions
   - Update calendar

**Step-by-Step Course Creation:**
1. From dashboard, click **"Create Course"**
2. Fill in course details:
   - Course title and description
   - Duration and requirements
   - Target audience
3. Add modules and lessons
4. Upload training materials
5. Create assessments
6. Set enrollment requirements
7. Publish the course

#### **Admin Dashboard**

**Main Features Access:**
1. **User Management**
   - Create user accounts
   - Manage permissions
   - View user activity
   - Reset passwords

2. **System Configuration**
   - Configure system settings
   - Manage database
   - Set up permissions
   - Monitor system health

3. **Reports and Analytics**
   - Generate enrollment reports
   - View system statistics
   - Export data
   - Monitor performance

---

## Feature-Specific Instructions

### 📝 **Pre-Enrollment Process**

**Detailed Steps:**
1. **Application Initiation**
   - Click "Start Pre-Enrollment" from landing page
   - Generate CSRF token for security
   - Start application form

2. **Personal Information Section**
   - First Name (required)
   - Last Name (required)
   - Middle Name (optional)
   - Birth Date (required, must be 16+ years)
   - Gender (required)
   - Contact Number (required)
   - Email Address (required, must be valid)

3. **Address Information**
   - Complete Address (required)
   - Barangay (required)
   - City/Municipality (required)
   - Province (required)
   - Postal Code (optional)

4. **Educational Background**
   - Civil Status (required)
   - Citizenship (defaults to Filipino)
   - Highest Educational Attainment (required)
   - School Last Attended (optional)
   - Year Graduated (optional, valid range: 1950-current year)

5. **Employment Information**
   - Employment Status (required)
   - Monthly Income (optional)
   - Previous TESDA Training (yes/no)
   - Previous Course (if applicable)

6. **Training Preferences**
   - Preferred Training Schedule (required)
   - Preferred Start Date (optional)
   - Reason for Applying (required)

7. **Emergency Contact**
   - Contact Name (required)
   - Relationship (required)
   - Contact Number (required)

8. **Submission**
   - Review all information
   - Accept terms and conditions
   - Submit application
   - Receive application number

### 🔐 **Login and Authentication**

**Standard Login Process:**
1. Navigate to login page
2. Select user type tab
3. Enter username/email
4. Enter password
5. Click "Sign In"

**Two-Factor Authentication (if enabled):**
1. After successful password verification
2. Check email for 6-digit code
3. Enter code in verification field
4. Code expires in 5 minutes
5. Option to resend code if needed

**Login Troubleshooting:**
- Forgot password: Use "Forgot Password?" link
- Account locked: Contact administrator
- Invalid credentials: Check username/password and user type

### 📚 **Course Navigation**

**For Students:**
1. Access course from dashboard
2. View course outline
3. Complete lessons sequentially
4. Take assessments
5. Track progress indicators
6. Download certificates upon completion

**For Instructors:**
1. Create course structure
2. Upload materials
3. Set up assessments
4. Monitor student progress
5. Grade submissions
6. Provide feedback

### 💰 **Scholarship Application**

**Application Steps:**
1. Login to student portal (or apply as guest)
2. Navigate to Scholarship section
3. Select scholarship type
4. Complete financial information
5. Upload required documents:
   - Proof of income
   - Academic records
   - Recommendation letters
6. Submit application
7. Track application status

---

## Troubleshooting

### Common Issues and Solutions

**Login Problems:**
- **Issue:** Invalid username/password
  - **Solution:** Check credentials, ensure correct user type selected
- **Issue:** Account locked
  - **Solution:** Contact administrator or wait for lockout period
- **Issue:** 2FA code not working
  - **Solution:** Request new code, check email spam folder

**Application Issues:**
- **Issue:** Form validation errors
  - **Solution:** Complete all required fields, check email format
- **Issue:** Duplicate application
  - **Solution:** Use same email, system will detect existing application

**Technical Issues:**
- **Issue:** Page not loading
  - **Solution:** Check internet connection, clear browser cache
- **Issue:** Database connection error
  - **Solution:** Contact system administrator

### Contact Support

For technical assistance:
- **Email:** support@tesda.gov.ph
- **Phone:** [Support Number]
- **Office Hours:** Monday-Friday, 8:00 AM - 5:00 PM

### System Requirements

**Minimum Requirements:**
- Modern web browser (Chrome 80+, Firefox 75+, Safari 13+)
- Stable internet connection (1 Mbps+)
- Screen resolution: 1024x768 or higher
- JavaScript enabled

**Recommended Requirements:**
- High-speed internet (5 Mbps+)
- Latest browser version
- Screen resolution: 1920x1080

---

## Quick Reference Guide

### Navigation Shortcuts
- **Home:** Click logo or "Home" link
- **Login:** "Login to Portal" button
- **Pre-Enrollment:** "Start Pre-Enrollment" button
- **Dashboard:** Available after login
- **Logout:** Top-right corner logout button

### Important URLs
- **Landing Page:** `/index.php`
- **Login:** `/login/index.php`
- **Pre-Enrollment:** `/pre_enrollment.php`
- **Scholarship:** `/scholarship_application.php`
- **Student Dashboard:** `/student/student_dashboard.php`
- **Admin Dashboard:** `/admin/admin_dashboard.php`

### User Type Quick Access
| User Type | Dashboard Path | Main Features |
|-----------|---------------|---------------|
| Student | `/student/` | Courses, Grades, Materials |
| Trainee | `/student/` | Training Modules, Assessments |
| Instructor | `/instructor/` | Course Management, Grading |
| Admin | `/admin/` | User Management, System Config |
| Support | `/support/` | Administrative Functions |
| Instructional Unit | `/instructional_unit/` | Program Oversight |

---

**Last Updated:** April 18, 2026  
**System Version:** 1.0.0  
**Document Version:** 1.0

For the most current information and updates, please check the system announcements or contact the support team.
