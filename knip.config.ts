import type { KnipConfig } from 'knip'

const config: KnipConfig = {
    entry: [
        'resources/js/app.ts',
        'resources/js/Pages/**/*.vue',
    ],
    project: ['resources/js/**/*.{ts,vue}'],
    ignore: [
        'resources/js/components/ui/**',
    ],
    ignoreDependencies: [
        'autoprefixer',
        'postcss',
        'tw-animate-css',
    ],
}

export default config
