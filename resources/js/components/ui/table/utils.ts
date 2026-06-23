import {isFunction} from "@tanstack/vue-table";
import type {Ref} from "vue";

export function valueUpdater<T>(updaterOrValue: T | ((old: T) => T), ref: Ref<T>): void {
    ref.value = isFunction(updaterOrValue)
        ? updaterOrValue(ref.value)
        : updaterOrValue;
}
