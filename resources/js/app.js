import Alpine from 'alpinejs';
import {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Archive,
    Activity,
    Baby,
    Ban,
    BadgeDollarSign,
    Badge,
    BadgeCheck,
    Bell,
    BellOff,
    BellRing,
    BarChart3,
    Blocks,
    Boxes,
    Bold,
    Bot,
    BookOpen,
    BookOpenCheck,
    Bookmark,
    BookmarkPlus,
    BookPlus,
    BriefcaseMedical,
    Bug,
    Building2,
    Braces,
    Calendar,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    CalendarX,
    ChartColumn,
    ChartNoAxesColumn,
    ChartNoAxesCombined,
    ChartNoAxesColumnIncreasing,
    CheckCheck,
    CheckCircle2,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Church,
    Captions,
    CircleDot,
    Circle,
    CircleAlert,
    CircleCheck,
    CircleHelp,
    CirclePause,
    Clock,
    Clock3,
    ClipboardCheck,
    ClipboardList,
    Cloud,
    CloudCheck,
    CloudDownload,
    Copy,
    CopyPlus,
    Columns3,
    Construction,
    CreditCard,
    Cross,
    Database,
    DoorOpen,
    Download,
    Droplets,
    Ellipsis,
    EllipsisVertical,
    ExternalLink,
    Eye,
    EyeOff,
    FileDown,
    FileChartColumn,
    FileSearch,
    FileText,
    FileWarning,
    Flag,
    Fingerprint,
    Filter,
    FolderPlus,
    Gauge,
    GitBranch,
    GraduationCap,
    Globe2,
    Grip,
    Hand,
    HandCoins,
    HandHeart,
    Handshake,
    HardDrive,
    Headphones,
    Heart,
    HeartHandshake,
    HeartPulse,
    Highlighter,
    History,
    Hourglass,
    Home,
    Image,
    ImagePlus,
    Inbox,
    Info,
    Italic,
    Landmark,
    LayoutDashboard,
    LayoutGrid,
    LayoutList,
    Layers2,
    Layers3,
    Leaf,
    Library,
    LifeBuoy,
    Lightbulb,
    Link,
    List,
    ListChecks,
    ListFilter,
    ListOrdered,
    ListPlus,
    Languages,
    LoaderCircle,
    LogIn,
    LogOut,
    LockKeyhole,
    Mail,
    MailPlus,
    MailX,
    Map,
    MapPin,
    Maximize,
    Megaphone,
    Menu,
    Minimize,
    Minus,
    Moon,
    MessageCircle,
    MessageCircleHeart,
    MessageSquare,
    MessageSquareCheck,
    MessageSquareOff,
    MessageSquareText,
    Milestone,
    MessageCircleQuestion,
    MessagesSquare,
    Mic,
    MicOff,
    Monitor,
    MonitorPlay,
    MonitorUp,
    MoreVertical,
    Music,
    Network,
    NotebookPen,
    NotebookTabs,
    PackageCheck,
    PackagePlus,
    PanelTop,
    Palette,
    Paperclip,
    Pencil,
    Phone,
    PhoneOff,
    PieChart,
    Plus,
    Podcast,
    Play,
    PlugZap,
    Power,
    PowerOff,
    QrCode,
    Receipt,
    ReceiptText,
    Radio,
    RadioTower,
    Repeat2,
    RefreshCw,
    Rocket,
    Route,
    RotateCcw,
    RotateCw,
    Save,
    Scale,
    Search,
    Send,
    Settings,
    ScanFace,
    ScanLine,
    ScanQrCode,
    ScanSearch,
    ScreenShare,
    Share2,
    ShoppingCart,
    Siren,
    SlidersHorizontal,
    ShieldAlert,
    ShieldCheck,
    ShieldX,
    Smartphone,
    Sparkles,
    Star,
    Store,
    Settings2,
    Square,
    SquarePen,
    Tags,
    Tag,
    Target,
    TextCursorInput,
    Timer,
    ToggleRight,
    TrendingUp,
    TriangleAlert,
    Trash2,
    Trophy,
    ThumbsUp,
    Underline,
    UnlockKeyhole,
    User,
    UserCheck,
    UserPlus,
    UserPen,
    UserRound,
    UserRoundCheck,
    UserRoundCog,
    UserX,
    Users,
    UsersRound,
    Upload,
    Video,
    VideoOff,
    Volume2,
    Wallet,
    Webhook,
    Wifi,
    Wrench,
    X,
    Zap,
    Sun,
    Flame,
    GitCompare,
    GitCompareArrows,
    KeyRound,
    Lock,
    createIcons,
} from 'lucide';
let Room = null;
let RoomEvent = null;
let Track = null;
let liveKitModulePromise = null;
let Chart = null;
let chartModulePromise = null;

async function ensureChartModule() {
    if (! chartModulePromise) {
        chartModulePromise = import('chart.js').then((module) => {
            Chart = module.Chart;
            Chart.register(
                module.ArcElement,
                module.BarController,
                module.BarElement,
                module.CategoryScale,
                module.DoughnutController,
                module.Filler,
                module.Legend,
                module.LinearScale,
                module.LineController,
                module.LineElement,
                module.PointElement,
                module.Tooltip,
            );

            return Chart;
        });
    }

    return chartModulePromise;
}

async function ensureLiveKitModule() {
    if (! liveKitModulePromise) {
        liveKitModulePromise = import('livekit-client').then((module) => {
            Room = module.Room;
            RoomEvent = module.RoomEvent;
            Track = module.Track;

            return module;
        });
    }

    return liveKitModulePromise;
}

window.Alpine = Alpine;

