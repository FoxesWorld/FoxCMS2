import { readFile } from 'node:fs/promises'
import { join } from 'node:path'
import { fileURLToPath } from 'node:url'

const repositoryRoot = fileURLToPath(new URL('../../..', import.meta.url))
const themeRoot = join(repositoryRoot, 'templates', 'foxengine2')
const failures = []

const files = {
  devicesView: join(repositoryRoot, 'engine', 'client', 'views', 'DevicesView.vue'),
  devicesController: join(repositoryRoot, 'engine', 'client', 'devices', 'useDevicesCenter.ts'),
  deviceCard: join(repositoryRoot, 'engine', 'client', 'devices', 'DeviceSessionCard.vue'),
  sliderView: join(themeRoot, 'src', 'Slider.vue'),
  sliderSettings: join(themeRoot, 'src', 'slider', 'sliderSettings.ts'),
  sliderController: join(themeRoot, 'src', 'slider', 'useHeroCarousel.ts'),
  sliderRepository: join(themeRoot, 'src', 'slider', 'sliderRuntimeRepository.ts'),
  userBlock: join(themeRoot, 'src', 'UserBlock.vue'),
  profilePopover: join(themeRoot, 'src', 'user-panel', 'ProfilePopover.vue'),
  messagesPopover: join(themeRoot, 'src', 'user-panel', 'MessagesPopover.vue'),
  notificationsPopover: join(themeRoot, 'src', 'user-panel', 'NotificationsPopover.vue'),
}

const source = Object.fromEntries(await Promise.all(
  Object.entries(files).map(async ([key, path]) => [key, await readFile(path, 'utf8')]),
))

function lines(value) {
  return value.split(/\r?\n/).length
}

function limit(name, key, maximum) {
  const count = lines(source[key])
  if (count > maximum) failures.push(`${name} exceeded ${maximum} lines (${count}); split responsibilities before adding more behavior`)
}

function requireText(name, key, tokens) {
  for (const token of tokens) {
    if (!source[key].includes(token)) failures.push(`${name} is missing architecture token: ${token}`)
  }
}

function rejectText(name, key, tokens) {
  for (const token of tokens) {
    if (source[key].includes(token)) failures.push(`${name} regained extracted responsibility: ${token}`)
  }
}

limit('Devices route shell', 'devicesView', 180)
limit('Header user shell', 'userBlock', 140)
limit('Hero slider shell', 'sliderView', 240)
limit('Devices orchestration composable', 'devicesController', 120)
limit('Device session card', 'deviceCard', 190)
limit('Hero carousel controller', 'sliderController', 260)
limit('Profile popover', 'profilePopover', 230)
limit('Messages popover', 'messagesPopover', 110)
limit('Notifications popover', 'notificationsPopover', 230)

requireText('Devices route shell', 'devicesView', [
  'useDevicesCenter',
  'DevicesHero',
  'DevicesMetrics',
  'CurrentSessionBanner',
  'DeviceSessionCard',
  '@/devices/devices-center.css',
])
rejectText('Devices route shell', 'devicesView', [
  'revokeUserSession',
  'formatSessionTime',
  'sessionDeviceIcon',
  '<style scoped>',
])

requireText('Hero slider shell', 'sliderView', [
  'normalizeSliderSettings',
  'loadSliderRuntimeSettings',
  'useHeroCarousel',
])
rejectText('Hero slider shell', 'sliderView', [
  'fetch(',
  'pointerStartX',
  'window.setInterval',
  'function asRecord',
])
requireText('Slider runtime repository', 'sliderRepository', [
  'loadRuntimeJson',
  'sliderRuntimeDataUrl',
  'normalizeSliderSettings',
])
requireText('Slider settings domain', 'sliderSettings', [
  'export interface SliderSettings',
  'normalizeSliderSettings',
  'sliderRuntimeDataUrl',
])
requireText('Hero carousel controller', 'sliderController', [
  'useHeroCarousel',
  'onPointerDown',
  'startTimer',
  'handleVisibilityChange',
])

requireText('Header user shell', 'userBlock', [
  'ProfilePopover',
  'MessagesPopover',
  'NotificationsPopover',
  'togglePanel',
])
rejectText('Header user shell', 'userBlock', [
  'notificationCenter',
  'userSessions',
  'markNotificationRead',
  'refreshUserSessions',
])
requireText('Profile popover', 'profilePopover', ['balanceCurrencies', 'devicesPreview', 'refreshUserSessions'])
requireText('Notifications popover', 'notificationsPopover', ['notificationCenter', 'markNotificationRead', 'refreshNotifications'])

if (failures.length) {
  console.error('Frontend architecture check failed:')
  for (const failure of [...new Set(failures)]) console.error(`- ${failure}`)
  process.exit(1)
}

console.log('Frontend architecture passed: route shells, header popovers, device sessions and hero carousel stay behind explicit feature boundaries.')
