# Resort Management System - Architecture Overview

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    USER LOGIN                                   │
│                   (login.php)                                   │
└──────────────┬──────────────────────────────────────────────────┘
               │
               ├─→ SESSION AUTHENTICATION
               │   (includes/functions.php)
               │
               ▼
    ┌──────────────────────────────────────────┐
    │      ROLE-BASED ACCESS CONTROL           │
    │     (4 User Roles with Different)        │
    │        Permissions & Features             │
    └──────────────────────────────────────────┘
               │
        ┌──────┼──────┬──────────┬──────────┐
        ▼      ▼      ▼          ▼          ▼
    ┌─────┐┌──────┐┌────────┐┌──────────┐
    │Owner││Admin ││Front   ││Supervisor│
    │     ││Staff ││Desk    ││          │
    └─────┘└──────┘└────────┘└──────────┘
        │      │      │          │
        │      │      │          │
    Facility  Booking Booking  Maintenance
    Management Management  Management Management
```

## Database Schema

```
┌─────────────────────────────────────────────────────────────────┐
│                       MYSQL DATABASE                             │
│                  resort_management                               │
└─────────────────────────────────────────────────────────────────┘
                              │
        ┌─────────────────────┼─────────────────────┐
        ▼                     ▼                     ▼
    ┌────────────┐        ┌──────────────┐    ┌──────────────┐
    │   USERS    │        │  FACILITIES  │    │   BOOKINGS   │
    ├────────────┤        ├──────────────┤    ├──────────────┤
    │ id (PK)    │        │ id (PK)      │    │ id (PK)      │
    │ username   │        │ name         │    │ facility_id  │
    │ password   │        │ type         │    │ guest_name   │
    │ email      │        │ price        │    │ check_in     │
    │ role       │        │ capacity     │    │ check_out    │
    │ status     │        │ status       │    │ status       │
    │ created_at │        │ created_at   │    │ booking_type │
    └────────────┘        └──────────────┘    └──────────────┘
                                │                    │
                                │                    └────────┐
                                │                             │
                                ▼                             ▼
                          ┌──────────────┐         ┌─────────────────┐
                          │ MAINTENANCE  │         │  (Foreign Keys) │
                          ├──────────────┤         │                 │
                          │ id (PK)      │         │ facility_id → FK│
                          │ facility_id  │         │ created_by → FK │
                          │ maintenance_ │         └─────────────────┘
                          │ type         │
                          │ status       │
                          │ priority     │
                          │ supervisor_id│
                          └──────────────┘
```

## Application File Structure

```
capstone/
│
├── 📁 config/
│   └── db_config.php ..................... Database connection
│
├── 📁 includes/
│   └── functions.php ..................... Helper functions & utilities
│
├── 📁 owner/
│   ├── manage_staff.php .................. Add/Remove staff
│   ├── manage_facilities.php ............. Create/Update/Delete facilities
│   └── bookings.php ...................... View all bookings
│
├── 📁 admin/
│   ├── walkin_booking.php ................ Create walk-in bookings
│   ├── online_bookings.php ............... Approve/Decline online bookings
│   └── bookings_history.php .............. View booking records
│
├── 📁 frontdesk/
│   ├── walkin_booking.php ................ Create walk-in bookings
│   ├── online_bookings.php ............... Approve/Decline online bookings
│   └── bookings_history.php .............. View booking records
│
├── 📁 supervisor/
│   ├── maintenance.php ................... Create/Update maintenance tasks
│   ├── maintenance_history.php ........... View maintenance history
│   └── facilities.php .................... View facility status
│
├── login.php ............................. Login page
├── dashboard.php ......................... Main dashboard (role-aware)
├── profile.php ........................... User profile & password change
├── logout.php ............................ Logout handler
├── unauthorized.php ...................... Access denied page
├── index.php ............................. Redirects to login
│
├── database.sql .......................... Database schema
├── README.md ............................. Full documentation
└── QUICK_START.md ........................ Quick setup guide
```

## User Roles & Permissions

### 1. OWNER
```
Dashboard Access:        ✅ Full
Staff Management:        ✅ Add/Remove/View
Facility Management:     ✅ Create/Update/Delete/Status Change
Booking Management:      ✅ Create/View All/Approve
Maintenance:            ⚠️ View only (Not create/update)
Reports:                ✅ Full access
```

### 2. ADMIN STAFF
```
Dashboard Access:        ✅ Limited
Staff Management:        ❌ None
Facility Management:     ⚠️ View only
Booking Management:      ✅ Create Walk-in/Approve-Decline Online
Maintenance:            ❌ None
Reports:                ✅ Booking history only
```

### 3. FRONT DESK STAFF
```
Dashboard Access:        ✅ Limited
Staff Management:        ❌ None
Facility Management:     ⚠️ View only
Booking Management:      ✅ Create Walk-in/Approve-Decline Online
Maintenance:            ❌ None
Reports:                ✅ Booking history only
```

### 4. SUPERVISOR
```
Dashboard Access:        ✅ Limited
Staff Management:        ❌ None
Facility Management:     ⚠️ View only
Booking Management:      ❌ None
Maintenance:            ✅ Create/Update/View History
Reports:                ✅ Maintenance only
```

## Feature Workflow Diagrams

### Workflow 1: Staff Management (Owner)
```
Owner Login
    ↓