function messageAttachmentPicker(initial = {}) {
    return {
        attachments: [],
        ...initial,

        updateAttachments(input) {
            this.releaseAttachmentUrls();
            this.attachments = Array.from(input.files || []).map((file, index) => ({
                index,
                name: file.name,
                size: this.formatFileSize(file.size),
                extension: file.name.includes('.') ? file.name.split('.').pop().slice(0, 5).toUpperCase() : 'FILE',
                image: file.type.startsWith('image/'),
                previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
            }));
        },

        removeAttachment(input, removedIndex) {
            const transfer = new DataTransfer();
            Array.from(input.files || []).forEach((file, index) => {
                if (index !== removedIndex) {
                    transfer.items.add(file);
                }
            });
            input.files = transfer.files;
            this.updateAttachments(input);
        },

        releaseAttachmentUrls() {
            this.attachments.forEach((attachment) => {
                if (attachment.previewUrl) {
                    URL.revokeObjectURL(attachment.previewUrl);
                }
            });
        },

        formatFileSize(bytes) {
            if (bytes < 1024) {
                return `${bytes} B`;
            }
            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(1)} KB`;
            }

            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },
    };
}

document.addEventListener('alpine:init', () => {
    Alpine.data('planImagePreview', () => ({
        previewUrl: null,
        removeCurrent: false,

        selectImage(event) {
            this.releasePreview();
            const [file] = Array.from(event.target.files || []);
            this.previewUrl = file ? URL.createObjectURL(file) : null;
            if (file) {
                this.removeCurrent = false;
            }
        },

        clearImage(input) {
            this.releasePreview();
            input.value = '';
        },

        releasePreview() {
            if (this.previewUrl) {
                URL.revokeObjectURL(this.previewUrl);
                this.previewUrl = null;
            }
        },

        destroy() {
            this.releasePreview();
        },
    }));

    Alpine.data('topbarCounts', (url, notificationCount = 0, messageCount = 0) => ({
        url,
        notificationCount: Number(notificationCount) || 0,
        messageCount: Number(messageCount) || 0,
        timer: null,
        focusHandler: null,
        visibilityHandler: null,

        start() {
            this.refresh();
            this.timer = window.setInterval(() => this.refresh(), 5000);
            this.focusHandler = () => this.refresh();
            this.visibilityHandler = () => this.refreshOnVisible();
            window.addEventListener('focus', this.focusHandler);
            document.addEventListener('visibilitychange', this.visibilityHandler);
        },

        stop() {
            if (this.timer) {
                window.clearInterval(this.timer);
                this.timer = null;
            }
            if (this.focusHandler) {
                window.removeEventListener('focus', this.focusHandler);
            }
            if (this.visibilityHandler) {
                document.removeEventListener('visibilitychange', this.visibilityHandler);
            }
        },

        async refresh() {
            try {
                const response = await fetch(this.url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });

                if (response.status === 401 || response.status === 419) {
                    this.stop();

                    return;
                }

                if (! response.ok) {
                    return;
                }

                const counts = await response.json();
                this.notificationCount = Number(counts.notifications) || 0;
                this.messageCount = Number(counts.messages) || 0;
            } catch {
                // Keep the last server-rendered count when polling is unavailable.
            }
        },

        displayCount(count) {
            return count > 99 ? '99+' : String(count);
        },

        refreshOnVisible() {
            if (document.visibilityState === 'visible') {
                this.refresh();
            }
        },
    }));

    Alpine.data('messageAttachments', () => messageAttachmentPicker());
    Alpine.data('messageReply', () => messageAttachmentPicker({
        html: '',
        plain: '',
        sendOnEnter: false,

        init() {
            this.sendOnEnter = localStorage.getItem('messages.sendOnEnter') === 'true';
        },

        format(command, value = null) {
            this.$refs.editor.focus();
            document.execCommand(command, false, value);
            this.syncMessage();
        },

        insertLink() {
            const url = window.prompt('Enter an HTTPS link');
            if (url && /^https?:\/\//i.test(url)) {
                this.format('createLink', url);
            }
        },

        syncMessage() {
            this.html = this.$refs.editor.innerHTML;
            this.plain = this.$refs.editor.innerText;
        },

        saveSendPreference() {
            localStorage.setItem('messages.sendOnEnter', String(this.sendOnEnter));
        },

        handleEditorKeydown(event) {
            if (event.key !== 'Enter' || event.shiftKey || ! this.sendOnEnter) {
                return;
            }

            event.preventDefault();
            this.syncMessage();

            if (this.plain.trim() || this.attachments.length) {
                this.$root.requestSubmit();
            }
        },
    }));

    Alpine.data('userDirectory', () => ({
        selected: [],
        search: '',
        role: '',
        campus: '',
        status: '',
        inviteOpen: false,
        viewing: null,
        editing: null,
        messaging: null,
        actioning: null,

        visibleRows() {
            return Array.from(document.querySelectorAll('[data-user-row]')).filter(row => this.matches(row));
        },

        visibleIds() {
            return this.visibleRows().map(row => row.dataset.userId);
        },

        visibleCount() {
            return this.visibleIds().length;
        },

        allVisibleSelected() {
            const ids = this.visibleIds();

            return ids.length > 0 && ids.every(id => this.selected.includes(id));
        },

        matches(row) {
            const query = this.search.trim().toLowerCase();
            const roles = (row.dataset.roles || '').split(',').filter(Boolean);

            return (! query || (row.dataset.search || '').includes(query))
                && (! this.role || roles.includes(this.role))
                && (! this.campus || row.dataset.campus === this.campus)
                && (! this.status || row.dataset.status === this.status);
        },

        toggleAll(event) {
            const ids = this.visibleIds();

            this.selected = event.target.checked
                ? Array.from(new Set([...this.selected, ...ids]))
                : this.selected.filter(id => ! ids.includes(id));
        },

        clearFilters() {
            this.search = '';
            this.role = '';
            this.campus = '';
            this.status = '';
        },
    }));

    Alpine.data('workflowBuilder', (initialSteps = []) => ({
        steps: [],

        init() {
            this.steps = (Array.isArray(initialSteps) && initialSteps.length > 0 ? initialSteps : this.defaultSteps())
                .map((step, index) => this.normalizeStep(step, index));
        },

        defaultSteps() {
            return [
                { label: 'Request Submitted', role: 'Requester', mode: 'auto', instructions: 'Capture the request and route it to the first approver.' },
                { label: 'Leader Review', role: 'Ministry Leader', mode: 'required', instructions: 'Review ministry impact, timing, and readiness before final approval.' },
                { label: 'Final Approval', role: 'Administrator', mode: 'required', instructions: 'Confirm policy, capacity, and final authorization.' },
            ];
        },

        normalizeStep(step, index) {
            const mode = step?.mode === 'auto' ? 'auto' : 'required';

            return {
                uid: step?.uid || `${Date.now()}-${index}-${Math.random().toString(16).slice(2)}`,
                label: step?.label || step?.role || '',
                role: step?.role || 'Ministry Leader',
                mode,
                instructions: step?.instructions || '',
            };
        },

        addStep() {
            this.steps.push(this.normalizeStep({
                label: 'Approval Step',
                role: 'Ministry Leader',
                mode: 'required',
                instructions: '',
            }, this.steps.length));
        },

        removeStep(index) {
            if (this.steps.length <= 1) {
                return;
            }

            this.steps.splice(index, 1);
        },

        moveStep(index, direction) {
            const target = index + direction;

            if (target < 0 || target >= this.steps.length) {
                return;
            }

            const [step] = this.steps.splice(index, 1);
            this.steps.splice(target, 0, step);
        },
    }));

    Alpine.data('leadershipReportWizard', (options = {}) => ({
        createOpen: Boolean(options.create_open),
        createStep: 1,
        createTotal: 8,
        periodStart: options.period_start || '',
        periodEnd: options.period_end || '',
        campusId: String(options.campus_id || ''),
        ministryId: String(options.ministry_id || ''),
        manualAttendanceScore: Number(options.attendance_score ?? 90),
        selectedAttendanceSessionIds: (options.selected_attendance_session_ids || []).map(id => String(id)),
        attendanceSources: Array.isArray(options.attendance_sources) ? options.attendance_sources : [],

        nextStep() {
            this.createStep = Math.min(this.createTotal, this.createStep + 1);
        },

        prevStep() {
            this.createStep = Math.max(1, this.createStep - 1);
        },

        filteredAttendanceSources() {
            return this.attendanceSources.filter(source => {
                const date = source.date || '';
                const campusId = String(source.campus_id || '');

                return (! this.periodStart || date >= this.periodStart)
                    && (! this.periodEnd || date <= this.periodEnd)
                    && (! this.campusId || campusId === this.campusId);
            });
        },

        selectedAttendanceSources() {
            return this.attendanceSources.filter(source => this.selectedAttendanceSessionIds.includes(String(source.id)));
        },

        selectedAttendanceTotal() {
            return this.selectedAttendanceSources().reduce((sum, source) => sum + (Number(source.present) || 0), 0);
        },

        selectedAttendanceExpected() {
            return this.selectedAttendanceSources().reduce((sum, source) => sum + (Number(source.expected) || 0), 0);
        },

        calculatedAttendanceScore() {
            const total = this.selectedAttendanceTotal();
            const expected = this.selectedAttendanceExpected();

            return expected > 0 ? Math.min(100, Math.round((total / expected) * 100)) : Math.min(100, total);
        },

        clearUnavailableAttendanceSelections() {
            const visibleIds = this.filteredAttendanceSources().map(source => String(source.id));
            this.selectedAttendanceSessionIds = this.selectedAttendanceSessionIds.filter(id => visibleIds.includes(id));
        },
    }));

    Alpine.data('searchableSelect', (options = {}) => ({
        open: false,
        selected: String(options.selected || ''),
        query: '',
        placeholder: options.placeholder || 'Search...',
        emptyLabel: options.emptyLabel || 'None',
        required: Boolean(options.required),
        options: Array.isArray(options.options) ? options.options : [],

        init() {
            const current = this.options.find(option => String(option.value) === this.selected);
            this.query = current?.label || '';
        },

        filteredOptions() {
            const search = this.query.trim().toLowerCase();

            if (! search || this.selected) {
                return this.options.slice(0, 20);
            }

            return this.options
                .filter(option => (option.search || '').includes(search))
                .slice(0, 20);
        },

        choose(option) {
            this.selected = String(option.value || '');
            this.query = option.label || '';
            this.open = false;
        },

        clear() {
            this.selected = '';
            this.query = '';
            this.open = false;
        },
    }));

    Alpine.data('roleDirectory', initialRoleId => ({
        selectedRole: String(initialRoleId || ''),
        search: '',
        status: '',
        type: '',
        addOpen: false,
        cloneOpen: false,
        menuOpen: null,
        cloneRoleId: String(initialRoleId || ''),
        cloneRoleName: '',

        roleRows() {
            return Array.from(document.querySelectorAll('[data-role-row]'));
        },

        matches(row) {
            const query = this.search.trim().toLowerCase();

            return (! query || (row.dataset.search || '').includes(query))
                && (! this.status || row.dataset.status === this.status)
                && (! this.type || row.dataset.type === this.type);
        },

        visibleCount() {
            return this.roleRows().filter(row => this.matches(row)).length;
        },

        selectRole(id) {
            this.selectedRole = String(id);
            this.menuOpen = null;
        },

        openClone(id, name) {
            this.cloneRoleId = String(id);
            this.cloneRoleName = `Copy of ${name}`;
            this.cloneOpen = true;
            this.menuOpen = null;
        },

        openSelectedClone() {
            const row = this.roleRows().find(item => item.dataset.roleId === this.selectedRole);

            this.openClone(this.selectedRole, row?.dataset.roleName || 'Selected Role');
        },

        clearFilters() {
            this.search = '';
            this.status = '';
            this.type = '';
        },
    }));

    Alpine.data('campusDirectory', (users, assignmentBaseUrl, campusEditorRecords = {}, churchEditorRecords = {}) => ({
        users,
        assignmentBaseUrl,
        campusEditorRecords,
        churchEditorRecords,
        search: '',
        church: '',
        type: '',
        status: '',
        minCapacity: '',
        userSearch: '',
        selectedUserId: String(users[0]?.id || ''),
        selectedChurchId: String(users[0]?.church_id || ''),
        selectedCampusId: String(users[0]?.campus_id || ''),
        selectedRoleId: String(users[0]?.role_ids?.[0] || ''),
        selectedCampusIds: [],
        accessScope: 'single',
        addOpen: false,
        importOpen: false,
        editCampusOpen: false,
        editChurchOpen: false,
        editingCampus: {},
        editingChurch: {},
        moreFiltersOpen: false,
        expandedCampusId: '',

        selectedUser() {
            return this.users.find(user => String(user.id) === String(this.selectedUserId)) || this.users[0] || {};
        },

        assignmentAction() {
            return this.selectedUser().update_url || `${this.assignmentBaseUrl}/${this.selectedUserId}`;
        },

        campusRows() {
            return Array.from(document.querySelectorAll('[data-campus-row]'));
        },

        matchesCampus(row) {
            const query = this.search.trim().toLowerCase();
            const minimumCapacity = Number(this.minCapacity) || 0;
            const capacity = Number(row.dataset.capacity) || 0;

            return (! query || (row.dataset.search || '').includes(query))
                && (! this.church || row.dataset.church === this.church)
                && (! this.type || row.dataset.type === this.type)
                && (! this.status || row.dataset.status === this.status)
                && (! minimumCapacity || capacity >= minimumCapacity);
        },

        visibleCampusCount() {
            return this.campusRows().filter(row => this.matchesCampus(row)).length;
        },

        filteredUsers() {
            const query = this.userSearch.trim().toLowerCase();

            return this.users.filter(user => ! query || user.search.includes(query));
        },

        selectUser(user) {
            this.selectedUserId = String(user.id);
            this.selectedChurchId = String(user.church_id || '');
            this.selectedCampusId = String(user.campus_id || '');
            this.selectedRoleId = String(user.role_ids?.[0] || '');
        },

        toggleCampus(id) {
            this.expandedCampusId = this.expandedCampusId === String(id) ? '' : String(id);
        },

        openCampusEditor(campusKey) {
            const campus = this.campusEditorRecords[campusKey];

            if (! campus) {
                return;
            }

            this.editingCampus = { ...campus };
            this.editCampusOpen = true;
        },

        openChurchEditor(churchKey) {
            const church = this.churchEditorRecords[churchKey];

            if (! church) {
                return;
            }

            this.editingChurch = { ...church };
            this.editChurchOpen = true;
        },

        resetAssignment() {
            this.userSearch = '';
            this.accessScope = 'single';
            if (this.users[0]) {
                this.selectUser(this.users[0]);
            }
        },

        clearFilters() {
            this.search = '';
            this.church = '';
            this.type = '';
            this.status = '';
            this.minCapacity = '';
        },
    }));

    Alpine.data('profilePage', (openEdit = false, openPassword = false) => ({
        tab: 'overview',
        editOpen: Boolean(openEdit),
        passwordOpen: Boolean(openPassword),
        actionOpen: false,
        avatarPreview: null,
        newPassword: '',

        get passwordStrengthScore() {
            if (! this.newPassword) return 0;

            return [
                this.newPassword.length >= 12,
                /[a-z]/.test(this.newPassword) && /[A-Z]/.test(this.newPassword),
                /\d/.test(this.newPassword),
                /[^A-Za-z0-9]/.test(this.newPassword),
            ].filter(Boolean).length;
        },

        get passwordStrengthLabel() {
            if (this.passwordStrengthScore === 4) return 'Strong';
            if (this.passwordStrengthScore >= 2) return 'Needs improvement';

            return this.newPassword ? 'Weak' : 'Not entered';
        },

        get passwordStrengthClass() {
            if (this.passwordStrengthScore === 4) return 'text-emerald-600';
            if (this.passwordStrengthScore >= 2) return 'text-amber-600';

            return this.newPassword ? 'text-rose-600' : 'text-slate-500';
        },

        get passwordStrengthBarClass() {
            if (this.passwordStrengthScore === 4) return 'bg-emerald-600';
            if (this.passwordStrengthScore >= 2) return 'bg-amber-500';

            return 'bg-rose-600';
        },

        previewAvatar(event) {
            const file = event.target.files?.[0];

            if (! file) {
                this.avatarPreview = null;

                return;
            }

            this.avatarPreview = URL.createObjectURL(file);
        },
    }));

    Alpine.data('meetingStudio', (liveKit = null, options = {}) => {
        const liveKitPayload = liveKit ? JSON.parse(JSON.stringify(liveKit)) : null;
        let studioRoom = null;

        return {
            copied: false,
            sceneTab: 'scenes',
            liveKit: liveKitPayload,
            liveScene: options?.live_scene || null,
            previewScene: options?.preview_scene || null,
            studioScenes: Array.isArray(options?.scenes) ? options.scenes : [],
            sceneSourceUrls: options?.scene_source_urls || {},
            selectedSceneId: String(options?.preview_scene?.id || options?.live_scene?.id || (Array.isArray(options?.scenes) && options.scenes.length ? options.scenes[0].id : '')),
            liveParticipants: [],
            liveParticipantStatus: liveKitPayload ? 'Connecting to LiveKit...' : 'LiveKit credentials are not available.',
            sourceAssignUrl: options?.source_assign_url || null,
            mainSourceAssignUrl: options?.main_source_assign_url || null,
            csrfToken: options?.csrf_token || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',

            init() {
                if (this.liveKit) {
                    void this.connectStudioRoom();
                }
            },

            parseParticipantMetadata(participant) {
                try {
                    return JSON.parse(participant?.metadata || '{}');
                } catch {
                    return {};
                }
            },

            async connectStudioRoom() {
                try {
                    await ensureLiveKitModule();
                    studioRoom = new Room({ adaptiveStream: true, dynacast: true });
                    studioRoom
                        .on(RoomEvent.ParticipantConnected, () => this.syncLiveParticipants())
                        .on(RoomEvent.ParticipantDisconnected, () => this.syncLiveParticipants())
                        .on(RoomEvent.TrackPublished, () => this.syncLiveParticipants())
                        .on(RoomEvent.TrackUnpublished, () => this.syncLiveParticipants())
                        .on(RoomEvent.TrackSubscribed, () => this.syncLiveParticipants())
                        .on(RoomEvent.TrackUnsubscribed, () => this.syncLiveParticipants())
                        .on(RoomEvent.TrackMuted, () => this.syncLiveParticipants())
                        .on(RoomEvent.TrackUnmuted, () => this.syncLiveParticipants())
                        .on(RoomEvent.Disconnected, () => {
                            this.liveParticipants = [];
                            this.liveParticipantStatus = 'Disconnected from LiveKit.';
                        });

                    await studioRoom.connect(this.liveKit.server_url, this.liveKit.token);
                    this.liveParticipantStatus = `Connected to ${this.liveKit.room}`;
                    this.syncLiveParticipants();
                } catch (error) {
                    console.error('Studio LiveKit connection failed', error);
                    this.liveParticipantStatus = error?.message || 'Studio could not connect to LiveKit.';
                }
            },

            syncLiveParticipants() {
                if (! studioRoom) {
                    this.liveParticipants = [];

                    return;
                }

                this.liveParticipants = Array.from(studioRoom.remoteParticipants.values())
                    .map((participant) => {
                        const metadata = this.parseParticipantMetadata(participant);

                        return {
                            identity: participant.identity,
                            sid: participant.sid,
                            name: participant.name || participant.identity || 'Guest',
                            role: metadata.role || null,
                            avatar: metadata.avatar || null,
                            hasCamera: Array.from(participant.videoTrackPublications?.values?.() || []).some(publication => publication.track && ! publication.isMuted && publication.source !== Track.Source.ScreenShare),
                            hasScreen: Array.from(participant.videoTrackPublications?.values?.() || []).some(publication => publication.track && ! publication.isMuted && publication.source === Track.Source.ScreenShare),
                            hasAudio: Array.from(participant.audioTrackPublications?.values?.() || []).some(publication => publication.track && ! publication.isMuted),
                        };
                    })
                    .filter(participant => participant.role !== 'studio' && participant.identity !== this.liveKit?.identity);

                this.liveParticipantStatus = this.liveParticipants.length > 0
                    ? `${this.liveParticipants.length} live participant${this.liveParticipants.length === 1 ? '' : 's'} in room`
                    : `Connected to ${this.liveKit.room}. Waiting for participants.`;
                this.$nextTick(() => {
                    this.attachStudioLiveTracks();
                    createIcons({ icons });
                });
            },

            trackFromPublications(publications, kind, source = null) {
                return Array.from(publications?.values?.() || [])
                    .find(publication => publication.kind === kind && publication.track && ! publication.isMuted && (! source || publication.source === source || (source === Track.Source.Camera && ! publication.source)))
                    ?.track || null;
            },

            studioLiveSourceKind() {
                return this.liveScene?.settings?.source_kind === 'screen' ? 'screen' : 'camera';
            },

            studioPreviewSourceKind() {
                return this.previewScene?.settings?.source_kind === 'screen' ? 'screen' : 'camera';
            },

            participantForScene(scene, sourceKind = 'camera') {
                const selectedIdentity = scene?.settings?.source_identity || null;

                if (selectedIdentity) {
                    return this.liveParticipants.find(participant => participant.identity === selectedIdentity) || null;
                }

                return this.liveParticipants.find(participant => sourceKind === 'screen' ? participant.hasScreen : participant.hasCamera)
                    || this.liveParticipants[0]
                    || null;
            },

            studioFeaturedParticipant() {
                return this.participantForScene(this.liveScene, this.studioLiveSourceKind());
            },

            studioPreviewParticipant() {
                return this.participantForScene(this.previewScene, this.studioPreviewSourceKind());
            },

            studioFeaturedHasVideo() {
                const participant = this.studioFeaturedParticipant();

                if (! participant) {
                    return false;
                }

                return this.studioLiveSourceKind() === 'screen' ? participant.hasScreen : participant.hasCamera;
            },

            studioPreviewHasVideo() {
                const participant = this.studioPreviewParticipant();

                if (! participant) {
                    return false;
                }

                return this.studioPreviewSourceKind() === 'screen' ? participant.hasScreen : participant.hasCamera;
            },

            selectedSceneSourceUrl() {
                return this.sceneSourceUrls[String(this.selectedSceneId)] || null;
            },

            selectedSceneTitle() {
                const scene = this.studioScenes.find(item => String(item.id) === String(this.selectedSceneId));

                return scene ? scene.title : 'No scene selected';
            },

            attachStudioLiveTracks() {
                if (! studioRoom) {
                    return;
                }

                const attachScene = (sceneParticipant, sourceKind, videoRef, screenRef, audioRef) => {
                    const remoteParticipant = sceneParticipant ? studioRoom.remoteParticipants.get(sceneParticipant.identity) : null;
                    const videoTrack = remoteParticipant
                        ? (sourceKind === 'screen'
                            ? this.trackFromPublications(remoteParticipant.videoTrackPublications, Track.Kind.Video, Track.Source.ScreenShare)
                            : (this.trackFromPublications(remoteParticipant.videoTrackPublications, Track.Kind.Video, Track.Source.Camera)
                                || this.trackFromPublications(remoteParticipant.videoTrackPublications, Track.Kind.Video)))
                        : null;
                    const audioTrack = remoteParticipant ? this.trackFromPublications(remoteParticipant.audioTrackPublications, Track.Kind.Audio) : null;

                    if (videoRef) {
                        if (videoTrack && sourceKind === 'camera') {
                            videoTrack.attach(videoRef);
                        } else {
                            videoRef.srcObject = null;
                        }
                    }

                    if (screenRef) {
                        if (videoTrack && sourceKind === 'screen') {
                            videoTrack.attach(screenRef);
                        } else {
                            screenRef.srcObject = null;
                        }
                    }

                    if (audioRef) {
                        if (audioTrack) {
                            audioTrack.attach(audioRef);
                        } else {
                            audioRef.srcObject = null;
                        }
                    }
                };

                const participant = this.studioFeaturedParticipant();
                const sourceKind = this.studioLiveSourceKind();
                attachScene(participant, sourceKind, this.$refs.studioLiveVideo, this.$refs.studioLiveScreen, this.$refs.studioLiveAudio);

                const previewParticipant = this.studioPreviewParticipant();
                const previewSourceKind = this.studioPreviewSourceKind();
                attachScene(previewParticipant, previewSourceKind, this.$refs.studioPreviewVideo, this.$refs.studioPreviewScreen, this.$refs.studioPreviewAudio);
            },

            async assignStudioSource(participant, sourceKind, target = 'preview') {
                const assignUrl = target === 'main'
                    ? this.mainSourceAssignUrl
                    : (target === 'preview' ? this.sourceAssignUrl : this.sceneSourceUrls[String(target)]);

                if (! assignUrl || ! participant?.identity) {
                    this.liveParticipantStatus = 'Choose a scene before assigning a source.';

                    return;
                }

                const body = new URLSearchParams();
                body.set('_method', 'PUT');
                body.set('manual_source_identity', participant.identity);
                body.set('source_name', sourceKind === 'screen' ? `${participant.name} screen` : participant.name);
                body.set('source_kind', sourceKind);

                const response = await fetch(assignUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                        ...(this.csrfToken ? { 'X-CSRF-TOKEN': this.csrfToken } : {}),
                    },
                    body,
                });

                if (response.ok || response.redirected) {
                    window.location.reload();
                    return;
                }

                this.liveParticipantStatus = 'Source assignment failed. Refresh and try again.';
            },
        };
    });

    Alpine.data('meetingRoom', (storageKey, liveKit = null, participantName = 'You', options = {}) => {
        const liveKitPayload = liveKit ? JSON.parse(JSON.stringify(liveKit)) : null;
        const chatStorageKey = `${storageKey}-chat`;
        const qnaStorageKey = `${storageKey}-qna`;
        const qnaStateStorageKey = `${storageKey}-qna-state`;
        const pollStateStorageKey = `${storageKey}-poll-state`;
        const pollStorageKey = `${storageKey}-poll`;
        const studioStateUrl = options?.studio_state_url || null;
        const storedChatMessages = (() => {
            try {
                return JSON.parse(localStorage.getItem(chatStorageKey) || '[]');
            } catch {
                return [];
            }
        })();
        const storedQuestions = (() => {
            try {
                return JSON.parse(localStorage.getItem(qnaStorageKey) || '[]');
            } catch {
                return [];
            }
        })();
        const storedPollVotes = (() => {
            try {
                return JSON.parse(localStorage.getItem(pollStorageKey) || '{}');
            } catch {
                return {};
            }
        })();
        const storedQnaState = (() => {
            try {
                return JSON.parse(localStorage.getItem(qnaStateStorageKey) || '{}');
            } catch {
                return {};
            }
        })();
        const storedPollState = (() => {
            try {
                return JSON.parse(localStorage.getItem(pollStateStorageKey) || '{}');
            } catch {
                return {};
            }
        })();
        let liveKitRoom = null;

        return ({
        muted: true,
        camera: false,
        screen: false,
        fullscreen: false,
        playerControlsVisible: true,
        playerControlsHideTimer: null,
        chat: true,
        roomView: 'speaker',
        sidePanel: null,
        panelTab: 'chat',
        hand: false,
        canManageInteractions: Boolean(options?.can_manage_interactions),
        mediaError: '',
        liveKit: liveKitPayload,
        liveKitConnected: false,
        liveKitConnecting: false,
        liveKitStatus: liveKitPayload ? 'Ready to join LiveKit' : 'Local room preview',
        liveKitError: '',
        remoteParticipantCount: 0,
        remoteParticipants: [],
        primaryParticipant: null,
        activeSpeakerIdentity: null,
        studioState: options?.studio || null,
        studioPollOptions: [],
        studioRefreshTimer: null,
        attendanceMarked: Boolean(liveKitPayload?.attendance_marked),
        attendanceRecordUrl: liveKitPayload?.attendance_record_url || null,
        checkedInCount: liveKitPayload?.participant_count || 0,
        note: localStorage.getItem(storageKey) || '',
        chatDraft: '',
        chatMessages: storedChatMessages,
        chatRecipientIdentity: null,
        chatRecipientName: null,
        mentionOpen: false,
        mentionQuery: '',
        qnaEnabled: storedQnaState.enabled !== false,
        questionDraft: '',
        qnaItems: storedQuestions,
        pollId: storedPollState.id || null,
        pollOpen: Boolean(storedPollState.open),
        pollQuestion: storedPollState.question || '',
        pollOptions: Array.isArray(storedPollState.options) ? storedPollState.options : [],
        pollDraftQuestion: '',
        pollDraftOptions: ['', '', '', ''],
        pollVotes: storedPollVotes,
        stream: null,
        checkoutSent: false,

        init() {
            this.applyStudioState(this.studioState);
            this.refreshStudioState();
            if (studioStateUrl) {
                this.studioRefreshTimer = window.setInterval(() => this.refreshStudioState(), 5000);
            }
            window.addEventListener('beforeunload', () => {
                if (this.studioRefreshTimer) {
                    window.clearInterval(this.studioRefreshTimer);
                }
                this.markLiveKitCheckout(true);
            });
            document.addEventListener('fullscreenchange', () => {
                this.fullscreen = Boolean(document.fullscreenElement);
                this.revealPlayerControls();
            });
            this.schedulePlayerControlsHide();
        },

        attachLocalPreviewStream() {
            if (this.$refs.preview) {
                this.$refs.preview.srcObject = this.stream;
            }
            if (this.$refs.speakerPreview) {
                this.$refs.speakerPreview.srcObject = this.stream;
            }
            if (this.$refs.galleryPreview) {
                this.$refs.galleryPreview.srcObject = this.stream;
            }
        },

        async ensureLocalMedia({ audio = false, video = false } = {}) {
            if (! navigator.mediaDevices?.getUserMedia) {
                this.mediaError = 'Camera and microphone are not available in this browser.';

                return;
            }

            try {
                const needsAudio = audio && ! this.stream?.getAudioTracks().length;
                const needsVideo = video && ! this.stream?.getVideoTracks().length;

                if (needsAudio || needsVideo) {
                    const newStream = await navigator.mediaDevices.getUserMedia({ audio: needsAudio, video: needsVideo });
                    this.stream = this.stream || new MediaStream();
                    newStream.getTracks().forEach(track => this.stream.addTrack(track));
                }

                this.attachLocalPreviewStream();
                this.stream.getAudioTracks().forEach(track => { track.enabled = ! this.muted; });
                this.stream.getVideoTracks().forEach(track => { track.enabled = this.camera; });
                this.mediaError = '';
            } catch (error) {
                this.mediaError = error?.message || 'Media permission was not granted.';
            }
        },

        async startCamera() {
            await this.ensureLocalMedia({ audio: true, video: true });
        },

        async toggleCamera() {
            this.camera = ! this.camera;
            if (this.liveKitConnected) {
                try {
                    await liveKitRoom.localParticipant.setCameraEnabled(this.camera);
                    this.attachLocalLiveKitTracks();
                    this.liveKitError = '';
                } catch (error) {
                    this.camera = false;
                    this.liveKitError = error?.message || 'Camera could not be enabled.';
                }

                return;
            }

            if (! this.stream && this.camera) {
                await this.ensureLocalMedia({ video: true });
            }
            if (this.camera && ! this.stream?.getVideoTracks().length) {
                await this.ensureLocalMedia({ video: true });
            }
            this.stream?.getVideoTracks().forEach(track => { track.enabled = this.camera; });
        },

        async toggleMute() {
            this.muted = ! this.muted;
            if (this.liveKitConnected) {
                try {
                    await liveKitRoom.localParticipant.setMicrophoneEnabled(! this.muted);
                    this.liveKitError = '';
                } catch (error) {
                    this.muted = true;
                    this.liveKitError = error?.message || 'Microphone could not be enabled.';
                }

                return;
            }

            if (! this.stream || ! this.stream.getAudioTracks().length) {
                await this.ensureLocalMedia({ audio: true });
            }
            this.stream?.getAudioTracks().forEach(track => { track.enabled = ! this.muted; });
        },

        async toggleLiveKit() {
            if (this.liveKitConnected) {
                await this.disconnectLiveKit();

                return;
            }

            await this.connectLiveKit();
        },

        async connectLiveKit() {
            if (! this.liveKit || this.liveKitConnecting) {
                return;
            }

            this.liveKitConnecting = true;
            this.liveKitError = '';
            this.liveKitStatus = 'Connecting to LiveKit...';

            try {
                await ensureLiveKitModule();
                liveKitRoom = new Room({ adaptiveStream: true, dynacast: true });
                liveKitRoom
                    .on(RoomEvent.ParticipantConnected, () => {
                        this.remoteParticipantCount = liveKitRoom.remoteParticipants.size;
                        this.syncRemoteParticipants();
                    })
                    .on(RoomEvent.ParticipantDisconnected, () => {
                        this.remoteParticipantCount = liveKitRoom.remoteParticipants.size;
                        this.syncRemoteParticipants();
                    })
                    .on(RoomEvent.TrackSubscribed, () => {
                        this.syncRemoteParticipants();
                    })
                    .on(RoomEvent.TrackUnsubscribed, (track) => {
                        track?.detach?.();
                        this.syncRemoteParticipants();
                    })
                    .on(RoomEvent.TrackMuted, () => {
                        this.syncRemoteParticipants();
                    })
                    .on(RoomEvent.TrackUnmuted, () => {
                        this.syncRemoteParticipants();
                    })
                    .on(RoomEvent.ActiveSpeakersChanged, (speakers) => {
                        this.activeSpeakerIdentity = speakers?.[0]?.identity || null;
                        this.syncRemoteParticipants();
                    })
                    .on(RoomEvent.DataReceived, (payload, participant, kind, topic) => {
                        this.receiveRoomData(payload, participant, topic);
                    })
                    .on(RoomEvent.LocalTrackPublished, () => {
                        this.attachLocalLiveKitTracks();
                    })
                    .on(RoomEvent.LocalTrackUnpublished, (publication) => {
                        publication?.track?.detach?.();
                        this.attachLocalLiveKitTracks();
                    })
                    .on(RoomEvent.Disconnected, () => {
                        if (this.liveKitConnected) {
                            void this.markLiveKitCheckout();
                        }
                        this.liveKitConnected = false;
                        this.remoteParticipantCount = 0;
                        this.remoteParticipants = [];
                        this.primaryParticipant = null;
                        if (! this.liveKitError) {
                            this.liveKitStatus = 'Disconnected from LiveKit';
                        }
                    });

                await liveKitRoom.connect(this.liveKit.server_url, this.liveKit.token);
                await liveKitRoom.localParticipant.setMicrophoneEnabled(! this.muted);
                await liveKitRoom.localParticipant.setCameraEnabled(this.camera);
                this.liveKitConnected = true;
                this.remoteParticipantCount = liveKitRoom.remoteParticipants.size;
                this.syncRemoteParticipants();
                this.attachLocalLiveKitTracks();
                this.liveKitStatus = `Connected to ${this.liveKit.room}`;
                await this.markLiveKitAttendance();
            } catch (error) {
                this.liveKitConnected = false;
                console.error('LiveKit connection failed', error);
                this.liveKitError = this.liveKitFriendlyError(error);
                this.liveKitStatus = 'LiveKit connection failed';
                liveKitRoom?.disconnect();
                liveKitRoom = null;
            } finally {
                this.liveKitConnecting = false;
            }
        },

        liveKitFriendlyError(error) {
            const message = error?.message || error?.reason || 'LiveKit connection failed.';

            if (message.toLowerCase().includes('pc connection')) {
                return `${message} LiveKit signaling worked, but the WebRTC media connection failed. Check the LiveKit server firewall/TURN setup: open UDP media ports or TCP/TURN fallback ports, and do not proxy media traffic through Cloudflare.`;
            }

            return message;
        },

        async markLiveKitAttendance() {
            if (! this.liveKit?.mark_attendance_url || this.attendanceMarked) {
                return;
            }

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const response = await fetch(this.liveKit.mark_attendance_url, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({
                        connected: true,
                        room: this.liveKit.room,
                        identity: this.liveKit.identity,
                        participant_name: this.liveKit.name || this.participantName,
                        remote_participants: this.remoteParticipantCount,
                    }),
                });

                if (! response.ok) {
                    throw new Error('LiveKit connected, but attendance could not be marked.');
                }

                const payload = await response.json();
                this.attendanceMarked = Boolean(payload.marked);
                this.attendanceRecordUrl = payload.record_url || this.attendanceRecordUrl;
                this.checkedInCount = payload.participant_count ?? this.checkedInCount;
                this.checkoutSent = false;
            } catch (error) {
                this.liveKitError = error?.message || 'Attendance could not be marked after joining LiveKit.';
            }
        },

        async markLiveKitCheckout(keepalive = false) {
            if (! this.liveKit?.mark_checkout_url || ! this.attendanceMarked || this.checkoutSent) {
                return;
            }

            this.checkoutSent = true;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            try {
                const response = await fetch(this.liveKit.mark_checkout_url, {
                    method: 'POST',
                    keepalive,
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({
                        room: this.liveKit.room,
                    }),
                });

                if (response.ok && ! keepalive) {
                    const payload = await response.json();
                    this.checkedInCount = payload.participant_count ?? Math.max(0, this.checkedInCount - 1);
                    this.attendanceMarked = false;
                }
            } catch {
                this.checkoutSent = false;
            }
        },

        async disconnectLiveKit() {
            await this.markLiveKitCheckout();
            liveKitRoom?.disconnect();
            liveKitRoom = null;
            this.liveKitConnected = false;
            this.remoteParticipantCount = 0;
            this.remoteParticipants = [];
            this.primaryParticipant = null;
            this.liveKitStatus = 'Disconnected from LiveKit';
        },

        async toggleScreenShare() {
            const nextState = ! this.screen;

            if (! this.liveKitConnected) {
                this.liveKitError = this.liveKit
                    ? 'Join the room before sharing your screen.'
                    : 'Screen sharing requires a LiveKit room connection.';
                this.screen = false;

                return;
            }

            if (this.liveKitConnected) {
                try {
                    await liveKitRoom.localParticipant.setScreenShareEnabled(nextState);
                    this.screen = nextState;
                    this.liveKitError = '';
                } catch (error) {
                    this.liveKitError = error?.message || 'Screen sharing could not be changed.';
                }

                return;
            }

            this.screen = nextState;
        },

        async toggleFullscreen(element) {
            if (! document.fullscreenEnabled || ! element?.requestFullscreen) {
                this.mediaError = 'Fullscreen is not available in this browser.';

                return;
            }

            try {
                if (document.fullscreenElement) {
                    await document.exitFullscreen();
                } else {
                    await element.requestFullscreen();
                }

                this.mediaError = '';
            } catch (error) {
                this.mediaError = error?.message || 'Fullscreen could not be changed.';
            }
        },

        revealPlayerControls() {
            this.playerControlsVisible = true;
            this.schedulePlayerControlsHide();
        },

        schedulePlayerControlsHide() {
            if (this.playerControlsHideTimer) {
                window.clearTimeout(this.playerControlsHideTimer);
            }

            this.playerControlsHideTimer = window.setTimeout(() => {
                this.playerControlsVisible = false;
            }, 3200);
        },

        participantInitials(name) {
            return String(name || 'Guest')
                .trim()
                .split(/\s+/)
                .slice(0, 2)
                .map(part => part.charAt(0).toUpperCase())
                .join('') || 'G';
        },

        parseParticipantMetadata(participant) {
            try {
                return JSON.parse(participant?.metadata || '{}');
            } catch {
                return {};
            }
        },

        participantAvatar(participant) {
            try {
                const metadata = JSON.parse(participant?.metadata || '{}');

                return metadata.avatar || null;
            } catch {
                return null;
            }
        },

        trackFromPublications(publications, kind, source = null) {
            return Array.from(publications?.values?.() || [])
                .find(publication => publication.kind === kind && publication.track && ! publication.isMuted && (! source || publication.source === source || (source === Track.Source.Camera && ! publication.source)))
                ?.track || null;
        },

        syncRemoteParticipants() {
            if (! liveKitRoom) {
                this.remoteParticipants = [];
                this.primaryParticipant = null;

                return;
            }

            const participants = Array.from(liveKitRoom.remoteParticipants.values()).map((participant) => {
                const metadata = this.parseParticipantMetadata(participant);
                const name = participant.name || participant.identity || 'Guest';
                const videoTrack = this.trackFromPublications(participant.videoTrackPublications, Track.Kind.Video, Track.Source.Camera)
                    || this.trackFromPublications(participant.videoTrackPublications, Track.Kind.Video);
                const screenTrack = this.trackFromPublications(participant.videoTrackPublications, Track.Kind.Video, Track.Source.ScreenShare);
                const audioTrack = this.trackFromPublications(participant.audioTrackPublications, Track.Kind.Audio);

                return {
                    identity: participant.identity,
                    sid: participant.sid,
                    name,
                    role: metadata.role || null,
                    initials: this.participantInitials(name),
                    avatar: metadata.avatar || null,
                    hasVideo: Boolean(videoTrack),
                    hasScreen: Boolean(screenTrack),
                    hasAudio: Boolean(audioTrack),
                    isSpeaking: participant.identity === this.activeSpeakerIdentity || Boolean(participant.isSpeaking),
                };
            }).filter(participant => participant.role !== 'studio');

            this.remoteParticipants = participants;
            this.primaryParticipant = participants.find(participant => participant.identity === this.activeSpeakerIdentity && participant.hasVideo)
                || participants.find(participant => participant.hasVideo)
                || participants[0]
                || null;
            this.$nextTick(() => this.attachLiveKitTracks());
        },

        totalParticipantCount() {
            return 1 + this.remoteParticipants.length;
        },

        visibleRemoteParticipants() {
            if (! this.primaryParticipant) {
                return this.remoteParticipants.slice(0, 6);
            }

            return this.remoteParticipants
                .filter(participant => participant.identity !== this.primaryParticipant.identity)
                .slice(0, 6);
        },

        featuredParticipant() {
            const identity = this.studioLiveScene()?.settings?.source_identity;

            if (identity) {
                return this.remoteParticipants.find(participant => participant.identity === identity) || this.primaryParticipant || null;
            }

            return this.primaryParticipant;
        },

        featuredSourceKind() {
            return this.studioLiveScene()?.settings?.source_kind === 'screen' ? 'screen' : 'camera';
        },

        featuredHasVideo(participant) {
            if (! participant) {
                return false;
            }

            return this.featuredSourceKind() === 'screen' ? participant.hasScreen : participant.hasVideo;
        },

        attachLiveKitTracks() {
            if (! liveKitRoom) {
                return;
            }

            Array.from(liveKitRoom.remoteParticipants.values()).forEach((participant) => {
                const videoTrack = this.trackFromPublications(participant.videoTrackPublications, Track.Kind.Video, Track.Source.Camera)
                    || this.trackFromPublications(participant.videoTrackPublications, Track.Kind.Video);
                const screenTrack = this.trackFromPublications(participant.videoTrackPublications, Track.Kind.Video, Track.Source.ScreenShare);
                const audioTrack = this.trackFromPublications(participant.audioTrackPublications, Track.Kind.Audio);

                document.querySelectorAll('[data-livekit-video]').forEach((element) => {
                    if (element.getAttribute('data-livekit-video') === participant.identity && videoTrack) {
                        videoTrack.attach(element);
                    }
                });

                document.querySelectorAll('[data-livekit-screen]').forEach((element) => {
                    if (element.getAttribute('data-livekit-screen') === participant.identity && screenTrack) {
                        screenTrack.attach(element);
                    }
                });

                document.querySelectorAll('[data-livekit-audio]').forEach((element) => {
                    if (element.getAttribute('data-livekit-audio') === participant.identity && audioTrack) {
                        audioTrack.attach(element);
                    }
                });
            });

            this.attachLocalLiveKitTracks();
        },

        attachLocalLiveKitTracks() {
            if (! liveKitRoom) {
                return;
            }

            const videoTrack = this.trackFromPublications(liveKitRoom.localParticipant.videoTrackPublications, Track.Kind.Video);

            if (videoTrack) {
                if (this.$refs.localLiveKitVideo) {
                    videoTrack.attach(this.$refs.localLiveKitVideo);
                }
                if (this.$refs.speakerLocalLiveKitVideo) {
                    videoTrack.attach(this.$refs.speakerLocalLiveKitVideo);
                }
                if (this.$refs.galleryLocalLiveKitVideo) {
                    videoTrack.attach(this.$refs.galleryLocalLiveKitVideo);
                }
            }
        },

        saveNote() {
            localStorage.setItem(storageKey, this.note);
        },

        persistChat() {
            localStorage.setItem(chatStorageKey, JSON.stringify(this.chatMessages.slice(-80)));
        },

        appendChatMessage(message) {
            this.chatMessages = [...this.chatMessages, message].slice(-80);
            this.persistChat();
            this.$nextTick(() => {
                if (this.$refs.chatScroll) {
                    this.$refs.chatScroll.scrollTop = this.$refs.chatScroll.scrollHeight;
                }
            });
        },

        async refreshStudioState() {
            if (! studioStateUrl) {
                return;
            }

            try {
                const response = await fetch(studioStateUrl, { headers: { Accept: 'application/json' } });
                if (! response.ok) {
                    return;
                }

                this.applyStudioState(await response.json());
            } catch {
                // Studio state polling is best effort; room media must keep working.
            }
        },

        applyStudioState(state) {
            if (! state) {
                return;
            }

            this.studioState = state;
            this.qnaEnabled = state.qna_enabled !== false;

            if (Array.isArray(state.qna)) {
                this.qnaItems = state.qna.map(question => ({
                    id: `db-${question.id}`,
                    author: question.author || 'Guest',
                    body: question.body || '',
                    at: question.at || '',
                    votes: question.votes || 0,
                    pinned: Boolean(question.pinned),
                    status: question.status || 'open',
                }));
            }

            if (state.poll_visible !== false && state.poll) {
                this.pollId = `db-${state.poll.id}`;
                this.pollOpen = Boolean(state.poll.is_open);
                this.pollQuestion = state.poll.question || '';
                this.studioPollOptions = Array.isArray(state.poll.options) ? state.poll.options : [];
                this.pollOptions = this.studioPollOptions.map(option => option.label);
                this.pollVotes = {};
                this.studioPollOptions.forEach((option) => {
                    for (let index = 0; index < (option.votes || 0); index += 1) {
                        this.pollVotes[`db-${option.id}-${index}`] = option.label;
                    }
                });
            }
        },

        studioChatVisible() {
            return this.studioState?.chat_visible !== false;
        },

        studioLowerThird() {
            return this.studioState?.lower_third || {};
        },

        studioLowerThirdStyle() {
            const backgroundUrl = this.studioLowerThird()?.background_url;
            const backgroundStyle = this.studioLowerThird()?.background_style;

            if (! backgroundUrl) {
                return backgroundStyle || '';
            }

            return `background-image: linear-gradient(90deg, rgba(0,0,0,.94), rgba(7,19,33,.86), rgba(0,0,0,.62)), url("${String(backgroundUrl).replaceAll('"', '%22')}"); background-size: cover; background-position: center;`;
        },

        studioScripture() {
            return this.studioState?.scripture || {};
        },

        studioLiveScene() {
            return this.studioState?.live_scene || null;
        },

        studioTickerText() {
            return this.studioState?.ticker_text || '';
        },

        openPanel(panel, tab = null) {
            if (this.sidePanel === panel && (! tab || this.panelTab === tab)) {
                this.sidePanel = null;

                return;
            }

            this.sidePanel = panel;

            if (tab) {
                this.panelTab = tab;
            }
        },

        chatRecipients() {
            return this.remoteParticipants.map(participant => ({
                identity: participant.identity,
                name: participant.name,
                initials: participant.initials,
                avatar: participant.avatar,
            }));
        },

        filteredMentionRecipients() {
            const query = this.mentionQuery.toLowerCase();

            return this.chatRecipients()
                .filter(participant => ! query || participant.name.toLowerCase().includes(query) || participant.identity.toLowerCase().includes(query))
                .slice(0, 8);
        },

        setChatRecipient(participant) {
            if (! participant) {
                this.clearChatRecipient();

                return;
            }

            this.chatRecipientIdentity = participant.identity;
            this.chatRecipientName = participant.name;
        },

        clearChatRecipient() {
            this.chatRecipientIdentity = null;
            this.chatRecipientName = null;
        },

        handleChatInput() {
            const match = this.chatDraft.match(/(^|\s)@([^\s@]*)$/);
            this.mentionOpen = Boolean(match);
            this.mentionQuery = match ? match[2] : '';
        },

        selectMentionRecipient(participant) {
            this.setChatRecipient(participant);
            this.chatDraft = this.chatDraft.replace(/(^|\s)@([^\s@]*)$/, '$1').trimStart();
            this.mentionOpen = false;
            this.mentionQuery = '';
            this.$nextTick(() => this.$refs.chatInput?.focus());
        },

        async publishRoomData(payload, options = {}) {
            if (! liveKitRoom || ! this.liveKitConnected) {
                return;
            }

            await liveKitRoom.localParticipant.publishData(new TextEncoder().encode(JSON.stringify(payload)), {
                reliable: true,
                topic: 'room-chat',
                ...options,
            });
        },

        async copyRoomLink(url = window.location.href) {
            try {
                await navigator.clipboard.writeText(url);
                this.mediaError = '';
                this.liveKitStatus = 'Room link copied.';
            } catch {
                this.mediaError = 'Room link could not be copied by this browser.';
            }
        },

        async shareRoom(title = document.title, url = window.location.href) {
            if (navigator.share) {
                try {
                    await navigator.share({ title, url });
                    this.mediaError = '';

                    return;
                } catch (error) {
                    if (error?.name === 'AbortError') {
                        return;
                    }
                }
            }

            await this.copyRoomLink(url);
        },

        async sendChatMessage() {
            const body = this.chatDraft.trim();

            if (! body || ! this.studioChatVisible()) {
                return;
            }

            const message = {
                id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                author: participantName || this.liveKit?.name || 'You',
                body,
                at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                local: true,
                avatar: options?.avatar || this.liveKit?.avatar || null,
                direct: Boolean(this.chatRecipientIdentity),
                recipientIdentity: this.chatRecipientIdentity,
                recipientName: this.chatRecipientName,
            };

            this.chatDraft = '';
            this.mentionOpen = false;
            this.appendChatMessage(message);

            if (! liveKitRoom || ! this.liveKitConnected) {
                return;
            }

            try {
                await this.publishRoomData({
                    type: 'chat',
                    id: message.id,
                    author: message.author,
                    avatar: message.avatar,
                    body: message.body,
                    at: message.at,
                    direct: message.direct,
                    recipientIdentity: message.recipientIdentity,
                    recipientName: message.recipientName,
                }, {
                    ...(this.chatRecipientIdentity ? { destinationIdentities: [this.chatRecipientIdentity] } : {}),
                });
            } catch (error) {
                this.liveKitError = error?.message || 'Chat message could not be sent to the room.';
            }
        },

        persistQuestions() {
            localStorage.setItem(qnaStorageKey, JSON.stringify(this.qnaItems.slice(-60)));
        },

        persistQnaState() {
            localStorage.setItem(qnaStateStorageKey, JSON.stringify({ enabled: this.qnaEnabled }));
        },

        setQnaEnabled(enabled) {
            if (! this.canManageInteractions) {
                return;
            }

            this.qnaEnabled = Boolean(enabled);
            this.persistQnaState();
            this.publishRoomData({ type: 'qna_state', enabled: this.qnaEnabled }).catch((error) => {
                this.liveKitError = error?.message || 'Q&A status could not be sent to the room.';
            });
        },

        clearQuestions() {
            if (! this.canManageInteractions) {
                return;
            }

            this.qnaItems = [];
            this.persistQuestions();
            this.publishRoomData({ type: 'qna_clear' }).catch((error) => {
                this.liveKitError = error?.message || 'Q&A could not be cleared for the room.';
            });
        },

        appendQuestion(question) {
            if (this.qnaItems.some(item => item.id === question.id)) {
                return;
            }

            this.qnaItems = [question, ...this.qnaItems].slice(0, 60);
            this.persistQuestions();
        },

        async sendQuestion() {
            const body = this.questionDraft.trim();

            if (! body || ! this.qnaEnabled) {
                return;
            }

            if (this.studioState?.qna_submit_url) {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                try {
                    const response = await fetch(this.studioState.qna_submit_url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                        },
                        body: JSON.stringify({ body }),
                    });

                    if (! response.ok) {
                        throw new Error('Question could not be sent to the host.');
                    }

                    const payload = await response.json();
                    this.questionDraft = '';
                    this.applyStudioState(payload.studio || null);

                    return;
                } catch (error) {
                    this.liveKitError = error?.message || 'Question could not be sent to the host.';
                }
            }

            const question = {
                id: `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                author: participantName || this.liveKit?.name || 'You',
                body,
                at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                votes: 1,
                local: true,
            };

            this.questionDraft = '';
            this.appendQuestion(question);

            try {
                await this.publishRoomData({ type: 'question', ...question });
            } catch (error) {
                this.liveKitError = error?.message || 'Question could not be sent to the room.';
            }
        },

        upvoteQuestion(id) {
            this.qnaItems = this.qnaItems.map(item => item.id === id ? { ...item, votes: (item.votes || 0) + 1 } : item);
            this.persistQuestions();
        },

        persistPollVotes() {
            localStorage.setItem(pollStorageKey, JSON.stringify(this.pollVotes));
        },

        persistPollState() {
            localStorage.setItem(pollStateStorageKey, JSON.stringify({
                id: this.pollId,
                open: this.pollOpen,
                question: this.pollQuestion,
                options: this.pollOptions,
            }));
        },

        hasActivePoll() {
            return Boolean(this.pollId && this.pollQuestion && this.pollOptions.length >= 2);
        },

        publishPollState() {
            return this.publishRoomData({
                type: 'poll_state',
                id: this.pollId,
                open: this.pollOpen,
                question: this.pollQuestion,
                options: this.pollOptions,
            });
        },

        createPoll() {
            if (! this.canManageInteractions) {
                return;
            }

            const question = this.pollDraftQuestion.trim();
            const options = this.pollDraftOptions
                .map(option => option.trim())
                .filter(Boolean)
                .slice(0, 6);

            if (! question || options.length < 2) {
                this.liveKitError = 'A poll needs a question and at least two answers.';

                return;
            }

            this.pollId = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
            this.pollOpen = true;
            this.pollQuestion = question;
            this.pollOptions = options;
            this.pollVotes = {};
            this.pollDraftQuestion = '';
            this.pollDraftOptions = ['', '', '', ''];
            this.liveKitError = '';
            this.persistPollState();
            this.persistPollVotes();
            this.publishPollState().catch((error) => {
                this.liveKitError = error?.message || 'Poll could not be sent to the room.';
            });
        },

        closePoll() {
            if (! this.canManageInteractions || ! this.hasActivePoll()) {
                return;
            }

            this.pollOpen = false;
            this.persistPollState();
            this.publishPollState().catch((error) => {
                this.liveKitError = error?.message || 'Poll status could not be sent to the room.';
            });
        },

        reopenPoll() {
            if (! this.canManageInteractions || ! this.hasActivePoll()) {
                return;
            }

            this.pollOpen = true;
            this.persistPollState();
            this.publishPollState().catch((error) => {
                this.liveKitError = error?.message || 'Poll status could not be sent to the room.';
            });
        },

        async votePoll(option) {
            if (! this.pollOpen || ! this.hasActivePoll()) {
                return;
            }

            if (this.studioState?.poll?.vote_url) {
                const selected = this.studioPollOptions.find(item => item.label === option);
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                if (! selected) {
                    return;
                }

                try {
                    const response = await fetch(this.studioState.poll.vote_url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                        },
                        body: JSON.stringify({ option: selected.id }),
                    });

                    if (! response.ok) {
                        throw new Error('Poll vote could not be saved.');
                    }

                    const payload = await response.json();
                    this.applyStudioState(payload.studio || null);

                    return;
                } catch (error) {
                    this.liveKitError = error?.message || 'Poll vote could not be saved.';
                }
            }

            const voter = this.liveKit?.identity || participantName || 'local-user';
            this.pollVotes = { ...this.pollVotes, [voter]: option };
            this.persistPollVotes();

            this.publishRoomData({
                type: 'poll_vote',
                voter,
                option,
            }).catch((error) => {
                this.liveKitError = error?.message || 'Poll vote could not be sent to the room.';
            });
        },

        pollTotalVotes() {
            return Object.keys(this.pollVotes).length;
        },

        pollPercent(option) {
            const total = this.pollTotalVotes();

            if (! total) {
                return 0;
            }

            return Math.round((Object.values(this.pollVotes).filter(value => value === option).length / total) * 100);
        },

        receiveRoomData(payload, participant, topic) {
            if (topic && topic !== 'room-chat') {
                return;
            }

            try {
                const decoded = new TextDecoder().decode(payload);
                const message = JSON.parse(decoded);

                if (message.type === 'question') {
                    if (! this.qnaEnabled) {
                        return;
                    }

                    this.appendQuestion({
                        id: message.id || `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                        author: message.author || participant?.name || participant?.identity || 'Guest',
                        body: message.body || '',
                        at: message.at || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                        votes: message.votes || 1,
                        local: false,
                    });

                    return;
                }

                if (message.type === 'qna_state') {
                    this.qnaEnabled = message.enabled !== false;
                    this.persistQnaState();

                    return;
                }

                if (message.type === 'qna_clear') {
                    this.qnaItems = [];
                    this.persistQuestions();

                    return;
                }

                if (message.type === 'poll_state') {
                    this.pollId = message.id || null;
                    this.pollOpen = Boolean(message.open);
                    this.pollQuestion = message.question || '';
                    this.pollOptions = Array.isArray(message.options) ? message.options : [];
                    this.pollVotes = {};
                    this.persistPollState();
                    this.persistPollVotes();

                    return;
                }

                if (message.type === 'poll_vote' && message.voter && message.option) {
                    if (! this.hasActivePoll()) {
                        return;
                    }

                    this.pollVotes = { ...this.pollVotes, [message.voter]: message.option };
                    this.persistPollVotes();

                    return;
                }

                if (message.type !== 'chat' || this.chatMessages.some(item => item.id === message.id)) {
                    return;
                }

                if (message.direct && message.recipientIdentity && message.recipientIdentity !== this.liveKit?.identity) {
                    return;
                }

                this.appendChatMessage({
                    id: message.id || `${Date.now()}-${Math.random().toString(16).slice(2)}`,
                    author: message.author || participant?.name || participant?.identity || 'Guest',
                    body: message.body || '',
                    at: message.at || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                    local: false,
                    avatar: message.avatar || this.participantAvatar(participant),
                    direct: Boolean(message.direct),
                    recipientIdentity: message.recipientIdentity || this.liveKit?.identity || null,
                    recipientName: message.recipientName || this.liveKit?.name || 'You',
                });
            } catch {
                // Ignore non-chat room data.
            }
        },
    });
    });
});

