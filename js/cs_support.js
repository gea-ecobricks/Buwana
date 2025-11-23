class CsSupportApp {
    constructor(config) {
        this.config = config;
        this.apiBase = config.apiBase.replace(/\/$/, '');
        this.appInboxesEl = document.getElementById('cs-app-inboxes');
        this.activeAppIconsEl = document.getElementById('cs-active-app-icons');
        this.adminSectionEl = document.getElementById('cs-admin-section');
        this.adminPersonalEl = document.getElementById('cs-admin-personal');
        this.adminGlobalEl = document.getElementById('cs-admin-global');
        this.newChatButton = document.getElementById('cs-new-chat-btn');
        this.refreshButtons = Array.from(document.querySelectorAll('.cs-refresh-btn'));
        const legacyRefresh = document.getElementById('cs-refresh-btn');
        if (legacyRefresh && !this.refreshButtons.includes(legacyRefresh)) {
            this.refreshButtons.push(legacyRefresh);
        }
        this.modalContentBox = document.getElementById('modal-content-box');
        this.chatModalTitle = null;
        this.chatModalSubtitle = null;
        this.chatMetaForm = null;
        this.chatMetaPriority = null;
        this.chatMetaStatus = null;
        this.chatMetaCategory = null;
        this.chatMetaAssigned = null;
        this.chatMetaTags = null;
        this.chatMetaCustomTags = null;
        this.chatMetaSummary = null;
        this.chatMetaSummaryParent = null;
        this.chatThreadEl = null;
        this.messageForm = null;
        this.messageBody = null;
        this.messageFileInput = null;
        this.messagePreview = null;
        this.chatModalUpvote = null;
        this.newChatForm = null;
        this.newChatAppSelect = null;
        this.newChatPriority = null;
        this.newChatTags = null;
        this.newChatCustomTags = null;
        this.newChatPreview = null;
        this.newChatFileInput = null;
        this.loadingIndicator = document.getElementById('cs-loading');

        this.tables = new Map();
        this.chatRegistry = new Map();
        this.connectedApps = [];
        this.meta = {
            priorities: ['low', 'medium', 'high', 'urgent'],
            statuses: ['open', 'in_progress', 'resolved', 'closed'],
            tags: [],
            support_staff: [],
        };
        this.currentChat = null;
        this.currentMessages = [];
        this.pendingMessageFiles = [];
        this.pendingNewChatFiles = [];

        this.handleChatMetaSubmit = (event) => {
            event.preventDefault();
            this.submitChatMeta();
        };
        this.handleMessageSubmit = (event) => {
            event.preventDefault();
            this.submitMessage();
        };
        this.handleMessageFileChange = (event) => {
            this.pendingMessageFiles = Array.from(event.target.files || []);
            this.renderAttachmentPreview(this.pendingMessageFiles, this.messagePreview);
        };
        this.handleModalUpvote = (event) => {
            event.preventDefault();
            if (this.currentChat) {
                this.toggleUpvote(this.currentChat.id);
            }
        };
    }

    init() {
        this.bindEvents();
        this.loadDashboard();
    }

    getCurrentAppIdentifiers() {
        const identifiers = [];
        if (this.config.currentAppId) {
            identifiers.push(String(this.config.currentAppId));
        }
        if (this.config.clientId) {
            identifiers.push(String(this.config.clientId));
        }
        return identifiers;
    }

    filterInboxesByCurrentApp(appInboxes) {
        const identifiers = this.getCurrentAppIdentifiers();
        if (!identifiers.length) {
            return appInboxes || [];
        }

        return (appInboxes || []).filter((section) => {
            const app = section && section.app ? section.app : {};
            const appIdentifiers = [app.app_id, app.client_id, app.id, app.app_name, app.app_display_name]
                .filter((value) => value !== undefined && value !== null)
                .map((value) => String(value));
            return appIdentifiers.some((value) => identifiers.includes(value));
        });
    }

    filterConnectedApps(connectedApps) {
        const identifiers = this.getCurrentAppIdentifiers();
        if (!identifiers.length) {
            return connectedApps || [];
        }

        return (connectedApps || []).filter((app) => {
            const values = [app.app_id, app.client_id, app.id, app.app_name, app.app_display_name]
                .filter((value) => value !== undefined && value !== null)
                .map((value) => String(value));
            return values.some((value) => identifiers.includes(value));
        });
    }

    bindEvents() {
        if (this.newChatButton) {
            this.newChatButton.addEventListener('click', () => this.openNewChatModal());
        }

        if (this.refreshButtons.length) {
            this.refreshButtons.forEach((button) => {
                button.addEventListener('click', () => this.loadDashboard());
            });
        }

        if (this.appInboxesEl) {
            this.appInboxesEl.addEventListener('click', (event) => this.handleTableClick(event));
        }

        if (this.adminSectionEl) {
            this.adminSectionEl.addEventListener('click', (event) => this.handleTableClick(event));
        }

    }

    buildChatModalShell() {
        return `
            <section class="cs-chat-modal">
                <header class="cs-chat-modal__header">
                    <div>
                        <h2 id="cs-chat-modal-title" class="cs-chat-modal__title"></h2>
                        <div id="cs-chat-modal-subtitle" class="cs-chat-modal__subtitle"></div>
                    </div>
                    <button type="button" id="cs-chat-modal-upvote" class="cs-upvote-toggle" data-chat-upvote="" aria-pressed="false" aria-label="Add upvote">
                        <span class="cs-upvote-count">0</span>
                        <span class="cs-upvote-icon" aria-hidden="true">+</span>
                    </button>
                </header>
                <div class="cs-chat-modal__body">
                    <div id="cs-chat-loading" class="cs-loading">
                        <span>Loading chat…</span>
                    </div>
                    <div id="cs-chat-meta-summary" class="cs-chat-meta-summary hidden" aria-live="polite"></div>
                    <form id="cs-chat-meta-form" class="cs-form cs-chat-meta">
                        <div class="cs-form__row">
                            <div class="cs-form__field">
                                <label for="cs-chat-meta-priority">Priority</label>
                                <select id="cs-chat-meta-priority" name="priority"></select>
                            </div>
                            <div class="cs-form__field">
                                <label for="cs-chat-meta-status">Status</label>
                                <select id="cs-chat-meta-status" name="status"></select>
                            </div>
                            <div class="cs-form__field">
                                <label for="cs-chat-meta-category">Category</label>
                                <input type="text" id="cs-chat-meta-category" name="category" list="cs-category-list" placeholder="Select or type a category">
                            </div>
                            <div class="cs-form__field">
                                <label for="cs-chat-meta-assigned">Assigned to</label>
                                <select id="cs-chat-meta-assigned" name="assigned_to"></select>
                            </div>
                        </div>
                        <div class="cs-form__field">
                            <label>Tags</label>
                            <div id="cs-chat-meta-tags" class="cs-tag-list"></div>
                            <input type="text" id="cs-chat-meta-custom-tags" placeholder="Add new tags separated by commas">
                        </div>
                        <button id="cs-chat-meta-save" type="submit" class="cs-button cs-button--secondary" style="align-self:flex-start;">Save updates</button>
                    </form>
                    <div id="cs-chat-thread" class="cs-chat-thread"></div>
                    <form id="cs-message-form" class="cs-message-input">
                        <label for="cs-message-body" class="cs-message-input__label">Reply</label>
                        <textarea id="cs-message-body" name="body" placeholder="Type your response"></textarea>
                        <div class="cs-message-input__actions">
                            <div class="cs-message-input__attachments">
                                <input type="file" id="cs-message-attachments" accept="image/*" multiple>
                                <div id="cs-message-attachment-preview" class="cs-attachment-preview"></div>
                            </div>
                            <button type="submit" class="cs-button">Send reply</button>
                        </div>
                    </form>
                </div>
            </section>
        `;
    }

    initializeChatModalElements() {
        const modalBox = this.modalContentBox || document.getElementById('modal-content-box');
        if (!modalBox) {
            return;
        }

        this.chatModalTitle = modalBox.querySelector('#cs-chat-modal-title');
        this.chatModalSubtitle = modalBox.querySelector('#cs-chat-modal-subtitle');
        this.chatMetaSummary = modalBox.querySelector('#cs-chat-meta-summary');
        this.chatMetaSummaryParent = this.chatMetaSummary ? this.chatMetaSummary.parentElement : null;
        this.chatMetaForm = modalBox.querySelector('#cs-chat-meta-form');
        this.chatMetaPriority = modalBox.querySelector('#cs-chat-meta-priority');
        this.chatMetaStatus = modalBox.querySelector('#cs-chat-meta-status');
        this.chatMetaCategory = modalBox.querySelector('#cs-chat-meta-category');
        this.chatMetaAssigned = modalBox.querySelector('#cs-chat-meta-assigned');
        this.chatMetaTags = modalBox.querySelector('#cs-chat-meta-tags');
        this.chatMetaCustomTags = modalBox.querySelector('#cs-chat-meta-custom-tags');
        this.chatThreadEl = modalBox.querySelector('#cs-chat-thread');
        this.messageForm = modalBox.querySelector('#cs-message-form');
        this.messageBody = modalBox.querySelector('#cs-message-body');
        this.messageFileInput = modalBox.querySelector('#cs-message-attachments');
        this.messagePreview = modalBox.querySelector('#cs-message-attachment-preview');
        this.chatModalUpvote = modalBox.querySelector('#cs-chat-modal-upvote');

        if (this.chatMetaForm) {
            this.chatMetaForm.addEventListener('submit', this.handleChatMetaSubmit);
        }
        if (this.messageForm) {
            this.messageForm.addEventListener('submit', this.handleMessageSubmit);
        }
        if (this.messageFileInput) {
            this.messageFileInput.addEventListener('change', this.handleMessageFileChange);
        }
        if (this.chatModalUpvote) {
            this.chatModalUpvote.addEventListener('click', this.handleModalUpvote);
        }

        this.pendingMessageFiles = [];
    }

    updateUpvoteButton(button, count, hasUpvoted) {
        if (!button) {
            return;
        }
        const safeCount = Number.isFinite(count) ? count : 0;
        const countEl = button.querySelector('.cs-upvote-count');
        const iconEl = button.querySelector('.cs-upvote-icon');
        button.dataset.chatUpvote = this.currentChat ? String(this.currentChat.id) : '';
        button.setAttribute('aria-pressed', hasUpvoted ? 'true' : 'false');
        button.setAttribute('aria-label', hasUpvoted ? 'Remove upvote' : 'Add upvote');
        button.classList.toggle('is-upvoted', Boolean(hasUpvoted));
        button.classList.toggle('cs-upvote-toggle--zero', safeCount === 0);
        if (countEl) {
            countEl.textContent = String(safeCount);
        }
        if (iconEl) {
            iconEl.textContent = hasUpvoted ? '−' : '+';
        }
    }

    handleTableClick(event) {
        const viewBtn = event.target.closest('[data-chat-open]');
        if (viewBtn) {
            const chatId = parseInt(viewBtn.getAttribute('data-chat-open'), 10);
            if (chatId) {
                this.openChatModal(chatId);
            }
            return;
        }

        const upvoteBtn = event.target.closest('[data-chat-upvote]');
        if (upvoteBtn) {
            const chatId = parseInt(upvoteBtn.getAttribute('data-chat-upvote'), 10);
            if (chatId) {
                this.toggleUpvote(chatId);
            }
        }
    }

    async loadDashboard() {
        this.setLoading(true);
        try {
            const payload = await this.request('get_dashboard.php', {
                method: 'POST',
                body: { client_id: this.config.clientId },
            });

            const { data } = payload;
            this.resetTableState();
            this.meta = data.meta || {};
            this.connectedApps = this.filterConnectedApps(data.connected_apps || []);
            const filteredInboxes = this.filterInboxesByCurrentApp(data.app_inboxes || []);
            this.renderAppInboxes(filteredInboxes);
            this.renderActiveAppIcons(filteredInboxes, data.admin || {});

            if (this.adminSectionEl && this.config.isAdmin && data.admin) {
                const hasAdminData = (Array.isArray(data.admin.global) && data.admin.global.length) ||
                    (Array.isArray(data.admin.personal) && data.admin.personal.length);
                if (hasAdminData) {
                    this.renderAdminInboxes(data.admin);
                    this.adminSectionEl.classList.remove('hidden');
                } else {
                    this.adminSectionEl.classList.add('hidden');
                }
            } else if (this.adminSectionEl) {
                this.adminSectionEl.classList.add('hidden');
            }

            this.updateNewChatOptions();
        } catch (error) {
            console.error('Failed to load dashboard', error);
            if (this.appInboxesEl) {
                this.appInboxesEl.innerHTML = `<div class="cs-empty-state">Unable to load chat inbox at this time.</div>`;
            }
        } finally {
            this.setLoading(false);
        }
    }

    resetTableState() {
        this.tables.forEach((entry) => {
            if (entry && entry.table && typeof entry.table.destroy === 'function') {
                entry.table.destroy();
            }
        });
        this.tables.clear();
        this.chatRegistry.clear();
    }

    renderAppInboxes(appInboxes) {
        if (!this.appInboxesEl) {
            return;
        }

        const hasInboxes = Array.isArray(appInboxes) && appInboxes.length > 0;

        this.appInboxesEl.innerHTML = '';

        if (!hasInboxes) {
            this.appInboxesEl.innerHTML = '<div class="cs-empty-state">No personal support chats yet.</div>';
            return;
        }

        appInboxes.forEach((section) => {
            const { app, chats } = section;
            const wrapper = document.createElement('section');
            wrapper.className = 'cs-inbox';

            const header = document.createElement('div');
            header.className = 'cs-inbox__header';

            const heading = document.createElement('div');
            heading.className = 'cs-inbox__heading';

            const title = document.createElement('div');
            title.className = 'cs-inbox__title';
            title.textContent = `${app.is_current ? 'Your' : 'Past'} ${app.app_display_name} Support Chats`;

            const metaRow = document.createElement('div');
            metaRow.className = 'cs-inbox__meta';
            const pill = document.createElement('span');
            pill.className = 'cs-pill cs-pill--app';
            pill.textContent = app.app_display_name;
            metaRow.appendChild(pill);
            metaRow.appendChild(this.createMetaText(`${chats.length} conversation${chats.length === 1 ? '' : 's'}`));

            heading.appendChild(title);
            heading.appendChild(metaRow);
            header.appendChild(heading);

            const iconUrl = this.resolveAssetUrl(app.app_square_icon_url || app.app_icon_url || app.icon_url || '');
            if (iconUrl) {
                const iconWrapper = document.createElement('div');
                iconWrapper.className = 'cs-dashboard__icon';
                const icon = document.createElement('img');
                icon.src = iconUrl;
                icon.alt = `${app.app_display_name} icon`;
                icon.title = app.app_display_name;
                icon.loading = 'lazy';
                iconWrapper.appendChild(icon);
                heading.appendChild(iconWrapper);
            }

            const tableId = `cs-chat-table-${app.app_id}-${Math.random().toString(36).slice(2, 7)}`;
            const table = document.createElement('table');
            table.className = 'display cs-chat-table';
            table.id = tableId;

            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Chat subjects</th>
                        <th>From</th>
                        <th class="col-updated">Updated</th>
                        <th>Priority</th>
                        <th class="col-status">Status</th>
                        <th>Upvotes</th>
                        <th class="col-readers">Readers</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;

            wrapper.appendChild(header);
            const tableContainer = document.createElement('div');
            tableContainer.className = 'cs-chat-table-container';
            tableContainer.appendChild(table);
            wrapper.appendChild(tableContainer);

            if (!chats.length) {
                const empty = document.createElement('div');
                empty.className = 'cs-empty-state';
                empty.textContent = 'No support chats yet. Start a new conversation to reach the team.';
                wrapper.appendChild(empty);
            }

            this.appInboxesEl.appendChild(wrapper);
            this.buildDataTable(table, chats, { includeAppColumn: false });
        });
    }

    renderActiveAppIcons(appInboxes, adminData = {}) {
        if (!this.activeAppIconsEl) {
            return;
        }

        const iconMap = new Map();
        const addApp = (app) => {
            if (!app) {
                return;
            }
            const identifier = app.app_id ?? app.client_id ?? app.id ?? app.app_name ?? app.app_display_name;
            const key = identifier ? String(identifier) : `app-${iconMap.size}`;
            if (iconMap.has(key)) {
                return;
            }
            const displayName = app.app_display_name || app.app_name || 'App';
            const iconUrl = this.resolveAssetUrl(app.app_square_icon_url || app.app_icon_url || app.icon_url || '');
            iconMap.set(key, { name: displayName, iconUrl });
        };

        (appInboxes || []).forEach((section) => {
            if (section && section.app && Array.isArray(section.chats) && section.chats.length) {
                addApp(section.app);
            }
        });

        const appendFromChats = (chats) => {
            (chats || []).forEach((chat) => {
                if (chat && chat.app) {
                    addApp(chat.app);
                }
            });
        };

        appendFromChats(adminData.global);
        appendFromChats(adminData.personal);

        this.activeAppIconsEl.innerHTML = '';

        if (!iconMap.size) {
            const empty = document.createElement('div');
            empty.className = 'cs-dashboard__icons-empty';
            empty.textContent = 'No open app chats';
            this.activeAppIconsEl.appendChild(empty);
            return;
        }

        const apps = Array.from(iconMap.values()).sort((a, b) => a.name.localeCompare(b.name));
        apps.forEach((app) => {
            const wrapper = document.createElement('div');
            wrapper.className = 'cs-dashboard__icon';
            wrapper.title = app.name;
            if (app.iconUrl) {
                const img = document.createElement('img');
                img.src = app.iconUrl;
                img.alt = `${app.name} icon`;
                img.loading = 'lazy';
                wrapper.appendChild(img);
            } else {
                const fallback = document.createElement('span');
                fallback.className = 'cs-dashboard__icon-initials';
                const initials = app.name.trim().charAt(0).toUpperCase() || 'A';
                fallback.textContent = initials;
                wrapper.appendChild(fallback);
            }
            this.activeAppIconsEl.appendChild(wrapper);
        });
    }

    renderAdminInboxes(adminData) {
        if (!this.adminSectionEl) {
            return;
        }

        if (this.adminPersonalEl) {
            this.adminPersonalEl.innerHTML = '';
        }
        if (this.adminGlobalEl) {
            this.adminGlobalEl.innerHTML = '';
        }

        const hasGlobal = Array.isArray(adminData.global) && adminData.global.length;
        const hasPersonal = Array.isArray(adminData.personal) && adminData.personal.length;

        if (!hasGlobal && this.adminGlobalEl) {
            this.adminGlobalEl.innerHTML = '<div class="cs-empty-state">No global support chats available.</div>';
        }

        if (!hasPersonal && this.adminPersonalEl) {
            this.adminPersonalEl.innerHTML = '<div class="cs-empty-state">No assigned support chats yet.</div>';
        }

        if (hasGlobal && this.adminGlobalEl) {
            const table = this.buildAdminTable('cs-admin-global-table', 'All App Support Chats', this.adminGlobalEl);
            this.buildDataTable(table, adminData.global, { includeAppColumn: true });
        }

        if (hasPersonal && this.adminPersonalEl) {
            const table = this.buildAdminTable('cs-admin-personal-table', 'Your Assigned Support Chats', this.adminPersonalEl);
            this.buildDataTable(table, adminData.personal, { includeAppColumn: true });
        }
    }

    buildAdminTable(tableId, title, container) {
        const wrapper = document.createElement('section');
        wrapper.className = 'cs-inbox';
        const header = document.createElement('div');
        header.className = 'cs-inbox__header';
        const heading = document.createElement('div');
        heading.className = 'cs-inbox__heading';
        const hTitle = document.createElement('div');
        hTitle.className = 'cs-inbox__title';
        hTitle.textContent = title;
        heading.appendChild(hTitle);
        header.appendChild(heading);
        wrapper.appendChild(header);

        const table = document.createElement('table');
        table.className = 'display cs-chat-table';
        table.id = tableId;
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Chat subjects</th>
                    <th>From</th>
                    <th class="col-updated">Updated</th>
                    <th>App</th>
                    <th>Priority</th>
                    <th class="col-status">Status</th>
                    <th>Upvotes</th>
                    <th class="col-readers">Readers</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;
        const tableContainer = document.createElement('div');
        tableContainer.className = 'cs-chat-table-container';
        tableContainer.appendChild(table);
        wrapper.appendChild(tableContainer);
        container.appendChild(wrapper);
        return table;
    }

    buildDataTable(tableElement, chats, options = {}) {
        const includeAppColumn = options.includeAppColumn || false;
        const data = chats.map((chat) => {
            this.chatRegistry.set(chat.id, {
                chat,
                tableId: tableElement.id,
            });
            return {
                id: chat.id,
                title: chat.title,
                owner: chat.owner || null,
                app: chat.app,
                priority: chat.priority,
                status: chat.status,
                category: chat.category,
                updated_at: chat.updated_at,
                readers: chat.readers || [],
                upvote_count: chat.upvote_count || 0,
                has_upvoted: chat.has_upvoted,
                tags: chat.tags || [],
            };
        });

        const columns = [
            {
                data: 'title',
                title: 'Chat subjects',
                render: (data, type, row) => {
                    if (type === 'display') {
                        const tagText = this.formatTagText(row.tags);
                        const safeTitle = this.escapeHtml(data);
                        const tooltip = tagText ? ` title="${tagText}"` : '';
                        return `
                            <div class="cs-chat-title">
                                <span class="cs-chat-title__text"${tooltip}>${safeTitle}</span>
                            </div>`;
                    }
                    return data;
                },
            },
            {
                data: 'owner',
                title: 'From',
                className: 'col-owner',
                render: (owner, type) => {
                    const name = owner && (owner.full_name || owner.first_name) ? owner.full_name || owner.first_name : '';
                    if (type !== 'display') {
                        return name;
                    }
                    if (!owner || !name) {
                        return '—';
                    }
                    return `<div class="cs-owner">${this.escapeHtml(name)}</div>`;
                },
            },
            {
                data: 'updated_at',
                title: 'Updated',
                className: 'col-updated',
                render: (value, type) => {
                    if (type === 'display') {
                        return this.formatDate(value);
                    }
                    return value;
                },
            },
        ];

        if (includeAppColumn) {
            columns.push({
                data: 'app',
                title: 'App',
                className: 'col-app',
                render: (app, type) => {
                    const name = app?.app_display_name || app?.app_name || 'App';
                    if (type !== 'display') {
                        return name;
                    }
                    const iconUrl = this.resolveAssetUrl(app?.app_square_icon_url || app?.app_icon_url || app?.icon_url || '');
                    const safeName = this.escapeHtml(name);
                    if (iconUrl) {
                        return `<span class="cs-app-icon" title="${safeName}"><img src="${iconUrl}" alt="${safeName} icon" loading="lazy"></span>`;
                    }
                    return `<span class="cs-pill cs-pill--app" title="${safeName}">${safeName}</span>`;
                },
            });
        }

        columns.push(
            {
                data: 'priority',
                title: 'Priority',
                className: 'col-priority',
                render: (priority, type) => {
                    if (type === 'display') {
                        const klass = `cs-pill cs-pill--priority-${priority}`;
                        return `<span class="${klass}">${priority}</span>`;
                    }
                    return priority;
                },
            },
            {
                data: 'status',
                title: 'Status',
                className: 'col-status',
                render: (status, type) => {
                    if (type === 'display') {
                        const normalized = (status || '').toLowerCase();
                        const displayStatus = this.formatTitleCase(status);
                        let klass = 'cs-pill';
                        if (normalized === 'open') {
                            klass += ' cs-pill--status-open';
                        } else if (normalized === 'closed') {
                            klass += ' cs-pill--status-closed';
                        }
                        return `<span class="${klass}">${this.escapeHtml(displayStatus || status || '')}</span>`;
                    }
                    return status;
                },
            },
            {
                data: 'upvote_count',
                title: 'Upvotes',
                className: 'col-upvotes',
                render: (count, type, row) => {
                    const safeCount = Number.isFinite(count) ? count : 0;
                    if (type !== 'display') {
                        return safeCount;
                    }
                    const isActive = row.has_upvoted;
                    const pressed = isActive ? 'true' : 'false';
                    const label = isActive ? 'Remove upvote' : 'Add upvote';
                    const icon = isActive ? '−' : '+';
                    const classList = ['cs-upvote-toggle'];
                    if (isActive) {
                        classList.push('is-upvoted');
                    }
                    if (safeCount === 0) {
                        classList.push('cs-upvote-toggle--zero');
                    }
                    return `<button type="button" class="${classList.join(' ')}" data-chat-upvote="${row.id}" aria-pressed="${pressed}" aria-label="${label}">
                            <span class="cs-upvote-count">${safeCount}</span>
                            <span class="cs-upvote-icon" aria-hidden="true">${icon}</span>
                        </button>`;
                },
            },
            {
                data: 'readers',
                title: 'Readers',
                className: 'col-readers',
                orderable: false,
                render: (readers, type) => {
                    if (type === 'display') {
                        return this.renderReaders(readers);
                    }
                    return Array.isArray(readers) ? readers.length : 0;
                },
            },
            {
                data: null,
                title: 'Actions',
                className: 'col-actions',
                orderable: false,
                render: (row) => `
                    <button type="button" class="cs-button cs-button--secondary" data-chat-open="${row.id}">
                        View
                    </button>`,
            }
        );

        if ($.fn.DataTable.isDataTable(tableElement)) {
            $(tableElement).DataTable().destroy();
        }

        const updatedColumnIndex = 2;
        const table = $(tableElement).DataTable({
            data,
            columns,
            order: [[updatedColumnIndex, 'desc']],
            responsive: true,
            scrollX: false,
            autoWidth: false,
            columnDefs: [
                {
                    targets: '_all',
                    className: 'cs-table-wrap',
                },
            ],
        });

        this.tables.set(tableElement.id, { table, includeAppColumn });
        this.relocateLengthControl(tableElement);
    }

    relocateLengthControl(tableElement) {
        if (!tableElement || typeof tableElement.insertAdjacentElement !== 'function') {
            return;
        }
        const wrapper = tableElement.closest('.dataTables_wrapper');
        if (!wrapper) {
            return;
        }
        const lengthControl = wrapper.querySelector('.dataTables_length');
        if (lengthControl) {
            tableElement.insertAdjacentElement('afterend', lengthControl);
            lengthControl.classList.add('cs-table-length');
        }
    }

    renderReaders(readers) {
        if (!readers || !readers.length) {
            return '—';
        }
        const avatars = readers.slice(0, 5).map((reader) => {
            const emoji = reader.earthling_emoji || '👤';
            const name = this.escapeHtml(reader.first_name || '');
            return `<span class="cs-chat-readers__avatar" title="${name}">${emoji}</span>`;
        }).join('');
        return `<div class="cs-chat-readers">${avatars}</div>`;
    }

    formatTagText(tags) {
        if (!Array.isArray(tags) || !tags.length) {
            return '';
        }
        return tags
            .map((tag) => `#${this.escapeHtml(tag.name)}`)
            .join(' ');
    }

    async openChatModal(chatId) {
        if (typeof window.openModal !== 'function') {
            console.warn('Modal system is not available');
            return;
        }

        window.openModal(this.buildChatModalShell());
        this.initializeChatModalElements();
        this.setModalLoading(true);

        try {
            const payload = await this.request('get_chat.php', {
                method: 'POST',
                body: { chat_id: chatId },
            });
            const { chat, messages } = payload.data;
            this.currentChat = chat;
            this.currentMessages = messages || [];
            this.populateChatModal();
            const messageIds = this.currentMessages.map((message) => message.id);
            if (messageIds.length) {
                this.markMessagesRead(chatId, messageIds);
            }
        } catch (error) {
            console.error('Unable to open chat', error);
            if (this.chatThreadEl) {
                this.chatThreadEl.innerHTML = '<div class="cs-empty-state">Unable to load chat at this time.</div>';
            }
        } finally {
            this.setModalLoading(false);
        }
    }

    populateChatModal() {
        if (!this.currentChat) {
            return;
        }

        const isAdmin = Boolean(this.config.isAdmin);

        if (this.chatModalTitle) {
            this.chatModalTitle.textContent = this.currentChat.title;
        }
        if (this.chatModalSubtitle) {
            const subtitleParts = [
                this.currentChat.app?.app_display_name,
                this.currentChat.priority ? `Priority: ${this.formatTitleCase(this.currentChat.priority)}` : '',
                this.currentChat.status ? `Status: ${this.formatTitleCase(this.currentChat.status)}` : '',
            ].filter(Boolean);
            this.chatModalSubtitle.textContent = subtitleParts.join(' • ');
        }

        if (this.chatModalUpvote) {
            this.updateUpvoteButton(this.chatModalUpvote, this.currentChat.upvote_count, this.currentChat.has_upvoted);
        }

        if (this.chatMetaSummary) {
            if (isAdmin) {
                if (this.chatMetaSummaryParent && this.chatMetaSummary.parentElement !== this.chatMetaSummaryParent) {
                    this.chatMetaSummaryParent.appendChild(this.chatMetaSummary);
                }
                this.chatMetaSummary.classList.add('hidden');
                this.chatMetaSummary.innerHTML = '';
            } else {
                this.chatMetaSummary.classList.remove('hidden');
                this.renderChatSummary();
                if (this.chatThreadEl && this.chatMetaSummary.parentElement !== this.chatThreadEl) {
                    this.chatThreadEl.prepend(this.chatMetaSummary);
                }
            }
        }

        if (this.chatMetaForm) {
            this.chatMetaForm.classList.toggle('hidden', !isAdmin);
        }

        if (isAdmin) {
            if (this.chatMetaPriority) {
                this.populateSelect(this.chatMetaPriority, this.meta.priorities, this.currentChat.priority);
                this.chatMetaPriority.disabled = false;
            }

            if (this.chatMetaStatus) {
                this.populateSelect(this.chatMetaStatus, this.meta.statuses, this.currentChat.status);
                this.chatMetaStatus.disabled = false;
            }

            if (this.chatMetaCategory) {
                this.populateDatalist('cs-category-list', this.meta.categories);
                this.chatMetaCategory.value = this.currentChat.category || '';
                this.chatMetaCategory.disabled = false;
                this.chatMetaCategory.removeAttribute('readonly');
            }

            if (this.chatMetaAssigned) {
                this.populateAssigneeSelect();
            }

            if (this.chatMetaTags) {
                this.renderTagSelector(this.chatMetaTags, this.currentChat.tags || []);
            }

            if (this.chatMetaCustomTags) {
                this.chatMetaCustomTags.value = '';
                this.chatMetaCustomTags.disabled = false;
            }
        } else {
            if (this.chatMetaPriority) {
                this.chatMetaPriority.innerHTML = '';
                this.chatMetaPriority.disabled = true;
            }

            if (this.chatMetaStatus) {
                this.chatMetaStatus.innerHTML = '';
                this.chatMetaStatus.disabled = true;
            }

            if (this.chatMetaCategory) {
                this.chatMetaCategory.value = this.currentChat.category || '';
                this.chatMetaCategory.disabled = true;
                this.chatMetaCategory.setAttribute('readonly', 'readonly');
            }

            if (this.chatMetaAssigned) {
                this.chatMetaAssigned.innerHTML = '';
                this.chatMetaAssigned.disabled = true;
            }

            if (this.chatMetaTags) {
                this.chatMetaTags.innerHTML = '';
            }

            if (this.chatMetaCustomTags) {
                this.chatMetaCustomTags.value = '';
                this.chatMetaCustomTags.disabled = true;
            }
        }

        if (this.chatThreadEl) {
            this.chatThreadEl.innerHTML = '';
            this.currentMessages.forEach((message) => {
                const card = document.createElement('article');
                card.className = 'cs-chat-message';
                card.innerHTML = `
                    <div class="cs-chat-message__header">
                        <div class="cs-chat-message__author">${this.escapeHtml(message.author.first_name || '')} ${message.author.earthling_emoji || '👤'}</div>
                        <time class="cs-chat-message__timestamp">${this.formatDate(message.created_at)}</time>
                    </div>
                    <div class="cs-chat-message__body">${this.escapeHtml(message.body)}</div>
                `;
                if (message.attachments && message.attachments.length) {
                    const attachmentList = document.createElement('div');
                    attachmentList.className = 'cs-chat-message__attachments';
                    message.attachments.forEach((attachment) => {
                        const item = document.createElement('a');
                        item.href = `../${attachment.file_url}`;
                        item.target = '_blank';
                        item.className = 'cs-chat-message__attachment';
                        item.style.backgroundImage = `url('../${attachment.thumbnail_url}')`;
                        attachmentList.appendChild(item);
                    });
                    card.appendChild(attachmentList);
                }
                this.chatThreadEl.appendChild(card);
            });
        }

        if (this.messageForm) {
            this.messageForm.classList.toggle('hidden', !isAdmin);
        }

        if (this.messageBody) {
            this.messageBody.value = '';
            this.messageBody.disabled = !isAdmin;
        }
        if (this.messageFileInput) {
            this.messageFileInput.value = '';
            this.messageFileInput.disabled = !isAdmin;
        }
        if (this.messagePreview) {
            this.messagePreview.innerHTML = '';
        }
        this.pendingMessageFiles = [];
    }

    populateSelect(selectElement, options, selectedValue) {
        if (!selectElement || !Array.isArray(options)) {
            return;
        }
        selectElement.innerHTML = '';
        options.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = value;
            if (value === selectedValue) {
                option.selected = true;
            }
            selectElement.appendChild(option);
        });
    }

    populateAssigneeSelect() {
        if (!this.chatMetaAssigned) {
            return;
        }
        this.chatMetaAssigned.innerHTML = '';
        const option = document.createElement('option');
        option.value = '0';
        option.textContent = 'Unassigned';
        this.chatMetaAssigned.appendChild(option);
        (this.meta.support_staff || []).forEach((staff) => {
            const opt = document.createElement('option');
            opt.value = String(staff.id);
            opt.textContent = `${staff.first_name || 'Support'} ${staff.earthling_emoji || ''}`;
            if (this.currentChat.assigned_to && staff.id === this.currentChat.assigned_to.id) {
                opt.selected = true;
            }
            this.chatMetaAssigned.appendChild(opt);
        });
        this.chatMetaAssigned.disabled = !this.config.isAdmin;
    }

    renderTagSelector(container, currentTags) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        const selectedSlugs = new Set((currentTags || []).map((tag) => tag.slug));
        (this.meta.tags || []).forEach((tag) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'cs-tag' + (selectedSlugs.has(tag.slug) ? ' cs-tag--selected' : '');
            button.textContent = tag.name;
            button.dataset.slug = tag.slug;
            button.dataset.name = tag.name;
            button.addEventListener('click', () => {
                button.classList.toggle('cs-tag--selected');
            });
            container.appendChild(button);
        });
    }

    formatAssignedDisplay(assigned) {
        if (!assigned) {
            return this.escapeHtml('Unassigned');
        }
        const name = assigned.first_name || assigned.name || 'Support';
        const emoji = assigned.earthling_emoji ? ` ${assigned.earthling_emoji}` : '';
        return this.escapeHtml(`${name}${emoji}`);
    }

    renderChatSummary() {
        if (!this.chatMetaSummary || !this.currentChat) {
            return;
        }
        const summaryItems = [
            { label: 'Priority', value: this.escapeHtml(this.formatTitleCase(this.currentChat.priority) || '') },
            { label: 'Status', value: this.escapeHtml(this.formatTitleCase(this.currentChat.status) || '') },
            { label: 'Category', value: this.currentChat.category ? this.escapeHtml(this.currentChat.category) : '' },
            { label: 'Assigned to', value: this.formatAssignedDisplay(this.currentChat.assigned_to) },
            { label: 'Tags', value: this.formatTagText(this.currentChat.tags) },
        ];

        this.chatMetaSummary.innerHTML = summaryItems
            .map(({ label, value }) => {
                const safeLabel = this.escapeHtml(label);
                const hasValue = typeof value === 'string' ? value.trim() !== '' : Boolean(value);
                const safeValue = hasValue ? value : '—';
                return `<div class="cs-chat-meta-summary__item"><span class="cs-chat-meta-summary__label">${safeLabel}</span><span>${safeValue}</span></div>`;
            })
            .join('');
    }

    populateDatalist(listId, options) {
        if (!options || !options.length) {
            return;
        }
        let dataList = document.getElementById(listId);
        if (!dataList) {
            dataList = document.createElement('datalist');
            dataList.id = listId;
            document.body.appendChild(dataList);
        }
        dataList.innerHTML = '';
        options.forEach((value) => {
            const option = document.createElement('option');
            option.value = value;
            dataList.appendChild(option);
        });
    }

    async submitChatMeta() {
        if (!this.currentChat) {
            return;
        }
        const payload = {
            chat_id: this.currentChat.id,
            priority: this.chatMetaPriority?.value,
            status: this.chatMetaStatus?.value,
            category: this.chatMetaCategory?.value,
            assigned_to: this.chatMetaAssigned ? parseInt(this.chatMetaAssigned.value, 10) || 0 : null,
        };

        const selectedTagButtons = this.chatMetaTags ? Array.from(this.chatMetaTags.querySelectorAll('.cs-tag--selected')) : [];
        const selectedTagNames = selectedTagButtons.map((button) => button.dataset.name);
        const customTags = this.chatMetaCustomTags?.value ? this.chatMetaCustomTags.value.split(',').map((tag) => tag.trim()).filter(Boolean) : [];
        payload.tags = [...selectedTagNames, ...customTags];

        try {
            const response = await this.request('update_chat_meta.php', {
                method: 'POST',
                body: payload,
            });
            if (response.data && response.data.chat) {
                this.currentChat = response.data.chat;
                this.updateChatRow(response.data.chat);
                this.populateChatModal();
            }
        } catch (error) {
            console.error('Failed to update chat meta', error);
        }
    }

    async submitMessage() {
        if (!this.currentChat || !this.messageForm) {
            return;
        }
        const body = this.messageBody?.value.trim();
        if (!body) {
            this.messageBody?.focus();
            return;
        }

        const formData = new FormData();
        formData.append('chat_id', String(this.currentChat.id));
        formData.append('body', body);
        formData.append('language_id', String(this.config.languageId || 0));
        this.pendingMessageFiles.forEach((file) => {
            formData.append('attachments[]', file);
        });

        try {
            const response = await this.request('post_message.php', {
                method: 'POST',
                body: formData,
            });
            if (response.data && response.data.message) {
                this.currentMessages.push(response.data.message);
                if (response.data.chat) {
                    this.currentChat = response.data.chat;
                    this.updateChatRow(response.data.chat);
                }
                this.populateChatModal();
            }
        } catch (error) {
            console.error('Failed to send message', error);
        }
    }

    async submitNewChat() {
        if (!this.newChatForm) {
            return;
        }
        const formData = new FormData(this.newChatForm);
        formData.append('language_id', String(this.config.languageId || 0));
        this.pendingNewChatFiles.forEach((file) => {
            formData.append('attachments[]', file);
        });

        const tags = this.collectTagSelection(this.newChatTags, this.newChatCustomTags);
        formData.append('tags', JSON.stringify(tags));

        try {
            const response = await this.request('create_chat.php', {
                method: 'POST',
                body: formData,
            });
            if (response.data && response.data.chat) {
                this.closeNewChatModal();
                await this.loadDashboard();
                this.openChatModal(response.data.chat.id);
            }
        } catch (error) {
            console.error('Failed to create chat', error);
        }
    }

    collectTagSelection(container, customInput) {
        const tags = [];
        if (container) {
            const selected = Array.from(container.querySelectorAll('.cs-tag--selected'));
            selected.forEach((button) => {
                tags.push(button.dataset.name);
            });
        }
        if (customInput && customInput.value.trim()) {
            customInput.value.split(',').forEach((tag) => {
                const trimmed = tag.trim();
                if (trimmed) {
                    tags.push(trimmed);
                }
            });
        }
        return tags;
    }

    async toggleUpvote(chatId) {
        try {
            const response = await this.request('toggle_upvote.php', {
                method: 'POST',
                body: { chat_id: chatId },
            });
            if (response.data) {
                const registry = this.chatRegistry.get(chatId);
                if (registry) {
                    const { tableId } = registry;
                    const tableEntry = this.tables.get(tableId);
                    if (tableEntry) {
                        const table = tableEntry.table;
                        table.rows().every(function toggleRow() {
                            const data = this.data();
                            if (data.id === chatId) {
                                data.has_upvoted = response.data.has_upvoted;
                                data.upvote_count = response.data.upvote_count;
                                this.data(data).invalidate();
                            }
                        });
                        table.draw(false);
                    }
                }

                if (this.currentChat && this.currentChat.id === chatId) {
                    this.currentChat.has_upvoted = response.data.has_upvoted;
                    this.currentChat.upvote_count = response.data.upvote_count;
                    if (this.chatModalUpvote) {
                        this.updateUpvoteButton(this.chatModalUpvote, response.data.upvote_count, response.data.has_upvoted);
                    }
                }
            }
        } catch (error) {
            console.error('Failed to toggle upvote', error);
        }
    }

    async markMessagesRead(chatId, messageIds) {
        try {
            await this.request('mark_read.php', {
                method: 'POST',
                body: { chat_id: chatId, message_ids: messageIds },
            });
        } catch (error) {
            console.error('Failed to mark read', error);
        }
    }

    updateChatRow(chat) {
        if (!chat) {
            return;
        }
        const registry = this.chatRegistry.get(chat.id);
        if (!registry) {
            return;
        }
        const tableEntry = this.tables.get(registry.tableId);
        if (!tableEntry) {
            return;
        }
        const table = tableEntry.table;
        table.rows().every(function updateRow() {
            const data = this.data();
            if (data.id === chat.id) {
                data.priority = chat.priority;
                data.status = chat.status;
                data.category = chat.category;
                data.updated_at = chat.updated_at;
                data.readers = chat.readers || [];
                data.upvote_count = chat.upvote_count || 0;
                data.has_upvoted = chat.has_upvoted || false;
                data.tags = chat.tags || [];
                this.data(data).invalidate();
            }
        });
        table.draw(false);
        this.chatRegistry.set(chat.id, { chat, tableId: registry.tableId });
    }

    updateNewChatOptions() {
        this.populateDatalist('cs-category-list', this.meta.categories || []);
        if (!this.newChatAppSelect) {
            return;
        }

        this.newChatAppSelect.innerHTML = '';
        this.newChatAppSelect.disabled = false;
        this.newChatAppSelect.required = true;

        if (this.connectedApps.length) {
            this.connectedApps.forEach((app) => {
                const option = document.createElement('option');
                option.value = String(app.app_id);
                option.textContent = app.app_display_name;
                if (String(app.app_id) === String(this.config.currentAppId)) {
                    option.selected = true;
                }
                this.newChatAppSelect.appendChild(option);
            });
        } else if (this.config.currentAppId) {
            const option = document.createElement('option');
            option.value = String(this.config.currentAppId);
            option.textContent = this.config.currentAppName || 'Current app';
            option.selected = true;
            this.newChatAppSelect.appendChild(option);
        } else {
            const option = document.createElement('option');
            option.value = '';
            option.textContent = 'No connected apps available';
            option.disabled = true;
            option.selected = true;
            this.newChatAppSelect.appendChild(option);
            this.newChatAppSelect.disabled = true;
            this.newChatAppSelect.required = false;
        }

        this.populateSelect(this.newChatPriority, this.meta.priorities, 'medium');
        if (this.newChatTags) {
            this.renderTagSelector(this.newChatTags, []);
        }
        if (this.newChatCustomTags) {
            this.newChatCustomTags.value = '';
        }
    }

    renderAttachmentPreview(files, container) {
        if (!container) {
            return;
        }
        container.innerHTML = '';
        files.slice(0, 6).forEach((file) => {
            const url = URL.createObjectURL(file);
            const item = document.createElement('div');
            item.className = 'cs-attachment-preview__item';
            item.style.backgroundImage = `url('${url}')`;
            container.appendChild(item);
        });
    }

    setLoading(isLoading) {
        if (!this.loadingIndicator) {
            return;
        }
        this.loadingIndicator.style.display = isLoading ? 'flex' : 'none';
    }

    setModalLoading(isLoading) {
        const overlay = document.getElementById('cs-chat-loading');
        if (overlay) {
            overlay.style.display = isLoading ? 'flex' : 'none';
        }
    }

    async request(endpoint, options = {}) {
        const url = `${this.apiBase}/${endpoint}`;
        const init = {
            method: options.method || 'GET',
            credentials: 'include',
            headers: options.headers || {},
        };

        if (options.body instanceof FormData) {
            init.body = options.body;
        } else if (options.body && typeof options.body === 'object') {
            init.method = init.method || 'POST';
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(options.body);
        }

        const response = await fetch(url, init);
        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }
        return response.json();
    }

    escapeHtml(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    formatDate(value) {
        if (!value) {
            return '—';
        }
        try {
            const date = new Date(value.replace(' ', 'T'));
            return new Intl.DateTimeFormat(undefined, {
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            }).format(date);
        } catch (error) {
            return value;
        }
    }

    formatTitleCase(value) {
        if (value === null || value === undefined) {
            return '';
        }
        return String(value)
            .toLowerCase()
            .split('_')
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join(' ');
    }

    resolveAssetUrl(value) {
        if (value === null || value === undefined) {
            return '';
        }
        const trimmed = String(value).trim();
        if (trimmed === '') {
            return '';
        }
        if (/^https?:/i.test(trimmed)) {
            return trimmed;
        }
        if (trimmed.startsWith('../')) {
            return trimmed;
        }
        if (trimmed.startsWith('/')) {
            return `..${trimmed}`;
        }
        return `../${trimmed.replace(/^\/+/, '')}`;
    }

    createMetaText(text) {
        const span = document.createElement('span');
        span.textContent = text;
        return span;
    }

    buildNewChatModalContent() {
        return `
            <div class="cs-new-chat-modal">
                <h2 class="cs-new-chat-modal__title">Start a new support chat</h2>
                <form id="cs-new-chat-form" class="cs-form cs-form--stacked cs-new-chat-form">
                    <div class="cs-new-chat-form__fields">
                        <div class="cs-form__field">
                            <label for="cs-new-chat-title">Title</label>
                            <input type="text" id="cs-new-chat-title" name="title" required>
                        </div>
                        <div class="cs-form__field">
                            <label for="cs-new-chat-app">App</label>
                            <select id="cs-new-chat-app" name="app_id" required></select>
                        </div>
                        <div class="cs-form__field">
                            <label for="cs-new-chat-priority">Priority</label>
                            <select id="cs-new-chat-priority" name="priority"></select>
                        </div>
                        <div class="cs-form__field">
                            <label for="cs-new-chat-description">Describe your issue</label>
                            <textarea id="cs-new-chat-description" name="description" required></textarea>
                        </div>
                        <div class="cs-form__field">
                            <label>Tags</label>
                            <div id="cs-new-chat-tags" class="cs-tag-list"></div>
                            <input type="text" id="cs-new-chat-custom-tags" placeholder="Add new tags separated by commas">
                        </div>
                        <div class="cs-form__field">
                            <label for="cs-new-chat-attachments">Attach images</label>
                            <input type="file" id="cs-new-chat-attachments" accept="image/*" multiple>
                            <div id="cs-new-chat-attachment-preview" class="cs-attachment-preview"></div>
                        </div>
                    </div>
                    <div class="cs-new-chat-form__actions">
                        <button type="button" class="submit-button" data-cancel-new-chat>Cancel</button>
                        <button type="submit" class="submit-button enabled">Create chat</button>
                    </div>
                </form>
            </div>
        `;
    }

    initializeNewChatForm() {
        const modalBox = this.modalContentBox || document.getElementById('modal-content-box');
        if (!modalBox) {
            return;
        }

        this.modalContentBox = modalBox;

        this.newChatForm = modalBox.querySelector('#cs-new-chat-form');
        if (!this.newChatForm) {
            return;
        }

        this.newChatAppSelect = this.newChatForm.querySelector('#cs-new-chat-app');
        this.newChatPriority = this.newChatForm.querySelector('#cs-new-chat-priority');
        this.newChatTags = this.newChatForm.querySelector('#cs-new-chat-tags');
        this.newChatCustomTags = this.newChatForm.querySelector('#cs-new-chat-custom-tags');
        this.newChatPreview = this.newChatForm.querySelector('#cs-new-chat-attachment-preview');
        this.newChatFileInput = this.newChatForm.querySelector('#cs-new-chat-attachments');

        this.pendingNewChatFiles = [];
        this.updateNewChatOptions();

        if (this.newChatPreview) {
            this.newChatPreview.innerHTML = '';
        }

        if (this.newChatFileInput) {
            this.newChatFileInput.value = '';
            this.newChatFileInput.addEventListener('change', (event) => {
                this.pendingNewChatFiles = Array.from(event.target.files || []);
                this.renderAttachmentPreview(this.pendingNewChatFiles, this.newChatPreview);
            });
        }

        const cancelButton = this.newChatForm.querySelector('[data-cancel-new-chat]');
        if (cancelButton) {
            cancelButton.addEventListener('click', () => this.closeNewChatModal());
        }

        this.newChatForm.addEventListener('submit', (event) => {
            event.preventDefault();
            this.submitNewChat();
        });

        const titleInput = this.newChatForm.querySelector('#cs-new-chat-title');
        if (titleInput) {
            setTimeout(() => titleInput.focus(), 0);
        }
    }

    closeNewChatModal() {
        if (this.newChatForm) {
            this.newChatForm.reset();
        }
        if (this.newChatPreview) {
            this.newChatPreview.innerHTML = '';
        }
        this.pendingNewChatFiles = [];
        if (typeof window.closeInfoModal === 'function') {
            window.closeInfoModal();
        }
        this.newChatForm = null;
        this.newChatAppSelect = null;
        this.newChatPriority = null;
        this.newChatTags = null;
        this.newChatCustomTags = null;
        this.newChatPreview = null;
        this.newChatFileInput = null;
    }

    openNewChatModal() {
        if (typeof window.openModal !== 'function') {
            console.warn('Modal system is not available');
            return;
        }

        window.openModal(this.buildNewChatModalContent());
        this.initializeNewChatForm();
    }
}

window.addEventListener('DOMContentLoaded', () => {
    if (!window.csSupportConfig) {
        return;
    }
    const app = new CsSupportApp(window.csSupportConfig);
    app.init();
});
