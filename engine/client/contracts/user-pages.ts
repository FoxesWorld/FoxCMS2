export type SettingsTab = 'profile' | 'appearance' | 'security'
export type SkinResource = 'skin' | 'cloak'

export interface FeedbackMessage {
  type?: string
  message?: string
}

export interface ProfileRecord {
  uuid?: string
  login?: string
  groupTag?: string
  realname?: string
  reg_date?: number | string
  last_date?: number | string
  profilePhoto?: string
  userStatus?: string
  land?: string
  colorScheme?: string
  groupName?: string
  groupColor?: string
  balance?: unknown
  badges?: unknown
  serversOnline?: unknown
}

export interface ProfileEntry {
  label: string
  value: string
  detail?: string
}

export interface ProfileBadge {
  id: string
  title: string
  description: string
  image: string | null
  acquiredAt?: number | string | null
  acquiredLabel?: string
}

export interface ProfileSettingsFormModel {
  login: string
  realname: string
  userStatus: string
  land: string
  email: string
  currentPassword: string
  newPassword: string
  repeatPassword: string
}
