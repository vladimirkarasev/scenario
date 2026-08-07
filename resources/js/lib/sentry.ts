/**
 * Determines whether the Sentry SDK should be loaded for the current build.
 */
export function shouldInitializeSentry(dsn: string | undefined, isProduction: boolean): boolean {
    return isProduction && Boolean(dsn?.trim())
}
