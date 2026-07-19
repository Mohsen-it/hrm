<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { PageHeader, Button, Card, EmptyState } from '@/Components/ui';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();

const props = defineProps({
    user: { type: Object, required: true },
});
</script>

<template>
    <AppLayout :title="t('users.manage_fingerprints')">
        <PageHeader
            :title="t('users.manage_fingerprints')"
            :description="`${user.name} — ${t('users.fingerprints_description')}`"
        >
            <template #actions>
                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('users.show', user.id)">{{ t('common.back') }}</Button>
            </template>
        </PageHeader>

        <Card variant="base" padding="none">
            <div class="p-5 sm:p-6">
                <EmptyState
                    :title="t('users.no_fingerprints')"
                    :description="t('users.fingerprints_description')"
                />
            </div>
        </Card>
    </AppLayout>
</template>
