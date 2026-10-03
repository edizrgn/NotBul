// Run testServerNoteSearch(appSource) in a JavaScript runtime; no browser/package dependency.
async function testServerNoteSearch(appSource) {
    let checks = 0;
    const check = (condition, message) => { if (!condition) throw new Error(message); checks += 1; };
    const extract = (name, next) => appSource.slice(appSource.indexOf(`    function ${name}(`), appSource.indexOf(`    function ${next}(`));
    const functions = extract('filterParams', 'searchLink') + extract('searchLink', 'syncUrl')
        + extract('initServerNoteSearch', 'copyTextToClipboard');
    class Params {
        constructor(value = '') {
            this.values = new Map();
            if (value instanceof Params) this.values = new Map(value.values);
            else String(value).replace(/^\?/, '').split('&').filter(Boolean).forEach((entry) => {
                const [key, ...rest] = entry.split('=');
                this.values.set(decodeURIComponent(key.replace(/\+/g, ' ')), decodeURIComponent(rest.join('=').replace(/\+/g, ' ')));
            });
        }
        get(key) { return this.values.get(key) ?? null; }
        set(key, value) { this.values.set(key, String(value)); }
        has(key) { return this.values.has(key); }
        toString() { return [...this.values].map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`).join('&'); }
    }
    class Element {
        constructor(name = '') { this.name = name; this.value = ''; this.dataset = {}; this.attributes = {}; this.events = {}; this.textContent = ''; this._html = ''; }
        addEventListener(name, callback) { this.events[name] = callback; }
        emit(name, event = {}) { return this.events[name]?.({ target: this, ...event }); }
        setAttribute(name, value) { this.attributes[name] = value; }
        removeAttribute(name) { delete this.attributes[name]; }
        hasAttribute(name) { return Object.hasOwn(this.attributes, name); }
        closest() { return this; }
        focus() { this.focused = true; }
        scrollIntoView() { this.scrolled = true; }
        contains(element) { return element.parent === this; }
        appendChild(option) { this.options.push(option); }
        get innerHTML() { return this._html; }
        set innerHTML(value) { this._html = value; }
    }
    class Select extends Element {
        constructor(name) { super(name); this.options = [{ value: '' }]; }
        get innerHTML() { return this._html; }
        set innerHTML(value) {
            this._html = value;
            this.options = [...value.matchAll(/<option value="([^"]*)"([^>]*)>/g)].map((match) => ({ value: match[1], selected: match[2].includes('selected') }));
            this.value = (this.options.find((item) => item.selected) || this.options[0])?.value || '';
        }
    }
    class Abort {
        constructor() { this.signal = { aborted: false }; }
        abort() { this.signal.aborted = true; }
    }
    const fixture = (home = false) => {
        const ids = {}, requests = [], timers = new Map(), history = [], fields = {}, links = [];
        const form = new Element();
        ['university_id', 'department_type', 'department_id', 'class_id', 'course', 'topic', 'file_type'].forEach((name) => { fields[name] = new Select(name); });
        fields.similar_to = new Element('similar_to');
        fields.q = new Element('q');
        fields.sort = new Select('sort');
        fields.sort.value = 'relevance';
        form.elements = { namedItem: (name) => fields[name] || null };
        const names = home ? ['homeFilterForm', 'homeQuery', 'popularNotesGrid', 'homeResultCount', 'homeSearchStatus']
            : ['searchFilterForm', 'searchQuery', 'searchResults', 'searchResultCount', 'searchStatus'];
        ids[names[0]] = form;
        ids[names[1]] = fields.q;
        names.slice(2).forEach((id) => { ids[id] = new Element(); });
        ids.searchSort = fields.sort;
        ids.searchPagination = new Element();
        ids.searchResultsPanel = new Element();
        ids.searchResultsPanel.dataset.page = '1';
        ids.searchFilterPanel = new Element();
        ['homePrimaryPanelTitle', 'homeLatestSection', 'latestNotesGrid', 'homeResultHint'].forEach((id) => { ids[id] = new Element(); });
        if (home) ['downloads', 'newest'].forEach((sort) => { const link = new Element(); link.dataset.sort = sort; links.push(link); });
        const document = { events: {}, getElementById: (id) => ids[id], querySelectorAll: () => links,
            addEventListener(name, callback) { this.events[name] = callback; }, createElement: () => new Element() };
        let timerId = 0;
        const window = { events: {}, location: { search: '', assign(url) { this.assigned = url; } },
            matchMedia: () => ({ matches: true, addEventListener() {} }),
            setTimeout(callback, delay) { timers.set(++timerId, { callback, delay }); return timerId; },
            clearTimeout(id) { timers.delete(id); }, addEventListener(name, callback) { this.events[name] = callback; } };
        const fetch = (url, options) => new Promise((resolve, reject) => requests.push({ url, options, resolve, reject }));
        class FormData {
            constructor(current) { this.current = current; }
            get(name) { return this.current.elements.namedItem(name)?.value ?? null; }
        }
        const syncUrl = (params, push = false) => { history.push({ query: params.toString(), push }); window.location.search = params.toString() ? '?' + params.toString() : ''; };
        new Function('document', 'window', 'fetch', 'AbortController', 'URLSearchParams', 'FormData', 'HTMLSelectElement', 'Element', 'syncUrl', 'home',
            `const FILTER_NAMES = ['university_id','department_type','department_id','class_id','course','topic','file_type'];\n${functions}\ninitServerNoteSearch(home);`)
            (document, window, fetch, Abort, Params, FormData, Select, Element, syncUrl, home);
        const flush = async () => { for (let i = 0; i < 8; i++) await Promise.resolve(); };
        const runTimer = () => { const callbacks = [...timers.values()]; timers.clear(); callbacks.forEach((timer) => timer.callback()); };
        const response = async (request, data) => { request.resolve({ ok: true, json: async () => data }); await flush(); };
        const click = (element, modifiers = {}) => { let prevented = false; document.events.click({ target: element, button: 0, preventDefault() { prevented = true; }, ...modifiers }); return prevented; };
        const data = (queryString, label, extra = {}) => ({ resultsHtml: label, paginationHtml: 'pages', countLabel: '25', total: 25,
            status: label + ' status', page: 1, queryString, options: {}, searchActive: true, latestHtml: 'latest', ...extra });
        return { ids, form, fields, requests, timers, history, document, window, flush, runTimer, response, click, data, links };
    };
    const test = fixture();
    check(test.requests.length === 0, 'Initial server-rendered page does not repeat the query');
    test.fields.q.value = 'final';
    test.fields.q.emit('input');
    test.fields.q.value = 'final notu';
    test.fields.q.emit('input');
    check(test.requests.length === 0 && test.timers.size === 1 && [...test.timers.values()][0].delay === 500, 'Keystrokes debounce for 500 ms');
    test.runTimer();
    check(test.requests.length === 1 && test.requests[0].url.includes('q=final%20notu') && test.requests[0].url.includes('format=json'), 'Search calls server with complete query');
    check(!test.requests[0].url.includes('include_options'), 'Typing does not refetch hierarchy facets');
    await test.response(test.requests[0], test.data('q=final%20notu', 'first'));
    check(test.ids.searchResults.innerHTML === 'first' && test.ids.searchResultCount.textContent === '25', 'Only server results/count are rendered');
    check(test.history.at(-1).query === 'q=final%20notu' && !test.ids.searchResults.attributes['aria-busy'], 'Canonical URL and busy state updated');

    test.fields.q.value = 'old'; test.fields.q.emit('input'); test.runTimer();
    const stale = test.requests.at(-1);
    test.fields.q.value = 'new'; test.fields.q.emit('input'); test.runTimer();
    const newest = test.requests.at(-1);
    check(stale.options.signal.aborted, 'New input aborts previous request');
    await test.response(stale, test.data('q=old', 'stale'));
    check(test.ids.searchResults.innerHTML === 'first' && test.ids.searchResults.attributes['aria-busy'] === 'true', 'Late stale response cannot overwrite results or clear new busy state');
    await test.response(newest, test.data('q=new', 'new'));
    check(test.ids.searchResults.innerHTML === 'new', 'Latest request wins');

    test.fields.university_id.value = 'u1'; test.fields.course.value = 'old-course'; test.fields.topic.value = 'old-topic';
    test.form.emit('change', { target: test.fields.university_id });
    const hierarchy = test.requests.at(-1);
    check(test.fields.course.value === '' && test.fields.topic.value === '' && hierarchy.url.includes('include_options=1'), 'Parent change clears dependent fields and updates options');
    await test.response(hierarchy, test.data('q=new&university_id=u1', 'scoped', { options: { course: '<option value="">Tüm dersler</option><option value="new-course">Ders</option>' } }));
    check(test.fields.course.options.some((option) => option.value === 'new-course'), 'Dependent options come from server');
    test.fields.sort.value = 'downloads'; test.fields.sort.emit('change');
    check(test.requests.at(-1).url.includes('sort=downloads') && !test.requests.at(-1).url.includes('include_options'), 'Sort stays on server and reuses options');
    await test.response(test.requests.at(-1), test.data('q=new&university_id=u1&sort=downloads', 'sorted'));

    const next = new Element(); next.dataset.page = '2'; next.parent = test.ids.searchPagination;
    const before = test.requests.length;
    check(!test.click(next, { ctrlKey: true }) && test.requests.length === before, 'Modified pagination clicks use native link behavior');
    check(test.click(next) && test.requests.at(-1).url.includes('page=2'), 'Pagination requests server page two');
    await test.response(test.requests.at(-1), test.data('q=new&university_id=u1&sort=downloads&page=2', 'second', { page: 2 }));
    check(test.history.at(-1).push && test.ids.searchResultsPanel.focused && test.ids.searchResultsPanel.scrolled, 'Pagination adds history and focuses results');

    test.window.location.search = '?q=restored&course=missing-option&page=3&sort=rating&similar_to=1';
    test.window.events.popstate();
    check(test.fields.course.value === 'missing-option' && test.requests.at(-1).url.includes('page=3')
        && test.requests.at(-1).url.includes('similar_to=1') && test.requests.at(-1).url.includes('include_options=1'), 'Back navigation restores missing options, mode and page before requesting');
    await test.response(test.requests.at(-1), test.data('q=restored&course=missing-option&page=2&sort=rating&similar_to=1', 'restored', { page: 2 }));
    check(test.history.at(-1).query.includes('page=2'), 'Server clamps stale bookmark page');

    test.fields.sort.emit('change');
    test.requests.at(-1).resolve({ ok: false, json: async () => ({ error: 'Notlar şu anda getirilemiyor.' }) });
    await test.flush();
    check(test.ids.searchStatus.textContent === 'Notlar şu anda getirilemiyor.' && test.ids.searchResults.innerHTML === 'restored', 'Server error preserves visible results and shows useful feedback');
    test.fields.sort.emit('change');
    test.requests.at(-1).reject(new Error('internal network details'));
    await test.flush();
    check(!test.ids.searchStatus.textContent.includes('internal') && !test.ids.searchResults.attributes['aria-busy'], 'Network failures show friendly feedback and unlock results');

    const clear = new Element(); clear.setAttribute('data-reset-search', '');
    test.click(clear);
    check(test.fields.q.value === '' && test.fields.course.value === '' && test.fields.sort.value === 'relevance'
        && !test.requests.at(-1).url.includes('similar_to'), 'Clear resets query, filters, sorting and similar mode');
    await test.response(test.requests.at(-1), test.data('', 'clear', { searchActive: false }));
    check(test.history.at(-1).query === '' && test.fields.q.focused, 'Clear leaves clean URL and restores keyboard focus');

    const home = fixture(true);
    home.fields.q.value = 'ders'; home.fields.q.emit('input'); home.runTimer();
    check(home.requests.at(-1).url.includes('format=home'), 'Home preview uses bounded server endpoint');
    await home.response(home.requests.at(-1), home.data('q=ders', 'home-filtered'));
    check(home.ids.homeLatestSection.hidden && home.ids.homePrimaryPanelTitle.textContent === 'Arama Sonuçları'
        && home.ids.homeResultHint.textContent.includes('İlk 6'), 'Home shows limited preview and hides latest section');
    check(home.links.every((link) => link.href.includes('q=ders') && link.href.includes('sort=relevance')), 'View-all links retain home filters');
    home.click(clear);
    await home.response(home.requests.at(-1), home.data('', 'home-clear', { searchActive: false, latestHtml: 'latest-restored' }));
    check(!home.ids.homeLatestSection.hidden && home.ids.latestNotesGrid.innerHTML === 'latest-restored'
        && home.ids.homePrimaryPanelTitle.textContent === 'Popüler Notlar', 'Clearing home restores both server sections');
    home.fields.q.value = 'new search';
    let prevented = false;
    home.form.emit('submit', { preventDefault() { prevented = true; } });
    check(prevented && home.window.location.assigned === 'search.php?q=new%20search', 'Home submit navigates to full server search');
    return { checks, message: 'Server search controller, debounce, stale requests, error recovery, pagination/history and home preview passed.' };
}
