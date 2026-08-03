export interface BalanceCurrency {
  code: 'units' | 'crystals'
  name: 'Units' | 'Crystals'
  amount: number
  symbol: 'U' | 'C'
  primary: boolean
}

export interface BalanceMatrix {
  version: 1
  currencies: BalanceCurrency[]
}

const DEFINITIONS: ReadonlyArray<Omit<BalanceCurrency, 'amount'>> = [
  { code: 'units', name: 'Units', symbol: 'U', primary: true },
  { code: 'crystals', name: 'Crystals', symbol: 'C', primary: false },
]

function parseUnknown(value: unknown): unknown {
  if (typeof value !== 'string') return value
  const source = value.trim()
  if (!source) return null
  try { return JSON.parse(source) } catch { return null }
}

function normalizeCode(value: unknown): BalanceCurrency['code'] | null {
  const code = String(value ?? '').trim().toLowerCase().replace(/[^a-z0-9]+/g, '')
  if (code === 'unit' || code === 'units') return 'units'
  if (code === 'crystal' || code === 'crystals') return 'crystals'
  return null
}

function normalizeAmount(value: unknown): number {
  const numeric = typeof value === 'string' && /^\d+$/.test(value.trim())
    ? Number(value.trim())
    : Number(value)
  return Number.isSafeInteger(numeric) && numeric >= 0 ? numeric : 0
}

export function normalizeBalanceMatrix(value: unknown): BalanceMatrix {
  const parsed = parseUnknown(value)
  const record = parsed && typeof parsed === 'object' && !Array.isArray(parsed)
    ? parsed as Record<string, unknown>
    : null
  const source = record?.currencies ?? record?.matrix ?? parsed
  const amounts = new Map<BalanceCurrency['code'], number>()

  if (Array.isArray(source)) {
    for (const rawEntry of source) {
      if (!rawEntry || typeof rawEntry !== 'object' || Array.isArray(rawEntry)) continue
      const entry = rawEntry as Record<string, unknown>
      const code = normalizeCode(entry.code ?? entry.id ?? entry.name ?? entry.label)
      if (code) {
        amounts.set(code, normalizeAmount(entry.amount ?? entry.value ?? entry.balance))
        continue
      }
      // Historical FoxCMS balances used singleton objects:
      // [{ "crystals": 200 }, { "units": 1000 }].
      for (const [legacyCode, legacyAmount] of Object.entries(entry)) {
        const normalizedLegacyCode = normalizeCode(legacyCode)
        if (normalizedLegacyCode) amounts.set(normalizedLegacyCode, normalizeAmount(legacyAmount))
      }
    }
  } else if (source && typeof source === 'object') {
    for (const [key, rawEntry] of Object.entries(source as Record<string, unknown>)) {
      const entry = rawEntry && typeof rawEntry === 'object' && !Array.isArray(rawEntry)
        ? rawEntry as Record<string, unknown>
        : null
      const code = normalizeCode(entry?.code ?? entry?.id ?? entry?.name ?? key)
      if (!code) continue
      amounts.set(code, normalizeAmount(entry?.amount ?? entry?.value ?? entry?.balance ?? rawEntry))
    }
  }

  return {
    version: 1,
    currencies: DEFINITIONS.map((definition) => ({
      ...definition,
      amount: amounts.get(definition.code) ?? 0,
    })),
  }
}

export function balanceCurrencyIconPath(code: BalanceCurrency['code']): string {
  return `icons/${code}.png`
}

export function formatBalanceAmount(value: number): string {
  return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 })
    .format(normalizeAmount(value))
}
