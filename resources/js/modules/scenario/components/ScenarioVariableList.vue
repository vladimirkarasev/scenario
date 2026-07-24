<script setup lang="ts">
import {ref, reactive, computed} from 'vue'
import {Check, Copy, Sparkles} from 'lucide-vue-next'
import {Popover, PopoverContent, PopoverTrigger} from '@/components/ui/popover'
import DateVariableHints from './variable-hints/DateVariableHints.vue'
import SelectVariableHints from './variable-hints/SelectVariableHints.vue'
import DirectoryVariableHints from './variable-hints/DirectoryVariableHints.vue'
import PhoneVariableHints from './variable-hints/PhoneVariableHints.vue'
import SuggestVariableHints from './variable-hints/SuggestVariableHints.vue'
import SystemVariableHints from './variable-hints/SystemVariableHints.vue'
import {directoryRepository} from '@/modules/directories/repositories/directoryRepository'
import {webhookRepository} from '@/modules/proxy/repositories/webhookRepository'
import type {DirectorySchemaField} from '@/modules/directories/types/directory'
import type {WebhookField} from '@/modules/proxy/types/webhook'
import {
  type VarLike, type SystemVariableGroup, SYSTEM_VARIABLE_GROUPS,
  systemGroupRef, hasHints, isDateVar, isSelectVar, isPhoneVar, isSuggestVar, extractVarName,
} from '@/modules/scenario/lib/scenario-variable-hints'

interface BlockEntry {
  id: string
  type: string
  data: { title?: string }
}

interface UserVariable {
  id: string
  name: string
  label: string
}

const props = withDefaults(defineProps<{
  variables?: VarLike[]
  blocks?: BlockEntry[]
  userVariables?: UserVariable[]
  currentBlockId?: string
  hoverClass?: string
}>(), {
  variables: () => [],
  blocks: () => [],
  userVariables: () => [],
  currentBlockId: '',
  hoverClass: 'hover:bg-slate-50',
})

const blocksWithVars = computed(() => {
  const varsByBlock = new Map<string, VarLike[]>()
  for (const v of props.variables) {
    if (!varsByBlock.has(v.blockId)) varsByBlock.set(v.blockId, [])
    varsByBlock.get(v.blockId)!.push(v)
  }
  return props.blocks
      .filter((b) => b.type === 'block' && varsByBlock.has(b.id))
      .map((b) => ({block: b, vars: varsByBlock.get(b.id)!}))
})

const copiedId = ref<string | null>(null)

async function copy(text: string, id: string): Promise<void> {
  await navigator.clipboard.writeText(text)
  copiedId.value = id
  setTimeout(() => {
    copiedId.value = null
  }, 1500)
}

const schemaCache = reactive<Record<string, DirectorySchemaField[]>>({})
const schemaLoading = reactive<Record<string, boolean>>({})

function cacheKey(directoryId: string, versionId: string): string {
  return `${directoryId}::${versionId || 'active'}`
}

async function loadSchema(directoryId: string, versionId: string): Promise<void> {
  const key = cacheKey(directoryId, versionId)
  if (schemaCache[key] || schemaLoading[key]) return
  schemaLoading[key] = true
  try {
    const res = await directoryRepository.versions(directoryId)
    const version = versionId
        ? res.items.find((v) => String(v.id) === String(versionId))
        : res.items.find((v) => v.is_active)
    schemaCache[key] = version?.schema_json ?? []
  } catch {
    schemaCache[key] = []
  } finally {
    schemaLoading[key] = false
  }
}

function schemaForVar(v: VarLike): DirectorySchemaField[] {
  if (!v.directoryId) return []
  return schemaCache[cacheKey(v.directoryId, v.versionId ?? '')] ?? []
}

function isLoadingSchema(v: VarLike): boolean {
  if (!v.directoryId) return false
  return Boolean(schemaLoading[cacheKey(v.directoryId, v.versionId ?? '')])
}

const suggestFieldsCache = reactive<Record<string, WebhookField[]>>({})
const suggestFieldsLoading = reactive<Record<string, boolean>>({})

