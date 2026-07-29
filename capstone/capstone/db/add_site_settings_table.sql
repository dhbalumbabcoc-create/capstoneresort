-- Add site_settings table for business information
CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    resort_name VARCHAR(255) NOT NULL,
    tagline VARCHAR(255) DEFAULT NULL,
    contact_info VARCHAR(255) DEFAULT NULL,
    business_hours VARCHAR(255) DEFAULT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default business info (edit as needed)
INSERT INTO site_settings (resort_name, tagline, contact_info, business_hours, logo) VALUES (
    'Sinulom and Bolao Cold Spring',
    'Cold Spring',
    '(example) 0917-123-4567',
    '8:00 AM - 5:00 PM',
    'logo.jpg'
);
