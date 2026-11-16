/**
 * TopBar の通知ベルアイコンから作動する通知ポップオーバー制御モジュール
 */
export function initNotificationPopover() {
    const root = document.querySelector('[data-notification-popover-root]');
    if (!root) return;

    const trigger = root.querySelector('[data-notification-popover-trigger]');
    const panel = root.querySelector('[data-notification-popover-panel]');
    const topbarBadge = root.querySelector('[data-notification-popover-badge]');
    
    if (!trigger || !panel) return;

    const tabButtons = panel.querySelectorAll('[data-notification-popover-tab]');
    const unreadTabCount = panel.querySelector('[data-notification-popover-unread-count]');
    const markAllBtn = panel.querySelector('[data-notification-popover-mark-all]');
    const loadingState = panel.querySelector('[data-notification-popover-loading]');
    const emptyState = panel.querySelector('[data-notification-popover-empty]');
    const itemsList = panel.querySelector('[data-notification-popover-items]');
    const template = panel.querySelector('[data-notification-popover-row-template]');

    let currentTab = 'all';
    let isOpen = false;

    // CSRF Cookie 確立およびヘッダー取得
    const getCsrfHeaders = () => {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': token || '',
            'X-Requested-With': 'XMLHttpRequest',
        };
    };

    // Sanctum CSRF Cookie 確立（初回の保護 API リクエスト前）
    const ensureSanctumCookie = async () => {
        try {
            await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin' });
        } catch (e) {
            console.error('Failed to establish Sanctum CSRF cookie', e);
        }
    };

    // バッジ件数の更新
    const updateBadgeCount = (count) => {
        const num = parseInt(count, 10) || 0;
        
        if (topbarBadge) {
            if (num <= 0) {
                topbarBadge.classList.add('hidden');
                topbarBadge.textContent = '0';
            } else {
                topbarBadge.classList.remove('hidden');
                topbarBadge.textContent = num > 99 ? '99+' : String(num);
            }
        }
        if (unreadTabCount) {
            unreadTabCount.textContent = String(num);
        }
    };

    // ポップオーバーの表示切り替え
    const togglePopover = (show) => {
        isOpen = typeof show === 'boolean' ? show : !isOpen;

        if (isOpen) {
            panel.style.display = 'flex';
            panel.classList.remove('hidden', 'opacity-0', '-translate-y-1');
            panel.classList.add('flex', 'opacity-100', 'translate-y-0');
            trigger.setAttribute('aria-expanded', 'true');
            fetchNotifications();
        } else {
            panel.classList.remove('opacity-100', 'translate-y-0');
            panel.classList.add('opacity-0', '-translate-y-1');
            trigger.setAttribute('aria-expanded', 'false');
            setTimeout(() => {
                if (!isOpen) {
                    panel.style.display = 'none';
                    panel.classList.add('hidden');
                    panel.classList.remove('flex');
                }
            }, 150);
        }
    };

    // 通知データの取得と描画
    const fetchNotifications = async () => {
        if (!itemsList) return;

        itemsList.innerHTML = '';
        if (emptyState) emptyState.classList.add('hidden');
        if (loadingState) loadingState.classList.remove('hidden');

        try {
            await ensureSanctumCookie();
            const res = await fetch(`/api/v1/notifications?tab=${currentTab}`, {
                method: 'GET',
                headers: getCsrfHeaders(),
                credentials: 'same-origin',
            });

            if (!res.ok) throw new Error(`HTTP error! status: ${res.status}`);

            const data = await res.json();
            updateBadgeCount(data.unread_count);

            if (loadingState) loadingState.classList.add('hidden');

            if (!data.notifications || data.notifications.length === 0) {
                if (emptyState) emptyState.classList.remove('hidden');
                return;
            }

            renderNotifications(data.notifications);

        } catch (error) {
            console.error('Error fetching notifications:', error);
            if (loadingState) loadingState.classList.add('hidden');
            if (emptyState) {
                emptyState.textContent = '通知の取得に失敗しました。';
                emptyState.classList.remove('hidden');
            }
        }
    };

    // 通知行のレンダリング
    const renderNotifications = (notifications) => {
        if (!template || !itemsList) return;

        notifications.forEach(item => {
            const clone = template.content.cloneNode(true);
            const rowAnchor = clone.querySelector('[data-notification-popover-row]');
            const dot = clone.querySelector('[data-notification-popover-row-dot]');
            const titleEl = clone.querySelector('[data-notification-popover-row-title]');
            const messageEl = clone.querySelector('[data-notification-popover-row-message]');
            const timeEl = clone.querySelector('[data-notification-popover-row-time]');

            if (rowAnchor) {
                rowAnchor.href = item.url || '#';
                if (item.is_unread) {
                    rowAnchor.setAttribute('data-unread', 'true');
                } else {
                    rowAnchor.removeAttribute('data-unread');
                }

                // 通知行クリック時の処理（既読化API呼出と遷移）
                rowAnchor.addEventListener('click', async (e) => {
                    if (item.is_unread) {
                        e.preventDefault();
                        try {
                            await markAsRead(item.id);
                        } catch (err) {
                            console.error('Failed to mark notification as read:', err);
                        } finally {
                            window.location.href = item.url;
                        }
                    }
                });
            }

            if (dot && !item.is_unread) {
                dot.classList.add('hidden');
            }
            if (titleEl) titleEl.textContent = item.title;
            if (messageEl) messageEl.textContent = item.message;
            if (timeEl) timeEl.textContent = item.created_at_human;

            itemsList.appendChild(clone);
        });
    };

    // 単一既読化 API 呼出
    const markAsRead = async (id) => {
        const res = await fetch(`/api/v1/notifications/${id}/read`, {
            method: 'POST',
            headers: getCsrfHeaders(),
            credentials: 'same-origin',
        });
        if (res.ok) {
            const data = await res.json();
            updateBadgeCount(data.unread_count);
        }
    };

    // 全件既読化 API 呼出
    const markAllAsRead = async () => {
        try {
            await ensureSanctumCookie();
            const res = await fetch('/api/v1/notifications/read-all', {
                method: 'POST',
                headers: getCsrfHeaders(),
                credentials: 'same-origin',
            });
            if (res.ok) {
                updateBadgeCount(0);
                fetchNotifications();
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    };

    // イベントリスナーの登録
    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        togglePopover();
    });

    // 枠外クリックで閉じる
    document.addEventListener('click', (e) => {
        if (isOpen && !root.contains(e.target)) {
            togglePopover(false);
        }
    });

    // タブ切替
    tabButtons.forEach(button => {
        button.addEventListener('click', (e) => {
            e.stopPropagation();
            const tab = button.getAttribute('data-notification-popover-tab');
            if (tab === currentTab) return;

            currentTab = tab;
            tabButtons.forEach(btn => btn.setAttribute('aria-selected', btn === button ? 'true' : 'false'));
            fetchNotifications();
        });
    });

    // 全件既読ボタン
    if (markAllBtn) {
        markAllBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            markAllAsRead();
        });
    }
}