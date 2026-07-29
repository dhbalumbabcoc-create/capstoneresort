# Paradise Resort - Website Gallery & Booking System

## 📋 Overview

A comprehensive, responsive website gallery and public booking system for Paradise Resort. This modern, user-friendly website showcases your resort's facilities, amenities, and enables online bookings with a complete customer journey from discovery to confirmation.

## 🎨 Website Features

### 1. **Landing Page** (`landing.php`)
- **Hero Section**: Eye-catching banner with welcome message and CTAs
- **Quick Date Picker**: Fast search for availability
- **Featured Facilities**: Showcase 6 highlighted rooms/facilities
- **Amenities Section**: Visual display of resort amenities (Pool, Spa, Dining, Fitness)
- **Statistics**: Show number of facilities, happy guests, ratings
- **Testimonials**: Guest reviews and 5-star ratings
- **Special Packages & Deals**: 3 featured packages (Romantic, Family, Business)
- **Call-to-Action**: Sticky booking prompts throughout
- **Footer**: Complete contact info, quick links, social media

### 2. **Gallery Page** (`gallery.php`)
- **Responsive Grid Layout**: 3-column layout on desktop, 2 on tablet, 1 on mobile
- **Filter by Type**: Filter facilities by room/cottage/function hall
- **Sort Options**: Sort by price (low-to-high, high-to-low, default)
- **Facility Cards**: Show:
  - Type, capacity, max occupancy
  - Description and amenities
  - Price per night
  - Book button with direct booking link
- **Interactive UI**: Hover effects and smooth transitions
- **Mobile Optimized**: Touch-friendly interface

### 3. **Public Booking Form** (`public_booking.php`)
Three-section booking form:

**Guest Information Section:**
- Full name (required)
- Email (required, with validation)
- Phone number (required)

**Booking Details Section:**
- Facility selection dropdown
- Check-in date picker
- Check-out date picker
- Number of guests selector
- Booking mode (Overnight/Daytour)

**Receipt Section:**
- Real-time price calculation
- Display nightly rate, number of nights, total price
- Currency formatting (₱)
- Special requests text area

**Sidebar:**
- Selected facility preview
- Facility details and amenities
- Price display

### 4. **Booking Confirmation Page** (`booking_confirmation.php`)
- ✅ Success message with checkmark icon
- **Booking Details Card**: Full summary of reservation
- **Price Summary**: Breakdown of costs
- **What's Next Section**: Information about:
  - Confirmation email
  - Phone contact
  - Payment methods
  - Check-in details
- **Facility Preview**: Selected room/facility display
- **Contact Information**: Quick access to support

### 5. **About & Contact Page** (`about.php`)
- **About Section**: Resort story and description
- **Core Values**: Excellence, Guest First, Sustainability
- **Contact Information Cards**: Location, phone, email
- **Contact Form**: Name, email, subject, message
- **Location Map**: (Placeholder for Google Maps integration)
- **Business Hours**: Operating hours for all facilities
- **Social Media Links**: Facebook, Instagram, Twitter, YouTube

## 🎯 Key Features

### User Experience
- ✅ **Responsive Design**: Mobile, tablet, desktop optimization
- ✅ **Smooth Scrolling**: Gentle navigation between sections
- ✅ **Loading States**: Visual feedback during submissions
- ✅ **Form Validation**: Client and server-side validation
- ✅ **Real-time Price Calculation**: Dynamic pricing based on dates and mode
- ✅ **Accessibility**: WCAG compliant with proper labels and ARIA attributes

### Functionality
- ✅ **Dynamic Content**: Pulls facilities from database
- ✅ **Booking Status**: Shows pending/confirmed status
- ✅ **Mode Support**: Overnight stays and day tours
- ✅ **Price Formatting**: Currency display with proper formatting
- ✅ **Email Integration**: Ready for confirmation email setup
- ✅ **Modal Forms**: Smooth overlay-based interactions

### Performance
- ✅ **Optimized Assets**: CSS and JS files are minified-ready
- ✅ **Bootstrap Framework**: Fast, responsive, reliable
- ✅ **Font Awesome Icons**: Professional icon library
- ✅ **Lazy Loading Ready**: Images optimized for web

## 📁 File Structure

