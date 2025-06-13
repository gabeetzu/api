const APP_VERSION = '1.0.1';
const BASE_URL = 'https://gabeetzu-project.onrender.com/';
const CACHE_NAME = 'gospod-app-v1.0.1';

// Global variables
let lastQuestion = '';
let lastImage = null;
let deferredPrompt = null;
let isProcessing = false;
let recognition = null;
let isRecording = false;

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
    textLimit: 3,
    imageLimit: 1,
    isPremium: false,
    premiumUntil: null,
    deviceHash: null,
    
    init() {
        console.log('Initializing UsageTracker...');
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
        console.log('Fetching usage data for device:', this.deviceHash);
        fetch(`${BASE_URL}get-usage.php?hash=${this.deviceHash}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Usage data received:', data);
                if (data.success) {
                    this.total = data.stats.total_count || 0;
                    this.photo = data.stats.image_count || 0;
                    this.text = data.stats.text_count || 0;
                    this.isPremium = data.stats.premium === 1;
                    this.premiumUntil = data.stats.premium_until || null;
                    this.textLimit = data.stats.text_limit || (this.isPremium ? 10 : 3);
                    this.imageLimit = data.stats.image_limit || (this.isPremium ? 3 : 1);
                    
                    // Update UI
                    this.updateUI();
                    
                    // Check for trophies
                    Trophies.checkAll();
                } else {
                    console.error('API returned error:', data.error);
                    this.loadLocalData();
                }
            })
            .catch(error => {
                console.error('Failed to fetch usage data', error);
                // Fallback to local data
                this.loadLocalData();
            });
    },
    
    loadLocalData() {
        console.log('Loading local usage data...');
        this.total = parseInt(localStorage.getItem('usage_total') || 0);
        this.photo = parseInt(localStorage.getItem('usage_photo') || 0);
        this.text = parseInt(localStorage.getItem('usage_text') || 0);
        this.updateUI();
    },
    
    updateUI() {
        const counter = document.getElementById('usage-counter');
        if (counter) {
            if (this.isPremium) {
                counter.textContent = `Text: ${this.text}/${this.textLimit} | Foto: ${this.photo}/${this.imageLimit}`;
            } else {
                counter.textContent = `Text: ${this.text}/${this.textLimit} | Foto: ${this.photo}/${this.imageLimit}`;
            }
        }
        
        const status = document.getElementById('premium-status');
        if (status) {
            if (this.isPremium) {
                status.textContent = 'Premium';
                status.classList.add('premium-badge');
            } else {
                status.textContent = '';
                status.classList.remove('premium-badge');
            }
        }
    },
    
    canMakeRequest(type) {
        if (type === 'image') {
            return this.photo < this.imageLimit;
        }
        if (type === 'text') {
            return this.text < this.textLimit;
        }
        return (this.photo < this.imageLimit) || (this.text < this.textLimit);
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
    console.log('Showing welcome screen...');
    const welcomeSection = document.getElementById('welcome-section');
    const chatSection = document.getElementById('chat-section');
    
    if (welcomeSection && chatSection) {
        welcomeSection.style.display = 'block';
        chatSection.style.display = 'none';
        console.log('Welcome screen displayed');
    } else {
        console.error('Welcome or chat section elements not found');
    }
}

function showChatScreen() {
    console.log('Showing chat screen...');
    const welcomeSection = document.getElementById('welcome-section');
    const chatSection = document.getElementById('chat-section');
    
    if (welcomeSection && chatSection) {
        welcomeSection.style.display = 'none';
        chatSection.style.display = 'block';
        console.log('Chat screen displayed');
    } else {
        console.error('Welcome or chat section elements not found');
    }
}

function saveUserName() {
    console.log('saveUserName function called');
    const nameInput = document.getElementById('user-name');
    
    if (!nameInput) {
        console.error('Name input element not found');
        showToast('Eroare: câmpul pentru nume nu a fost găsit.', 'error');
        return;
    }
    
    if (nameInput && nameInput.value.trim()) {
        const userName = nameInput.value.trim();
        console.log('Saving user name:', userName);
        localStorage.setItem('user_name', userName);
        
        // Update referral code based on name
        const refCode = (userName.replace(/\s+/g, '').toUpperCase().slice(0, 5) + Math.floor(Math.random() * 1000));
        localStorage.setItem('ref_code', refCode);
        updateReferralDisplay();
        
        showChatScreen();
        showToast(`Bun venit, ${userName}!`, 'success');
        
        // Add welcome message to chat
        setTimeout(() => {
            addMessage(`Salut, ${userName}! Sunt asistentul tău pentru grădinărit. Poți să îmi pui întrebări despre plante sau să îmi trimiți poze cu problemele tale din grădină.`, 'bot');
        }, 500);
    } else {
        console.log('Name input is empty');
        showToast('Te rog să introduci numele tău.', 'error');
        nameInput.focus();
    }
}

function updateReferralDisplay() {
    const refCode = localStorage.getItem('ref_code');
    const refCodeElement = document.getElementById('ref-code');
    if (refCodeElement && refCode) {
        refCodeElement.textContent = refCode;
        console.log('Referral code updated:', refCode);
    }
}

// Chat Functions
function addMessage(text, sender, imageData = null) {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) {
        console.error('Chat messages container not found');
        return;
    }
    
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
    console.log('Message added:', text.substring(0, 50) + '...');
}

function showTypingIndicator() {
    const chatMessages = document.getElementById('chat-messages');
    if (!chatMessages) return;
    
    // Remove existing typing indicator if any
    hideTypingIndicator();
    
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

function autoResizeInput() {
    const textInput = document.getElementById('text-input');
    if (textInput) {
        textInput.style.height = 'auto';
        textInput.style.height = textInput.scrollHeight + 'px';
    }
}

function toggleSendIcon() {
    const textInput = document.getElementById('text-input');
    const sendIcon = document.getElementById('send-icon');
    const sendBtn = document.getElementById('send-btn');
    if (textInput && sendIcon) {
        sendIcon.classList.add('icon-hidden');
        setTimeout(() => {
            if (textInput.value.trim().length > 0) {
                sendIcon.src = 'icons/send.svg';
                sendBtn.removeEventListener('click', startVoiceRecognition);
                sendBtn.addEventListener('click', handleUserMessage);
            } else {
                sendIcon.src = 'icons/microphone.svg';
                sendBtn.removeEventListener('click', handleUserMessage);
                sendBtn.addEventListener('click', startVoiceRecognition);
            }
            sendIcon.classList.remove('icon-hidden');
        }, 150);
    }
}

function initSpeechRecognition() {
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRecognition) {
        console.warn('Speech recognition not supported');
        return;
    }
    recognition = new SpeechRecognition();
    recognition.lang = 'ro-RO';
    recognition.interimResults = false;
    recognition.onstart = () => {
        isRecording = true;
        document.getElementById('send-icon').classList.add('recording');
    };
    recognition.onend = () => {
        isRecording = false;
        document.getElementById('send-icon').classList.remove('recording');
    };
    recognition.onerror = () => {
        showToast('Eroare la recunoașterea vocală.', 'error');
    };
    recognition.onresult = (e) => {
        const transcript = e.results[0][0].transcript;
        const textInput = document.getElementById('text-input');
        textInput.value = transcript;
        autoResizeInput();
        toggleSendIcon();
        handleUserMessage();
    };
}

function startVoiceRecognition() {
    if (!recognition) {
        showToast('Recunoașterea vocală nu este disponibilă.', 'error');
        return;
    }
    recognition.start();
}

// Message Handling Functions
function handleUserMessage() {
    if (isProcessing) {
        console.log('Already processing, ignoring request');
        return;
    }
    
    const textInput = document.getElementById('text-input');
    if (!textInput) {
        console.error('Text input element not found');
        return;
    }
    
    if (!textInput.value.trim()) {
        console.log('Text input is empty');
        showToast('Te rog să introduci o întrebare.', 'error');
        return;
    }
    
    const message = textInput.value.trim();
    textInput.value = '';
    textInput.style.height = 'auto';
    toggleSendIcon();
    
    if (!UsageTracker.canMakeRequest('text')) {
        showToast('Ai atins limita zilnică de utilizări. Încearcă mâine sau fă upgrade la Premium!', 'error');
        return;
    }
    
    console.log('Handling user message:', message);
    
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
    
    if (file.size > 3 * 1024 * 1024) { // 3MB limit
        showToast('Imaginea este prea mare. Te rog să selectezi o imagine mai mică de 3MB.', 'error');
        return;
    }
    
    if (!UsageTracker.canMakeRequest('image')) {
        showToast('Ai atins limita zilnică de utilizări. Încearcă mâine sau fă upgrade la Premium!', 'error');
        return;
    }
    
    console.log('Handling image upload:', file.name, file.size);
    
    const reader = new FileReader();
    reader.onload = function(e) {
        const imageData = e.target.result;
        
        // Add image message to chat
        addMessage('', 'user', imageData);
        lastImage = imageData;
        
        // Send to API
        sendMessageToAPI('', imageData);
    };
    reader.onerror = function() {
        showToast('Eroare la citirea imaginii. Te rog să încerci din nou.', 'error');
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
        try {
            const blob = dataURLtoBlob(imageData);
            formData.append('image', blob, 'plant_image.jpg');
            UsageTracker.incrementUsage('image');
            console.log('Sending image to API...');
        } catch (error) {
            console.error('Error converting image:', error);
            hideTypingIndicator();
            addMessage('Eroare la procesarea imaginii. Te rog să încerci din nou.', 'bot');
            isProcessing = false;
            return;
        }
    } else {
        formData.append('message', message);
        UsageTracker.incrementUsage('text');
        console.log('Sending text message to API:', message);
    }
    
    fetch(`${BASE_URL}process-image.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('Response status:', response.status);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.text();
    })
    .then(text => {
        console.log('Raw response:', text);
        try {
            const data = JSON.parse(text);
            console.log('Parsed response:', data);

            hideTypingIndicator();

            if (data.success) {
                const responseText = handleAPIResponse(data.response);
                addMessage(responseText, 'bot');
            } else {
                const errorMsg = data.error || 'A apărut o eroare necunoscută.';
                addMessage(`Ne pare rău, ${errorMsg}`, 'bot');
                console.error('API Error:', data.error);
            }
        } catch (parseError) {
            console.error('JSON Parse Error:', parseError);
            console.error('Response text:', text);
            addMessage('Eroare la procesarea răspunsului. Te rog să încerci din nou.', 'bot');
        }
    })
    .catch(error => {
        hideTypingIndicator();
        console.error('Network Error:', error);

        if (error.message.includes('Failed to fetch')) {
            addMessage('Nu pot să mă conectez la server. Verifică conexiunea la internet și încearcă din nou.', 'bot');
        } else if (error.message.includes('HTTP error! status: 413')) {
            addMessage('Imaginea este prea mare. Te rog să folosești o imagine mai mică.', 'bot');
        } else {
            addMessage('Ne pare rău, nu te putem ajuta acum. Te rog să încerci din nou mai târziu.', 'bot');
        }
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

function handleAPIResponse(data) {
    if (!data) {
        throw new Error('Empty response from server');
    }
    if (typeof data === 'string') {
        return data;
    }
    if (typeof data === 'object') {
        if (data.hasOwnProperty('response')) {
            return data.response;
        }
        if (data.choices && data.choices[0] && data.choices[0].message) {
            return data.choices[0].message.content;
        }
        return JSON.stringify(data);
    }
    return String(data);
}

function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        console.log('Toast:', message);
        return;
    }
    
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
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(refCode).then(() => {
                showToast('Codul de referință a fost copiat!', 'success');
            }).catch(() => {
                fallbackCopyTextToClipboard(refCode);
            });
        } else {
            fallbackCopyTextToClipboard(refCode);
        }
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.position = "fixed";

    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();

    try {
        const successful = document.execCommand('copy');
        if (successful) {
            showToast('Codul de referință a fost copiat!', 'success');
        } else {
            showToast('Nu s-a putut copia codul. Încearcă din nou.', 'error');
        }
    } catch (err) {
        showToast('Nu s-a putut copia codul. Încearcă din nou.', 'error');
    }

    document.body.removeChild(textArea);
}

