export interface DirectorySyncSchedule {
    enabled: boolean
    cron: string | null
    timezone: string
    last_run_at: string | null
    next_run_at: string | null
}

export interface DirectorySyncSchedulePayload {
    enabled: boolean
    cron: string | null
    timezone?: string | null
}
