<script setup>
import { computed } from 'vue';

const props = defineProps({
    rows: { type: Number, default: 5 },
    columns: { type: Number, default: 5 },
    selectable: { type: Boolean, default: false },
    density: { type: String, default: 'default' },
    dir: { type: String, default: 'rtl' },
});

const densityPad = computed(() => ({
    compact: 'py-2 px-3',
    default: 'py-3 px-4',
    comfortable: 'py-4 px-4',
}[props.density] || 'py-3 px-4'));

const cellWidths = computed(() => {
    const widths = [];
    for (let i = 0; i < props.columns; i++) {
        if (i === 0 && props.selectable) {
            widths.push('w-[40px]');
        } else if (i === props.columns - 1) {
            widths.push('w-[100px]');
        } else {
            const w = 60 + Math.floor(Math.random() * 80);
            widths.push(`w-[${w}%]`);
        }
    }
    return widths;
});
</script>

<template>
    <div :dir="dir">
        <div class="relative overflow-x-auto">
            <table class="w-full border-collapse text-start">
                <thead>
                    <tr class="bg-mistral-surface/60 border-b border-mistral-hairline-soft">
                        <th v-if="selectable" class="px-4 py-3 w-[40px]">
                            <div class="w-4 h-4 rounded bg-mistral-hairline-soft animate-pulse"></div>
                        </th>
                        <th
                            v-for="i in columns"
                            :key="i"
                            class="px-4 py-3"
                        >
                            <div class="h-3 rounded bg-mistral-hairline animate-pulse" :style="{ width: (40 + (i * 7) % 60) + '%' }"></div>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="row in rows"
                        :key="row"
                        :class="[
                            'border-b border-mistral-hairline-soft/60 last:border-0',
                            row % 2 === 1 ? 'bg-mistral-surface/30' : 'bg-white',
                        ]"
                    >
                        <td v-if="selectable" :class="[densityPad, 'w-[40px]']">
                            <div class="w-4 h-4 rounded bg-mistral-hairline-soft animate-pulse"></div>
                        </td>
                        <td
                            v-for="col in columns"
                            :key="col"
                            :class="densityPad"
                        >
                            <div
                                class="h-3 rounded bg-mistral-hairline-soft animate-pulse"
                                :style="{ width: (30 + ((row + col) * 11) % 50) + '%' }"
                            ></div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