function showInstallPrompt() {
    if (deferredPrompt) {
        const installBtn = document.createElement('button');
        installBtn.textContent = '📱 Instalează aplicația';
        installBtn.className = 'install-btn';
        installBtn.style.cssText = `
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.9rem;
            margin-left: 1rem;
        `;
        
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

function showModal(title, content) {
    const container = document.getElementById('modal-container');
    if (!container) return;
    container.innerHTML = `
        <div class="modal">
            <h2>${title}</h2>
            <div class="modal-body">${content}</div>
            <button class="modal-close">Închide</button>
        </div>`;
    container.style.display = 'flex';
    container.querySelector('.modal-close').addEventListener('click', closeModal);
    container.addEventListener('click', (e) => {
        if (e.target === container) closeModal();
    });
}

function closeModal() {
    const container = document.getElementById('modal-container');
    if (container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }
}

function showPremiumUpgrade() {
    const benefits = `
        <table class="premium-table">
            <thead>
                <tr><th></th><th>Free</th><th>Premium</th></tr>
            </thead>
            <tbody>
                <tr><td>Întrebări text zilnice</td><td>3</td><td>10</td></tr>
                <tr><td>Analize imagini zilnice</td><td>1</td><td>3</td></tr>
                <tr><td>Fără reclame</td><td>❌</td><td>✅</td></tr>
            </tbody>
        </table>
        <p style="margin-top:1rem;">Preț: <strong>4.99 RON/lună</strong> (disponibil curând pe Google Play)</p>
        <button id="upgrade-btn" class="modal-close">OK</button>
    `;
    showModal('Upgrade Premium', benefits);
}

function openMenuModal(action) {
    switch(action) {
        case 'settings':
            showModal('Setări', '<p>Aici vor fi setările aplicației.</p>');
            break;
        case 'help':
            showModal('Ajutor', '<p>Secțiune de ajutor.</p>');
            break;
        case 'social':
            showModal('Rețele Sociale', '<p>Urmărește-ne pe rețelele sociale.</p>');
            break;
        case 'premium':
            showPremiumUpgrade();
            break;
        case 'invite':
            showModal('Invită prieteni', '<p>Trimite codul tău de invitație.</p>');
            break;
        case 'privacy':
            showModal('Politica de confidențialitate', '<p>Informații despre confidențialitate.</p>');
            break;
        default:
            break;
    }
}

// Menu toggle functionality
function setupEventListeners() {
    console.log('Setting up event listeners...');
    
    // Menu toggle
    const navToggle = document.getElementById('nav-toggle');
    const sideMenu = document.getElementById('side-menu');
    const menuOverlay = document.getElementById('menu-overlay');

    const closeMenu = () => {
        sideMenu.classList.remove('active');
        navToggle.classList.remove('active');
        menuOverlay.classList.remove('active');
    };

    if (navToggle && sideMenu && menuOverlay) {
        navToggle.addEventListener('click', () => {
            const isOpen = sideMenu.classList.toggle('active');
            navToggle.classList.toggle('active');
            menuOverlay.classList.toggle('active');
            console.log('Menu toggled');
        });
        menuOverlay.addEventListener('click', closeMenu);
        sideMenu.querySelectorAll('.menu-item').forEach(btn => {
            btn.addEventListener('click', (e) => {
                openMenuModal(e.target.dataset.action);
            });
        });
        console.log('Menu toggle listeners attached');
    } else {
        console.warn('Menu toggle elements not found');
    }

    // Close menu when pressing Esc
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeMenu();
    });
    
    // Setup chat listeners
    setupChatListeners();
    
    // Setup other UI listeners
    setupUIListeners();
}

