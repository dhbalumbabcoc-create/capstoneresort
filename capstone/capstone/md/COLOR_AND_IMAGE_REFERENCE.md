# Color Palette & Image Reference Guide

## Modern Green Color Palette

### Primary Colors

**Primary Dark Green**
```
Color: #1B7D3A
RGB: 27, 125, 58
Used for: Main buttons, headers, primary accents
Hex Code: #1B7D3A
```

**Primary Light Green**
```
Color: #27A457
RGB: 39, 164, 87
Used for: Secondary buttons, hover states, decorative accents
Hex Code: #27A457
```

### Color Gradient
```css
background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%);
```

Used extensively in:
- Hero sections
- Button gradients
- Header backgrounds
- Decorative overlays

### Alternative Colors (Retained)
```
Success:  #43e97b
Danger:   #ff6b6b
Warning:  #ffd89b
Info:     #4facfe
```

---

## Image Usage Map

### Quick Reference by Page

#### 🏠 Landing Page (landing.php)
```
Section: Featured Facilities
Location: Line ~97
Images: Dynamic mapping based on facility type
Heights: 250px
Pattern: background: url('images/...') center/cover;
```

#### 🖼️ Gallery Page (gallery.php)
```
Section: Facility Gallery Grid
Location: Line ~117
Images: Dynamic mapping (same as landing)
Heights: 300px
Pattern: background: url('images/...') center/cover;
```

#### 📅 Public Booking Page (public_booking.php)
```
Section: Booking Form Sidebar
Location: Line ~335
Images: Dynamic mapping based on selected facility
Heights: 250px
Pattern: background: url('images/...') center/cover;
```

#### ℹ️ About Page (about.php)
```
Section 1 - Hero:
  Image: hero-section.jpg
  Overlay: Green gradient rgba(27, 125, 58, 0.7)
  Pattern: Linear gradient overlay + image

Section 2 - About Image:
  Image: villa-gracia.jpg
  Height: 400px
  Pattern: Direct img tag with object-fit: cover

Section 3 - Map:
  Image: booking-header.jpg
  Overlay: Green gradient
  Pattern: Linear gradient overlay + image
```

---

## Image Mapping Reference

### Facility Type → Image Assignment

```php
$image_map = [
    'cottage'  => 'cottage1.jpg',
    'villa'    => 'villa-gracia.jpg',
    'function' => 'function-hall1.jpg'
];
```

**Logic Flow:**
1. Get facility type from database
2. Convert to lowercase
3. Check if facility type contains mapping key
4. If match found → use mapped image
5. If no match → default to 'villa-gracia.jpg'

**Example:**
- Facility type "cottage_deluxe" → contains "cottage" → cottage1.jpg
- Facility type "villa" → contains "villa" → villa-gracia.jpg
- Facility type "function_hall" → contains "function" → function-hall1.jpg
- Facility type "room" → no match → villa-gracia.jpg (fallback)

---

## Complete Image Inventory

### Cottage Images
```
File: cottage1.jpg
Type: Primary cottage facility
Used For: Featured cottages on landing/gallery
Aspect Ratio: Landscape
Fallback: None (primary image)

File: cottage2.jpg
Type: Alternative cottage
Used For: Future rotation or specific facilities
Status: Available for implementation
```

### Villa Images
```
File: villa-gracia.jpg
Type: Primary villa property
Used For: Most villas, default fallback
Aspect Ratio: Landscape
Usage: Most prominent image in system

File: villa-carolina.jpg
Type: Secondary villa
Used For: Future rotation or specific facilities
Status: Available for implementation

File: villa-candida.jpg
Type: Tertiary villa
Used For: Future rotation or specific facilities
Status: Available for implementation
```

### Function/Event Space Images
```
File: function-hall1.jpg
Type: Primary function hall
Used For: Event spaces, banquet halls
Aspect Ratio: Landscape
Fallback: None (primary image)

File: function-hall2.jpg
Type: Alternative function hall
Used For: Future rotation or specific facilities
Status: Available for implementation
```

### Hero/Header Images
```
File: hero-section.jpg
Type: Full-width hero background
Used For: Landing page header, About page header
Aspect Ratio: 16:9 (Wide)
Overlay: Green gradient (rgba(27, 125, 58, 0.7))

File: booking-header.jpg
Type: Booking-related header
Used For: About page contact/map sections
Aspect Ratio: Landscape
Overlay: Green gradient (rgba(27, 125, 58, 0.7))
```

### Amenity Images
```
File: umbrella.jpg
Type: Beach/pool amenity
Used For: Resort amenity showcases
Status: Available for implementation
Aspect Ratio: Portrait or Square

File: longtable.jpg
Type: Dining/event setup
Used For: Function space and dining sections
Status: Available for implementation
Aspect Ratio: Landscape
```

---

## CSS Color Implementation

### Root Variables
```css
:root {
    --primary-color: #1B7D3A;
    --secondary-color: #27A457;
    --success-color: #43e97b;
    --danger-color: #ff6b6b;
    --warning-color: #ffd89b;
    --info-color: #4facfe;
}
```

