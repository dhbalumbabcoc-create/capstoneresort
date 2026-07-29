# Booking & Authentication Changes

## Summary
This document outlines the recent updates to remove password hashing and enhance the booking system with mode selection (overnight/daytour) and receipt generation.

## 1. Password Authentication Changes
**Objective**: Remove hashing for development/testing convenience

### Files Modified:
- **includes/functions.php**
  - `hash_password($password)`: Changed to return plain text instead of SHA-256 hash
  - `verify_password($password, $hash)`: Changed to direct string comparison instead of hash verification

- **login.php** (modified in earlier iteration)
  - Login verification now uses direct string comparison for plain-text passwords

- **owner/manage_staff.php**
  - Staff password creation now stores plain text instead of hashing

- **profile.php**
  - Password change functionality now stores plain text instead of hashing

- **database.sql**
  - Default owner account password changed to plain text: `'owner123'`

## 2. Booking System Enhancements
**Objective**: Add booking mode (overnight/daytour) and implement receipt generation

### Database Changes (database.sql):
- Added new column to `bookings` table:
  - `mode ENUM('overnight','daytour') DEFAULT 'overnight'`
  - Existing column `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`

### New Files Created:
- **receipt.php** - Printable receipt generator
  - Displays guest information, booking details, and price breakdown
  - Supports print functionality via browser print dialog
  - Shows:
    - Booking ID and status
    - Guest Information (name, email, phone)
    - Booking Details (facility, mode, dates, duration, guest count)
    - Receipt/Pricing table with itemized breakdown

- **staff/booking.php** - Staff booking form
  - Complete booking form with all three sections:
    1. Guest Information (name, email, phone, guest count)
    2. Booking Details (facility selection, mode selection, check-in/check-out dates)
    3. Receipt (auto-calculated total price with dynamic calculation based on mode)
  - Automatically redirects to receipt.php after successful booking creation

### Modified Booking Files:

#### admin/walkin_booking.php
- Added `mode` input field to booking form
- Updated INSERT statement to capture `mode` parameter
- Updated bind_param to include `mode` binding
- Redirects to receipt.php after booking creation instead of showing success message

#### owner/bookings.php
- Added `mode` dropdown input to modal form
- Updated INSERT statement to capture `mode` parameter
- Updated bind_param to include `mode` binding
- Redirects to receipt.php after booking creation

#### frontdesk/online_bookings.php & admin/online_bookings.php
- No changes needed (these only approve/decline existing bookings)

## 3. Booking Price Calculation Logic
- **Overnight Mode**: Total = Price × Number of Nights (calculated from check-in to check-out)
- **Daytour Mode**: Total = Price × 1 (fixed at 1 day regardless of dates)

## 4. Key Features

### Receipt Generation
- Accessible at: `receipt.php?booking_id={id}`
- Display includes:
  - Guest information (name, email, phone)
  - Booking details (facility, mode, dates, duration)
  - Itemized receipt with rate, quantity, and amount
  - Created by user ID and timestamp
  - Print button for easy printing
- Requires login authentication

### Three-Part Booking Form Structure
All booking forms now follow this structure:
1. **Guest Information Section**
   - Guest Name (required)
   - Guest Email (optional)
   - Guest Phone (optional)
   - Number of Guests (required)

2. **Booking Details Section**
   - Facility Selection (required)
   - Booking Mode: Overnight/Daytour (required)
   - Check-in Date (required)
   - Check-out Date (required)
   - Total Price (auto-calculated, read-only)

3. **Receipt Section**
   - Automatically generated after booking creation
   - Printable format with all relevant information

## 5. Testing Checklist
- [ ] Login with plain-text password (owner123)
- [ ] Create a staff member with plain-text password
- [ ] Change password using plain-text storage
- [ ] Create walk-in booking as admin/frontdesk
- [ ] Create booking as owner
- [ ] Create booking as staff member
- [ ] Verify receipt displays correctly with overnight mode
- [ ] Verify receipt displays correctly with daytour mode
- [ ] Print receipt from browser
- [ ] Verify price calculation: overnight (nights × price) vs daytour (1 × price)

## 6. Database Migration
If deploying to existing database, run:
```sql
-- Only if mode column doesn't exist
ALTER TABLE bookings ADD COLUMN mode ENUM('overnight','daytour') DEFAULT 'overnight';
```

## 7. Security Notes
⚠️ **WARNING**: Plain-text password storage is NOT suitable for production. This change was made for development/testing convenience only. For production deployment:
- Implement proper password hashing (bcrypt, argon2)
- Use SSL/TLS for all connections
- Implement proper access controls and logging
- Use secure session management
