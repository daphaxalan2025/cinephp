/* Add to your style.css */
.cart-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--card-gradient);
    backdrop-filter: blur(10px);
    -webkit-backdrop-filter: blur(10px);
    border: 1px solid rgba(229, 9, 20, 0.3);
    border-left: 4px solid var(--red);
    border-radius: 40px;
    padding: 15px 25px;
    color: var(--text-primary);
    font-family: 'Inter', sans-serif;
    transform: translateX(120%);
    transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    z-index: 9999;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
}

.cart-notification.show {
    transform: translateX(0);
}

.cart-notification.success {
    border-left-color: var(--red);
}

.cart-notification.error {
    border-left-color: #ff4444;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 15px;
}

.notification-icon {
    font-size: 1.5rem;
    filter: drop-shadow(0 0 10px var(--red));
}

.notification-message {
    font-weight: 500;
    letter-spacing: 0.5px;
}

#cart-counter {
    background: var(--red);
    color: #fff;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 5px;
    transition: all 0.3s;
}

#cart-counter.pulse {
    animation: cartPulse 0.3s ease;
}

@keyframes cartPulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.5); }
    100% { transform: scale(1); }
}

.cart-icon {
    position: relative;
    display: inline-block;
    color: var(--red);
    font-size: 1.2rem;
    transition: all 0.3s;
}

.cart-icon:hover {
    text-shadow: var(--red-glow);
}