Manage Staff
    ├─ Add Staff
    │  └─ Enter Details → Assign Role → Save → Account Created
    │
    └─ Remove Staff
       └─ Select Staff → Mark Inactive → Staff Cannot Login
```

### Workflow 2: Booking Management (Admin/Front Desk)
```
Walk-in Booking Flow:
Guest Arrives
    ↓
Admin/Front Desk Login
    ↓
Walk-in Booking Form
    ├─ Enter Guest Details
    ├─ Select Facility
    ├─ Select Dates
    ├─ Auto-Calculate Price
    └─ Confirm Booking

Online Booking Flow:
Guest Books via Website
    ↓
Booking Status: PENDING
    ↓
Admin/Front Desk Reviews
    ├─ Approve → Status: CONFIRMED
    └─ Decline → Status: DECLINED
    ↓
Guest Notification (Email/Phone)
```

### Workflow 3: Maintenance Management (Supervisor)
```
Maintenance Creation:
Supervisor Login
    ↓
Create Maintenance Task
    ├─ Select Facility
    ├─ Enter Type & Description
    ├─ Set Priority (Low/Medium/High)
    ├─ Schedule Date
    └─ Save → Status: PENDING

Task Execution:
Maintenance Staff
    ├─ View Pending Tasks
    ├─ Start Work → Status: IN_PROGRESS
    ├─ Complete Work
    └─ Update Status → COMPLETED (Auto-timestamp)

Monitoring:
Supervisor Reviews
    ├─ Current Tasks (Dashboard)
    ├─ Maintenance History
    └─ Facility Status with Pending Count
```

## Data Flow

### Login Process
```
User Submits Form
    ↓
Validate Input
    ↓
Query Database (username match)
    ↓
Verify Password (SHA-256)
    ├─ Match → Create Session → Redirect to Dashboard
    └─ No Match → Show Error → Redirect to Login
```

### Booking Creation
```
User Submits Booking
    ↓
Validate Guest Info
    ↓
Check Facility Availability
    ↓
Calculate Total Price (nights × price_per_night)
    ↓
Insert into Database
    ├─ Walk-in → Status: CONFIRMED (Immediate)
    └─ Online → Status: PENDING (Approval Required)
```

### Maintenance Status Update
```
Supervisor Updates Status
    ↓
Validate New Status
    ├─ If COMPLETED → Add timestamp
    └─ Otherwise → Clear timestamp
    ↓
Update Database
    ↓
Redirect with Success Message
```

## Security Layers

```
User Input
    ↓
Input Validation
    ├─ Escape special characters
    ├─ Validate data types
    └─ Check field requirements
    ↓
Prepared Statements
    ├─ Prevent SQL Injection
    └─ Bind parameters safely
    ↓
Password Security
    ├─ Hash with SHA-256
    └─ Verify on login
    ↓
Session Authentication
    ├─ Check session exists
    └─ Verify user role
    ↓
Role-Based Access Control
    ├─ Check user role
    ├─ Verify permissions
    └─ Redirect if unauthorized
    ↓
Database
```

## Technology Stack

```
Frontend:
  • HTML5 ......................... Markup
  • CSS3 .......................... Styling
  • Bootstrap 5.1.3 ............... Framework (CDN)
  • JavaScript .................... Interactivity
  • Font Awesome 6.0 .............. Icons (CDN)

Backend:
  • PHP 7.4+ ...................... Server-side language
  • Session Management ............ User sessions
  • Prepared Statements ........... Database security

Database:
  • MySQL 5.7+ .................... Relational database
  • InnoDB ........................ Storage engine

Server:
  • Apache/Nginx .................. Web server
  • XAMPP ......................... Local development
```

## Key Features

✨ **Multi-User System**
  - 4 distinct user roles
  - Role-based dashboard
  - Customized menu per role

🔐 **Security**
  - SHA-256 password hashing
  - SQL injection prevention
  - Session-based authentication
  - Input validation & sanitization

📅 **Booking System**
  - Walk-in bookings (instant)
  - Online bookings (approval required)
  - Automatic price calculation
  - Booking status tracking

🏢 **Facility Management**
  - Multiple facility types
  - Dynamic pricing
  - Availability status
  - Amenities tracking

🔧 **Maintenance Tracking**
  - Priority-based tasks
  - Status workflow
  - Completion tracking
  - History reports

👥 **Staff Management**
  - Add/Remove staff
  - Role assignment
  - Status management
  - Account deactivation

---

**System Version:** 1.0  
**Build Date:** January 2026  
**Status:** Production Ready
