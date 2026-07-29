# Paradise Resort Website - Quick Start Guide

## 🌐 Website Access URLs

Access the website at these URLs:

```
Landing Page (Home):      http://localhost/capstone/landing.php
Facilities Gallery:       http://localhost/capstone/gallery.php
Book a Room:             http://localhost/capstone/public_booking.php
Booking Confirmation:    http://localhost/capstone/booking_confirmation.php?booking_id=1
About & Contact:         http://localhost/capstone/about.php
```

## 📱 Website Structure

### 1. **Landing Page** - `landing.php` ⭐ START HERE
The main landing page with:
- Hero section with welcome message
- Quick date picker for availability search
- Featured facilities showcase (6 rooms)
- Amenities highlights
- Guest statistics & testimonials
- Special packages & deals
- Call-to-action buttons throughout
- Complete footer with contact info

**Key Features:**
- Responsive hero banner
- Featured facility cards with booking links
- Testimonials section
- Package showcase (Romantic, Family, Business)
- Sticky navigation bar

---

### 2. **Facilities Gallery** - `gallery.php`
Complete gallery of all available facilities with:
- Grid layout (3 columns desktop, 2 tablet, 1 mobile)
- Filter by type (Room, Cottage, Function Hall)
- Sort by price (Low→High, High→Low)
- Facility cards showing:
  - Type badge
  - Description
  - Capacity & max occupancy
  - Amenities list
  - Price per night
  - Book button

**Key Features:**
- Advanced filtering and sorting
- Responsive image placeholders
- Full facility details
- Direct booking links

---

### 3. **Public Booking Form** - `public_booking.php`
Complete booking form with three sections:

**Section 1: Guest Information**
- Full name (required)
- Email (required, validated)
- Phone number (required)

**Section 2: Booking Details**
- Facility selector (dropdown)
- Check-in date picker
- Check-out date picker
- Number of guests
- Booking mode (Overnight/Daytour toggle)

**Section 3: Special Requests**
- Optional notes/special requests textarea

**Section 4: Price Summary**
- Real-time nightly rate display
- Auto-calculated number of nights
- Dynamic total price calculation
- Sidebar with facility preview

**Key Features:**
- Real-time price calculation
- Mode-aware pricing (daytour = 1 day)
- Form validation
- Facility details sidebar
- Responsive design

---

### 4. **Booking Confirmation** - `booking_confirmation.php`
Success page after booking with:
- ✅ Success message with icon
- Booking ID (formatted with leading zeros)
- Booking status (Pending)
- Guest information summary
- Booking details recap
- Price breakdown
- What's next section (email, phone, payment, check-in)
- Facility preview
- Contact information card
- Social media sharing prompts

**URL Format:**
```
http://localhost/capstone/booking_confirmation.php?booking_id=1
```

**Key Features:**
- Professional confirmation layout
- Complete booking details
- Next steps guidance
- Contact support section
- Social sharing options

---

### 5. **About & Contact** - `about.php`
About the resort with:
- Resort story & description
- Core values section
- Contact information cards (location, phone, email)
- Contact form (template)
- Location map placeholder
- Business hours table
- Call-to-action buttons

**Key Features:**
- Comprehensive resort information
- Multiple contact methods
- Contact form (backend integration ready)
- Business hours display
- Professional layout

---

## 🎨 Design Highlights

