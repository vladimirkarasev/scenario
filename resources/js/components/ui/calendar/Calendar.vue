<script setup>
import { getLocalTimeZone, today } from "@internationalized/date";
import { createReusableTemplate, reactiveOmit, useVModel } from "@vueuse/core";
import { CalendarRoot, useDateFormatter, useForwardPropsEmits } from "reka-ui";
import { createYear, createYearRange, toDate } from "reka-ui/date";
import { computed, toRaw } from "vue";
import { cn } from "@/lib/utils";
import { ChevronDownIcon } from "lucide-vue-next";
import {
  CalendarCell,
  CalendarCellTrigger,
  CalendarGrid,
  CalendarGridBody,
  CalendarGridHead,
  CalendarGridRow,
  CalendarHeadCell,
  CalendarHeader,
  CalendarHeading,
  CalendarNextButton,
  CalendarPrevButton,
} from ".";

const props = defineProps({
  defaultValue: { type: Object, required: false },
  defaultPlaceholder: { type: Object, required: false },
  placeholder: { type: Object, required: false },
  pagedNavigation: { type: Boolean, required: false },
  preventDeselect: { type: Boolean, required: false },
  weekStartsOn: { type: Number, required: false, default: 1 },
  weekdayFormat: { type: String, required: false },
  calendarLabel: { type: String, required: false },
  fixedWeeks: { type: Boolean, required: false },
  maxValue: { type: Object, required: false },
  minValue: { type: Object, required: false },
  locale: { type: String, required: false, default: "ru-RU" },
  numberOfMonths: { type: Number, required: false },
  disabled: { type: Boolean, required: false },
  readonly: { type: Boolean, required: false },
  initialFocus: { type: Boolean, required: false },
  isDateDisabled: { type: Function, required: false },
  isDateUnavailable: { type: Function, required: false },
  dir: { type: String, required: false },
  nextPage: { type: Function, required: false },
  prevPage: { type: Function, required: false },
  modelValue: { type: null, required: false, default: undefined },
  multiple: { type: Boolean, required: false },
  disableDaysOutsideCurrentView: { type: Boolean, required: false },
  asChild: { type: Boolean, required: false },
  as: { type: null, required: false },
  class: {
    type: [Boolean, null, String, Object, Array],
    required: false,
    skipCheck: true,
  },
  layout: { type: null, required: false, default: undefined },
  yearRange: { type: Array, required: false },
});
const emits = defineEmits(["update:modelValue", "update:placeholder"]);

const delegatedProps = reactiveOmit(props, "class", "layout", "placeholder");

const placeholder = useVModel(props, "placeholder", emits, {
  passive: true,
  defaultValue: props.defaultPlaceholder ?? today(getLocalTimeZone()),
});

const formatter = useDateFormatter(props.locale ?? "ru-RU");

const yearRange = computed(() => {
  return (
    props.yearRange ??
    createYearRange({
      start:
        props?.minValue ??
        (
          toRaw(props.placeholder) ??
          props.defaultPlaceholder ??
          today(getLocalTimeZone())
        ).cycle("year", -100),

      end:
        props?.maxValue ??
        (
          toRaw(props.placeholder) ??
          props.defaultPlaceholder ??
          today(getLocalTimeZone())
        ).cycle("year", 10),
    })
  );
});

const [DefineMonthTemplate, ReuseMonthTemplate] = createReusableTemplate();
const [DefineYearTemplate, ReuseYearTemplate] = createReusableTemplate();

const forwarded = useForwardPropsEmits(delegatedProps, emits);
</script>

