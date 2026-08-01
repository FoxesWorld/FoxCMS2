import { appBootstrap } from '@/app/context'
import { FoxesApiClient } from './FoxesApiClient'
export { FoxesApiError } from './FoxesApiClient'
export type { FoxesApiErrorPayload } from './FoxesApiClient'

export const foxesApi = new FoxesApiClient(appBootstrap)
