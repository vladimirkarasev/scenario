import {ref} from 'vue'

// Глобальный флаг «авторизация не подтверждена»: при true корневой компонент
// подменяет страницу на 403 (URI при этом не меняется).
export const authForbidden = ref(false)

export function markForbidden(): void {
    authForbidden.value = true
}
