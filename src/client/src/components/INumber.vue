<script setup>
import { component as NumberInput } from '@coders-tm/vue-number-format'
import { computed } from 'vue'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: null
  },
  min: {
    type: Number,
    default: undefined
  },
  max: {
    type: Number,
    default: undefined
  },
  decimals: {
    type: Number,
    default: 2
  },
  placeholder: {
    type: String,
    default: ''
  }
})

const emit = defineEmits(['update:modelValue', 'change', 'blur'])

// Ensure modelValue is always a valid value for the component
const safeModelValue = computed(() => {
  if (props.modelValue === null || props.modelValue === undefined || props.modelValue === '') {
    return ''
  }
  // Ensure it's a number
  const num = Number(props.modelValue)
  return isNaN(num) ? '' : num
})

const handleUpdate = (value) => {
  // The component returns unmasked numeric value
  if (value === '' || value === null || value === undefined) {
    emit('update:modelValue', null)
  } else {
    const num = Number(value)
    emit('update:modelValue', isNaN(num) ? null : num)
  }
}

const handleInput = (value) => {
  if (value === '' || value === null || value === undefined) {
    emit('change', null)
  } else {
    const num = Number(value)
    emit('change', isNaN(num) ? null : num)
  }
}

const handleBlur = (event) => {
  emit('blur', event)
}
</script>

<template>
  <NumberInput
    :model-value="safeModelValue"
    @update:model-value="handleUpdate"
    @input:model-value="handleInput"
    @blur="handleBlur"
    :placeholder="placeholder"
    :precision="decimals"
    separator="."
    decimal=","
    :min="min"
    :max="max"
    inputmode="decimal"
    v-bind="$attrs"
  />
</template>