async function loadSuggestFields(proxyUuid: string): Promise<void> {
  if (suggestFieldsCache[proxyUuid] || suggestFieldsLoading[proxyUuid]) return
  suggestFieldsLoading[proxyUuid] = true
  try {
    suggestFieldsCache[proxyUuid] = await webhookRepository.resultFields(proxyUuid)
  } catch {
    suggestFieldsCache[proxyUuid] = []
  } finally {
    suggestFieldsLoading[proxyUuid] = false
  }
}

function suggestFieldsForVar(v: VarLike): WebhookField[] {
  return v.proxyUuid ? suggestFieldsCache[v.proxyUuid] ?? [] : []
}

function isLoadingSuggestFields(v: VarLike): boolean {
  return v.proxyUuid ? Boolean(suggestFieldsLoading[v.proxyUuid]) : false
}

const openVarId = ref<string | null>(null)

function onPopoverOpen(v: VarLike, open: boolean): void {
  openVarId.value = open ? v.fieldId : null
  if (open && v.directoryId) void loadSchema(v.directoryId, v.versionId ?? '')
  if (open && v.proxyUuid) void loadSuggestFields(v.proxyUuid)
}

const openSysGroup = ref<string | null>(null)

function onSysPopoverOpen(group: SystemVariableGroup, open: boolean): void {
  openSysGroup.value = open ? group.name : null
}
</script>

