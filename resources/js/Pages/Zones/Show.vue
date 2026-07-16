<script setup>

import { computed, ref } from 'vue';

import { Link, usePage } from '@inertiajs/vue3';

import AppLayout from '@/Layouts/AppLayout.vue';

import PageHeader from '@/Components/ui/PageHeader.vue';

import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';

import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';

import Badge from '@/Components/ui/Badge.vue';

import { useTranslations } from '@/composables/useTranslations';

const { t, locale } = useTranslations();

const page = usePage();

const props = defineProps({

    zone: { type: Object, required: true },

    branches: { type: Array, default: () => [] },

});

const showDelete = ref(false);

const flashSuccess = computed(() => page.props.flash?.success);

const displayName = computed(() => (locale.value === 'en' && props.zone.name_en ? props.zone.name_en : props.zone.name_ar));

function performDelete() {

   
const r = confirm(t('zones.delete_confirm_message', { name: props.zone.name_ar }));

    if (r) {

       
const form = document.createElement('form');

        form.method = 'POST';

        form.action = route('zones.destroy', props.zone.id);

       
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        form.innerHTML = `<input type="hidden" name="_method" value="DELETE" /><input type="hidden" name="_token" value="${csrf}" />`;

        document.body.appendChild(form);

        form.submit();

    }

}

function performDeleteBranch(branchId) {

    if (!confirm(t('zones.confirm_remove_branch'))) return;

   
const form = document.createElement('form');

    form.method = 'POST';

    form.action = route('zones.branches.detach', [props.zone.id, branchId]);

   
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    form.innerHTML = `<input type="hidden" name="_method" value="DELETE" /><input type="hidden" name="_token" value="${csrf}" />`;

    document.body.appendChild(form);

    form.submit();

}

</script>

<template>

    <AppLayout :title="displayName">

        <PageHeader :title="displayName" :description="zone.code">

            <template #actions>

                <Button variant="secondary" icon="fas fa-arrow-right rtl-flip" :href="route('zones.index')">{{ t('common.back') }}</Button>

                <Button variant="secondary" :href="route('zones.branches', zone.id)" icon="fas fa-code-branch">{{ t('zones.manage_branches') }}</Button>

                <Button variant="primary" icon="fas fa-edit" :href="route('zones.edit', zone.id)">{{ t('common.edit') }}</Button>

                <Button variant="danger" icon="fas fa-trash" @click="showDelete = true">{{ t('common.delete') }}</Button>

            
</template>

        </PageHeader>

        <div v-if="flashSuccess" class="alert alert-success flex items-center gap-2 mb-4">

            <i class="fas fa-check-circle"></i>

            <span>{{ flashSuccess }}</span>

        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">

            <div class="card p-6 lg:col-span-2">

                <h3 class="text-[14px] font-semibold mb-4 flex items-center gap-2">

                    <i class="fas fa-info-circle text-mistral-primary"></i>

                    {{ t('zones.zone_information') }}

                </h3>

                <dl class="grid grid-cols-1 md:grid-cols-2 gap-3 text-[13px]">

                    <div><dt class="text-mistral-steel">{{ t('zones.code') }}</dt>
<dd class="font-medium">{{ zone.code }}</dd>
</div>

                    <div><dt class="text-mistral-steel">{{ t('zones.name_ar') }}</dt>
<dd class="font-medium">{{ zone.name_ar }}</dd>
</div>

                    <div><dt class="text-mistral-steel">{{ t('zones.name_en') }}</dt>
<dd class="font-medium">{{ zone.name_en || '���' }}</dd>
</div>

                    <div><dt class="text-mistral-steel">{{ t('zones.zone_type') }}</dt>
<dd>{{ t('zones.zone_type_' + zone.zone_type) }}</dd>
</div>

                    <div><dt class="text-mistral-steel">{{ t('zones.country') }}</dt>
<dd>{{ zone.country || '���' }}</dd>
</div>

                    <div><dt class="text-mistral-steel">{{ t('zones.region') }}</dt>
