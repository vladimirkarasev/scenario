<script setup lang="ts">
import AppShell from '@/layouts/AppShell.vue'
import ScenarioPlayer from '@/modules/scenario/components/player/ScenarioPlayer.vue'
import { useDashboardNavigation } from '@/composables/useDashboardNavigation'
import { Head } from '@inertiajs/vue3'

defineProps<{
    scenarioId?: string | null
    scenarioVersionId?: string | null
    runId?: string | null
}>()

const { navigationItems } = useDashboardNavigation()
</script>

<template>
    <Head title="Сценарий" />

    <AppShell
        title="Сценарий"
        description=""
        :navigation-items="navigationItems"
    >
        <div class="survey-content h-full overflow-auto bg-[#F7F8FA]">
            <div class="mx-auto w-full max-w-2xl px-4 py-8 md:py-12">
                <ScenarioPlayer
                    :run-id="runId ?? null"
                    :scenario-id="scenarioId ?? null"
                    :scenario-version-id="scenarioVersionId ?? null"
                />
            </div>
        </div>
    </AppShell>
</template>

<style scoped>
.survey-content {
    background-image: radial-gradient(ellipse 120% 80% at 50% -10%, rgba(59, 130, 246, 0.05) 0%, transparent 60%);
}
</style>
