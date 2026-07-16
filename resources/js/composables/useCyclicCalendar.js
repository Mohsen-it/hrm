export function isWorkDay(startDate, workDays, restDays, targetDate) {
    const start = new Date(startDate)
    const target = new Date(targetDate)
    const diffDays = Math.floor((target - start) / (1000 * 60 * 60 * 24))
    if (diffDays < 0) return false
    const cycleLength = workDays + restDays
    return (diffDays % cycleLength) < workDays
}

export function getMonthCalendar(year, month, startDate, workDays, restDays) {
    const daysInMonth = new Date(year, month, 0).getDate()
    const arabicDays = ['الأحد', 'الإثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']
    const calendar = []

    for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month - 1, day)
        const dateStr = date.toISOString().split('T')[0]
        calendar.push({
            date: dateStr,
            dayName: arabicDays[date.getDay()],
            dayOfWeek: date.getDay(),
            isWorkDay: isWorkDay(startDate, workDays, restDays, dateStr),
        })
    }

    return calendar
}

export function getNextWorkDay(startDate, workDays, restDays, fromDate) {
    const next = new Date(fromDate)
    next.setDate(next.getDate() + 1)
    while (!isWorkDay(startDate, workDays, restDays, next.toISOString().split('T')[0])) {
        next.setDate(next.getDate() + 1)
    }
    return next
}
