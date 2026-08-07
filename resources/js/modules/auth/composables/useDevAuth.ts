import {computed, watch} from 'vue'
import {useZodForm} from '@/composables/useZodForm'
import {devAuthRepository} from '@/modules/auth/repositories/devAuthRepository'
import {devAuthSchema} from '@/modules/auth/schemas/devAuthSchema'
import type {DevAuthPageProps} from '@/modules/auth/types/devAuth'

export function useDevAuth(props: DevAuthPageProps) {
    const defaultProject = props.projects.find(project => project.id === props.defaultProjectId)
        ?? props.projects[0]
        ?? null
    const defaultUser = defaultProject?.users.find(user => user.id === props.defaultUserId)
        ?? defaultProject?.users[0]
        ?? null

    const {
        formData,
        errors,
        formError,
        submitting,
        submit,
        reset,
        clearError,
    } = useZodForm(devAuthSchema, {
        projectId: defaultProject?.id ?? '',
        userId: defaultUser === null ? '' : String(defaultUser.id),
    })

    const selectedProject = computed(() => (
        props.projects.find(project => project.id === formData.projectId) ?? null
    ))
    const availableUsers = computed(() => selectedProject.value?.users ?? [])
    const selectedUser = computed(() => (
        availableUsers.value.find(user => String(user.id) === formData.userId) ?? null
    ))

    watch(() => formData.projectId, () => {
        const currentUserIsAvailable = availableUsers.value.some(
            user => String(user.id) === formData.userId,
        )

        if (!currentUserIsAvailable) {
            formData.userId = availableUsers.value[0] === undefined
                ? ''
                : String(availableUsers.value[0].id)
        }
        clearError('projectId')
        clearError('userId')
    })

    async function authorize(): Promise<void> {
        try {
            await submit(async values => {
                const result = await devAuthRepository.impersonate(values)
                window.location.assign(`/scenarios?_token=${encodeURIComponent(result.token)}`)
            })
        } catch {
        }
    }

    function resetSelection(): void {
        reset({
            projectId: defaultProject?.id ?? '',
            userId: defaultUser === null ? '' : String(defaultUser.id),
        })
    }

    return {
        formData,
        errors,
        formError,
        submitting,
        selectedProject,
        availableUsers,
        selectedUser,
        authorize,
        resetSelection,
    }
}
