const APP_VERSION = '1.0.1';
const BASE_URL = 'https://gabeetzu-project.onrender.com/';
const CACHE_NAME = 'gospod-app-v1.0.1';

// Global variables
let lastQuestion = '';
let lastImage = null;
let deferredPrompt = null;
let isProcessing = false;

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

// Handle PWA install prompt
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    showInstallPrompt();
});

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
    deviceHash: null,
    
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
    },
    
    canMakeRequest() {
        return this.isPremium || this.total < this.dailyLimit;
    },
    
    incrementUsage(type) {
        if (type === 'text') {
            this.text++;
            this.total++;
            localStorage.setItem('usage_text', this.text);
        } else if (type === 'image') {
            this.photo++;
            this.total++;
            localStorage.setItem('usage_photo', this.photo);
        }
        localStorage.setItem('usage_total', this.total);
        this.updateUI();
        Trophies.checkAll();
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
            
            showToast('🏆 Trofeu deblocat: ' + this.get(id).text, 'trophy');
            this.render();
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

// UI Management Functions
function showWelcomeScreen() {
    const welcomeSection = document.getElementById('welcome-section');
    const chatSection = document.getElementById('chat-section');
    
    if (welcomeSection && chatSection) {
        welcomeSection.style.display = 'block';
        chatSection.style.display = 'none';
    }
}

function showChatScreen() {
    const welcomeSection = document.getElementById('welcome-section');
    const chatSection = document.getElementById('chat-section');
    
    if (welcomeSection && chatSection) {
        welcomeSection.style.display = 'none';
        chatSection.style.display = 'block';
    }
}

function saveUserName() {
    const nameInput = document.getElementById('user-name');
    if (nameInput && nameInput.value.trim()) {
        const userName = nameInput.value.trim();
        localStorage.setItem('user_name', userName);
        
        // Update referral code based on name
        const refCode = (userName.replace(/\s+/g, '').toUpperCase().slice(0, 5) + Math.floor(Math.random() * 1000));
        localStorage.setItem('ref_code', refCode);
        updateReferralDisplay();
        
        showChatScreen();
        showToast(`Bun venit, ${userName}!`, 'success');
        
        // Add welcome message to chat
        addMessage(`Salut, ${userName}! Sunt asistentul tău pentru grădinărit. Poți să îmi pui întrebări despre plante sau să îmi trimiți poze cu problemele tale din grădină.`, 'bot');
    }
}

function updateReferralDisplay() {
    const refCode = localStorage.getItem('ref_code');
    const refCodeElement = document.getElementById('ref-code');
    if (refCodeElement && refCode) {
        refCodeElement.textContent = refCode;
    }
}

// Chat Functions
function addMessage(text, sender, imageData = null) {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `message ${sender}-message`;
    
    const messageText = document.createElement('div');
    messageText.className = 'message-text';
    
    if (imageData && sender === 'user') {
        const img = document.createElement('img');
        img.src = imageData;
        img.style.maxWidth = '200px';
        img.style.borderRadius = '8px';
        img.style.marginBottom = '0.5rem';
        messageText.appendChild(img);
        
        if (text) {
            const textNode = document.createElement('p');
            textNode.textContent = text;
            messageText.appendChild(textNode);
        }
    } else {
        messageText.textContent = text;
    }
    
    messageDiv.appendChild(messageText);
    chatMessages.appendChild(messageDiv);
    
    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function showTypingIndicator() {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return;
    
    const typingDiv = document.createElement('div');
    typingDiv.className = 'message bot-message typing-indicator';
    typingDiv.id = 'typing-indicator';
    
    const messageText = document.createElement('div');
    messageText.className = 'message-text';
    messageText.innerHTML = '<span class="typing-dots">Se gândește<span>.</span><span>.</span><span>.</span></span>';
    
    typingDiv.appendChild(messageText);
    chatMessages.appendChild(typingDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function hideTypingIndicator() {
    const typingIndicator = document.getElementById('typing-indicator');
    if (typingIndicator) {
        typingIndicator.remove();
    }
}

// Message Handling Functions
function handleUserMessage() {
    if (isProcessing) return;
    
    const textInput = document.getElementById('text-input');
    if (!textInput || !textInput.value.trim()) return;
    
    const message = textInput.value.trim();
    textInput.value = '';
    
    if (!UsageTracker.canMakeRequest()) {
        showToast('Ai atins limita zilnică de utilizări. Încearcă mâine sau fă upgrade la Premium!', 'error');
        return;
    }
    
    // Add user message to chat
    addMessage(message, 'user');
    lastQuestion = message;
    
    // Send to API
    sendMessageToAPI(message, null);
}

function handleImageUpload(files) {
    if (!files || files.length === 0 || isProcessing) return;
    
    const file = files[0];
    if (!file.type.startsWith('image/')) {
        showToast('Te rog să selectezi o imagine validă.', 'error');
        return;
    }
    
    if (!UsageTracker.canMakeRequest()) {
        showToast('Ai atins limita zilnică de utilizări. Încearcă mâine sau fă upgrade la Premium!', 'error');
        return;
    }
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageData = e.target.result;
        
        // Add image message to chat
        addMessage('', 'user', imageData);
        lastImage = imageData;
        
        // Send to API
        sendMessageToAPI('', imageData);
    };
    reader.readAsDataURL(file);
}

function sendMessageToAPI(message, imageData) {
    isProcessing = true;
    showTypingIndicator();
    
    const formData = new FormData();
    formData.append('device_hash', UsageTracker.deviceHash);
    
    if (imageData) {
        // Convert base64 to blob
        const blob = dataURLtoBlob(imageData);
        formData.append('image', blob, 'plant_image.jpg');
        UsageTracker.incrementUsage('image');
    } else {
        formData.append('message', message);
        UsageTracker.incrementUsage('text');
    }
    
    fetch(`${BASE_URL}process-image.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideTypingIndicator();
        
        if (data.success) {
            addMessage(data.response, 'bot');
        } else {
            addMessage('Ne pare rău, a apărut o eroare. Te rog să încerci din nou.', 'bot');
            console.error('API Error:', data.error);
        }
    })
    .catch(error => {
        hideTypingIndicator();
        addMessage('Ne pare rău, nu te pot ajuta acum. Verifică conexiunea la internet și încearcă din nou.', 'bot');
        console.error('Network Error:', error);
    })
    .finally(() => {
        isProcessing = false;
    });
}

// Utility Functions
function dataURLtoBlob(dataURL) {
    const arr = dataURL.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new Blob([u8arr], { type: mime });
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) return;
    
    const toast = document.createElement('div');
    toast.className = `toast ${type === 'trophy' ? 'trophy-toast' : ''}`;
    toast.textContent = message;
    
    toastContainer.appendChild(toast);
    
    // Auto remove after 4 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.remove();
        }
    }, 4000);
}

function copyReferralCode() {
    const refCode = localStorage.getItem('ref_code');
    if (refCode) {
        navigator.clipboard.writeText(refCode).then(() => {
            showToast('Codul de referință a fost copiat!', 'success');
        }).catch(() => {
            showToast('Nu s-a putut copia codul. Încearcă din nou.', 'error');
        });
    }
}

function showInstallPrompt() {
    if (deferredPrompt) {
        const installBtn = document.createElement('button');
        installBtn.textContent = '📱 Instalează aplicația';
        installBtn.className = 'install-btn';
        installBtn.onclick = () => {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    showToast('Aplicația a fost instalată!', 'success');
                }
                deferredPrompt = null;
                installBtn.remove();
            });
        };
        
        const header = document.querySelector('.header-content');
        if (header) {
            header.appendChild(installBtn);
        }
    }
}

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
    
    // Setup chat listeners
    setupChatListeners();
    
    // Setup other UI listeners
    setupUIListeners();
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
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
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

function setupUIListeners() {
    // Save name button
    const saveNameBtn = document.getElementById('save-name');
    if (saveNameBtn) {
        saveNameBtn.addEventListener('click', saveUserName);
    }
    
    // Name input enter key
    const nameInput = document.getElementById('user-name');
    if (nameInput) {
        nameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                saveUserName();
            }
        });
    }
    
    // Copy referral code button
    const copyRefBtn = document.getElementById('copy-ref');
    if (copyRefBtn) {
        copyRefBtn.addEventListener('click', copyReferralCode);
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize systems
    UsageTracker.init();
    Trophies.render();
    setupEventListeners();
    updateReferralDisplay();
    
    // Check if user has a name saved
    const savedName = localStorage.getItem('user_name');
    if (savedName) {
        showChatScreen();
        addMessage(`Bun venit înapoi, ${savedName}! Cu ce te pot ajuta astăzi?`, 'bot');
    } else {
        showWelcomeScreen();
    }
});
