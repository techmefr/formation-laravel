import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import frLocale from '@fullcalendar/core/locales/fr';

document.addEventListener('DOMContentLoaded', () => {
    const el = document.getElementById('calendar');

    if (!el) {
        return;
    }

    const agencySelect = document.getElementById('agency-filter');
    const mineCheckbox = document.getElementById('mine-filter');
    const canCreate = el.dataset.canCreate === '1';

    const buildUrl = () => {
        const url = new URL(el.dataset.eventsUrl, window.location.origin);
        url.searchParams.set('agency', agencySelect ? agencySelect.value : el.dataset.agency);

        if (mineCheckbox && mineCheckbox.checked) {
            url.searchParams.set('mine', '1');
        }

        return url.toString();
    };

    const calendar = new Calendar(el, {
        plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
        locale: frLocale,
        timeZone: 'UTC',
        initialView: el.dataset.initialView || 'timeGridWeek',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'timeGridWeek,dayGridMonth,listWeek',
        },
        buttonText: {
            today: "Aujourd'hui",
            week: 'Semaine',
            month: 'Mois',
            list: 'Liste',
        },
        slotMinTime: '07:00:00',
        slotMaxTime: '20:00:00',
        allDaySlot: false,
        nowIndicator: true,
        height: 'auto',
        events(info, success, failure) {
            fetch(buildUrl(), { headers: { Accept: 'application/json' } })
                .then((response) => response.json())
                .then(success)
                .catch(failure);
        },
        eventClick(info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
        dateClick(info) {
            if (!canCreate) {
                return;
            }

            const [datePart, timePart] = info.dateStr.split('T');
            const start = timePart ? timePart.slice(0, 5) : '08:00';
            const url = new URL(el.dataset.createUrl, window.location.origin);
            url.searchParams.set('date', datePart);
            url.searchParams.set('start', start);
            window.location.href = url.toString();
        },
    });

    calendar.render();

    if (agencySelect) {
        agencySelect.addEventListener('change', () => calendar.refetchEvents());
    }

    if (mineCheckbox) {
        mineCheckbox.addEventListener('change', () => calendar.refetchEvents());
    }
});
