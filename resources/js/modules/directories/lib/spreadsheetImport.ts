export const MAX_SPREADSHEET_BYTES = 10 * 1024 * 1024
export const MAX_SPREADSHEET_COLUMNS = 200
export const SPREADSHEET_PREVIEW_ROWS = 20

const SPREADSHEET_EXTENSION = /\.(csv|xls|xlsx)$/i

export function spreadsheetFileError(file: Pick<File, 'name' | 'size'>): string | null {
    if (!SPREADSHEET_EXTENSION.test(file.name)) {
        return `Файл «${file.name}» должен иметь формат CSV, XLS или XLSX.`
    }
    if (file.size > MAX_SPREADSHEET_BYTES) {
        return `Файл «${file.name}» превышает допустимый размер 10 МБ.`
    }
    if (file.size === 0) {
        return `Файл «${file.name}» пуст.`
    }
    return null
}

export function spreadsheetColumnsError(columns: number): string | null {
    return columns > MAX_SPREADSHEET_COLUMNS
        ? `В файле больше ${MAX_SPREADSHEET_COLUMNS} колонок.`
        : null
}