<dd>{{ zone.region || '���' }}</dd>
</div>

                    <div><dt class="text-mistral-steel">{{ t('zones.city') }}</dt>
<dd>{{ zone.city || '���' }}</dd>
</div>

                    <div><dt class="text-mistral-steel">{{ t('common.status') }}</dt>
<dd>

                        <Badge v-if="zone.is_active" :text="t('common.active')" variant="active" />

                        <Badge v-else :text="t('common.inactive')" variant="inactive" />

                    </dd>
</div>

                </dl>

            </div>

            <div class="card p-6">

                <h3 class="text-[14px] font-semibold mb-4 flex items-center gap-2">

                    <i class="fas fa-chart-bar text-mistral-primary"></i>

                    {{ t('zones.stats.total') }}

                </h3>

                <ul class="space-y-3 text-[13px]">

                    <li class="flex items-center justify-between"><span class="text-mistral-steel">{{ t('zones.branches') }}</span>
<span class="font-semibold">{{ zone.branches_count || 0 }}</span>
</li>

                    <li class="flex items-center justify-between"><span class="text-mistral-steel">{{ t('zones.employees_count') }}</span>
<span class="font-semibold">{{ zone.employees_count || 0 }}</span>
</li>

                    <li class="flex items-center justify-between"><span class="text-mistral-steel">{{ t('zones.devices_count') }}</span>
<span class="font-semibold">{{ zone.devices_count || 0 }}</span>
</li>

                </ul>

            </div>

        </div>

        <div class="card p-5 mt-4" v-if="zone.description">

            <h3 class="text-[14px] font-semibold mb-3 flex items-center gap-2">

                <i class="fas fa-align-left text-mistral-primary"></i>

                {{ t('zones.description') }}

            </h3>

            <p class="text-[13px] leading-relaxed whitespace-pre-line">{{ zone.description }}</p>

        </div>

        <div class="card p-6 mt-4">

            <div class="flex items-center justify-between mb-3">

                <h3 class="text-[14px] font-semibold flex items-center gap-2">

                    <i class="fas fa-code-branch text-mistral-primary"></i>

                    {{ t('zones.assigned_branches') }}

                </h3>

                <Button variant="secondary" :href="route('zones.branches', zone.id)" icon="fas fa-cog">{{ t('zones.manage_branches') }}</Button>

            </div>

            <div v-if="branches.length === 0" class="text-center py-6 text-[13px] text-mistral-steel">

                {{ t('zones.no_branches') }}

            </div>

            <table v-else class="w-full text-right" dir="rtl">

                <thead class="text-[12px] text-mistral-steel border-b border-mistral-hairline-soft">

                    <tr>

                        <th class="py-2">{{ t('branches.branch_code') }}</th>

                        <th class="py-2">{{ t('branches.branch_name') }}</th>

                        <th class="py-2">{{ t('zones.city') }}</th>

                        <th class="py-2">{{ t('zones.priority') }}</th>

                        <th class="py-2 text-center">{{ t('zones.is_primary') }}</th>

                    </tr>

                </thead>

                <tbody>

                    <tr v-for="b in branches" :key="b.id" class="border-b border-mistral-hairline-soft hover:bg-mistral-surface">

                        <td class="py-2 font-mono text-[12px]">{{ b.branch_code }}</td>

                        <td class="py-2 font-medium">{{ b.branch_name }}</td>

                        <td class="py-2">{{ b.city || '���' }}</td>

                        <td class="py-2">{{ b.pivot_priority }}</td>

                        <td class="py-2 text-center">

                            <Badge v-if="b.pivot_is_primary" :text="t('zones.primary_branch')" variant="info" />

                            <span v-else class="text-mistral-stone">���</span>

                        </td>

                    </tr>

                </tbody>

            </table>

        </div>

        <ConfirmDialog

            v-model="showDelete"

            :title="t('zones.delete_confirm_title')"

            :message="t('zones.delete_confirm_message', { name: zone.name_ar })"

            :confirm-text="t('common.delete')"

            :cancel-text="t('common.cancel')"

            confirm-variant="danger"

            @confirm="performDelete"

        />

    </AppLayout>

</template>
