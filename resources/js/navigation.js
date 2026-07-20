/**
 * Navigation Configuration — Centralized menu definition
 *
 * Each group defines a section of the sidebar navigation.
 * Items are permission-gated via the `permissions` array.
 * The `id` is used for favorites persistence and active-state matching.
 */

export const navigationGroups = [
  {
    key: 'dashboard',
    label: 'menu.dashboard',
    items: [
      {
        id: 'dashboard',
        label: 'menu.dashboard',
        route: 'dashboard',
        icon: 'fa-solid fa-gauge-high',
        permissions: ['view-dashboard'],
      },
    ],
  },
  {
    key: 'people',
    label: 'common.people',
    items: [
      {
        id: 'companies',
        label: 'menu.companies',
        route: 'companies.index',
        icon: 'fa-solid fa-building',
        permissions: ['view-companies'],
      },
      {
        id: 'branches',
        label: 'menu.branches',
        route: 'branches.index',
        icon: 'fa-solid fa-code-branch',
        permissions: ['view-branches'],
      },
      {
        id: 'departments',
        label: 'menu.departments',
        route: 'departments.index',
        icon: 'fa-solid fa-sitemap',
        permissions: ['view-departments'],
      },
      {
        id: 'positions',
        label: 'menu.positions',
        route: 'positions.index',
        icon: 'fa-solid fa-briefcase',
        permissions: ['view-positions'],
      },
      {
        id: 'grades',
        label: 'menu.grades',
        route: 'grades.index',
        icon: 'fa-solid fa-layer-group',
        permissions: ['view-grades'],
      },
      {
        id: 'subordinations',
        label: 'menu.subordinations',
        route: 'subordinations.index',
        icon: 'fa-solid fa-map-location-dot',
        permissions: ['view-subordinations'],
      },
      {
        id: 'users',
        label: 'menu.users',
        route: 'users.index',
        icon: 'fa-solid fa-users',
        permissions: ['view-users'],
      },
    ],
  },
  {
    key: 'attendance',
    label: 'common.attendance',
    items: [
      {
        id: 'attendance.live',
        label: 'menu.attendance_live',
        route: 'attendance.live.index',
        icon: 'fa-solid fa-satellite-dish',
        permissions: ['view-attendance'],
      },
      {
        id: 'attendance.sessions',
        label: 'menu.attendance_sessions',
        route: 'attendance.sessions.index',
        icon: 'fa-solid fa-calendar-check',
        permissions: ['view-attendance'],
      },
      {
        id: 'attendance.daily-summaries',
        label: 'menu.attendance_daily_summaries',
        route: 'attendance.daily-summaries.index',
        icon: 'fa-solid fa-calendar-day',
        permissions: ['view-attendance'],
      },
      {
        id: 'attendance.raw-logs',
        label: 'menu.attendance_raw_logs',
        route: 'attendance.raw-logs.index',
        icon: 'fa-solid fa-scroll',
        permissions: ['view-attendance'],
      },
      {
        id: 'attendance.reports',
        label: 'menu.attendance_reports',
        route: 'attendance.reports.index',
        icon: 'fa-solid fa-chart-line',
        permissions: ['view-attendance'],
      },
      {
        id: 'attendance.groups',
        label: 'menu.attendance_groups',
        route: 'attendance.groups.index',
        icon: 'fa-solid fa-users-rectangle',
        permissions: ['view-attendance-groups'],
      },
      {
        id: 'attendance.shifts',
        label: 'menu.attendance_shifts',
        route: 'attendance.shifts.index',
        icon: 'fa-solid fa-clock',
        permissions: ['view-attendance-shifts'],
      },
      {
        id: 'attendance.group-schedules',
        label: 'menu.group_schedules',
        route: 'attendance.group-schedules.index',
        icon: 'fa-solid fa-table-cells',
        permissions: ['view-group-schedules'],
      },
      {
        id: 'attendance.smart-absence',
        label: 'menu.smart_absence',
        route: 'smart-absence.daily',
        icon: 'fa-solid fa-user-clock',
        permissions: ['view-attendance-by-schedule'],
      },
    ],
  },
  {
    key: 'shifts',
    label: 'common.shifts',
    items: [
    
 
    
      {
        id: 'shifts.rotations',
        label: 'menu.rotations',
        route: 'rotations.index',
        icon: 'fa-solid fa-rotate',
        permissions: ['view-rotations'],
      },
      {
        id: 'shifts.rotation-assignments',
        label: 'menu.rotation_assignments',
        route: 'rotations.assign',
        icon: 'fa-solid fa-people-arrows',
        permissions: ['assign-employees-to-rotation'],
      },
        {
        id: 'shifts.schedules',
        label: 'menu.schedules',
        route: 'schedules.index',
        icon: 'fa-solid fa-calendar-days',
        permissions: ['view-shift-categories'],
      },
    ],
  },
  {
    key: 'leave',
    label: 'common.leave',
    items: [
      {
        id: 'leave.requests',
        label: 'menu.vacation_requests',
        route: 'vacations.requests.index',
        icon: 'fa-solid fa-inbox',
        permissions: ['view-vacation-requests'],
      },
      {
        id: 'leave.my',
        label: 'menu.my_vacations',
        route: 'vacations.my.index',
        icon: 'fa-solid fa-suitcase-rolling',
        permissions: ['view-vacations'],
      },
      {
        id: 'leave.types',
        label: 'menu.vacation_types',
        route: 'vacations.types.index',
        icon: 'fa-solid fa-tags',
        permissions: ['view-vacation-types'],
      },
      {
        id: 'leave.holidays',
        label: 'menu.holidays',
        route: 'holidays.index',
        icon: 'fa-solid fa-umbrella-beach',
        permissions: ['view-holidays'],
      },
    ],
  },
  {
    key: 'devices',
    label: 'common.devices',
    items: [
      {
        id: 'devices.dashboard',
        label: 'menu.device_dashboard',
        route: 'fingerprint-devices.dashboard',
        icon: 'fa-solid fa-gauge',
        permissions: ['view-fingerprint-devices'],
      },
      {
        id: 'devices.devices',
        label: 'menu.devices',
        route: 'fingerprint-devices.index',
        icon: 'fa-solid fa-microchip',
        permissions: ['view-fingerprint-devices'],
      },
      {
        id: 'devices.types',
        label: 'menu.device_types',
        route: 'fingerprint-device-types.index',
        icon: 'fa-solid fa-list-check',
        permissions: ['view-fingerprint-device-types'],
      },
      {
        id: 'devices.monitoring',
        label: 'menu.device_monitoring',
        route: 'fingerprint-devices.monitoring',
        icon: 'fa-solid fa-desktop',
        permissions: ['view-fingerprint-devices'],
      },
      {
        id: 'devices.live-scan',
        label: 'menu.device_live_scan',
        route: 'fingerprint-devices.live-scan',
        icon: 'fa-solid fa-fingerprint',
        permissions: ['view-fingerprint-devices'],
      },
      {
        id: 'devices.sync',
        label: 'menu.device_sync',
        route: 'fingerprint-devices.sync',
        icon: 'fa-solid fa-arrow-right-arrow-left',
        permissions: ['view-fingerprint-devices'],
      },
      {
        id: 'devices.templates',
        label: 'menu.device_templates',
        route: 'fingerprint-templates.index',
        icon: 'fa-solid fa-id-card',
        permissions: ['view-fingerprint-devices'],
      },
    ],
  },
  {
    key: 'zones',
    label: 'zones.title',
    items: [
      {
        id: 'zones.dashboard',
        label: 'menu.zones_dashboard',
        route: 'zones.dashboard',
        icon: 'fa-solid fa-chart-pie',
        permissions: ['view-zones'],
      },
      {
        id: 'zones.zones',
        label: 'menu.zones',
        route: 'zones.index',
        icon: 'fa-solid fa-earth-americas',
        permissions: ['view-zones'],
      },
    ],
  },
  {
    key: 'admin',
    label: 'common.system',
    items: [
      {
        id: 'admin.settings',
        label: 'menu.settings',
        route: 'settings.index',
        icon: 'fa-solid fa-gear',
        permissions: ['view-settings'],
      },
      {
        id: 'admin.roles',
        label: 'menu.roles',
        route: 'roles.index',
        icon: 'fa-solid fa-shield-halved',
        permissions: ['view-roles'],
      },
      {
        id: 'admin.permissions',
        label: 'menu.permissions',
        route: 'permissions.index',
        icon: 'fa-solid fa-key',
        permissions: ['view-permissions'],
      },
    ],
  },
];

