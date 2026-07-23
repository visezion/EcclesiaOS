import './bootstrap';
import Alpine from 'alpinejs';
import { Room, RoomEvent } from 'livekit-client';
import {
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Archive,
    Activity,
    Baby,
    BadgeDollarSign,
    Badge,
    BadgeCheck,
    Bell,
    BellOff,
    BellRing,
    BarChart3,
    Bold,
    Bot,
    BookOpen,
    BookPlus,
    BriefcaseMedical,
    Building2,
    Braces,
    Calendar,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    ChartNoAxesCombined,
    CheckCheck,
    CheckCircle2,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Church,
    CircleDot,
    CircleAlert,
    CircleCheck,
    CircleHelp,
    CirclePause,
    Clock,
    Clock3,
    ClipboardCheck,
    ClipboardList,
    CloudCheck,
    Copy,
    CopyPlus,
    Columns3,
    Cross,
    Database,
    Download,
    Droplets,
    Ellipsis,
    ExternalLink,
    Eye,
    FileChartColumn,
    FileSearch,
    FileText,
    Fingerprint,
    Filter,
    Gauge,
    GitBranch,
    GraduationCap,
    Globe2,
    Hand,
    HandCoins,
    HandHeart,
    Handshake,
    HardDrive,
    Heart,
    HeartHandshake,
    History,
    Home,
    Image,
    Inbox,
    Italic,
    Landmark,
    LayoutDashboard,
    LayoutGrid,
    LayoutList,
    Leaf,
    Library,
    Link,
    ListChecks,
    ListFilter,
    LogIn,
    LogOut,
    Mail,
    MailPlus,
    MailX,
    Map,
    MapPin,
    Menu,
    Minus,
    Moon,
    MessageCircleHeart,
    MessageSquare,
    MessageSquareCheck,
    MessageSquareText,
    MessagesSquare,
    Mic,
    MicOff,
    Monitor,
    MonitorPlay,
    MoreVertical,
    Network,
    PackageCheck,
    PackagePlus,
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
    PowerOff,
    Receipt,
    Radio,
    RadioTower,
    RefreshCw,
    Route,
    RotateCcw,
    RotateCw,
    Save,
    Search,
    Send,
    Settings,
    ScanFace,
    ScanLine,
    ScanQrCode,
    ScreenShare,
    SlidersHorizontal,
    ShieldAlert,
    ShieldCheck,
    ShieldX,
    Smartphone,
    Sparkles,
    Star,
    Settings2,
    Tags,
    TrendingUp,
    TriangleAlert,
    Trash2,
    Underline,
    User,
    UserCheck,
    UserPlus,
    UserPen,
    UserRound,
    UserRoundCheck,
    UserRoundCog,
    Users,
    UsersRound,
    Upload,
    Video,
    VideoOff,
    Wallet,
    Webhook,
    Wrench,
    X,
    KeyRound,
    Lock,
    createIcons,
} from 'lucide';
import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
    ArcElement,
} from 'chart.js';

Chart.register(
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    DoughnutController,
    Filler,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
);

window.Alpine = Alpine;

