export interface ServerStatus {
  serverName: string
  status: string
  version?: string
  playersOnline?: number
  playersMax?: number
  favicon?: string
}
export interface MonitorResponse {
  servers?: ServerStatus[]
  totalPlayersOnline?: number
  totalPlayersMax?: number
  todaysRecord?: number
  emptyReason?: 'no_accessible_servers'
  message?: string
}
export interface LastUserRecord {
  login: string
  realname?: string
  profilePhoto?: string
  reg_date?: string | number
  colorScheme?: string
}
