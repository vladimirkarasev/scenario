import {computed, onBeforeUnmount, ref, type Ref} from 'vue'

// Кнопки-стрелки горизонтальной прокрутки таблицы (появляются по hover).
// Держит метрики скролла и плавно прокручивает через requestAnimationFrame,
// пока курсор над стрелкой. Используется в DirectoryItemsTable и
// SurveyDirectoryTableField — обе таблицы шире своего контейнера.
export function useHorizontalScrollArrows(scrollRef: Ref<HTMLElement | null>) {
    const scrollLeft = ref(0)
    const clientWidth = ref(0)
    const scrollWidth = ref(0)
    const frame = ref<number | null>(null)

    const canScrollLeft = computed(() => scrollLeft.value > 1)
    const canScrollRight = computed(() => scrollLeft.value + clientWidth.value < scrollWidth.value - 1)

    function updateMetrics(): void {
        const el = scrollRef.value
        if (!el) return
        scrollLeft.value = el.scrollLeft
        clientWidth.value = el.clientWidth
        scrollWidth.value = el.scrollWidth
    }

    // Навешивается на @scroll контейнера: событие игнорируем, метрики берём из ref.
    function onScroll(): void {
        updateMetrics()
    }

    function scrollStep(direction: 'left' | 'right'): void {
        const el = scrollRef.value
        if (!el) return
        el.scrollLeft += direction === 'left' ? -18 : 18
        updateMetrics()
    }

    function startScroll(direction: 'left' | 'right'): void {
        stopScroll()
        const tick = (): void => {
            if (direction === 'left' && !canScrollLeft.value) return
            if (direction === 'right' && !canScrollRight.value) return
            scrollStep(direction)
            frame.value = window.requestAnimationFrame(tick)
        }
        frame.value = window.requestAnimationFrame(tick)
    }

    function stopScroll(): void {
        if (frame.value === null) return
        window.cancelAnimationFrame(frame.value)
        frame.value = null
    }

    onBeforeUnmount(stopScroll)

    return {canScrollLeft, canScrollRight, updateMetrics, onScroll, startScroll, stopScroll}
}
