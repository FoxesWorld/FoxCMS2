import ThemeRoot from './ThemeRoot.vue'
import { mountEngine } from '@engine/runtime/mountEngine'
import './styles/fonts.css'
import './styles/tokens.css'
import './styles/reset.css'
import './styles/app.css'
import './styles/news.css'
import './styles/badges.css'
import './styles/admin-slides.css'
import './styles/admin-panel.css'
import './styles/admin-content.css'

mountEngine(ThemeRoot)
