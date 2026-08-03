const FIELD_NAME_PATTERN = /^[A-Za-z][A-Za-z0-9_.-]{0,63}$/

export function focusFormField(field: unknown): void {
  if (typeof field !== 'string' || !FIELD_NAME_PATTERN.test(field)) return
  const target = document.querySelector<HTMLInputElement>(`[name="${field}"]`)
  if (!target || target.disabled) return
  target.focus({ preventScroll: true })
  target.scrollIntoView({ behavior: 'smooth', block: 'center' })
}
