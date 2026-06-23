export function redirectToPartner(): boolean {
    const url = import.meta.env.VITE_IFRAME_AUTH_REDIRECT_URL
    if (url) {
        window.location.href = url
        return true
    }
    return false
}