Alpine.start();

const icons = {
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Archive,
    Activity,
    Baby,
    Ban,
    BadgeDollarSign,
    Badge,
    BadgeCheck,
    Bell,
    BellOff,
    BellRing,
    BarChart3,
    Blocks,
    Boxes,
    Bold,
    Bot,
    BookOpen,
    BookOpenCheck,
    Bookmark,
    BookmarkPlus,
    BookPlus,
    BriefcaseMedical,
    Bug,
    Building2,
    Braces,
    Calendar,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    CalendarX,
    ChartColumn,
    ChartNoAxesColumn,
    ChartNoAxesCombined,
    ChartNoAxesColumnIncreasing,
    CheckCheck,
    CheckCircle2,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Church,
    Captions,
    CircleAlert,
    CircleCheck,
    CircleDot,
    Circle,
    CircleHelp,
    CirclePause,
    Clock,
    Clock3,
    ClipboardCheck,
    ClipboardList,
    Cloud,
    CloudCheck,
    CloudDownload,
    Columns3,
    Copy,
    CopyPlus,
    Construction,
    CreditCard,
    Cross,
    Database,
    DoorOpen,
    Download,
    Droplets,
    Ellipsis,
    EllipsisVertical,
    ExternalLink,
    Eye,
    EyeOff,
    FileDown,
    FileChartColumn,
    FileSearch,
    FileText,
    FileWarning,
    Flag,
    Filter,
    Fingerprint,
    FolderPlus,
    Gauge,
    GitBranch,
    GraduationCap,
    Globe2,
    Grip,
    Hand,
    HandCoins,
    HandHeart,
    Handshake,
    HardDrive,
    Headphones,
    Heart,
    HeartHandshake,
    HeartPulse,
    Highlighter,
    History,
    Hourglass,
    Home,
    Image,
    ImagePlus,
    Inbox,
    Info,
    Italic,
    Landmark,
    LayoutDashboard,
    LayoutGrid,
    LayoutList,
    Layers2,
    Layers3,
    Leaf,
    Library,
    LifeBuoy,
    Lightbulb,
    Link,
    List,
    ListChecks,
    ListFilter,
    ListOrdered,
    ListPlus,
    Languages,
    LoaderCircle,
    LogIn,
    LogOut,
    LockKeyhole,
    Mail,
    MailPlus,
    MailX,
    Map,
    MapPin,
    Maximize,
    Megaphone,
    Menu,
    Minimize,
    Minus,
    Moon,
    MessageCircle,
    MessageCircleHeart,
    MessageSquare,
    MessageSquareCheck,
    MessageSquareOff,
    MessageSquareText,
    Milestone,
    MessageCircleQuestion,
    MessagesSquare,
    Mic,
    MicOff,
    Monitor,
    MonitorPlay,
    MonitorUp,
    MoreVertical,
    Music,
    Network,
    NotebookPen,
    NotebookTabs,
    PackageCheck,
    PackagePlus,
    PanelTop,
    Palette,
    Paperclip,
    Pencil,
    Phone,
    PhoneOff,
    PieChart,
    Plus,
    Play,
    PlugZap,
    Podcast,
    Power,
    PowerOff,
    QrCode,
    Receipt,
    ReceiptText,
    Radio,
    RadioTower,
    Repeat2,
    RefreshCw,
    Rocket,
    Route,
    RotateCcw,
    RotateCw,
    Save,
    Scale,
    Search,
    Send,
    Settings,
    ScanFace,
    ScanLine,
    ScanQrCode,
    ScanSearch,
    ScreenShare,
    Share2,
    ShoppingCart,
    Siren,
    SlidersHorizontal,
    ShieldAlert,
    ShieldCheck,
    ShieldX,
    Smartphone,
    Sparkles,
    Star,
    Store,
    Settings2,
    Square,
    SquarePen,
    Tags,
    Tag,
    Target,
    TextCursorInput,
    Timer,
    ToggleRight,
    TrendingUp,
    TriangleAlert,
    Trash2,
    Trophy,
    ThumbsUp,
    Underline,
    UnlockKeyhole,
    User,
    UserCheck,
    UserPlus,
    UserPen,
    UserRound,
    UserRoundCheck,
    UserRoundCog,
    UserX,
    Users,
    UsersRound,
    Upload,
    Video,
    VideoOff,
    Volume2,
    Wallet,
    Webhook,
    Wifi,
    Wrench,
    X,
    Zap,
    Sun,
    Flame,
    GitCompare,
    GitCompareArrows,
    KeyRound,
    Lock,
};

