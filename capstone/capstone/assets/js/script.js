
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips and popovers
    initializeBootstrapComponents();
    
    // Add scroll animations
    addScrollAnimations();
    
    // Initialize date pickers
    initializeDatePickers();
});

/**
 * Initialize Bootstrap components
 */
function initializeBootstrapComponents() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Initialize popovers
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
}

/**
 * Add scroll animations
 */
function addScrollAnimations() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in-up');
            }
        });
    }, { threshold: 0.1 });
    
    document.querySelectorAll('.facility-card, .amenity-card, .testimonial-card, .package-card').forEach(el => {
        observer.observe(el);
    });
}

/**
 * Initialize date pickers with minimum date validation
 */
function initializeDatePickers() {
    const today = new Date().toISOString().split('T')[0];
    
    const checkInInputs = document.querySelectorAll('input[name="check_in_date"], input[name="check_in"]');
    const checkOutInputs = document.querySelectorAll('input[name="check_out_date"], input[name="check_out"]');
    
    checkInInputs.forEach(input => {
        input.setAttribute('min', today);
        input.addEventListener('change', function() {
            const checkOut = document.querySelector('input[name="check_out_date"], input[name="check_out"]');
            if (checkOut) {
                checkOut.setAttribute('min', this.value);
            }
        });
    });
    
    checkOutInputs.forEach(input => {
        input.setAttribute('min', today);
    });
}

/**
 * Smooth scroll to section
 */
function scrollToSection(selector) {
    const element = document.querySelector(selector);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Format currency
 */
function formatCurrency(value, currency = '₱') {
    return currency + parseFloat(value).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

/**
 * Calculate nights between two dates
 */
function calculateNights(checkInDate, checkOutDate, mode = 'overnight') {
    if (!checkInDate || !checkOutDate) return 0;
    
    const checkIn = new Date(checkInDate);
    const checkOut = new Date(checkOutDate);
    
    if (mode === 'daytour') {
        return 1;
    }
    
    const nights = Math.ceil((checkOut - checkIn) / (1000 * 60 * 60 * 24));
    return Math.max(1, nights);
}

/**
 * Validate email
 */
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

/**
 * Validate phone number
 */
function isValidPhone(phone) {
    const phoneRegex = /^[0-9\s\-\+\(\)]+$/;
    return phoneRegex.test(phone) && phone.replace(/\D/g, '').length >= 10;
}

/**
 * Show notification
 */
function showNotification(message, type = 'info', duration = 5000) {
    const alertClass = `alert-${type}`;
    const alertHTML = `
        <div class="alert ${alertClass} alert-dismissible fade show position-fixed top-0 end-0 m-3" role="alert" style="z-index: 9999;">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHTML);
    
    if (duration > 0) {
        setTimeout(() => {
            const alert = document.querySelector('.alert:last-of-type');
            if (alert) {
                alert.remove();
            }
        }, duration);
    }
}

/**
 * Debounce function
 */
function debounce(func, delay) {
    let timeoutId;
    return function(...args) {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(() => {
            func.apply(this, args);
        }, delay);
    };
}

/**
 * Add loading spinner
 */
function addLoadingSpinner(element) {
    const spinner = document.createElement('div');
    spinner.className = 'spinner-border text-primary';
    spinner.setAttribute('role', 'status');
    spinner.innerHTML = '<span class="visually-hidden">Loading...</span>';
    element.appendChild(spinner);
}

/**
 * Remove loading spinner
 */
function removeLoadingSpinner(element) {
    const spinner = element.querySelector('.spinner-border');
    if (spinner) {
        spinner.remove();
    }
}

/**
 * Track page view (for analytics)
 */
function trackPageView(pageName) {
    if (typeof gtag !== 'undefined') {
        gtag('event', 'page_view', {
            'page_title': pageName,
            'page_location': window.location.href
        });
    }
}

/**
 * Track event (for analytics)
 */
function trackEvent(eventName, eventData = {}) {
    if (typeof gtag !== 'undefined') {
        gtag('event', eventName, eventData);
    }
}

/**
 * Handle form submission with validation
 */
function handleFormSubmit(formSelector, onSuccess, onError) {
    const form = document.querySelector(formSelector);
    
    if (!form) return;
    
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        try {
            addLoadingSpinner(form);
            
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: formData
            });
            
            removeLoadingSpinner(form);
            
            if (response.ok) {
                if (onSuccess) onSuccess(response);
            } else {
                if (onError) onError(response);
            }
        } catch (error) {
            removeLoadingSpinner(form);
            console.error('Form submission error:', error);
            if (onError) onError(error);
        }
    });
}

// Export functions for use in other scripts
window.ParadiseResort = {
    scrollToSection,
    formatCurrency,
    calculateNights,
    isValidEmail,
    isValidPhone,
    showNotification,
    debounce,
    addLoadingSpinner,
    removeLoadingSpinner,
    trackPageView,
    trackEvent,
    handleFormSubmit
};
