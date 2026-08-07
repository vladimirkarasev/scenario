const TEMPLATE_RE = /\{\{\s*([\w.-]+)\s*\}\}/g

export function extractTemplateKeys(template: string): string[] {
    return [...template.matchAll(TEMPLATE_RE)].map((m) => m[1])
}
