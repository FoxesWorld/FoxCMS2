import { appBootstrap } from '@/app/context'
import { FoxesApiClient } from './FoxesApiClient'
export { FoxesApiError, foxesApiFailureFeedback } from './FoxesApiClient'
export type { FoxesApiErrorPayload, FoxesApiFailureFeedback } from './FoxesApiClient'

export const foxesApi = new FoxesApiClient(appBootstrap)