/**
 * Module switcher definitions — Groups sidebar sections into logical modules
 * for the top-bar module switcher dropdown.
 */
export const navigationModules = [
  {
    id: 'dashboard',
    label: 'menu.dashboard',
    icon: 'fa-solid fa-gauge-high',
    color: 'bg-mistral-primary/10 text-mistral-primary',
    groupKeys: ['dashboard'],
    route: 'dashboard',
    permissions: ['view-dashboard'],
  },
  {
    id: 'people',
    label: 'common.people',
    icon: 'fa-solid fa-users',
    color: 'bg-blue-50 text-blue-600',
    groupKeys: ['people'],
    route: 'companies.index',
    permissions: ['view-companies', 'view-branches', 'view-departments', 'view-positions', 'view-grades', 'view-users'],
  },
  {
    id: 'attendance',
    label: 'common.attendance',
    icon: 'fa-solid fa-calendar-check',
    color: 'bg-mistral-success/10 text-mistral-success',
    groupKeys: ['attendance'],
    route: 'attendance.sessions.index',
    permissions: ['view-attendance'],
  },
  {
    id: 'shifts',
    label: 'common.shifts',
    icon: 'fa-solid fa-clock',
    color: 'bg-mistral-info/10 text-mistral-info',
    groupKeys: ['shifts'],
    route: 'rotations.index',
    permissions: ['view-rotations'],
  },
  {
    id: 'leave',
    label: 'common.leave',
    icon: 'fa-solid fa-umbrella-beach',
    color: 'bg-mistral-warning/10 text-mistral-warning',
    groupKeys: ['leave'],
    route: 'vacations.requests.index',
    permissions: ['view-vacation-requests', 'view-vacations'],
  },
  {
    id: 'devices',
    label: 'common.devices',
    icon: 'fa-solid fa-microchip',
    color: 'bg-purple-50 text-purple-600',
    groupKeys: ['devices'],
    route: 'fingerprint-devices.index',
    permissions: ['view-fingerprint-devices'],
  },
  {
    id: 'zones',
    label: 'zones.title',
    icon: 'fa-solid fa-earth-americas',
    color: 'bg-cyan-50 text-cyan-600',
    groupKeys: ['zones'],
    route: 'zones.index',
    permissions: ['view-zones'],
  },
  {
    id: 'admin',
    label: 'common.system',
    icon: 'fa-solid fa-gear',
    color: 'bg-mistral-surface text-mistral-steel',
    groupKeys: ['admin'],
    route: 'settings.index',
    permissions: ['view-settings', 'view-roles', 'view-permissions'],
  },
];

/**
 * Flatten all navigation items into a single array for search
 */
export function flattenNavItems(groups) {
  const items = [];
  for (const group of groups) {
    for (const item of group.items) {
      items.push({ ...item, groupKey: group.key, groupLabel: group.label });
    }
  }
  return items;
}
