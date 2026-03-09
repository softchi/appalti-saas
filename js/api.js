/**
 * APPALTI PUBBLICI SAAS - API Client Layer
 *
 * Wrapper fetch con:
 * - CSRF token automatico su ogni richiesta
 * - Gestione errori centralizzata
 * - Refresh token CSRF dalla risposta
 * - Retry su 401 (redirect login)
 * - Indicatore loading automatico
 *
 * @version 1.0.0
 */

const API = (() => {

  // Recupera il CSRF token dal meta tag (aggiornato ad ogni risposta)
  const getCsrfToken = () =>
    document.querySelector('meta[name="csrf-token"]')?.content || '';

  const getAppUrl = () =>
    document.querySelector('meta[name="app-url"]')?.content || '';

  /**
   * Aggiorna il CSRF token nel DOM dopo ogni risposta API
   */
  const updateCsrfToken = (responseData) => {
    if (responseData?.csrf_token) {
      const meta = document.querySelector('meta[name="csrf-token"]');
      if (meta) meta.content = responseData.csrf_token;
    }
  };

  /**
   * Core fetch wrapper
   *
   * @param {string} endpoint  - URL relativo (es. '/api/commesse.php')
   * @param {Object} options   - Opzioni fetch + { showLoader, body (object) }
   * @returns {Promise<Object>}
   */
  const request = async (endpoint, options = {}) => {
    const {
      method = 'GET',
      body = null,
      showLoader = false,
      headers: extraHeaders = {},
      isFormData = false,
    } = options;

    if (showLoader) UI.showLoader();

    const url = endpoint.startsWith('http') ? endpoint : getAppUrl() + endpoint;

    const headers = {
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-Token': getCsrfToken(),
      ...extraHeaders,
    };

    const fetchOptions = { method, headers };

    if (body !== null) {
      if (isFormData || body instanceof FormData) {
        fetchOptions.body = body instanceof FormData ? body : objectToFormData(body);
        // NON impostare Content-Type (lo fa il browser con boundary)
      } else {
        headers['Content-Type'] = 'application/json';
        fetchOptions.body = JSON.stringify(body);
      }
    }

    try {
      const response = await fetch(url, fetchOptions);
      const text = await response.text();

      let data;
      try {
        data = JSON.parse(text);
      } catch {
        throw new Error('Risposta non valida dal server');
      }

      // Aggiorna CSRF token
      updateCsrfToken(data);

      // Gestione 401: reindirizza al login
      if (response.status === 401) {
        window.location.href = getAppUrl() + '/login.php?session_expired=1';
        return;
      }

      if (!response.ok) {
        const err = new Error(data.message || `Errore HTTP ${response.status}`);
        err.status = response.status;
        err.errors = data.errors || {};
        err.data   = data;
        throw err;
      }

      return data;

    } catch (err) {
      if (err.name === 'TypeError' && err.message.includes('fetch')) {
        throw new Error('Errore di rete. Verificare la connessione.');
      }
      throw err;
    } finally {
      if (showLoader) UI.hideLoader();
    }
  };

  // Helper conversione oggetto → FormData
  const objectToFormData = (obj, fd = new FormData(), prefix = '') => {
    for (const key in obj) {
      if (!Object.prototype.hasOwnProperty.call(obj, key)) continue;
      const fullKey = prefix ? `${prefix}[${key}]` : key;
      const val = obj[key];
      if (val === null || val === undefined) continue;
      if (typeof val === 'object' && !(val instanceof File) && !(val instanceof Blob)) {
        objectToFormData(val, fd, fullKey);
      } else {
        fd.append(fullKey, val);
      }
    }
    return fd;
  };

  // ==========================================================================
  // METODI PUBBLICI
  // ==========================================================================

  return {
    get: (endpoint, params = {}, options = {}) => {
      const qs = new URLSearchParams(params).toString();
      return request(endpoint + (qs ? '?' + qs : ''), { method: 'GET', ...options });
    },

    post: (endpoint, body = {}, options = {}) =>
      request(endpoint, { method: 'POST', body, ...options }),

    put: (endpoint, body = {}, options = {}) =>
      request(endpoint, { method: 'PUT', body, ...options }),

    delete: (endpoint, options = {}) =>
      request(endpoint, { method: 'DELETE', ...options }),

    upload: (endpoint, formData, options = {}) =>
      request(endpoint, { method: 'POST', body: formData, isFormData: true, ...options }),

    // Shortcut endpoint comuni
    commesse: {
      list:   (params) => API.get('/api/commesse.php', params),
      get:    (id)     => API.get('/api/commesse.php', { id }),
      create: (data)   => API.post('/api/commesse.php', data),
      update: (id, data) => API.put(`/api/commesse.php?id=${id}`, data),
      delete: (id)     => API.delete(`/api/commesse.php?id=${id}`),
    },

    tasks: {
      list:   (commessaId) => API.get('/api/tasks.php', { commessa_id: commessaId }),
      get:    (id)         => API.get('/api/tasks.php', { id }),
      create: (data)       => API.post('/api/tasks.php', data),
      update: (id, data)   => API.put(`/api/tasks.php?id=${id}`, data),
      delete: (id)         => API.delete(`/api/tasks.php?id=${id}`),
      reorder: (tasks)     => API.post('/api/tasks.php?action=reorder', { tasks }),
    },

    sal: {
      list:    (commessaId) => API.get('/api/sal.php', { commessa_id: commessaId }),
      get:     (id)         => API.get('/api/sal.php', { id }),
      create:  (data)       => API.post('/api/sal.php', data),
      update:  (id, data)   => API.put(`/api/sal.php?id=${id}`, data),
      approve: (id, note)   => API.post(`/api/sal.php?action=approve&id=${id}`, { note_rup: note }),
    },

    documenti: {
      list:     (commessaId, params) => API.get('/api/documenti.php', { commessa_id: commessaId, ...params }),
      get:      (id)         => API.get('/api/documenti.php', { id }),
      upload:   (formData)   => API.upload('/api/documenti.php', formData),
      update:   (id, data)   => API.put(`/api/documenti.php?id=${id}`, data),
      delete:   (id)         => API.delete(`/api/documenti.php?id=${id}`),
      downloadUrl: (id)      => API.getAppUrl() + `/api/documenti.php?action=download&id=${id}`,
    },

    notifiche: {
      list:    (params)  => API.get('/api/notifiche.php', params),
      read:    (id)      => API.post(`/api/notifiche.php?action=read&id=${id}`, {}),
      readAll: ()        => API.post('/api/notifiche.php?action=readall', {}),
    },

    dashboard: () => API.get('/api/dashboard.php'),

    scadenze: {
      list:   (params) => API.get('/api/scadenze.php', params),
      create: (data)   => API.post('/api/scadenze.php', data),
      update: (id, d)  => API.put(`/api/scadenze.php?id=${id}`, d),
    },

    reports: {
      avanzamento: (cid)  => API.get('/api/reports.php', { tipo: 'avanzamento', commessa_id: cid }),
      sal:         (cid)  => API.get('/api/reports.php', { tipo: 'sal', commessa_id: cid }),
      costi:       (cid)  => API.get('/api/reports.php', { tipo: 'costi', commessa_id: cid }),
      gantt:       (cid)  => API.get('/api/reports.php', { tipo: 'gantt', commessa_id: cid }),
      scadenze:    (cid)  => API.get('/api/reports.php', { tipo: 'scadenze', commessa_id: cid }),
      riepilogo:   ()     => API.get('/api/reports.php', { tipo: 'riepilogo' }),
    },

    ai: {
      analyze: (commessaId, tipo = 'completa', domanda = '') =>
        API.post('/api/ai_assistant.php', { commessa_id: commessaId, tipo, domanda }),
    },

    utenti: {
      list:     (params) => API.get('/api/utenti.php', params),
      dropdown: ()       => API.get('/api/utenti.php', { dropdown: true }),
    },

    getAppUrl,
  };

})();
