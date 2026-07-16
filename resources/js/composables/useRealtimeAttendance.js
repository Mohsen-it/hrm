import { ref, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'

export function useRealtimeAttendance(options = {}) {
    const {
        channel = 'attendance.live',
        event = 'punch.received',
        autoRefresh = true,
        onPunch = null,
    } = options

    const isConnected = ref(false)
    const lastPunch = ref(null)
    const punchCount = ref(0)

    let cleanup = null

    function connect() {
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
                    console.error('Echo subscription error:', error)
                    isConnected.value = false
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
