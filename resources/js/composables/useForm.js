import { ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';

export function useForm(initialValues = {}) {
    const form = ref({ ...initialValues });
    const errors = ref({});
    const processing = ref(false);
    const recentlySuccessful = ref(false);

    function reset(...fields) {
        if (fields.length === 0) {
            form.value = { ...initialValues };
        } else {
            fields.forEach((field) => {
                if (field in initialValues) {
                    form.value[field] = initialValues[field];
                }
            });
        }
        errors.value = {};
    }

    async function submit(method, url, options = {}) {
        processing.value = true;
        errors.value = {};
        try {
            await router[method](url, form.value, {
                preserveScroll: true,
                ...options,
                onError: (errs) => {
                    errors.value = errs || {};
                    if (options.onError) options.onError(errs);
                },
                onSuccess: (...args) => {
                    recentlySuccessful.value = true;
                    setTimeout(() => (recentlySuccessful.value = false), 2000);
                    if (options.onSuccess) options.onSuccess(...args);
                },
                onFinish: () => {
                    processing.value = false;
                    if (options.onFinish) options.onFinish();
                },
            });
        } catch (e) {
            processing.value = false;
            throw e;
        }
    }

    return { form, errors, processing, recentlySuccessful, reset, submit };
}
