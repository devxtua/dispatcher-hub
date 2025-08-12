import { ref, nextTick } from 'vue'
import { KanbanApi } from '../api/kanban'

export function useKanbanReorder(columnsRef) {
  const past = ref([]);
  const future = ref([]);
  const savingOrder = ref(false);
  const HISTORY_LIMIT = 50;

  const snapshotIds = () => columnsRef.value.slice(1).map(c => c.id);

  function applyOrder(ids) {
    const first = columnsRef.value[0];
    const byId = new Map(columnsRef.value.slice(1).map(c => [c.id, c]));
    const reordered = ids.map(id => byId.get(id)).filter(Boolean);
    const rest = columnsRef.value.slice(1).filter(c => !ids.includes(c.id));
    columnsRef.value = [first, ...reordered, ...rest];
  }

  function onMove(evt) {
    const children = Array.from(evt.to.children);
    const targetIndex = evt.related
      ? children.indexOf(evt.related) + (evt.willInsertAfter ? 1 : 0)
      : children.length;
    return targetIndex > 0; // запрещаем дроп на индекс 0
  }

  function onStart() {
    past.value.push(snapshotIds());
    if (past.value.length > HISTORY_LIMIT) past.value.shift();
    future.value = [];
  }

  async function onEnd() {
    try {
      savingOrder.value = true;
      await nextTick();
      const codes = snapshotIds();
      if (codes.length) await KanbanApi.reorderColumns(codes);
    } finally {
      savingOrder.value = false;
    }
  }

  async function undo() {
    if (!past.value.length) return;
    const prev = past.value.pop();
    const current = snapshotIds();
    future.value.push(current);
    applyOrder(prev);
    try { await KanbanApi.reorderColumns(prev); } catch {}
  }

  async function redo() {
    if (!future.value.length) return;
    const nextIds = future.value.pop();
    past.value.push(snapshotIds());
    applyOrder(nextIds);
    try { await KanbanApi.reorderColumns(nextIds); } catch {}
  }

  return {
    past, future, savingOrder,
    snapshotIds, applyOrder,
    onMove, onStart, onEnd,
    undo, redo,
  };
}
