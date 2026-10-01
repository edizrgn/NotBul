(() => {
    'use strict';

    const CLASS_OPTIONS = [
        { id: '1', name: '1. Sınıf' },
        { id: '2', name: '2. Sınıf' },
        { id: '3', name: '3. Sınıf' },
        { id: '4', name: '4. Sınıf' }
    ];

    const REMOTE = {
        universities: [],
        universitiesById: new Map(),
        departmentsByType: {
            lisans: [],
            onlisans: []
        },
        departmentsById: new Map()
    };

    async function loadRemoteFilterData() {
        try {
            const [universitiesResponse, departmentsResponse] = await Promise.all([
                fetch('assets/data/universiteler.json'),
                fetch('assets/data/bolumler.json')
            ]);

            if (!universitiesResponse.ok || !departmentsResponse.ok) {
                throw new Error('Json dosyalari okunamadi');
            }

            const universities = await universitiesResponse.json();
            const departments = await departmentsResponse.json();

            REMOTE.universities = Array.isArray(universities) ? universities : [];
            REMOTE.universitiesById = new Map(REMOTE.universities.map((item) => [item.id, item]));

            REMOTE.departmentsByType = {
                lisans: Array.isArray(departments.lisans) ? departments.lisans : [],
                onlisans: Array.isArray(departments.onlisans) ? departments.onlisans : []
            };
            REMOTE.departmentsById = new Map(
                [...REMOTE.departmentsByType.lisans, ...REMOTE.departmentsByType.onlisans].map((item) => [item.id, item])
            );
        } catch (error) {
            REMOTE.universities = [];
            REMOTE.universitiesById = new Map(REMOTE.universities.map((item) => [item.id, item]));
            REMOTE.departmentsByType = {
                lisans: [],
                onlisans: []
            };
            REMOTE.departmentsById = new Map();
        }
    }

    function normalize(value) {
        return (value || '').toString().trim().toLocaleLowerCase('tr-TR');
    }

    function normalizeSearch(value) {
        return normalize(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/ı/g, 'i');
    }

    const FILTER_NAMES = ['university_id', 'department_type', 'department_id', 'class_id', 'course', 'topic', 'file_type'];

    function restoreFilterParams(form, queryInput) {
        const params = new URLSearchParams(window.location.search);
        FILTER_NAMES.forEach((name) => {
            const field = form.elements.namedItem(name);
            if (!field) return;
            const value = params.get(name) || '';
            field.value = value;
            if (field instanceof HTMLSelectElement) field.dataset.selected = value;
        });
        if (queryInput) queryInput.value = params.get('q') || params.get('query') || '';
    }

    function filterParams(form, queryInput, sort = '', page = 1, similarTo = '') {
        const params = new URLSearchParams();
        const data = new FormData(form);
        const query = (queryInput?.value || '').trim();
        if (query) params.set('q', query);
        FILTER_NAMES.forEach((name) => {
            const value = (data.get(name) || '').toString().trim();
            if (value) params.set(name, value);
        });
        if (sort && sort !== 'relevance') params.set('sort', sort);
        if (page > 1) params.set('page', String(page));
        if (similarTo) params.set('similar_to', String(similarTo));
        return params;
    }

    function searchLink(params) {
        const query = params.toString();
        return `search.php${query ? `?${query}` : ''}`;
    }

    function syncUrl(params, push = false) {
        const url = new URL(window.location.href);
        url.search = params.toString();
        if (url.href !== window.location.href) {
            window.history[push ? 'pushState' : 'replaceState']({}, '', url);
        }
        const returnTo = `${url.pathname.split('/').pop() || 'index.php'}${url.search}${url.hash}`;
        document.querySelectorAll('[data-auth-page]').forEach((link) => {
            const authParams = new URLSearchParams();
            authParams.set('return_to', returnTo);
            link.href = `${link.dataset.authPage}?${authParams.toString()}`;
        });
    }

    function resetFilters(form, queryInput) {
        form.reset();
        FILTER_NAMES.forEach((name) => {
            const field = form.elements.namedItem(name);
            if (field) {
                field.value = '';
                delete field.dataset.selected;
            }
        });
        if (queryInput) queryInput.value = '';
        form.dispatchEvent(new Event('hierarchy:restore'));
    }

    function sortNotes(notes, sort = 'relevance', query = '') {
        const words = normalizeSearch(query).split(/\s+/).filter(Boolean);
        const relevance = (note) => {
            const title = normalizeSearch(note.title);
            const course = normalizeSearch(resolveCourseName(note));
            const tags = normalizeSearch((note.tags || []).join(' '));
            return words.reduce((score, word) => score + (title.includes(word) ? 5 : 0)
                + (course.includes(word) ? 3 : 0) + (tags.includes(word) ? 2 : 0), 0);
        };
        return [...notes].sort((left, right) => {
            let difference = 0;
            if (sort === 'downloads') difference = (right.downloads || 0) - (left.downloads || 0);
            if (sort === 'rating') difference = (Number(right.ratingAverage) || 0) - (Number(left.ratingAverage) || 0)
                || (right.ratingCount || 0) - (left.ratingCount || 0);
            if (sort === 'relevance' && words.length) difference = relevance(right) - relevance(left);
            return difference || new Date(right.createdAt) - new Date(left.createdAt) || right.id - left.id;
        });
    }

    function escapeHtml(value) {
        return (value || '').toString()
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(dateValue) {
        const date = new Date(dateValue);
        if (Number.isNaN(date.getTime())) return '';
        return new Intl.DateTimeFormat('tr-TR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).format(date);
    }

    function formatNumber(numberValue) {
        return new Intl.NumberFormat('tr-TR').format(numberValue || 0);
    }

    function formatRatingAverage(ratingValue) {
        const rating = Number.parseFloat(ratingValue);
        if (!Number.isFinite(rating)) {
            return '';
        }

        return new Intl.NumberFormat('tr-TR', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1
        }).format(Math.min(5, Math.max(1, rating)));
    }

    function ratingSummaryTemplate(note, showCount = false) {
        const count = Number.parseInt(note.ratingCount || 0, 10);
        const average = Number.parseFloat(note.ratingAverage);

        if (!count || !Number.isFinite(average)) {
            return '';
        }

        const countLabel = count === 1 ? '1 değerlendirme' : `${formatNumber(count)} değerlendirme`;
        const countHtml = showCount ? `<span class="rating-count">(${formatNumber(count)})</span>` : '';

        return `
            <span class="rating-summary" title="${escapeHtml(countLabel)}">
                <i class="fa-solid fa-star" aria-hidden="true"></i>
                <span class="rating-score">${escapeHtml(formatRatingAverage(average))}</span>
                ${countHtml}
            </span>
        `;
    }

    function ratingStarsTemplate(ratingValue, showValue = true) {
        const parsedRating = Number.parseInt(ratingValue || 0, 10);
        const rating = Number.isFinite(parsedRating)
            ? Math.min(5, Math.max(1, parsedRating))
            : 1;
        const stars = Array.from({ length: 5 }, (_, index) => {
            const iconClass = index < rating ? 'fa-solid' : 'fa-regular';
            return `<i class="${iconClass} fa-star" aria-hidden="true"></i>`;
        }).join('');
        const valueHtml = showValue ? `<span class="rating-value">${rating}/5</span>` : '';

        return `
            <span class="rating-stars-wrap">
                <span class="rating-stars" aria-label="${rating}/5">${stars}</span>
                ${valueHtml}
            </span>
        `;
    }

    function resolveUniversityName(id) {
        const remote = REMOTE.universitiesById.get(id);
        if (remote) {
            return remote.name;
        }
        return '-';
    }

    function resolveDepartmentName(id) {
        const remote = REMOTE.departmentsById.get(id);
        if (remote) {
            return remote.name;
        }
        return '-';
    }

    function resolveCourseName(note) {
        return (note.course || '').toString();
    }

    function resolveTopicName(note) {
        return (note.topic || '').toString();
    }

    function getAllNotes() {
        if (Array.isArray(window.NOTBUL_NOTES)) {
            return window.NOTBUL_NOTES;
        }
        return [];
    }

    function getUniqueOptions(values) {
        return [...new Set(values.map((value) => value.trim()).filter(Boolean))].sort((a, b) => a.localeCompare(b, 'tr'));
    }

    function populateDatalist(datalist, values) {
        if (!datalist) {
            return;
        }
        datalist.innerHTML = values.map((value) => `<option value="${escapeHtml(value)}"></option>`).join('');
    }

    function populateSelect(select, items, placeholder, keepCurrent) {
        if (!select) {
            return;
        }

        const current = keepCurrent ? (select.dataset.selected ?? select.value) : '';
        const preserveUnknown = select.dataset.selected && ['university', 'department'].includes(select.dataset.level)
            && select.closest('[data-hierarchy-group]')?.dataset.optionsScope !== 'notes';
        if (preserveUnknown && !items.some((item) => item.id === current)) {
            items = [...items, { id: current, name: `${current} (mevcut seçim)` }];
        }
        delete select.dataset.selected;
        const options = [`<option value="">${escapeHtml(placeholder)}</option>`]
            .concat(items.map((item) => `<option value="${escapeHtml(item.id)}">${escapeHtml(item.name)}</option>`));

        select.innerHTML = options.join('');

        if (keepCurrent && current && items.some((item) => item.id === current)) {
            select.value = current;
        }
    }
    function initHierarchyGroups() {
        document.querySelectorAll('[data-hierarchy-group]').forEach((group) => {
            initHierarchyGroup(group);
        });
    }

    function initHierarchyGroup(group) {
        initPublicFilterGroup(group);
    }

    function initPublicFilterGroup(group) {
        const university = group.querySelector('select[data-level="university"]');
        const departmentType = group.querySelector('select[data-level="department-type"]');
        const department = group.querySelector('select[data-level="department"]');
        const classSelect = group.querySelector('select[data-level="class"]');
        const courseSelect = group.querySelector('select[data-level="course"]');
        const topicSelect = group.querySelector('select[data-level="topic"]');
        const courseInput = group.querySelector('input[data-level="course-input"]');
        const topicInput = group.querySelector('input[data-level="topic-input"]');
        const courseDatalist = group.querySelector('#uploadCourseList');
        const topicDatalist = group.querySelector('#uploadTopicList');
        const allNotes = getAllNotes();
        const useNotesScopedOptions = group.dataset.optionsScope === 'notes' && allNotes.length > 0;
        const departmentTypeOptions = [
            { id: 'onlisans', name: 'Önlisans' },
            { id: 'lisans', name: 'Lisans' }
        ];

        const getAvailableIds = (notes, key) => new Set(
            notes
                .map((note) => (note[key] || '').toString().trim())
                .filter(Boolean)
        );

        const refreshUniversities = () => {
            if (!university) {
                return;
            }

            if (!useNotesScopedOptions) {
                populateSelect(university, REMOTE.universities, university.dataset.placeholder || 'Seçiniz', true);
                return;
            }

            const availableUniversityIds = getAvailableIds(allNotes, 'universityId');
            const missingUniversities = [...availableUniversityIds]
                .filter((id) => !REMOTE.universitiesById.has(id))
                .map((id) => ({ id, name: id }));
            const list = REMOTE.universities
                .filter((item) => availableUniversityIds.has(item.id))
                .concat(missingUniversities)
                .sort((a, b) => a.name.localeCompare(b.name, 'tr'));

            populateSelect(university, list, university.dataset.placeholder || 'Seçiniz', true);
        };

        const refreshDepartmentTypes = () => {
            if (!departmentType) {
                return;
            }

            if (!useNotesScopedOptions) {
                populateSelect(
                    departmentType,
                    departmentTypeOptions,
                    departmentType.dataset.placeholder || 'Seçiniz',
                    true
                );
                return;
            }

            const selectedUniversity = university ? university.value : '';
            const scopedNotes = allNotes.filter((note) => {
                if (selectedUniversity && note.universityId !== selectedUniversity) {
                    return false;
                }
                return true;
            });
            const availableDepartmentTypes = getAvailableIds(scopedNotes, 'departmentType');
            const list = departmentTypeOptions.filter((item) => availableDepartmentTypes.has(item.id));

            populateSelect(
                departmentType,
                list,
                departmentType.dataset.placeholder || 'Seçiniz',
                true
            );
        };

        populateSelect(classSelect, CLASS_OPTIONS, classSelect?.dataset.placeholder || 'Seçiniz', true);

        const refreshDepartments = () => {
            if (!department) {
                return;
            }

            const selectedType = departmentType ? departmentType.value : '';
            const placeholder = selectedType
                ? (department.dataset.placeholder || 'Seçiniz')
                : 'Önce program türü seçiniz';

            if (!selectedType) {
                populateSelect(department, [], placeholder, true);
                return;
            }

            if (!useNotesScopedOptions) {
                const list = REMOTE.departmentsByType[selectedType] || [];
                populateSelect(department, list, placeholder, true);
                return;
            }

            const selectedUniversity = university ? university.value : '';
            const scopedNotes = allNotes.filter((note) => {
                if (note.departmentType !== selectedType) {
                    return false;
                }
                if (selectedUniversity && note.universityId !== selectedUniversity) {
                    return false;
                }
                return true;
            });
            const availableDepartmentIds = getAvailableIds(scopedNotes, 'departmentId');
            const baseList = REMOTE.departmentsByType[selectedType] || [];
            const missingDepartments = [...availableDepartmentIds]
                .filter((id) => !REMOTE.departmentsById.has(id))
                .map((id) => ({ id, name: id }));
            const list = baseList
                .filter((item) => availableDepartmentIds.has(item.id))
                .concat(missingDepartments)
                .sort((a, b) => a.name.localeCompare(b.name, 'tr'));

            populateSelect(department, list, placeholder, true);
        };

        const getScopedNotes = () => {
            const selectedUniversity = university ? university.value : '';
            const selectedDepartmentType = departmentType ? departmentType.value : '';
            const selectedDepartment = department ? department.value : '';
            const selectedClass = classSelect ? classSelect.value : '';

            return getAllNotes().filter((note) => {
                if (selectedUniversity && note.universityId !== selectedUniversity) {
                    return false;
                }
                if (selectedDepartmentType && note.departmentType !== selectedDepartmentType) {
                    return false;
                }
                if (selectedDepartment && note.departmentId !== selectedDepartment) {
                    return false;
                }
                if (selectedClass && note.classId !== selectedClass) {
                    return false;
                }
                return true;
            });
        };

        const refreshCourse = () => {
            const notes = getScopedNotes();
            const courseValues = getUniqueOptions(notes.map((note) => resolveCourseName(note)));

            if (!courseSelect && !courseInput) {
                return;
            }

            if (courseSelect) {
                const list = courseValues.map((value) => ({ id: value, name: value }));
                populateSelect(courseSelect, list, courseSelect.dataset.placeholder || 'Seçiniz', true);
            }
            if (courseInput) {
                populateDatalist(courseDatalist, courseValues);
            }
        };

        const refreshTopic = () => {
            const selectedCourse = courseSelect ? courseSelect.value : (courseInput ? courseInput.value : '');
            let notes = getScopedNotes();

            if (selectedCourse) {
                notes = notes.filter((note) => normalize(resolveCourseName(note)) === normalize(selectedCourse));
            }

            const topicValues = getUniqueOptions(notes.map((note) => resolveTopicName(note)));
            if (!topicSelect && !topicInput) {
                return;
            }

            if (topicSelect) {
                const list = topicValues.map((value) => ({ id: value, name: value }));
                populateSelect(topicSelect, list, topicSelect.dataset.placeholder || 'Seçiniz', true);
            }
            if (topicInput) {
                populateDatalist(topicDatalist, topicValues);
            }
        };

        refreshUniversities();
        refreshDepartmentTypes();
        refreshDepartments();
        refreshCourse();
        refreshTopic();

        departmentType?.addEventListener('change', () => {
            refreshDepartments();
            refreshCourse();
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        university?.addEventListener('change', () => {
            refreshDepartmentTypes();
            refreshDepartments();
            refreshCourse();
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        department?.addEventListener('change', () => {
            refreshCourse();
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        classSelect?.addEventListener('change', () => {
            refreshCourse();
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        courseSelect?.addEventListener('change', () => {
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        courseInput?.addEventListener('input', () => {
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        topicSelect?.addEventListener('change', () => {
            group.dispatchEvent(new Event('hierarchy:changed'));
        });
        topicInput?.addEventListener('input', () => {
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        group.addEventListener('hierarchy:restore', () => {
            refreshUniversities();
            refreshDepartmentTypes();
            populateSelect(classSelect, CLASS_OPTIONS, classSelect?.dataset.placeholder || 'Seçiniz', true);
            refreshDepartments();
            refreshCourse();
            refreshTopic();
        });

    }

    function collectFilters(form) {
        const formData = new FormData(form);
        return {
            query: normalize(formData.get('query')),
            universityId: normalize(formData.get('university_id')),
            facultyId: normalize(formData.get('faculty_id')),
            departmentType: normalize(formData.get('department_type')),
            departmentId: normalize(formData.get('department_id')),
            classId: normalize(formData.get('class_id')),
            course: normalize(formData.get('course') || formData.get('course_id')),
            topic: normalize(formData.get('topic') || formData.get('topic_id')),
            fileType: normalize(formData.get('file_type'))
        };
    }

    function matchesFilters(note, filters) {
        if (filters.universityId && note.universityId !== filters.universityId) {
            return false;
        }
        if (filters.facultyId && note.facultyId !== filters.facultyId) {
            return false;
        }
        if (filters.departmentType && note.departmentType !== filters.departmentType) {
            return false;
        }
        if (filters.departmentId && note.departmentId !== filters.departmentId) {
            return false;
        }
        if (filters.classId && note.classId !== filters.classId) {
            return false;
        }
        if (filters.course && normalize(resolveCourseName(note)) !== filters.course) {
            return false;
        }
        if (filters.topic && normalize(resolveTopicName(note)) !== filters.topic) {
            return false;
        }
        if (filters.fileType && note.fileType !== filters.fileType) {
            return false;
        }

        if (filters.query) {
            const searchable = normalizeSearch([
                note.title,
                note.description,
                (note.tags || []).join(' '),
                resolveCourseName(note),
                resolveTopicName(note),
                resolveDepartmentName(note.departmentId),
                resolveUniversityName(note.universityId)
            ].join(' '));

            if (!normalizeSearch(filters.query).split(/\s+/).filter(Boolean).every((word) => searchable.includes(word))) {
                return false;
            }
        }

        return true;
    }

    function filterNotes(filters, notes = getAllNotes()) {
        return notes.filter((note) => matchesFilters(note, filters));
    }

    function shortenText(value, maxLength = 80) {
        const text = (value || '').toString().trim();
        if (!text) {
            return 'Açıklama eklenmemiş.';
        }
        if (text.length <= maxLength) {
            return text;
        }
        return `${text.slice(0, maxLength)}...`;
    }

    function noteCardTemplate(note) {
        const tagHtml = (note.tags || [])
            .slice(0, 2)
            .map((tag) => `<span class="badge bg-light text-secondary fw-normal">#${escapeHtml(tag)}</span>`)
            .join('');
        const course = resolveCourseName(note) || '-';
        const ratingHtml = ratingSummaryTemplate(note);
        const returnTo = `${window.location.pathname.split('/').pop() || 'index.php'}${window.location.search}`;

        return `
            <article class="col-sm-6 col-xl-4">
                <div class="note-card card shadow-sm border-0">
                    <div class="card-body">
                        <h3 class="h6 mb-2">${escapeHtml(note.title)}</h3>
                        <p class="text-secondary mb-3 small" style="height: 3em; overflow: hidden;">
                            ${escapeHtml(shortenText(note.description))}
                        </p>
                        <p class="note-card-context small text-secondary mb-2">${escapeHtml(resolveUniversityName(note.universityId))} · ${escapeHtml(resolveDepartmentName(note.departmentId))} · ${escapeHtml(note.fileType === 'image' ? 'Görsel' : (note.fileType || '').toUpperCase())}</p>
                        <div class="note-tags mb-3">${tagHtml}</div>
                        <div class="note-card-footer d-flex justify-content-between align-items-center gap-3">
                            <div class="small">
                                <div class="fw-bold text-dark">${escapeHtml(note.uploader || '-')}</div>
                                <div class="text-secondary">${escapeHtml(course)}</div>
                            </div>
                            <div class="note-card-actions d-flex align-items-center gap-2 ms-auto">
                                ${ratingHtml}
                                <a href="note-detail.php?id=${note.id}&amp;return_to=${escapeHtml(encodeURIComponent(returnTo))}" class="btn btn-sm btn-primary" aria-label="${escapeHtml(note.title)} notunu incele">Detay</a>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        `;
    }

    function renderGrid(gridElement, notes, emptyMessage) {
        if (!gridElement) {
            return;
        }

        if (!notes.length) {
            gridElement.innerHTML = `<div class="col-12"><div class="empty-state">${escapeHtml(emptyMessage)}</div></div>`;
            return;
        }

        gridElement.innerHTML = notes.map((note) => noteCardTemplate(note)).join('');
    }
    function initHomePage() {
        const form = document.getElementById('homeFilterForm');
        if (!form) {
            return;
        }

        const popularGrid = document.getElementById('popularNotesGrid');
        const latestGrid = document.getElementById('latestNotesGrid');
        const latestSection = latestGrid?.closest('section');
        const primaryTitle = document.getElementById('homePrimaryPanelTitle');
        const resultCount = document.getElementById('homeResultCount');
        const resultHint = document.getElementById('homeResultHint');
        const queryInput = document.getElementById('homeQuery');

        const hasActiveSearch = (filters) => Object.values(filters).some((value) => value !== '');

        const render = () => {
            const filters = collectFilters(form);
            const filtered = filterNotes(filters);
            const searchActive = hasActiveSearch(filters);
            const params = filterParams(form, queryInput);
            syncUrl(params);

            const notesToShow = searchActive
                ? sortNotes(filtered, 'relevance', filters.query).slice(0, 6)
                : [...getAllNotes()]
                    .sort((a, b) => {
                        const downloadDiff = (b.downloads || 0) - (a.downloads || 0);
                        if (downloadDiff !== 0) {
                            return downloadDiff;
                        }
                        return new Date(b.createdAt) - new Date(a.createdAt);
                    })
                    .slice(0, 6);

            renderGrid(
                popularGrid,
                notesToShow,
                searchActive ? 'Arama kriterlerine uygun not bulunamadı.' : 'Henüz popüler not bulunmuyor.'
            );

            if (latestSection) latestSection.hidden = searchActive;
            if (!searchActive) renderGrid(latestGrid, sortNotes(getAllNotes(), 'newest').slice(0, 6), 'Henüz not yüklenmemiş.');
            document.querySelectorAll('[data-home-results-link]').forEach((link) => {
                const linkParams = new URLSearchParams(params);
                linkParams.set('sort', searchActive ? 'relevance' : (link.dataset.sort || 'newest'));
                link.href = searchLink(linkParams);
            });

            if (primaryTitle) {
                primaryTitle.textContent = searchActive ? 'Arama Sonuçları' : 'Popüler Notlar';
            }

            if (resultCount) {
                resultCount.textContent = formatNumber(filtered.length);
            }
            if (resultHint) resultHint.textContent = searchActive && filtered.length > 6
                ? ' · İlk 6 not gösteriliyor. Tüm sonuçları açabilirsiniz.' : '';
            if (searchActive && !filtered.length && popularGrid) {
                popularGrid.innerHTML = '<div class="col-12"><div class="empty-state">Aramanıza uygun not bulunamadı. Daha az kelimeyle aramayı veya filtreleri temizlemeyi deneyin. <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-reset-search>Filtreleri temizle</button></div></div>';
            }
        };

        form.addEventListener('input', render);
        form.addEventListener('change', render);
        form.addEventListener('hierarchy:changed', render);
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            window.location.assign(searchLink(filterParams(form, queryInput)));
        });
        const clear = () => { resetFilters(form, queryInput); render(); };
        form.querySelector('[data-reset-search]')?.addEventListener('click', clear);
        popularGrid?.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('[data-reset-search]')) clear();
        });
        window.addEventListener('popstate', () => {
            restoreFilterParams(form, queryInput);
            form.dispatchEvent(new Event('hierarchy:restore'));
            render();
        });
        render();
    }

    function initTagInputs() {
        document.querySelectorAll('[data-tag-input]').forEach((container) => {
            const chipsContainer = container.querySelector('[data-tag-chips]');
            const textField = container.querySelector('[data-tag-field]');
            const hiddenField = container.querySelector('[data-tag-hidden]');

            if (!chipsContainer || !textField || !hiddenField) {
                return;
            }

            let tags = [];

            const normalizeTag = (rawValue) => {
                return (rawValue || '')
                    .toString()
                    .trim()
                    .toLocaleLowerCase('tr-TR')
                    .replace(/\s+/g, '-')
                    .replace(/[^0-9a-zçğıöşü-]/g, '')
                    .replace(/-+/g, '-')
                    .replace(/^-+|-+$/g, '');
            };

            const sync = () => {
                hiddenField.value = tags.join(',');
                chipsContainer.innerHTML = tags.map((tag) => {
                    const safeTag = escapeHtml(tag);
                    return `<span class="tag-chip">${safeTag}<button type="button" data-remove-tag="${safeTag}" aria-label="${safeTag} etiketini kaldır">&times;</button></span>`;
                }).join('');
            };

            const addTag = (rawValue) => {
                const cleanValue = normalizeTag(rawValue);
                if (!cleanValue || tags.includes(cleanValue) || tags.length >= 12) {
                    return;
                }
                tags.push(cleanValue);
                sync();
            };

            textField.addEventListener('keydown', (event) => {
                if (event.key !== 'Enter' && event.key !== ',') {
                    return;
                }

                event.preventDefault();
                addTag(textField.value);
                textField.value = '';
            });

            textField.addEventListener('blur', () => {
                if (!textField.value) {
                    return;
                }
                addTag(textField.value);
                textField.value = '';
            });

            chipsContainer.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLButtonElement)) {
                    return;
                }
                const tagValue = target.dataset.removeTag;
                if (!tagValue) {
                    return;
                }
                tags = tags.filter((tag) => tag !== normalizeTag(tagValue));
                sync();
            });

            container.addEventListener('tag:clear', () => {
                tags = [];
                textField.value = '';
                sync();
            });

            tags = [...new Set((hiddenField.value || '').split(',').map(normalizeTag).filter(Boolean))].slice(0, 12);
            sync();
        });
    }

    function initUploadPage() {
        const uploadForm = document.getElementById('uploadForm');
        if (!uploadForm) {
            return;
        }

        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('noteFile');
        const fileList = document.getElementById('fileList');
        const notice = document.getElementById('uploadNotice');
        const pickFileButton = document.getElementById('pickFileButton');

        const configuredMaxMb = Number.parseInt(uploadForm.dataset.maxUploadMb || '25', 10);
        const maxUploadMb = Number.isFinite(configuredMaxMb) && configuredMaxMb > 0 ? configuredMaxMb : 25;
        const maxBytes = maxUploadMb * 1024 * 1024;
        const acceptedExtensions = new Set(['pdf', 'docx', 'pptx', 'png', 'jpg', 'jpeg', 'webp']);
        const showNotice = (message, type) => {
            if (!notice) {
                return;
            }

            notice.classList.remove('d-none', 'alert-success', 'alert-danger', 'alert-info');
            notice.classList.add(`alert-${type}`);
            notice.textContent = message;
        };

        const clearNotice = () => {
            if (!notice) {
                return;
            }
            notice.classList.add('d-none');
            notice.textContent = '';
        };

        const renderSelectedFile = (file) => {
            if (!fileList) {
                return;
            }
            fileList.innerHTML = `
                <div class="file-item"><strong>${escapeHtml(file.name)}</strong></div>
                <div class="file-item">Boyut: ${formatNumber(Math.ceil(file.size / 1024))} KB</div>
                <button type="button" class="btn btn-sm btn-outline-secondary mt-2" data-clear-file>Dosyayı kaldır</button>
            `;
        };

        const validateFile = (file) => {
            const errors = [];
            const extension = normalize(file.name.split('.').pop());

            if (!acceptedExtensions.has(extension)) {
                errors.push('Desteklenmeyen dosya uzantısı.');
            }

            if (file.size > maxBytes) {
                errors.push(`Dosya ${maxUploadMb} MB limitini aşıyor.`);
            }

            return errors;
        };

        const handleSelectedFile = (file) => {
            if (!file) {
                return;
            }

            const errors = validateFile(file);
            if (errors.length) {
                showNotice(errors.join(' '), 'danger');
                if (fileInput) {
                    fileInput.value = '';
                }
                if (fileList) {
                    fileList.innerHTML = '';
                }
                return;
            }

            renderSelectedFile(file);
            showNotice('Dosya seçildi. Yüklemek için "Dosyayı Yükle" butonuna tıklayın.', 'success');
        };

        const preventDefaults = (event) => {
            event.preventDefault();
            event.stopPropagation();
        };

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach((eventName) => {
            dropZone?.addEventListener(eventName, preventDefaults);
        });

        ['dragenter', 'dragover'].forEach((eventName) => {
            dropZone?.addEventListener(eventName, () => dropZone.classList.add('drag-over'));
        });

        ['dragleave', 'drop'].forEach((eventName) => {
            dropZone?.addEventListener(eventName, () => dropZone.classList.remove('drag-over'));
        });

        dropZone?.addEventListener('drop', (event) => {
            const transfer = event.dataTransfer;
            if (!transfer || !transfer.files || !transfer.files.length) {
                return;
            }
            if (fileInput) {
                fileInput.files = transfer.files;
            }
            handleSelectedFile(transfer.files[0]);
        });

        pickFileButton?.addEventListener('click', () => fileInput?.click());
        fileInput?.addEventListener('change', () => {
            clearNotice();
            handleSelectedFile(fileInput.files?.[0]);
        });
        fileList?.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('[data-clear-file]')) {
                fileInput.value = '';
                fileList.innerHTML = '';
                clearNotice();
                pickFileButton?.focus();
            }
        });

        const submitButton = uploadForm.querySelector('button[type="submit"]');
        const submitLabel = submitButton?.textContent || 'Dosyayı Yükle';
        const restoreSubmit = () => {
            delete uploadForm.dataset.submitting;
            uploadForm.removeAttribute('aria-busy');
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.textContent = submitLabel;
            }
        };
        window.addEventListener('pageshow', restoreSubmit);

        uploadForm.addEventListener('submit', (event) => {
            if (uploadForm.dataset.submitting === 'true') {
                event.preventDefault();
                return;
            }
            const selectedFile = fileInput?.files?.[0];
            if (!selectedFile) {
                event.preventDefault();
                showNotice('Lütfen önce bir dosya seçin.', 'danger');
                return;
            }

            const formData = new FormData(uploadForm);
            const courseValue = (formData.get('course') || '').toString().trim();
            if (!courseValue) {
                event.preventDefault();
                showNotice('Ders alanı zorunlu. Lütfen ders bilgisini girin.', 'danger');
                return;
            }
            uploadForm.dataset.submitting = 'true';
            uploadForm.setAttribute('aria-busy', 'true');
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Yükleniyor…';
            }
            showNotice('Dosyanız yükleniyor. Yükleme bitince notunuza yönlendirileceksiniz.', 'info');
        });
    }
    function initNoteDetailPage() {
        const form = document.getElementById('commentForm');
        const commentsList = document.getElementById('commentsList');

        if (!form || !commentsList) {
            return;
        }

        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const authorField = document.getElementById('commentAuthor');
            const ratingField = document.getElementById('commentRating');
            const textField = document.getElementById('commentText');

            if (!(authorField instanceof HTMLInputElement) || !(ratingField instanceof HTMLSelectElement) || !(textField instanceof HTMLTextAreaElement)) {
                return;
            }

            const author = authorField.value.trim();
            const rating = normalize(ratingField.value);
            const text = textField.value.trim();

            if (!author || !rating || !text) {
                return;
            }

            const item = document.createElement('article');
            item.className = 'comment-item';
            item.innerHTML = `
                <header><strong>${escapeHtml(author)}</strong> ${ratingStarsTemplate(rating)}</header>
                <p class="mb-0">${escapeHtml(text)}</p>
            `;

            commentsList.prepend(item);
            form.reset();
        });
    }

    function initSearchPage() {
        const form = document.getElementById('searchFilterForm');
        const queryInput = document.getElementById('searchQuery');
        const sortSelect = document.getElementById('searchSort');
        const resultsContainer = document.getElementById('searchResults');
        const pagination = document.getElementById('searchPagination');
        const countElement = document.getElementById('searchResultCount');
        const status = document.getElementById('searchStatus');
        const resultsPanel = document.getElementById('searchResultsPanel');
        const filterPanel = document.getElementById('searchFilterPanel');
        if (!form || !resultsContainer || !pagination || !countElement) return;

        const params = new URLSearchParams(window.location.search);
        let similarTo = params.get('similar_to') || '';
        const validSorts = new Set(['relevance', 'newest', 'rating', 'downloads']);
        if (sortSelect) sortSelect.value = validSorts.has(params.get('sort')) ? params.get('sort') : 'relevance';
        const state = {
            currentPage: Math.max(1, parseInt(params.get('page'), 10) || 1),
            pageSize: 10,
            filtered: []
        };
        const desktop = window.matchMedia('(min-width: 992px)');
        const syncFilterPanel = () => { if (filterPanel) filterPanel.open = desktop.matches; };
        syncFilterPanel();
        desktop.addEventListener?.('change', syncFilterPanel);

        const currentParams = () => filterParams(form, queryInput, sortSelect?.value || 'relevance', state.currentPage, similarTo);
        const drawPagination = () => {
            const totalPages = Math.ceil(state.filtered.length / state.pageSize);
            if (totalPages <= 1) { pagination.innerHTML = ''; return; }
            const pages = [...new Set([1, totalPages, state.currentPage - 1, state.currentPage, state.currentPage + 1])]
                .filter((page) => page >= 1 && page <= totalPages).sort((a, b) => a - b);
            const button = (page, label, disabled = false) => `<li class="page-item ${page === state.currentPage && !disabled ? 'active' : ''} ${disabled ? 'disabled' : ''}"><button type="button" class="page-link" data-page="${page}" ${disabled ? 'disabled' : ''} ${page === state.currentPage && !disabled ? 'aria-current="page"' : ''} aria-label="${typeof label === 'number' ? `Sayfa ${page}` : label}">${label}</button></li>`;
            const items = [button(state.currentPage - 1, 'Önceki', state.currentPage === 1)];
            let previous = 0;
            pages.forEach((page) => {
                if (previous && page - previous > 1) items.push('<li class="page-item disabled"><span class="page-link" aria-hidden="true">…</span></li>');
                items.push(button(page, page));
                previous = page;
            });
            items.push(button(state.currentPage + 1, 'Sonraki', state.currentPage === totalPages));
            pagination.innerHTML = items.join('');
        };
        const drawResults = (push = false) => {
            const totalPages = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));
            state.currentPage = Math.min(state.currentPage, totalPages);
            syncUrl(currentParams(), push);
            const start = (state.currentPage - 1) * state.pageSize;
            const pageItems = state.filtered.slice(start, start + state.pageSize);
            const returnTo = `search.php${window.location.search}`;
            countElement.textContent = formatNumber(state.filtered.length);
            if (status) status.textContent = pageItems.length
                ? `${formatNumber(state.filtered.length)} not bulundu. ${start + 1}–${start + pageItems.length} arası gösteriliyor.${similarTo ? ' Benzer notlar listeleniyor.' : ''}`
                : 'Sonuç bulunamadı. Daha az kelime veya daha geniş filtrelerle aramayı deneyin.';
            if (!pageItems.length) {
                resultsContainer.innerHTML = '<div class="empty-state">Aramanıza uygun not bulunamadı. Farklı kelimeler kullanabilir veya filtreleri temizleyebilirsiniz.<br><button type="button" class="btn btn-sm btn-outline-primary mt-2" data-reset-search>Aramayı ve filtreleri temizle</button></div>';
            } else {
                resultsContainer.innerHTML = pageItems.map((note) => {
                    const context = [resolveUniversityName(note.universityId), resolveDepartmentName(note.departmentId), resolveCourseName(note), resolveTopicName(note)]
                        .filter((value) => value && value !== '-');
                    const fileType = note.fileType === 'image' ? 'Görsel' : (note.fileType || '').toUpperCase();
                    return `<article class="result-item">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="flex-grow-1" style="min-width: 0">
                                <h3 class="h5 mb-1">${escapeHtml(note.title)}</h3>
                                <p class="mb-2 text-secondary">${escapeHtml(shortenText(note.description, 240))}</p>
                            </div>
                            <a href="note-detail.php?id=${note.id}&amp;return_to=${escapeHtml(encodeURIComponent(returnTo))}" class="btn btn-sm btn-outline-primary flex-shrink-0" aria-label="${escapeHtml(note.title)} notunu incele">Detay</a>
                        </div>
                        <div class="result-footer">
                            <div class="d-flex flex-wrap gap-2">${context.map((value) => `<span class="note-tag">${escapeHtml(value)}</span>`).join('')}<span class="note-tag">${escapeHtml(fileType)}</span></div>
                            <div class="result-stats text-secondary small">${ratingSummaryTemplate(note, true)}<span>${formatDate(note.createdAt)} · ${formatNumber(note.downloads)} indirme</span></div>
                        </div>
                    </article>`;
                }).join('');
            }
            drawPagination();
        };
        const apply = (resetPage = true) => {
            if (resetPage) state.currentPage = 1;
            const filters = collectFilters(form);
            filters.query = normalize(queryInput?.value);
            let notes = filterNotes(filters);
            const similarNote = getAllNotes().find((note) => String(note.id) === similarTo);
            if (similarNote) {
                const tags = (similarNote.tags || []).map(normalizeSearch);
                const words = normalizeSearch(similarNote.title).split(/\s+/).filter((word) => word.length > 2);
                const score = (note) => (note.tags || []).map(normalizeSearch).filter((tag) => tags.includes(tag)).length * 10
                    + normalizeSearch(note.title).split(/\s+/).filter((word) => word.length > 2 && words.includes(word)).length * 2
                    + (similarNote.course && normalizeSearch(note.course) === normalizeSearch(similarNote.course) ? 5 : 0);
                notes = notes.filter((note) => note.id !== similarNote.id && score(note) > 0);
                state.filtered = (sortSelect?.value || 'relevance') === 'relevance'
                    ? [...notes].sort((a, b) => score(b) - score(a) || new Date(b.createdAt) - new Date(a.createdAt))
                    : sortNotes(notes, sortSelect.value, filters.query);
            } else {
                similarTo = '';
                state.filtered = sortNotes(notes, sortSelect?.value || 'relevance', filters.query);
            }
            drawResults();
        };
        const clear = () => {
            similarTo = '';
            resetFilters(form, queryInput);
            if (sortSelect) sortSelect.value = 'relevance';
            apply();
            queryInput?.focus();
        };
        let timer;
        const schedule = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(() => apply(), 150);
        };
        form.addEventListener('change', () => apply());
        form.addEventListener('hierarchy:changed', () => apply());
        form.addEventListener('submit', (event) => { event.preventDefault(); apply(); });
        queryInput?.addEventListener('input', schedule);
        sortSelect?.addEventListener('change', () => apply());
        form.querySelector('[data-reset-search]')?.addEventListener('click', clear);
        resultsContainer.addEventListener('click', (event) => {
            if (event.target instanceof Element && event.target.closest('[data-reset-search]')) clear();
        });
        pagination.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('button[data-page]') : null;
            if (!target || target.disabled) return;
            const page = Number(target.dataset.page);
            if (!Number.isInteger(page) || page < 1 || page > Math.ceil(state.filtered.length / state.pageSize)) return;
            window.clearTimeout(timer);
            state.currentPage = page;
            drawResults(true);
            resultsPanel?.scrollIntoView({ block: 'start' });
            resultsPanel?.focus({ preventScroll: true });
        });
        window.addEventListener('popstate', () => {
            window.clearTimeout(timer);
            restoreFilterParams(form, queryInput);
            form.dispatchEvent(new Event('hierarchy:restore'));
            const restored = new URLSearchParams(window.location.search);
            similarTo = restored.get('similar_to') || '';
            if (sortSelect) sortSelect.value = validSorts.has(restored.get('sort')) ? restored.get('sort') : 'relevance';
            state.currentPage = Math.max(1, parseInt(restored.get('page'), 10) || 1);
            apply(false);
        });
        apply(false);
    }

    function copyTextToClipboard(text) {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }

        return new Promise((resolve, reject) => {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            textarea.setAttribute('readonly', '');
            textarea.style.position = 'fixed';
            textarea.style.inset = '0 auto auto 0';
            textarea.style.opacity = '0';
            document.body.appendChild(textarea);
            textarea.select();

            try {
                const copied = document.execCommand('copy');
                document.body.removeChild(textarea);
                copied ? resolve() : reject(new Error('Copy command failed'));
            } catch (error) {
                document.body.removeChild(textarea);
                reject(error);
            }
        });
    }

    function initAdminCopyTools() {
        const buttons = document.querySelectorAll('[data-copy-value]');
        if (!buttons.length) {
            return;
        }

        buttons.forEach((button) => {
            button.addEventListener('click', async () => {
                const value = button.dataset.copyValue || '';
                if (!value.trim()) {
                    return;
                }

                if (!button.dataset.copyOriginalHtml) {
                    button.dataset.copyOriginalHtml = button.innerHTML;
                }

                try {
                    await copyTextToClipboard(value);
                    window.clearTimeout(Number(button.dataset.copyTimer || 0));
                    button.classList.add('is-copied');

                    if (button.classList.contains('admin-copy-btn-icon')) {
                        button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i><span class="visually-hidden">Kopyalandı</span>';
                    } else {
                        button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i><span>Kopyalandı</span>';
                    }

                    const timer = window.setTimeout(() => {
                        button.classList.remove('is-copied');
                        button.innerHTML = button.dataset.copyOriginalHtml || '';
                    }, 1400);
                    button.dataset.copyTimer = String(timer);
                } catch (error) {
                    button.classList.add('is-invalid');
                    window.setTimeout(() => button.classList.remove('is-invalid'), 1400);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        await loadRemoteFilterData();
        const filterForm = document.getElementById('homeFilterForm') || document.getElementById('searchFilterForm');
        if (filterForm) restoreFilterParams(filterForm, document.getElementById('homeQuery') || document.getElementById('searchQuery'));
        initHierarchyGroups();
        initTagInputs();

        const page = document.body.dataset.page;
        if (page === 'home') {
            initHomePage();
        }
        if (page === 'upload') {
            initUploadPage();
        }
        if (page === 'detail') {
            initNoteDetailPage();
        }
        if (page === 'search') {
            initSearchPage();
        }
        if (page === 'admin') {
            initAdminCopyTools();
        }
    });
})();
