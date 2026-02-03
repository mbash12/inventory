<script setup>
import { ref, watch } from 'vue'

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
  },
  decimals: {
    type: Number,
    default: 2
  }
})

const emit = defineEmits(['update:modelValue', 'change'])

const displayValue = ref('')

const formatNumber = (value) => {
  if (!value && value !== 0) return ''
  
  // Parse as float to preserve decimals
  const numValue = parseFloat(value)
  
  if (isNaN(numValue)) return ''
  
  // Format with fixed decimal places
  const fixedValue = numValue.toFixed(props.decimals)
  
  // Split into integer and decimal parts
  const [intPart, decPart] = fixedValue.split('.')
  
  // Handle negative numbers
  const isNegative = intPart.startsWith('-')
  const absInt = isNegative ? intPart.slice(1) : intPart
  
  // Add dots for thousand separators
  const formattedInt = absInt.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  
  // Combine with decimal part
  const result = isNegative ? `-${formattedInt},${decPart}` : `${formattedInt},${decPart}`
  
  return result
}

const unformatNumber = (value) => {
  // Remove thousand separators (dots) and convert decimal comma to dot
  return value.replace(/\./g, '').replace(',', '.')
}

watch(() => props.modelValue, (newValue) => {
  displayValue.value = formatNumber(newValue)
}, { immediate: true })

const handleInput = (event) => {
  const input = event.target.value
  
  // Allow digits, one comma for decimal, and minus at start
  // Remove all dots (thousand separators) first for processing
  const cleanInput = input.replace(/\./g, '')
  
  // Validate: allow digits, one comma, minus at start
  if (!/^-?\d*,?\d{0,2}$/.test(cleanInput)) {
    event.preventDefault()
    return
  }

  // Format for display (add thousand separators)
  const parts = cleanInput.split(',')
  const intPart = parts[0]
  const decPart = parts[1] || ''
  
  // Add thousand separators to integer part
  const isNegative = intPart.startsWith('-')
  const absInt = isNegative ? intPart.slice(1) : intPart
  const formattedInt = absInt.replace(/\B(?=(\d{3})+(?!\d))/g, '.')
  const formattedIntWithSign = isNegative ? `-${formattedInt}` : formattedInt
  
  // Reconstruct display value
  displayValue.value = decPart ? `${formattedIntWithSign},${decPart}` : formattedIntWithSign
  
  // Emit numeric value (convert comma back to dot for parsing)
  const rawValue = cleanInput.replace(',', '.')
  const numericValue = rawValue ? parseFloat(rawValue) : null
  
  if (numericValue === null || isNaN(numericValue)) {
    emit('update:modelValue', null)
    emit('change', null)
    return
  }
  
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
      // Allow digits, minus at start, and comma for decimal separator
      const input = e.target.value
      const isMinusAtStart = e.key === '-' && input.length === 0
      const isCommaForDecimal = e.key === ',' && !input.includes(',')
      if (!/[\d]/.test(e.key) && !isMinusAtStart && !isCommaForDecimal) {
        e.preventDefault()
      }
    }"
    v-bind="$attrs"
  />
</template>
