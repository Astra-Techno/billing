// Indian number format: ₹1,50,000
export function inr(amount, decimals = 2) {
  if (amount === null || amount === undefined || amount === '') return '₹0'
  const num = parseFloat(amount)
  if (isNaN(num)) return '₹0'

  return '₹' + num.toLocaleString('en-IN', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  })
}

export function inrCompact(amount) {
  const num = parseFloat(amount) || 0
  if (num >= 10000000) return '₹' + (num / 10000000).toFixed(2) + ' Cr'
  if (num >= 100000)   return '₹' + (num / 100000).toFixed(2)   + ' L'
  if (num >= 1000)     return '₹' + (num / 1000).toFixed(1)     + 'K'
  return inr(num, 0)
}
