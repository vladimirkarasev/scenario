import { onMounted, onUnmounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import type { Subscription } from 'centrifuge'
import { subscribeToPersonal } from '@/composables/useCentrifugo'

export interface StartScenarioMessage {
  type: 'start_scenario'
  run_id: string
  url: string
}

/**
 * Подписывается на личный канал текущего пользователя и вызывает onStart
 * при получении сообщения type=start_scenario. Используется на страницах
 * workspace/scenarios и workspace, чтобы внешние системы могли запустить
 * опрос у пользователя через POST /api/scenarios/dispatch.
 */
export function useStartScenarioListener(
  onStart: (message: StartScenarioMessage) => void,
): void {
  const page = usePage()
  let sub: Subscription | null = null

  onMounted(() => {
    const userId = String((page.props.auth as { user?: { id?: unknown } })?.user?.id ?? '')
    if (!userId) return

    subscribeToPersonal<StartScenarioMessage>(userId, (data) => {
      if (data?.type === 'start_scenario') onStart(data)
    })
      .then((s) => { sub = s })
      .catch(() => {})
  })

  onUnmounted(() => {
    sub?.unsubscribe()
  })
}
