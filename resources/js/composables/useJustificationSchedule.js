import { ref, watch } from 'vue';

export function useJustificationSchedule(form) {
    const schedule = ref(null);
    const loading = ref(false);
    let requestId = 0;

    const load = async () => {
        if (!form.user_id || !form.attendance_date) {
            schedule.value = null;
            return;
        }

        const id = ++requestId;
        loading.value = true;
        try {
            const response = await fetch(route('vacations.justifications.schedule', {
                user_id: form.user_id,
                attendance_date: form.attendance_date,
            }), { headers: { Accept: 'application/json' } });

            if (!response.ok) throw new Error('Unable to resolve schedule');
            if (id === requestId) schedule.value = await response.json();
        } catch {
            if (id === requestId) schedule.value = null;
        } finally {
            if (id === requestId) loading.value = false;
        }
    };

    watch(() => [form.user_id, form.attendance_date], load, { immediate: true });

    return { schedule, loading };
}
