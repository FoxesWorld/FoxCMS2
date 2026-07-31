import ThemeRoot from './ThemeRoot.vue'
import { mountEngine } from '@engine/runtime/mountEngine'
import './styles/fonts.css'
import './styles/tokens.css'
import './styles/reset.css'
import './styles/app.css'

mountEngine(ThemeRoot)
