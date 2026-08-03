export interface EmoticonDefinition {
  name: string
  shortcode: string
  url: string
  width: number
  height: number
}

export interface EmoticonCategory {
  id: string
  label: string
  items: readonly EmoticonDefinition[]
}

export interface EmoticonCatalog {
  schema: 1
  syntax: ':emoji:'
  count: number
  categories: readonly EmoticonCategory[]
}

export type EmoticonTextPart =
  | { type: 'text'; value: string }
  | { type: 'emoticon'; value: EmoticonDefinition }
