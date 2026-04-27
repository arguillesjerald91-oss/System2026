-- =====================================================
-- Seed Data: Pre-Enrollment Sample Applications (Module 1)
-- =====================================================
-- This file inserts sample pre-enrollment applications with various
-- statuses (Pending, Under Review, Qualified, Not Qualified, etc.)
-- for testing the admin management interface.
-- =====================================================

-- NOTE: Run this AFTER running the main schema creation and migration 005.
-- Ensure the `users` table has an admin with user_id = 1, or adjust reviewed_by values.

INSERT INTO pre_enrollment_applications (
    application_number, first_name, last_name, middle_name, birth_date, gender,
    contact_number, email_address, complete_address, barangay, city_municipality, province, postal_code,
    civil_status, citizenship, highest_educational_attainment, school_last_attended, year_graduated,
    employment_status, monthly_income, preferred_training_schedule, preferred_start_date,
    has_previous_tesda_training, previous_tesa_course, reason_for_applying,
    emergency_contact_name, emergency_contact_relationship, emergency_contact_number,
    application_status, submission_date, review_date, interview_date, assessment_date, remarks,
    reviewed_by, reviewed_at
) VALUES
-- 1. Qualified (Approved)
(
    'APP-2025-0001', 'Juan', 'Dela Cruz', 'M.', '1998-05-15', 'Male',
    '09171234567', 'juan.delacruz1@example.com', '123 Main St., Brgy. San Antonio', 'San Antonio', 'Makati City', 'Metro Manila', '1200',
    'Single', 'Filipino', 'College Graduate', 'University of the Philippines', 2020,
    'Unemployed', NULL, 'Morning', '2025-06-01',
    0, NULL, 'I want to pursue a career in automotive servicing and enhance my technical skills.',
    'Maria Dela Cruz', 'Mother', '09179876543',
    'Qualified', '2025-09-15 09:00:00', '2025-09-20 10:00:00', NULL, NULL, 'Excellent academic background and strong motivation.',
    1, '2025-09-20 10:00:00'
),
-- 2. Not Qualified (Rejected)
(
    'APP-2025-0002', 'Maria', 'Santos', 'L.', '1995-08-22', 'Female',
    '09182345678', 'maria.santos@example.com', '456 Rizal Ave., Brgy. Santa Ana', 'Santa Ana', 'Manila', 'Metro Manila', '1000',
    'Married', 'Filipino', 'High School', 'Santa Ana High School', 2013,
    'Employed', 12000.00, 'Afternoon', '2025-07-01',
    0, NULL, 'To gain certification and improve job prospects.',
    'Jose Santos', 'Husband', '09191234567',
    'Not Qualified', '2025-09-16 11:30:00', '2025-09-21 14:00:00', NULL, NULL, 'Does not meet educational attainment requirement for the program.',
    1, '2025-09-21 14:00:00'
),
-- 3. Pending (just submitted)
(
    'APP-2025-0003', 'Pedro', 'Reyes', 'S.', '2000-03-10', 'Male',
    '09193456789', 'pedro.reyes@example.com', '789 Mabini St., Brgy. San Isidro', 'San Isidro', 'Quezon City', 'Metro Manila', '1100',
    'Single', 'Filipino', 'College Undergraduate', 'Quezon City University', NULL,
    'Student', NULL, 'Evening', '2025-09-01',
    0, NULL, 'To acquire automotive skills while studying.',
    'Ana Reyes', 'Sister', '09194567890',
    'Pending', '2025-10-01 08:00:00', NULL, NULL, NULL, NULL,
    NULL, NULL
),
-- 4. Under Review
(
    'APP-2025-0004', 'Ana', 'Garcia', 'P.', '1997-11-05', 'Female',
    '09195678901', 'ana.garcia@example.com', '1010 Del Monte Ave., Brgy. Del Monte', 'Del Monte', 'Quezon City', 'Metro Manila', '1105',
    'Single', 'Filipino', 'Vocational', 'TESDA Accredited Center', 2019,
    'Self-Employed', 18000.00, 'Weekend', '2025-08-15',
    1, 'Electrical Installation NC II', 'To formalize my automotive knowledge with a TESDA NC II.',
    'Luis Garcia', 'Brother', '09196789012',
    'Under Review', '2025-09-20 14:00:00', NULL, NULL, NULL, 'Document verification in progress.',
    NULL, NULL
),
-- 5. Waitlisted
(
    'APP-2025-0005', 'Roberto', 'Mendoza', 'T.', '1985-07-30', 'Male',
    '09197890123', 'roberto.mendoza@example.com', '2020 EDSA, Brgy. Cubao', 'Cubao', 'Quezon City', 'Metro Manila', '1109',
    'Married', 'Filipino', 'High School', 'Cubao High School', 2003,
    'Employed', 25000.00, 'Morning', '2025-10-01',
    0, NULL, 'To shift careers and become a certified auto mechanic.',
    'Susan Mendoza', 'Wife', '09198901234',
    'Waitlisted', '2025-09-10 10:00:00', '2025-09-25 16:30:00', NULL, NULL, 'Slots full; placed on waitlist for next batch.',
    1, '2025-09-25 16:30:00'
),
-- 6. Enrolled
(
    'APP-2025-0006', 'Carmen', 'Lopez', 'R.', '2002-02-14', 'Female',
    '09199012345', 'carmen.lopez@example.com', '3030 Taft Ave., Brgy. Malate', 'Malate', 'Manila', 'Metro Manila', '1004',
    'Single', 'Filipino', 'College Undergraduate', 'De La Salle University', NULL,
    'Student', NULL, 'Afternoon', '2025-06-15',
    0, NULL, 'To gain practical automotive skills for future employment.',
    'Elena Lopez', 'Aunt', '09190123456',
    'Enrolled', '2025-08-01 11:00:00', '2025-08-05 09:00:00', '2025-08-03 10:00:00', '2025-08-10 14:00:00', 'Completed enrollment for Batch 2026-A.',
    1, '2025-08-05 09:00:00'
),
-- 7. Submitted (awaiting initial review)
(
    'APP-2025-0007', 'Daniel', 'Torres', 'F.', '1993-09-25', 'Male',
    '09191234567', 'daniel.torres@example.com', '4040 Shaw Blvd., Brgy. Wack-Wack', 'Wack-Wack', 'Mandaluyong City', 'Metro Manila', '1550',
    'Single', 'Filipino', 'College Graduate', 'Ateneo de Manila University', 2015,
    'Employed', 35000.00, 'Evening', '2025-09-15',
    0, NULL, 'To supplement my engineering background with hands-on auto skills.',
    'Megan Torres', 'Sister', '09192345678',
    'Submitted', '2025-10-05 13:00:00', NULL, NULL, NULL, NULL,
    NULL, NULL
),
-- 8. Draft (incomplete/not yet submitted)
(
    'APP-2025-0008', 'Elena', 'Cruz', 'G.', '2005-04-18', 'Female',
    '09193456789', 'elena.cruz@example.com', '5050 Chino Roces Ave., Brgy. Pio del Pilar', 'Pio del Pilar', 'Makati City', 'Metro Manila', '1200',
    'Single', 'Filipino', 'High School', 'Makati High School', 2023,
    'Student', NULL, 'Morning', NULL,
    0, NULL, 'To start my career in auto mechanics right after high school.',
    'Jose Cruz', 'Father', '09194567890',
    'Draft', NULL, NULL, NULL, NULL, NULL,
    NULL, NULL
),
-- 9. For Interview
(
    'APP-2025-0009', 'Luis', 'Navarro', 'H.', '1990-12-03', 'Male',
    '09195678901', 'luis.navarro@example.com', '6060 Ortigas Ave., Brgy. Greenhills', 'Greenhills', 'San Juan', 'Metro Manila', '1500',
    'Single', 'Filipino', 'College Graduate', 'University of Santo Tomas', 2012,
    'Employed', 40000.00, 'Evening', '2025-11-01',
    1, 'Automotive Servicing NC I', 'To obtain NC II certification for career advancement.',
    'Isabella Navarro', 'Wife', '09196789012',
    'For Interview', '2025-10-02 09:00:00', '2025-10-06 11:00:00', '2025-10-10 10:00:00', NULL, 'Shortlisted for interview.',
    1, '2025-10-06 11:00:00'
);

-- =====================================================
-- End of Sample Data
-- =====================================================
