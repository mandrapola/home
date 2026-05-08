(function () {
    const i18n = window.reportI18n || {};
    const t = (key, fallback) => i18n[key] || fallback || key;

    const reportBody = document.getElementById('reportBody');
    const reportRefreshBtn = document.getElementById('reportRefreshBtn');
    if (!reportBody) return;

    function renderReportGantt(data) {
        const rows = Array.isArray(data?.rows) ? data.rows : [];
        const timelineStart = Date.parse(String(data?.timeline_start || ''));
        const timelineEnd = Date.parse(String(data?.timeline_end || ''));
        const span = timelineEnd - timelineStart;
        const nowTs = Date.now();
        const msPerHour = 3600 * 1000;
        const labelWidthPx = 200;
        const hourWidthPx = 160;

        if (!Number.isFinite(timelineStart) || !Number.isFinite(timelineEnd) || span <= 0 || rows.length === 0) {
            reportBody.textContent = t('power_events_empty', 'No power events for current day.');
            return;
        }

        const timelineHours = Math.max(1, Math.round(span / msPerHour));
        const timelineWidthPx = timelineHours * hourWidthPx;

        const tsToPx = (ts) => {
            if (!Number.isFinite(ts)) return 0;
            const px = ((ts - timelineStart) / span) * timelineWidthPx;
            return Math.min(timelineWidthPx, Math.max(0, px));
        };

        const isoToPx = (iso) => tsToPx(Date.parse(String(iso || '')));

        const renderSegments = (segments, lane) => {
            if (!Array.isArray(segments)) return '';
            return segments.map((segment) => {
                const left = isoToPx(segment.start);
                const right = isoToPx(segment.end);
                const width = Math.max(1, right - left);
                const planState = lane === 'plan' ? String(segment.state || '').toLowerCase() : '';
                const stateClass = lane === 'plan'
                    ? (planState === 'green'
                        ? ' power-gantt-segment--plan-green'
                        : ' power-gantt-segment--plan-yellow')
                    : '';
                return `<span class="power-gantt-segment${stateClass}" style="left:${left.toFixed(2)}px;width:${width.toFixed(2)}px;"></span>`;
            }).join('');
        };

        const hourLines = Array.from({ length: timelineHours + 1 }).map((_, hourOffset) => {
            const leftPx = hourOffset * hourWidthPx;
            return `<span class="hour-line" style="left:${leftPx.toFixed(2)}px;"></span>`;
        }).join('');

        const hourLabels = Array.from({ length: timelineHours + 1 }).map((_, hourOffset) => {
            const leftPx = hourOffset * hourWidthPx;
            const labelDate = new Date(timelineStart + (hourOffset * msPerHour));
            const hh = String(labelDate.getHours()).padStart(2, '0');
            return `<span class="power-gantt-hour" style="left:${leftPx.toFixed(2)}px;">${hh}:00</span>`;
        }).join('');

        const nowLine = `<span class="power-gantt-now-line" style="left:${tsToPx(nowTs).toFixed(2)}px;"></span>`;

        const rowHtml = rows.map((row) => `
            <div class="power-gantt-row">
                <div class="power-gantt-label" title="${String(row.label || row.pin || '')}">${String(row.label || row.pin || '')}</div>
                <div class="power-gantt-lanes" style="width:${timelineWidthPx}px;">
                    ${nowLine}
                    <div class="power-gantt-lane power-gantt-lane--fact">
                        ${hourLines}
                        ${renderSegments(row.fact, 'fact')}
                    </div>
                    <div class="power-gantt-lane power-gantt-lane--plan">
                        ${hourLines}
                        ${renderSegments(row.plan, 'plan')}
                    </div>
                </div>
            </div>
        `).join('');

        reportBody.innerHTML = `
            <div class="power-gantt" style="--power-gantt-label-width:${labelWidthPx}px;">
                <div class="power-gantt-scroll" id="powerGanttScroll">
                    <div class="power-gantt-grid">
                        <span class="power-gantt-hover-line d-none" id="powerGanttHoverLine"></span>
                        <span class="power-gantt-hover-label d-none" id="powerGanttHoverLabel"></span>
                        <div class="power-gantt-header">
                            <div class="label-col">${t('pin', 'Pin')}</div>
                            <div class="time-col" style="width:${timelineWidthPx}px;">
                                ${hourLines}
                                ${hourLabels}
                                ${nowLine}
                            </div>
                        </div>
                        ${rowHtml}
                    </div>
                </div>
            </div>
        `;

        const scrollEl = reportBody.querySelector('#powerGanttScroll');
        if (scrollEl) {
            const hoverLineEl = reportBody.querySelector('#powerGanttHoverLine');
            const hoverLabelEl = reportBody.querySelector('#powerGanttHoverLabel');
            let isDragging = false;
            let dragStartX = 0;
            let dragStartScrollLeft = 0;
            const syncPinnedColumn = () => {
                const x = scrollEl.scrollLeft;
                const pinned = reportBody.querySelectorAll('.power-gantt-label, .power-gantt-header .label-col');
                pinned.forEach((el) => {
                    el.style.transform = `translateX(${x}px)`;
                });
            };

            const hideHover = () => {
                if (hoverLineEl) hoverLineEl.classList.add('d-none');
                if (hoverLabelEl) hoverLabelEl.classList.add('d-none');
            };

            const showHoverAt = (clientX) => {
                if (!hoverLineEl || !hoverLabelEl) return;
                const rect = scrollEl.getBoundingClientRect();
                const viewportX = clientX - rect.left;
                const timelineViewportX = viewportX - labelWidthPx;
                if (timelineViewportX < 0 || timelineViewportX > (rect.width - labelWidthPx)) {
                    hideHover();
                    return;
                }

                const timelineX = Math.max(0, Math.min(timelineWidthPx, scrollEl.scrollLeft + timelineViewportX));
                const lineLeft = labelWidthPx + timelineX;
                hoverLineEl.style.left = `${lineLeft.toFixed(2)}px`;

                const ratio = timelineWidthPx > 0 ? (timelineX / timelineWidthPx) : 0;
                const ts = Math.round(timelineStart + (span * ratio));
                const d = new Date(ts);
                const hh = String(d.getHours()).padStart(2, '0');
                const mm = String(d.getMinutes()).padStart(2, '0');
                const ss = String(d.getSeconds()).padStart(2, '0');
                hoverLabelEl.textContent = `${hh}:${mm}:${ss}`;
                hoverLabelEl.style.left = `${lineLeft.toFixed(2)}px`;

                hoverLineEl.classList.remove('d-none');
                hoverLabelEl.classList.remove('d-none');
            };

            const currentHourTs = Math.floor(nowTs / msPerHour) * msPerHour;
            const currentHourPx = tsToPx(currentHourTs);
            const visibleTimelinePx = Math.max(1, scrollEl.clientWidth - labelWidthPx);
            const desiredScrollLeft = currentHourPx - (visibleTimelinePx / 2);
            const maxScrollLeft = Math.max(0, scrollEl.scrollWidth - scrollEl.clientWidth);
            scrollEl.scrollLeft = Math.max(0, Math.min(desiredScrollLeft, maxScrollLeft));

            syncPinnedColumn();
            scrollEl.addEventListener('scroll', syncPinnedColumn, { passive: true });
            scrollEl.addEventListener('mousemove', (event) => {
                if (isDragging) {
                    const deltaX = event.clientX - dragStartX;
                    scrollEl.scrollLeft = dragStartScrollLeft - deltaX;
                }
                showHoverAt(event.clientX);
            }, { passive: true });
            scrollEl.addEventListener('mouseleave', hideHover, { passive: true });

            scrollEl.addEventListener('wheel', (event) => {
                const horizontalDelta = Math.abs(event.deltaX) > 0 ? event.deltaX : event.deltaY;
                if (horizontalDelta === 0) {
                    return;
                }
                event.preventDefault();
                scrollEl.scrollLeft += horizontalDelta;
            }, { passive: false });

            scrollEl.addEventListener('mousedown', (event) => {
                if (event.button !== 0) {
                    return;
                }
                isDragging = true;
                dragStartX = event.clientX;
                dragStartScrollLeft = scrollEl.scrollLeft;
                scrollEl.classList.add('is-dragging');
                event.preventDefault();
            });

            window.addEventListener('mouseup', () => {
                if (!isDragging) {
                    return;
                }
                isDragging = false;
                scrollEl.classList.remove('is-dragging');
            });
        }
    }

    async function fetchJson(url) {
        const response = await fetch(url, {
            headers: { 'Accept': 'application/json' },
        });
        const data = await response.json();
        return { response, data };
    }

    async function loadReport() {
        reportBody.textContent = t('loading', 'Loading...');
        try {
            const pinsResp = await fetchJson('/api/pairing/report-pins');
            if (!pinsResp.response.ok) {
                reportBody.textContent = pinsResp.data.message || t('chart_failed', 'Failed to load chart.');
                return;
            }

            const pins = Array.isArray(pinsResp.data?.pins) ? pinsResp.data.pins : [];
            if (pins.length === 0) {
                reportBody.textContent = t('power_events_empty', 'No power events for current day.');
                return;
            }

            const rows = [];
            let timelineStart = '';
            let timelineEnd = '';
            let timeZone = '';
            let date = '';

            for (const pin of pins) {
                const pinId = String(pin?.pin_id || '');
                if (!pinId) continue;
                const reportResp = await fetchJson(`/api/pairing/report?pin_id=${encodeURIComponent(pinId)}`);
                if (!reportResp.response.ok) {
                    reportBody.textContent = reportResp.data.message || t('chart_failed', 'Failed to load chart.');
                    return;
                }

                if (!timelineStart) {
                    timelineStart = String(reportResp.data?.timeline_start || '');
                    timelineEnd = String(reportResp.data?.timeline_end || '');
                    timeZone = String(reportResp.data?.time_zone || '');
                    date = String(reportResp.data?.date || '');
                }

                if (Array.isArray(reportResp.data?.rows) && reportResp.data.rows.length > 0) {
                    rows.push(...reportResp.data.rows);
                }
            }

            renderReportGantt({
                date,
                time_zone: timeZone,
                timeline_start: timelineStart,
                timeline_end: timelineEnd,
                rows,
            });
        } catch (_) {
            reportBody.textContent = t('chart_failed', 'Failed to load chart.');
        }
    }

    if (reportRefreshBtn) {
        reportRefreshBtn.addEventListener('click', () => {
            loadReport().catch(() => {});
        });
    }

    loadReport().catch(() => {});
})();
