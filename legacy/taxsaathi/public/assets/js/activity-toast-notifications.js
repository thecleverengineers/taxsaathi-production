(function (window, document) {
    'use strict';

    const state = {
        config: null,
        timer: null,
        latestId: 0,
        initialized: false,
        firstLoadComplete: false,
        polling: false,
        items: [],
    };

    const toneClasses = {
        info: {
            icon: 'bg-blue-50 text-blue-700 ring-blue-100',
            border: 'border-blue-200',
            accent: 'bg-blue-500',
        },
        success: {
            icon: 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            border: 'border-emerald-200',
            accent: 'bg-emerald-500',
        },
        amber: {
            icon: 'bg-amber-50 text-amber-700 ring-amber-100',
            border: 'border-amber-200',
            accent: 'bg-amber-500',
        },
        danger: {
            icon: 'bg-rose-50 text-rose-700 ring-rose-100',
            border: 'border-rose-200',
            accent: 'bg-rose-500',
        },
        violet: {
            icon: 'bg-violet-50 text-violet-700 ring-violet-100',
            border: 'border-violet-200',
            accent: 'bg-violet-500',
        },
        slate: {
            icon: 'bg-slate-100 text-slate-700 ring-slate-200',
            border: 'border-slate-200',
            accent: 'bg-slate-500',
        },
    };

    const elements = {};

    function cacheElements() {
        elements.wrap = document.getElementById('tsNotificationWrap');
        elements.button = document.getElementById('tsNotificationButton');
        elements.badge = document.getElementById('tsNotificationBadge');
        elements.panel = document.getElementById('tsNotificationPanel');
        elements.list = document.getElementById('tsNotificationList');
        elements.empty = document.getElementById('tsNotificationEmpty');
        elements.markAll = document.getElementById('tsNotificationMarkAll');
        elements.refresh = document.getElementById('tsNotificationRefresh');
        elements.status = document.getElementById('tsNotificationConnectionStatus');
        elements.toastRegion = document.getElementById('tsNotificationToastRegion');
    }

    function setStatus(label, mode) {
        if (!elements.status) {
            return;
        }

        const classes = {
            live: ['bg-emerald-50', 'text-emerald-700'],
            syncing: ['bg-amber-50', 'text-amber-700'],
            offline: ['bg-rose-50', 'text-rose-700'],
        };

        elements.status.className = 'inline-flex items-center rounded-full px-2.5 py-1 text-[10px] font-bold';
        (classes[mode] || classes.syncing).forEach((className) => elements.status.classList.add(className));
        elements.status.textContent = label;
    }

    function updateBadge(count) {
        if (!elements.badge) {
            return;
        }

        const total = Math.max(0, Number(count || 0));
        elements.badge.textContent = total > 99 ? '99+' : String(total);
        elements.badge.classList.toggle('hidden', total === 0);
        elements.button && elements.button.setAttribute('aria-label', total > 0
            ? `Open notifications, ${total} unread`
            : 'Open notifications');
    }

    function createElement(tag, className, text) {
        const element = document.createElement(tag);

        if (className) {
            element.className = className;
        }

        if (typeof text === 'string') {
            element.textContent = text;
        }

        return element;
    }

    function itemTone(item) {
        return toneClasses[item.tone] || toneClasses.info;
    }

    function renderList(items) {
        state.items = Array.isArray(items) ? items : [];

        if (!elements.list || !elements.empty) {
            return;
        }

        elements.list.innerHTML = '';
        elements.empty.classList.toggle('hidden', state.items.length > 0);

        state.items.forEach((item) => {
            const tone = itemTone(item);
            const row = createElement(
                'button',
                `group relative flex w-full items-start gap-3 border-b border-slate-100 px-4 py-4 text-left transition hover:bg-slate-50 ${item.is_read ? 'bg-white' : 'bg-blue-50/35'}`
            );
            row.type = 'button';
            row.dataset.notificationId = String(item.id || 0);
            row.dataset.notificationUrl = item.url || '';

            const unreadBar = createElement(
                'span',
                `absolute inset-y-3 left-0 w-1 rounded-r-full ${item.is_read ? 'bg-transparent' : tone.accent}`
            );
            row.appendChild(unreadBar);

            const icon = createElement(
                'span',
                `inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg ring-1 ${tone.icon}`,
                item.icon || '🔔'
            );
            row.appendChild(icon);

            const content = createElement('span', 'min-w-0 flex-1');
            const top = createElement('span', 'flex items-start justify-between gap-3');
            const title = createElement(
                'span',
                `block truncate text-[13px] ${item.is_read ? 'font-bold text-slate-800' : 'font-black text-slate-950'}`,
                item.title
            );
            const time = createElement('span', 'shrink-0 text-[10px] font-semibold text-slate-400', item.time_ago || 'Just now');
            top.append(title, time);

            const message = createElement('span', 'mt-1.5 block text-xs leading-5 text-slate-600', item.message);
            const meta = createElement('span', 'mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400');

            if (item.order_no) {
                meta.appendChild(createElement('span', 'rounded-full bg-slate-100 px-2 py-1 text-slate-600', item.order_no));
            }

            if (item.category) {
                meta.appendChild(createElement('span', '', item.category));
            }

            if (!item.is_read) {
                meta.appendChild(createElement('span', 'rounded-full bg-blue-100 px-2 py-1 text-blue-700', 'Unread'));
            }

            content.append(top, message, meta);
            row.appendChild(content);

            row.addEventListener('click', async function () {
                if (!item.is_read) {
                    await markRead(item.id, false);
                }

                if (item.url) {
                    window.location.href = item.url;
                }
            });

            elements.list.appendChild(row);
        });
    }

    function showToast(item) {
        if (!elements.toastRegion || !item) {
            return;
        }

        const tone = itemTone(item);
        const toast = createElement(
            'div',
            `pointer-events-auto relative w-full translate-y-3 overflow-hidden rounded-[20px] border bg-white opacity-0 shadow-[0_24px_70px_rgba(15,23,42,.20)] transition duration-300 ${tone.border}`
        );

        toast.setAttribute('role', 'status');
        toast.setAttribute('aria-live', 'polite');

        const accent = createElement('span', `absolute inset-y-0 left-0 w-1.5 ${tone.accent}`);
        const inner = createElement('div', 'flex items-start gap-3 p-4 pl-5');
        const icon = createElement(
            'div',
            `inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-lg ring-1 ${tone.icon}`,
            item.icon || '🔔'
        );
        const body = createElement('div', 'min-w-0 flex-1');
        const eyebrow = createElement('div', 'text-[10px] font-black uppercase tracking-[0.18em] text-slate-400', item.category);
        const title = createElement('div', 'mt-1 text-sm font-black text-slate-900', item.title);
        const message = createElement('div', 'mt-1 text-xs leading-5 text-slate-600', item.message);
        const footer = createElement('div', 'mt-2 flex flex-wrap items-center gap-2 text-[10px] font-bold text-slate-400');

        if (item.order_no) {
            footer.appendChild(createElement('span', 'rounded-full bg-slate-100 px-2 py-1 text-slate-600', item.order_no));
        }

        footer.appendChild(createElement('span', '', item.time_ago || 'Just now'));
        body.append(eyebrow, title, message, footer);

        const close = createElement('button', 'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-slate-700', '×');
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.addEventListener('click', function (event) {
            event.stopPropagation();
            removeToast(toast);
        });

        inner.append(icon, body, close);
        toast.append(accent, inner);

        toast.addEventListener('click', async function (event) {
            if (event.target === close) {
                return;
            }

            await markRead(item.id, false);

            if (item.url) {
                window.location.href = item.url;
            }
        });

        elements.toastRegion.prepend(toast);

        while (elements.toastRegion.children.length > 4) {
            elements.toastRegion.lastElementChild.remove();
        }

        window.requestAnimationFrame(function () {
            toast.classList.remove('translate-y-3', 'opacity-0');
        });

        window.setTimeout(function () {
            removeToast(toast);
        }, 7000);
    }

    function removeToast(toast) {
        if (!toast || !toast.isConnected) {
            return;
        }

        toast.classList.add('translate-y-3', 'opacity-0');
        window.setTimeout(function () {
            toast.remove();
        }, 300);
    }

    async function request(url, options) {
        const response = await fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }, options || {}));

        const payload = await response.json().catch(function () {
            return { ok: false, message: 'Invalid server response.' };
        });

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'Request failed.');
        }

        return payload;
    }

    function formBody(values) {
        const body = new URLSearchParams();

        Object.keys(values || {}).forEach(function (key) {
            body.set(key, String(values[key]));
        });

        if (state.config && state.config.csrfToken) {
            body.set('_csrf', state.config.csrfToken);
        }

        return body.toString();
    }

    async function markRead(id, refreshAfter) {
        if (!state.config || !state.config.markReadUrl || !id) {
            return;
        }

        try {
            const payload = await request(state.config.markReadUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formBody({ id: id }),
            });

            updateBadge(payload.unread_count);

            state.items = state.items.map(function (item) {
                return Number(item.id) === Number(id)
                    ? Object.assign({}, item, { is_read: true })
                    : item;
            });

            renderList(state.items);

            if (refreshAfter) {
                await poll(false);
            }
        } catch (error) {
            console.error('Unable to mark notification read:', error);
        }
    }

    async function markAllRead() {
        if (!state.config || !state.config.markAllReadUrl) {
            return;
        }

        if (elements.markAll) {
            elements.markAll.disabled = true;
            elements.markAll.textContent = 'Updating…';
        }

        try {
            const payload = await request(state.config.markAllReadUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formBody({}),
            });

            state.items = state.items.map(function (item) {
                return Object.assign({}, item, { is_read: true });
            });
            renderList(state.items);
            updateBadge(payload.unread_count || 0);
        } catch (error) {
            console.error('Unable to mark all notifications read:', error);
        } finally {
            if (elements.markAll) {
                elements.markAll.disabled = false;
                elements.markAll.textContent = 'Mark all read';
            }
        }
    }

    async function poll(showLoading) {
        if (!state.config || !state.config.pollUrl || state.polling || document.hidden) {
            return;
        }

        state.polling = true;

        if (showLoading) {
            setStatus('Syncing', 'syncing');
        }

        try {
            const url = new URL(state.config.pollUrl, window.location.origin);
            url.searchParams.set('limit', String(state.config.limit || 30));

            if (state.latestId > 0) {
                url.searchParams.set('since_id', String(state.latestId));
            }

            const payload = await request(url.toString());

            if (payload.source_table !== 'activity_logs' || payload.delivery !== 'activity_logs_only_toast') {
                throw new Error('Rejected notification payload: activity_logs is the only permitted source.');
            }

            const items = Array.isArray(payload.items)
                ? payload.items.filter((item) => Number(item && item.id) > 0 && String(item.action || '') !== '' && String(item.message) !== '')
                : [];
            const newItems = Array.isArray(payload.new_items)
                ? payload.new_items.filter((item) => Number(item && item.id) > 0 && String(item.action || '') !== '' && String(item.message) !== '')
                : [];

            renderList(items);
            updateBadge(payload.unread_count);

            if (state.firstLoadComplete) {
                newItems.forEach(function (item) {
                    showToast(item);
                });
            }

            state.latestId = Math.max(state.latestId, Number(payload.latest_id || 0));
            state.firstLoadComplete = true;
            setStatus('Live', 'live');

            if (Number(payload.poll_interval_ms || 0) >= 5000) {
                state.config.pollIntervalMs = Number(payload.poll_interval_ms);
            }
        } catch (error) {
            setStatus('Offline', 'offline');
            console.error('Notification polling failed:', error);
        } finally {
            state.polling = false;
            scheduleNextPoll();
        }
    }

    function scheduleNextPoll() {
        window.clearTimeout(state.timer);

        if (!state.config) {
            return;
        }

        state.timer = window.setTimeout(function () {
            poll(false);
        }, Math.max(5000, Number(state.config.pollIntervalMs || 12000)));
    }

    function bindEvents() {
        if (elements.button && elements.panel && elements.wrap) {
            elements.button.addEventListener('click', function (event) {
                event.stopPropagation();
                elements.panel.classList.toggle('hidden');
            });

            document.addEventListener('click', function (event) {
                if (!elements.wrap.contains(event.target)) {
                    elements.panel.classList.add('hidden');
                }
            });
        }

        elements.markAll && elements.markAll.addEventListener('click', markAllRead);
        elements.refresh && elements.refresh.addEventListener('click', function () {
            poll(true);
        });

        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                poll(true);
            }
        });

        window.addEventListener('focus', function () {
            poll(false);
        });
    }

    function init(config) {
        if (state.initialized) {
            return;
        }

        state.config = Object.assign({
            limit: 30,
            pollIntervalMs: 12000,
        }, config || {});

        cacheElements();

        if (!elements.button || !elements.panel || !elements.toastRegion) {
            return;
        }

        state.initialized = true;
        bindEvents();
        setStatus('Syncing', 'syncing');
        poll(true);
    }

    window.TaxSaathiActivityNotifications = {
        init: init,
        refresh: function () {
            return poll(true);
        },
    };
}(window, document));