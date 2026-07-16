/**
 * useRotationPreview — composable for rotation schedule preview calculations.
 *
 * Provides helpers for visualizing rotation patterns and group schedules
 * on the frontend, matching the backend RotationEngine logic.
 */

export function isRotationWorkDay(pattern, cycleLength, groupIndex, targetDate, anchorDate) {
    const target = new Date(targetDate);
    const anchor = new Date(anchorDate);
    const diffDays = Math.floor((target - anchor) / (1000 * 60 * 60 * 24));
    const positionInCycle = (diffDays + groupIndex) % cycleLength;
    return pattern[positionInCycle] === 1;
}

export function getRotationMonthCalendar(year, month, pattern, groupIndex, anchorDate) {
    const cycleLength = pattern.length;
    const daysInMonth = new Date(year, month, 0).getDate();
    const arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
    const calendar = [];

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month - 1, day);
        const dateStr = date.toISOString().split('T')[0];
        calendar.push({
            date: dateStr,
            dayName: arabicDays[date.getDay()],
            dayOfWeek: date.getDay(),
            isWorkDay: isRotationWorkDay(pattern, cycleLength, groupIndex, dateStr, anchorDate),
        });
    }

    return calendar;
}

export function getGroupSchedules(pattern, numberOfGroups, anchorDate, fromDate, toDate) {
    const cycleLength = pattern.length;
    const offsetStep = Math.floor(cycleLength / Math.max(numberOfGroups, 1));
    const groups = [];

    for (let g = 0; g < numberOfGroups; g++) {
        groups.push({
            name: String.fromCharCode(65 + g),
            groupIndex: g * offsetStep,
        });
    }

    const current = new Date(fromDate);
    const end = new Date(toDate);
    const schedule = [];

    while (current <= end) {
        const dateStr = current.toISOString().split('T')[0];
        const dayData = { date: dateStr, groups: {} };

        groups.forEach(group => {
            dayData.groups[group.name] = isRotationWorkDay(
                pattern,
                cycleLength,
                group.groupIndex,
                dateStr,
                anchorDate
            );
        });

        schedule.push(dayData);
        current.setDate(current.getDate() + 1);
    }

    return { groups, schedule };
}
