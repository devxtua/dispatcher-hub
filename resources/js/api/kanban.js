// Kanban API (axios)
// Колонки: POST /kanban/columns, PUT /kanban/columns/:code, DELETE /kanban/columns/:code,
//          PUT /kanban/columns/reorder { codes[] }.
// Заказы:  PUT /orders/reorder { column_id, ordered_ids[] },
//          PUT /orders/:id/move { to_column_id, position },
//          PUT /orders/:id/note { note }.
// Примечания: code/orderId экранируются через encodeURIComponent;
// при наличии Ziggy можно заменить пути на route('...').




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

  reorderOrders(columnCode, orderedIds) {
    return axios.put('/orders/reorder', {
      column: String(columnCode),
      ordered_ids: (orderedIds || []).map(String),
    });
  },


  // Перенос одной карточки между колонками (или внутри с позицией)
  moveOrder(orderId, toColumnCode, newIndex, orderedIds, shopOrderNumber) {
    return axios.put(`/orders/${encodeURIComponent(orderId)}/move`, {
      column: toColumnCode,
      new_index: newIndex,
      ordered_ids: orderedIds,
      shop_order_number: shopOrderNumber ?? null,
    });
  },

  // (опционально) заметка по карточке
  updateOrderNote(orderId, note) {
    return axios.put(`/orders/${encodeURIComponent(orderId)}/note`, { note });
  },
};



