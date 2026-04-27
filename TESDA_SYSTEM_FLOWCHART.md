# TESDA Auto Mechanic Training Centre - System Flowcharts

## Table of Contents
1. [Overall System Architecture Flowchart](#overall-system-architecture-flowchart)
2. [User Authentication Flowchart](#user-authentication-flowchart)
3. [Pre-Enrollment Process Flowchart](#pre-enrollment-process-flowchart)
4. [User Dashboard Navigation Flowchart](#user-dashboard-navigation-flowchart)
5. [Course Management Flowchart](#course-management-flowchart)
6. [Scholarship Application Flowchart](#scholarship-application-flowchart)
7. [Database Operations Flowchart](#database-operations-flowchart)

---

## Overall System Architecture Flowchart

```mermaid
graph TD
    A[User Access] --> B{Landing Page}
    B --> C[New Applicant]
    B --> D[Existing User]
    
    C --> E[Pre-Enrollment]
    C --> F[Scholarship Application]
    C --> G[Login Portal]
    
    D --> H[Authentication]
    H --> I{User Type?}
    
    I -->|Student/Trainee| J[Student Dashboard]
    I -->|Instructor| K[Instructor Dashboard]
    I -->|Admin| L[Admin Dashboard]
    I -->|Support Staff| M[Support Dashboard]
    I -->|Instructional Unit| N[Unit Dashboard]
    
    J --> O[Course Access]
    J --> P[Grades View]
    J --> Q[Profile Management]
    
    K --> R[Course Management]
    K --> S[Student Grading]
    K --> T[Schedule Management]
    
    L --> U[User Management]
    L --> V[System Configuration]
    L --> W[Reports Generation]
    
    style A fill:#e1f5fe
    style B fill:#f3e5f5
    style I fill:#fff3e0
```

---

## User Authentication Flowchart

```mermaid
flowchart TD
    Start([Start]) --> Access[Access Login Page]
    Access --> SelectType{Select User Type}
    
    SelectType -->|Student| StudentType[Student Login]
    SelectType -->|Trainee| TraineeType[Trainee Login]
    SelectType -->|Instructor| InstructorType[Instructor Login]
    SelectType -->|Admin| AdminType[Admin Login]
    SelectType -->|Support| SupportType[Support Login]
    SelectType -->|Unit| UnitType[Unit Login]
    
    StudentType --> Credentials[Enter Credentials]
    TraineeType --> Credentials
    InstructorType --> Credentials
    AdminType --> Credentials
    SupportType --> Credentials
    UnitType --> Credentials
    
    Credentials --> Validate[Validate User]
    Validate -->|Success| Check2FA{2FA Enabled?}
    Validate -->|Failed| Error[Invalid Credentials]
    Error --> Credentials
    
    Check2FA -->|Yes| SendCode[Send 2FA Code]
    Check2FA -->|No| DirectLogin[Direct Login]
    
    SendCode --> EnterCode[Enter 6-Digit Code]
    EnterCode --> VerifyCode[Verify Code]
    VerifyCode -->|Valid| CompleteLogin
    VerifyCode -->|Invalid| CodeError[Invalid Code]
    CodeError --> ResendCode{Resend?}
    ResendCode -->|Yes| SendCode
    ResendCode -->|No| EnterCode
    
    DirectLogin --> CompleteLogin[Complete Authentication]
    CompleteLogin --> Dashboard[Redirect to Dashboard]
    
    style Start fill:#4caf50
    style Dashboard fill:#2196f3
    style Error fill:#f44336
```

---

## Pre-Enrollment Process Flowchart

```mermaid
flowchart TD
    Start([Start Application]) --> Form[Application Form]
    
    Form --> Personal[Personal Information]
    Personal --> Address[Address Details]
    Address --> Education[Educational Background]
    Education --> Employment[Employment Info]
    Employment --> Training[Training Preferences]
    Training --> Emergency[Emergency Contact]
    
    Emergency --> Validate[Validate All Fields]
    Validate -->|Missing Fields| Errors[Display Errors]
    Errors --> Form
    
    Validate -->|All Valid| CheckEmail{Email Exists?}
    CheckEmail -->|Yes| Duplicate[Duplicate Application Error]
    Duplicate --> Form
    CheckEmail -->|No| GenerateApp[Generate Application Number]
    
    GenerateApp --> SaveToDB[Save to Database]
    SaveToDB --> SendConfirmation[Send Email Confirmation]
    SendConfirmation --> Success[Application Submitted]
    
    Success --> TrackStatus[Track Application Status]
    TrackStatus --> AdminReview[Admin Review]
    AdminReview --> Decision{Application Decision}
    
    Decision -->|Approved| Approved[Approve Application]
    Decision -->|Rejected| Rejected[Reject Application]
    Decision -->|Need More Info| RequestInfo[Request Additional Info]
    
    Approved --> Enroll[Proceed to Enrollment]
    Rejected --> Notify[Notify Applicant]
    RequestInfo --> Form
    
    style Start fill:#4caf50
    style Success fill:#2196f3
    style Errors fill:#f44336
    style Approved fill:#8bc34a
```

---

## User Dashboard Navigation Flowchart

```mermaid
graph TD
    Login([User Logged In]) --> Dashboard[User Dashboard]
    
    Dashboard --> UserType{User Type}
    
    UserType -->|Student/Trainee| StudentFlow[Student Features]
    UserType -->|Instructor| InstructorFlow[Instructor Features]
    UserType -->|Admin| AdminFlow[Admin Features]
    UserType -->|Support| SupportFlow[Support Features]
    UserType -->|Unit| UnitFlow[Unit Features]
    
    StudentFlow --> Courses[My Courses]
    StudentFlow --> Grades[View Grades]
    StudentFlow --> Materials[Training Materials]
    StudentFlow --> Profile[Profile Management]
    
    InstructorFlow --> CourseMgmt[Course Management]
    InstructorFlow --> StudentMgmt[Student Management]
    InstructorFlow --> Grading[Grade Assessments]
    InstructorFlow --> Schedule[Class Schedule]
    
    AdminFlow --> UserMgmt[User Management]
    AdminFlow --> SystemConfig[System Configuration]
    AdminFlow --> Reports[Reports & Analytics]
    AdminFlow --> Database[Database Management]
    
    SupportFlow --> Applications[Process Applications]
    SupportFlow --> Records[Manage Records]
    SupportFlow --> HelpDesk[Help Desk]
    
    UnitFlow --> Programs[Program Oversight]
    UnitFlow --> Curriculum[Curriculum Management]
    UnitFlow --> Quality[Quality Assurance]
    
    Courses --> CourseDetail[Course Details]
    Grades --> GradeView[Grade Details]
    Materials --> MaterialView[Material Access]
    Profile --> ProfileEdit[Edit Profile]
    
    CourseMgmt --> CourseCreate[Create Course]
    StudentMgmt --> StudentList[Student List]
    Grading --> GradeAssign[Assign Grades]
    Schedule --> ScheduleEdit[Edit Schedule]
    
    style Login fill:#4caf50
    style Dashboard fill:#2196f3
```

---

## Course Management Flowchart

```mermaid
flowchart TD
    Start([Course Management]) --> Action{Select Action}
    
    Action -->|Create| CreateCourse[Create New Course]
    Action -->|Edit| EditCourse[Edit Existing Course]
    Action -->|Delete| DeleteCourse[Delete Course]
    Action -->|View| ViewCourse[View Course Details]
    
    CreateCourse --> CourseForm[Course Information Form]
    CourseForm --> ValidateCourse[Validate Course Data]
    ValidateCourse -->|Invalid| CourseError[Display Errors]
    CourseError --> CourseForm
    ValidateCourse -->|Valid| SaveCourse[Save Course to DB]
    SaveCourse --> AddModules[Add Modules]
    AddModules --> UploadMaterials[Upload Materials]
    UploadMaterials --> CreateAssessments[Create Assessments]
    CreateAssessments --> Publish[Publish Course]
    
    EditCourse --> SelectCourse[Select Course to Edit]
    SelectCourse --> LoadCourse[Load Course Data]
    LoadCourse --> CourseForm
    
    DeleteCourse --> ConfirmDelete{Confirm Deletion?}
    ConfirmDelete -->|No| ViewCourse
    ConfirmDelete -->|Yes| RemoveCourse[Remove from DB]
    RemoveCourse --> Success[Deletion Success]
    
    ViewCourse --> CourseDetails[Display Course Info]
    CourseDetails --> StudentList[Enrolled Students]
    CourseDetails --> CourseStats[Course Statistics]
    
    style Start fill:#4caf50
    style Publish fill:#8bc34a
    style CourseError fill:#f44336
    style Success fill:#2196f3
```

---

## Scholarship Application Flowchart

```mermaid
flowchart TD
    Start([Scholarship Application]) --> LoginCheck{User Logged In?}
    
    LoginCheck -->|No| GuestLogin[Login as Guest]
    LoginCheck -->|Yes| UserDashboard[User Dashboard]
    
    GuestLogin --> ScholarshipForm[Scholarship Application Form]
    UserDashboard --> ScholarshipForm
    
    ScholarshipForm --> PersonalInfo[Personal Information]
    PersonalInfo --> FinancialInfo[Financial Information]
    FinancialInfo --> AcademicInfo[Academic Records]
    AcademicInfo --> Documents[Upload Documents]
    
    Documents --> ValidateApp[Validate Application]
    ValidateApp -->|Missing Data| AppErrors[Display Errors]
    AppErrors --> ScholarshipForm
    ValidateApp -->|Complete| SubmitApp[Submit Application]
    
    SubmitApp --> ReviewProcess[Review Process]
    ReviewProcess --> EligibilityCheck{Eligibility Check}
    
    EligibilityCheck -->|Not Eligible| RejectApp[Reject Application]
    EligibilityCheck -->|Eligible| DocumentReview[Document Verification]
    
    DocumentReview -->|Incomplete| RequestDocs[Request Additional Documents]
    RequestDocs --> Documents
    DocumentReview -->|Complete| CommitteeReview[Scholarship Committee Review]
    
    CommitteeReview --> Decision{Final Decision}
    Decision -->|Award| AwardScholarship[Award Scholarship]
    Decision -->|Waitlist| Waitlist[Add to Waitlist]
    Decision -->|Reject| FinalReject[Final Rejection]
    
    AwardScholarship --> NotifyAward[Notify Student]
    FinalReject --> NotifyReject[Notify Student]
    
    style Start fill:#4caf50
    style AwardScholarship fill:#8bc34a
    style AppErrors fill:#f44336
    style FinalReject fill:#ff9800
```

---

## Database Operations Flowchart

```mermaid
flowchart TD
    Start([Database Operation]) --> Operation{Operation Type}
    
    Operation -->|Read| ReadOp[Read Operation]
    Operation -->|Write| WriteOp[Write Operation]
    Operation -->|Update| UpdateOp[Update Operation]
    Operation -->|Delete| DeleteOp[Delete Operation]
    
    ReadOp --> ConnectDB[Establish Connection]
    ConnectDB --> PrepareQuery[Prepare SQL Query]
    PrepareQuery --> BindParams[Bind Parameters]
    BindParams --> ExecuteRead[Execute Query]
    ExecuteRead --> FetchResults[Fetch Results]
    FetchResults --> CloseRead[Close Connection]
    CloseRead --> ReturnData[Return Data]
    
    WriteOp --> ConnectWrite[Establish Connection]
    ConnectWrite --> PrepareInsert[Prepare INSERT]
    PrepareInsert --> BindInsert[Bind Parameters]
    BindInsert --> ExecuteInsert[Execute INSERT]
    ExecuteInsert --> CheckSuccess{Success?}
    CheckSuccess -->|No| InsertError[Rollback Transaction]
    CheckSuccess -->|Yes| CommitInsert[Commit Transaction]
    CommitInsert --> CloseWrite[Close Connection]
    InsertError --> CloseWrite
    CloseWrite --> ReturnInsert[Return Status]
    
    UpdateOp --> ConnectUpdate[Establish Connection]
    ConnectUpdate --> PrepareUpdate[Prepare UPDATE]
    PrepareUpdate --> BindUpdate[Bind Parameters]
    BindUpdate --> ExecuteUpdate[Execute UPDATE]
    ExecuteUpdate --> CheckUpdate{Rows Affected?}
    CheckUpdate -->|0| NoUpdate[No Records Updated]
    CheckUpdate -->|>0| SuccessUpdate[Update Successful]
    SuccessUpdate --> CloseUpdate[Close Connection]
    NoUpdate --> CloseUpdate
    CloseUpdate --> ReturnUpdate[Return Status]
    
    DeleteOp --> ConnectDelete[Establish Connection]
    ConnectDelete --> PrepareDelete[Prepare DELETE]
    PrepareDelete --> BindDelete[Bind Parameters]
    BindDelete --> ExecuteDelete[Execute DELETE]
    ExecuteDelete --> CheckDelete{Rows Affected?}
    CheckDelete -->|0| NoDelete[No Records Deleted]
    CheckDelete -->|>0| SuccessDelete[Delete Successful]
    SuccessDelete --> CloseDelete[Close Connection]
    NoDelete --> CloseDelete
    CloseDelete --> ReturnDelete[Return Status]
    
    style Start fill:#4caf50
    style ReturnData fill:#2196f3
    style InsertError fill:#f44336
    style SuccessUpdate fill:#8bc34a
    style SuccessDelete fill:#ff9800
```

---

## Program Flow Summary

### **Main Entry Points:**
1. **Landing Page** (`index.php`) - System entry point
2. **Login Portal** (`login/index.php`) - Authentication gateway
3. **Pre-Enrollment** (`pre_enrollment.php`) - Application process
4. **Scholarship** (`scholarship_application.php`) - Financial aid

### **User Journey Paths:**
- **New User:** Landing → Pre-Enrollment → Login → Dashboard
- **Existing User:** Landing → Login → Dashboard → Features
- **Admin:** Landing → Login → Admin Dashboard → System Management

### **Key Decision Points:**
- User type selection during login
- Application validation checkpoints
- Authentication with/without 2FA
- Course access permissions
- Scholarship eligibility checks

### **Data Flow:**
1. **Input:** User forms and interactions
2. **Validation:** Server-side validation and sanitization
3. **Processing:** Business logic execution
4. **Storage:** Database operations
5. **Output:** Results and user feedback

---

## Technical Implementation Notes

### **Security Flow:**
- CSRF token generation and validation
- Input sanitization and parameter binding
- Password hashing with PHP's `password_hash()`
- Two-factor authentication implementation
- Session management and timeout

### **Error Handling:**
- Database connection failures
- Form validation errors
- Authentication failures
- File upload errors
- System exception handling

### **Performance Considerations:**
- Database connection pooling
- Query optimization
- Caching strategies
- Resource cleanup
- Transaction management

---

**Last Updated:** April 18, 2026  
**System Version:** 1.0.0  
**Flowchart Version:** 1.0

For technical implementation details, refer to the consolidated source code file: `CRUCIAL_SOURCE_CODE_CONSOLIDATED.php`
