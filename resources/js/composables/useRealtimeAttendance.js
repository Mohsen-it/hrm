import { ref, onMounted, onUnmounted } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

export function useRealtimeAttendance(options = {}) {
    const {
        channel = 'attendance.live',
        event = 'punch.received',
        autoRefresh = false,
        onPunch = null,
        enabled = null,
    } = options

    const isConnected = ref(false)
    const lastPunch = ref(null)
    const punchCount = ref(0)

    let cleanup = null

    function isRealtimeEnabled() {
        if (enabled !== null && enabled !== undefined) {
            return Boolean(enabled)
        }

        try {
            const props = usePage().props
            if (props?.realtime && typeof props.realtime.enabled === 'boolean') {
                return props.realtime.enabled
            }
        } catch {
            // usePage() unavailable outside Inertia context — fall through.
        }

        return true
    }

    function connect() {
        // Skip private-channel auth entirely when the backend broadcaster
        // (log/null) cannot answer POST /broadcasting/auth with JSON. Without
        // this guard pusher-js logs: "JSON returned from channel-authorization
        // endpoint was invalid, yet status code was 200. Data was:".
        if (!isRealtimeEnabled()) {
            if (!connect.warned) {
                connect.warned = true
                console.info('Realtime broadcasting is disabled (BROADCAST_CONNECTION=log/null); skipping Echo subscription.')
            }
            return
        }

        if (typeof window === 'undefined' || !window.Echo) {
            if (!connect.warned) {
                connect.warned = true
                console.info('Laravel Echo is not initialized; realtime updates disabled.')
            }
            return
        }

        try {
            window.Echo.private(channel)
                .listen(`.${event}`, (data) => {
                    punchCount.value++
                    lastPunch.value = {
                        ...data,
                        received_at: new Date().toISOString(),
                    }

                    if (onPunch) {
                        onPunch(data)
                    }

                    if (autoRefresh) {
                        router.reload({
                            only: ['live', 'missing', 'anomalies', 'health'],
                            preserveState: true,
                            preserveScroll: true,
                        })
                    }
                })
                .subscribed(() => {
                    isConnected.value = true
                })
                .error((error) => {
                    isConnected.value = false
                    // Auth errors mean the backend refused/failed channel auth
                    // (e.g. missing Broadcast::channel definition or a `log`
                    // driver). Log a hint instead of a raw stack-trace dump.
                    if (error?.type === 'AuthError') {
                        console.warn(
                            'Realtime channel authorization failed for private channel. '
                            + 'Set BROADCAST_CONNECTION=reverb (and run the Reverb server) to enable live punches, '
                            + 'or leave it disabled to silence this warning.',
                            error,
                        )
                        return
                    }
                    console.error('Echo subscription error:', error)
                })

            cleanup = () => {
                window.Echo.leave(channel)
            }
        } catch (error) {
            console.error('Failed to connect to WebSocket:', error)
        }
    }

    function disconnect() {
        if (cleanup) {
            cleanup()
            cleanup = null
        }
        isConnected.value = false
    }

    onMounted(() => {
        connect()
    })

    onUnmounted(() => {
        disconnect()
    })

    return {
        isConnected,
        lastPunch,
        punchCount,
        disconnect,
    }
}