const palette = {
    purple: '#6d4aff',
    blue: '#2477f2',
    teal: '#14b8a6',
    orange: '#f97316',
    rose: '#f43f5e',
    amber: '#f59e0b',
    emerald: '#10b981',
};

function parseJson(value, fallback = []) {
    try {
        return JSON.parse(value || '[]');
    } catch {
        return fallback;
    }
}

function initAttendanceChart(canvas) {
    const labels = parseJson(canvas.dataset.labels);
    const values = parseJson(canvas.dataset.values);
    const gradient = canvas.getContext('2d').createLinearGradient(0, 0, 0, 260);
    gradient.addColorStop(0, 'rgba(109, 74, 255, 0.28)');
    gradient.addColorStop(1, 'rgba(109, 74, 255, 0.02)');

    new Chart(canvas, {
        type: 'line',
        data: {
            labels,
            datasets: [{
                data: values,
                borderColor: palette.purple,
                backgroundColor: gradient,
                borderWidth: 3,
                pointRadius: 4,
                pointBackgroundColor: '#fff',
                pointBorderColor: palette.purple,
                fill: true,
                tension: 0.35,
            }],
        },
        options: chartOptions({ yTicks: callbackThousands }),
    });
}

function initMultiLineChart(canvas) {
    const labels = parseJson(canvas.dataset.labels);
    const datasets = parseJson(canvas.dataset.datasets).map((dataset) => ({
        label: dataset.label,
        data: dataset.values,
        borderColor: dataset.color || palette.purple,
        backgroundColor: dataset.color || palette.purple,
        borderWidth: 2,
        pointRadius: 3,
        pointHoverRadius: 4,
        pointBackgroundColor: '#fff',
        pointBorderColor: dataset.color || palette.purple,
        fill: false,
        tension: 0.35,
    }));

    Chart.getChart(canvas)?.destroy();

    new Chart(canvas, {
        type: 'line',
        data: { labels, datasets },
        options: chartOptions({ yTicks: callbackThousands }),
    });
}