<template>
  <div class="space-y-3">
    <!-- Системные переменные опроса (run/operator/project/call). Backend инжектит их всегда. -->
    <div class="space-y-0.5">
      <div class="mb-1.5 text-[10px] font-medium text-slate-400">Системные</div>
      <div
          v-for="group in SYSTEM_VARIABLE_GROUPS"
          :key="`sys:${group.name}`"
          :class="['group flex w-full items-center rounded-lg pr-1.5 transition', hoverClass]"
      >
        <button
            type="button"
            :title="group.label"
            class="flex min-w-0 flex-1 items-center px-2 py-1 text-left"
            @click="copy(systemGroupRef(group), `sys:${group.name}`)"
        >
          <span class="truncate font-mono text-[10px] text-slate-500">{{ systemGroupRef(group) }}</span>
        </button>

        <div class="flex shrink-0 items-center gap-0.5">
          <button
              type="button"
              title="Скопировать переменную"
              class="flex size-5 items-center justify-center rounded-md text-slate-300 transition hover:bg-slate-100 hover:text-slate-700"
              @click="copy(systemGroupRef(group), `sys:${group.name}`)"
          >
            <Check v-if="copiedId === `sys:${group.name}`" class="size-3 text-emerald-500"/>
            <Copy v-else class="size-3"/>
          </button>

          <Popover
              :open="openSysGroup === group.name"
              @update:open="(o: boolean) => onSysPopoverOpen(group, o)"
          >
            <PopoverTrigger as-child>
              <button
                  type="button"
                  title="Поля переменной"
                  class="flex size-5 items-center justify-center rounded-md text-slate-300 transition hover:bg-blue-50 hover:text-blue-600"
                  :class="openSysGroup === group.name ? 'bg-blue-50 text-blue-600' : ''"
                  @click.stop
              >
                <Sparkles class="size-3"/>
              </button>
            </PopoverTrigger>

            <PopoverContent
                side="right"
                align="start"
                :side-offset="12"
                class="w-80 p-0"
                @open-auto-focus.prevent
            >
              <!-- Header -->
              <div class="border-b border-slate-100 px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="flex size-7 items-center justify-center rounded-lg bg-blue-50">
                    <Sparkles class="size-3.5 text-blue-500"/>
                  </div>
                  <div class="min-w-0 flex-1">
                    <div class="truncate text-[12px] font-semibold text-slate-800">{{ group.label }}</div>
                    <div class="truncate font-mono text-[10px] text-slate-400">{{ systemGroupRef(group) }}</div>
                  </div>
                </div>
              </div>

              <SystemVariableHints
                  :group="group"
                  :copied-id="copiedId"
                  @copy="copy"
              />
            </PopoverContent>
          </Popover>
        </div>
      </div>
    </div>

    <template v-for="{ block, vars } in blocksWithVars" :key="block.id">
      <div class="space-y-0.5">
        <div class="mb-1 flex items-center gap-1.5">
          <span class="truncate text-[10px] font-medium text-slate-500">{{ block.data.title || block.id }}</span>
          <span
              v-if="block.id === currentBlockId"
              class="shrink-0 rounded-full bg-blue-100 px-1.5 py-px text-[9px] font-semibold text-blue-600"
          >текущий</span>
        </div>

        <template v-for="v in vars" :key="v.fieldId">
          <div :class="['group flex w-full items-center rounded-lg pr-1.5 transition', hoverClass]">
            <button
                type="button"
                class="flex min-w-0 flex-1 items-center px-2 py-1 text-left"
                @click="copy(v.varRef, v.fieldId)"
            >
              <span class="truncate font-mono text-[10px] text-slate-500">{{ v.varRef }}</span>
            </button>

            <div class="flex shrink-0 items-center gap-0.5">
              <button
                  type="button"
                  title="Скопировать переменную"
                  class="flex size-5 items-center justify-center rounded-md text-slate-300 transition hover:bg-slate-100 hover:text-slate-700"
                  @click="copy(v.varRef, v.fieldId)"
              >
                <Check v-if="copiedId === v.fieldId" class="size-3 text-emerald-500"/>
                <Copy v-else class="size-3"/>
              </button>

              <Popover
                  v-if="hasHints(v)"
                  :open="openVarId === v.fieldId"
                  @update:open="(o: boolean) => onPopoverOpen(v, o)"
              >
                <PopoverTrigger as-child>
                  <button
                      type="button"
                      :title="isDateVar(v) ? 'Подсказки по форматам даты' : isSelectVar(v) ? 'Подсказки по опциям' : isPhoneVar(v) ? 'Подсказки по частям телефона' : isSuggestVar(v) ? 'Подсказки по полям интеграции' : 'Подсказки по полям справочника'"
                      class="flex size-5 items-center justify-center rounded-md text-slate-300 transition hover:bg-blue-50 hover:text-blue-600"
                      :class="openVarId === v.fieldId ? 'bg-blue-50 text-blue-600' : ''"
                      @click.stop
                  >
                    <Sparkles class="size-3"/>
                  </button>
                </PopoverTrigger>

                <PopoverContent
                    side="right"
                    align="start"
                    :side-offset="12"
                    class="w-80 p-0"
                    @open-auto-focus.prevent
                >
                  <!-- Header -->
                  <div class="border-b border-slate-100 px-4 py-3">
                    <div class="flex items-center gap-2">
                      <div class="flex size-7 items-center justify-center rounded-lg bg-blue-50">
                        <Sparkles class="size-3.5 text-blue-500"/>
                      </div>
                      <div class="min-w-0 flex-1">
                        <div class="truncate text-[12px] font-semibold text-slate-800">
                          {{ v.label || extractVarName(v.varRef) }}
                        </div>
                        <div class="truncate font-mono text-[10px] text-slate-400">{{ v.varRef }}</div>
                      </div>
                    </div>
                  </div>

                  <DateVariableHints
                      v-if="isDateVar(v)"
                      :v="v"
                      :copied-id="copiedId"
                      @copy="copy"
                  />
                  <SelectVariableHints
                      v-else-if="isSelectVar(v)"
                      :v="v"
                      :copied-id="copiedId"
                      @copy="copy"
                  />
                  <PhoneVariableHints
                      v-else-if="isPhoneVar(v)"
                      :v="v"
                      :copied-id="copiedId"
                      @copy="copy"
                  />
                  <SuggestVariableHints
                      v-else-if="isSuggestVar(v)"
                      :v="v"
                      :copied-id="copiedId"
                      :fields="suggestFieldsForVar(v)"
                      :loading="isLoadingSuggestFields(v)"
                      @copy="copy"
                  />
                  <DirectoryVariableHints
                      v-else
                      :v="v"
                      :copied-id="copiedId"
                      :schema="schemaForVar(v)"
                      :loading="isLoadingSchema(v)"
                      @copy="copy"
                  />
                </PopoverContent>
              </Popover>
            </div>
          </div>
        </template>
      </div>
    </template>
  </div>
</template>
