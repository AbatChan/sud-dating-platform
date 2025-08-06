class InternetTracker {
    static STATES = {
        HIDDEN: 'hidden',
        CONNECTING: 'connecting',
        OFFLINE: 'offline',
        ONLINE: 'online',
    };

    constructor(options = {}) {
        this.options = {
            checkUrl: window.location.origin + '/wordpress/sud/assets/img/logo.png',
            checkInterval: 5000,
            onlineDisplayDuration: 3000,
            offlineGracePeriod: 3000,
            ...options
        };

        this.state = InternetTracker.STATES.HIDDEN;
        this.isLockedOffline = false;
        
        this.element = null;
        this.iconElement = null;
        this.textElement = null;

        this.intervalId = null;
        this.hideTimeoutId = null;
        this.abortController = null;

        this.init();
    }

    init() {
        this.createFloatingBox();
        this.addStyles();
        this.setupEventListeners();
        this.startMonitoring();
        setTimeout(() => this.checkConnection(true), 500);
    }

    createFloatingBox() {
        const existing = document.getElementById('sud-internet-tracker');
        if (existing) existing.remove();
        this.element = document.createElement('div');
        this.element.id = 'sud-internet-tracker';
        this.element.innerHTML = `<div class="tracker-content"><div class="tracker-icon"></div><span class="tracker-text"></span></div>`;
        document.body.appendChild(this.element);
        this.iconElement = this.element.querySelector('.tracker-icon');
        this.textElement = this.element.querySelector('.tracker-text');
    }

    addStyles() {
        const styleId = 'sud-internet-tracker-styles';
        if (document.getElementById(styleId)) return;
        const style = document.createElement('style');
        style.id = styleId;
        style.textContent = `
            :root { --tracker-bg-offline: rgba(239, 68, 68, 0.9); --tracker-bg-online: rgba(34, 197, 94, 0.9); --tracker-bg-connecting: rgba(245, 158, 11, 0.9); --tracker-border-offline: rgba(239, 68, 68, 0.3); --tracker-border-online: rgba(34, 197, 94, 0.3); --tracker-border-connecting: rgba(245, 158, 11, 0.3); }
            #sud-internet-tracker { position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%) translateY(calc(100% + 20px)); background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px); color: white; padding: 10px 20px; border-radius: 50px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; font-size: 14px; font-weight: 500; z-index: 9999; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.1); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); opacity: 0; pointer-events: none; display: flex; align-items: center; justify-content: center; }
            #sud-internet-tracker.visible { opacity: 1; transform: translateX(-50%) translateY(0); pointer-events: auto; }
            #sud-internet-tracker.state-online { background-color: var(--tracker-bg-online); border-color: var(--tracker-border-online); }
            #sud-internet-tracker.state-offline { background-color: var(--tracker-bg-offline); border-color: var(--tracker-border-offline); animation: pulse 2s infinite ease-in-out; }
            #sud-internet-tracker.state-connecting { background-color: var(--tracker-bg-connecting); border-color: var(--tracker-border-connecting); }
            .tracker-content { display: flex; align-items: center; gap: 10px; }
            .tracker-icon { width: 20px; height: 20px; display: flex; align-items: center; justify-content: center; }
            .tracker-icon svg { width: 100%; height: 100%; }
            .tracker-text { line-height: 1; }
            .state-connecting .tracker-icon { animation: spin 1s linear infinite; }
            @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
            @keyframes pulse { 50% { transform: translateX(-50%) scale(1.05); } }
            @media (max-width: 480px) { #sud-internet-tracker { left: 15px; right: 15px; transform: translateY(calc(100% + 20px)); width: auto; } #sud-internet-tracker.visible { transform: translateY(0); } @keyframes pulse { 50% { transform: scale(1.02); } } }
        `;
        document.head.appendChild(style);
    }
    
    setupEventListeners() {
        window.addEventListener('online', () => {
            this.isLockedOffline = false;
            this.setState(InternetTracker.STATES.CONNECTING);
        });

        window.addEventListener('offline', () => {
            this.isLockedOffline = true;
            if (this.abortController) this.abortController.abort();
            this.setState(InternetTracker.STATES.OFFLINE);
            setTimeout(() => {
                this.isLockedOffline = false;
            }, this.options.offlineGracePeriod);
        });

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.stopMonitoring();
            } else {
                this.startMonitoring();
                this.checkConnection();
            }
        });
    }

    startMonitoring() {
        if (this.intervalId) return;
        this.intervalId = setInterval(() => this.checkConnection(), this.options.checkInterval);
    }

    stopMonitoring() {
        clearInterval(this.intervalId);
        this.intervalId = null;
    }

    setState(newState) {
        if (this.state === newState) return; 
        this.state = newState;
        clearTimeout(this.hideTimeoutId); 
        this.element.classList.remove('state-online', 'state-offline', 'state-connecting');

        switch (this.state) {
            case InternetTracker.STATES.HIDDEN: 
                this.element.classList.remove('visible');
                break;
            case InternetTracker.STATES.ONLINE:
                this.element.classList.add('visible', 'state-online');
                this.iconElement.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" /></svg>`;
                this.textElement.textContent = 'Connection restored';
                this.hideTimeoutId = setTimeout(() => this.setState(InternetTracker.STATES.HIDDEN), this.options.onlineDisplayDuration);
                break;
            case InternetTracker.STATES.OFFLINE:
                this.element.classList.add('visible', 'state-offline');
                this.iconElement.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728m-12.728 0a9 9 0 010-12.728m12.728 0L5.636 18.364" /></svg>`;
                this.textElement.textContent = 'You are offline';
                break;
            case InternetTracker.STATES.CONNECTING:
                this.element.classList.add('visible', 'state-connecting');
                this.iconElement.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h5M20 20v-5h-5M4 4l16 16" /></svg>`;
                this.textElement.textContent = 'Connecting...';
                this.checkConnection();
                break;
        }
    }

    async checkConnection(isInitialCheck = false) {
        if (this.isLockedOffline || (this.abortController && !this.abortController.signal.aborted)) {
            return;
        }

        this.abortController = new AbortController();
        const { signal } = this.abortController;

        try {
            const response = await fetch(`${this.options.checkUrl}?t=${Date.now()}`, {
                method: 'HEAD',
                cache: 'no-store',
                headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' },
                signal,
            });

            if (!response.ok) {
                throw new Error(`Server responded with status: ${response.status}`);
            }

            if (this.state === InternetTracker.STATES.OFFLINE || this.state === InternetTracker.STATES.CONNECTING) {
                this.setState(InternetTracker.STATES.ONLINE);
            } else if (isInitialCheck) {
                this.setState(InternetTracker.STATES.HIDDEN);
            }

        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }
            
            if (this.state !== InternetTracker.STATES.OFFLINE) {
                this.setState(InternetTracker.STATES.OFFLINE);
            }
        } finally {
            this.abortController = null;
        }
    }
    
    destroy() {
        this.stopMonitoring();
        clearTimeout(this.hideTimeoutId);
        if (this.element) this.element.remove();
        const style = document.getElementById('sud-internet-tracker-styles');
        if (style) style.remove();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.sudInternetTracker = new InternetTracker();
    });
} else {
    window.sudInternetTracker = new InternetTracker();
}

window.InternetTracker = InternetTracker;