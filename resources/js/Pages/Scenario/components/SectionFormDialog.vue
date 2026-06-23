<script setup lang="ts">
import {
  Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import {
  FormCheckbox, FormError, FormInput, FormTagSearch,
} from '@/components/form'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'
import { Pencil, FolderPlus, Plus, RefreshCw } from 'lucide-vue-next'
import type { useScenarioSectionModal } from '@/modules/scenario/composables/useScenarioSectionModal'
import type { useScenarioSectionTree } from '@/modules/scenario/composables/useScenarioSectionTree'

defineProps<{
    modal: ReturnType<typeof useScenarioSectionModal>
    tree: ReturnType<typeof useScenarioSectionTree>
    loadGroups: (q: string) => Promise<Array<Record<string, unknown>>>
}>()
</script>

<template>
    <Dialog v-model:open="modal.open.value">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-blue-50 text-blue-600">
                        <Pencil v-if="modal.isEditing.value" :size="14" />
                        <FolderPlus v-else :size="14" />
                    </div>
                    {{ modal.isEditing.value ? 'Редактировать раздел' : 'Новый раздел' }}
                </DialogTitle>
            </DialogHeader>

            <form class="space-y-4" novalidate @submit.prevent="modal.submit()">
                <FormError :message="modal.formError.value" />
                <FormInput
                    v-model="modal.form.name"
                    label="Название"
                    placeholder="Например: HR"
                    required
                    :error="modal.errors.name"
                    @keydown.enter="modal.submit()"
                />
                <div v-if="!modal.isEditing.value && tree.allSectionsFlat.value.length" class="space-y-1.5">
                    <Label>Родительский раздел</Label>
                    <Select
                        :model-value="modal.form.parent_id ?? '__root__'"
                        @update:model-value="(v: string) => modal.form.parent_id = v === '__root__' ? null : v"
                    >
                        <SelectTrigger class="w-full">
                            <SelectValue placeholder="Корневой раздел" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__root__">— Корневой раздел</SelectItem>
                            <SelectItem
                                v-for="s in tree.allSectionsFlat.value"
                                :key="s.id"
                                :value="s.id"
                            >
                                {{ '    '.repeat(s.depth) }}{{ s.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <FormTagSearch
                    v-model="modal.selectedGroups.value"
                    :loader="loadGroups"
                    value-key="id"
                    display-key="name"
                    label="Группы доступа"
                    placeholder="Поиск группы…"
                    hint="Группы, доступ которым к сценариям этого раздела"
                />

                <FormCheckbox
                    v-model="modal.form.inherit_to_descendants"
                    label="Применить ко всем вложенным разделам и сценариям"
                    description="Существующие группы у вложенных объектов будут заменены на эти. Без галочки — изменится только сам раздел."
                />

                <DialogFooter>
                    <Button type="button" variant="outline" @click="modal.close()">Отмена</Button>
                    <Button type="submit" :disabled="modal.submitting.value" class="gap-1.5">
                        <RefreshCw v-if="modal.submitting.value" :size="13" class="animate-spin" />
                        <Pencil v-else-if="modal.isEditing.value" :size="13" />
                        <Plus v-else :size="13" />
                        {{ modal.isEditing.value ? 'Сохранить' : 'Создать' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
