const PUBLIC_VAPID_KEY = 'BD73uAv-jsUeSLwDal94EqJmiOiVdfLD-O5-qFqUbQSGw0oEkb_Of7sNCT4R1W91ShPJtNw2SOeRM7FNNeaS3cg';

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);
    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

async function registerPushNotifications(force = false) {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn("Push notifications are not supported on this browser.");
        return;
    }

    // If already synced, don't re-subscribe on refresh unless user clicks the bell
    if (!force && localStorage.getItem('caresync_push_synced') === 'true') {
        return;
    }

    try {
        const registration = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        await navigator.serviceWorker.ready;

        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            return;
        }

        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: urlBase64ToUint8Array(PUBLIC_VAPID_KEY)
            });
        }

        const res = await fetch('save-subscription.php', {
            method: 'POST',
            body: JSON.stringify(subscription),
            headers: { 'Content-Type': 'application/json' }
        });

        const data = await res.json();
        if (data.status === 'success') {
            localStorage.setItem('caresync_push_synced', 'true');
            if (force) {
                createToast("Success", "Device registered for background alerts!");
            }
        }
    } catch (err) {
        console.error("Push registration error:", err);
    }
}

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

let menuDebounce = false;
function toggleMobileMenu(e) {
    if (e) {
        e.preventDefault();
        e.stopPropagation();
    }
    if (menuDebounce) return;
    menuDebounce = true;
    setTimeout(() => { menuDebounce = false; }, 300);

    const sidebar = document.getElementById('app-sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (sidebar) sidebar.classList.toggle('open');
    if (overlay) overlay.classList.toggle('open');
}

function closeMobileMenu(e) {
    if (e) e.stopPropagation();
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
    
    if (task.id && task.id !== 'test') {
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

const localAlertedIds = new Set();

function checkTasks() {
    if (typeof todayTasks === 'undefined' || !Array.isArray(todayTasks)) return;

    const now = new Date();
    
    todayTasks.forEach(task => {
        if (!task.schedule_time || !task.id) return;

        const safeDateString = task.schedule_time.replace(' ', 'T'); 
        const taskTime = new Date(safeDateString);
        
        if (now >= taskTime) {
            const storageKey = 'notified_' + task.id;
            const alreadyNotified = localAlertedIds.has(task.id) || localStorage.getItem(storageKey);

            if (!alreadyNotified) {
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
    const menuBtn = document.getElementById('menu-toggle-btn');
    const overlay = document.getElementById('sidebar-overlay');

    if (menuBtn) menuBtn.addEventListener('click', toggleMobileMenu);
    if (overlay) overlay.addEventListener('click', closeMobileMenu);

    // Clicking the bell forces re-registration
    document.querySelectorAll('.notification-btn, #request-notif-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            registerPushNotifications(true);
        });
    });

    // Check on load (will not duplicate if already synced)
    if ('Notification' in window && Notification.permission === 'granted') {
        registerPushNotifications(false);
    }

    checkTasks();
    setInterval(checkTasks, 10000);
});