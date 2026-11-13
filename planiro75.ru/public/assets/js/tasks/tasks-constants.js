/**
 * Общие константы страницы задач (до сборки tasks-shell-start + filters + list + modal + chat + shell-end).
 */
(function (w) {
  w.__plannerTasksConstants = {
    STATUS_ORDER: ['new', 'in_progress', 'review', 'done', 'cancelled'],
    STATUS_LABEL: {
      new: 'Новые',
      in_progress: 'В работе',
      review: 'Проверка',
      done: 'Готово',
      cancelled: 'Отмена',
    },
    LIST_STATUS_LABEL: {
      new: 'Новая',
      in_progress: 'В работе',
      review: 'На проверке',
      done: 'Выполнена',
      cancelled: 'Отменена',
    },
    /**
     * Заголовки стадий «Мой план» (как в Битрикс24: независимы от статуса и дедлайна).
     * К колонкам «эта / следующая неделя» в коде добавляется диапазон дат понедельник–воскресенье.
     */
    PLAN_LANE_TITLE: {
      unplanned: 'Не спланированы',
      active: 'Сделаю на этой неделе',
      week: 'Сделаю на следующей неделе',
    },
  };
})(window);