function setupChatListeners() {
    console.log('Setting up chat listeners...');

    initSpeechRecognition();

    // Send message button
    const sendBtn = document.getElementById('send-btn');
    if (sendBtn) {
        console.log('Send button found');
    } else {
        console.warn('Send button not found');
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
        textInput.addEventListener('input', () => {
            autoResizeInput();
            toggleSendIcon();
        });
        autoResizeInput();
        toggleSendIcon();
        console.log('Text input listener attached');
    } else {
        console.warn('Text input not found');
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
        console.log('Image upload listeners attached');
    } else {
        console.warn('Image upload elements not found');
    }
}

function setupUIListeners() {
    console.log('Setting up UI listeners...');
    
    // Save name button
    const saveNameBtn = document.getElementById('save-name');
    if (saveNameBtn) {
        saveNameBtn.addEventListener('click', saveUserName);
        console.log('Save name button listener attached');
    } else {
        console.warn('Save name button not found');
    }
    
    // Name input enter key
    const nameInput = document.getElementById('user-name');
    if (nameInput) {
        nameInput.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                saveUserName();
            }
        });
        console.log('Name input listener attached');
    } else {
        console.warn('Name input not found');
    }
    
    // Copy referral code button
    const copyRefBtn = document.getElementById('copy-ref');
    if (copyRefBtn) {
        copyRefBtn.addEventListener('click', copyReferralCode);
        console.log('Copy referral button listener attached');
    } else {
        console.warn('Copy referral button not found');
    }
}

