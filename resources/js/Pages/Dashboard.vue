<script setup>
import { ref, computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    consumptions: {
        type: Array,
        default: () => []
    },
    prices: {
        type: Array,
        default: () => []
    }
});

// Reactive local arrays for instant update on live OMIE sync
const localConsumptions = ref([...props.consumptions]);
const localPrices = ref([...props.prices]);

// Calculator Form state (default to recent 2026 dataset)
const startDate = ref('2026-06-01');
const endDate = ref('2026-08-10');
const formula = ref('([OMIE_MD]*0.6)+0.88');

// Quick Date Range Presets for Calculator
const calcDatePresets = [
    { label: 'Todo el Periodo (Jun-Ago)', start: '2026-06-01', end: '2026-08-10' },
    { label: 'Mes de Junio', start: '2026-06-01', end: '2026-06-30' },
    { label: 'Mes de Julio', start: '2026-07-01', end: '2026-07-31' },
    { label: 'Mes de Agosto', start: '2026-08-01', end: '2026-08-10' },
    { label: 'Últimos 7 Días', start: '2026-08-03', end: '2026-08-10' }
];

const setCalcDatePreset = (start, end) => {
    startDate.value = start;
    endDate.value = end;
};

// Preset formulas for fast selection
const presetFormulas = [
    { label: 'Especificación Oficial', value: '([OMIE_MD]*0.6)+0.88' },
    { label: 'Margen + Fee Fijo', value: '([OMIE_MD] * 1.15) + 0.005' },
    { label: 'Indexado Directo + 10%', value: '[OMIE_MD] * 1.10' },
    { label: 'Peaje + Coeficiente', value: '([OMIE_MD] + 0.015) * 1.05' }
];

// Calculation result & status
const loading = ref(false);
const result = ref(null);
const errorData = ref(null);

// Real-time OMIE Sync State
const syncingOmie = ref(false);
const syncStatus = ref(null);

// Active Tab in Data Visualization: 'consumptions' | 'prices'
const activeTab = ref('consumptions');

// Visualization Date Range Filters
const filterStartDate = ref('');
const filterEndDate = ref('');

const clearFilters = () => {
    filterStartDate.value = '';
    filterEndDate.value = '';
};

// Helper function to auto-open native datepicker modal on click/focus
const openDatePicker = (event) => {
    if (event.target && typeof event.target.showPicker === 'function') {
        try {
            event.target.showPicker();
        } catch (e) {
            // Ignore browser focus restrictions
        }
    }
};

// Trigger Live OMIE Data Sync from ESIOS / OMIE
const handleSyncOmie = async () => {
    syncingOmie.value = true;
    syncStatus.value = null;

    try {
        const todayStr = new Date().toISOString().split('T')[0];
        const response = await axios.post('/api/omie/sync', {
            date: todayStr
        });

        syncStatus.value = {
            type: 'success',
            message: response.data.message,
            source: response.data.source
        };

        if (response.data.prices) {
            localPrices.value = response.data.prices;
        }
        if (response.data.consumptions) {
            localConsumptions.value = response.data.consumptions;
        }

        // Hide success alert after 5 seconds
        setTimeout(() => {
            if (syncStatus.value && syncStatus.value.type === 'success') {
                syncStatus.value = null;
            }
        }, 5000);

    } catch (err) {
        syncStatus.value = {
            type: 'error',
            message: err.response?.data?.error || 'Error al conectar con la API para obtener datos de OMIE.'
        };
    } finally {
        syncingOmie.value = false;
    }
};

// Execute POST /calculate
const handleCalculate = async () => {
    loading.value = true;
    result.value = null;
    errorData.value = null;

    try {
        const response = await axios.post('/calculate', {
            start_date: startDate.value,
            end_date: endDate.value,
            formula: formula.value
        });

        result.value = response.data;
    } catch (err) {
        if (err.response) {
            errorData.value = {
                status: err.response.status,
                message: err.response.data.error || 'Ocurrió un error en el servidor.',
                statusText: getStatusText(err.response.status)
            };
        } else {
            errorData.value = {
                status: 500,
                message: 'No se pudo establecer conexión con el servidor backend.',
                statusText: 'Error de Red / Servidor'
            };
        }
    } finally {
        loading.value = false;
    }
};

