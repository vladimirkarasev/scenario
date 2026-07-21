import {ref} from 'vue'

export const authForbidden = ref(false)

export function markForbidden(): void {
    authForbidden.value = true
}
