<script setup>
import {useProjectManagerStore} from '@/modules/projects/stores/projectManager'
import {Badge} from '@/components/ui/badge'
import {Button} from '@/components/ui/button'
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
import {Input} from '@/components/ui/input'
import {Label} from '@/components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import {Textarea} from '@/components/ui/textarea'
import {Pencil, Plus, Trash2} from 'lucide-vue-next'
import {storeToRefs} from 'pinia'
import {watch} from 'vue'

const props = defineProps({
  title: {
    type: String,
    default: 'Projects',
  },
  description: {
    type: String,
    default: '',
  },
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
    default: true,
  },
})

const projectStore = useProjectManagerStore()
const {items: projects, loading, error, isDialogOpen, form, dialogTitle} = storeToRefs(projectStore)

watch(
    () => props.items,
    (items) => {
      projectStore.initialize(items, props.endpoints)
    },
    {immediate: true, deep: true},
)
</script>

<template>
  <Card class="border-border/60 shadow-sm">
    <CardHeader class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="space-y-2">
        <CardTitle class="text-xl">{{ title }}</CardTitle>
        <CardDescription>{{ description }}</CardDescription>
      </div>
      <Button v-if="editable" class="gap-2" @click="projectStore.openCreateDialog()">
        <Plus class="size-4"/>
        New project
      </Button>
    </CardHeader>

    <CardContent class="space-y-4">
      <div v-if="error"
           class="rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
        {{ error }}
      </div>

      <div class="overflow-hidden rounded-2xl border border-border/60">
        <Table>
          <TableHeader>
            <TableRow>
              <TableHead>Name</TableHead>
              <TableHead>Sitekey</TableHead>
              <TableHead>Host</TableHead>
              <TableHead>Secret</TableHead>
              <TableHead>Status</TableHead>
              <TableHead v-if="editable" class="w-[120px] text-right">Actions</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            <TableRow v-for="item in projects" :key="item.id">
              <TableCell class="font-medium">{{ item.name }}</TableCell>
              <TableCell>{{ item.sitekey }}</TableCell>
              <TableCell>{{ item.host }}</TableCell>
              <TableCell class="max-w-[220px] truncate font-mono text-xs">{{ item.shared_secret }}</TableCell>
              <TableCell>
                <Badge :variant="item.is_active ? 'default' : 'outline'">
                  {{ item.is_active ? 'Active' : 'Disabled' }}
                </Badge>
              </TableCell>
              <TableCell v-if="editable" class="text-right">
                <div class="flex justify-end gap-2">
                  <Button variant="outline" size="icon-sm" @click="projectStore.openEditDialog(item)">
                    <Pencil class="size-4"/>
                  </Button>
                  <Button variant="destructive" size="icon-sm" @click="projectStore.remove(item)">
                    <Trash2 class="size-4"/>
                  </Button>
                </div>
              </TableCell>
            </TableRow>
            <TableRow v-if="!projects.length">
              <TableCell :colspan="editable ? 6 : 5" class="h-24 text-center text-sm text-muted-foreground">
                No projects yet. Create one to enable embed login.
              </TableCell>
            </TableRow>
          </TableBody>
        </Table>
      </div>
    </CardContent>

    <CardFooter class="text-xs text-muted-foreground">
      One active project with valid `sitekey + host + shared_secret` is required for embed token exchange.
    </CardFooter>
  </Card>

  <Dialog v-model:open="isDialogOpen">
    <DialogContent class="sm:max-w-2xl">
      <DialogHeader>
        <DialogTitle>{{ dialogTitle }}</DialogTitle>
        <DialogDescription>
          Configure project identity and signing secret used for embed authentication.
        </DialogDescription>
      </DialogHeader>

      <div class="grid gap-4 py-2 md:grid-cols-2">
        <div class="space-y-2">
          <Label for="project-name">Name</Label>
          <Input id="project-name" v-model="form.name" placeholder="Demo project"/>
        </div>
        <div class="space-y-2">
          <Label for="project-sitekey">Sitekey</Label>
          <Input id="project-sitekey" v-model="form.sitekey" placeholder="demo-site"/>
        </div>
        <div class="space-y-2">
          <Label for="project-host">Host</Label>
          <Input id="project-host" v-model="form.host" placeholder="parent.localhost"/>
        </div>
        <div class="space-y-2">
          <Label for="project-status">Status</Label>
          <label class="flex h-10 items-center gap-3 rounded-xl border border-border px-3 text-sm">
            <input v-model="form.is_active" type="checkbox" class="size-4 rounded border-border"/>
            <span>Project is active</span>
          </label>
        </div>
        <div class="space-y-2 md:col-span-2">
          <Label for="project-secret">Shared secret</Label>
          <Textarea id="project-secret" v-model="form.shared_secret" rows="4" placeholder="at least 32 characters"/>
        </div>
      </div>

      <DialogFooter>
        <Button variant="outline" @click="isDialogOpen = false">Cancel</Button>
        <Button :disabled="loading" @click="projectStore.submit()">
          {{ loading ? 'Saving...' : 'Save project' }}
        </Button>
      </DialogFooter>
    </DialogContent>
  </Dialog>
</template>
