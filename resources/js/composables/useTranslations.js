import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

/**
 * useTranslations — Composable للترجمة واتجاه RTL
 *
 * يوفر:
 *  - t(key)      : دالة الترجمة من $page.props.translations
 *  - locale      : اللغة الحالية ('ar' | 'en')
 *  - direction   : الاتجاه ('rtl' | 'ltr')
 *  - isRtl       : boolean مختصر
 *  - dirClass    : 'rtl' أو 'ltr' للاستخدام في class
 */
export function useTranslations() {
    const page = usePage();

    const locale = computed(() => page.props.locale || 'ar');
    const direction = computed(() => (locale.value === 'ar' ? 'rtl' : 'ltr'));
    const isRtl = computed(() => direction.value === 'rtl');

    const translations = computed(() => page.props.translations || {});

    /**
     * ترجمة مفتاح — t('companies.title') أو t('common.save')
     * @param {string} key
     * @param {object} params
     * @returns {string}
     */
    function t(key, params = {}) {
        const parts = key.split('.');
        let value = translations.value;

        for (const part of parts) {
            if (value && typeof value === 'object' && part in value) {
                value = value[part];
            } else {
                return key;
            }
        }

        if (typeof value !== 'string') {
            return key;
        }

        if (params && Object.keys(params).length > 0) {
            return value.replace(/:(\w+)/g, (match, param) => {
                return params[param] !== undefined ? String(params[param]) : match;
            });
        }

        return value;
    }

    return {
        t,
        locale,
        direction,
        isRtl,
        translations,
    };
}
