import { appBootstrap } from '@/app/context'
import { FoxesApiClient } from './FoxesApiClient'

export const foxesApi = new FoxesApiClient(appBootstrap)
