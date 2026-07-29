# Email Configuration Instructions

## Setup Steps

### 1. Install PHPMailer via Composer

Run this command in your project directory (c:\xampp\htdocs\capstone):

```bash
composer install
```

If you don't have Composer installed, download it from: https://getcomposer.org/download/

### 2. Configure Email Settings

Edit `includes/send_booking_email.php` and update the following:

```php
$mail->Host       = 'smtp.gmail.com'; // Your SMTP host
$mail->Username   = 'your-email@gmail.com'; // Your email
$mail->Password   = 'your-app-password'; // Your app password
$mail->setFrom('your-email@gmail.com', 'Sinulom Falls and Bolao Cold Spring');
```

### 3. Gmail App Password Setup (if using Gmail)

1. Go to your Google Account settings
2. Enable 2-Step Verification
3. Go to Security > App passwords
4. Generate a new app password for "Mail"
5. Copy the 16-character password
6. Use this password in the `$mail->Password` field

### 4. Alternative SMTP Providers

**For other email providers, update these settings:**

- **Outlook/Hotmail:**
  - Host: `smtp.office365.com`
  - Port: `587`

- **Yahoo:**
  - Host: `smtp.mail.yahoo.com`
  - Port: `587`

- **Custom SMTP:**
  - Contact your hosting provider for SMTP settings

### 5. Test the Email Functionality

1. Make a test booking through the public booking form
2. Check if the email arrives in the guest's inbox
3. Check spam/junk folder if not received
4. Check error logs if email fails: `xampp/apache/logs/error.log`

## Features Implemented

✅ Automatic email confirmation after booking
✅ Professional HTML email template
✅ Includes all booking details (ID, dates, guests, facility, area)
✅ Shows total amount
✅ Displays next steps and instructions
✅ Branded email design with resort colors

## Troubleshooting

**Email not sending?**
- Check SMTP credentials are correct
- Verify internet connection
- Check firewall/antivirus settings
- Enable "Less secure app access" (Gmail) or use App Password
- Check PHP error logs for detailed error messages

**Email goes to spam?**
- Add SPF and DKIM records to your domain
- Use a verified email address
- Ensure content doesn't trigger spam filters
