// --- 1. VIEW SWITCHING & MOBILE DRAWER LOGIC ---
function switchView(viewId, element, pageTitle) {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    if (element) element.classList.add('active');

    const views = document.querySelectorAll('.view-section');
    views.forEach(view => view.classList.remove('active'));
    
    const target = document.getElementById(viewId);
    if (target) target.classList.add('active');

    if (pageTitle) {
        const titleElem = document.getElementById('page-title');
        if (titleElem) titleElem.innerText = pageTitle;
    }
}

function closeMobileMenu() {
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('open');
}

// --- 2. NOTIFICATION SYSTEM ---

function playDing() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.type = 'sine'; 
        osc.frequency.setValueAtTime(880, ctx.currentTime); 
        gain.gain.setValueAtTime(0.5, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 1);
        osc.start();
        osc.stop(ctx.currentTime + 1);
    } catch(e) { 
        console.warn("Audio autoplay blocked or failed", e); 
    }
}

function createToast(title, body) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `
        <i class="fas fa-bell" style="color: var(--danger, #ef4444); font-size: 20px; margin-right: 12px;"></i>
        <div>
            <h4 style="margin: 0 0 4px; color: #1e293b; font-size: 14px;">${title}</h4>
            <p style="margin: 0; font-size: 13px; color: #64748b;">${body}</p>
        </div>
    `;
    container.appendChild(toast);
    
    // Auto-remove after 8 seconds
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        setTimeout(() => toast.remove(), 300);
    }, 8000);
}

function triggerAlert(task) {
    const title = `Time for ${task.member_name || 'Family'}'s Task`;
    const body = `${task.title || 'Medicine/Clinic'} - ${task.instructions || 'No instructions'}`;

    playDing();
    createToast(title, body);

    // Browser Notification API
    if ('Notification' in window && Notification.permission === 'granted') {
        try {
            new Notification(title, { 
                body: body,
                icon: 'Gemini_Generated_Image_h1uw8kh1uw8kh1uw.jpg'
            });
        } catch(e) {
            console.warn("Native notification blocked:", e);
        }
    }
    
    // Send email via backend
    if (task.id) {
        const formData = new FormData();
        formData.append('task_id', task.id);

        fetch('send-email.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => console.log("CareSync Email Status:", data))
        .catch(error => console.error("Error triggering email:", error));
    }
}

// In-memory set + localStorage backup to guarantee zero duplicate toasts
const localAlertedIds = new Set();

function checkTasks() {
    if (typeof todayTasks === 'undefined' || !Array.isArray(todayTasks)) return;

    const now = new Date();
    
    todayTasks.forEach(task => {
        if (!task.schedule_time || !task.id) return;

        // Standardize date parsing: replace space with 'T' or slash for cross-browser support
        const safeDateString = task.schedule_time.replace(' ', 'T'); 
        const taskTime = new Date(safeDateString);
        
        if (now >= taskTime) {
            const storageKey = 'notified_' + task.id;
            const alreadyNotified = localAlertedIds.has(task.id) || localStorage.getItem(storageKey);

            if (!alreadyNotified) {
                // Mark IMMEDIATELY to prevent multiple firings
                localAlertedIds.add(task.id);
                try {
                    localStorage.setItem(storageKey, 'true');
                } catch(e) {}

                triggerAlert(task);
            }
        }
    });
}

// --- 3. INITIALIZATION & LISTENERS ---
document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Drawer Controls
    const menuBtn = document.getElementById('menu-toggle-btn');
    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (menuBtn && sidebar) {
        menuBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('open');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', closeMobileMenu);
    }

    // 2. Request Notification Permission
    function requestPerm() {
        if ('Notification' in window && Notification.permission !== 'granted') {
            Notification.requestPermission();
        }
    }

    // Connect both desktop and mobile bell buttons
    document.querySelectorAll('.notification-btn, #request-notif-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            requestPerm();
            triggerAlert({
                id: 'test',
                member_name: "Test User",
                title: "System Check",
                instructions: "Notifications are working!"
            });
        });
    });

    // Initial check and periodic polling every 10 seconds
    checkTasks();
    setInterval(checkTasks, 10000);
});