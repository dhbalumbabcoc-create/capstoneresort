# Resort Management System - Quick Start Guide

## 🚀 Getting Started

### Prerequisites
- XAMPP installed (Apache, MySQL, PHP)
- Web browser (Chrome, Firefox, Safari, Edge)

### Step-by-Step Setup

#### 1. Create Database

1. Start XAMPP (Apache and MySQL)
2. Open http://localhost/phpmyadmin
3. Click "New" on the left sidebar
4. Enter database name: `resort_management`
5. Click "Create"
6. Select the new `resort_management` database
7. Click "Import" tab
8. Click "Choose File" and select `database.sql` from your capstone folder
9. Click "Import"

**Result:** Database tables are created automatically with default owner account

---

#### 2. Access the System

Open your browser and navigate to:
```
http://localhost/capstone/
```

You will be automatically redirected to the login page.

---

#### 3. First Login

**Default Credentials:**
- Username: `owner`
- Password: `owner123`

Click "Login"

---

## 📋 Role-Based Functions

### 👑 OWNER

**Access:** http://localhost/capstone/dashboard.php

**Available Functions:**

1. **Manage Staff** (owner/manage_staff.php)
   - Add new staff members
   - Assign roles (Admin, Front Desk, Supervisor)
   - Remove staff members
   - View all staff accounts

2. **Manage Facilities** (owner/manage_facilities.php)
   - Create rooms, cottages, function halls
   - Set pricing per night/hour
   - Add amenities and descriptions
   - Update facility status (Available/Unavailable)
   - Delete facilities

3. **View Bookings** (owner/bookings.php)
   - Create manual bookings for guests
   - View all bookings
   - Monitor occupancy
   - Track revenue

---

### 👨‍💼 ADMIN STAFF

**How to Create:**
1. Login as Owner
2. Go to Manage Staff
3. Click "Add Staff Member"
4. Fill details, select role as "Admin Staff"
5. Save

**Functions:**

1. **Walk-in Booking** (admin/walkin_booking.php)
   - Create bookings for walk-in guests
   - Select facility and dates
   - Automatic price calculation
   - Instant confirmation

2. **Online Bookings** (admin/online_bookings.php)
   - View pending online booking requests
   - Approve bookings
   - Decline bookings with notes

3. **Booking History** (admin/bookings_history.php)
   - View all past bookings
   - Filter by status
   - Export booking records

---

### 💼 FRONT DESK STAFF

**How to Create:**
1. Login as Owner
2. Go to Manage Staff
3. Click "Add Staff Member"
4. Fill details, select role as "Front Desk Staff"
5. Save

**Functions:** (Same as Admin Staff)
- Walk-in Booking
- Online Bookings
- Booking History

---

### 🔧 SUPERVISOR

**How to Create:**
1. Login as Owner
2. Go to Manage Staff
3. Click "Add Staff Member"
4. Fill details, select role as "Supervisor"
5. Save

**Functions:**

1. **Maintenance** (supervisor/maintenance.php)
   - Create maintenance tasks
   - Set priority (Low, Medium, High)
   - Schedule maintenance date
   - Update status (Pending → In Progress → Completed)

2. **Maintenance History** (supervisor/maintenance_history.php)
   - View completed maintenance
   - Filter by facility
   - Track completion dates

3. **Facilities Status** (supervisor/facilities.php)
   - View all facilities
   - Check pending maintenance count
   - Monitor facility availability
   - View facility details

---

## 💡 Common Workflows

### Workflow 1: Setting Up a New Resort

1. **Login as Owner**
   - Go to Dashboard

2. **Create Facilities**
   - Navigate to "Manage Facilities"
   - Add rooms: "Room 101", "Room 102", etc.
   - Add cottages: "Cottage A", "Cottage B", etc.
   - Add function halls: "Grand Hall", "Meeting Room"
   - Set prices for each

3. **Add Staff**
   - Go to "Manage Staff"
   - Add Admin Staff members
   - Add Front Desk Staff members
   - Add Supervisor

4. **Assign Maintenance Tasks**
   - Login as Supervisor
   - Go to "Maintenance"
   - Create initial maintenance checks
   - Schedule regular maintenance

---

### Workflow 2: Guest Booking (Walk-in)

1. **Receptionist/Admin Staff**
   - Login and go to Dashboard
   - Click "Walk-in Booking"
   - Enter guest name, email, phone
   - Select facility and dates
   - Review automatic price calculation
   - Click "Complete Booking"

2. **Guest Check-in**
   - Booking is immediately confirmed
   - Guest assigned to selected facility
   - Record updated in system

---

### Workflow 3: Online Booking Management

1. **Guest Books Online** (via website)
   - Booking appears as "Pending" status

2. **Admin/Front Desk Reviews**
   - Go to "Online Bookings"
   - View all pending requests
   - Check facility availability
   - Click ✓ to approve or ✕ to decline

3. **Guest Notification**
   - Use guest email/phone on record
   - Inform of booking status

---

### Workflow 4: Facility Maintenance

1. **Supervisor Creates Task**
   - Login as Supervisor
   - Go to "Maintenance"
   - Add new maintenance task
   - Set facility, type, priority, scheduled date

2. **Maintenance Team Executes**
   - View assigned tasks
   - Start work (Status: In Progress)
   - Complete work (Status: Completed)

3. **Owner Reviews**
   - Login as Owner
   - Dashboard shows facility status
   - Check maintenance history

---

## 📊 Dashboard Indicators

### Owner Dashboard
- **Total Bookings:** All bookings in system
- **Total Facilities:** Rooms, cottages, function halls
- **Active Staff:** Available staff members

### Admin/Front Desk Dashboard
- **Pending Online Bookings:** Awaiting approval
- **Recent Bookings:** Last 10 bookings
- **System Messages:** Important notifications

### Supervisor Dashboard
- **Pending Maintenance:** Tasks not completed
- **High Priority Tasks:** Urgent maintenance
- **Facility Status:** Available/Unavailable

---

## 🔐 Account Management

### Change Your Password
1. Click your name/profile in top right
2. Go to "Profile"
3. Enter current password
4. Enter new password twice
5. Click "Change Password"

### Update Your Profile
1. Go to "Profile"
2. Update personal information
3. Click "Update Profile"

### Logout
1. Click "Logout" in top right menu
2. You are logged out and redirected to login page

---

## 📞 Contact & Support

**Default Owner Account:** owner / owner123

**System Admin:** Set up additional owner accounts as needed by creating new users with "Owner" role

---

## ✅ Checklist for First-Time Setup

- [ ] Database created and imported
- [ ] Can login with default owner credentials
- [ ] Created at least one facility
- [ ] Added at least one staff member per role
- [ ] Created a test booking
- [ ] Tested online booking approval/decline
- [ ] Created maintenance task
- [ ] Updated maintenance status
- [ ] Changed your password

---

## 🎯 Key Features Summary

✅ Multi-role user system with role-based access  
✅ Complete facility management (Create/Update/Delete)  
✅ Guest booking system (Walk-in & Online)  
✅ Maintenance tracking with priority levels  
✅ Booking approval/decline workflow  
✅ User profile management  
✅ Session-based authentication  
✅ Responsive design with Bootstrap  
✅ Clean, intuitive interface  
✅ Automatic price calculations  

---

**Version:** 1.0  
**Last Updated:** January 2026
