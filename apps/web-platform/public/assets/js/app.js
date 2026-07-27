'use strict';

document.documentElement.classList.add('js-ready');

document.addEventListener('DOMContentLoaded', () => {
    const shell = document.querySelector('.portal-shell');
    const toggle = document.querySelector('[data-portal-toggle]');
    const overlay = document.querySelector('[data-portal-overlay]');
    const toast = document.querySelector('[data-portal-toast]');
    const sidebarNav = document.querySelector('[data-portal-nav]');
    const activeNavigation = sidebarNav?.querySelector('.portal-nav-link.active');
    const role = shell?.dataset.portalRole || 'portal';
    const navigationScrollKey = `coursehub:${role}:navigation-scroll`;

    const closeNavigation = () => {
        if (!shell) return;
        shell.classList.remove('nav-open');
        toggle?.setAttribute('aria-expanded', 'false');
    };

    toggle?.addEventListener('click', () => {
        if (!shell) return;
        const open = shell.classList.toggle('nav-open');
        toggle.setAttribute('aria-expanded', String(open));
    });
    overlay?.addEventListener('click', closeNavigation);

    if (sidebarNav) {
        const savedPosition = Number.parseInt(sessionStorage.getItem(navigationScrollKey) || '', 10);
        window.requestAnimationFrame(() => {
            if (Number.isFinite(savedPosition)) sidebarNav.scrollTop = savedPosition;
            else activeNavigation?.scrollIntoView({ block: 'nearest' });
        });
        sidebarNav.addEventListener('scroll', () => {
            sessionStorage.setItem(navigationScrollKey, String(Math.round(sidebarNav.scrollTop)));
        }, { passive: true });
        sidebarNav.querySelectorAll('.portal-nav-link').forEach((link) => {
            link.addEventListener('click', () => {
                sessionStorage.setItem(navigationScrollKey, String(Math.round(sidebarNav.scrollTop)));
                closeNavigation();
            });
        });
    }

    const logoutForm = document.querySelector('[data-logout-form]');
    const logoutDialog = document.querySelector('[data-logout-dialog]');
    const logoutCancel = document.querySelector('[data-logout-cancel]');
    const logoutConfirm = document.querySelector('[data-logout-confirm]');
    let logoutApproved = false;

    logoutForm?.addEventListener('submit', (event) => {
        if (logoutApproved || !logoutDialog || typeof logoutDialog.showModal !== 'function') return;
        event.preventDefault();
        logoutDialog.showModal();
        logoutCancel?.focus();
    });
    logoutCancel?.addEventListener('click', () => logoutDialog?.close());
    logoutConfirm?.addEventListener('click', () => {
        logoutApproved = true;
        logoutDialog?.close();
        logoutForm?.requestSubmit();
    });
    logoutDialog?.addEventListener('click', (event) => {
        if (event.target === logoutDialog) logoutDialog.close();
    });

    const photoDialog = document.querySelector('[data-photo-dialog]');
    const photoImage = photoDialog?.querySelector('[data-photo-image]');
    let photoScale = 1;
    const applyPhotoScale = () => {
        if (photoImage) photoImage.style.transform = `scale(${photoScale})`;
    };
    const closePhoto = () => {
        if (!photoDialog) return;
        photoScale = 1;
        applyPhotoScale();
        photoDialog.close();
    };
    document.querySelectorAll('[data-photo-open]').forEach((button) => {
        button.addEventListener('click', () => {
            if (!photoDialog || typeof photoDialog.showModal !== 'function') return;
            photoScale = 1;
            applyPhotoScale();
            photoDialog.showModal();
        });
    });
    photoDialog?.querySelectorAll('[data-photo-close]').forEach((button) => button.addEventListener('click', closePhoto));
    photoDialog?.querySelector('[data-photo-zoom-in]')?.addEventListener('click', () => {
        photoScale = Math.min(3, photoScale + 0.25);
        applyPhotoScale();
    });
    photoDialog?.querySelector('[data-photo-zoom-out]')?.addEventListener('click', () => {
        photoScale = Math.max(0.75, photoScale - 0.25);
        applyPhotoScale();
    });
    photoDialog?.querySelector('[data-photo-reset]')?.addEventListener('click', () => {
        photoScale = 1;
        applyPhotoScale();
    });
    photoDialog?.addEventListener('click', (event) => {
        if (event.target === photoDialog) closePhoto();
    });

    const removeDialog = document.querySelector('[data-photo-remove-dialog]');
    const removeCancel = document.querySelector('[data-photo-remove-cancel]');
    const removeConfirm = document.querySelector('[data-photo-remove-confirm]');
    let pendingRemoveForm = null;
    let removeApproved = false;
    document.querySelectorAll('[data-profile-photo-remove]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (removeApproved) return;
            if (!removeDialog || typeof removeDialog.showModal !== 'function') {
                if (!window.confirm('Remove this profile photo and return to the initials avatar?')) event.preventDefault();
                return;
            }
            event.preventDefault();
            pendingRemoveForm = form;
            removeDialog.showModal();
            removeCancel?.focus();
        });
    });
    removeCancel?.addEventListener('click', () => {
        pendingRemoveForm = null;
        removeDialog?.close();
    });
    removeConfirm?.addEventListener('click', () => {
        if (!pendingRemoveForm) return;
        removeApproved = true;
        removeDialog?.close();
        pendingRemoveForm.requestSubmit();
    });
    removeDialog?.addEventListener('click', (event) => {
        if (event.target === removeDialog) {
            pendingRemoveForm = null;
            removeDialog.close();
        }
    });

    document.querySelectorAll('input[type="number"]').forEach((input) => {
        input.addEventListener('keydown', (event) => {
            if (['e', 'E', '+', '-'].includes(event.key)) event.preventDefault();
        });
    });

    document.addEventListener('invalid', (event) => {
        const control = event.target;
        if (!(control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement || control instanceof HTMLSelectElement)) return;
        const reason = control.dataset.error || '';
        if (reason) control.setCustomValidity(reason);
    }, true);
    document.addEventListener('input', (event) => {
        const control = event.target;
        if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement || control instanceof HTMLSelectElement) {
            control.setCustomValidity('');
        }
    });
    document.addEventListener('change', (event) => {
        const control = event.target;
        if (control instanceof HTMLInputElement || control instanceof HTMLTextAreaElement || control instanceof HTMLSelectElement) {
            control.setCustomValidity('');
        }
    });

    const completeAuthoring = document.querySelector('[data-complete-authoring]');
    if (completeAuthoring) {
        const form = completeAuthoring.querySelector('[data-course-form]');
        const sectionList = completeAuthoring.querySelector('[data-section-list]');
        const curriculumJson = completeAuthoring.querySelector('[data-curriculum-json]');
        const addSectionButton = completeAuthoring.querySelector('[data-add-section]');
        const previewTitle = completeAuthoring.querySelector('[data-preview-title]');
        const previewDescription = completeAuthoring.querySelector('[data-preview-description]');
        const previewCategory = completeAuthoring.querySelector('[data-preview-category]');
        const previewPrice = completeAuthoring.querySelector('[data-preview-price]');
        const previewLevel = completeAuthoring.querySelector('[data-preview-level]');
        const previewLanguage = completeAuthoring.querySelector('[data-preview-language]');
        const previewMedia = completeAuthoring.querySelector('[data-preview-media]');
        const titleInput = completeAuthoring.querySelector('[name="title"]');
        const descriptionInput = completeAuthoring.querySelector('[name="short_description"]');
        const categoryInput = completeAuthoring.querySelector('[name="category_id"]');
        const priceInput = completeAuthoring.querySelector('[name="price"]');
        const discountInput = completeAuthoring.querySelector('[name="discount_price"]');
        const levelInput = completeAuthoring.querySelector('[name="level"]');
        const languageInput = completeAuthoring.querySelector('[name="language"]');
        const thumbnailInput = completeAuthoring.querySelector('[name="thumbnail"]');
        let thumbnailObjectUrl = '';
        const sectionPrototype = sectionList?.querySelector('[data-section]')?.cloneNode(true) || null;

        const cleanPreviewText = (value, fallback) => {
            const text = String(value || '').replace(/\s+/g, ' ').trim();
            return text || fallback;
        };

        const clearLesson = (lesson) => {
            lesson.querySelectorAll('input, textarea, select').forEach((control) => {
                if (control instanceof HTMLSelectElement) control.value = control.matches('[data-content-type]') ? 'text' : control.options[0]?.value || '';
                else if (control instanceof HTMLInputElement && control.type === 'checkbox') control.checked = false;
                else if (control instanceof HTMLInputElement && control.type === 'number') control.value = '0';
                else if (control instanceof HTMLInputElement && control.type === 'file') control.value = '';
                else control.value = '';
                control.setCustomValidity('');
            });
            lesson.querySelectorAll('.existing-resource').forEach((status) => { status.textContent = 'No file uploaded yet.'; });
        };

        const setLessonPanel = (lesson) => {
            const type = lesson.querySelector('[data-content-type]')?.value || 'text';
            lesson.querySelectorAll('[data-content-panel]').forEach((panel) => {
                const accepted = String(panel.dataset.contentPanel || '').split(/\s+/).filter(Boolean);
                const active = accepted.includes(type);
                panel.hidden = !active;
                panel.querySelectorAll('input, textarea, select').forEach((control) => {
                    if (control instanceof HTMLInputElement && control.type === 'file') control.disabled = !active;
                });
            });
        };

        const renumberAuthoring = () => {
            let globalLessonNumber = 0;
            sectionList?.querySelectorAll('[data-section]').forEach((section, sectionIndex) => {
                const sectionNumber = section.querySelector('[data-section-number]');
                if (sectionNumber) sectionNumber.textContent = `SECTION ${String(sectionIndex + 1).padStart(2, '0')}`;
                section.querySelectorAll('[data-lesson]').forEach((lesson, lessonIndex) => {
                    globalLessonNumber += 1;
                    const lessonNumber = lesson.querySelector('[data-lesson-number]');
                    if (lessonNumber) lessonNumber.textContent = `LESSON ${String(globalLessonNumber).padStart(2, '0')}`;
                    lesson.querySelectorAll('[data-resource-file]').forEach((fileInput) => {
                        fileInput.name = `lesson_file_${sectionIndex}_${lessonIndex}`;
                    });
                    setLessonPanel(lesson);
                });
            });
        };

        const wireLesson = (lesson) => {
            if (lesson.dataset.wired === '1') return;
            lesson.dataset.wired = '1';
            lesson.querySelector('[data-content-type]')?.addEventListener('change', () => setLessonPanel(lesson));
            lesson.querySelector('[data-remove-lesson]')?.addEventListener('click', () => {
                lesson.remove();
                renumberAuthoring();
            });
            lesson.querySelectorAll('[data-resource-file]').forEach((fileInput) => {
                fileInput.addEventListener('change', () => {
                    const status = fileInput.closest('label')?.querySelector('.existing-resource');
                    if (status) status.textContent = fileInput.files?.[0]?.name || 'No file uploaded yet.';
                });
            });
            setLessonPanel(lesson);
        };

        const wireSection = (section) => {
            if (section.dataset.wired === '1') return;
            section.dataset.wired = '1';
            section.querySelectorAll('[data-lesson]').forEach(wireLesson);
            section.querySelector('[data-remove-section]')?.addEventListener('click', () => {
                section.remove();
                renumberAuthoring();
            });
            section.querySelector('[data-add-lesson]')?.addEventListener('click', () => {
                const lessonList = section.querySelector('[data-lesson-list]');
                const lessonSource = section.querySelector('[data-lesson]') || sectionPrototype?.querySelector('[data-lesson]');
                if (!lessonList || !lessonSource) return;
                const lesson = lessonSource.cloneNode(true);
                delete lesson.dataset.wired;
                clearLesson(lesson);
                lessonList.appendChild(lesson);
                wireLesson(lesson);
                renumberAuthoring();
                lesson.querySelector('[data-lesson-title]')?.focus();
            });
        };

        sectionList?.querySelectorAll('[data-section]').forEach(wireSection);
        addSectionButton?.addEventListener('click', () => {
            if (!sectionList || !sectionPrototype) return;
            const section = sectionPrototype.cloneNode(true);
            delete section.dataset.wired;
            const lessons = Array.from(section.querySelectorAll('[data-lesson]'));
            lessons.slice(1).forEach((lesson) => lesson.remove());
            const firstLesson = section.querySelector('[data-lesson]');
            section.querySelectorAll('input, textarea, select').forEach((control) => control.setCustomValidity(''));
            const sectionTitle = section.querySelector('[data-section-title]');
            if (sectionTitle) sectionTitle.value = '';
            if (firstLesson) {
                delete firstLesson.dataset.wired;
                clearLesson(firstLesson);
            }
            sectionList.appendChild(section);
            wireSection(section);
            renumberAuthoring();
            sectionTitle?.focus();
        });

        const serializeCurriculum = () => {
            const sections = [];
            sectionList?.querySelectorAll('[data-section]').forEach((section, sectionIndex) => {
                const lessons = [];
                section.querySelectorAll('[data-lesson]').forEach((lesson, lessonIndex) => {
                    const type = lesson.querySelector('[data-content-type]')?.value || 'text';
                    const linkValue = lesson.querySelector('[data-link-url]')?.value || '';
                    const contentUrl = type === 'link' ? linkValue : (lesson.querySelector('[data-content-url]')?.value || '');
                    const activeFile = Array.from(lesson.querySelectorAll('[data-resource-file]')).find((input) => !input.disabled);
                    lessons.push({
                        title: lesson.querySelector('[data-lesson-title]')?.value || '',
                        content_type: type,
                        content_url: contentUrl,
                        content_name: lesson.querySelector('[data-content-name]')?.value || '',
                        content_text: ['text', 'word'].includes(type) ? (lesson.querySelector('[data-content-text]')?.value || '') : '',
                        duration_minutes: lesson.querySelector('[data-duration]')?.value || '0',
                        is_preview: Boolean(lesson.querySelector('[data-is-preview]')?.checked),
                        file_key: activeFile?.name || `lesson_file_${sectionIndex}_${lessonIndex}`,
                    });
                });
                sections.push({ title: section.querySelector('[data-section-title]')?.value || '', lessons });
            });
            if (curriculumJson) curriculumJson.value = JSON.stringify(sections);
            return sections;
        };

        const validateSubmissionSpecificContent = (submitAction) => {
            const submitting = submitAction === 'submit';
            let firstInvalid = null;
            completeAuthoring.querySelectorAll('[data-error]').forEach((control) => control.setCustomValidity(''));
            if (!submitting) return true;
            const requiredNames = ['subtitle', 'learning_outcomes', 'requirements', 'target_audience'];
            requiredNames.forEach((name) => {
                const control = form?.querySelector(`[name="${name}"]`);
                if (!control || String(control.value || '').trim() !== '') return;
                control.setCustomValidity(control.dataset.error || 'Complete this field before submitting for review.');
                firstInvalid ||= control;
            });
            const existingThumbnail = form?.querySelector('[name="existing_thumbnail"]')?.value || '';
            if (!existingThumbnail && !thumbnailInput?.files?.[0]) {
                thumbnailInput?.setCustomValidity('Upload the real public course thumbnail before submitting for review.');
                firstInvalid ||= thumbnailInput;
            }
            const sections = Array.from(sectionList?.querySelectorAll('[data-section]') || []);
            if (sections.length === 0) {
                showToast('Add at least one section and one lesson before submitting.');
                return false;
            }
            let lessonCount = 0;
            sections.forEach((section) => {
                section.querySelectorAll('[data-lesson]').forEach((lesson) => {
                    lessonCount += 1;
                    const type = lesson.querySelector('[data-content-type]')?.value || 'text';
                    if (['text', 'word'].includes(type)) {
                        const editor = lesson.querySelector('[data-content-text]');
                        if (editor && !editor.value.trim()) {
                            editor.setCustomValidity('Write the lesson content that Students should read inside CourseHub.');
                            firstInvalid ||= editor;
                        }
                    } else if (type === 'link') {
                        const link = lesson.querySelector('[data-link-url]');
                        if (link && !link.value.trim()) {
                            link.setCustomValidity('Enter the HTTPS resource address for this link lesson.');
                            firstInvalid ||= link;
                        }
                    } else {
                        const existing = lesson.querySelector('[data-content-url]')?.value || '';
                        const file = Array.from(lesson.querySelectorAll('[data-resource-file]')).find((input) => !input.disabled);
                        if (!existing && !file?.files?.[0]) {
                            file?.setCustomValidity(`Upload the actual ${type} file for this lesson.`);
                            firstInvalid ||= file;
                        }
                    }
                });
            });
            if (lessonCount === 0) {
                showToast('Add at least one lesson before submitting.');
                return false;
            }
            if (firstInvalid) {
                firstInvalid.reportValidity();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
            return true;
        };

        form?.addEventListener('submit', (event) => {
            serializeCurriculum();
            const submitter = event.submitter;
            const submitAction = submitter instanceof HTMLButtonElement ? submitter.value : 'draft';
            completeAuthoring.querySelectorAll('[data-error]').forEach((control) => {
                if (!control.checkValidity()) control.setCustomValidity(control.dataset.error || 'Check this field.');
            });
            if (!form.checkValidity() || !validateSubmissionSpecificContent(submitAction)) {
                event.preventDefault();
                const invalid = form.querySelector(':invalid');
                invalid?.reportValidity();
                invalid?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });

        const updateCoursePreview = () => {
            if (previewTitle) previewTitle.textContent = cleanPreviewText(titleInput?.value, 'Your course title will appear here');
            if (previewDescription) previewDescription.textContent = cleanPreviewText(descriptionInput?.value, 'The short course benefit appears here and remains neatly contained inside the card.');
            if (previewCategory && categoryInput instanceof HTMLSelectElement) previewCategory.textContent = cleanPreviewText(categoryInput.selectedOptions[0]?.textContent, 'Choose a category');
            if (previewLevel && levelInput instanceof HTMLSelectElement) previewLevel.textContent = cleanPreviewText(levelInput.selectedOptions[0]?.textContent, 'Beginner');
            if (previewLanguage) previewLanguage.textContent = cleanPreviewText(languageInput?.value, 'English');
            const discount = Number.parseFloat(discountInput?.value || '');
            const standard = Number.parseFloat(priceInput?.value || '0');
            const amount = Number.isFinite(discount) ? discount : standard;
            if (previewPrice) previewPrice.textContent = Number.isFinite(amount) && amount > 0
                ? `NPR ${new Intl.NumberFormat('en-GB', { maximumFractionDigits: 0 }).format(amount)}`
                : 'Free';
        };
        [titleInput, descriptionInput, categoryInput, priceInput, discountInput, levelInput, languageInput].forEach((field) => {
            field?.addEventListener('input', updateCoursePreview);
            field?.addEventListener('change', updateCoursePreview);
        });
        thumbnailInput?.addEventListener('change', () => {
            const file = thumbnailInput.files?.[0];
            if (thumbnailObjectUrl) URL.revokeObjectURL(thumbnailObjectUrl);
            thumbnailObjectUrl = '';
            if (!previewMedia || !file) return;
            thumbnailObjectUrl = URL.createObjectURL(file);
            const image = document.createElement('img');
            image.src = thumbnailObjectUrl;
            image.alt = 'Selected course thumbnail preview';
            previewMedia.replaceChildren(image);
        });
        renumberAuthoring();
        serializeCurriculum();
        updateCoursePreview();
    }

    let toastTimer = 0;
    function showToast(message) {
        if (!toast) return;
        window.clearTimeout(toastTimer);
        toast.textContent = message;
        toast.classList.add('visible');
        toastTimer = window.setTimeout(() => toast.classList.remove('visible'), 3600);
    }

    if (shell) {
        let checkingSession = false;
        let sessionEnded = false;
        const checkSession = async () => {
            if (checkingSession || sessionEnded || document.visibilityState === 'hidden') return;
            checkingSession = true;
            try {
                const response = await fetch('/session-status', {
                    method: 'GET', credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' },
                });
                if (response.status !== 401) return;
                const payload = await response.json().catch(() => ({}));
                const loginUrl = typeof payload.login_url === 'string' && payload.login_url.startsWith('/') ? payload.login_url : '/login';
                sessionEnded = true;
                showToast('This session was revoked or expired. Redirecting to sign in.');
                window.setTimeout(() => {
                    const separator = loginUrl.includes('?') ? '&' : '?';
                    window.location.replace(`${loginUrl}${separator}session=ended`);
                }, 500);
            } catch (error) {
                // A temporary service outage must not destroy a valid local browser session.
            } finally {
                checkingSession = false;
            }
        };
        window.setTimeout(checkSession, 1500);
        window.setInterval(checkSession, 12000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') checkSession();
        });
    }

    document.querySelectorAll('[data-demo-action]').forEach((button) => {
        button.addEventListener('click', () => showToast(button.dataset.demoAction || 'This action is ready for service integration.'));
    });
    document.querySelectorAll('.payment-method').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('.payment-method').forEach((item) => item.classList.remove('active'));
            button.classList.add('active');
        });
    });
    document.querySelectorAll('.filter-tabs').forEach((tabs) => {
        tabs.querySelectorAll('button').forEach((button) => {
            button.addEventListener('click', () => {
                tabs.querySelectorAll('button').forEach((item) => item.classList.remove('active'));
                button.classList.add('active');
            });
        });
    });

    const pageSearch = document.querySelector('.portal-search input');
    pageSearch?.addEventListener('input', () => {
        const term = pageSearch.value.trim().toLowerCase();
        document.querySelectorAll('.portal-main tbody tr:not(.empty-row), .portal-main details.portal-card, .portal-main details.support-message-row').forEach((item) => {
            const matches = term === '' || (item.textContent || '').toLowerCase().includes(term);
            item.hidden = !matches;
        });
    });
});
