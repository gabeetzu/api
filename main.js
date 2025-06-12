const APP_VERSION = '1.0.1';
const BASE_URL = 'https://gabeetzu-project.onrender.com/';
const CACHE_NAME = 'gospod-app-v1.0.1';

// Global variables
let lastQuestion = '';
let lastImage = null;
let deferredPrompt = null;

// Register service worker for PWA functionality
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('service-worker.js')
    .then(registration => {
        console.log('Service Worker registered with scope:', registration.scope);
    })
    .catch(error => {
        console.error('Service Worker registration failed:', error);
    });
}

// Generate a referral code on first load if one doesn't exist
if (!localStorage.getItem('ref_code')) {
    const user = localStorage.getItem('user_name') || 'anon';
    const refCode = (user.replace(/\s+/g, '').toUpperCase().slice(0, 5) + Math.floor(Math.random() * 1000));
    localStorage.setItem('ref_code', refCode);
}

// User tracker and API functions
const UsageTracker = {
    total: 0,
    photo: 0,
    text: 0,
    dailyLimit: 30,
    isPremium: false,
    premiumUntil: null,
    
    init() {
        // Get device hash or generate a new one
        this.deviceHash = localStorage.getItem('device_hash') || this.generateDeviceHash();
        if (!localStorage.getItem('device_hash')) {
            localStorage.setItem('device_hash', this.deviceHash);
        }
        
        // Load usage stats from server
        this.fetchUsageData();
    },
    
    generateDeviceHash() {
        return 'dev_' + Math.random().toString(36).substring(2, 15) + 
               Math.random().toString(36).substring(2, 15);
    },
    
    fetchUsageData() {
        fetch(`${BASE_URL}get-usage.php?hash=${this.deviceHash}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.total = data.stats.total_count || 0;
                    this.photo = data.stats.image_count || 0;
                    this.text = data.stats.text_count || 0;
                    this.isPremium = data.stats.premium === 1;
                    this.premiumUntil = data.stats.premium_until || null;
                    
                    // Update UI
                    this.updateUI();
                    
                    // Check for trophies
                    Trophies.checkAll();
                }
            })
            .catch(error => {
                console.error('Failed to fetch usage data', error);
                // Fallback to local data
                this.loadLocalData();
            });
    },
    
    loadLocalData() {
        this.total = parseInt(localStorage.getItem('usage_total') || 0);
        this.photo = parseInt(localStorage.getItem('usage_photo') || 0);
        this.text = parseInt(localStorage.getItem('usage_text') || 0);
        this.updateUI();
    },
    
    updateUI() {
        const counter = document.getElementById('usage-counter');
        if (counter) {
            counter.textContent = this.isPremium ? 
                `Utilizări: ${this.total} (Nelimitat)` : 
                `Utilizări: ${this.total}/${this.dailyLimit}`;
        }
        
        const status = document.getElementById('premium-status');
        if (status) {
            if (this.isPremium) {
                const formattedDate = new Date(this.premiumUntil)
                    .toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
                status.textContent = `Premium activ până la: ${formattedDate}`;
                status.classList.add('premium-active');
            } else {
                status.textContent = '';
                status.classList.remove('premium-active');
            }
        }
    }
};

// Trophy system
const Trophies = {
    list: [
        { 
            id: 'first_question', 
            text: '🏅 Prima întrebare', 
            condition: () => UsageTracker.total === 1 
        },
        { 
            id: 'first_image', 
            text: '📸 Prima imagine', 
            condition: () => UsageTracker.photo === 1 
        },
        { 
            id: 'hundred_questions', 
            text: '💯 100 întrebări', 
            condition: () => UsageTracker.total === 100 
        },
        { 
            id: 'first_friend', 
            text: '🫂 Ai invitat un prieten', 
            condition: () => localStorage.getItem('referred_friend') === 'true' 
        }
    ],
    
    unlock(id) {
        const unlocked = JSON.parse(localStorage.getItem('trophies') || '[]');
        if (!unlocked.includes(id)) {
            unlocked.push(id);
            localStorage.setItem('trophies', JSON.stringify(unlocked));
            
            const toast = document.createElement('div');
            toast.className = 'toast trophy-toast';
            toast.textContent = '🏆 Trofeu deblocat: ' + this.get(id).text;
            document.getElementById('toast-container').appendChild(toast);
            
            setTimeout(() => toast.remove(), 4000);
        }
    },
    
    get(id) {
        return this.list.find(t => t.id === id);
    },
    
    checkAll() {
        for (const t of this.list) {
            if (t.condition()) this.unlock(t.id);
        }
    },
    
    render() {
        const unlocked = JSON.parse(localStorage.getItem('trophies') || '[]');
        const box = document.getElementById('trophies');
        
        if (box) {
            if (unlocked.length === 0) {
                box.innerHTML = '<p class="empty-trophies">Încă nu ai deblocat niciun trofeu</p>';
            } else {
                box.innerHTML = unlocked.map(id => {
                    const trophy = this.get(id);
                    return `<div class="trophy-item">${trophy.text}</div>`;
                }).join('');
            }
        }
    }
};

// Menu toggle functionality
function setupEventListeners() {
    // Menu toggle
    const navToggle = document.getElementById('nav-toggle');
    const navMenu = document.getElementById('nav-menu');
    
    if (navToggle && navMenu) {
        navToggle.addEventListener('click', () => {
            navToggle.classList.toggle('active');
            navMenu.classList.toggle('active');
        });
    }
    
    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        if (navMenu && navMenu.classList.contains('active') && 
            !navMenu.contains(e.target) && 
            !navToggle.contains(e.target)) {
            navMenu.classList.remove('active');
            navToggle.classList.remove('active');
        }
    });
    
    // Additional event listeners for chat, images, etc.
    setupChatListeners();
}

function setupChatListeners() {
    // Send message button
    const sendBtn = document.getElementById('send-btn');
    if (sendBtn) {
        sendBtn.addEventListener('click', handleUserMessage);
    }
    
    // Text input enter key
    const textInput = document.getElementById('text-input');
    if (textInput) {
        textInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                handleUserMessage();
            }
        });
    }
    
    // Image upload functionality
    const imageBtn = document.getElementById('image-btn');
    const imageInput = document.getElementById('image-input');
    
    if (imageBtn && imageInput) {
        imageBtn.addEventListener('click', () => {
            imageInput.click();
        });
        
        imageInput.addEventListener('change', (e) => {
            handleImageUpload(e.target.files);
        });
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    UsageTracker.init();
    Trophies.render();
    setupEventListeners();
});
