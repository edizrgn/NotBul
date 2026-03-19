(() => {
    'use strict';

    const DATA = {
        universities: [
            { id: 'itu', name: 'İstanbul Teknik Üniversitesi' },
            { id: 'ytu', name: 'Yıldız Teknik Üniversitesi' },
            { id: 'metu', name: 'Orta Doğu Teknik Üniversitesi' }
        ],
        faculties: [
            { id: 'itu-muh', universityId: 'itu', name: 'Mühendislik Fakültesi' },
            { id: 'itu-fen', universityId: 'itu', name: 'Fen Edebiyat Fakültesi' },
            { id: 'ytu-muh', universityId: 'ytu', name: 'Mühendislik Fakültesi' },
            { id: 'metu-eng', universityId: 'metu', name: 'Mühendislik Fakültesi' }
        ],
        departments: [
            { id: 'itu-ceng', facultyId: 'itu-muh', name: 'Bilgisayar Mühendisliği' },
            { id: 'itu-ee', facultyId: 'itu-muh', name: 'Elektrik Mühendisliği' },
            { id: 'ytu-ceng', facultyId: 'ytu-muh', name: 'Bilgisayar Mühendisliği' },
            { id: 'ytu-math', facultyId: 'ytu-muh', name: 'Matematik Mühendisliği' },
            { id: 'metu-ceng', facultyId: 'metu-eng', name: 'Bilgisayar Mühendisliği' }
        ],
        classes: [
            { id: '1', name: '1. Sınıf' },
            { id: '2', name: '2. Sınıf' },
            { id: '3', name: '3. Sınıf' },
            { id: '4', name: '4. Sınıf' }
        ],
        courses: [
            { id: 'calc', departmentId: '14051', classId: '1', name: 'Calculus I' },
            { id: 'prog', departmentId: '12010', classId: '1', name: 'Programlamaya Giriş' },
            { id: 'data', departmentId: '12010', classId: '2', name: 'Veri Yapıları' },
            { id: 'algo', departmentId: '12010', classId: '2', name: 'Algorithms' },
            { id: 'db', departmentId: '12010', classId: '3', name: 'Veritabanı Sistemleri' },
            { id: 'signals', departmentId: '12644', classId: '3', name: 'Sayısal İşaret İşleme' },
            { id: 'net', departmentId: '12010', classId: '4', name: 'Bilgisayar Ağları' }
        ],
        topics: [
            { id: 'limits', courseId: 'calc', name: 'Limit ve Süreklilik' },
            { id: 'oop', courseId: 'prog', name: 'OOP Temelleri' },
            { id: 'trees', courseId: 'data', name: 'Ağaç ve Heap Yapıları' },
            { id: 'dp', courseId: 'algo', name: 'Dynamic Programming' },
            { id: 'normalization', courseId: 'db', name: 'Normalizasyon' },
            { id: 'fft', courseId: 'signals', name: 'Fourier ve FFT' },
            { id: 'routing', courseId: 'net', name: 'Routing Protokolleri' }
        ],
        notes: [
            {
                id: 1,
                title: 'Veri Yapıları Final Özet',
                description: 'Ağaçlar, hash tablolar ve sınavda çıkmış kritik soruların kısa özetleri.',
                uploader: 'Ahmet Yılmaz',
                universityId: '115069',
                facultyId: 'itu-muh',
                departmentId: '12010',
                departmentType: 'lisans',
                classId: '2',
                courseId: 'data',
                topicId: 'trees',
                tags: ['final', 'ağaç', 'hash'],
                views: 4982,
                downloads: 1364,
                fileType: 'pdf',
                createdAt: '2026-03-17'
            },
            {
                id: 2,
                title: 'Database Normalization Cheatsheet',
                description: '1NF-3NF örnekleri ve tablo ilişki modelleme adımları.',
                uploader: 'Elif C.',
                universityId: '126982',
                facultyId: 'ytu-muh',
                departmentId: '12010',
                departmentType: 'lisans',
                classId: '3',
                courseId: 'db',
                topicId: 'normalization',
                tags: ['sql', 'normalizasyon', 'cheatsheet'],
                views: 3110,
                downloads: 980,
                fileType: 'pdf',
                createdAt: '2026-03-18'
            },
            {
                id: 3,
                title: 'Algorithms Midterm Practice',
                description: 'Greedy, DP ve grafik problemleri için örnek çözüm seti.',
                uploader: 'Merve D.',
                universityId: '122571',
                facultyId: 'metu-eng',
                departmentId: '12010',
                departmentType: 'lisans',
                classId: '2',
                courseId: 'algo',
                topicId: 'dp',
                tags: ['algorithms', 'dp', 'midterm'],
                views: 4410,
                downloads: 1290,
                fileType: 'pdf',
                createdAt: '2026-03-16'
            },
            {
                id: 4,
                title: 'Programlamaya Giriş OOP Notları',
                description: 'Sınıf, nesne, kapsülleme ve kalıtım anlatım notları.',
                uploader: 'Sena K.',
                universityId: '115069',
                facultyId: 'itu-muh',
                departmentId: '12010',
                departmentType: 'lisans',
                classId: '1',
                courseId: 'prog',
                topicId: 'oop',
                tags: ['oop', 'php', 'başlangıç'],
                views: 2800,
                downloads: 760,
                fileType: 'docx',
                createdAt: '2026-03-13'
            },
            {
                id: 5,
                title: 'FFT Çıkış Soruları',
                description: 'Sayısal İşaret İşleme dersinden çıkmış final soruları ve çözüm akışları.',
                uploader: 'Burak T.',
                universityId: '115069',
                facultyId: 'itu-muh',
                departmentId: '12644',
                departmentType: 'lisans',
                classId: '3',
                courseId: 'signals',
                topicId: 'fft',
                tags: ['fft', 'sinyal', 'çıkmış-soru'],
                views: 2200,
                downloads: 640,
                fileType: 'pptx',
                createdAt: '2026-03-11'
            },
            {
                id: 6,
                title: 'Bilgisayar Ağları Routing Özet',
                description: 'OSPF, BGP ve paket yönlendirme mantığını tek dokümanda toplayan not.',
                uploader: 'Kaan U.',
                universityId: '126982',
                facultyId: 'ytu-muh',
                departmentId: '12010',
                departmentType: 'lisans',
                classId: '4',
                courseId: 'net',
                topicId: 'routing',
                tags: ['routing', 'network', 'final'],
                views: 3870,
                downloads: 1115,
                fileType: 'pdf',
                createdAt: '2026-03-19'
            },
            {
                id: 7,
                title: 'Calculus Limit Formülleri',
                description: 'Limit, süreklilik ve türev geçişleri için hızlı tekrar kartları.',
                uploader: 'Irmak S.',
                universityId: '126982',
                facultyId: 'ytu-muh',
                departmentId: '14051',
                departmentType: 'lisans',
                classId: '1',
                courseId: 'calc',
                topicId: 'limits',
                tags: ['limit', 'türev', 'vize'],
                views: 2080,
                downloads: 530,
                fileType: 'image',
                createdAt: '2026-03-15'
            },
            {
                id: 8,
                title: 'Veri Yapıları Çıkış Soru Arşivi',
                description: 'Son 5 yıl final soruları ve kısa cevap anahtarları.',
                uploader: 'Nisa P.',
                universityId: '115069',
                facultyId: 'itu-muh',
                departmentId: '12010',
                departmentType: 'lisans',
                classId: '2',
                courseId: 'data',
                topicId: 'trees',
                tags: ['çıkmış-soru', 'final', 'veri-yapıları'],
                views: 3560,
                downloads: 1212,
                fileType: 'pdf',
                createdAt: '2026-03-12'
            }
        ]
    };

    const REMOTE = {
        universities: [],
        universitiesById: new Map(),
        departmentsByType: {
            lisans: [],
            onlisans: []
        },
        departmentsById: new Map()
    };

    const LOOKUP = {
        facultyById: new Map(DATA.faculties.map((item) => [item.id, item])),
        departmentById: new Map(DATA.departments.map((item) => [item.id, item]))
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
            // Fallback: mevcut demo verisiyle çalışmaya devam et.
            REMOTE.universities = [...DATA.universities];
            REMOTE.universitiesById = new Map(REMOTE.universities.map((item) => [item.id, item]));
            REMOTE.departmentsByType = {
                lisans: [...DATA.departments],
                onlisans: []
            };
            REMOTE.departmentsById = new Map(REMOTE.departmentsByType.lisans.map((item) => [item.id, item]));
        }
    }

    function getUniversityByDepartmentId(departmentId) {
        const department = LOOKUP.departmentById.get(departmentId);
        if (!department) {
            return '';
        }

        const faculty = LOOKUP.facultyById.get(department.facultyId);
        return faculty ? faculty.universityId : '';
    }

    function normalize(value) {
        return (value || '').toString().trim().toLowerCase();
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
        return new Intl.DateTimeFormat('tr-TR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        }).format(date);
    }

    function formatNumber(numberValue) {
        return new Intl.NumberFormat('tr-TR').format(numberValue || 0);
    }

    function resolveLabel(items, id) {
        const item = items.find((entry) => entry.id === id);
        return item ? item.name : '-';
    }

    function resolveUniversityName(id) {
        const remote = REMOTE.universitiesById.get(id);
        if (remote) {
            return remote.name;
        }
        return resolveLabel(DATA.universities, id);
    }

    function resolveDepartmentName(id) {
        const remote = REMOTE.departmentsById.get(id);
        if (remote) {
            return remote.name;
        }
        return resolveLabel(DATA.departments, id);
    }

    function populateSelect(select, items, placeholder, keepCurrent) {
        if (!select) {
            return;
        }

        const current = keepCurrent ? select.value : '';
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
        if (group.dataset.filterSource === 'public') {
            initPublicFilterGroup(group);
            return;
        }

        const university = group.querySelector('select[data-level="university"]');
        const faculty = group.querySelector('select[data-level="faculty"]');
        const department = group.querySelector('select[data-level="department"]');
        const classSelect = group.querySelector('select[data-level="class"]');
        const course = group.querySelector('select[data-level="course"]');
        const topic = group.querySelector('select[data-level="topic"]');

        populateSelect(university, DATA.universities, university?.dataset.placeholder || 'Seçiniz', true);
        populateSelect(classSelect, DATA.classes, classSelect?.dataset.placeholder || 'Seçiniz', true);

        const refreshFaculty = () => {
            if (!faculty) {
                return;
            }

            const selectedUniversity = university ? university.value : '';
            const list = selectedUniversity
                ? DATA.faculties.filter((item) => item.universityId === selectedUniversity)
                : DATA.faculties;

            populateSelect(faculty, list, faculty.dataset.placeholder || 'Seçiniz', true);
        };

        const refreshDepartment = () => {
            if (!department) {
                return;
            }

            let list = DATA.departments;
            if (faculty && faculty.value) {
                list = list.filter((item) => item.facultyId === faculty.value);
            } else if (university && university.value) {
                const facultyIds = DATA.faculties
                    .filter((item) => item.universityId === university.value)
                    .map((item) => item.id);
                list = list.filter((item) => facultyIds.includes(item.facultyId));
            }

            populateSelect(department, list, department.dataset.placeholder || 'Seçiniz', true);
        };

        const refreshCourse = () => {
            if (!course) {
                return;
            }

            let list = DATA.courses;
            if (department && department.value) {
                list = list.filter((item) => item.departmentId === department.value);
            } else if (university && university.value) {
                list = list.filter((item) => getUniversityByDepartmentId(item.departmentId) === university.value);
            }

            if (classSelect && classSelect.value) {
                list = list.filter((item) => item.classId === classSelect.value);
            }

            populateSelect(course, list, course.dataset.placeholder || 'Seçiniz', true);
        };

        const refreshTopic = () => {
            if (!topic) {
                return;
            }

            const selectedCourse = course ? course.value : '';
            const list = selectedCourse
                ? DATA.topics.filter((item) => item.courseId === selectedCourse)
                : DATA.topics;

            populateSelect(topic, list, topic.dataset.placeholder || 'Seçiniz', true);
        };

        refreshFaculty();
        refreshDepartment();
        refreshCourse();
        refreshTopic();

        university?.addEventListener('change', () => {
            refreshFaculty();
            refreshDepartment();
            refreshCourse();
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        faculty?.addEventListener('change', () => {
            refreshDepartment();
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

        course?.addEventListener('change', () => {
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });
    }

    function initPublicFilterGroup(group) {
        const university = group.querySelector('select[data-level="university"]');
        const departmentType = group.querySelector('select[data-level="department-type"]');
        const department = group.querySelector('select[data-level="department"]');
        const classSelect = group.querySelector('select[data-level="class"]');
        const course = group.querySelector('select[data-level="course"]');
        const topic = group.querySelector('select[data-level="topic"]');

        populateSelect(university, REMOTE.universities, university?.dataset.placeholder || 'Seçiniz', true);
        populateSelect(classSelect, DATA.classes, classSelect?.dataset.placeholder || 'Seçiniz', true);
        populateSelect(
            departmentType,
            [
                { id: 'onlisans', name: 'Önlisans' },
                { id: 'lisans', name: 'Lisans' }
            ],
            departmentType?.dataset.placeholder || 'Seçiniz',
            true
        );

        const refreshDepartments = () => {
            if (!department) {
                return;
            }

            const selectedType = departmentType ? departmentType.value : '';
            const list = selectedType
                ? (REMOTE.departmentsByType[selectedType] || [])
                : [];
            const placeholder = selectedType
                ? (department.dataset.placeholder || 'Seçiniz')
                : 'Önce program türü seçiniz';

            populateSelect(department, list, placeholder, true);
        };

        const refreshCourse = () => {
            if (!course) {
                return;
            }

            let list = DATA.courses;
            if (department && department.value) {
                const byDepartment = list.filter((item) => item.departmentId === department.value);
                list = byDepartment.length ? byDepartment : list;
            }

            if (classSelect && classSelect.value) {
                list = list.filter((item) => item.classId === classSelect.value);
            }

            populateSelect(course, list, course.dataset.placeholder || 'Seçiniz', true);
        };

        const refreshTopic = () => {
            if (!topic) {
                return;
            }
            const selectedCourse = course ? course.value : '';
            const list = selectedCourse ? DATA.topics.filter((item) => item.courseId === selectedCourse) : DATA.topics;
            populateSelect(topic, list, topic.dataset.placeholder || 'Seçiniz', true);
        };

        refreshDepartments();
        refreshCourse();
        refreshTopic();

        departmentType?.addEventListener('change', () => {
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

        course?.addEventListener('change', () => {
            refreshTopic();
            group.dispatchEvent(new Event('hierarchy:changed'));
        });

        university?.addEventListener('change', () => {
            group.dispatchEvent(new Event('hierarchy:changed'));
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
            courseId: normalize(formData.get('course_id')),
            topicId: normalize(formData.get('topic_id')),
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
        if (filters.courseId && note.courseId !== filters.courseId) {
            return false;
        }
        if (filters.topicId && note.topicId !== filters.topicId) {
            return false;
        }
        if (filters.fileType && note.fileType !== filters.fileType) {
            return false;
        }

        if (filters.query) {
            const searchable = [
                note.title,
                note.description,
                note.tags.join(' '),
                resolveLabel(DATA.courses, note.courseId),
                resolveLabel(DATA.topics, note.topicId),
                resolveDepartmentName(note.departmentId),
                resolveUniversityName(note.universityId)
            ].join(' ').toLowerCase();

            if (!searchable.includes(filters.query)) {
                return false;
            }
        }

        return true;
    }

    function filterNotes(filters) {
        return DATA.notes.filter((note) => matchesFilters(note, filters));
    }

    function noteCardTemplate(note) {
        const tagHtml = note.tags.slice(0, 3).map((tag) => `<span class="note-tag">#${escapeHtml(tag)}</span>`).join('');
        return `
            <article class="col-sm-6 col-xl-4">
                <div class="note-card card">
                    <div class="card-body">
                        <h3 class="h6 mb-2">${escapeHtml(note.title)}</h3>
                        <p class="text-secondary mb-3">${escapeHtml(note.description)}</p>
                        <div class="note-tags">${tagHtml}</div>
                        <div class="meta-row">
                            <span>${escapeHtml(note.uploader)}</span>
                            <span>${formatNumber(note.downloads)} indirme</span>
                        </div>
                        <div class="meta-row">
                            <span>${formatNumber(note.views)} görüntülenme</span>
                            <a href="note-detail.php?id=${note.id}" class="text-primary text-decoration-none fw-semibold">Detay</a>
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
        const resultCount = document.getElementById('homeResultCount');

        const render = () => {
            const filtered = filterNotes(collectFilters(form));
            const popular = [...filtered].sort((a, b) => b.downloads - a.downloads).slice(0, 6);
            const latest = [...filtered].sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt)).slice(0, 6);

            renderGrid(popularGrid, popular, 'Bu filtreye uygun popüler not bulunamadı.');
            renderGrid(latestGrid, latest, 'Bu filtreye uygun yeni not bulunamadı.');

            if (resultCount) {
                resultCount.textContent = formatNumber(filtered.length);
            }
        };

        form.addEventListener('input', render);
        form.addEventListener('change', render);
        form.addEventListener('hierarchy:changed', render);
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

            const sync = () => {
                hiddenField.value = tags.join(',');
                chipsContainer.innerHTML = tags.map((tag) => {
                    const safeTag = escapeHtml(tag);
                    return `<span class="tag-chip">${safeTag}<button type="button" data-remove-tag="${safeTag}">&times;</button></span>`;
                }).join('');
            };

            const addTag = (rawValue) => {
                const cleanValue = normalize(rawValue).replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
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
                tags = tags.filter((tag) => tag !== normalize(tagValue));
                sync();
            });

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

        const maxBytes = 25 * 1024 * 1024;
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
                <div class="file-item">Tür: ${escapeHtml(file.type || 'Bilinmiyor')}</div>
            `;
        };

        const validateFile = (file) => {
            const errors = [];
            const extension = normalize(file.name.split('.').pop());

            if (!acceptedExtensions.has(extension)) {
                errors.push('Desteklenmeyen dosya uzantısı.');
            }

            if (file.size > maxBytes) {
                errors.push('Dosya 25 MB limitini aşıyor.');
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
            showNotice('Dosya istemci tarafında doğrulandı. Son kontrol backend tarafında yapılacak.', 'success');
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

        uploadForm.addEventListener('submit', (event) => {
            event.preventDefault();

            const selectedFile = fileInput?.files?.[0];
            if (!selectedFile) {
                showNotice('Lütfen önce bir dosya seç.', 'danger');
                return;
            }

            showNotice('Yükleme isteği hazırlanıyor...', 'info');
            setTimeout(() => {
                showNotice('Frontend prototipinde doğrulama başarılı. Backend endpoint bağlantısı sonraki adımda eklenecek.', 'success');
            }, 650);
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
                <header><strong>${escapeHtml(author)}</strong> <span class="text-secondary">| ${escapeHtml(rating)}/5</span></header>
                <p class="mb-0">${escapeHtml(text)}</p>
            `;

            commentsList.prepend(item);
            form.reset();
        });
    }

    function initSearchPage() {
        const form = document.getElementById('searchFilterForm');
        const queryInput = document.getElementById('searchQuery');
        const resultsContainer = document.getElementById('searchResults');
        const pagination = document.getElementById('searchPagination');
        const countElement = document.getElementById('searchResultCount');

        if (!form || !resultsContainer || !pagination || !countElement) {
            return;
        }

        const state = {
            currentPage: 1,
            pageSize: 5,
            filtered: []
        };

        const drawPagination = () => {
            const totalPages = Math.max(1, Math.ceil(state.filtered.length / state.pageSize));
            state.currentPage = Math.min(state.currentPage, totalPages);

            const buttons = [];
            for (let page = 1; page <= totalPages; page += 1) {
                buttons.push(`
                    <li class="page-item ${page === state.currentPage ? 'active' : ''}">
                        <button type="button" class="page-link" data-page="${page}">${page}</button>
                    </li>
                `);
            }

            pagination.innerHTML = buttons.join('');
        };

        const drawResults = () => {
            const start = (state.currentPage - 1) * state.pageSize;
            const pageItems = state.filtered.slice(start, start + state.pageSize);

            if (!pageItems.length) {
                resultsContainer.innerHTML = '<div class="empty-state">Filtreye uygun not bulunamadı.</div>';
                countElement.textContent = '0';
                drawPagination();
                return;
            }

            resultsContainer.innerHTML = pageItems.map((note) => {
                return `
                    <article class="result-item">
                        <h3>${escapeHtml(note.title)}</h3>
                        <p>${escapeHtml(note.description)}</p>
                        <div class="result-footer">
                            <div class="d-flex flex-wrap gap-2">
                                <span class="note-tag">${escapeHtml(resolveUniversityName(note.universityId))}</span>
                                <span class="note-tag">${escapeHtml(resolveLabel(DATA.courses, note.courseId))}</span>
                                <span class="note-tag">${escapeHtml(resolveLabel(DATA.topics, note.topicId))}</span>
                            </div>
                            <div class="text-secondary small">
                                ${formatDate(note.createdAt)} | ${formatNumber(note.downloads)} indirme
                            </div>
                        </div>
                    </article>
                `;
            }).join('');

            countElement.textContent = formatNumber(state.filtered.length);
            drawPagination();
        };

        const apply = () => {
            const filters = collectFilters(form);
            if (queryInput instanceof HTMLInputElement) {
                filters.query = normalize(queryInput.value);
            }

            state.filtered = filterNotes(filters)
                .sort((a, b) => new Date(b.createdAt) - new Date(a.createdAt));
            state.currentPage = 1;
            drawResults();
        };

        form.addEventListener('change', apply);
        form.addEventListener('input', apply);
        form.addEventListener('hierarchy:changed', apply);
        queryInput?.addEventListener('input', apply);

        pagination.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLButtonElement)) {
                return;
            }

            const nextPage = Number(target.dataset.page);
            if (!Number.isInteger(nextPage) || nextPage < 1) {
                return;
            }

            state.currentPage = nextPage;
            drawResults();
        });

        apply();
    }

    document.addEventListener('DOMContentLoaded', async () => {
        await loadRemoteFilterData();
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
    });
})();

