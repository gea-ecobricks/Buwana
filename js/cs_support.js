class CsSupportApp {
    constructor(config) {
        this.config = config;
        this.apiBase = config.apiBase.replace(/\/$/, '');
        this.appInboxesEl = document.getElementById('cs-app-inboxes');
        this.adminSectionEl = document.getElementById('cs-admin-section');
        this.adminPersonalEl = document.getElementById('cs-admin-personal');
        this.adminGlobalEl = document.getElementById('cs-admin-global');
        this.newChatButton = document.getElementById('cs-new-chat-btn');
        this.refreshButton = document.getElementById('cs-refresh-btn');
        this.chatModal = document.getElementById('cs-chat-modal');
        this.chatModalClose = this.chatModal?.querySelector('[data-close]');
        this.chatModalTitle = document.getElementById('cs-chat-modal-title');
        this.chatModalSubtitle = document.getElementById('cs-chat-modal-subtitle');
        this.chatMetaForm = document.getElementById('cs-chat-meta-form');
        this.chatMetaPriority = document.getElementById('cs-chat-meta-priority');
        this.chatMetaStatus = document.getElementById('cs-chat-meta-status');
        this.chatMetaCategory = document.getElementById('cs-chat-meta-category');
        this.chatMetaAssigned = document.getElementById('cs-chat-meta-assigned');
        this.chatMetaTags = document.getElementById('cs-chat-meta-tags');
        this.chatMetaCustomTags = document.getElementById('cs-chat-meta-custom-tags');
        this.chatMetaSaveBtn = document.getElementById('cs-chat-meta-save');
        this.chatThreadEl = document.getElementById('cs-chat-thread');
        this.messageForm = document.getElementById('cs-message-form');
        this.messageBody = document.getElementById('cs-message-body');
        this.messageFileInput = document.getElementById('cs-message-attachments');
        this.messagePreview = document.getElementById('cs-message-attachment-preview');
        this.chatModalUpvote = document.getElementById('cs-chat-modal-upvote');
        this.newChatModal = document.getElementById('cs-new-chat-modal');
        this.newChatModalClose = this.newChatModal?.querySelector('[data-close]');
        this.newChatForm = document.getElementById('cs-new-chat-form');
        this.newChatAppSelect = document.getElementById('cs-new-chat-app');
        this.newChatPriority = document.getElementById('cs-new-chat-priority');
        this.newChatTags = document.getElementById('cs-new-chat-tags');
        this.newChatCustomTags = document.getElementById('cs-new-chat-custom-tags');
        this.newChatPreview = document.getElementById('cs-new-chat-attachment-preview');
        this.newChatFileInput = document.getElementById('cs-new-chat-attachments');
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
    }

    init() {
        this.bindEvents();
        this.loadDashboard();
    }

    bindEvents() {
        if (this.newChatButton) {
            this.newChatButton.addEventListener('click', () => this.openNewChatModal());
        }

        if (this.refreshButton) {
            this.refreshButton.addEventListener('click', () => this.loadDashboard());
        }

        if (this.appInboxesEl) {
            this.appInboxesEl.addEventListener('click', (event) => this.handleTableClick(event));
        }

        if (this.adminSectionEl) {
            this.adminSectionEl.addEventListener('click', (event) => this.handleTableClick(event));
        }

        if (this.chatModalClose) {
            this.chatModalClose.addEventListener('click', () => this.closeModal(this.chatModal));
        }

        if (this.chatModal) {
            this.chatModal.addEventListener('click', (event) => {
                if (event.target === this.chatModal) {
                    this.closeModal(this.chatModal);
                }
            });
        }

        if (this.chatMetaForm) {
            this.chatMetaForm.addEventListener('submit', (event) => {
                event.preventDefault();
                this.submitChatMeta();
            });
        }

        if (this.messageForm) {
            this.messageForm.addEventListener('submit', (event) => {
                event.preventDefault();
                this.submitMessage();
            });
        }

        if (this.messageFileInput) {
            this.messageFileInput.addEventListener('change', (event) => {
                this.pendingMessageFiles = Array.from(event.target.files || []);
                this.renderAttachmentPreview(this.pendingMessageFiles, this.messagePreview);
            });
        }

        if (this.chatModalUpvote) {
            this.chatModalUpvote.addEventListener('click', () => {
                if (this.currentChat) {
                    this.toggleUpvote(this.currentChat.id);
                }
            });
        }

        if (this.newChatModalClose) {
            this.newChatModalClose.addEventListener('click', () => this.closeModal(this.newChatModal));
        }

        if (this.newChatModal) {
            this.newChatModal.addEventListener('click', (event) => {
                if (event.target === this.newChatModal) {
                    this.closeModal(this.newChatModal);
                }
            });
        }

        if (this.newChatForm) {
            this.newChatForm.addEventListener('submit', (event) => {
                event.preventDefault();
                this.submitNewChat();
            });
        }

        if (this.newChatFileInput) {
            this.newChatFileInput.addEventListener('change', (event) => {
                this.pendingNewChatFiles = Array.from(event.target.files || []);
                this.renderAttachmentPreview(this.pendingNewChatFiles, this.newChatPreview);
            });
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
            this.meta = data.meta || {};
            this.connectedApps = data.connected_apps || [];
            this.renderAppInboxes(data.app_inboxes || []);

            if (this.adminSectionEl && this.config.isAdmin && data.admin) {
                this.renderAdminInboxes(data.admin);
                this.adminSectionEl.classList.remove('hidden');
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

    renderAppInboxes(appInboxes) {
        if (!this.appInboxesEl) {
            return;
        }

        this.appInboxesEl.innerHTML = '';
        this.tables.clear();
        this.chatRegistry.clear();

        appInboxes.forEach((section) => {
            const { app, chats } = section;
            const wrapper = document.createElement('section');
            wrapper.className = 'cs-inbox';

            const header = document.createElement('div');
            header.className = 'cs-inbox__header';
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

            header.appendChild(title);
            header.appendChild(metaRow);

            const tableId = `cs-chat-table-${app.app_id}-${Math.random().toString(36).slice(2, 7)}`;
            const table = document.createElement('table');
            table.className = 'display cs-chat-table';
            table.id = tableId;

            table.innerHTML = `
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Category</th>
                        <th>Updated</th>
                        <th>Readers</th>
                        <th>Upvotes</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            `;

            wrapper.appendChild(header);
            wrapper.appendChild(table);

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

        if (adminData.personal && this.adminPersonalEl) {
            const tableId = `cs-admin-personal-${Math.random().toString(36).slice(2, 7)}`;
            const table = this.buildAdminTable(tableId, 'Your assigned chats', this.adminPersonalEl);
            this.buildDataTable(table, adminData.personal, { includeAppColumn: true });
        }

        if (adminData.global && this.adminGlobalEl) {
            const tableId = `cs-admin-global-${Math.random().toString(36).slice(2, 7)}`;
            const table = this.buildAdminTable(tableId, 'All app support chats', this.adminGlobalEl);
            this.buildDataTable(table, adminData.global, { includeAppColumn: true });
        }
    }

    buildAdminTable(tableId, title, container) {
        const wrapper = document.createElement('section');
        wrapper.className = 'cs-inbox';
        const header = document.createElement('div');
        header.className = 'cs-inbox__header';
        const hTitle = document.createElement('div');
        hTitle.className = 'cs-inbox__title';
        hTitle.textContent = title;
        header.appendChild(hTitle);
        wrapper.appendChild(header);

        const table = document.createElement('table');
        table.className = 'display cs-chat-table';
        table.id = tableId;
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Title</th>
                    <th>App</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Category</th>
                    <th>Updated</th>
                    <th>Readers</th>
                    <th>Upvotes</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        `;
        wrapper.appendChild(table);
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
                title: 'Title',
                render: (data, type, row) => {
                    if (type === 'display') {
                        return `
                            <div>
                                <strong>${this.escapeHtml(data)}</strong>
                                <div class="cs-row-sub">${this.renderTagList(row.tags)}</div>
                            </div>`;
                    }
                    return data;
                },
            },
        ];

        if (includeAppColumn) {
            columns.push({
                data: 'app',
                title: 'App',
                render: (app, type) => {
                    if (type === 'display') {
                        return `<span class="cs-pill cs-pill--app">${this.escapeHtml(app.app_display_name)}</span>`;
                    }
                    return app.app_display_name;
                },
            });
        }

        columns.push(
            {
                data: 'priority',
                title: 'Priority',
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
                render: (status) => `<span class="cs-pill">${status}</span>`,
            },
            {
                data: 'category',
                title: 'Category',
                render: (category) => (category ? `<span class="cs-pill cs-pill--category">${this.escapeHtml(category)}</span>` : '—'),
            },
            {
                data: 'updated_at',
                title: 'Updated',
                render: (value, type) => {
                    if (type === 'display') {
                        return this.formatDate(value);
                    }
                    return value;
                },
            },
            {
                data: 'readers',
                title: 'Readers',
                orderable: false,
                render: (readers) => this.renderReaders(readers),
            },
            {
                data: null,
                title: 'Upvotes',
                render: (row) => {
                    const btnClass = row.has_upvoted ? 'cs-button cs-button--secondary' : 'cs-button';
                    return `
                        <div class="cs-upvote-group">
                            <span>${row.upvote_count}</span>
                            <button type="button" class="${btnClass}" data-chat-upvote="${row.id}">
                                ${row.has_upvoted ? 'Unvote' : 'Upvote'}
                            </button>
                        </div>`;
                },
            },
            {
                data: null,
                title: 'Actions',
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

        const updatedColumnIndex = includeAppColumn ? 5 : 4;
        const table = $(tableElement).DataTable({
            data,
            columns,
            order: [[updatedColumnIndex, 'desc']],
            responsive: true,
            autoWidth: false,
        });

        this.tables.set(tableElement.id, { table, includeAppColumn });
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

    renderTagList(tags) {
        if (!tags || !tags.length) {
            return '';
        }
        return tags.map((tag) => `<span class="cs-pill">#${this.escapeHtml(tag.name)}</span>`).join(' ');
    }

    async openChatModal(chatId) {
        try {
            this.setModalLoading(true);
            const payload = await this.request('get_chat.php', {
                method: 'POST',
                body: { chat_id: chatId },
            });
            const { chat, messages } = payload.data;
            this.currentChat = chat;
            this.currentMessages = messages || [];
            this.populateChatModal();
            this.openModal(this.chatModal);
            const messageIds = this.currentMessages.map((message) => message.id);
            if (messageIds.length) {
                this.markMessagesRead(chatId, messageIds);
            }
        } catch (error) {
            console.error('Unable to open chat', error);
        } finally {
            this.setModalLoading(false);
        }
    }

    populateChatModal() {
        if (!this.currentChat) {
            return;
        }

        if (this.chatModalTitle) {
            this.chatModalTitle.textContent = this.currentChat.title;
        }
        if (this.chatModalSubtitle) {
            const subtitleParts = [
                this.currentChat.app?.app_display_name,
                `Priority: ${this.currentChat.priority}`,
                `Status: ${this.currentChat.status}`,
            ].filter(Boolean);
            this.chatModalSubtitle.textContent = subtitleParts.join(' • ');
        }

        if (this.chatModalUpvote) {
            this.chatModalUpvote.dataset.chatUpvote = this.currentChat.id;
            this.chatModalUpvote.textContent = this.currentChat.has_upvoted ? `Unvote (${this.currentChat.upvote_count})` : `Upvote (${this.currentChat.upvote_count})`;
        }

        if (this.chatMetaPriority) {
            this.populateSelect(this.chatMetaPriority, this.meta.priorities, this.currentChat.priority);
        }

        if (this.chatMetaStatus) {
            this.populateSelect(this.chatMetaStatus, this.meta.statuses, this.currentChat.status);
            if (!this.config.isAdmin) {
                this.chatMetaStatus.disabled = true;
            } else {
                this.chatMetaStatus.disabled = false;
            }
        }

        if (this.chatMetaCategory) {
            this.populateDatalist('cs-category-list', this.meta.categories);
            this.chatMetaCategory.value = this.currentChat.category || '';
        }

        if (this.chatMetaAssigned) {
            this.populateAssigneeSelect();
        }

        if (this.chatMetaTags) {
            this.renderTagSelector(this.chatMetaTags, this.currentChat.tags || []);
        }

        if (this.chatMetaCustomTags) {
            this.chatMetaCustomTags.value = '';
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

        if (this.messageBody) {
            this.messageBody.value = '';
        }
        if (this.messageFileInput) {
            this.messageFileInput.value = '';
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
                this.closeModal(this.newChatModal);
                this.newChatForm.reset();
                if (this.newChatPreview) {
                    this.newChatPreview.innerHTML = '';
                }
                this.pendingNewChatFiles = [];
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
                        this.chatModalUpvote.textContent = response.data.has_upvoted ? `Unvote (${response.data.upvote_count})` : `Upvote (${response.data.upvote_count})`;
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
        if (!this.newChatAppSelect) {
            return;
        }
        this.newChatAppSelect.innerHTML = '';
        this.populateDatalist('cs-category-list', this.meta.categories || []);
        this.connectedApps.forEach((app) => {
            const option = document.createElement('option');
            option.value = String(app.app_id);
            option.textContent = app.app_display_name;
            if (String(app.app_id) === String(this.config.currentAppId)) {
                option.selected = true;
            }
            this.newChatAppSelect.appendChild(option);
        });
        if (!this.connectedApps.length && this.config.currentAppId) {
            const option = document.createElement('option');
            option.value = String(this.config.currentAppId);
            option.textContent = this.config.currentAppName || 'Current app';
            option.selected = true;
            this.newChatAppSelect.appendChild(option);
        }

        this.populateSelect(this.newChatPriority, this.meta.priorities, 'medium');
        if (this.newChatTags) {
            this.renderTagSelector(this.newChatTags, []);
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

    openModal(modal) {
        if (modal) {
            modal.classList.add('cs-modal--open');
        }
    }

    closeModal(modal) {
        if (modal) {
            modal.classList.remove('cs-modal--open');
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

    createMetaText(text) {
        const span = document.createElement('span');
        span.textContent = text;
        return span;
    }

    openNewChatModal() {
        this.populateSelect(this.newChatPriority, this.meta.priorities, 'medium');
        if (this.newChatTags) {
            this.renderTagSelector(this.newChatTags, []);
        }
        if (this.newChatCustomTags) {
            this.newChatCustomTags.value = '';
        }
        if (this.newChatFileInput) {
            this.newChatFileInput.value = '';
        }
        if (this.newChatPreview) {
            this.newChatPreview.innerHTML = '';
        }
        this.pendingNewChatFiles = [];
        this.openModal(this.newChatModal);
    }
}

window.addEventListener('DOMContentLoaded', () => {
    if (!window.csSupportConfig) {
        return;
    }
    const app = new CsSupportApp(window.csSupportConfig);
    app.init();
});