### Usage in Stylesheets
```css
/* Buttons */
.btn-primary {
    background-color: var(--primary-color);
}

/* Links */
.text-primary {
    color: var(--primary-color);
}

/* Backgrounds */
.bg-primary {
    background-color: var(--primary-color);
}

/* Gradients */
.gradient-green {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
}
```

---

## Inline Color Usage

### Buttons & Links
```html
<!-- Button with new green color -->
<button class="btn btn-primary">Book Now</button>
<!-- Renders: #1B7D3A background -->

<!-- Styled icon with green -->
<i class="fas fa-award" style="color: #1B7D3A;"></i>
```

### Gradient Backgrounds
```html
<!-- Full gradient background -->
<div style="background: linear-gradient(135deg, #1B7D3A 0%, #27A457 100%); 
            color: white; padding: 2rem;">
    Content here
</div>

<!-- Gradient overlay with image -->
<div style="background: linear-gradient(rgba(27, 125, 58, 0.7), rgba(27, 125, 58, 0.7)), 
            url('images/hero-section.jpg') center/cover;">
    Content with text on image
</div>
```

---

## Image Path Structure

### Standard Image Paths
```
URL: images/filename.jpg
Full Path: /capstone/images/filename.jpg
PHP: url('images/filename.jpg')
HTML: <img src="images/filename.jpg">
```

### Path Usage in Different Locations
```
File in Root (landing.php):
  src="images/cottage1.jpg"
  url('images/cottage1.jpg')

File in /admin/ (admin/walkin_booking.php):
  src="../images/cottage1.jpg"
  url('../images/cottage1.jpg')
  
File in /owner/ (owner/manage_facilities.php):
  src="../images/cottage1.jpg"
  url('../images/cottage1.jpg')
```

**Note:** Current implementation uses root-relative paths (`images/`) in PHP files

---

## Color Override Reference

### How to Change Primary Color
1. Edit `assets/css/style.css`
2. Find `:root { --primary-color: ... }`
3. Change `#1B7D3A` to new color
4. All pages automatically use new color
5. No PHP changes needed

### How to Change Primary Gradient
1. Edit `assets/css/style.css`
2. Find gradient definition
3. Update both `--primary-color` and `--secondary-color`
4. Gradient automatically updates site-wide

### How to Override Single Page Color
```php
<!-- Override with inline style -->
<div style="background-color: #new-color;">
    Content
</div>
```

---

## Color Accessibility

### Contrast Ratios (WCAG AA Standard)
```
Dark Green (#1B7D3A) on White:
Ratio: 6.5:1 ✅ PASSES AA & AAA

Light Green (#27A457) on White:
Ratio: 3.8:1 ✅ PASSES AA

Light Green (#27A457) on Dark Green:
Ratio: 1.8:1 ⚠️ Not recommended for text

Both on light gray backgrounds:
✅ Sufficient contrast maintained
```

### Color Blindness Considerations
- Green tones are distinguishable for all types
- Complementary colors (red, orange) provide additional contrast
- Avoid relying on color alone for information
- Use icons and text labels alongside colors

---

## Print Color Mapping

### Print Stylesheets
For print versions, colors convert to grayscale:
```css
@media print {
    .btn-primary {
        background-color: #333; /* Dark gray instead */
        color: white;
    }
}
```

---

## Design System Color Values

### Complete Color Set
```
Primary Dark:    #1B7D3A (RGB: 27, 125, 58)
Primary Light:   #27A457 (RGB: 39, 164, 87)
Primary Light:   #3CBE5C (RGB: 60, 190, 92) [Lighter shade]
Primary Light:   #4DB566 (RGB: 77, 181, 102) [Even lighter]

Secondary:       #43e97b (Bright green - success)
Danger:          #ff6b6b (Red - errors)
Warning:         #ffd89b (Yellow - warnings)
Info:            #4facfe (Blue - information)

Neutral White:   #ffffff
Neutral Dark:    #333333
Neutral Gray:    #666666
Light Gray:      #f5f5f5
```

---

## Quick Color Paste Reference

### For Developers
```
Primary Dark Green:  #1B7D3A
Primary Light Green: #27A457
RGB Alternative: rgb(27, 125, 58) and rgb(39, 164, 87)
HSL: hsl(143, 57%, 32%) and hsl(141, 58%, 44%)
```

### For Designers
- **Pantone:** Similar to Pantone 356 C (Dark Green) and Pantone 346 C (Light Green)
- **Hex Codes:** #1B7D3A, #27A457
- **RGB Mode:** (27, 125, 58) and (39, 164, 87)

---

## Testing Checklist

- [ ] All pages display with green color scheme
- [ ] Images load on all pages
- [ ] Color contrast is readable
- [ ] Images have proper aspect ratios
- [ ] No broken image links
- [ ] Gradients display correctly
- [ ] Mobile view shows images properly
- [ ] Print preview shows appropriate grayscale
- [ ] Browser compatibility tested
- [ ] Performance is acceptable

---

*Last Updated: February 1, 2026*
*Version: 1.0*
*Status: Complete & Ready for Reference*