function initGivingChart(canvas) {
    const labels = parseJson(canvas.dataset.labels);
    const values = parseJson(canvas.dataset.values);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: [palette.purple, palette.blue, palette.teal, palette.orange, palette.rose, palette.amber],
                borderRadius: 5,
                maxBarThickness: 36,
            }],
        },
        options: chartOptions({ yTicks: value => `$${value / 1000}K` }),
    });
}

function initDoughnutChart(canvas) {
    const labels = parseJson(canvas.dataset.labels);
    const values = parseJson(canvas.dataset.values);
    const colors = parseJson(canvas.dataset.colors, [palette.purple, palette.blue, palette.teal, palette.orange, palette.rose, palette.amber]);
    const numericValues = values.map(value => Number(value) || 0);
    const total = numericValues.reduce((sum, value) => sum + value, 0);

    Chart.getChart(canvas)?.destroy();

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels: total > 0 ? labels : ['No data'],
            datasets: [{
                data: total > 0 ? numericValues : [1],
                backgroundColor: total > 0 ? colors : ['#e2e8f0'],
                borderColor: '#fff',
                borderWidth: 4,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            plugins: { legend: { display: false } },
        },
    });
}

function initSparkline(canvas) {
    new Chart(canvas, {
        type: 'line',
        data: {
            labels: parseJson(canvas.dataset.values).map((_, index) => index + 1),
            datasets: [{
                data: parseJson(canvas.dataset.values),
                borderColor: canvas.dataset.color || palette.blue,
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.35,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            scales: { x: { display: false }, y: { display: false } },
        },
    });
}

function chartOptions({ yTicks }) {
    return {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0f172a',
                padding: 10,
                titleColor: '#fff',
                bodyColor: '#e2e8f0',
            },
        },
        scales: {
            x: {
                grid: { display: false },
                ticks: { color: '#64748b', font: { size: 11 } },
            },
            y: {
                beginAtZero: true,
                grid: { color: '#e8edf5' },
                ticks: { color: '#64748b', font: { size: 11 }, callback: yTicks },
            },
        },
    };
}

function callbackThousands(value) {
    return value >= 1000 ? `${value / 1000}K` : value;
}

function safeCreateIcons() {
    try {
        createIcons({ icons });
    } catch (error) {
        console.error('Lucide icons could not be initialized.', error);
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    safeCreateIcons();

    const chartElements = document.querySelectorAll('[data-chart]');
    if (chartElements.length === 0) {
        return;
    }

    try {
        await ensureChartModule();
        document.querySelectorAll('[data-chart="attendance"]').forEach(initAttendanceChart);
        document.querySelectorAll('[data-chart="multi-line"]').forEach(initMultiLineChart);
        document.querySelectorAll('[data-chart="giving"]').forEach(initGivingChart);
        document.querySelectorAll('[data-chart="doughnut"]').forEach(initDoughnutChart);
        document.querySelectorAll('[data-chart="sparkline"]').forEach(initSparkline);
    } catch (error) {
        console.error('Charts could not be loaded.', error);
    }
});
