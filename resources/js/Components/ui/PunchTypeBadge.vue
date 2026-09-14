<script setup>
import { computed } from 'vue';
import Badge from './Badge.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    type: { type: String, default: 'unknown' },
    dir: { type: String, default: 'rtl' },
    withHint: { type: Boolean, default: true },
});

const { t } = useTranslations();

const variant = computed(() => ({
    check_in: 'active',
    check_out: 'info',
    break_out: 'pending',
    break_in: 'orange',
    extra: 'inactive',
    unknown: 'pending',
}[props.type] || 'inactive'));

const label = computed(() => t(`attendance.punch_type.${props.type}`, props.type));

const hint = computed(() => {
    if (!props.withHint) return '';
    if (props.type === 'extra') return t('attendance.punch_type.extra_hint', 'بصمة خارج نوافذ الدخول والانصراف — محفوظة للتدقيق ولا تفتح أو تغلق جلسة');
    if (props.type === 'unknown') return t('attendance.punch_type.unknown_hint', 'تعذر التصنيف — تحقق من نوافذ الدورية وإسناد الموظف');
    return '';
});
</script>

<template>
    <Badge :text="label" :variant="variant" :dir="dir" :title="hint" />
</template>
