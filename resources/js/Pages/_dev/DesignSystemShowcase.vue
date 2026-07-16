<script setup>
import { ref, inject } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import FormInput from '@/Components/ui/FormInput.vue';
import FormTextarea from '@/Components/ui/FormTextarea.vue';
import FormSelect from '@/Components/ui/FormSelect.vue';
import FormDatepicker from '@/Components/ui/FormDatepicker.vue';
import FormCheckbox from '@/Components/ui/FormCheckbox.vue';
import FormRadio from '@/Components/ui/FormRadio.vue';
import FormSwitch from '@/Components/ui/FormSwitch.vue';
import FormGroup from '@/Components/ui/FormGroup.vue';
import Badge from '@/Components/ui/Badge.vue';
import Alert from '@/Components/ui/Alert.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import Breadcrumb from '@/Components/ui/Breadcrumb.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import DataTable from '@/Components/ui/DataTable.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import LoadingSpinner from '@/Components/ui/LoadingSpinner.vue';

const dir = inject('dir', ref('rtl'));
const form = ref({ name: '', email: '', role: '', bio: '', birthdate: '' });
const agreed = ref(false);
const permissions = ref([]);
const gender = ref('male');
const notifications = ref(true);
const activeTab = ref('overview');

const columns = [
    { key: 'name', label: 'الاسم' },
    { key: 'role', label: 'الدور' },
    { key: 'status', label: 'الحالة' },
];
const sampleData = {
    data: [
        { id: 1, name: 'أحمد محمد', role: 'مدير', status: 'active' },
        { id: 2, name: 'فاطمة علي', role: 'مطور', status: 'inactive' },
        { id: 3, name: 'خالد سعيد', role: 'محلل', status: 'active' },
    ],
    current_page: 1,
    last_page: 5,
    from: 1,
    to: 3,
    total: 15,
};
</script>

