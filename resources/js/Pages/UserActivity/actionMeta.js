/**
 * Shared icon + colour metadata for every recorded activity action.
 * Used by the Index (top actions) and Show (timeline / breakdown) pages.
 */
export const ACTION_META = {
    view: { icon: 'fas fa-eye', color: 'bg-mistral-info/10 text-mistral-info' },
    create: { icon: 'fas fa-plus', color: 'bg-mistral-success/10 text-mistral-success' },
    open_create: { icon: 'fas fa-file-circle-plus', color: 'bg-cyan-50 text-cyan-600' },
    edit: { icon: 'fas fa-pen', color: 'bg-mistral-warning/10 text-mistral-warning' },
    open_edit: { icon: 'fas fa-file-pen', color: 'bg-amber-50 text-amber-600' },
    delete: { icon: 'fas fa-trash', color: 'bg-mistral-danger/10 text-mistral-danger' },
    cancel: { icon: 'fas fa-ban', color: 'bg-mistral-danger/10 text-mistral-danger' },
    sync: { icon: 'fas fa-arrows-rotate', color: 'bg-cyan-50 text-cyan-600' },
    adjust: { icon: 'fas fa-scale-balanced', color: 'bg-purple-50 text-purple-600' },
    set: { icon: 'fas fa-bullseye', color: 'bg-purple-50 text-purple-600' },
    grant: { icon: 'fas fa-gift', color: 'bg-purple-50 text-purple-600' },
    switch: { icon: 'fas fa-language', color: 'bg-mistral-info/10 text-mistral-info' },
    copy: { icon: 'fas fa-copy', color: 'bg-mistral-stone/10 text-mistral-stone' },
    publish: { icon: 'fas fa-paper-plane', color: 'bg-purple-50 text-purple-600' },
    regenerate: { icon: 'fas fa-rotate', color: 'bg-purple-50 text-purple-600' },
    assign: { icon: 'fas fa-people-arrows', color: 'bg-cyan-50 text-cyan-600' },
    unassign: { icon: 'fas fa-user-slash', color: 'bg-mistral-danger/10 text-mistral-danger' },
    transfer: { icon: 'fas fa-arrow-right-arrow-left', color: 'bg-cyan-50 text-cyan-600' },
    export: { icon: 'fas fa-download', color: 'bg-mistral-success/10 text-mistral-success' },
    approve: { icon: 'fas fa-check-double', color: 'bg-mistral-success/10 text-mistral-success' },
    reject: { icon: 'fas fa-xmark', color: 'bg-mistral-danger/10 text-mistral-danger' },
    login: { icon: 'fas fa-right-to-bracket', color: 'bg-mistral-info/10 text-mistral-info' },
    logout: { icon: 'fas fa-right-from-bracket', color: 'bg-mistral-stone/10 text-mistral-stone' },
    other: { icon: 'fas fa-circle-dot', color: 'bg-mistral-surface text-mistral-stone' },
};

export function actionMeta(action) {
    return ACTION_META[action] || ACTION_META.other;
}
