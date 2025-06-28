<template>
    <div class="panel orders-chart-panel">
        <h3>Orders timeline</h3>
        <n-skeleton v-if="loading" text :repeat="2" />
        <div v-else>
            <Line :data="chartData" :options="chartOptions" />
        </div>
    </div>
</template>

<script setup>
import { Line } from "vue-chartjs";
import { computed } from "vue";
import {
    Chart as ChartJS,
    Title,
    Tooltip,
    Legend,
    LineElement,
    CategoryScale,
    LinearScale,
    PointElement,
    Filler, // 👈 this!
} from "chart.js";

ChartJS.register(Title, Tooltip, Legend, LineElement, CategoryScale, LinearScale, PointElement, Filler);

const props = defineProps({
    series: {
        type: Array,
        required: true,
    },
    loading: {
        type: Boolean,
        default: false,
    },
});

const chartData = computed(() => ({
    labels: props.series.map((entry) => entry.label),
    datasets: [
        {
            label: "Orders Count",
            data: props.series.map((entry) => entry.count),
            fill: true,
            tension: 0.4,
            pointRadius: 3,
            borderColor: "#42A5F5",
            backgroundColor: "rgba(66, 165, 245, 0.2)",
        },
    ],
}));

const chartOptions = {
    responsive: true,
    plugins: {
        legend: { position: "top" },
        title: { display: true, text: "Orders Throughout the period" },
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { stepSize: 20 },
        },
    },
};
</script>
