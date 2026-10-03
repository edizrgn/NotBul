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
            if (!group.hasAttribute('data-server-filters')) initHierarchyGroup(group);
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

    function initHomePage() {
        initServerNoteSearch(true);
    }

    function initSearchPage() {
        initServerNoteSearch(false);
    }

    function initServerNoteSearch(home) {
        const form = document.getElementById(home ? 'homeFilterForm' : 'searchFilterForm');
        if (!form) return;
        const queryInput = document.getElementById(home ? 'homeQuery' : 'searchQuery');
        const sortSelect = home ? null : document.getElementById('searchSort');
        const results = document.getElementById(home ? 'popularNotesGrid' : 'searchResults');
        const pagination = document.getElementById('searchPagination');
        const count = document.getElementById(home ? 'homeResultCount' : 'searchResultCount');
        const status = document.getElementById(home ? 'homeSearchStatus' : 'searchStatus');
        const panel = home ? results?.closest('.panel-card') : document.getElementById('searchResultsPanel');
        const hierarchy = ['university_id', 'department_type', 'department_id', 'class_id', 'course', 'topic'];
        let page = home ? 1 : Number(panel?.dataset.page || 1);
        let similarTo = form.elements.namedItem('similar_to')?.value || '';
        let timer;
        let controller;
        let generation = 0;
        let pendingOptions = false;
        if (!results || !count) return;

        if (!home) {
            const filters = document.getElementById('searchFilterPanel');
            const desktop = window.matchMedia('(min-width: 992px)');
            const syncPanel = () => { if (filters) filters.open = desktop.matches; };
            syncPanel();
            desktop.addEventListener?.('change', syncPanel);
        }
        const params = () => filterParams(form, queryInput, sortSelect?.value || '', page, similarTo === '0' ? '' : similarTo);
        const updateHomeLinks = (current, active) => {
            document.querySelectorAll('[data-home-results-link]').forEach((link) => {
                const linkParams = new URLSearchParams(current);
                linkParams.set('sort', active ? 'relevance' : link.dataset.sort);
                link.href = searchLink(linkParams);
            });
        };
        const stop = () => {
            window.clearTimeout(timer);
            controller?.abort();
            generation += 1;
        };
        const apply = async (push = false, focus = false) => {
            stop();
            const currentGeneration = generation;
            const current = params();
            const fetchParams = new URLSearchParams(current);
            fetchParams.set('format', home ? 'home' : 'json');
            if (pendingOptions) fetchParams.set('include_options', '1');
            controller = new AbortController();
            results.setAttribute('aria-busy', 'true');
            if (status) status.textContent = 'Notlar aranıyor…';
            try {
                const response = await fetch(searchLink(fetchParams), { signal: controller.signal, headers: { Accept: 'application/json' } });
                const data = await response.json();
                if (!response.ok) {
                    const error = new Error(data.error || 'Sonuçlar alınamadı. Lütfen tekrar deneyin.');
                    error.userMessage = true;
                    throw error;
                }
                if (currentGeneration !== generation) return;
                results.innerHTML = data.resultsHtml;
                if (pagination) pagination.innerHTML = data.paginationHtml;
                count.textContent = data.countLabel;
                if (status) status.textContent = home && !data.searchActive ? '' : data.status;
                page = data.page;
                const canonical = new URLSearchParams(data.queryString);
                similarTo = canonical.get('similar_to') || '';
                const similarField = form.elements.namedItem('similar_to');
                if (similarField) similarField.value = similarTo;
                Object.entries(data.options).forEach(([name, html]) => {
                    const field = form.elements.namedItem(name);
                    if (field instanceof HTMLSelectElement) field.innerHTML = html;
                });
                if (fetchParams.has('include_options')) pendingOptions = false;
                syncUrl(canonical, push);
                if (home) {
                    const title = document.getElementById('homePrimaryPanelTitle');
                    if (title) title.textContent = data.searchActive ? 'Arama Sonuçları' : 'Popüler Notlar';
                    const latest = document.getElementById('homeLatestSection');
                    if (latest) latest.hidden = data.searchActive;
                    const latestGrid = document.getElementById('latestNotesGrid');
                    if (!data.searchActive && latestGrid) latestGrid.innerHTML = data.latestHtml;
                    const hint = document.getElementById('homeResultHint');
                    if (hint) hint.textContent = data.searchActive && data.total > 6 ? ' · İlk 6 not gösteriliyor. Tüm sonuçları açabilirsiniz.' : '';
                    updateHomeLinks(canonical, data.searchActive);
                }
                if (focus) {
                    panel?.scrollIntoView({ block: 'start' });
                    panel?.focus({ preventScroll: true });
                }
            } catch (error) {
                if (error.name !== 'AbortError' && currentGeneration === generation && status) {
                    status.textContent = error.userMessage ? error.message : 'Sonuçlar alınamadı. Lütfen tekrar deneyin.';
                }
            } finally {
                if (currentGeneration === generation) results.removeAttribute('aria-busy');
            }
        };
        const changed = (event) => {
            const name = event.target.name;
            const level = hierarchy.indexOf(name);
            if (level >= 0) {
                hierarchy.slice(level + 1).forEach((field) => {
                    const input = form.elements.namedItem(field);
                    if (input) input.value = '';
                });
                pendingOptions = true;
            }
            page = 1;
            apply();
        };
        form.addEventListener('change', (event) => { if (event.target !== queryInput) changed(event); });
        sortSelect?.addEventListener('change', changed);
        queryInput?.addEventListener('input', () => {
            stop();
            results.removeAttribute('aria-busy');
            page = 1;
            timer = window.setTimeout(() => apply(), 500);
        });
        form.addEventListener('submit', (event) => {
            event.preventDefault();
            stop();
            page = 1;
            if (home) window.location.assign(searchLink(params()));
            else apply(true);
        });
        const clear = () => {
            stop();
            FILTER_NAMES.forEach((name) => {
                const field = form.elements.namedItem(name);
                if (field) field.value = '';
            });
            if (queryInput) queryInput.value = '';
            if (sortSelect) sortSelect.value = 'relevance';
            similarTo = '';
            page = 1;
            pendingOptions = true;
            apply();
            queryInput?.focus();
        };
        document.addEventListener('click', (event) => {
            const target = event.target instanceof Element ? event.target.closest('[data-reset-search], a[data-page]') : null;
            if (!target || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
            if (target.hasAttribute('data-reset-search')) {
                event.preventDefault();
                clear();
            } else if (pagination?.contains(target)) {
                event.preventDefault();
                page = Number(target.dataset.page);
                apply(true, true);
            }
        });
        window.addEventListener('popstate', () => {
            stop();
            const restored = new URLSearchParams(window.location.search);
            FILTER_NAMES.forEach((name) => {
                const field = form.elements.namedItem(name);
                if (!field) return;
                const value = restored.get(name) || '';
                if (field instanceof HTMLSelectElement && value && !Array.from(field.options).some((option) => option.value === value)) {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = value;
                    field.appendChild(option);
                }
                field.value = value;
            });
            if (queryInput) queryInput.value = restored.get('q') || restored.get('query') || '';
            if (sortSelect) sortSelect.value = restored.get('sort') || 'relevance';
            similarTo = restored.get('similar_to') || '';
            page = Math.max(1, Number(restored.get('page')) || 1);
            pendingOptions = true;
            apply();
        });
        syncUrl(params());
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

    function initNoteFileForms() {
        const forms = document.querySelectorAll('[data-note-file-form]');
        forms.forEach((form) => {
            const input = form.querySelector('input[type="file"]');
            const button = form.querySelector('button[type="submit"]');
            const status = form.querySelector('[data-file-form-status]');
            const originalLabel = button.textContent;
            let pending = false;
            const reset = () => {
                pending = false;
                button.disabled = false;
                button.textContent = originalLabel;
                form.removeAttribute('aria-busy');
                status.textContent = '';
            };
            const validate = () => {
                if (!input) return;
                input.setCustomValidity('');
                const file = input.files[0];
                if (!file) return;
                if (file.size < 1 || file.size > Number(form.dataset.maxBytes)) {
                    input.setCustomValidity('Dosya boş olmamalı ve 25 MB sınırını aşmamalı.');
                } else if (!/\.(pdf|docx|pptx|png|jpe?g|webp)$/i.test(file.name)) {
                    input.setCustomValidity('PDF, DOCX, PPTX, PNG, JPG veya WEBP seçin.');
                }
            };
            input?.addEventListener('change', validate);
            form.addEventListener('submit', (event) => {
                if (pending) {
                    event.preventDefault();
                    return;
                }
                validate();
                if (!form.reportValidity()) {
                    event.preventDefault();
                    return;
                }
                pending = true;
                button.disabled = true;
                button.textContent = form.dataset.pendingLabel;
                form.setAttribute('aria-busy', 'true');
                status.textContent = 'İşlem tamamlanana kadar bekleyin.';
            });
            window.addEventListener('pageshow', reset);
        });
    }

    document.addEventListener('DOMContentLoaded', async () => {
        initNoteFileForms();
        if (document.querySelector('[data-hierarchy-group]:not([data-server-filters])')) await loadRemoteFilterData();
        const filterForm = document.getElementById('homeFilterForm') || document.getElementById('searchFilterForm');
        if (filterForm && !filterForm.hasAttribute('data-server-filters')) restoreFilterParams(filterForm, document.getElementById('homeQuery') || document.getElementById('searchQuery'));
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
