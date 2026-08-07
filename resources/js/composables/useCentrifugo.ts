import type {Subscription} from 'centrifuge';
import {Centrifuge} from 'centrifuge'
import {centrifugoRepository} from '@/modules/realtime/repositories/centrifugoRepository'

let client: Centrifuge | null = null
let clientPromise: Promise<Centrifuge> | null = null
let connectPromise: Promise<void> | null = null

async function getClient(): Promise<Centrifuge> {
    if (client) {
        return client
    }

    if (clientPromise) {
        return clientPromise
    }

    clientPromise = createClient()

    try {
        return await clientPromise
    } catch (error) {
        clientPromise = null
        throw error
    }
}

async function createClient(): Promise<Centrifuge> {
    const {token, ws_url} = await centrifugoRepository.connection()

    const nextClient = new Centrifuge(ws_url, {
        token,
        getToken: async () => {
            const data = await centrifugoRepository.connection()
            return data.token
        },
    })

    connectPromise = new Promise<void>((resolve, reject) => {
        nextClient.on('connected', () => resolve())
        nextClient.on('error', (ctx) => reject(new Error(ctx.error.message)))
    })

    nextClient.connect()

    await connectPromise
    client = nextClient

    return client
}

export async function subscribeTo<T = unknown>(
    channel: string,
    onMessage: (data: T) => void,
): Promise<Subscription> {
    const centrifuge = await getClient()

    let sub = centrifuge.getSubscription(channel)

    if (sub === null) {
        sub = centrifuge.newSubscription(channel)
        sub.subscribe()
    }

    sub.on('publication', (ctx) => onMessage(ctx.data as T))

    return sub
}

export async function unsubscribeFrom(sub: Subscription): Promise<void> {
    sub.unsubscribe()
    const centrifuge = await getClient()
    centrifuge.removeSubscription(sub)
}

export async function subscribeToPersonal<T = unknown>(
    userId: string,
    onMessage: (data: T) => void,
): Promise<Subscription> {
    const centrifuge = await getClient()

    const channel = `#user:${userId}`
    const sub = centrifuge.newSubscription(channel)

    sub.on('publication', (ctx) => onMessage(ctx.data as T))
    sub.subscribe()

    return sub
}