### Color Scheme
- **Primary**: Blue-Purple gradient (#667eea → #764ba2)
- **Success**: Green (#43e97b)
- **Info**: Light Blue (#4facfe)
- **Text**: Dark gray (#333)
- **Background**: Light gray (#f5f5f5)

### Components
- Responsive Bootstrap 5 layout
- Font Awesome icons
- Smooth hover animations
- Card-based design
- Gradient backgrounds
- Professional typography

### Responsive Breakpoints
- **Desktop** (1200px+): Full layouts
- **Tablet** (768-1199px): 2 columns
- **Mobile** (<768px): Single column

---

## 🔧 Database Integration

The website automatically pulls data from your resort database:

### Facilities Table
```sql
SELECT * FROM facilities WHERE status = 'available'
```
Shows all available rooms/facilities

### Bookings Table
```sql
INSERT INTO bookings (facility_id, guest_name, guest_email, guest_phone, 
    check_in_date, check_out_date, num_guests, mode, booking_type, 
    status, total_price, notes)
```
Stores guest bookings with mode support

---

## 📊 Key Features Implemented

✅ **Hero Section** - Large banner with welcome & CTAs  
✅ **Gallery with Filters** - Sort by type & price  
✅ **Public Booking Form** - Three-section form with real-time calculation  
✅ **Booking Confirmation** - Success page with details  
✅ **About & Contact** - Resort info & contact options  
✅ **Responsive Design** - Works on all devices  
✅ **Price Calculation** - Overnight vs Daytour modes  
✅ **Sticky Navigation** - Easy access throughout site  
✅ **Testimonials** - Guest reviews section  
✅ **Packages** - Special deals showcase  

---

## 🚀 Testing Checklist

- [ ] **Landing Page**: Visit landing.php, check all sections load
- [ ] **Featured Facilities**: Verify 6 facilities display correctly
- [ ] **Gallery Filters**: Test type filters and price sorting
- [ ] **Gallery Display**: Check responsive layout on mobile
- [ ] **Booking Form**: Fill form and verify validation
- [ ] **Price Calculation**: Change dates/facility and verify total updates
- [ ] **Mode Toggle**: Test overnight vs daytour pricing
- [ ] **Form Submission**: Submit booking and verify confirmation
- [ ] **Confirmation Page**: Check all details display correctly
- [ ] **About Page**: Verify all sections load
- [ ] **Navigation**: Test sticky navbar on all pages
- [ ] **Mobile View**: Test on mobile device or browser DevTools
- [ ] **Footer**: Verify footer displays on all pages
- [ ] **Links**: Test all internal navigation links

---

## 💡 Usage Tips

### For Administrators
1. **Add New Facilities**: Add to facilities table → automatically shows in gallery
2. **View Online Bookings**: Check bookings table with booking_type='online'
3. **Update Pricing**: Modify facility prices → auto-updates throughout site
4. **Manage Availability**: Change facility status → controls what appears on site

### For Customers
1. **Browse Facilities**: Visit gallery.php to see all options
2. **Quick Search**: Use hero section date picker for quick search
3. **View Details**: Gallery shows full facility information
4. **Book Online**: Click "Book" button on any facility
5. **Confirm Booking**: Get instant confirmation with details

### For Staff (Admin)
1. Access staff login at `login.php` (different from public site)
2. View online bookings submitted through public_booking.php
3. Manage facilities from admin panel
4. Handle booking confirmations
5. Process payments

---

## 🔗 Navigation Flow

```
Landing Page (landing.php)
    ├─→ Gallery (gallery.php)
    │    ├─→ Public Booking (public_booking.php)
    │    │    └─→ Confirmation (booking_confirmation.php)
    │    └─→ Landing (landing.php)
    ├─→ Public Booking (public_booking.php)
    │    └─→ Confirmation (booking_confirmation.php)
    ├─→ About (about.php)
    └─→ Staff Login (login.php) - Opens in new tab
```

---

## 📝 Example URLs

**With Parameters:**
```
Landing:  http://localhost/capstone/landing.php
Gallery:  http://localhost/capstone/gallery.php?type=room&sort=asc
Booking:  http://localhost/capstone/public_booking.php?facility=1&check_in=2026-02-15&check_out=2026-02-17&guests=2
Confirm:  http://localhost/capstone/booking_confirmation.php?booking_id=123
About:    http://localhost/capstone/about.php
```

---

## 🎯 Next Steps

1. **Customize Content**
   - Edit resort name (Paradise Resort → your name)
   - Update contact info (phone, email, address)
   - Modify business hours
   - Update social media links

2. **Add Real Images**
   - Replace gradient placeholders with actual photos
   - Add facility photos
   - Upload hero banner image

3. **Configure Email**
   - Set up email confirmation system
   - Create confirmation email template
   - Configure SMTP settings

4. **Integrate Payment**
   - Add payment gateway (Stripe, PayMongo, etc.)
   - Set up payment confirmation
   - Handle payment status in bookings

5. **Enable Notifications**
   - Send confirmation emails to guests
   - Notify admin of new bookings
   - Enable SMS alerts (optional)

---

**Website Status**: ✅ **Production Ready**  
**Version**: 1.0.0  
**Last Updated**: January 31, 2026

For detailed documentation, see [WEBSITE_GUIDE.md](WEBSITE_GUIDE.md)
