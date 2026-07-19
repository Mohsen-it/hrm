<script setup>
import { ref, onMounted, watch, onBeforeUnmount, shallowRef } from 'vue';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const props = defineProps({
    type: { type: String, required: true },
    data: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    height: { type: [String, Number], default: 260 },
});

const canvasRef = ref(null);
const chartInstance = shallowRef(null);

const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: {
            display: true,
            position: 'bottom',
            labels: {
                padding: 16,
                usePointStyle: true,
                pointStyleWidth: 8,
                font: {
                    family: "'Tajawal', 'Cairo', 'Inter', sans-serif",
                    size: 11,
                },
                color: '#525252',
            },
        },
        tooltip: {
            backgroundColor: '#171717',
            titleFont: { family: "'Tajawal', 'Inter', sans-serif", size: 12 },
            bodyFont: { family: "'Tajawal', 'Inter', sans-serif", size: 11 },
            padding: 10,
            cornerRadius: 8,
            displayColors: true,
            boxPadding: 4,
        },
    },
    scales: {},
};

function buildOptions() {
    const opts = JSON.parse(JSON.stringify(defaultOptions));

    if (props.type !== 'doughnut' && props.type !== 'pie' && props.type !== 'radar') {
        opts.scales = {
            x: {
                grid: { display: false },
                ticks: {
                    font: { family: "'Tajawal', 'Inter', sans-serif", size: 10 },
                    color: '#737373',
                },
                border: { display: false },
            },
            y: {
                grid: { color: '#ededed' },
                ticks: {
                    font: { family: "'Tajawal', 'Inter', sans-serif", size: 10 },
                    color: '#737373',
                },
                border: { display: false },
            },
        };
    }

    return deepMerge(opts, props.options);
}

function deepMerge(target, source) {
    const output = { ...target };
    for (const key of Object.keys(source)) {
        if (source[key] && typeof source[key] === 'object' && !Array.isArray(source[key])) {
            output[key] = deepMerge(output[key] || {}, source[key]);
        } else {
            output[key] = source[key];
        }
    }
    return output;
}

function createChart() {
    if (!canvasRef.value) return;
    if (chartInstance.value) {
        chartInstance.value.destroy();
    }
    chartInstance.value = new Chart(canvasRef.value, {
        type: props.type,
        data: props.data,
        options: buildOptions(),
    });
}

onMounted(() => {
    createChart();
});

watch(
    () => props.data,
    () => {
        if (chartInstance.value) {
            chartInstance.value.data = props.data;
            chartInstance.value.options = buildOptions();
            chartInstance.value.update();
        } else {
            createChart();
        }
    },
    { deep: true },
);

watch(
    () => props.type,
    () => {
        createChart();
    },
);

onBeforeUnmount(() => {
    if (chartInstance.value) {
        chartInstance.value.destroy();
        chartInstance.value = null;
    }
});
</script>

<template>
    <div :style="{ height: typeof height === 'number' ? height + 'px' : height }">
        <canvas ref="canvasRef"></canvas>
    </div>
</template>
