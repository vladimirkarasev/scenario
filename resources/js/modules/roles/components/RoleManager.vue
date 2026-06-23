<script setup>
import { formatDateTime } from '@/lib/formatters'
import { useRoleManagerStore } from '@/modules/roles/stores/roleManager'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
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
import { Pencil, Plus, Shield, Trash2 } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { watch } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        required: true,
    },
    endpoints: {
        type: Object,
        required: true,
    },
    editable: {
        type: Boolean,
        default: false,
    },
})

const roleStore = useRoleManagerStore()
const { items: roles, loading, error, isDialogOpen, form, dialogTitle } = storeToRefs(roleStore)

watch(
    () => props.items,
    (items) => {
        roleStore.initialize(items, props.endpoints)
    },
    { immediate: true, deep: true },
)
</script>

<template>
    <Card class="border-border/60 shadow-sm">
        <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="space-y-2">
                <CardTitle class="text-xl">Roles</CardTitle>
                <CardDescription>
                    CRUD for application roles managed by `spatie/laravel-permission`.
                </CardDescription>
            </div>
            <Button v-if="editable" class="gap-2" @click="roleStore.openCreateDialog()">
                <Plus class="size-4" />
                New role
            </Button>
        </CardHeader>

        <CardContent class="space-y-4">
            <div v-if="error" class="rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                {{ error }}
            </div>

            <div class="overflow-hidden rounded-2xl border border-border/60">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Name</TableHead>
                            <TableHead>Guard</TableHead>
                            <TableHead>Users</TableHead>
                            <TableHead>Updated</TableHead>
                            <TableHead v-if="editable" class="w-[120px] text-right">Actions</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="item in roles" :key="item.id">
                            <TableCell class="font-medium">
                                <span class="inline-flex items-center gap-2">
                                    <Shield class="size-4 text-muted-foreground" />
                                    {{ item.name }}
                                </span>
                            </TableCell>
                            <TableCell>
                                <Badge variant="secondary">{{ item.guard_name }}</Badge>
                            </TableCell>
                            <TableCell>{{ item.users_count }}</TableCell>
                            <TableCell>{{ formatDateTime(item.updated_at) }}</TableCell>
                            <TableCell v-if="editable" class="text-right">
                                <div class="flex justify-end gap-2">
                                    <Button variant="outline" size="icon-sm" @click="roleStore.openEditDialog(item)">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button variant="destructive" size="icon-sm" @click="roleStore.remove(item)">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </TableCell>
                        </TableRow>
                        <TableRow v-if="!roles.length">
                            <TableCell :colspan="editable ? 5 : 4" class="h-24 text-center text-sm text-muted-foreground">
                                No roles yet.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </CardContent>

        <CardFooter class="text-xs text-muted-foreground">
            Roles assigned to users cannot be deleted until assignments are removed.
        </CardFooter>
    </Card>

    <Dialog v-model:open="isDialogOpen">
        <DialogContent class="sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ dialogTitle }}</DialogTitle>
                <DialogDescription>
                    Role names are unique within the `web` guard.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-2">
                <div class="space-y-2">
                    <Label for="role-name">Name</Label>
                    <Input id="role-name" v-model="form.name" placeholder="project_admin" />
                </div>
            </div>

            <DialogFooter>
                <Button variant="outline" @click="isDialogOpen = false">Cancel</Button>
                <Button :disabled="loading" @click="roleStore.submit()">
                    {{ loading ? 'Saving...' : 'Save role' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
