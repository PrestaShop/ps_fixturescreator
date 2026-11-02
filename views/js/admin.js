/*
 * PS Fixtures Creator - back office page.
 * Renders the dashboard and the generator form from the JSON payload embedded in the page,
 * and drives generation in small HTTP batches so it survives PHP time limits.
 */
(function () {
  'use strict';

  var root = document.getElementById('psfc-app');
  var dataNode = document.getElementById('psfc-data');
  if (!root || !dataNode) {
    return;
  }

  var data = JSON.parse(dataNode.textContent);
  var state = {
    stats: data.stats,
    shopId: data.defaultShopId,
    langId: data.defaultLangId,
    config: {},        // generator name => { count, options }
    running: false,
    abort: false,
    activePreset: null
  };

  var GROUP_LABELS = {
    catalog: 'Catalog',
    customers: 'Customers',
    sales: 'Sales',
    support: 'Customer service',
    content: 'Content & team',
    traffic: 'Traffic & statistics',
    system: 'System'
  };
  var GROUP_ICONS = {
    catalog: 'inventory_2',
    customers: 'people',
    sales: 'shopping_cart',
    support: 'forum',
    content: 'article',
    traffic: 'bar_chart',
    system: 'settings'
  };

  // ------------------------------------------------------------------ helpers

  function el(tag, attrs, children) {
    var node = document.createElement(tag);
    if (attrs) {
      Object.keys(attrs).forEach(function (key) {
        if (key === 'class') {
          node.className = attrs[key];
        } else if (key === 'text') {
          node.textContent = attrs[key];
        } else if (key === 'html') {
          node.innerHTML = attrs[key];
        } else if (key.indexOf('on') === 0) {
          node.addEventListener(key.substring(2), attrs[key]);
        } else if (attrs[key] !== null && attrs[key] !== undefined && attrs[key] !== false) {
          node.setAttribute(key, attrs[key]);
        }
      });
    }
    (children || []).forEach(function (child) {
      if (child === null || child === undefined) {
        return;
      }
      node.appendChild(typeof child === 'string' ? document.createTextNode(child) : child);
    });
    return node;
  }

  function fmtInt(value) {
    return Number(value || 0).toLocaleString();
  }

  function fmtCompact(value) {
    value = Number(value || 0);
    if (value >= 1000000) {
      return (value / 1000000).toFixed(value >= 10000000 ? 0 : 1) + 'M';
    }
    if (value >= 10000) {
      return (value / 1000).toFixed(value >= 100000 ? 0 : 1) + 'K';
    }
    return value.toLocaleString();
  }

  function fmtBytes(bytes) {
    bytes = Number(bytes || 0);
    var units = ['B', 'KB', 'MB', 'GB', 'TB'];
    var power = bytes > 0 ? Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1) : 0;
    return (bytes / Math.pow(1024, power)).toFixed(power === 0 ? 0 : 1) + ' ' + units[power];
  }

  function fmtDuration(ms) {
    ms = Number(ms || 0);
    if (ms < 1000) {
      return ms + ' ms';
    }
    var seconds = Math.round(ms / 1000);
    if (seconds < 60) {
      return seconds + ' s';
    }
    var minutes = Math.floor(seconds / 60);
    if (minutes < 60) {
      return minutes + ' min ' + (seconds % 60) + ' s';
    }
    return Math.floor(minutes / 60) + ' h ' + (minutes % 60) + ' min';
  }

  function fmtDate(value) {
    if (!value) {
      return '';
    }
    var date = new Date(String(value).replace(' ', 'T'));
    return isNaN(date.getTime()) ? value : date.toLocaleString();
  }

  function postJson(url, payload) {
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.text().then(function (text) {
        var json;
        try {
          json = JSON.parse(text);
        } catch (e) {
          throw new Error('HTTP ' + response.status + ' - unexpected response: ' + text.substring(0, 300));
        }
        if (!response.ok || json.success === false) {
          throw new Error(json.error || ('HTTP ' + response.status));
        }
        return json;
      });
    });
  }

  function findGenerator(name) {
    for (var i = 0; i < data.generators.length; i++) {
      if (data.generators[i].name === name) {
        return data.generators[i];
      }
    }
    return null;
  }

  function getConfig(name) {
    if (!state.config[name]) {
      var generator = findGenerator(name);
      var options = {};
      (generator ? generator.options : []).forEach(function (option) {
        options[option.name] = option.default;
      });
      state.config[name] = { count: 0, options: options };
    }
    return state.config[name];
  }

  // ------------------------------------------------------------------ tabs

  function showTab(name) {
    root.querySelectorAll('[data-psfc-tab]').forEach(function (link) {
      link.classList.toggle('active', link.getAttribute('data-psfc-tab') === name);
    });
    root.querySelectorAll('.psfc-panel').forEach(function (panel) {
      panel.classList.toggle('is-active', panel.id === 'psfc-' + name);
    });
    try {
      window.history.replaceState(null, '', '#' + name);
    } catch (e) { /* ignore */ }
  }

  root.querySelectorAll('[data-psfc-tab]').forEach(function (link) {
    link.addEventListener('click', function (event) {
      event.preventDefault();
      showTab(link.getAttribute('data-psfc-tab'));
    });
  });

  // ------------------------------------------------------------------ dashboard

  function counterValue(stats, key) {
    var groups = stats.counters || {};
    var found = 0;
    Object.keys(groups).forEach(function (group) {
      groups[group].forEach(function (counter) {
        if (counter.key === key) {
          found = counter.count;
        }
      });
    });
    return found;
  }

  function renderTile(label, value, sub) {
    return el('div', { class: 'psfc-tile' }, [
      el('div', { class: 'psfc-tile-label', text: label }),
      el('div', { class: 'psfc-tile-value', text: value, title: label }),
      sub ? el('div', { class: 'psfc-tile-sub', text: sub }) : null
    ]);
  }

  function renderCard(title, icon, body, extraClass, headerRight) {
    return el('div', { class: 'card ' + (extraClass || '') }, [
      el('div', { class: 'card-header' }, [
        el('h3', {}, [el('i', { class: 'material-icons', text: icon }), title]),
        headerRight || null
      ]),
      el('div', { class: 'card-body p-2' }, [body])
    ]);
  }

  function renderCounterGroup(group, counters) {
    var table = el('table', { class: 'psfc-counters' });
    counters.forEach(function (counter) {
      var action = null;
      if (counter.generator && data.canGenerate && findGenerator(counter.generator)) {
        action = el('a', {
          href: '#psfc-generate',
          title: 'Generate ' + counter.label.toLowerCase(),
          onclick: function (event) {
            event.preventDefault();
            showTab('generate');
            var card = document.getElementById('psfc-gen-' + counter.generator);
            if (card) {
              card.scrollIntoView({ behavior: 'smooth', block: 'center' });
              var input = card.querySelector('input[data-role="count"]');
              if (input) {
                input.focus();
              }
            }
          }
        }, [el('i', { class: 'material-icons', text: 'add_circle_outline' })]);
      }
      table.appendChild(el('tr', {}, [
        el('td', { text: counter.label }),
        el('td', { class: 'psfc-num', text: fmtInt(counter.count), title: fmtInt(counter.count) }),
        el('td', { class: 'psfc-act' }, [action])
      ]));
    });
    return renderCard(GROUP_LABELS[group] || group, GROUP_ICONS[group] || 'folder', table);
  }

  function renderTableSizes(tables) {
    if (!tables || !tables.length) {
      return el('div', { class: 'psfc-empty', text: 'Table sizes are not available (information_schema access denied?).' });
    }
    var max = tables[0].total_bytes || 1;
    var table = el('table', { class: 'psfc-bars' });
    tables.forEach(function (row) {
      var dataPct = Math.max(0.5, (row.data_bytes / max) * 100);
      var indexPct = Math.max(0, (row.index_bytes / max) * 100);
      var tooltip = row.table + ': ' + fmtBytes(row.total_bytes) + ' (data ' + fmtBytes(row.data_bytes) + ', indexes ' + fmtBytes(row.index_bytes) + '), ~' + fmtInt(row.rows) + ' rows';
      table.appendChild(el('tr', { title: tooltip }, [
        el('td', { class: 'psfc-bar-label', text: row.table }),
        el('td', {}, [
          el('div', { class: 'psfc-bar-track', role: 'img', 'aria-label': tooltip }, [
            el('div', { class: 'psfc-bar-fill is-index', style: 'width:' + Math.min(100, dataPct + indexPct) + '%' }),
            el('div', { class: 'psfc-bar-fill', style: 'width:' + Math.min(100, dataPct) + '%' })
          ])
        ]),
        el('td', { class: 'psfc-num', text: fmtBytes(row.total_bytes) }),
        el('td', { class: 'psfc-num', text: fmtCompact(row.rows) + ' rows' })
      ]));
    });
    var legend = el('div', { class: 'text-muted', style: 'font-size:0.72rem;padding:0.25rem 0.5rem' }, [
      el('span', { class: 'psfc-bar-fill', style: 'display:inline-block;position:static;width:10px;height:10px;border-radius:2px;margin-right:4px' }), 'data ',
      el('span', { class: 'psfc-bar-fill is-index', style: 'display:inline-block;position:static;width:10px;height:10px;border-radius:2px;margin:0 4px 0 10px' }), 'indexes'
    ]);
    return el('div', {}, [table, legend]);
  }

  function renderOrdersPerMonth(rows) {
    if (!rows || !rows.length) {
      return el('div', { class: 'psfc-empty', text: 'No order in the last 12 months.' });
    }
    var max = rows.reduce(function (acc, row) { return Math.max(acc, row.orders); }, 0) || 1;
    var columns = el('div', { class: 'psfc-columns', role: 'img', 'aria-label': 'Orders per month' });
    var axis = el('div', { class: 'psfc-column-axis' });
    rows.forEach(function (row) {
      var tooltip = row.month + ': ' + fmtInt(row.orders) + ' orders, revenue ' + fmtInt(Math.round(row.revenue));
      columns.appendChild(el('div', { class: 'psfc-column', title: tooltip }, [
        el('span', { class: 'psfc-column-value', text: fmtCompact(row.orders) }),
        el('div', { class: 'psfc-column-fill', style: 'height:' + Math.max(2, (row.orders / max) * 100) + '%' })
      ]));
      axis.appendChild(el('span', { text: row.month.substring(5) + '/' + row.month.substring(2, 4) }));
    });
    return el('div', {}, [columns, axis]);
  }

  function renderCategoryDepth(rows) {
    if (!rows || !rows.length) {
      return el('div', { class: 'psfc-empty', text: 'No category.' });
    }
    var max = rows.reduce(function (acc, row) { return Math.max(acc, row.categories); }, 0) || 1;
    var table = el('table', { class: 'psfc-bars' });
    rows.forEach(function (row) {
      table.appendChild(el('tr', { title: 'Depth ' + row.depth + ': ' + fmtInt(row.categories) + ' categories' }, [
        el('td', { class: 'psfc-bar-label', text: 'Depth ' + row.depth }),
        el('td', {}, [el('div', { class: 'psfc-bar-track' }, [el('div', { class: 'psfc-bar-fill', style: 'width:' + Math.max(0.5, (row.categories / max) * 100) + '%' })])]),
        el('td', { class: 'psfc-num', text: fmtInt(row.categories) })
      ]));
    });
    return table;
  }

  function describeCreated(created) {
    var parts = [];
    Object.keys(created || {}).forEach(function (key) {
      parts.push(fmtInt(created[key]) + ' ' + key.replace(/_/g, ' '));
    });
    return parts.join(', ');
  }

  function renderRecentRuns(runs) {
    if (!runs || !runs.length) {
      return el('div', { class: 'psfc-empty', text: 'No generation run yet. Runs started from this page or from the console are listed here.' });
    }
    var table = el('table', { class: 'table table-sm psfc-runs mb-0' }, [
      el('thead', {}, [el('tr', {}, ['Date', 'Generator', 'Requested', 'Created', 'Duration', 'Source'].map(function (h) {
        return el('th', { text: h });
      }))])
    ]);
    var body = el('tbody');
    runs.forEach(function (run) {
      var generator = findGenerator(run.generator);
      body.appendChild(el('tr', {}, [
        el('td', { text: fmtDate(run.date_add) }),
        el('td', { text: generator ? generator.label : run.generator }),
        el('td', { class: 'text-right', text: fmtInt(run.quantity) }),
        el('td', { class: 'psfc-created', text: describeCreated(run.created) }),
        el('td', { class: 'text-right', text: fmtDuration(run.duration_ms) }),
        el('td', {}, [el('span', { class: 'badge badge-' + (run.source === 'cli' ? 'secondary' : 'info'), text: run.source })])
      ]));
    });
    table.appendChild(body);
    return el('div', { class: 'table-responsive' }, [table]);
  }

  function renderRunTotals(totals) {
    var names = Object.keys(totals || {});
    if (!names.length) {
      return el('div', { class: 'psfc-empty', text: 'Nothing generated by this module yet.' });
    }
    var max = names.reduce(function (acc, name) { return Math.max(acc, totals[name].quantity); }, 0) || 1;
    var table = el('table', { class: 'psfc-bars' });
    names.sort(function (a, b) { return totals[b].quantity - totals[a].quantity; }).forEach(function (name) {
      var generator = findGenerator(name);
      var total = totals[name];
      var tooltip = (generator ? generator.label : name) + ': ' + fmtInt(total.quantity) + ' requested in ' + total.runs + ' run(s), ' + fmtDuration(total.duration_ms);
      table.appendChild(el('tr', { title: tooltip }, [
        el('td', { class: 'psfc-bar-label', text: generator ? generator.label : name }),
        el('td', {}, [el('div', { class: 'psfc-bar-track' }, [el('div', { class: 'psfc-bar-fill', style: 'width:' + Math.max(0.5, (total.quantity / max) * 100) + '%' })])]),
        el('td', { class: 'psfc-num', text: fmtInt(total.quantity) }),
        el('td', { class: 'psfc-num', text: fmtDuration(total.duration_ms) })
      ]));
    });
    return table;
  }

  function renderDashboard() {
    var stats = state.stats;
    var panel = document.getElementById('psfc-dashboard');
    panel.innerHTML = '';

    var db = stats.database || {};
    panel.appendChild(el('div', { class: 'psfc-tiles' }, [
      renderTile('Products', fmtCompact(counterValue(stats, 'products')), fmtCompact(counterValue(stats, 'combinations')) + ' combinations'),
      renderTile('Categories', fmtCompact(counterValue(stats, 'categories')), fmtCompact(counterValue(stats, 'images')) + ' product images'),
      renderTile('Customers', fmtCompact(counterValue(stats, 'customers')), fmtCompact(counterValue(stats, 'addresses')) + ' addresses'),
      renderTile('Orders', fmtCompact(counterValue(stats, 'orders')), fmtCompact(counterValue(stats, 'order_details')) + ' order lines'),
      renderTile('Carts', fmtCompact(counterValue(stats, 'carts')), fmtCompact(counterValue(stats, 'cart_rules')) + ' cart rules'),
      renderTile('Visitors', fmtCompact(counterValue(stats, 'guests')), fmtCompact(counterValue(stats, 'connections')) + ' connections'),
      renderTile('Database', fmtBytes(db.total_bytes), fmtInt(db.tables) + ' tables, ~' + fmtCompact(db.rows) + ' rows')
    ]));

    var grid = el('div', { class: 'psfc-grid' });
    Object.keys(stats.counters || {}).forEach(function (group) {
      grid.appendChild(renderCounterGroup(group, stats.counters[group]));
    });
    grid.appendChild(renderCard('Biggest tables', 'storage', renderTableSizes(stats.tables), 'psfc-span-2'));
    grid.appendChild(renderCard('Orders per month (last 12 months)', 'show_chart', renderOrdersPerMonth(stats.ordersPerMonth)));
    grid.appendChild(renderCard('Categories per depth', 'account_tree', renderCategoryDepth(stats.categoryDepth)));
    grid.appendChild(renderCard('Generated by this module', 'auto_awesome', renderRunTotals(stats.runTotals)));
    grid.appendChild(renderCard('Recent generation runs', 'history', renderRecentRuns(stats.recentRuns), 'psfc-span-2'));
    panel.appendChild(grid);

    var env = document.getElementById('psfc-env');
    if (env) {
      var e = data.environment || {};
      env.textContent = 'PrestaShop ' + e.prestashopVersion + ' · PHP ' + e.phpVersion + ' · figures at ' + fmtDate(stats.generatedAt);
    }
  }

  function refreshStats() {
    var button = document.getElementById('psfc-refresh');
    if (button) {
      button.disabled = true;
    }
    return fetch(data.urls.stats, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (response) { return response.json(); })
      .then(function (stats) {
        state.stats = stats;
        renderDashboard();
      })
      .catch(function (error) {
        log('Could not refresh figures: ' + error.message, 'error');
      })
      .then(function () {
        if (button) {
          button.disabled = false;
        }
      });
  }

  document.getElementById('psfc-refresh').addEventListener('click', refreshStats);

  // ------------------------------------------------------------------ generate form

  function renderOptionInput(generator, option) {
    var config = getConfig(generator.name);
    var id = 'psfc-opt-' + generator.name + '-' + option.name;
    var input;
    if (option.type === 'bool') {
      input = el('select', { class: 'custom-select', id: id });
      input.appendChild(el('option', { value: '1', text: 'Yes' }));
      input.appendChild(el('option', { value: '0', text: 'No' }));
      input.value = config.options[option.name] ? '1' : '0';
      input.addEventListener('change', function () {
        config.options[option.name] = input.value === '1';
        onConfigChanged();
      });
    } else if (option.type === 'choice') {
      input = el('select', { class: 'custom-select', id: id });
      option.choices.forEach(function (choice) {
        input.appendChild(el('option', { value: choice, text: choice }));
      });
      input.value = config.options[option.name];
      input.addEventListener('change', function () {
        config.options[option.name] = input.value;
        onConfigChanged();
      });
    } else {
      input = el('input', { class: 'form-control', type: 'number', min: '0', step: '1', id: id, value: config.options[option.name] });
      input.addEventListener('input', function () {
        config.options[option.name] = parseInt(input.value, 10) || 0;
        onConfigChanged();
      });
    }
    return el('div', { class: 'psfc-option', title: option.description || '' }, [
      el('label', { for: id, text: option.label }),
      input
    ]);
  }

  function renderGeneratorCard(generator) {
    var config = getConfig(generator.name);
    var countInput = el('input', {
      class: 'form-control form-control-sm',
      type: 'number', min: '0', step: '1',
      'data-role': 'count',
      value: config.count || '',
      placeholder: '0',
      disabled: !data.canGenerate
    });
    var card = el('div', { class: 'psfc-gen', id: 'psfc-gen-' + generator.name });
    countInput.addEventListener('input', function () {
      config.count = Math.max(0, parseInt(countInput.value, 10) || 0);
      card.classList.toggle('is-selected', config.count > 0);
      onConfigChanged();
    });

    var runButton = el('button', {
      type: 'button',
      class: 'btn btn-outline-primary btn-sm',
      text: 'Run',
      disabled: !data.canGenerate,
      onclick: function () {
        if (config.count > 0) {
          runQueue([generator.name]);
        } else {
          countInput.focus();
        }
      }
    });
    var optionsToggle = generator.options.length ? el('button', {
      type: 'button', class: 'btn btn-link', text: 'Options (' + generator.options.length + ')',
      onclick: function () { card.classList.toggle('show-options'); }
    }) : el('span', { class: 'text-muted', style: 'font-size:0.78rem', text: 'No option' });

    var currentKey = generator.name === 'products_with_combinations' ? 'products_combinations' : generator.name;
    var currentCountMap = {
      attributes: 'attribute_groups', customer_groups: 'groups', traffic: 'guests', tags: 'product_tags',
      customer_threads: 'customer_threads', cms_pages: 'cms_pages'
    };
    var current = counterValue(state.stats, currentCountMap[generator.name] || currentKey);

    card.appendChild(el('div', { class: 'psfc-gen-head' }, [
      el('div', {}, [
        el('h4', { class: 'psfc-gen-title', text: generator.label }),
        el('p', { class: 'psfc-gen-desc', text: generator.description })
      ]),
      el('div', { class: 'psfc-gen-count' }, [
        el('label', { text: generator.countLabel.replace(/^Number of /, '') }),
        countInput
      ])
    ]));
    var options = el('div', { class: 'psfc-options' });
    generator.options.forEach(function (option) {
      options.appendChild(renderOptionInput(generator, option));
    });
    card.appendChild(options);
    card.appendChild(el('div', { class: 'psfc-gen-foot' }, [
      el('span', { class: 'psfc-current', text: 'Currently in the shop: ' + fmtInt(current) }),
      el('div', {}, [optionsToggle, ' ', runButton])
    ]));
    card.classList.toggle('is-selected', config.count > 0);
    return card;
  }

  function selectedGenerators() {
    return data.generators
      .filter(function (generator) { return getConfig(generator.name).count > 0; })
      .sort(function (a, b) { return a.priority - b.priority; })
      .map(function (generator) { return generator.name; });
  }

  function cliCommands() {
    var lines = ['cd /path/to/prestashop'];
    selectedGenerators().forEach(function (name) {
      var generator = findGenerator(name);
      var config = getConfig(name);
      var parts = ['php bin/console prestashop:fixtures:generate ' + name, '--count=' + config.count];
      generator.options.forEach(function (option) {
        var value = config.options[option.name];
        if (value !== option.default) {
          parts.push('--option ' + option.name + '=' + (typeof value === 'boolean' ? (value ? 'true' : 'false') : value));
        }
      });
      if (state.shopId !== data.defaultShopId) {
        parts.push('--shopId=' + state.shopId);
      }
      if (state.langId !== data.defaultLangId) {
        parts.push('--languageId=' + state.langId);
      }
      lines.push(parts.join(' \\\n    '));
    });
    if (lines.length === 1) {
      return '# Enter a quantity on at least one generator to build the equivalent console commands.\n# php bin/console prestashop:fixtures:list';
    }
    return lines.join('\n');
  }

  function onConfigChanged() {
    var selected = selectedGenerators();
    var summary = document.getElementById('psfc-run-summary');
    if (summary) {
      if (!selected.length) {
        summary.textContent = 'Enter a quantity on the generators you want to run.';
      } else {
        var total = selected.reduce(function (acc, name) { return acc + getConfig(name).count; }, 0);
        summary.textContent = selected.length + ' generator(s) selected, ' + fmtInt(total) + ' items in total. Generators run in dependency order (catalog first, orders and traffic last).';
      }
    }
    var runAll = document.getElementById('psfc-run-all');
    if (runAll) {
      runAll.disabled = !selected.length || state.running || !data.canGenerate;
    }
    var cli = document.getElementById('psfc-cli-text');
    if (cli) {
      cli.value = cliCommands();
    }
  }

  function applyPreset(name) {
    var preset = data.presets[name];
    if (!preset) {
      return;
    }
    data.generators.forEach(function (generator) {
      delete state.config[generator.name];
    });
    preset.steps.forEach(function (step) {
      var config = getConfig(step.generator);
      config.count = step.count;
      Object.keys(step.options || {}).forEach(function (key) {
        config.options[key] = step.options[key];
      });
    });
    state.activePreset = name;
    renderGenerate();
    showTab('generate');
  }

  function clearSelection() {
    data.generators.forEach(function (generator) {
      delete state.config[generator.name];
    });
    state.activePreset = null;
    renderGenerate();
  }

  function renderGenerate() {
    var panel = document.getElementById('psfc-generate');
    panel.innerHTML = '';

    if (!data.canGenerate) {
      panel.appendChild(el('div', { class: 'alert alert-warning', text: 'Your profile does not have the "edit" permission on this page: generation is disabled.' }));
    }

    // Toolbar: target + presets
    var shopSelect = el('select', { class: 'custom-select custom-select-sm' });
    data.shops.forEach(function (shop) {
      shopSelect.appendChild(el('option', { value: shop.id, text: shop.name + ' (#' + shop.id + ')' }));
    });
    shopSelect.value = String(state.shopId);
    shopSelect.addEventListener('change', function () {
      state.shopId = parseInt(shopSelect.value, 10);
      onConfigChanged();
    });
    var langSelect = el('select', { class: 'custom-select custom-select-sm' });
    data.languages.forEach(function (lang) {
      langSelect.appendChild(el('option', { value: lang.id, text: lang.name }));
    });
    langSelect.value = String(state.langId);
    langSelect.addEventListener('change', function () {
      state.langId = parseInt(langSelect.value, 10);
      onConfigChanged();
    });

    var presets = el('div', { class: 'psfc-presets' }, [el('span', { class: 'text-muted', style: 'font-size:0.85rem', text: 'Presets:' })]);
    Object.keys(data.presets).forEach(function (name) {
      var preset = data.presets[name];
      presets.appendChild(el('button', {
        type: 'button',
        class: 'btn btn-sm ' + (state.activePreset === name ? 'btn-primary' : 'btn-outline-primary'),
        text: preset.label,
        title: preset.description,
        onclick: function () { applyPreset(name); }
      }));
    });
    presets.appendChild(el('button', { type: 'button', class: 'btn btn-sm btn-outline-secondary', text: 'Clear', onclick: clearSelection }));
    if (state.activePreset) {
      presets.appendChild(el('div', { class: 'psfc-preset-desc', text: data.presets[state.activePreset].description + ' Quantities are pre-filled below, adjust them before running.' }));
    }

    panel.appendChild(el('div', { class: 'psfc-toolbar' }, [
      el('div', { class: 'form-group' }, [el('label', { text: 'Target shop' }), shopSelect]),
      el('div', { class: 'form-group' }, [el('label', { text: 'Language' }), langSelect]),
      presets
    ]));

    panel.appendChild(el('div', { class: 'alert alert-info', style: 'font-size:0.85rem' }, [
      el('strong', { text: 'How it works. ' }),
      'Enter a quantity on each generator you need, then run them one by one or all at once. Generation is split in small HTTP batches, so it keeps working beyond PHP\'s max_execution_time, but a browser tab must stay open. ' +
      'For very large volumes (tens of thousands of products or more) prefer the console commands shown at the bottom of this page, then dump the database to reuse it in your performance tests.'
    ]));

    // Generators grouped
    var groups = {};
    data.generators.forEach(function (generator) {
      (groups[generator.group] = groups[generator.group] || []).push(generator);
    });
    Object.keys(groups).forEach(function (group) {
      panel.appendChild(el('h3', { class: 'psfc-group-title', text: GROUP_LABELS[group] || group }));
      var grid = el('div', { class: 'psfc-generators' });
      groups[group].sort(function (a, b) { return a.priority - b.priority; }).forEach(function (generator) {
        grid.appendChild(renderGeneratorCard(generator));
      });
      panel.appendChild(grid);
    });

    // Run bar
    var runAll = el('button', {
      type: 'button', id: 'psfc-run-all', class: 'btn btn-primary', disabled: true,
      onclick: function () { runQueue(selectedGenerators()); }
    }, [el('i', { class: 'material-icons', text: 'play_arrow' }), ' Run selected generators']);
    var stop = el('button', {
      type: 'button', id: 'psfc-stop', class: 'btn btn-outline-danger', disabled: true,
      onclick: function () { state.abort = true; log('Stop requested: finishing the current batch...', 'info'); }
    }, [el('i', { class: 'material-icons', text: 'stop' }), ' Stop']);
    panel.appendChild(el('div', { class: 'psfc-runbar' }, [
      el('div', { class: 'psfc-runbar-top' }, [
        el('div', { class: 'psfc-runbar-summary', id: 'psfc-run-summary' }),
        el('div', { class: 'psfc-runbar-actions' }, [stop, runAll])
      ]),
      el('div', { class: 'psfc-progress-list', id: 'psfc-progress' }),
      el('div', { class: 'psfc-log', id: 'psfc-log' })
    ]));

    // CLI equivalent
    var cliText = el('textarea', { class: 'form-control', id: 'psfc-cli-text', readonly: true, rows: 6 });
    panel.appendChild(el('div', { class: 'card psfc-cli' }, [
      el('div', { class: 'card-header' }, [
        el('h3', { style: 'font-size:0.95rem;margin:0', text: 'Equivalent console commands' }),
        el('button', {
          type: 'button', class: 'btn btn-outline-secondary btn-sm', text: 'Copy',
          onclick: function () {
            cliText.select();
            try { document.execCommand('copy'); } catch (e) { /* ignore */ }
            if (navigator.clipboard) {
              navigator.clipboard.writeText(cliText.value).catch(function () { /* ignore */ });
            }
          }
        })
      ]),
      el('div', { class: 'card-body' }, [
        el('p', { class: 'text-muted', style: 'font-size:0.8rem', text: 'Recommended for large volumes: run these from the shop root, in a screen/tmux session. Add --batch=100 for progress feedback.' }),
        cliText
      ])
    ]));

    // Maintenance
    var maintResult = el('div', { class: 'psfc-maint-result' });
    function maintenance(action, extra, label) {
      maintResult.textContent = label + '...';
      postJson(data.urls.maintenance, Object.assign({ action: action }, extra || {}))
        .then(function (json) {
          maintResult.textContent = json.message + ' (' + fmtDuration(json.durationMs) + ')';
          refreshStats();
        })
        .catch(function (error) {
          maintResult.textContent = 'Error: ' + error.message;
        });
    }
    panel.appendChild(el('div', { class: 'card psfc-maintenance mt-3' }, [
      el('div', { class: 'card-header' }, [el('h3', { style: 'font-size:0.95rem;margin:0', text: 'After generation' })]),
      el('div', { class: 'card-body' }, [
        el('p', { class: 'text-muted', style: 'font-size:0.8rem', text: 'Generated products are not indexed for the front office search until the index is rebuilt. The category tree is regenerated automatically after each category batch.' }),
        el('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', disabled: !data.canGenerate, text: 'Index new products for search', onclick: function () { maintenance('search_index', { full: false }, 'Indexing products'); } }),
        el('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', disabled: !data.canGenerate, text: 'Rebuild the whole search index', onclick: function () { maintenance('search_index', { full: true }, 'Rebuilding search index'); } }),
        el('button', { type: 'button', class: 'btn btn-outline-secondary btn-sm', disabled: !data.canGenerate, text: 'Regenerate category tree', onclick: function () { maintenance('category_tree', {}, 'Regenerating category tree'); } }),
        maintResult
      ])
    ]));

    onConfigChanged();
  }

  // ------------------------------------------------------------------ runner

  function log(message, level) {
    var box = document.getElementById('psfc-log');
    if (!box) {
      return;
    }
    box.classList.add('is-visible');
    var line = el('div', { class: level === 'error' ? 'is-error' : (level === 'ok' ? 'is-ok' : ''), text: '[' + new Date().toLocaleTimeString() + '] ' + message });
    box.appendChild(line);
    box.scrollTop = box.scrollHeight;
  }

  function setRunning(running) {
    state.running = running;
    state.abort = false;
    var stop = document.getElementById('psfc-stop');
    if (stop) {
      stop.disabled = !running;
    }
    root.querySelectorAll('.psfc-gen input, .psfc-gen select, .psfc-gen button').forEach(function (node) {
      node.disabled = running || !data.canGenerate;
    });
    onConfigChanged();
    if (running) {
      window.addEventListener('beforeunload', beforeUnload);
    } else {
      window.removeEventListener('beforeunload', beforeUnload);
    }
  }

  function beforeUnload(event) {
    event.preventDefault();
    event.returnValue = 'A generation is running. Leaving the page will stop it.';
    return event.returnValue;
  }

  function runQueue(names) {
    if (state.running || !names.length) {
      return;
    }
    var progressList = document.getElementById('psfc-progress');
    progressList.innerHTML = '';
    progressList.classList.add('is-visible');
    var items = names.map(function (name) {
      var generator = findGenerator(name);
      var config = getConfig(name);
      var bar = el('div', { class: 'progress-bar', role: 'progressbar', style: 'width:0%' });
      var status = el('span', { class: 'psfc-pstatus', text: '0 / ' + fmtInt(config.count) });
      var row = el('div', { class: 'psfc-progress-item' }, [
        el('span', { text: generator.label }),
        el('div', { class: 'progress' }, [bar]),
        status
      ]);
      progressList.appendChild(row);
      return { name: name, generator: generator, count: config.count, options: config.options, bar: bar, status: status, row: row, done: 0, created: {}, durationMs: 0 };
    });

    setRunning(true);
    log('Starting ' + items.length + ' generator(s) on shop #' + state.shopId + '.', 'info');
    var startedAt = Date.now();

    function runItem(index) {
      if (index >= items.length) {
        return Promise.resolve();
      }
      var item = items[index];
      if (state.abort) {
        item.status.textContent = 'skipped';
        return runItem(index + 1);
      }
      var batchSize = Math.max(1, item.generator.batchSize);
      var itemStart = Date.now();

      function runBatch() {
        if (item.done >= item.count || state.abort) {
          return Promise.resolve();
        }
        var size = Math.min(batchSize, item.count - item.done);
        return postJson(data.urls.run, {
          generator: item.name,
          count: size,
          options: item.options,
          shopId: state.shopId,
          langId: state.langId
        }).then(function (json) {
          item.done += size;
          item.durationMs += json.durationMs || 0;
          Object.keys(json.created || {}).forEach(function (key) {
            item.created[key] = (item.created[key] || 0) + json.created[key];
          });
          (json.messages || []).forEach(function (message) {
            log(item.generator.label + ': ' + message, 'info');
          });
          var pct = Math.round((item.done / item.count) * 100);
          item.bar.style.width = pct + '%';
          var elapsed = Date.now() - itemStart;
          var eta = item.done < item.count ? Math.round((elapsed / item.done) * (item.count - item.done)) : 0;
          item.status.textContent = fmtInt(item.done) + ' / ' + fmtInt(item.count) + (eta ? ' · ~' + fmtDuration(eta) + ' left' : '');
          // Adapt batch size: aim at ~10s per request
          var perItem = (json.durationMs || 1) / size;
          batchSize = Math.max(1, Math.min(item.generator.batchSize * 10, Math.round(10000 / Math.max(perItem, 1))));
          return runBatch();
        });
      }

      return runBatch().then(function () {
        if (item.done >= item.count) {
          item.row.classList.add('is-done');
          item.status.textContent = 'done in ' + fmtDuration(Date.now() - itemStart);
          log(item.generator.label + ': ' + (describeCreated(item.created) || 'nothing created') + ' (' + fmtDuration(Date.now() - itemStart) + ').', 'ok');
        } else {
          item.status.textContent = 'stopped at ' + fmtInt(item.done);
          log(item.generator.label + ': stopped after ' + fmtInt(item.done) + ' item(s).', 'info');
        }
        return runItem(index + 1);
      }).catch(function (error) {
        item.row.classList.add('is-error');
        item.status.textContent = 'error';
        log(item.generator.label + ': ' + error.message, 'error');
        state.abort = true;
        return runItem(index + 1);
      });
    }

    runItem(0).then(function () {
      log('Finished in ' + fmtDuration(Date.now() - startedAt) + '. Refreshing figures...', 'info');
      setRunning(false);
      return refreshStats();
    });
  }

  // ------------------------------------------------------------------ boot

  renderDashboard();
  renderGenerate();
  if (window.location.hash === '#generate') {
    showTab('generate');
  }
  root.removeAttribute('data-loading');
})();
