(function (w) {
  /** Сообщения для поля error в JSON ответов API (совпадают с кодами в PHP). */
  var DEFAULT_API_ERROR_MESSAGES = {
    error: 'Не удалось выполнить операцию.',
    unauthorized: 'Требуется вход в систему. Обновите страницу и войдите снова.',
    forbidden: 'Недостаточно прав для этого действия.',
    forbidden_edit: 'Нет прав на изменение этой задачи.',
    forbidden_delete: 'Удалить задачу может только автор или администратор.',
    forbidden_project: 'Нет доступа к выбранному проекту.',
    forbidden_department: 'Нет доступа к этому подразделению.',
    forbidden_bulk: 'Массовые действия доступны только руководителям и администраторам.',
    forbidden_default: 'Нельзя изменить представление по умолчанию таким образом.',
    forbidden_task: 'Нет доступа к этой задаче.',
    not_found: 'Запись не найдена или удалена.',
    task_not_found: 'Задача не найдена.',
    template_not_found: 'Шаблон не найден.',
    bad_request: 'Некорректный запрос.',
    bad_id: 'Некорректный идентификатор.',
    bad_ids: 'Некорректный список задач.',
    bad_params: 'Некорректные параметры запроса.',
    bad_name: 'Некорректное или пустое имя.',
    bad_password: 'Пароль не подходит по правилам безопасности.',
    bad_role: 'Некорректная роль пользователя.',
    bad_department: 'Некорректное или недоступное подразделение.',
    bad_email: 'Некорректный адрес электронной почты.',
    bad_assignee: 'Некорректный или недоступный исполнитель.',
    user_blocked: 'Заблокированного сотрудника нельзя назначить исполнителем или соисполнителем.',
    bad_user: 'Некорректный пользователь или пользователь не из вашей организации.',
    bad_project: 'Некорректный или недоступный проект.',
    project_year_mismatch: 'Проект из другого рабочего года: переносите задачу только в проекты текущего года работы организации.',
    bad_linked_task: 'Связанная задача указана неверно или недоступна.',
    bad_link_type: 'Некорректный тип связи между задачами.',
    bad_parent_portfolio: 'Некорректный родительский портфель.',
    bad_source_portfolio: 'Некорректный исходный портфель.',
    bad_target_portfolio: 'Некорректный целевой портфель.',
    bad_trigger_status: 'Некорректный статус для правила автоматизации.',
    bad_status: 'Некорректный статус задачи.',
    bad_status_transition: 'Такой переход статуса недопустим.',
    bad_priority: 'Некорректный приоритет.',
    bad_extension: 'Тип файла не разрешён.',
    bad_comment: 'Комментарий к файлу указан неверно.',
    bad_response: 'Сервер вернул непонятный ответ. Попробуйте позже.',
    duplicate: 'Такая запись уже существует.',
    duplicate_link: 'Эта связь между задачами уже добавлена.',
    title_required: 'Укажите название.',
    title_empty: 'Название не может быть пустым.',
    name_required: 'Укажите название.',
    project_id_required: 'Выберите проект.',
    project_required: 'Укажите проект.',
    project_title_required: 'Заполните название и проект для шаблона.',
    department_id_required: 'Выберите подразделение.',
    target_portfolio_id_required: 'Укажите целевой портфель.',
    task_id_required: 'Не указана задача.',
    body_required: 'Введите текст.',
    body_too_long: 'Текст слишком длинный.',
    filters_required: 'Задайте условия фильтра для сохранения.',
    order_required: 'Укажите порядок элементов.',
    file_required: 'Выберите файл для загрузки.',
    file_too_large: 'Файл слишком большой (максимум 40 МБ).',
    upload_failed: 'Не удалось сохранить файл на сервере.',
    storage_not_writable: 'На сервере нет прав на запись в каталог storage/uploads (chmod 775).',
    cannot_create_storage: 'Не удалось создать каталог storage/uploads на сервере.',
    upload_partial: 'Файл загружен не полностью — увеличьте upload_max_filesize / post_max_size в PHP.',
    upload_error: 'Ошибка загрузки PHP — проверьте лимиты upload_max_filesize и post_max_size.',
    no_fields: 'Нечего сохранить: не переданы изменения.',
    no_tasks: 'Не выбраны задачи.',
    too_many: 'Слишком много задач за один раз.',
    transition_note_required:
      'Нужно примечание к году работы организации при переводе в статус «На проверке».',
    project_change_forbidden: 'Менять проект может только постановщик задачи.',
    method_not_allowed: 'Эта операция здесь недоступна.',
    not_configured: 'Функция не настроена или таблица в БД отсутствует (нужна миграция).',
    not_visible: 'Нет доступа к задаче.',
    migration_required: 'Нужно выполнить миграцию базы данных.',
    maintenance: 'Сайт на техническом обслуживании.',
    mail_disabled: 'Отправка почты отключена в настройках.',
    mail_send_failed: 'Не удалось отправить письмо.',
    mail_queue_table_missing: 'Таблица очереди почты не найдена. Обратитесь к администратору.',
    portfolio_cycle: 'Нельзя назначить родителя портфеля: получится замкнутый цикл.',
    need_action: 'Укажите действие для правила автоматизации.',
    head_role_forbidden: 'Назначать руководителя подразделения может только администратор.',
    has_projects: 'Нельзя удалить подразделение: к нему привязаны проекты.',
    subtasks_empty: 'Чек-лист пустой. Добавьте хотя бы один пункт.',
    bad_action: 'Неизвестное массовое действие.',
    bad_plan_lane: 'Некорректная колонка плана.',
    bad_plan_stage: 'Некорректная стадия «Мой план».',
    bad_reorder: 'Некорректный порядок стадий.',
    last_stage: 'Нельзя удалить последнюю стадию.',
    bad_recurrence: 'Некорректный режим повтора.',
    bad_template: 'Шаблон задачи не найден или недоступен.',
    bad_subtask_ids: 'Некорректный список пунктов чек-листа.',
    csrf_mismatch: 'Сессия устарела. Обновите страницу и попробуйте снова.',
    account_blocked: 'Доступ к системе заблокирован администратором.',
    cannot_block_self: 'Нельзя заблокировать свою учётную запись.',
    already_blocked: 'Пользователь уже заблокирован.',
    not_blocked: 'Пользователь не заблокирован.',
    cannot_delete_self: 'Нельзя удалить свою учётную запись.',
    has_tasks: 'Нельзя удалить пользователя: у него есть задачи.',
    last_admin: 'Нельзя удалить последнего администратора организации.',
    department_required: 'Укажите хотя бы одно подразделение.',
    email_not_verified: 'Пользователь ещё не подтвердил email.',
    approve_failed: 'Не удалось активировать пользователя.',
  };

  var UNKNOWN_API_ERROR =
    'Не удалось выполнить операцию. Обновите страницу или обратитесь к администратору.';

  /**
   * Переводит код ошибки API в текст для пользователя.
   * pageMap — переопределения для конкретной страницы (имеют приоритет).
   */
  function humanizeApiError(code, pageMap) {
    var c = code != null ? String(code) : '';
    if (pageMap && Object.prototype.hasOwnProperty.call(pageMap, c) && pageMap[c]) {
      return pageMap[c];
    }
    if (c && DEFAULT_API_ERROR_MESSAGES[c]) {
      return DEFAULT_API_ERROR_MESSAGES[c];
    }
    return UNKNOWN_API_ERROR;
  }

  function byId(id) {
    return document.getElementById(id);
  }

  function escapeHtml(s) {
    if (s == null || s === '') return '';
    var d = document.createElement('div');
    d.textContent = String(s);
    return d.innerHTML;
  }

  function apiUrl(path) {
    var root = typeof w.__PLANNER_ROOT__ === 'string' ? w.__PLANNER_ROOT__ : '';
    var p = String(path || '');
    if (!p.startsWith('/')) p = '/' + p;
    return root + p;
  }

  function plannerCsrfHeader() {
    var t = typeof w.__PLANNER_CSRF__ === 'string' ? w.__PLANNER_CSRF__ : '';
    return t || '';
  }

  function setPlannerCsrf(token) {
    if (typeof token === 'string' && token !== '') {
      w.__PLANNER_CSRF__ = token;
    }
  }

  function api(path, opts, errorMap) {
    opts = opts || {};
    var csrfRetried = opts._plannerCsrfRetried === true;
    delete opts._plannerCsrfRetried;
    var realMethod = (opts.method || 'GET').toUpperCase();
    opts.credentials = 'same-origin';
    opts.headers = opts.headers || {};
    var csrf = plannerCsrfHeader();
    var mutating = realMethod !== 'GET' && realMethod !== 'HEAD' && realMethod !== 'OPTIONS';
    if (csrf !== '' && mutating) {
      opts.headers['X-CSRF-Token'] = csrf;
    }
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
      opts.headers['Content-Type'] = 'application/json';
      if (csrf !== '' && mutating) {
        opts.body = Object.assign({}, opts.body, { _csrf: csrf });
      }
      opts.body = JSON.stringify(opts.body);
    }
    if (realMethod === 'PATCH' || realMethod === 'PUT' || realMethod === 'DELETE') {
      opts.headers['X-HTTP-Method-Override'] = realMethod;
      opts.method = 'POST';
      if (csrf !== '') {
        opts.headers['X-CSRF-Token'] = csrf;
      }
      var sep = path.indexOf('?') >= 0 ? '&' : '?';
      path = path + sep + '__method=' + encodeURIComponent(realMethod.toLowerCase());
    } else {
      opts.method = realMethod;
    }

    var allowGetRetry = opts.retryNetwork !== false && realMethod === 'GET';
    var didRetry = false;

    function attemptFetch() {
      return fetch(apiUrl(path), opts).then(
        function (r) {
          return r.json().then(
            function (j) {
              if (!r.ok) {
                var code = (j && j.error) || 'error';
                if (
                  !csrfRetried &&
                  r.status === 419 &&
                  code === 'csrf_mismatch' &&
                  j &&
                  typeof j.csrf_token === 'string' &&
                  j.csrf_token !== ''
                ) {
                  setPlannerCsrf(j.csrf_token);
                  var retryOpts = Object.assign({}, opts, { _plannerCsrfRetried: true });
                  return api(path, retryOpts, errorMap);
                }
                var customMsg = j && typeof j.message === 'string' ? j.message.trim() : '';
                throw new Error(customMsg !== '' ? customMsg : humanizeApiError(code, errorMap));
              }
              return j;
            },
            function () {
              throw new Error(humanizeApiError('bad_response', errorMap));
            }
          );
        },
        function (netErr) {
          if (allowGetRetry && !didRetry) {
            didRetry = true;
            return new Promise(function (resolve, reject) {
              setTimeout(function () {
                attemptFetch().then(resolve, reject);
              }, 700);
            });
          }
          throw netErr instanceof Error
            ? netErr
            : new Error(humanizeApiError('bad_response', errorMap));
        }
      );
    }

    return attemptFetch();
  }

  function ensureToastHost() {
    var host = document.getElementById('plannerToastHost');
    if (host) return host;
    host = document.createElement('div');
    host.id = 'plannerToastHost';
    host.className = 'planner-toast-host';
    document.body.appendChild(host);
    return host;
  }

  function notify(message, opts) {
    opts = opts || {};
    var type = opts.type || 'info';
    var timeoutMs = opts.timeout_ms != null ? opts.timeout_ms : 3200;
    var host = ensureToastHost();
    var item = document.createElement('div');
    item.className = 'planner-toast planner-toast--' + type;
    item.textContent = String(message || '');
    host.appendChild(item);
    requestAnimationFrame(function () {
      item.classList.add('is-visible');
    });
    var done = false;
    function removeToast() {
      if (done) return;
      done = true;
      item.classList.remove('is-visible');
      setTimeout(function () {
        if (item.parentNode) item.parentNode.removeChild(item);
      }, 220);
    }
    item.addEventListener('click', removeToast);
    setTimeout(removeToast, Math.max(1200, timeoutMs));
  }

  w.PlannerCore = {
    byId: byId,
    escapeHtml: escapeHtml,
    apiUrl: apiUrl,
    setPlannerCsrf: setPlannerCsrf,
    api: api,
    notify: notify,
    humanizeApiError: humanizeApiError,
  };
})(window);
