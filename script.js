// --- 1. VIEW SWITCHING LOGIC ---
function switchView(viewId, element, pageTitle) {
    const navItems = document.querySelectorAll('.nav-item');
    navItems.forEach(item => item.classList.remove('active'));
    element.classList.add('active');

    const views = document.querySelectorAll('.view-section');
    views.forEach(view => view.classList.remove('active'));
    document.getElementById(viewId).classList.add('active');

    if(pageTitle) {
        document.getElementById('page-title').innerText = pageTitle;
    }
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
        console.error("Audio failed to play", e); 
    }
}

function createToast(title, body) {
    const container = document.getElementById('toast-container');
    if(!container) return;
    
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `
        <i class="fas fa-bell" style="color: var(--danger); font-size: 24px;"></i>
        <div>
            <h4 style="margin: 0; margin-bottom: 4px; color: #1e293b;">${title}</h4>
            <p style="margin: 0; font-size: 13px; color: #64748b;">${body}</p>
        </div>
    `;
    container.appendChild(toast);
    
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => toast.remove(), 300);
    }, 10000);
}

function triggerAlert(task) {
    const title = `Time for ${task.member_name}'s Task`;
    const body = `${task.title} - ${task.instructions}`;

    playDing();
    createToast(title, body);

    if (Notification.permission === 'granted') {
        new Notification(title, { body: body });
    }
    
    // NEW: Fire an asynchronous request to send the email
    const formData = new FormData();
    formData.append('task_id', task.id);

    // FIXED: Changed hyphen to underscore to match your file exactly
    fetch('send_email.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => console.log("CareSync Email Status:", data))
    .catch(error => console.error("Error triggering email:", error));
}

function checkTasks() {
    if(typeof todayTasks === 'undefined') return;

    const now = new Date();
    
    todayTasks.forEach(task => {
        // FIX: Replaced space with 'T' to ensure all browsers read the date correctly
        const safeDateString = task.schedule_time.replace(' ', 'T'); 
        const taskTime = new Date(safeDateString);
        
        if (now >= taskTime) {
            const storageKey = 'notified_' + task.id;
            if (!localStorage.getItem(storageKey)) {
                triggerAlert(task);
                localStorage.setItem(storageKey, 'true'); 
            }
        }
    });
}

// --- 3. INITIALIZATION & TEST BUTTON ---
document.addEventListener('DOMContentLoaded', () => {
    
    // Request permission immediately when the page loads
    if (Notification.permission !== 'granted' && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }

    // Connect the Bell Icon to a manual test function!
    const bellBtn = document.querySelector('.notification-btn');
    if(bellBtn) {
        bellBtn.addEventListener('click', () => {
            // Ask for permission if they haven't given it yet
            if (Notification.permission !== 'granted') {
                Notification.requestPermission();
            }
            
            // Fire a fake alert to test the system
            triggerAlert({
                member_name: "Test User",
                title: "System Check",
                instructions: "Notifications are working perfectly!"
            });
        });
    }

    // Start checking the clock
    checkTasks();
    setInterval(checkTasks, 5000); // Check every 5 seconds instead of 10
});