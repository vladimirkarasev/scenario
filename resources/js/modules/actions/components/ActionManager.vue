<script setup>
import { useActionManagerStore } from '@/modules/actions/stores/actionManager'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card'
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { Play, Plus, Shield, Trash2, WandSparkles } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'

defineProps({
    editable: {
        type: Boolean,
        default: true,
    },
})

const store = useActionManagerStore()
const {
    actions,
    credentials,
    runs,
    loading,
    error,
    actionDialogOpen,
    credentialDialogOpen,
    executeDialogOpen,
    actionDialogTitle,
    credentialDialogTitle,
    actionForm,
    credentialForm,
    executionForm,
} = storeToRefs(store)

onMounted(() => {
    store.load()
})

function statusVariant(status) {
    if (status === 'success') return 'default'
    if (status === 'failed') return 'destructive'
    if (status === 'running') return 'secondary'
    return 'outline'
}
</script>

<template>
    <div class="grid gap-6">
        <div v-if="error" class="rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {{ error }}
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.35fr_0.95fr]">
            <Card class="border-border/60">
                <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-2">
                        <CardTitle class="text-xl">Actions</CardTitle>
                        <CardDescription>
                            Универсальные integrations: HTTP, webhook, email, XML, CRM и custom handlers.
                        </CardDescription>
                    </div>
                    <Button v-if="editable" class="gap-2" @click="store.openCreateAction()">
                        <Plus class="size-4" />
                        New action
                    </Button>
                </CardHeader>
                <CardContent>
                    <div class="overflow-hidden rounded-2xl border border-border/60">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Name</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Key</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Runs</TableHead>
                                    <TableHead v-if="editable" class="w-[190px] text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="item in actions" :key="item.id">
                                    <TableCell>
                                        <div class="font-medium">{{ item.name }}</div>
                                        <div class="text-xs text-muted-foreground">{{ item.description || 'No description' }}</div>
                                    </TableCell>
                                    <TableCell><Badge variant="secondary">{{ item.type }}</Badge></TableCell>
                                    <TableCell class="font-mono text-xs">{{ item.key }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="item.is_active ? 'default' : 'outline'">
                                            {{ item.is_active ? 'Active' : 'Disabled' }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{{ item.runs?.length ?? 0 }}</TableCell>
                                    <TableCell v-if="editable" class="text-right">
                                        <div class="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" class="gap-1" @click="store.openExecuteDialog(item)">
                                                <Play class="size-4" />
                                                Run
                                            </Button>
                                            <Button variant="outline" size="sm" class="gap-1" @click="store.openEditAction(item)">
                                                <WandSparkles class="size-4" />
                                                Edit
                                            </Button>
                                            <Button variant="destructive" size="icon-sm" @click="store.removeAction(item)">
                                                <Trash2 class="size-4" />
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                                <TableRow v-if="!actions.length">
                                    <TableCell :colspan="editable ? 6 : 5" class="h-24 text-center text-sm text-muted-foreground">
                                        No actions created yet.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>

            <Card class="border-border/60">
                <CardHeader class="flex flex-row items-start justify-between gap-4">
                    <div class="space-y-2">
                        <CardTitle>Credentials</CardTitle>
                        <CardDescription>
                            Secrets are stored encrypted and never returned to the frontend.
                        </CardDescription>
                    </div>
                    <Button v-if="editable" variant="outline" class="gap-2" @click="store.openCreateCredential()">
                        <Shield class="size-4" />
                        New credential
                    </Button>
                </CardHeader>
                <CardContent class="grid gap-3">
                    <div
                        v-for="item in credentials"
                        :key="item.id"
                        class="rounded-2xl border border-border/60 bg-background/70 p-4"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="font-medium">{{ item.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ item.type }}</div>
                            </div>
                            <Badge :variant="item.has_secrets ? 'default' : 'outline'">
                                {{ item.has_secrets ? 'Encrypted secrets' : 'No secrets' }}
                            </Badge>
                        </div>
                        <div class="mt-3 text-xs text-muted-foreground">
                            {{ Object.keys(item.masked_secrets || {}).join(', ') || 'No masked secret keys yet' }}
                        </div>
                        <div v-if="editable" class="mt-4 flex justify-end gap-2">
                            <Button variant="outline" size="sm" @click="store.openEditCredential(item)">Edit</Button>
                            <Button variant="destructive" size="icon-sm" @click="store.removeCredential(item)">
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div v-if="!credentials.length" class="rounded-2xl border border-dashed border-border/60 p-4 text-sm text-muted-foreground">
                        No credentials yet.
                    </div>
                </CardContent>
            </Card>
        </div>

        <Card class="border-border/60">
            <CardHeader>
                <CardTitle>Run History</CardTitle>
                <CardDescription>
                    Последние 100 запусков с результатами delivery и ошибками.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="overflow-hidden rounded-2xl border border-border/60">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Run</TableHead>
                                <TableHead>Action</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Duration</TableHead>
                                <TableHead>Error</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="item in runs" :key="item.id">
                                <TableCell class="font-medium">#{{ item.id }}</TableCell>
                                <TableCell>{{ item.action_name || item.action_id }}</TableCell>
                                <TableCell>
                                    <Badge :variant="statusVariant(item.status)">{{ item.status }}</Badge>
                                </TableCell>
                                <TableCell>{{ item.duration_ms ?? '—' }}</TableCell>
                                <TableCell class="max-w-[420px] truncate text-xs text-muted-foreground">
                                    {{ item.error || '—' }}
                                </TableCell>
                            </TableRow>
                            <TableRow v-if="!runs.length">
                                <TableCell colspan="5" class="h-24 text-center text-sm text-muted-foreground">
                                    No action runs yet.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    </div>

    <Dialog v-model:open="actionDialogOpen">
        <DialogContent class="sm:max-w-5xl">
            <DialogHeader>
                <DialogTitle>{{ actionDialogTitle }}</DialogTitle>
                <DialogDescription>
                    Configure transport, payload, delivery settings and UI/schema metadata.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="action-name">Name</Label>
                    <Input id="action-name" v-model="actionForm.name" placeholder="CRM lead webhook" />
                </div>
                <div class="space-y-2">
                    <Label for="action-key">Key</Label>
                    <Input id="action-key" v-model="actionForm.key" placeholder="crm_lead_webhook" />
                </div>
                <div class="space-y-2">
                    <Label for="action-type">Type</Label>
                    <select id="action-type" v-model="actionForm.type" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                        <option value="http_request">http_request</option>
                        <option value="webhook">webhook</option>
                        <option value="email">email</option>
                        <option value="xml">xml</option>
                        <option value="crm">crm</option>
                        <option value="custom">custom</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <Label for="action-status">Status</Label>
                    <label class="flex h-10 items-center gap-3 rounded-xl border border-border px-3 text-sm">
                        <input v-model="actionForm.is_active" type="checkbox" class="size-4 rounded border-border" />
                        <span>Action is active</span>
                    </label>
                </div>
                <div class="space-y-2 md:col-span-2">
                    <Label for="action-description">Description</Label>
                    <Textarea id="action-description" v-model="actionForm.description" rows="2" />
                </div>
                <div class="space-y-2 md:col-span-2">
                    <Label for="action-config">Config JSON</Label>
                    <Textarea id="action-config" v-model="actionForm.configText" class="min-h-56 font-mono text-xs" />
                </div>
                <div class="space-y-2">
                    <Label for="action-schema">Schema JSON</Label>
                    <Textarea id="action-schema" v-model="actionForm.schemaText" class="min-h-44 font-mono text-xs" />
                </div>
                <div class="space-y-2">
                    <Label for="action-ui-schema">UI Schema JSON</Label>
                    <Textarea id="action-ui-schema" v-model="actionForm.uiSchemaText" class="min-h-44 font-mono text-xs" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="actionDialogOpen = false">Cancel</Button>
                <Button :disabled="loading" @click="store.submitAction()">
                    {{ loading ? 'Saving...' : 'Save action' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="credentialDialogOpen">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>{{ credentialDialogTitle }}</DialogTitle>
                <DialogDescription>
                    Secrets are write-only. Leave secret fields blank on edit to keep existing encrypted values.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2 md:grid-cols-2">
                <div class="space-y-2">
                    <Label for="credential-name">Name</Label>
                    <Input id="credential-name" v-model="credentialForm.name" placeholder="Main CRM bearer token" />
                </div>
                <div class="space-y-2">
                    <Label for="credential-type">Type</Label>
                    <select id="credential-type" v-model="credentialForm.type" class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm">
                        <option value="none">none</option>
                        <option value="bearer">bearer</option>
                        <option value="basic">basic</option>
                        <option value="api_key">api_key</option>
                        <option value="oauth2_placeholder">oauth2_placeholder</option>
                    </select>
                </div>
                <div class="space-y-2">
                    <Label for="credential-config">Config JSON</Label>
                    <Textarea id="credential-config" v-model="credentialForm.configText" class="min-h-44 font-mono text-xs" />
                </div>
                <div class="space-y-2">
                    <Label for="credential-secrets">Secrets JSON</Label>
                    <Textarea id="credential-secrets" v-model="credentialForm.secretsText" class="min-h-44 font-mono text-xs" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="credentialDialogOpen = false">Cancel</Button>
                <Button :disabled="loading" @click="store.submitCredential()">
                    {{ loading ? 'Saving...' : 'Save credential' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="executeDialogOpen">
        <DialogContent class="sm:max-w-3xl">
            <DialogHeader>
                <DialogTitle>Execute action</DialogTitle>
                <DialogDescription>
                    Run action manually with arbitrary input payload to test HTTP delivery, mail, XML or custom handler behavior.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-2 py-2">
                <Label for="execution-input">Input JSON</Label>
                <Textarea id="execution-input" v-model="executionForm.inputText" class="min-h-64 font-mono text-xs" />
            </div>

            <DialogFooter>
                <Button variant="outline" @click="executeDialogOpen = false">Cancel</Button>
                <Button :disabled="loading" class="gap-2" @click="store.executeAction()">
                    <Play class="size-4" />
                    {{ loading ? 'Executing...' : 'Run action' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
