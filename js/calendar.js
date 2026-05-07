(function () {
    'use strict';

    var CalendarData = window.CalendarData;
    if (!CalendarData) return;

    // -------------------------------------------------------------------------
    // Constants
    // -------------------------------------------------------------------------

    var MONTH_NAMES = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];

    // -------------------------------------------------------------------------
    // Date utilities
    // -------------------------------------------------------------------------

    function isLeapYear(year) {
        return (year % 4 === 0 && year % 100 !== 0) || (year % 400 === 0);
    }

    function daysInMonth(year, month) {
        var days = [31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        if (month === 2 && isLeapYear(year)) return 29;
        return days[month - 1];
    }

    // Day of week (0=Sun..6=Sat) via Tomohiko Sakamoto's algorithm
    function dayOfWeek(year, month, day) {
        var t = [0, 3, 2, 5, 0, 3, 5, 1, 4, 6, 2, 4];
        var y = year;
        if (month < 3) y--;
        return (y + Math.floor(y / 4) - Math.floor(y / 100) + Math.floor(y / 400) + t[month - 1] + day) % 7;
    }

    function padTwo(n) { return n < 10 ? '0' + n : String(n); }

    function toDateKey(year, month, day) {
        return year + '-' + padTwo(month) + '-' + padTwo(day);
    }

    function toYYYYMM(year, month) {
        return year + '-' + padTwo(month);
    }

    function monthLabel(year, month) {
        return MONTH_NAMES[month - 1] + ' ' + year;
    }

    function prevMonth(year, month) {
        return month === 1 ? { year: year - 1, month: 12 } : { year: year, month: month - 1 };
    }

    function nextMonth(year, month) {
        return month === 12 ? { year: year + 1, month: 1 } : { year: year, month: month + 1 };
    }

    function ordinal(n) {
        var s = ['th', 'st', 'nd', 'rd'];
        var v = n % 100;
        return n + (s[(v - 20) % 10] || s[v] || s[0]);
    }

    function extractTime(datetimeStr) {
        var idx = datetimeStr.lastIndexOf(' - ');
        return idx !== -1 ? datetimeStr.substring(idx + 3) : datetimeStr;
    }

    var DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    function formatEventDateLine(ev) {
        var parts = ev.date_key.split('-');
        var year  = parseInt(parts[0], 10);
        var month = parseInt(parts[1], 10);
        var day   = parseInt(parts[2], 10);
        var dow   = dayOfWeek(year, month, day);
        return DAY_NAMES[dow] + ', ' + MONTH_NAMES[month - 1] + ' ' + ordinal(day)
            + ', ' + extractTime(ev.start_time) + ' – ' + extractTime(ev.end_time);
    }

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    var parts = CalendarData.current_month.split('-');
    var state = {
        year:      parseInt(parts[0], 10),
        month:     parseInt(parts[1], 10),
        events:    CalendarData.events.slice(),
        fetchUrl:  CalendarData.fetch_url,
        nonce:     CalendarData.nonce,
        today:     CalendarData.today,
        single:    CalendarData.single    || 'yes',
        recurring: CalendarData.recurring || 'yes'
    };

    // -------------------------------------------------------------------------
    // Filtering
    // -------------------------------------------------------------------------

    function filterEvents(events) {
        return events.filter(function (ev) {
            if (ev.event_type === 'single'    && state.single    !== 'yes') return false;
            if (ev.event_type === 'recurring' && state.recurring !== 'yes') return false;
            return true;
        });
    }

    // -------------------------------------------------------------------------
    // Grid rendering
    // -------------------------------------------------------------------------

    function renderGrid(gridEl, year, month) {
        gridEl.innerHTML = '';

        var filtered = filterEvents(state.events);
        var firstDow = dayOfWeek(year, month, 1);
        var dim      = daysInMonth(year, month);
        var prev     = prevMonth(year, month);
        var prevDim  = daysInMonth(prev.year, prev.month);
        var nx       = nextMonth(year, month);

        for (var p = firstDow - 1; p >= 0; p--) {
            var d    = prevDim - p;
            var cell = document.createElement('div');
            cell.className           = 'day faded';
            cell.dataset.calendarDay = '';
            cell.dataset.date        = toDateKey(prev.year, prev.month, d);
            cell.dataset.otherMonth  = '';
            cell.textContent         = d;
            gridEl.appendChild(cell);
        }

        for (var day = 1; day <= dim; day++) {
            var dateKey = toDateKey(year, month, day);
            var cell    = document.createElement('div');
            cell.dataset.calendarDay = '';
            cell.dataset.date        = dateKey;
            cell.textContent         = day;

            var classes = 'day';
            if (dateKey === state.today) {
                cell.dataset.today = '';
                classes += ' today';
            }

            var hasEvent = false;
            for (var i = 0; i < filtered.length; i++) {
                if (filtered[i].date_key === dateKey) { hasEvent = true; break; }
            }
            if (hasEvent) {
                cell.dataset.hasEvents = '';
                classes += ' event-day';
            }

            cell.className = classes;
            cell.addEventListener('click', onDayClick);
            gridEl.appendChild(cell);
        }

        var totalCells = firstDow + dim;
        var trailing   = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (var n = 1; n <= trailing; n++) {
            var cell = document.createElement('div');
            cell.className           = 'day faded';
            cell.dataset.calendarDay = '';
            cell.dataset.date        = toDateKey(nx.year, nx.month, n);
            cell.dataset.otherMonth  = '';
            cell.textContent         = n;
            gridEl.appendChild(cell);
        }
    }

    function renderDetails(detailsEl, dateKey) {
        var events = filterEvents(state.events).filter(function (ev) {
            return ev.date_key === dateKey;
        });
        if (events.length > 0) {
            var html = '';
            events.forEach(function (ev) {
                html += '<div data-calendar-event>'
                    + '<h3>' + ev.title + '</h3>'
                    + '<p>' + ev.start_time + ' – ' + ev.end_time + '</p>'
                    + ev.description
                    + '</div>';
            });
            detailsEl.innerHTML = html;
        } else {
            detailsEl.innerHTML = '';
        }
    }

    function renderError(detailsEl) {
        detailsEl.innerHTML = '<p>' + (CalendarData.ajax_error || 'Unable to load events. Please try again.') + '</p>';
    }

    // -------------------------------------------------------------------------
    // Event list rendering
    // -------------------------------------------------------------------------

    function renderEventList(listEl, events) {
        if (!listEl) return;

        var filtered = filterEvents(events);
        filtered.sort(function (a, b) {
            if (a.date_key < b.date_key) return -1;
            if (a.date_key > b.date_key) return  1;
            return a.start_time_raw - b.start_time_raw;
        });

        if (filtered.length === 0) {
            listEl.innerHTML = '<p class="text-center py-4">No events scheduled this month.</p>';
            return;
        }

        var html = '';
        filtered.forEach(function (ev, i) {
            var dayNum  = parseInt(ev.date_key.substr(8, 2), 10);
            var isFirst = i === 0;

            html += '<div class="event-collapse' + (isFirst ? ' clicked' : '') + '"'
                + ' data-event-date="' + ev.date_key + '">';
            html += '<div class="event-head d-flex">';
            html += '<div class="date d-flex justify-content-center align-items-center">' + dayNum + '</div>';
            html += '<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center flex-grow-1">';
            html += '<div class="copy">';
            html += '<h2>' + ev.title + '</h2>';
            html += '<p>' + formatEventDateLine(ev) + '</p>';
            html += '</div>';
            html += '<div class="more d-flex align-items-center mt-3 mt-md-0">Learn More<span class="icon ms-1">' + (isFirst ? '-' : '+') + '</span></div>';
            html += '</div></div>';
            html += '<div class="event-content"' + (isFirst ? ' style="display:block"' : '') + '>';
            html += '<div class="text">';
            if (ev.description) { html += '<h2>About</h2>' + ev.description; }
            html += '</div></div></div>';
        });

        listEl.innerHTML = html;
    }

    // -------------------------------------------------------------------------
    // Nav label update — all matching elements
    // -------------------------------------------------------------------------

    function updateNavLabels(year, month) {
        var prev = prevMonth(year, month);
        var next = nextMonth(year, month);

        document.querySelectorAll('[data-calendar-month-label]').forEach(function (el) {
            el.textContent = monthLabel(year, month);
        });

        document.querySelectorAll('[data-calendar-nav-prev]').forEach(function (el) {
            var span = el.querySelector('span');
            if (span) span.textContent = monthLabel(prev.year, prev.month);
            el.classList.remove('disabled');
        });

        document.querySelectorAll('[data-calendar-nav-next]').forEach(function (el) {
            var span = el.querySelector('span');
            if (span) span.textContent = monthLabel(next.year, next.month);
        });
    }

    function scrollToEvent(dateKey) {
        var listEl = document.querySelector('[data-calendar-event-list]');
        if (!listEl) return;
        var target = listEl.querySelector('[data-event-date="' + dateKey + '"]');
        if (!target) return;
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        if (!target.classList.contains('clicked')) {
            var moreBtn = target.querySelector('.more');
            if (moreBtn) {
                moreBtn.click();
            } else {
                target.classList.add('clicked');
                var content = target.querySelector('.event-content');
                var icon    = target.querySelector('.icon');
                if (content) content.style.display = 'block';
                if (icon)    icon.textContent = '-';
            }
        }
    }

    // -------------------------------------------------------------------------
    // Event handlers
    // -------------------------------------------------------------------------

    function onDayClick(e) {
        var cell     = e.currentTarget;
        var calendar = cell.closest('[data-calendar]');
        if (!calendar) return;

        calendar.querySelectorAll('[data-calendar-day]').forEach(function (c) {
            delete c.dataset.active;
        });
        cell.dataset.active = '';

        var detailsEl = calendar.querySelector('[data-calendar-details]');
        if (detailsEl) renderDetails(detailsEl, cell.dataset.date);

        scrollToEvent(cell.dataset.date);
    }

    // -------------------------------------------------------------------------
    // AJAX
    // -------------------------------------------------------------------------

    function setLoading(calendars, loading) {
        calendars.forEach(function (cal) {
            if (loading) { cal.dataset.loading = ''; } else { delete cal.dataset.loading; }
        });
    }

    function fetchMonth(month, onSuccess, onError) {
        var calendars = Array.from(document.querySelectorAll('[data-calendar]'));
        setLoading(calendars, true);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', state.fetchUrl, true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.setRequestHeader('X-Calendar-Nonce', state.nonce);

        xhr.onload = function () {
            setLoading(calendars, false);
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    if (data.success) { onSuccess(data.events); } else { onError(); }
                } catch (ex) { onError(); }
            } else { onError(); }
        };

        xhr.onerror = function () { setLoading(calendars, false); onError(); };
        xhr.send('month=' + encodeURIComponent(month));
    }

    function onNavClick(calendar, direction) {
        var gridEl    = calendar.querySelector('[data-calendar-grid]');
        var detailsEl = calendar.querySelector('[data-calendar-details]');
        var listEl    = document.querySelector('[data-calendar-event-list]');

        if (!gridEl) return;

        var nm = direction === 'prev'
            ? prevMonth(state.year, state.month)
            : nextMonth(state.year, state.month);

        fetchMonth(
            toYYYYMM(nm.year, nm.month),
            function (events) {
                state.year   = nm.year;
                state.month  = nm.month;
                state.events = events;
                renderGrid(gridEl, state.year, state.month);
                if (detailsEl) detailsEl.innerHTML = '';
                updateNavLabels(state.year, state.month);
                renderEventList(listEl, state.events);
            },
            function () {
                state.year   = nm.year;
                state.month  = nm.month;
                state.events = [];
                renderGrid(gridEl, state.year, state.month);
                if (detailsEl) renderError(detailsEl);
                updateNavLabels(state.year, state.month);
                renderEventList(listEl, []);
            }
        );
    }

    // -------------------------------------------------------------------------
    // Initialization
    // -------------------------------------------------------------------------

    document.addEventListener('DOMContentLoaded', function () {
        var calendars = document.querySelectorAll('[data-calendar]');
        var listEl    = document.querySelector('[data-calendar-event-list]');

        updateNavLabels(state.year, state.month);
        renderEventList(listEl, state.events);

        calendars.forEach(function (calendar) {
            var gridEl    = calendar.querySelector('[data-calendar-grid]');
            var detailsEl = calendar.querySelector('[data-calendar-details]');

            if (!gridEl) return;

            renderGrid(gridEl, state.year, state.month);
            if (detailsEl) detailsEl.innerHTML = '';

            document.querySelectorAll('[data-calendar-nav-prev]').forEach(function (btn) {
                btn.addEventListener('click', function () { onNavClick(calendar, 'prev'); });
            });
            document.querySelectorAll('[data-calendar-nav-next]').forEach(function (btn) {
                btn.addEventListener('click', function () { onNavClick(calendar, 'next'); });
            });
        });
    });

}());
