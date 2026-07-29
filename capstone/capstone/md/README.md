# Resort Management System

A comprehensive PHP-based resort management system with role-based access control for managing bookings, facilities, staff, and maintenance.

## Features

### User Roles

#### 1. Owner
- Add/Remove staff members
- Create/Delete facilities (rooms, cottages, function halls)
- View all bookings and guest information
- Manage facility status and pricing

#### 2. Admin Staff
- Create walk-in bookings for guests
- Approve/Decline online booking requests
- View complete booking history
- Manage guest information

#### 3. Front Desk Staff
- Create walk-in bookings for guests
- Approve/Decline online booking requests
- View booking history
- Handle guest check-ins

#### 4. Supervisor
- Create and manage maintenance tasks
- Update maintenance status (Pending, In Progress, Completed)
- View maintenance history
- Monitor facility status and maintenance needs
- Set maintenance priority levels (Low, Medium, High)

## System Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache/Nginx web server
- Bootstrap 5.1.3 (CDN)
- Font Awesome 6.0 (CDN)

## Installation

### Step 1: Database Setup

1. Open phpMyAdmin (http://localhost/phpmyadmin)
2. Create a new database named `resort_management`
3. Import the `database.sql` file:
   - Go to Import tab
   - Choose `database.sql` file
   - Click Import

### Step 2: Configuration

The system is pre-configured for local development:
- Database Host: `localhost`
- Database User: `root`
- Database Password: (empty)
- Database Name: `resort_management`

If your setup is different, edit `config/db_config.php`

### Step 3: Access the System

1. Navigate to: `http://localhost/capstone/`
2. You will be redirected to login page

## Default Login Credentials

**Username:** owner  
**Password:** owner123

Use these credentials to login as Owner and create additional staff accounts.

## File Structure

```
capstone/
├── config/
│   └── db_config.php          # Database configuration
├── includes/
│   └── functions.php           # Helper functions
├── owner/
│   ├── manage_staff.php        # Manage staff members
│   ├── manage_facilities.php   # Manage facilities
│   └── bookings.php            # View all bookings
├── admin/
│   ├── walkin_booking.php      # Create walk-in bookings
│   ├── online_bookings.php     # Approve/Decline online bookings
│   └── bookings_history.php    # View booking history
├── frontdesk/
│   ├── walkin_booking.php      # Create walk-in bookings
│   ├── online_bookings.php     # Approve/Decline online bookings
│   └── bookings_history.php    # View booking history
├── supervisor/
│   ├── maintenance.php         # Manage maintenance tasks
│   ├── maintenance_history.php # View maintenance history
│   └── facilities.php          # View facilities status
├── login.php                   # Login page
├── dashboard.php               # Main dashboard
├── profile.php                 # User profile
├── logout.php                  # Logout script
├── unauthorized.php            # Unauthorized access page
├── index.php                   # Redirect to login
└── database.sql                # Database schema
```

## Database Tables

### users
- Stores user accounts with roles (owner, admin, frontdesk, supervisor)
- Fields: id, username, password, email, role, first_name, last_name, phone, address, status

### facilities
- Stores resort facilities (rooms, cottages, function halls)
- Fields: id, name, type, description, capacity, max_occupancy, price, amenities, status

### bookings
- Stores guest bookings
- Fields: id, facility_id, guest_name, guest_email, guest_phone, check_in_date, check_out_date, num_guests, booking_type, status, total_price, notes, created_by

### maintenance
- Stores facility maintenance records
- Fields: id, facility_id, maintenance_type, description, status, priority, scheduled_date, completed_date, supervisor_id, notes

## Usage Examples

### Creating a Staff Account (Owner)
1. Login with Owner credentials
2. Click "Manage Staff"
3. Click "Add Staff Member"
4. Fill in required information (First Name, Last Name, Username, Email, Password, Role)
5. Click "Add Staff"

### Creating a Facility (Owner)
1. Login with Owner credentials
2. Click "Manage Facilities"
3. Click "Add Facility"
4. Enter facility details (Name, Type, Capacity, Price, Amenities)
5. Click "Add Facility"

### Creating a Walk-in Booking (Admin/Front Desk)
1. Click "Walk-in Booking"
2. Enter guest information (Name, Email, Phone, Number of Guests)
3. Select facility and dates
4. Total price is calculated automatically
5. Click "Complete Booking"

### Managing Maintenance (Supervisor)
1. Click "Maintenance"
2. Click "Add Maintenance Task"
3. Select facility and enter maintenance details
4. Set priority level and scheduled date
5. Click "Add Task"
6. Update status (Pending → In Progress → Completed) as needed

## Security Features

- Password hashing using SHA-256
- Session-based authentication
- Role-based access control (RBAC)
- SQL injection prevention with prepared statements
- Input validation and sanitization

## User Guide

### Owner Dashboard
- View total bookings, facilities, and active staff
- Quick access to all management functions
- Monitor system-wide statistics

### Admin/Front Desk Dashboard
- View pending online bookings
- Access to booking history
- Quick walk-in booking creation
- Facility availability check

### Supervisor Dashboard
- Pending maintenance tasks display
- Priority-based task management
- Maintenance completion tracking
- Facility status overview

## Common Issues

**Issue:** Database connection error
**Solution:** Verify database name is `resort_management` and credentials in `config/db_config.php` are correct

**Issue:** Pages not loading after login
**Solution:** Ensure PHP sessions are enabled. Check file permissions on the project directory.

**Issue:** Password login fails
**Solution:** Clear browser cache. Ensure default owner account exists in database.

## Support

For issues or questions, please contact the system administrator.

## License

This system is proprietary and for authorized use only.
