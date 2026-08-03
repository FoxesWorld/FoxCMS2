import { reactive } from 'vue'
import { readBootstrap } from '@/domain/bootstrap'

export const appBootstrap = reactive(readBootstrap())
