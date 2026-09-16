<script>
import AppLayout from '@/Layouts/AppLayout.vue';

export default {
    layout: AppLayout,
};
</script>

<script setup>
import { ref, computed } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { PageHeader, Badge, Button, Alert, Card, FormTextarea, FormCheckbox } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';
import { usePageTitle } from '@/composables/usePageTitle';

const { t } = useTranslations();
const page = usePage();

const props = defineProps({
    backup: { type: Object, required: true },
    audit: { type: Array, default: () => [] },
});

const restoreForm = useForm({ reason: '', confirm: false });

function formatDate(dateStr) {
    if (!dateStr) return '—';
    try {
        const d = new Date(dateStr);
        return d.toLocaleDateString('ar-EG', {
            year: 'numeric', month: 'long', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    } catch {
        return dateStr;
    }
}

function statusLabel(s) {
    return t(`backups.status_${s}`) || s;
}

function typeLabel(s) {
    return t(`backups.type_${s}`) || s;
}

function submitRestore() {
    restoreForm.post(route('backups.restore', props.backup.id), { preserveScroll: true });
}

function verify() {
    router.post(route('backups.verify', props.backup.id), {}, { preserveScroll: true });
}

function restoreTest() {
    router.post(route('backups.restore-test', props.backup.id), {}, { preserveScroll: true });
}

function formatSize(bytes) {
    if (!bytes) return '—';
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

const canRestoreProduction = computed(() => page.props.auth?.permissions?.includes('restore-backups-production'));

const flashSuccess = computed(() => page.props.flash?.success);
const flashError = computed(() => page.props.flash?.error);

usePageTitle(`${t('backups.title')} #${props.backup.id}`);
</script>

<template>
    <PageHeader :title="`${t('backups.title')} #${backup.id}`" :description="backup.file_name">
        <template #actions>
            <Button variant="secondary" icon="fas fa-arrow-right" :href="route('backups.index')">
                {{ t('backups.back_to_list') }}
            </Button>
            <Button variant="secondary" icon="fas fa-download" :href="route('backups.download', backup.id)">
                {{ t('backups.download') }}
            </Button>
        </template>
    </PageHeader>

    <Alert v-if="flashSuccess" type="success" :message="flashSuccess" class="mb-4" />
    <Alert v-if="flashError" type="danger" :message="flashError" class="mb-4" />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <Card>
            <template #header>
                <h3 class="text-[15px] font-bold text-mistral-ink">{{ t('backups.details') }}</h3>
            </template>
            <dl class="grid grid-cols-2 gap-3 text-sm">
                <dt class="text-mistral-steel">{{ t('backups.file') }}</dt>
                <dd class="font-mono break-all text-xs">{{ backup.file_name }}</dd>
                <dt class="text-mistral-steel">{{ t('backups.type') }}</dt>
                <dd><Badge :text="typeLabel(backup.type)" variant="info" /></dd>
                <dt class="text-mistral-steel">{{ t('backups.status') }}</dt>
                <dd><Badge :text="statusLabel(backup.status)" :variant="backup.status === 'completed' ? 'success' : 'danger'" /></dd>
                <dt class="text-mistral-steel">{{ t('backups.verification') }}</dt>
                <dd><Badge :text="statusLabel(backup.verification_status)" :variant="backup.verification_status === 'verified' ? 'success' : 'warning'" /></dd>
                <dt class="text-mistral-steel">{{ t('backups.size') }}</dt>
                <dd>{{ formatSize(backup.file_size) }}</dd>
                <dt class="text-mistral-steel">{{ t('backups.checksum') }}</dt>
                <dd class="font-mono text-xs break-all" dir="ltr">{{ backup.checksum }}</dd>
                <dt class="text-mistral-steel">{{ t('backups.created_at') }}</dt>
                <dd>{{ formatDate(backup.created_at) }}</dd>
            </dl>

            <div class="flex gap-2 mt-4 pt-4 border-t border-mistral-hairline-soft">
                <Button variant="secondary" size="sm" icon="fas fa-check" @click="verify">
                    {{ t('backups.verify_now') }}
                </Button>
                <Button variant="secondary" size="sm" icon="fas fa-flask" @click="restoreTest">
                    {{ t('backups.test_restore_now') }}
                </Button>
            </div>
        </Card>

        <!-- Safety guarantee -->
        <Card>
            <template #header>
                <h3 class="text-[15px] font-bold text-mistral-ink">{{ t('backups.safety_title') }}</h3>
            </template>
            <ul class="space-y-2 text-[13px] text-mistral-ink p-4">
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-mistral-success mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                    <span>{{ t('backups.safety_checksum') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-mistral-success mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                    <span>{{ t('backups.safety_test_first') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-mistral-success mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                    <span>{{ t('backups.safety_pre_backup') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-mistral-success mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                    <span>{{ t('backups.safety_health_check') }}</span>
                </li>
                <li class="flex items-start gap-2">
                    <i class="fas fa-check-circle text-mistral-success mt-0.5 flex-shrink-0" aria-hidden="true"></i>
                    <span>{{ t('backups.safety_rollback') }}</span>
                </li>
            </ul>
            <div class="bg-mistral-success/5 border-t border-mistral-success/20 p-4 text-center">
                <p class="text-[13px] font-semibold text-mistral-success">
                    ✅ {{ t('backups.safety_guarantee') }}
                </p>
            </div>
        </Card>

        <div class="space-y-4 lg:col-span-2">
            <Card v-if="canRestoreProduction">
                <template #header>
                    <h3 class="text-[15px] font-bold text-mistral-ink">{{ t('backups.restore_production') }}</h3>
                </template>
                <Alert type="warning" :message="t('backups.danger_zone')" class="mb-4" />
                <form @submit.prevent="submitRestore" class="space-y-3">
                    <FormTextarea
                        v-model="restoreForm.reason"
                        :label="t('backups.restore_reason_label')"
                        :placeholder="t('backups.restore_reason_placeholder')"
                        :error="restoreForm.errors.reason"
                        :rows="3"
                    />
                    <FormCheckbox
                        v-model="restoreForm.confirm"
                        :label="t('backups.confirm_restore')"
                        :error="restoreForm.errors.confirm"
                    />
                    <Button variant="danger" icon="fas fa-triangle-exclamation" :loading="restoreForm.processing" type="submit">
                        {{ t('backups.execute_restore') }}
                    </Button>
                </form>
            </Card>

            <Card>
                <template #header>
                    <h3 class="text-[15px] font-bold text-mistral-ink">{{ t('backups.audit_trail') }}</h3>
                </template>
                <ul v-if="audit.length" class="space-y-2 text-sm">
                    <li v-for="entry in audit" :key="entry.id" class="flex justify-between gap-2 border-b border-mistral-hairline-soft pb-2">
                        <span class="font-medium">{{ entry.event }}</span>
                        <span class="text-mistral-steel text-xs" dir="ltr">{{ formatDate(entry.created_at) }}</span>
                    </li>
                </ul>
                <p v-else class="text-mistral-steel text-sm">—</p>
            </Card>
        </div>
    </div>
</template>
