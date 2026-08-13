<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Button, FormInput } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    value: { type: Number, required: true },
});

const { t, direction } = useTranslations();

const gap = ref(props.value);
const saving = ref(false);

function save() {
    const minutes = Math.max(1, Math.min(120, Math.round(Number(gap.value) || 2)));
    gap.value = minutes;
    saving.value = true;

    router.post(route('user-activity.idle-gap'), { idle_gap_minutes: minutes }, {
        preserveState: true,
        preserveScroll: true,
        onFinish: () => { saving.value = false; },
    });
}
</script>

<template>
    <div class="flex items-end gap-2" :dir="direction">
        <div class="w-36">
            <FormInput
                v-model="gap"
                type="number"
                :label="t('useractivity.idle_gap_label')"
                :hint="t('useractivity.idle_gap_hint')"
                :min="1"
                :max="120"
                :dir="direction"
                @keyup.enter="save"
            />
        </div>
        <Button variant="secondary" :loading="saving" icon="fas fa-floppy-disk" @click="save">
            {{ t('useractivity.save') }}
        </Button>
    </div>
</template>
