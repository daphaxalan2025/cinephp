// assets/js/script.js
document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // Form validation
    initFormValidation();
});

function initFormValidation() {
    // Username validation
    const usernameInput = document.getElementById('username');
    if (usernameInput) {
        usernameInput.addEventListener('input', function(e) {
            const value = e.target.value;
            const isValid = /^[a-zA-Z0-9_]{3,20}$/.test(value);
            updateFieldStatus(e.target, isValid);
        });
    }

    // Email validation
    const emailInput = document.getElementById('email');
    if (emailInput) {
        emailInput.addEventListener('input', function(e) {
            const value = e.target.value;
            const isValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
            updateFieldStatus(e.target, isValid);
        });
    }

    // Password validation
    const passwordInput = document.getElementById('password');
    if (passwordInput) {
        passwordInput.addEventListener('input', function(e) {
            const value = e.target.value;
            const isValid = value.length >= 6;
            updateFieldStatus(e.target, isValid);
        });
    }

    // Confirm password
    const confirmInput = document.getElementById('confirm_password');
    const passwordInput_ref = document.getElementById('password');
    if (confirmInput && passwordInput_ref) {
        confirmInput.addEventListener('input', function(e) {
            const isValid = e.target.value === passwordInput_ref.value && e.target.value.length > 0;
            updateFieldStatus(e.target, isValid);
        });
    }
}

function updateFieldStatus(field, isValid) {
    field.classList.remove('valid', 'invalid');
    if (isValid) {
        field.classList.add('valid');
    } else if (field.value.length > 0) {
        field.classList.add('invalid');
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type}`;
    notification.textContent = message;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => notification.remove(), 500);
    }, 3000);
}

// Seat selection functions (used in purchase.php)
let selectedSeats = [];

function selectSeat(seat, maxSeats) {
    if (seat.classList.contains('booked')) return;
    
    if (seat.classList.contains('selected')) {
        seat.classList.remove('selected');
        selectedSeats = selectedSeats.filter(s => s !== seat.dataset.seat);
    } else {
        if (selectedSeats.length < maxSeats) {
            seat.classList.add('selected');
            selectedSeats.push(seat.dataset.seat);
        } else {
            showNotification(`You can only select ${maxSeats} seat(s)`, 'error');
        }
    }
    
    updateSelectedSeats();
}

function updateSelectedSeats() {
    const display = document.getElementById('selectedSeats');
    const input = document.getElementById('selectedSeatsInput');
    
    if (display) {
        display.textContent = selectedSeats.length ? selectedSeats.join(', ') : 'None';
    }
    if (input) {
        input.value = selectedSeats.join(',');
    }
}