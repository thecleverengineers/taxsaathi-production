(function (global) {
    'use strict';

    const state = {
        config: null,
        latestId: 0,
        unreadCount: 0,
        seen: new Set(),
        source: null,
        pollTimer: null,
        reconnectTimer: null,
        firebaseMessaging: null,
        serviceWorkerRegistration: null,
        initialized: false,
    };

    const byId = (id) => document.getElementById(id);

    function csrfBody(values) {
        const body = new URLSearchParams();
        Object.entries(values || {}).forEach(([key, value]) => {
            if (Array.isArray(value)) {
                value.forEach((item) => body.append(key + '[]', String(item)));
            } else if (value !== undefined && value !== null) {
                body.append(key, String(value));
            }
        });
        if (state.config.csrfToken) {
            body.set('csrf_token', state.config.csrfToken);
            body.set('_token', state.config.csrfToken);
        }
        return body;
    }

    async function post(url, values) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                'X-CSRF-TOKEN': state.config.csrfToken || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: csrfBody(values),
        });
        const payload = await response.json().catch(() => ({}));
        if (!response.ok || payload.ok === false) {
            throw new Error(payload.message || 'Request failed.');
        }
        return payload;
    }

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function relativeTime(value) {
        const date = new Date(String(value || '').replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return '';
        const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));
        if (seconds < 60) return 'Just now';
        const minutes = Math.floor(seconds / 60);
        if (minutes < 60) return minutes + 'm ago';
        const hours = Math.floor(minutes / 60);
        if (hours < 24) return hours + 'h ago';
        const days = Math.floor(hours / 24);
        if (days < 7) return days + 'd ago';
        return date.toLocaleDateString();
    }

    function severityClasses(severity) {
        switch (String(severity || '').toLowerCase()) {
            case 'critical': return ['bg-rose-100 text-rose-700', 'bg-rose-500'];
            case 'high': return ['bg-orange-100 text-orange-700', 'bg-orange-500'];
            case 'warning': return ['bg-amber-100 text-amber-700', 'bg-amber-500'];
            case 'success': return ['bg-emerald-100 text-emerald-700', 'bg-emerald-500'];
            case 'info': return ['bg-sky-100 text-sky-700', 'bg-sky-500'];
            default: return ['bg-slate-100 text-slate-700', 'bg-slate-400'];
        }
    }

    function updateBadge(count) {
        state.unreadCount = Math.max(0, Number(count || 0));
        const badge = byId('tsNotificationBadge');
        if (!badge) return;
        if (state.unreadCount > 0) {
            badge.textContent = state.unreadCount > 99 ? '99+' : String(state.unreadCount);
            badge.classList.remove('hidden');
        } else {
            badge.textContent = '0';
            badge.classList.add('hidden');
        }
    }

    function setConnectionStatus(text, tone) {
        const el = byId('tsPushConnectionStatus');
        if (!el) return;
        el.textContent = text;
        el.className = 'inline-flex items-center rounded-full px-2 py-1 text-[10px] font-semibold ' + (
            tone === 'online' ? 'bg-emerald-50 text-emerald-700' :
            tone === 'connecting' ? 'bg-amber-50 text-amber-700' :
            'bg-slate-100 text-slate-600'
        );
    }

    function notificationHtml(item) {
        const [badgeClass, dotClass] = severityClasses(item.severity);
        const unreadClass = item.is_read ? 'bg-white' : 'bg-brand-50/60';
        const status = item.status_label
            ? '<span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ' + badgeClass + '">' + escapeHtml(item.status_label.replaceAll('_', ' ')) + '</span>'
            : '';
        return '<button type="button" data-notification-uid="' + escapeHtml(item.uid) + '" data-notification-url="' + escapeHtml(item.url || '') + '" class="ts-notification-item group flex w-full gap-3 border-b border-slate-100 px-4 py-3 text-left transition hover:bg-slate-50 ' + unreadClass + '">' +
            '<span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full ' + dotClass + '"></span>' +
            '<span class="min-w-0 flex-1">' +
                '<span class="flex items-start justify-between gap-3">' +
                    '<strong class="line-clamp-2 text-[13px] font-bold text-slate-900">' + escapeHtml(item.title) + '</strong>' +
                    '<span class="shrink-0 text-[10px] text-slate-400">' + escapeHtml(relativeTime(item.created_at)) + '</span>' +
                '</span>' +
                '<span class="mt-1 line-clamp-2 block text-xs leading-5 text-slate-500">' + escapeHtml(item.message || '') + '</span>' +
                '<span class="mt-2 flex flex-wrap items-center gap-2">' + status +
                    (item.order_no ? '<span class="text-[10px] font-semibold text-slate-400">' + escapeHtml(item.order_no) + '</span>' : '') +
                '</span>' +
            '</span>' +
        '</button>';
    }

    function bindNotificationItem(element, item) {
        element.addEventListener('click', async function () {
            try {
                if (!item.is_read) {
                    await post(state.config.markReadUrl, { uid: item.uid });
                    item.is_read = true;
                    updateBadge(Math.max(0, state.unreadCount - 1));
                }
            } catch (error) {
                console.warn('Unable to mark notification read:', error);
            }
            if (item.url) window.location.href = item.url;
        });
    }

    function renderItems(items, replace) {
        const list = byId('tsNotificationList');
        const empty = byId('tsNotificationEmpty');
        if (!list) return;

        if (replace) {
            list.innerHTML = '';
            state.seen.clear();
        }

        const ordered = Array.isArray(items) ? items.slice() : [];
        ordered.sort((a, b) => Number(a.id || 0) - Number(b.id || 0));

        ordered.forEach((item) => {
            if (!item || !item.uid || state.seen.has(item.uid)) return;
            state.seen.add(item.uid);
            state.latestId = Math.max(state.latestId, Number(item.id || 0));
            const holder = document.createElement('div');
            holder.innerHTML = notificationHtml(item);
            const element = holder.firstElementChild;
            if (!element) return;
            bindNotificationItem(element, item);
            list.prepend(element);
        });

        const hasItems = list.children.length > 0;
        if (empty) empty.classList.toggle('hidden', hasItems);
    }

    function showToast(item) {
        const region = byId('tsNotificationToastRegion');
        if (!region) return;
        const [badgeClass, dotClass] = severityClasses(item.severity);
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto w-full max-w-sm translate-y-2 rounded-2xl border border-slate-200 bg-white p-4 opacity-0 shadow-2xl transition duration-200';
        toast.innerHTML = '<div class="flex gap-3">' +
            '<span class="mt-1 h-3 w-3 shrink-0 rounded-full ' + dotClass + '"></span>' +
            '<div class="min-w-0 flex-1"><div class="font-bold text-slate-900">' + escapeHtml(item.title) + '</div>' +
            '<div class="mt-1 text-sm leading-5 text-slate-500">' + escapeHtml(item.message || '') + '</div>' +
            '<div class="mt-2"><span class="rounded-full px-2 py-1 text-[10px] font-semibold ' + badgeClass + '">' + escapeHtml(item.status_label || item.severity || 'new') + '</span></div></div>' +
            '<button type="button" aria-label="Dismiss" class="h-7 w-7 rounded-lg text-slate-400 hover:bg-slate-100">×</button>' +
        '</div>';
        const close = toast.querySelector('button');
        close.addEventListener('click', () => removeToast(toast));
        toast.addEventListener('click', (event) => {
            if (event.target === close) return;
            if (item.url) window.location.href = item.url;
        });
        region.prepend(toast);
        requestAnimationFrame(() => toast.classList.remove('translate-y-2', 'opacity-0'));
        window.setTimeout(() => removeToast(toast), 7000);
    }

    function removeToast(toast) {
        if (!toast || !toast.isConnected) return;
        toast.classList.add('translate-y-2', 'opacity-0');
        window.setTimeout(() => toast.remove(), 220);
    }

    function showForegroundBrowserNotification(item) {
        if (!('Notification' in window) || Notification.permission !== 'granted' || !document.hidden || state.firebaseMessaging) return;
        try {
            const notification = new Notification(item.title || 'Tax Saathi', {
                body: item.message || '',
                icon: state.config.iconUrl || '/favicon.ico',
                badge: state.config.badgeUrl || '/favicon.ico',
                tag: item.uid || undefined,
                data: { url: item.url || state.config.notificationsPageUrl },
            });
            notification.onclick = function () {
                window.focus();
                if (notification.data && notification.data.url) window.location.href = notification.data.url;
                notification.close();
            };
        } catch (error) {
            console.warn('Browser notification failed:', error);
        }
    }

    function receive(item, source) {
        if (!item || !item.uid || state.seen.has(item.uid)) return;
        renderItems([item], false);
        if (!item.is_read) updateBadge(state.unreadCount + 1);
        showToast(item);
        if (source !== 'fcm') showForegroundBrowserNotification(item);
        document.dispatchEvent(new CustomEvent('taxsaathi:notification', { detail: item }));
    }

    async function poll(replace) {
        if (!state.config.pollUrl) return;
        const url = new URL(state.config.pollUrl, window.location.origin);
        if (!replace && state.latestId > 0) url.searchParams.set('after_id', String(state.latestId));
        url.searchParams.set('limit', replace ? '20' : '50');
        const response = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            cache: 'no-store',
        });
        if (!response.ok) throw new Error('Notification poll failed.');
        const payload = await response.json();
        const items = Array.isArray(payload.items) ? payload.items : [];
        if (replace) renderItems(items, true);
        else items.forEach((item) => receive(item, 'poll'));
        updateBadge(payload.unread_count || 0);
        state.latestId = Math.max(state.latestId, Number(payload.latest_id || 0));
    }

    function schedulePoll() {
        clearInterval(state.pollTimer);
        const interval = Math.max(5000, Number(state.config.pollIntervalMs || 15000));
        state.pollTimer = window.setInterval(() => poll(false).catch(() => {}), interval);
    }

    function connectStream() {
        if (!state.config.streamUrl || !('EventSource' in window)) {
            setConnectionStatus('Polling', 'offline');
            schedulePoll();
            return;
        }

        if (state.source) state.source.close();
        clearTimeout(state.reconnectTimer);
        const url = new URL(state.config.streamUrl, window.location.origin);
        if (state.latestId > 0) url.searchParams.set('after_id', String(state.latestId));
        setConnectionStatus('Connecting', 'connecting');
        const source = new EventSource(url.toString(), { withCredentials: true });
        state.source = source;

        source.onopen = function () {
            setConnectionStatus('Live', 'online');
            clearInterval(state.pollTimer);
        };
        source.addEventListener('notification', function (event) {
            try { receive(JSON.parse(event.data), 'sse'); } catch (_) {}
        });
        source.addEventListener('unread', function (event) {
            try { updateBadge(JSON.parse(event.data).count || 0); } catch (_) {}
        });
        source.addEventListener('reconnect', function () {
            source.close();
            state.reconnectTimer = window.setTimeout(connectStream, 1200);
        });
        source.onerror = function () {
            setConnectionStatus('Polling', 'offline');
            source.close();
            schedulePoll();
            clearTimeout(state.reconnectTimer);
            state.reconnectTimer = window.setTimeout(connectStream, 10000);
        };
    }

    function loadScript(src) {
        return new Promise((resolve, reject) => {
            const existing = document.querySelector('script[src="' + src + '"]');
            if (existing) {
                if (global.firebase) resolve();
                else existing.addEventListener('load', resolve, { once: true });
                return;
            }
            const script = document.createElement('script');
            script.src = src;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    function deviceKey() {
        const storageKey = 'taxsaathi_push_device_key';
        let value = localStorage.getItem(storageKey);
        if (!value) {
            value = (global.crypto && crypto.randomUUID)
                ? crypto.randomUUID()
                : 'web-' + Date.now() + '-' + Math.random().toString(16).slice(2);
            localStorage.setItem(storageKey, value);
        }
        return value;
    }

    async function setupFirebase(requestPermission) {
        if (!window.isSecureContext) {
            setPushStatus('Browser push requires HTTPS (localhost is allowed for development).', 'offline');
            return false;
        }
        if (!state.config.pushConfigUrl || !('serviceWorker' in navigator) || !('Notification' in window)) {
            setPushStatus('Push is unavailable in this browser.', 'offline');
            return false;
        }

        const configResponse = await fetch(state.config.pushConfigUrl, { credentials: 'same-origin', cache: 'no-store' });
        const remote = await configResponse.json();
        if (!remote.firebase_enabled) {
            setPushStatus('Firebase is not configured. In-app realtime is still active.', 'offline');
            return false;
        }
        if (!remote.vapid_key) {
            setPushStatus('Firebase VAPID public key is missing.', 'offline');
            return false;
        }

        if (requestPermission && Notification.permission !== 'granted') {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') {
                setPushStatus('Browser notification permission was not granted.', 'offline');
                return false;
            }
        }

        if (Notification.permission !== 'granted') {
            setPushStatus('Enable browser push to receive updates while away.', 'connecting');
            return false;
        }

        await loadScript('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
        await loadScript('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');
        if (!global.firebase.apps.length) global.firebase.initializeApp(remote.firebase);

        const registration = await navigator.serviceWorker.register(remote.service_worker_url || state.config.serviceWorkerUrl, { scope: '/' });
        state.serviceWorkerRegistration = registration;
        const messaging = global.firebase.messaging();
        state.firebaseMessaging = messaging;
        const token = await messaging.getToken({
            vapidKey: remote.vapid_key,
            serviceWorkerRegistration: registration,
        });

        if (!token) {
            setPushStatus('No push token was returned.', 'offline');
            return false;
        }

        await post(state.config.registerTokenUrl, {
            fcm_token: token,
            device_key: deviceKey(),
            platform: 'web',
        });

        messaging.onMessage(function (payload) {
            const data = payload.data || {};
            const notification = payload.notification || {};
            receive({
                id: Number(data.id || 0),
                uid: data.uid || ('fcm-' + Date.now()),
                title: notification.title || data.title || 'Tax Saathi',
                message: notification.body || data.message || '',
                severity: data.severity || 'info',
                status_label: data.status_label || '',
                url: data.url || state.config.notificationsPageUrl,
                created_at: new Date().toISOString(),
                is_read: false,
                order_no: data.order_no || '',
            }, 'fcm');
        });

        setPushStatus('Browser push is enabled on this device.', 'online');
        return true;
    }

    function setPushStatus(text, tone) {
        const status = byId('tsPushStatus');
        if (!status) return;
        status.textContent = text;
        status.className = 'text-xs leading-5 ' + (
            tone === 'online' ? 'text-emerald-700' :
            tone === 'connecting' ? 'text-amber-700' :
            'text-slate-500'
        );
    }

    function bindUi() {
        const button = byId('tsNotificationButton');
        const panel = byId('tsNotificationPanel');
        const wrap = byId('tsNotificationWrap');
        if (button && panel && wrap) {
            button.addEventListener('click', function (event) {
                event.stopPropagation();
                panel.classList.toggle('hidden');
                if (!panel.classList.contains('hidden')) poll(true).catch(() => {});
            });
            document.addEventListener('click', function (event) {
                if (!wrap.contains(event.target)) panel.classList.add('hidden');
            });
        }

        const markAll = byId('tsNotificationMarkAll');
        if (markAll) {
            markAll.addEventListener('click', async function () {
                try {
                    await post(state.config.markAllReadUrl, {});
                    updateBadge(0);
                    document.querySelectorAll('.ts-notification-item').forEach((el) => {
                        el.classList.remove('bg-brand-50/60');
                        el.classList.add('bg-white');
                    });
                } catch (error) {
                    console.warn(error);
                }
            });
        }

        const enable = byId('tsPushEnable');
        if (enable) {
            enable.addEventListener('click', async function () {
                enable.disabled = true;
                try {
                    await setupFirebase(true);
                } catch (error) {
                    setPushStatus(error.message || 'Unable to enable push notifications.', 'offline');
                } finally {
                    enable.disabled = false;
                }
            });
        }

        const test = byId('tsPushTest');
        if (test) {
            test.addEventListener('click', async function () {
                test.disabled = true;
                try {
                    await post(state.config.testUrl, {});
                    setPushStatus('Test notification sent.', 'online');
                } catch (error) {
                    setPushStatus(error.message || 'Unable to send test.', 'offline');
                } finally {
                    test.disabled = false;
                }
            });
        }
    }

    async function init(config) {
        if (state.initialized) return;
        state.initialized = true;
        state.config = Object.assign({ pollIntervalMs: 15000 }, config || {});
        bindUi();

        try {
            await poll(true);
        } catch (_) {
            setConnectionStatus('Offline', 'offline');
        }
        connectStream();

        if ('Notification' in window && Notification.permission === 'granted') {
            setupFirebase(false).catch(() => {});
        } else {
            setPushStatus('Enable browser push to receive updates while away.', 'connecting');
        }

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll(false).catch(() => {});
                if (!state.source || state.source.readyState === EventSource.CLOSED) connectStream();
            }
        });
        global.addEventListener('online', connectStream);
        global.addEventListener('offline', function () { setConnectionStatus('Offline', 'offline'); });
    }

    global.TaxSaathiPush = { init, enable: () => setupFirebase(true), poll: () => poll(true) };
})(window);