```
capstone/
├── landing.php                 # Main landing page
├── gallery.php                 # Facilities gallery with filters
├── public_booking.php          # Public booking form
├── booking_confirmation.php    # Booking confirmation page
├── about.php                   # About & contact page
├── assets/
│   ├── css/
│   │   └── style.css           # Main stylesheet (700+ lines)
│   └── js/
│       └── script.js           # Main JavaScript utilities
├── config/
│   └── db_config.php           # Database configuration
└── [other system files]
```

## 🛠 Technologies Used

- **Backend**: PHP with MySQLi
- **Frontend**: HTML5, CSS3, JavaScript
- **Framework**: Bootstrap 5.1.3
- **Icons**: Font Awesome 6.0.0
- **Database**: MySQL/MariaDB

## 🚀 Getting Started

### Prerequisites
- PHP 7.4+
- MySQL/MariaDB
- Web server (Apache, Nginx, etc.)
- XAMPP or similar stack

### Installation

1. **Setup Database**:
   - The booking system uses existing `bookings` and `facilities` tables
   - Ensure mode column exists: `ALTER TABLE bookings ADD COLUMN mode ENUM('overnight','daytour') DEFAULT 'overnight';`

2. **Access Website**:
   - Landing Page: `http://localhost/capstone/landing.php`
   - Gallery: `http://localhost/capstone/gallery.php`
   - Book Now: `http://localhost/capstone/public_booking.php`
   - About: `http://localhost/capstone/about.php`
   - Admin Panel: `http://localhost/capstone/login.php`

3. **Configuration**:
   - Database connection in `config/db_config.php`
   - Contact details in footer sections (phone, email, address)
   - Social media links in footer

## 📱 Responsive Breakpoints

- **Desktop**: 1200px+ (Full 3-column layouts)
- **Tablet**: 768px - 1199px (2-column layouts)
- **Mobile**: < 768px (Single column, full-width)

## 🎨 Color Scheme

- **Primary**: #667eea (Blue-Purple)
- **Secondary**: #764ba2 (Dark Purple)
- **Success**: #43e97b (Green)
- **Danger**: #ff6b6b (Red)
- **Warning**: #ffd89b (Yellow)
- **Info**: #4facfe (Light Blue)

## 📊 Database Integration

### Tables Used:
- `facilities`: Room/facility data
- `bookings`: Online booking submissions

### Queries:
- Featured facilities list
- Facility count & statistics
- Booking confirmation retrieval
- Filter & sort operations

## 🔐 Security Features

- ✅ Input sanitization with `escape_input()`
- ✅ Prepared statements for database queries
- ✅ Email validation
- ✅ Form CSRF protection ready
- ✅ HTML entity encoding for output
- ✅ Type casting for numeric values

## 📧 Email Integration Ready

The booking confirmation page is prepared for:
- Automated confirmation emails
- Guest notification system
- Admin notification setup
- Email templates

To integrate:
1. Configure email service in booking handler
2. Create email templates
3. Set up SMTP credentials

## 💳 Payment Integration Ready

Structure supports:
- Payment gateway integration
- Booking status workflow
- Invoice generation
- Receipt display

## 🎯 SEO Optimization

- Semantic HTML5 structure
- Meta tags for social sharing
- Structured navigation
- Mobile-friendly responsive design
- Fast loading optimization

## 📞 Support & Contact

Resort Information:
- **Phone**: +63 123 456 7890
- **Email**: info@paradiseresort.com
- **Address**: 123 Beach Road, Paradise City, PC 12345

## 🔄 Integration with Admin Panel

The public website integrates with the existing admin system:
- Reads from shared database
- Bookings visible in admin dashboard
- Staff can manage online bookings
- Real-time availability reflection

## ✨ Future Enhancements

- [ ] Payment gateway integration (Stripe, PayMongo)
- [ ] Live chat support widget
- [ ] Photo gallery lightbox
- [ ] Google Maps embed
- [ ] Email confirmation system
- [ ] SMS notifications
- [ ] Guest reviews system
- [ ] Multi-language support
- [ ] Booking calendar view
- [ ] Promo code system

## 🐛 Known Limitations

- Contact form is template only (requires backend processing)
- Map is placeholder (needs Google Maps API)
- Gallery uses gradient placeholders (replace with actual images)
- Email notifications not configured (requires SMTP setup)

## 📝 License

© 2026 Paradise Resort. All rights reserved.

---

**Version**: 1.0.0  
**Last Updated**: January 31, 2026  
**Status**: Production Ready
