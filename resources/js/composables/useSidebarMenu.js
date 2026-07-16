import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

export function useSidebarMenu() {
    const page = usePage();

    const permissions = computed(
        () => page.props.auth?.permissions || [],
    );

    function has(...perms) {
        if (!perms.length) return true;
        if (permissions.value.length === 0) return true;
        return perms.some((p) => permissions.value.includes(p));
    }

    const menuGroups = computed(() => {
        const groups = [];

        groups.push({
            key: 'main',
            section: 'common.main',
            icon: 'fas fa-tachometer-alt',
            items: [
                { label: 'menu.dashboard', route: 'dashboard', icon: 'fas fa-home' },
            ],
        });

        if (
            has('view-companies', 'view-branches', 'view-departments', 'view-positions', 'view-grades')
        ) {
            const items = [];
            if (has('view-companies')) items.push({ label: 'menu.companies', route: 'companies.index', icon: 'fas fa-building' });
            if (has('view-branches')) items.push({ label: 'menu.branches', route: 'branches.index', icon: 'fas fa-code-branch' });
            if (has('view-departments')) items.push({ label: 'menu.departments', route: 'departments.index', icon: 'fas fa-sitemap' });
            if (has('view-positions')) items.push({ label: 'menu.positions', route: 'positions.index', icon: 'fas fa-briefcase' });
            if (has('view-grades')) items.push({ label: 'menu.grades', route: 'grades.index', icon: 'fas fa-layer-group' });
            if (items.length) {
                groups.push({
                    key: 'organization',
                    section: 'common.organization',
                    icon: 'fas fa-sitemap',
                    items,
                });
            }
        }

        if (has('view-users', 'view-shifts', 'view-shift-categories', 'view-time-schedules', 'view-rotations')) {
            const items = [];
            if (has('view-users')) items.push({ label: 'menu.users', route: 'users.index', icon: 'fas fa-users' });
            if (has('view-shift-categories')) items.push({ label: 'menu.shift_categories', route: 'shift-categories.index', icon: 'fas fa-layer-group' });
            if (has('view-time-schedules')) items.push({ label: 'menu.time_schedules', route: 'time-schedules.index', icon: 'fas fa-calendar-alt' });
            if (has('view-shifts')) items.push({ label: 'menu.shifts', route: 'shifts.index', icon: 'fas fa-clock' });
            if (has('view-rotations')) items.push({ label: 'menu.rotations', route: 'rotations.index', icon: 'fas fa-sync-alt' });
            if (has('view-shift-categories')) items.push({ label: 'menu.shift_assignments', route: 'shift-assignments.index', icon: 'fas fa-user-check' });
            if (has('view-rotations')) items.push({ label: 'menu.rotation_assignments', route: 'rotations.assign', icon: 'fas fa-users-cog' });
            if (has('view-shift-categories')) items.push({ label: 'menu.schedules', route: 'schedules.index', icon: 'fas fa-calendar-check' });
            if (items.length) {
                groups.push({
                    key: 'personnel',
                    section: 'common.personnel',
                    icon: 'fas fa-users-cog',
                    items,
                });
            }
        }

        if (has('view-attendance')) {
            const items = [
                { label: 'menu.attendance_live', route: 'attendance.live.index', icon: 'fas fa-satellite-dish' },
                { label: 'menu.attendance_sessions', route: 'attendance.sessions.index', icon: 'fas fa-calendar-check' },
                { label: 'menu.attendance_raw_logs', route: 'attendance.raw-logs.index', icon: 'fas fa-list' },
                { label: 'menu.attendance_daily_summaries', route: 'attendance.daily-summaries.index', icon: 'fas fa-calendar-day' },
                { label: 'menu.attendance_reports', route: 'attendance.reports.index', icon: 'fas fa-chart-line' },
            ];
            if (has('view-attendance-by-schedule')) {
                items.push({ label: 'menu.smart_absence', route: 'smart-absence.daily', icon: 'fas fa-user-clock' });
            }
            groups.push({
                key: 'attendance',
                section: 'common.attendance',
                icon: 'fas fa-fingerprint',
                items,
            });
        }

        if (has('view-attendance-groups', 'view-attendance-shifts', 'view-group-schedules', 'view-time-intervals')) {
            const items = [];
            if (has('view-attendance-groups')) items.push({ label: 'menu.attendance_groups', route: 'attendance.groups.index', icon: 'fas fa-layer-group' });
            if (has('view-attendance-shifts')) items.push({ label: 'menu.attendance_shifts', route: 'attendance.shifts.index', icon: 'fas fa-clock' });
            if (has('view-group-schedules')) items.push({ label: 'menu.group_schedules', route: 'attendance.group-schedules.index', icon: 'fas fa-calendar-alt' });
            if (items.length) {
                groups.push({
                    key: 'attendance-config',
                    section: 'common.attendance_config',
                    icon: 'fas fa-cogs',
                    items,
                });
            }
        }

        if (has('view-vacations', 'view-vacation-types', 'view-vacation-requests', 'view-holidays')) {
            const items = [];
            if (has('view-vacation-requests')) items.push({ label: 'menu.vacation_requests', route: 'vacations.requests.index', icon: 'fas fa-inbox' });
            if (has('view-vacations')) items.push({ label: 'menu.my_vacations', route: 'vacations.my.index', icon: 'fas fa-suitcase-rolling' });
            if (has('view-vacation-types')) items.push({ label: 'menu.vacation_types', route: 'vacations.types.index', icon: 'fas fa-tags' });
            if (has('view-holidays')) items.push({ label: 'menu.holidays', route: 'holidays.index', icon: 'fas fa-calendar-day' });
            if (items.length) {
                groups.push({
                    key: 'leave',
                    section: 'common.leave',
                    icon: 'fas fa-umbrella-beach',
                    items,
                });
            }
        }

        if (has('view-fingerprint-devices', 'view-fingerprint-device-types')) {
            const items = [];
            if (has('view-fingerprint-devices')) items.push({ label: 'menu.device_dashboard', route: 'fingerprint-devices.dashboard', icon: 'fas fa-chart-bar' });
            if (has('view-fingerprint-devices')) items.push({ label: 'menu.devices', route: 'fingerprint-devices.index', icon: 'fas fa-microchip' });
            if (has('view-fingerprint-device-types')) items.push({ label: 'menu.device_types', route: 'fingerprint-device-types.index', icon: 'fas fa-th-list' });
            if (items.length) {
                groups.push({
                    key: 'devices',
                    section: 'common.devices',
                    icon: 'fas fa-microchip',
                    items,
                });
            }
        }

        if (has('view-zones')) {
            const items = [
                { label: 'menu.zones_dashboard', route: 'zones.dashboard', icon: 'fas fa-chart-pie' },
                { label: 'menu.zones', route: 'zones.index', icon: 'fas fa-globe' },
            ];
            groups.push({
                key: 'geography',
                section: 'zones.title',
                icon: 'fas fa-globe',
                items,
            });
        }

        if (has('view-settings')) {
            const items = [
                { label: 'menu.settings', route: 'settings.index', icon: 'fas fa-sliders-h' },
            ];
            groups.push({
                key: 'system',
                section: 'common.system',
                icon: 'fas fa-cog',
                items,
            });
        }

        return groups;
    });

    return {
        permissions,
        menuGroups,
    };
}
