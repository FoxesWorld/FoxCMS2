export type JsonPrimitive = string | number | boolean | null
export type JsonValue = JsonPrimitive | JsonObject | JsonValue[]
export interface JsonObject { [key: string]: JsonValue }
export type JsonKind = 'object' | 'array' | 'string' | 'number' | 'boolean' | 'null'
export type JsonRootKind = JsonKind | 'auto'

export type JsonFieldOptions = Record<string, readonly string[]>
