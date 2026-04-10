export function buildTree<T extends { id: number; parent_id: number; children?: T[] }>(
  items: T[],
  parentId = 0
): T[] {
  const tree: T[] = [];
  for (const item of items) {
    if (item.parent_id === parentId) {
      const children = buildTree(items, item.id);
      if (children.length) {
        item.children = children;
      }
      tree.push(item);
    }
  }
  return tree;
}