const getStatusText = (status) => {
    switch (status) {
        case 400: return '400 Bad Request - Petición Inválida';
        case 404: return '404 Not Found - Registros No Encontrados';
        case 500: return '500 Internal Server Error - Error de Servidor';
        default: return `${status} Error`;
    }
};

const setPreset = (presetVal) => {
    formula.value = presetVal;
};

const insertTag = () => {
    if (!formula.value.includes('[OMIE_MD]')) {
        formula.value += '[OMIE_MD]';
    }
};

// Data filtering for visualization tables
const filteredConsumptions = computed(() => {
    return localConsumptions.value.filter(c => {
        const dateOnly = c.date ? c.date.substring(0, 10) : '';
        if (filterStartDate.value && dateOnly < filterStartDate.value) return false;
        if (filterEndDate.value && dateOnly > filterEndDate.value) return false;
        return true;
    });
});

const filteredPrices = computed(() => {
    return localPrices.value.filter(p => {
        const dateOnly = p.date ? p.date.substring(0, 10) : '';
        if (filterStartDate.value && dateOnly < filterStartDate.value) return false;
        if (filterEndDate.value && dateOnly > filterEndDate.value) return false;
        return true;
    });
});

// Calculate row total consumption
const getRowTotalConsumption = (row) => {
    let sum = 0;
    for (let i = 1; i <= 25; i++) {
        const val = row[`h${i}`];
        if (val !== null && val !== undefined) {
            sum += parseFloat(val);
        }
    }
    return sum.toFixed(2);
};

// Calculate row average price
const getRowAveragePrice = (row) => {
    let sum = 0;
    let count = 0;
    for (let i = 1; i <= 25; i++) {
        const val = row[`h${i}`];
        if (val !== null && val !== undefined) {
            sum += parseFloat(val);
            count++;
        }
    }
    return count > 0 ? (sum / count).toFixed(4) : '0.0000';
};
</script>

