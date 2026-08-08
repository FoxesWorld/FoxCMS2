import type { AchievementStatistic } from './playerAchievements'

export interface AchievementTreeNodeModel extends AchievementStatistic {
  children: AchievementTreeNodeModel[]
}

export interface AchievementServerTree {
  serverId: string
  roots: AchievementTreeNodeModel[]
  achievementCount: number
  unlockCount: number
}

function nodeKey(serverId: string, achievementKey: string): string {
  return `${serverId}\u0000${achievementKey}`
}

function compareNodes(left: AchievementTreeNodeModel, right: AchievementTreeNodeModel): number {
  return left.category.localeCompare(right.category, 'ru-RU')
    || left.title.localeCompare(right.title, 'ru-RU')
    || left.achievementKey.localeCompare(right.achievementKey, 'en-US')
}

function createsCycle(
  child: AchievementTreeNodeModel,
  parent: AchievementTreeNodeModel,
  nodes: Map<string, AchievementTreeNodeModel>,
): boolean {
  const visited = new Set<string>([nodeKey(child.serverId, child.achievementKey)])
  let cursor: AchievementTreeNodeModel | undefined = parent
  while (cursor) {
    const key = nodeKey(cursor.serverId, cursor.achievementKey)
    if (visited.has(key)) return true
    visited.add(key)
    if (!cursor.parentKey) return false
    cursor = nodes.get(nodeKey(cursor.serverId, cursor.parentKey))
  }
  return false
}

export function buildAchievementTrees(items: AchievementStatistic[]): AchievementServerTree[] {
  const nodes = new Map<string, AchievementTreeNodeModel>()
  for (const item of items) {
    nodes.set(nodeKey(item.serverId, item.achievementKey), { ...item, children: [] })
  }

  const rootsByServer = new Map<string, AchievementTreeNodeModel[]>()
  for (const node of nodes.values()) {
    const parent = node.parentKey ? nodes.get(nodeKey(node.serverId, node.parentKey)) : undefined
    if (parent && parent.category === node.category && !createsCycle(node, parent, nodes)) {
      parent.children.push(node)
      continue
    }
    const roots = rootsByServer.get(node.serverId) ?? []
    roots.push(node)
    rootsByServer.set(node.serverId, roots)
  }

  const sortRecursively = (node: AchievementTreeNodeModel): void => {
    node.children.sort(compareNodes)
    node.children.forEach(sortRecursively)
  }

  return [...rootsByServer.entries()]
    .map(([serverId, roots]) => {
      roots.sort(compareNodes)
      roots.forEach(sortRecursively)
      const serverItems = items.filter((item) => item.serverId === serverId)
      return {
        serverId,
        roots,
        achievementCount: serverItems.length,
        unlockCount: serverItems.reduce((total, item) => total + item.earnedCount, 0),
      }
    })
    .sort((left, right) => left.serverId.localeCompare(right.serverId, 'ru-RU'))
}

export function filterAchievementTree(
  node: AchievementTreeNodeModel,
  search: string,
  category: string,
): AchievementTreeNodeModel | null {
  const children = node.children
    .map((child) => filterAchievementTree(child, search, category))
    .filter((child): child is AchievementTreeNodeModel => child !== null)
  const matchesCategory = category === 'all' || node.category === category
  const normalizedSearch = search.trim().toLocaleLowerCase('ru-RU')
  const matchesSearch = normalizedSearch === '' || [
    node.title,
    node.description,
    node.category,
    node.achievementKey,
    node.iconItem,
  ].some((value) => value.toLocaleLowerCase('ru-RU').includes(normalizedSearch))

  if ((!matchesCategory || !matchesSearch) && children.length === 0) return null
  return { ...node, children }
}
