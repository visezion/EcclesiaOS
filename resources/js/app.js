import './bootstrap';
import Alpine from 'alpinejs';
import {
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Baby,
    BadgeDollarSign,
    Badge,
    BadgeCheck,
    Bell,
    BellOff,
    BellRing,
    BookOpen,
    BookPlus,
    Building2,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    ChartNoAxesCombined,
    CheckCircle2,
    Check,
    ChevronRight,
    ChevronUp,
    CircleDot,
    CircleHelp,
    Clock,
    ClipboardCheck,
    ClipboardList,
    CloudCheck,
    Copy,
    Cross,
    Database,
    Download,
    Eye,
    FileChartColumn,
    FileSearch,
    Fingerprint,
    Gauge,
    GitBranch,
    GraduationCap,
    Globe2,
    Hand,
    HandHeart,
    Handshake,
    HardDrive,
    Heart,
    HeartHandshake,
    Inbox,
    Landmark,
    LayoutDashboard,
    LayoutGrid,
    LayoutList,
    Library,
    Link,
    ListChecks,
    LogIn,
    LogOut,
    Mail,
    Map,
    MapPin,
    Menu,
    Minus,
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
    Pencil,
    Phone,
    Plus,
    Podcast,
    Receipt,
    Radio,
    RadioTower,
    RefreshCw,
    RotateCcw,
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
    Sparkles,
    Star,
    TrendingUp,
    TriangleAlert,
    UserCheck,
    UserPlus,
    UserRound,
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
            return `${this.assignmentBaseUrl}/${this.selectedUserId}`;
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

    Alpine.data('meetingRoom', storageKey => ({
        muted: false,
        camera: false,
        screen: false,
        chat: true,
        hand: false,
        mediaError: '',
        note: localStorage.getItem(storageKey) || '',
        stream: null,

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
            if (! this.stream && this.camera) {
                await this.startCamera();
            }
            this.stream?.getVideoTracks().forEach(track => { track.enabled = this.camera; });
        },

        async toggleMute() {
            this.muted = ! this.muted;
            if (! this.stream) {
                await this.startCamera();
            }
            this.stream?.getAudioTracks().forEach(track => { track.enabled = ! this.muted; });
        },

        saveNote() {
            localStorage.setItem(storageKey, this.note);
        },
    }));
});

Alpine.start();

const icons = {
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Baby,
    BadgeDollarSign,
    Badge,
    BadgeCheck,
    Bell,
    BellOff,
    BellRing,
    BookOpen,
    BookPlus,
    Building2,
    CalendarClock,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    ChartNoAxesCombined,
    CheckCircle2,
    Check,
    ChevronRight,
    ChevronUp,
    CircleDot,
    CircleHelp,
    Clock,
    ClipboardCheck,
    ClipboardList,
    CloudCheck,
    Copy,
    Cross,
    Database,
    Download,
    Eye,
    FileChartColumn,
    FileSearch,
    Fingerprint,
    Gauge,
    GitBranch,
    GraduationCap,
    Globe2,
    Hand,
    HandHeart,
    Handshake,
    HardDrive,
    Heart,
    HeartHandshake,
    Inbox,
    Landmark,
    LayoutDashboard,
    LayoutGrid,
    LayoutList,
    Library,
    Link,
    ListChecks,
    LogIn,
    LogOut,
    Mail,
    Map,
    MapPin,
    Menu,
    Minus,
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
    Pencil,
    Phone,
    Plus,
    Podcast,
    Receipt,
    Radio,
    RadioTower,
    RefreshCw,
    RotateCcw,
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
    Sparkles,
    Star,
    TrendingUp,
    TriangleAlert,
    UserCheck,
    UserPlus,
    UserRound,
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
    document.querySelectorAll('[data-chart="giving"]').forEach(initGivingChart);
    document.querySelectorAll('[data-chart="doughnut"]').forEach(initDoughnutChart);
    document.querySelectorAll('[data-chart="sparkline"]').forEach(initSparkline);
});
