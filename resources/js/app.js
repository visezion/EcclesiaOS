import './bootstrap';
import Alpine from 'alpinejs';
import {
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Baby,
    BadgeDollarSign,
    Bell,
    BellRing,
    BookOpen,
    BookPlus,
    Building2,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    ChartNoAxesCombined,
    CheckCircle2,
    ChevronRight,
    ChevronUp,
    CircleHelp,
    ClipboardCheck,
    Cross,
    FileChartColumn,
    FileSearch,
    Gauge,
    GitBranch,
    GraduationCap,
    HandHeart,
    Handshake,
    Heart,
    HeartHandshake,
    Inbox,
    Landmark,
    LayoutDashboard,
    Library,
    ListChecks,
    LogIn,
    Mail,
    Map,
    MapPin,
    Menu,
    MessageCircleHeart,
    MessageSquare,
    MessageSquareCheck,
    MessageSquareText,
    MonitorPlay,
    Network,
    PackageCheck,
    PackagePlus,
    Podcast,
    Receipt,
    Search,
    Send,
    Settings,
    ShieldAlert,
    ShieldCheck,
    Star,
    TrendingUp,
    UserCheck,
    UserPlus,
    UserRound,
    UserRoundCog,
    Users,
    UsersRound,
    Wallet,
    Wrench,
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
Alpine.start();

const icons = {
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    Baby,
    BadgeDollarSign,
    Bell,
    BellRing,
    BookOpen,
    BookPlus,
    Building2,
    CalendarDays,
    CalendarPlus,
    ChartColumn,
    ChartNoAxesCombined,
    CheckCircle2,
    ChevronRight,
    ChevronUp,
    CircleHelp,
    ClipboardCheck,
    Cross,
    FileChartColumn,
    FileSearch,
    Gauge,
    GitBranch,
    GraduationCap,
    HandHeart,
    Handshake,
    Heart,
    HeartHandshake,
    Inbox,
    Landmark,
    LayoutDashboard,
    Library,
    ListChecks,
    LogIn,
    Mail,
    Map,
    MapPin,
    Menu,
    MessageCircleHeart,
    MessageSquare,
    MessageSquareCheck,
    MessageSquareText,
    MonitorPlay,
    Network,
    PackageCheck,
    PackagePlus,
    Podcast,
    Receipt,
    Search,
    Send,
    Settings,
    ShieldAlert,
    ShieldCheck,
    Star,
    TrendingUp,
    UserCheck,
    UserPlus,
    UserRound,
    UserRoundCog,
    Users,
    UsersRound,
    Wallet,
    Wrench,
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

function initGivingChart(canvas) {
    const labels = parseJson(canvas.dataset.labels);
    const values = parseJson(canvas.dataset.values);

    new Chart(canvas, {
        type: 'bar',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: [palette.purple, palette.blue, palette.teal, palette.orange, palette.rose],
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

    new Chart(canvas, {
        type: 'doughnut',
        data: {
            labels,
            datasets: [{
                data: values,
                backgroundColor: [palette.purple, palette.blue, palette.teal, palette.orange, palette.rose],
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