<template>
    <AppLayout title="نظام التصميم - Mistral">
        <div class="space-y-8 max-w-6xl">
            <header>
                <h1 class="text-[28px] font-semibold text-mistral-ink mb-2">نظام التصميم المُستوحى من Mistral AI</h1>
                <p class="text-[14px] text-mistral-steel">
                    صفحة عرض لجميع المكونات المشتركة. جميع الألوان والطباعة والمسافات تأتي من
                    <code class="bg-mistral-cream px-2 py-0.5 rounded text-mistral-primary">mistral.ai\DESIGN.md</code>.
                </p>
            </header>

            <Breadcrumb :items="[{ label: 'الرئيسية', href: '/' }, { label: 'الإعدادات' }, { label: 'نظام التصميم' }]" />

            <Tabs
                :tabs="[
                    { value: 'overview', label: 'نظرة عامة' },
                    { value: 'components', label: 'المكونات' },
                    { value: 'forms', label: 'النماذج' },
                    { value: 'feedback', label: 'التغذية الراجعة' },
                ]"
                v-model="activeTab"
                variant="pill"
            />

            <section v-if="activeTab === 'overview' || activeTab === 'components'" class="space-y-8">
                <Card variant="feature" padding="lg">
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-4">الأزرار</h2>
                    <div class="flex flex-wrap items-center gap-3">
                        <Button variant="primary" icon="fas fa-plus">إضافة جديد</Button>
                        <Button variant="secondary">إلغاء</Button>
                        <Button variant="cream">تعديل</Button>
                        <Button variant="dark">دخول</Button>
                        <Button variant="on-cream">تصدير</Button>
                        <Button variant="danger" icon="fas fa-trash">حذف</Button>
                        <Button variant="ghost">المزيد</Button>
                        <Button variant="link" href="#">رابط</Button>
                        <IconButton icon="fas fa-cog" aria-label="إعدادات" />
                        <IconButton icon="fas fa-bell" aria-label="إشعارات" variant="primary" />
                    </div>
                    <div class="flex flex-wrap items-center gap-3 mt-4">
                        <Button variant="primary" loading>جاري الحفظ</Button>
                        <Button variant="primary" disabled>معطّل</Button>
                    </div>
                </Card>

                <div>
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-4">البطاقات</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="card p-6">
                            <h3 class="text-[16px] font-semibold mb-2">بطاقة أساسية</h3>
                            <p class="text-[14px] text-mistral-steel">بطاقة محتوى قياسية بحدود ناعمة.</p>
                        </div>
                        <Card variant="cream" padding="md">
                            <h3 class="text-[16px] font-semibold mb-2">بطاقة كريمية</h3>
                            <p class="text-[14px] text-mistral-ink">تستخدم للوحات النماذج والميزات المميزة.</p>
                        </Card>
                        <Card variant="feature-product" padding="md">
                            <h3 class="text-[16px] font-semibold mb-2">بطاقة منتج</h3>
                            <p class="text-[14px] text-mistral-steel">مع رفع خفيف للظلال.</p>
                        </Card>
                    </div>
                </div>

                <Card variant="feature" padding="lg">
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-4">لوحة التحكم (Dashboard)</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <StatCard label="إجمالي المستخدمين" :value="1234" icon="fas fa-users" />
                        <StatCard label="نشط" :value="1150" trend="+5%" trend-direction="up" />
                        <StatCard label="في الانتظار" :value="84" icon="fas fa-clock" />
                        <StatCard label="غير نشط" :value="0" icon="fas fa-user-slash" />
                    </div>
                </Card>

                <Card variant="feature" padding="lg">
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-4">الجدول</h2>
                    <DataTable :columns="columns" :data="sampleData" :dir="dir">
                        <template #cell-name="{ row }">
                            <div class="flex items-center gap-2">
                                <Avatar :name="row.name" size="sm" />
                                <span>{{ row.name }}</span>
                            </div>
                        </template>
                        <template #cell-status="{ row }">
                            <Badge :text="row.status === 'active' ? 'نشط' : 'غير نشط'" :variant="row.status" />
                        </template>
                    </DataTable>
                    <div class="mt-4">
                        <Pagination :data="sampleData" />
                    </div>
                </Card>
            </section>

            <section v-if="activeTab === 'forms'" class="space-y-8">
                <Card variant="cream" padding="xl">
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-6">لوحة النموذج</h2>
                    <div class="space-y-5">
                        <FormGroup label="الاسم" required>
                            <FormInput v-model="form.name" placeholder="أدخل اسمك" />
                        </FormGroup>
                        <FormGroup label="البريد الإلكتروني" hint="لن نشارك بريدك مع أي طرف" required>
                            <FormInput v-model="form.email" type="email" placeholder="email@example.com" />
                        </FormGroup>
                        <FormGroup label="الدور">
                            <FormSelect
                                v-model="form.role"
                                :options="[
                                    { value: 'admin', label: 'مدير' },
                                    { value: 'user', label: 'مستخدم' },
                                    { value: 'guest', label: 'زائر' },
                                ]"
                            />
                        </FormGroup>
                        <FormGroup label="تاريخ الميلاد">
                            <FormDatepicker v-model="form.birthdate" />
                        </FormGroup>
                        <FormGroup label="نبذة عنك">
                            <FormTextarea v-model="form.bio" :rows="4" placeholder="اكتب نبذة قصيرة..." />
                        </FormGroup>
                        <FormGroup label="الجنس">
                            <div class="flex items-center gap-6">
                                <FormRadio v-model="gender" value="male" label="ذكر" />
                                <FormRadio v-model="gender" value="female" label="أنثى" />
                            </div>
                        </FormGroup>
                        <FormGroup label="الصلاحيات">
                            <div class="space-y-2">
                                <FormCheckbox v-model="permissions" value="read" label="قراءة" />
                                <FormCheckbox v-model="permissions" value="write" label="كتابة" />
                                <FormCheckbox v-model="permissions" value="delete" label="حذف" />
                            </div>
                        </FormGroup>
                        <FormGroup>
                            <FormSwitch v-model="notifications" label="تلقي الإشعارات" />
                        </FormGroup>
                        <div class="flex items-center gap-2 justify-end pt-4 border-t border-mistral-beige-deep">
                            <Button variant="secondary">إلغاء</Button>
                            <Button variant="primary" type="submit">حفظ</Button>
                        </div>
                    </div>
                </Card>
            </section>

            <section v-if="activeTab === 'feedback'" class="space-y-6">
                <Card variant="feature" padding="lg">
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-4">التنبيهات</h2>
                    <div class="space-y-3">
                        <Alert type="success" message="تم الحفظ بنجاح!" dismissible />
                        <Alert type="info" message="هذه رسالة معلوماتية." />
                        <Alert type="warning" message="يرجى التحقق من البيانات قبل المتابعة." />
                        <Alert type="danger" message="حدث خطأ أثناء الحفظ." />
                    </div>
                </Card>

                <Card variant="feature" padding="lg">
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-4">الشارات</h2>
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge text="نشط" variant="active" :dot="true" />
                        <Badge text="غير نشط" variant="inactive" />
                        <Badge text="في الانتظار" variant="pending" :dot="true" />
                        <Badge text="جديد" variant="orange" />
                        <Badge text="مميز" variant="cream" />
                        <Badge text="محذوف" variant="danger" />
                        <Badge text="معلومات" variant="info" />
                    </div>
                </Card>

                <Card variant="feature" padding="lg">
                    <h2 class="text-[20px] font-semibold text-mistral-ink mb-4">حالات فارغة والتحميل</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <EmptyState
                            icon="fas fa-inbox"
                            title="لا توجد بيانات"
                            description="لم يتم العثور على أي سجلات. أضف سجلاً جديداً للبدء."
                        />
                        <div class="flex flex-col items-center justify-center py-8 gap-3">
                            <LoadingSpinner size="lg" />
                            <p class="text-[14px] text-mistral-steel">جار التحميل...</p>
                        </div>
                    </div>
                </Card>
            </section>
        </div>
    </AppLayout>
</template>
