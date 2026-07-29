# Image Folder Usage Guide

## Location
`/images/` folder at the root of the website

## Available Images

### Hero & Background Images
- **hero-section.jpg** - Full-width hero backgrounds, 16:9 aspect ratio
  - Used in: Landing page header, About page header
  - Best for: Page headers with overlay text

- **booking-header.jpg** - Booking-related sections
  - Used in: About page contact/map section
  - Best for: Call-to-action sections

### Facility Images - Cottages
- **cottage1.jpg** - Primary cottage imagery
  - Used in: Featured facilities (landing), Gallery cards
  - Facility Type Mapping: `cottage` type

- **cottage2.jpg** - Alternative cottage
  - Available for future use or rotation

### Facility Images - Villas
- **villa-gracia.jpg** - Primary villa property image
  - Used in: Default facility image, About page
  - Facility Type Mapping: `villa` type
  - Widely used as fallback image

- **villa-carolina.jpg** - Secondary villa
  - Available for future use or rotation

- **villa-candida.jpg** - Tertiary villa
  - Available for future use or rotation

### Event & Function Spaces
- **function-hall1.jpg** - Primary function hall
  - Used in: Featured facilities, Gallery cards
  - Facility Type Mapping: `function` type

- **function-hall2.jpg** - Secondary function hall
  - Available for future use or rotation

### Amenity Images
- **umbrella.jpg** - Resort amenity (beach/pool area)
  - Available for amenity sections or gallery

- **longtable.jpg** - Event/dining setup
  - Available for function space showcase

---

## How Images Are Currently Used

### 1. Landing Page (`landing.php`)
- **Section:** Featured Facilities
- **Implementation:** Dynamic image mapping based on facility type
- **Height:** 250px
- **Fallback:** villa-gracia.jpg

```php
$image_map = [
    'cottage' => 'cottage1.jpg',
    'villa' => 'villa-gracia.jpg',
    'function' => 'function-hall1.jpg'
];
```

### 2. Gallery Page (`gallery.php`)
- **Section:** Facility Gallery Grid
- **Implementation:** Same dynamic mapping as landing page
- **Height:** 300px
- **Fallback:** villa-gracia.jpg

### 3. Public Booking Page (`public_booking.php`)
- **Section:** Booking form sidebar (selected facility)
- **Implementation:** Dynamic image mapping
- **Height:** 250px
- **Fallback:** villa-gracia.jpg

### 4. About Page (`about.php`)
- **Section 1:** About section image
  - Image: villa-gracia.jpg
  - Height: 400px
- **Section 2:** Hero header
  - Image: hero-section.jpg with green overlay
- **Section 3:** Location/Map section
  - Image: booking-header.jpg with green overlay

---

## Image Specifications

### Recommended Image Properties
- **Format:** JPG (optimized for web)
- **Quality:** High (80-90% compression)
- **Minimum Width:** 400px
- **Aspect Ratios:**
  - Hero sections: 16:9
  - Facility cards: 4:3 or 5:4
  - Square images: 1:1

### CSS Properties for Images
```css
/* Standard facility images */
background: url('images/filename.jpg') center/cover;
height: 250px; /* or 300px for gallery */
display: flex;
align-items: center;
justify-content: center;

/* Or direct img tag */
<img src="images/filename.jpg" 
     class="img-fluid rounded shadow" 
     style="height: 400px; object-fit: cover; width: 100%;">
```

---

## Adding New Images

### Steps to Add New Facility Images
1. Place JPG image in `/images/` folder
2. Update image mapping in PHP files:

```php
$image_map = [
    'cottage' => 'cottage1.jpg',  // Add new facility type here
    'villa' => 'villa-gracia.jpg',
    'function' => 'function-hall1.jpg',
    'your_type' => 'your_image.jpg'  // New line
];
```

3. Files to update:
   - `landing.php` (line ~97)
   - `gallery.php` (line ~117)
   - `public_booking.php` (line ~335)

### Example: Adding Pool Cabanas
```php
$image_map = [
    'cottage' => 'cottage1.jpg',
    'villa' => 'villa-gracia.jpg',
    'function' => 'function-hall1.jpg',
    'cabana' => 'pool-cabana.jpg'  // New
];
```

---

## Future Enhancement Ideas

1. **Image Rotation**
   - Use array_rand() to rotate between similar images
   - Example: Multiple cottage images rotating on each load

2. **Gallery Lightbox**
   - Click facility image to open full-screen gallery
   - Show multiple facility images

3. **Photo Upload**
   - Admin feature to upload facility photos
   - Database storage of image paths

4. **Responsive Images**
   - Multiple image sizes (small, medium, large)
   - Conditional loading based on screen size

5. **Image Carousel**
   - Featured facilities carousel on landing page
   - Auto-rotating or manual navigation

---

## Troubleshooting

### Images Not Loading
1. Check file exists in `/images/` folder
2. Verify filename matches (case-sensitive on Linux)
3. Check file permissions (755 for folder, 644 for files)
4. Clear browser cache (Ctrl+Shift+Delete)
5. Check browser console for 404 errors

### Broken Image Fallbacks
- If image path is wrong, gradient background still displays
- Green gradient (#1B7D3A to #27A457) serves as fallback
- Text content is still readable over fallback gradient

### Performance Issues
- Optimize JPG images before uploading
- Use online tools: TinyJPG, Squoosh.app
- Target file size: 50-150KB per image
- Consider WebP format for faster loading (future)

---

## Color Overlays on Images

All images with text overlays use semi-transparent green:
```css
background: linear-gradient(rgba(27, 125, 58, 0.7), rgba(27, 125, 58, 0.7)), 
            url('images/filename.jpg') center/cover;
```

This creates:
- Green tinted overlay (70% opacity)
- Better text readability
- Professional appearance
- Brand color consistency

---

*Last Updated: February 1, 2026*
*Version: 1.0*