document.addEventListener('alpine:init', () => {
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

    Alpine.data('campusDirectory', (users, assignmentBaseUrl) => ({
        users,
        assignmentBaseUrl,
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

    Alpine.data('profilePage', (openEdit = false) => ({
        tab: 'overview',
        editOpen: Boolean(openEdit),
        passwordOpen: false,
        actionOpen: false,
        avatarPreview: null,

        previewAvatar(event) {
            const file = event.target.files?.[0];

            if (! file) {
                this.avatarPreview = null;

                return;
            }

            this.avatarPreview = URL.createObjectURL(file);
        },
    }));

    Alpine.data('meetingRoom', (storageKey, liveKit = null) => {
        const liveKitPayload = liveKit ? JSON.parse(JSON.stringify(liveKit)) : null;
        let liveKitRoom = null;

        return ({
        muted: true,
        camera: false,
        screen: false,
        chat: true,
        hand: false,
        mediaError: '',
        liveKit: liveKitPayload,
        liveKitConnected: false,
        liveKitConnecting: false,
        liveKitStatus: liveKitPayload ? 'Ready to join LiveKit' : 'Local room preview',
        liveKitError: '',
        remoteParticipantCount: 0,
        attendanceMarked: Boolean(liveKitPayload?.attendance_marked),
        attendanceRecordUrl: liveKitPayload?.attendance_record_url || null,
        checkedInCount: liveKitPayload?.participant_count || 0,
        note: localStorage.getItem(storageKey) || '',
        stream: null,
        checkoutSent: false,

        init() {
            window.addEventListener('beforeunload', () => {
                this.markLiveKitCheckout(true);
            });
        },

        async startCamera() {
            if (! navigator.mediaDevices?.getUserMedia) {
                this.mediaError = 'Camera is not available in this browser.';

                return;
            }

            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                if (this.$refs.preview) {
                    this.$refs.preview.srcObject = this.stream;
                }
                this.stream.getAudioTracks().forEach(track => { track.enabled = ! this.muted; });
                this.stream.getVideoTracks().forEach(track => { track.enabled = this.camera; });
                this.mediaError = '';
            } catch (error) {
                this.mediaError = error?.message || 'Camera permission was not granted.';
            }
        },

        async toggleCamera() {
            this.camera = ! this.camera;
            if (this.liveKitConnected) {
                try {
                    await liveKitRoom.localParticipant.setCameraEnabled(this.camera);
                    this.liveKitError = '';
                } catch (error) {
                    this.camera = false;
                    this.liveKitError = error?.message || 'Camera could not be enabled.';
                }

                return;
            }

            if (! this.stream && this.camera) {
                await this.startCamera();
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

            if (! this.stream) {
                await this.startCamera();
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
                liveKitRoom = new Room({ adaptiveStream: true, dynacast: true });
                liveKitRoom
                    .on(RoomEvent.ParticipantConnected, () => {
                        this.remoteParticipantCount = liveKitRoom.remoteParticipants.size;
                    })
                    .on(RoomEvent.ParticipantDisconnected, () => {
                        this.remoteParticipantCount = liveKitRoom.remoteParticipants.size;
                    })
                    .on(RoomEvent.Disconnected, () => {
                        if (this.liveKitConnected) {
                            void this.markLiveKitCheckout();
                        }
                        this.liveKitConnected = false;
                        this.remoteParticipantCount = 0;
                        if (! this.liveKitError) {
                            this.liveKitStatus = 'Disconnected from LiveKit';
                        }
                    });

                await liveKitRoom.connect(this.liveKit.server_url, this.liveKit.token);
                this.liveKitConnected = true;
                this.remoteParticipantCount = liveKitRoom.remoteParticipants.size;
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
            this.liveKitStatus = 'Disconnected from LiveKit';
        },

        saveNote() {
            localStorage.setItem(storageKey, this.note);
        },
    });
    });
});

Alpine.start();

const icons = {
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Archive,
    Activity,
    Baby,
    BadgeDollarSign,
    Badge,
    BadgeCheck,
    Bell,
    BellOff,
    BellRing,
    BarChart3,
    Bold,
    Bot,
    BookOpen,
    BookPlus,
    BriefcaseMedical,
    Building2,
    Braces,
    Calendar,
    CalendarCheck,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    ChartNoAxesCombined,
    CheckCheck,
    CheckCircle2,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Church,
    CircleAlert,
    CircleCheck,
    CircleDot,
    CircleHelp,
    CirclePause,
    Clock,
    Clock3,
    ClipboardCheck,
    ClipboardList,
    CloudCheck,
    Columns3,
    Copy,
    CopyPlus,
    Cross,
    Database,
    Download,
    Droplets,
    Ellipsis,
    ExternalLink,
    Eye,
    FileChartColumn,
    FileSearch,
    FileText,
    Filter,
    Fingerprint,
    Gauge,
    GitBranch,
    GraduationCap,
    Globe2,
    Hand,
    HandCoins,
    HandHeart,
    Handshake,
    HardDrive,
    Heart,
    HeartHandshake,
    History,
    Home,
    Image,
    Inbox,
    Italic,
    Landmark,
    LayoutDashboard,
    LayoutGrid,
    LayoutList,
    Leaf,
    Library,
    Link,
    ListChecks,
    ListFilter,
    LogIn,
    LogOut,
    Mail,
    MailPlus,
    MailX,
    Map,
    MapPin,
    Menu,
    Minus,
    Moon,
    MessageCircleHeart,
    MessageSquare,
    MessageSquareCheck,
    MessageSquareText,
    MessagesSquare,
    Mic,
    MicOff,
    Monitor,
    MonitorPlay,
    MoreVertical,
    Network,
    PackageCheck,
    PackagePlus,
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
    PowerOff,
    Receipt,
    Radio,
    RadioTower,
    RefreshCw,
    Route,
    RotateCcw,
    RotateCw,
    Save,
    Search,
    Send,
    Settings,
    ScanFace,
    ScanLine,
    ScanQrCode,
    ScreenShare,
    SlidersHorizontal,
    ShieldAlert,
    ShieldCheck,
    ShieldX,
    Smartphone,
    Sparkles,
    Star,
    Settings2,
    Tags,
    TrendingUp,
    TriangleAlert,
    Trash2,
    Underline,
    User,
    UserCheck,
    UserPlus,
    UserPen,
    UserRound,
    UserRoundCheck,
    UserRoundCog,
    Users,
    UsersRound,
    Upload,
    Video,
    VideoOff,
    Wallet,
    Webhook,
    Wrench,
    X,
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
        if (typeof value === 'string' && value.trim().startsWith('JSON.parse(')) {
            try {
                return Function(`"use strict"; return (${value});`)();
            } catch {
                return fallback;
            }
        }

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

document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });
    document.querySelectorAll('[data-chart="attendance"]').forEach(initAttendanceChart);
    document.querySelectorAll('[data-chart="multi-line"]').forEach(initMultiLineChart);
    document.querySelectorAll('[data-chart="giving"]').forEach(initGivingChart);
    document.querySelectorAll('[data-chart="doughnut"]').forEach(initDoughnutChart);
    document.querySelectorAll('[data-chart="sparkline"]').forEach(initSparkline);
});
