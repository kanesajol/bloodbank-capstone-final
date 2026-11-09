# Blood Collection and Consumption Reporting Management System

## System Overview

This document is a Markdown version of the provided system flow/documentation for the **Blood Collection and Consumption Reporting Management System**. It covers the landing page, login, dashboard, donor management, blood consumption, reports, and staff accounts.

## 1. Landing Page and Login

- Landing page title: **Blood Collection and Consumption Reporting Management System**
- Login page
- Dashboard

## 2. Dashboard

### 2.1 Staff Profile / Account

Each staff account contains profile information such as:

- Profile information
- Job title (example: Nurse 2)
- Shift schedule
- Other staff/account information

## 3. Blood Collection

The **Blood Collection** module is used to record, search, and manage blood donor information and donation history.

### 3.1 Search Donor

Staff can search for a donor using the donor's name.

When a donor is found, the system displays:

- Full Name
- Blood Type
- Age
- Gender
- Barangay
- Municipality/Location
- Contact Information
- Total Number of Donations
- Donation History

The donation history must show the specific date of every donation.

Example:

| Date | Blood Type | Location |
|---|---|---|
| January 15, 2026 | O+ | Tubajon |
| April 20, 2026 | O+ | Tubajon |
| August 14, 2026 | O+ | Tubajon |

### 3.2 Search Donors by Location

Staff can search donors based on:

- Barangay
- Municipality
- Location

Example:

**Search:** Tubajon

The system displays all donors associated with that location.

Example results:

- Juan Dela Cruz — O+
- Maria Santos — A+
- Pedro Garcia — B+
- Ana Cruz — O-

Staff can click a donor's name to view the complete donor information and donation history.

### 3.3 Search by Location and Year

Staff can filter donor records by location and year.

Example:

- Location: Tubajon
- Year: 2026

The system displays donors who have donation records within the selected year.

Records can be organized by month from January to December.

Example:

#### January 2026

- Juan Dela Cruz — January 15
- Maria Santos — January 20

#### March 2026

- Pedro Garcia — March 10

#### August 2026

- Juan Dela Cruz — August 14

The exact date, month, and year must remain visible.

## 4. Register a Donor

This function is used when a person donates for the first time or does not yet have a record in the system.

The staff records:

- Full Name
- Blood Type
- Age
- Gender
- Barangay
- Municipality/Location
- Contact Information
- Initial Donation Date

### 4.1 Returning Donor

If the donor has already donated before, staff should **not** create another donor profile.

Instead, the workflow is:

**Search Existing Donor → Open Donor Profile → Add New Donation**

The system then adds the new donation to the donor's existing history.

Example:

**Juan Dela Cruz**

- Total Donations: 3

Donation History:

- January 15, 2026
- April 20, 2026
- August 14, 2026 — new update

The search function should also allow the donor information to be updated and reflected automatically in the Blood Collection module.

## 5. Blood Consumption

The **Blood Consumption** module records consolidated blood consumption or blood requirement reports submitted by municipalities, hospitals, or other authorized locations.

No patient names or other personal information are required. The system only records the summarized blood-related information needed for monitoring and reporting.

### 5.1 Add Consumption Record

Staff records:

- Location
- Blood Type
- Number of Bags
- Reporting Period
- Date Submitted

Example:

- Location: Tubajon
- Blood Type: O+
- Number of Bags: 10
- Reporting Period: August 2026
- Date Submitted: August 31, 2026

The system saves the record and automatically includes it in the appropriate consumption reports.

### 5.2 Search Consumption Records

Staff can search or filter records by:

- Location
- Blood Type
- Month
- Year
- Reporting Period

Example:

- Location: Tubajon
- Year: 2026

The system can display:

| Month | Blood Type | Number of Bags |
|---|---|---:|
| January | O+ | 8 |
| February | O+ | 12 |
| March | A+ | 5 |
| April | B+ | 7 |
| August | O+ | 10 |

The system can automatically calculate the total number of bags by:

- Blood type
- Location
- Month
- Year

## 6. Reports

Reports should be automatically generated from records already stored in the system.

### Basic Workflow

**Staff enters data → System stores data → System calculates → System generates reports**

### 6.1 Monthly Donor Report

The system generates a list and statistics of donors for each month.

The report may include:

- Total Donors
- Donor Names
- Blood Types
- Locations
- Donation Dates

### 6.2 Quarterly New Donor Report

The system automatically counts new donors for every three-month period.

Example:

| Quarter | Period | New Donors |
|---|---|---:|
| Q1 2026 | January–March | 125 |
| Q2 2026 | April–June | 143 |
| Q3 2026 | July–September | 118 |

### 6.3 Yearly Donor Report

The system can generate annual statistics such as:

- Total Donors
- Total Donations
- New Donors
- Donors by Location
- Donors by Blood Type
- Donors by Age
- Donors by Gender

### 6.4 Donor Age Report

The system can categorize donors by age group.

Example:

| Age Group | Number of Donors |
|---|---:|
| 16–25 | 80 |
| 26–35 | 65 |
| 36–45 | 42 |
| 46–55 | 20 |
| 56+ | 5 |

### 6.5 Gender Report

The system automatically counts donors by gender.

Example for 2026:

- Male: 120
- Female: 180

Staff can also filter the gender report by month.

### 6.6 Blood Consumption Report

Consumption reports show only aggregated information:

- Location
- Blood Type
- Number of Bags
- Reporting Period
- Date Submitted

The consumption reporting module must **not** include:

- Patient name
- Patient age
- Patient gender
- Other patient-identifying information

## 7. Staff Accounts

Different staff members work different shifts, so every staff member should have their own account.

Example:

### Staff A

- Logged in: 7:00 AM

### Staff B

- Logged in: 3:00 PM

The system should automatically record staff information and shift schedules.

Example shift records:

| Staff | Shift |
|---|---|
| Kane Sajol | Morning Shift — 6:00 AM–2:00 PM |
| John Doe | Afternoon Shift — 2:00 PM–10:00 PM |
| Jumel Alipao | Midnight Shift — 10:00 PM–6:00 AM |

## 8. Main System Flow

```text
Landing Page
     ↓
Login
     ↓
Dashboard
     ├── Staff Profile / Account
     ├── Blood Collection
     │     ├── Search Donor
     │     ├── Search by Location
     │     ├── Search by Location and Year
     │     ├── Register New Donor
     │     └── Add New Donation to Existing Donor
     │
     ├── Blood Consumption
     │     ├── Add Consumption Record
     │     ├── Search / Filter Records
     │     └── Calculate Consumption Totals
     │
     ├── Reports
     │     ├── Monthly Donor Report
     │     ├── Quarterly New Donor Report
     │     ├── Yearly Donor Report
     │     ├── Donor Age Report
     │     ├── Gender Report
     │     └── Blood Consumption Report
     │
     └── Staff Accounts
           └── Staff Shift / Login Records
```

## 9. Important Data Rules

1. A returning donor must use their existing donor profile.
2. A new donation must be added to the donor's existing donation history.
3. Donation dates must retain the exact date, month, and year.
4. Donor records must be searchable by name and location.
5. Donor records must be filterable by location and year.
6. Blood consumption records must contain aggregated information only.
7. Blood consumption reports must not contain patient-identifying information.
8. Reports should be generated from stored system records rather than requiring duplicate data entry.
9. Each staff member should have an individual account.
10. Staff shift information should be recorded automatically where applicable.
