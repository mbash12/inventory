<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  modelValue: {
    type: [Number, String],
    default: ''
  },
  min: {
    type: Number,
    default: undefined
  },
  max: {
    type: Number,
    default: undefined
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const displayValue = ref('')

const formatNumber = (value) => {
  if (!value) return ''
  // First clean the input to ensure valid number
  const cleanNum = value.toString().replace(/[^\d-]/g, '')
  if (!cleanNum) return ''
  
  // Handle negative numbers
  const isNegative = cleanNum.startsWith('-')
  const absNumber = cleanNum.replace('-', '')
  
  // Add dots for thousand separators
  const formatted = absNumber.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  
  return isNegative ? `-${formatted}` : formatted
}

const unformatNumber = (value) => {
  return value.replace(/\./g, '')
}

watch(() => props.modelValue, (newValue) => {
  displayValue.value = formatNumber(newValue)
}, { immediate: true })

const handleInput = (event) => {
  const input = event.target.value
  const rawValue = unformatNumber(input)
  
  // Only allow digits and minus sign at start
  if (!/^-?\d*$/.test(rawValue)) {
    event.preventDefault()
    return
  }

  const formattedValue = formatNumber(rawValue)
  displayValue.value = formattedValue
  
  const numericValue = rawValue ? parseInt(rawValue) : null
  
  if (props.min !== undefined && numericValue < props.min) return
  if (props.max !== undefined && numericValue > props.max) return
  
  emit('update:modelValue', numericValue)
  emit('change', numericValue)
}
</script>

<template>
  <input
    type="text"
    :value="displayValue"
    @input="handleInput"
    @keypress="(e) => {
      if (!/[-\d]/.test(e.key)) e.preventDefault()
    }"
    v-bind="$attrs"
  />
</template>