<template>
    <Head title="Gestión de Precios Indexados de Energía" />

    <div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased selection:bg-emerald-500 selection:text-white pb-16">
        
        <!-- Top Navigation / Header -->
        <header class="border-b border-slate-800 bg-slate-900/80 backdrop-blur-md sticky top-0 z-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-emerald-500 via-teal-500 to-cyan-500 flex items-center justify-center shadow-lg shadow-emerald-500/20">
                        <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-white flex items-center gap-2">
                            NEMON <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Energía Indexada</span>
                        </h1>
                        <p class="text-xs text-slate-400">Sistema de Gestión & Cálculo de Precios OMIE_MD</p>
                    </div>
                </div>

                <div class="flex items-center space-x-3 text-xs">
                    <button 
                        @click="handleSyncOmie"
                        :disabled="syncingOmie"
                        class="px-3.5 py-2 rounded-xl font-medium text-white bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 border border-teal-400/30 shadow-md shadow-teal-500/20 disabled:opacity-50 transition-all flex items-center gap-2"
                    >
                        <svg v-if="syncingOmie" class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else class="w-3.5 h-3.5 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        <span>{{ syncingOmie ? 'Sincronizando OMIE...' : '⚡ Sincronizar OMIE en Vivo' }}</span>
                    </button>

                    <span class="hidden sm:flex px-3 py-1.5 rounded-lg bg-slate-800 border border-slate-700 text-slate-300 items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        BD: <strong class="text-emerald-400 font-mono">nemondb</strong>
                    </span>
                </div>
            </div>
        </header>

        <!-- Live Sync Toast Notification -->
        <div v-if="syncStatus" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4">
            <div 
                :class="syncStatus.type === 'success' ? 'bg-emerald-950/60 border-emerald-500/40 text-emerald-200' : 'bg-rose-950/60 border-rose-500/40 text-rose-200'"
                class="p-4 rounded-xl border flex items-center justify-between text-xs shadow-lg transition-all"
            >
                <div class="flex items-center space-x-2">
                    <span class="w-2 h-2 rounded-full" :class="syncStatus.type === 'success' ? 'bg-emerald-400' : 'bg-rose-400'"></span>
                    <span>{{ syncStatus.message }}</span>
                    <span v-if="syncStatus.source" class="font-mono text-[11px] px-2 py-0.5 rounded bg-slate-900 border border-slate-800 text-teal-300">
                        {{ syncStatus.source }}
                    </span>
                </div>
                <button @click="syncStatus = null" class="text-slate-400 hover:text-white">&times;</button>
            </div>
        </div>

        <!-- Main Content Area -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-8 space-y-8">
            
            <!-- SECTION 1: CALCULADORA DE PRECIO INDEXADO -->
            <section class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Calculator Form Card (7 Cols) -->
                <div class="lg:col-span-7 bg-slate-900/90 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl shadow-slate-950/50 backdrop-blur-sm">
                    <div class="flex items-center justify-between pb-6 border-b border-slate-800 mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-lg bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-white">Calculadora de Precio Indexado</h2>
                                <p class="text-xs text-slate-400">Configure el rango de fechas y la fórmula de cálculo</p>
                            </div>
                        </div>
                    </div>

                    <form @submit.prevent="handleCalculate" class="space-y-6">
                        
                        <!-- Quick Date Range Selector -->
                        <div>
                            <span class="block text-[11px] font-medium text-slate-400 mb-2">Selección Rápida de Periodo:</span>
                            <div class="flex flex-wrap gap-2">
                                <button 
                                    v-for="(preset, idx) in calcDatePresets" 
                                    :key="idx"
                                    type="button"
                                    @click="setCalcDatePreset(preset.start, preset.end)"
                                    :class="startDate === preset.start && endDate === preset.end ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50 font-semibold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:border-slate-700 hover:text-slate-200'"
                                    class="text-xs px-3 py-1.5 rounded-lg border transition-all duration-150"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Date Range Inputs -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">Fecha Inicio (start_date)</label>
                                <div class="relative">
                                    <input 
                                        v-model="startDate"
                                        type="date" 
                                        required
                                        @click="openDatePicker($event)"
                                        @focus="openDatePicker($event)"
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 font-mono cursor-pointer custom-date-input"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1.5">Fecha Fin (end_date)</label>
                                <div class="relative">
                                    <input 
                                        v-model="endDate"
                                        type="date" 
                                        required
                                        @click="openDatePicker($event)"
                                        @focus="openDatePicker($event)"
                                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 font-mono cursor-pointer custom-date-input"
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Formula Input -->
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="block text-xs font-medium text-slate-300">Fórmula de Precio (formula)</label>
                                <button 
                                    type="button" 
                                    @click="insertTag"
                                    class="text-[11px] font-mono px-2 py-0.5 rounded bg-teal-500/10 text-teal-300 border border-teal-500/30 hover:bg-teal-500/20 transition-colors"
                                >
                                    + Insertar [OMIE_MD]
                                </button>
                            </div>
                            <input 
                                v-model="formula"
                                type="text" 
                                placeholder="([OMIE_MD]*0.6)+0.88"
                                required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-emerald-400 font-mono tracking-wide focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500"
                            />
                            <p class="mt-1.5 text-[11px] text-slate-500 flex items-center gap-1">
                                <svg class="w-3.5 h-3.5 text-slate-400 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                La fórmula debe contener obligatoriamente el parámetro <code class="text-teal-300 bg-slate-800 px-1 py-0.5 rounded">[OMIE_MD]</code>.
                            </p>
                        </div>

                        <!-- Formula Presets -->
                        <div>
                            <span class="block text-[11px] font-medium text-slate-400 mb-2">Plantillas rápidas de fórmula:</span>
                            <div class="flex flex-wrap gap-2">
                                <button 
                                    v-for="(preset, idx) in presetFormulas" 
                                    :key="idx"
                                    type="button"
                                    @click="setPreset(preset.value)"
                                    :class="formula === preset.value ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50 font-semibold' : 'bg-slate-950 text-slate-400 border-slate-800 hover:border-slate-700 hover:text-slate-200'"
                                    class="text-xs px-3 py-1.5 rounded-lg border transition-all duration-150"
                                >
                                    {{ preset.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit" 
                            :disabled="loading"
                            class="w-full py-3.5 px-6 rounded-xl font-semibold text-white bg-gradient-to-r from-emerald-500 via-teal-500 to-cyan-600 hover:from-emerald-400 hover:to-cyan-500 focus:outline-none focus:ring-2 focus:ring-emerald-500/50 shadow-lg shadow-emerald-500/25 disabled:opacity-50 disabled:cursor-not-allowed transition-all duration-200 flex items-center justify-center space-x-2"
                        >
                            <svg v-if="loading" class="animate-spin -ml-1 mr-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span>{{ loading ? 'Calculando Precio Indexado...' : 'Calcular Precio Indexado' }}</span>
                        </button>
                    </form>
                </div>

                <!-- Result & Error Banner Display Card (5 Cols) -->
                <div class="lg:col-span-5 space-y-6">
                    
                    <!-- Result Card -->
                    <div 
                        v-if="result" 
                        class="bg-gradient-to-br from-slate-900 via-slate-900 to-emerald-950/40 border border-emerald-500/40 rounded-2xl p-6 sm:p-8 shadow-2xl shadow-emerald-950/30 relative overflow-hidden transition-all duration-300"
                    >
                        <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

                        <div class="flex items-center justify-between mb-4">
                            <span class="text-xs font-semibold px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                Cálculo Completado (200 OK)
                            </span>
                            <span class="text-xs text-slate-400 font-mono">{{ result.summary?.total_dias }} días procesados</span>
                        </div>

                        <h3 class="text-xs uppercase tracking-wider text-slate-400 font-semibold mb-1">Precio Indexado Final</h3>
                        <div class="flex items-baseline space-x-2 my-3">
                            <span class="text-4xl sm:text-5xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 via-teal-300 to-cyan-300 font-mono tracking-tight">
                                {{ result.price_indexed?.toFixed(6) }}
                            </span>
                            <span class="text-lg font-bold text-emerald-400">€/kWh</span>
                        </div>

                        <!-- Summary breakdown -->
                        <div class="mt-6 pt-6 border-t border-slate-800/80 grid grid-cols-2 gap-4">
                            <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                                <span class="block text-[11px] text-slate-400">Suma Importes</span>
                                <span class="text-sm font-semibold text-slate-200 font-mono">{{ result.summary?.suma_importes }} €</span>
                            </div>
                            <div class="bg-slate-950/60 p-3 rounded-xl border border-slate-800">
                                <span class="block text-[11px] text-slate-400">Suma Consumos</span>
                                <span class="text-sm font-semibold text-slate-200 font-mono">{{ result.summary?.suma_consumos }} kWh</span>
                            </div>
                        </div>
                    </div>

                    <!-- Error Alert Card -->
                    <div 
                        v-if="errorData" 
                        :class="[
                            errorData.status === 400 ? 'bg-amber-950/30 border-amber-500/40 text-amber-200' : '',
                            errorData.status === 404 ? 'bg-blue-950/30 border-blue-500/40 text-blue-200' : '',
                            errorData.status === 500 ? 'bg-rose-950/30 border-rose-500/40 text-rose-200' : ''
                        ]"
                        class="border rounded-2xl p-6 shadow-xl transition-all duration-300"
                    >
                        <div class="flex items-start space-x-4">
                            <div 
                                :class="[
                                    errorData.status === 400 ? 'bg-amber-500/20 text-amber-400 border-amber-500/30' : '',
                                    errorData.status === 404 ? 'bg-blue-500/20 text-blue-400 border-blue-500/30' : '',
                                    errorData.status === 500 ? 'bg-rose-500/20 text-rose-400 border-rose-500/30' : ''
                                ]"
                                class="w-10 h-10 rounded-xl border flex-shrink-0 flex items-center justify-center font-bold font-mono text-sm"
                            >
                                {{ errorData.status }}
                            </div>
                            <div>
                                <h4 class="text-sm font-bold tracking-wide mb-1">
                                    {{ errorData.statusText }}
                                </h4>
                                <p class="text-xs leading-relaxed opacity-90">
                                    {{ errorData.message }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Placeholder state when no calculation performed yet -->
                    <div 
                        v-if="!result && !errorData" 
                        class="bg-slate-900/40 border border-slate-800/80 rounded-2xl p-8 text-center space-y-3"
                    >
                        <div class="w-12 h-12 rounded-full bg-slate-800/80 mx-auto flex items-center justify-center text-slate-500">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                            </svg>
                        </div>
                        <h4 class="text-sm font-medium text-slate-300">Listo para Calcular</h4>
                        <p class="text-xs text-slate-500 max-w-xs mx-auto">
                            Pulse "Calcular Precio Indexado" para evaluar la fórmula matemática sobre los consumos y precios diarios.
                        </p>
                    </div>

                </div>

            </section>

            <!-- SECTION 2: VISUALIZACIÓN DE DATOS (CONSUMOS & PRECIOS) -->
            <section class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                
                <!-- Header & Controls -->
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-slate-800 pb-6">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Visualización de Datos Registrados</h3>
                        <p class="text-xs text-slate-400">Consumos (kWh) y Precios de Mercado Diario (OMIE_MD) almacenados en MySQL</p>
                    </div>

                    <!-- Tabs Switcher -->
                    <div class="bg-slate-950 p-1 rounded-xl border border-slate-800 flex space-x-1 self-start lg:self-auto">
                        <button 
                            @click="activeTab = 'consumptions'"
                            :class="activeTab === 'consumptions' ? 'bg-emerald-500 text-white font-medium shadow-lg shadow-emerald-500/20' : 'text-slate-400 hover:text-slate-200'"
                            class="px-4 py-1.5 rounded-lg text-xs transition-all duration-150"
                        >
                            Consumos (kWh)
                        </button>
                        <button 
                            @click="activeTab = 'prices'"
                            :class="activeTab === 'prices' ? 'bg-emerald-500 text-white font-medium shadow-lg shadow-emerald-500/20' : 'text-slate-400 hover:text-slate-200'"
                            class="px-4 py-1.5 rounded-lg text-xs transition-all duration-150"
                        >
                            Precios OMIE (€/kWh)
                        </button>
                    </div>
                </div>

                <!-- Date Range Filters Toolbar -->
                <div class="bg-slate-950/80 border border-slate-800 p-4 rounded-xl flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto">
                        <span class="text-xs font-semibold text-slate-300 flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Filtrar por Rango de Fechas:
                        </span>

                        <div class="flex items-center space-x-2">
                            <label class="text-[11px] text-slate-400">Desde:</label>
                            <input 
                                v-model="filterStartDate"
                                type="date" 
                                @click="openDatePicker($event)"
                                @focus="openDatePicker($event)"
                                class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-emerald-500 font-mono cursor-pointer custom-date-input"
                            />
                        </div>

                        <div class="flex items-center space-x-2">
                            <label class="text-[11px] text-slate-400">Hasta:</label>
                            <input 
                                v-model="filterEndDate"
                                type="date" 
                                @click="openDatePicker($event)"
                                @focus="openDatePicker($event)"
                                class="bg-slate-900 border border-slate-800 rounded-lg px-3 py-1.5 text-xs text-slate-200 focus:outline-none focus:ring-1 focus:ring-emerald-500 font-mono cursor-pointer custom-date-input"
                            />
                        </div>

                        <button 
                            v-if="filterStartDate || filterEndDate"
                            @click="clearFilters"
                            type="button"
                            class="text-xs px-3 py-1.5 rounded-lg bg-rose-500/10 text-rose-300 border border-rose-500/30 hover:bg-rose-500/20 transition-colors"
                        >
                            Limpiar Filtro
                        </button>
                    </div>

                    <div class="text-xs text-slate-400 font-mono">
                        Mostrando <strong class="text-emerald-400">{{ activeTab === 'consumptions' ? filteredConsumptions.length : filteredPrices.length }}</strong> registros
                    </div>
                </div>

                <!-- Consumptions Table (With Max-Height Vertical Scroll & Sticky Header) -->
                <div v-if="activeTab === 'consumptions'" class="max-h-[500px] overflow-y-auto overflow-x-auto border border-slate-800/80 rounded-xl custom-scrollbar">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="sticky top-0 bg-slate-950 shadow-md z-20">
                            <tr class="text-slate-400 border-b border-slate-800 font-mono">
                                <th class="py-3.5 px-4 sticky left-0 bg-slate-950 z-30">Fecha</th>
                                <th class="py-3.5 px-3 font-semibold text-emerald-400 bg-slate-950">Total Día</th>
                                <th v-for="h in 24" :key="h" class="py-3.5 px-2 text-center bg-slate-950">H{{ h }}</th>
                                <th class="py-3.5 px-2 text-center text-slate-500 bg-slate-950">H25</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <tr 
                                v-for="row in filteredConsumptions" 
                                :key="row.id"
                                class="hover:bg-slate-800/40 transition-colors"
                            >
                                <td class="py-3 px-4 font-mono font-medium text-slate-200 sticky left-0 bg-slate-900 z-10 border-r border-slate-800">
                                    {{ row.date ? row.date.substring(0, 10) : '' }}
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-emerald-400 border-r border-slate-800/60 bg-slate-900/40">
                                    {{ getRowTotalConsumption(row) }} kWh
                                </td>
                                <td 
                                    v-for="h in 24" 
                                    :key="h"
                                    class="py-3 px-2 text-center font-mono text-slate-300"
                                >
                                    {{ row[`h${h}`] !== null ? row[`h${h}`] : '-' }}
                                </td>
                                <td class="py-3 px-2 text-center font-mono text-slate-500">
                                    {{ row.h25 !== null ? row.h25 : '-' }}
                                </td>
                            </tr>
                            <tr v-if="filteredConsumptions.length === 0">
                                <td colspan="27" class="py-12 text-center text-slate-500">
                                    No hay registros de consumos para el rango de fechas seleccionado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Prices Table (With Max-Height Vertical Scroll & Sticky Header) -->
                <div v-if="activeTab === 'prices'" class="max-h-[500px] overflow-y-auto overflow-x-auto border border-slate-800/80 rounded-xl custom-scrollbar">
                    <table class="w-full text-left text-xs border-collapse">
                        <thead class="sticky top-0 bg-slate-950 shadow-md z-20">
                            <tr class="text-slate-400 border-b border-slate-800 font-mono">
                                <th class="py-3.5 px-4 sticky left-0 bg-slate-950 z-30">Fecha</th>
                                <th class="py-3.5 px-3 font-semibold text-teal-400 bg-slate-950">Media Día</th>
                                <th v-for="h in 24" :key="h" class="py-3.5 px-2 text-center bg-slate-950">H{{ h }}</th>
                                <th class="py-3.5 px-2 text-center text-slate-500 bg-slate-950">H25</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/50">
                            <tr 
                                v-for="row in filteredPrices" 
                                :key="row.id"
                                class="hover:bg-slate-800/40 transition-colors"
                            >
                                <td class="py-3 px-4 font-mono font-medium text-slate-200 sticky left-0 bg-slate-900 z-10 border-r border-slate-800">
                                    {{ row.date ? row.date.substring(0, 10) : '' }}
                                </td>
                                <td class="py-3 px-3 font-mono font-bold text-teal-400 border-r border-slate-800/60 bg-slate-900/40">
                                    {{ getRowAveragePrice(row) }} €
                                </td>
                                <td 
                                    v-for="h in 24" 
                                    :key="h"
                                    class="py-3 px-2 text-center font-mono text-slate-300"
                                >
                                    {{ row[`h${h}`] !== null ? row[`h${h}`] : '-' }}
                                </td>
                                <td class="py-3 px-2 text-center font-mono text-slate-500">
                                    {{ row.h25 !== null ? row.h25 : '-' }}
                                </td>
                            </tr>
                            <tr v-if="filteredPrices.length === 0">
                                <td colspan="27" class="py-12 text-center text-slate-500">
                                    No hay registros de precios para el rango de fechas seleccionado.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </section>

        </main>
    </div>
</template>

<style scoped>
/* Force dark color scheme for native browser datepickers */
.custom-date-input {
    color-scheme: dark;
}

/* Custom scrollbar styling for vertical table scrolling */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: #090d16;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: #1e293b;
    border-radius: 9999px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: #10b981;
}
</style>