<template>
  <DefineMonthTemplate v-slot="{ date }">
    <div class="relative">
      <div class="flex h-8 items-center justify-between gap-2 rounded-lg border border-input bg-background px-2.5 text-sm">
        <span class="truncate">{{ formatter.custom(toDate(date), { month: "long" }) }}</span>
        <ChevronDownIcon class="size-4 text-muted-foreground" aria-hidden="true" />
      </div>
      <select
        class="absolute inset-0 size-full cursor-pointer opacity-0"
        aria-label="Месяц"
        :value="date.month"
        @change="
          (e) => {
            placeholder = placeholder.set({
              month: Number(e?.target?.value),
            });
          }
        "
      >
        <option
          v-for="month in createYear({ dateObj: date })"
          :key="month.toString()"
          :value="month.month"
        >
          {{ formatter.custom(toDate(month), { month: "long" }) }}
        </option>
      </select>
    </div>
  </DefineMonthTemplate>

  <DefineYearTemplate v-slot="{ date }">
    <div class="relative">
      <div class="flex h-8 min-w-20 items-center justify-between gap-2 rounded-lg border border-input bg-background px-2.5 text-sm">
        <span>{{ formatter.custom(toDate(date), { year: "numeric" }) }}</span>
        <ChevronDownIcon class="size-4 text-muted-foreground" aria-hidden="true" />
      </div>
      <select
        class="absolute inset-0 size-full cursor-pointer opacity-0"
        aria-label="Год"
        :value="date.year"
        @change="
          (e) => {
            placeholder = placeholder.set({
              year: Number(e?.target?.value),
            });
          }
        "
      >
        <option
          v-for="year in yearRange"
          :key="year.toString()"
          :value="year.year"
        >
          {{ formatter.custom(toDate(year), { year: "numeric" }) }}
        </option>
      </select>
    </div>
  </DefineYearTemplate>

  <CalendarRoot
    v-slot="{ grid, weekDays, date }"
    v-bind="forwarded"
    v-model:placeholder="placeholder"
    data-slot="calendar"
    :class="
      cn(
        'w-fit p-2 [--cell-radius:var(--radius-md)] [--cell-size:--spacing(7)] group/calendar bg-background in-data-[slot=card-content]:bg-transparent in-data-[slot=popover-content]:bg-transparent',
        props.class,
      )
    "
  >
    <CalendarHeader class="pt-0">
      <nav
        class="flex items-center gap-1 absolute top-0 inset-x-0 justify-between"
      >
        <CalendarPrevButton>
          <slot name="calendar-prev-icon" />
        </CalendarPrevButton>
        <CalendarNextButton>
          <slot name="calendar-next-icon" />
        </CalendarNextButton>
      </nav>

      <slot
        name="calendar-heading"
        :date="date"
        :month="ReuseMonthTemplate"
        :year="ReuseYearTemplate"
      >
        <template v-if="layout === 'month-and-year'">
          <div class="flex items-center justify-center gap-1">
            <ReuseMonthTemplate :date="date" />
            <ReuseYearTemplate :date="date" />
          </div>
        </template>
        <template v-else-if="layout === 'month-only'">
          <div class="flex items-center justify-center gap-1">
            <ReuseMonthTemplate :date="date" />
            {{ formatter.custom(toDate(date), { year: "numeric" }) }}
          </div>
        </template>
        <template v-else-if="layout === 'year-only'">
          <div class="flex items-center justify-center gap-1">
            {{ formatter.custom(toDate(date), { month: "long" }) }}
            <ReuseYearTemplate :date="date" />
          </div>
        </template>
        <template v-else>
          <CalendarHeading />
        </template>
      </slot>
    </CalendarHeader>

    <div class="flex w-fit flex-col gap-y-4 mt-4 sm:flex-row sm:gap-x-4 sm:gap-y-0">
      <CalendarGrid v-for="month in grid" :key="month.value.toString()" class="w-fit">
        <CalendarGridHead>
          <CalendarGridRow>
            <CalendarHeadCell v-for="day in weekDays" :key="day">
              {{ day }}
            </CalendarHeadCell>
          </CalendarGridRow>
        </CalendarGridHead>
        <CalendarGridBody>
          <CalendarGridRow
            v-for="(weekDates, index) in month.rows"
            :key="`weekDate-${index}`"
            class="mt-2 w-full"
          >
            <CalendarCell
              v-for="weekDate in weekDates"
              :key="weekDate.toString()"
              :date="weekDate"
            >
              <CalendarCellTrigger :day="weekDate" :month="month.value" />
            </CalendarCell>
          </CalendarGridRow>
        </CalendarGridBody>
      </CalendarGrid>
    </div>
  </CalendarRoot>
</template>
