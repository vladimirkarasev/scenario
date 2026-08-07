import {getJson} from '@/lib/http'
import type {CentrifugoConnection} from '@/modules/realtime/types/centrifugo'

export const centrifugoRepository = {
    connection(): Promise<CentrifugoConnection> {
        return getJson<CentrifugoConnection>(
            '/api/centrifugo/connection-token',
            'Не удалось получить токен Centrifugo.',
        )
    },
}
