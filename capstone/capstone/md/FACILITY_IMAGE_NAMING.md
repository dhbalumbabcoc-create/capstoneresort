# Facility Image Naming Convention

## How Images Are Matched

The system now automatically matches facility names to image files:

### Image File Naming Rule
```
Facility Name: "Villa Gracia"
Image File:   "villa-gracia.jpg"

Facility Name: "Cottage Deluxe"
Image File:   "cottage-deluxe.jpg"

Facility Name: "Function Hall A"
Image File:   "function-hall-a.jpg"
```

**Rules:**
1. Convert facility name to lowercase
2. Replace spaces with hyphens (-)
3. Add .jpg extension
4. If image doesn't exist, falls back to `villa-gracia.jpg`

---

## Current Image Locations Used

### Landing Page
- **Section:** Featured Facilities (6 facilities shown)
- **Image Height:** 250px
- **Logic:** Facility name → image filename
- **Fallback:** villa-gracia.jpg

### Gallery Page
- **Section:** All Facilities Grid
- **Image Height:** 300px
- **Logic:** Facility name → image filename
- **Fallback:** villa-gracia.jpg

### Booking Page
- **Section:** Selected Facility Card (sidebar)
- **Image Height:** 250px
- **Logic:** Facility name → image filename
- **Fallback:** villa-gracia.jpg

### Hero Sections
- **Landing Page:** `hero-section.jpg` (with green overlay)
- **Booking Page:** `booking-header.jpg` (with green overlay)

---

## How to Add Facility Images

### Step 1: Create Image File
- Save image as JPG format
- Optimize for web (50-150KB)
- Name it using facility name convention

### Step 2: Place in `/images/` Folder
```
Example:
Facility: "Villa Carolina"
Image: villa-carolina.jpg
Location: /capstone/images/villa-carolina.jpg
```

### Step 3: System Automatically Uses It
The system will automatically:
1. Check database for facility name "Villa Carolina"
2. Convert to lowercase: "villa carolina"
3. Replace spaces with hyphens: "villa-carolina"
4. Look for file: "villa-carolina.jpg"
5. Display image on all pages using that facility

---

## Examples

### If You Add These Facilities:

```
Facility Name              →  Image Filename
─────────────────────────────────────────────
Villa Candida              →  villa-candida.jpg
Cottage Premium            →  cottage-premium.jpg
Function Hall Ballroom     →  function-hall-ballroom.jpg
Beachfront Villa           →  beachfront-villa.jpg
Economy Room               →  economy-room.jpg
Deluxe Suite               →  deluxe-suite.jpg
Main Event Space           →  main-event-space.jpg
Poolside Cabana            →  poolside-cabana.jpg
```

---

## Current Facilities & Their Image Files

Based on standard resort naming, here are common facilities:

### If You Have:
- "Villa Gracia" → image file: `villa-gracia.jpg` ✅ (ready)
- "Cottage 1" → image file: `cottage-1.jpg`
- "Function Hall" → image file: `function-hall.jpg`
- "Villa Carolina" → image file: `villa-carolina.jpg` ✅ (ready)
- "Villa Candida" → image file: `villa-candida.jpg` ✅ (ready)
- "Cottage 2" → image file: `cottage-2.jpg` ✅ (cottage2.jpg - close match)

---

## Fallback Behavior

If image file doesn't exist:
```
Facility: "Unknown Resort"
Looked for: /images/unknown-resort.jpg
Result: NOT FOUND
Action: Uses fallback villa-gracia.jpg
```

This ensures:
- No broken image links
- Page always displays something
- No errors in logs

---

## Technical Implementation

### Code Logic:
```php
// Convert facility name to image filename
$facility_name = strtolower(str_replace(' ', '-', $facility['name']));
$image_file = $facility_name . '.jpg';

// Check if file exists, use fallback if not
if (!file_exists('images/' . $image_file)) {
    $image_file = 'villa-gracia.jpg';
}

// Use the image
echo "url('images/" . $image_file . "')";
```

---

## File Upload Instructions

When adding a new facility with custom image:

1. **Prepare Image**
   - Format: JPG
   - Size: 800x600px minimum
   - File size: 50-150KB

2. **Name File Correctly**
   - Use facility name with spaces as hyphens
   - Example: "My Cool Villa" → "my-cool-villa.jpg"

3. **Upload to Folder**
   ```
   /capstone/images/my-cool-villa.jpg
   ```

4. **Add Facility in Admin**
   - Go to Manage Facilities
   - Create facility named "My Cool Villa"
   - Save (no need to manually link images)

5. **System Automatically Uses It**
   - Landing page: Shows image
   - Gallery: Shows image
   - Booking: Shows image when selected

---

## Troubleshooting

### Image Not Showing
**Possible Causes:**
1. File name doesn't match facility name
2. Image file not uploaded to `/images/` folder
3. File name has wrong spacing (use hyphens, not underscores)
4. File extension is wrong (must be .jpg)

**Solution:**
- Check file exists: `/images/facility-name.jpg`
- Verify facility name in database
- Rename file to match (lowercase, hyphens)
- Re-upload file

### Wrong Image Displaying
**Cause:** Image file not found, showing fallback (villa-gracia.jpg)

**Solution:**
- Rename image file to match facility name exactly
- Example: Facility "Cottage Premium" needs file "cottage-premium.jpg"

### Case Sensitivity Issues
**Note:** Linux servers are case-sensitive!
```
CORRECT:   cottage-deluxe.jpg  ✅
WRONG:     Cottage-Deluxe.jpg  ❌
WRONG:     COTTAGE-DELUXE.JPG  ❌
```

Always use lowercase for filenames!

---

## Best Practices

1. **Naming Convention**
   - Use lowercase
   - Use hyphens for spaces
   - Use .jpg extension
   - Keep names short and memorable

2. **Image Quality**
   - High resolution (at least 800x600px)
   - Optimized file size (50-150KB)
   - Consistent aspect ratios

3. **Consistency**
   - Use same naming style for all images
   - Keep facility names consistent in database
   - Update images regularly

---

## Future Enhancements

The current system supports:
- [x] Automatic name-based image matching
- [x] Fallback to default image
- [x] Display on multiple pages
- [ ] Multiple images per facility
- [ ] Image carousel for facility
- [ ] Admin image upload feature
- [ ] Thumbnail generation

---

*Last Updated: February 1, 2026*
*Version: 2.0 - Facility Name Based Images*
