import axios from 'axios'

// Если используешь Ziggy — можешь подменить эти функции на route('kanban.columns.*')
export const api = {
  create: () => '/kanban/columns',
  update: (code) => `/kanban/columns/${encodeURIComponent(code)}`,
  destroy: (code) => `/kanban/columns/${encodeURIComponent(code)}`,
  reorder: () => '/kanban/columns/reorder',
};

export const KanbanApi = {
  createColumn(payload) { return axios.post(api.create(), payload) },
  updateColumn(code, payload) { return axios.put(api.update(code), payload) },
  deleteColumn(code) { return axios.delete(api.destroy(code)) },
  reorderColumns(codes) { return axios.put(api.reorder(), { codes }) },
};