// Initialize app when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing GospodApp...');
    
    // Small delay to ensure all elements are rendered
    setTimeout(() => {
        // Initialize systems
        UsageTracker.init();
        Trophies.render();
        setupEventListeners();
        updateReferralDisplay();
        
        // Check if user has a name saved
        const savedName = localStorage.getItem('user_name');
        if (savedName) {
            console.log('Existing user detected:', savedName);
            showChatScreen();
            setTimeout(() => {
                addMessage(`Bun venit înapoi, ${savedName}! Cu ce te pot ajuta astăzi?`, 'bot');
            }, 1000);
        } else {
            console.log('New user - showing welcome screen');
            showWelcomeScreen();
        }
        
        console.log('GospodApp initialization complete');
    }, 100);
});

// Debug function to check element existence
function debugElements() {
    const elements = [
        'save-name', 'user-name', 'send-btn', 'text-input', 
        'image-btn', 'image-input', 'nav-toggle', 'side-menu',
        'welcome-section', 'chat-section', 'usage-counter',
        'premium-status', 'trophies', 'ref-code', 'copy-ref'
    ];
    
    console.log('Element Debug Check:');
    elements.forEach(id => {
        const element = document.getElementById(id);
        console.log(`${id}: ${element ? 'Found' : 'NOT FOUND'}`);
    });
}

// Expose debug function to global scope for testing
window.debugElements = debugElements;
