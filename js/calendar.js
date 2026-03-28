const container = document.getElementById('calendar');

const monthNav = document.createElement('div');
monthNav.classList.add('month-nav', 'd-flex', 'align-items-center', 'px-3');

const monthYearDisplay = document.createElement('div');
monthYearDisplay.classList.add('me-auto', 'month', 'text-uppercase');
monthNav.appendChild(monthYearDisplay);

const prevMonthBtn = document.createElement('button');
prevMonthBtn.classList.add('prev', 'border-0', 'fs-4', 'lh-1', 'p-0', 'bg-transparent', 'mx-1', 'bttn');
prevMonthBtn.innerHTML = '<i class="bi bi-caret-left-fill"></i>';
monthNav.appendChild(prevMonthBtn);

const nextMonthBtn = document.createElement('button');
nextMonthBtn.classList.add('next', 'border-0', 'fs-4', 'lh-1', 'p-0', 'bg-transparent', 'mx-1', 'bttn');
nextMonthBtn.innerHTML = '<i class="bi bi-caret-right-fill"></i>';
monthNav.appendChild(nextMonthBtn);

const daysHeader = document.createElement('div');
daysHeader.classList.add('week-days');
['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach((day) => {
	const dayCell = document.createElement('div');
	dayCell.classList.add('day');
	dayCell.textContent = day;
	daysHeader.appendChild(dayCell);
});

const calendarDays = document.createElement('div');
calendarDays.classList.add('days');

container.appendChild(monthNav);
container.appendChild(daysHeader);
container.appendChild(calendarDays);

let currentDate = new Date();
const todayDate = currentDate.getDate();
const todayMonth = currentDate.getMonth();
const todayYear = currentDate.getFullYear();

const eventDays = [{ day: 12 }, { day: 15 }, { day: 20 }, { day: 25 }];

function updateCalendar() {
	const month = currentDate.getMonth();

	const monthName = currentDate.toLocaleString('default', { month: 'long' });
	// monthYearDisplay.textContent = `${monthName} ${todayYear}`;
	monthYearDisplay.textContent = monthName;

	calendarDays.innerHTML = '';

	const firstDay = new Date(todayYear, month, 1).getDay();
	const lastDate = new Date(todayYear, month + 1, 0).getDate();

	const prevMonthLastDate = new Date(todayYear, month, 0).getDate();
	const nextMonthFirstDate = 1;

	for (let i = 0; i < firstDay; i++) {
		const emptyCell = document.createElement('div');
		emptyCell.classList.add('day', 'faded');
		emptyCell.textContent = prevMonthLastDate - firstDay + i + 1;
		calendarDays.appendChild(emptyCell);
	}

	for (let day = 1; day <= lastDate; day++) {
		const dayCell = document.createElement('div');
		dayCell.classList.add('day');

		if (day === todayDate && month === todayMonth && todayYear === currentDate.getFullYear()) {
			dayCell.classList.add('today');
		}

		const event = eventDays.find((event) => event.day === day);
		if (event) {
			dayCell.classList.add('event-day');
		}

		dayCell.textContent = day;
		calendarDays.appendChild(dayCell);
	}

	const totalDaysInGrid = firstDay + lastDate;
	const remainingDays = 42 - totalDaysInGrid;

	for (let i = 0; i < remainingDays; i++) {
		const emptyCell = document.createElement('div');
		emptyCell.classList.add('day', 'faded');
		emptyCell.textContent = nextMonthFirstDate + i;
		calendarDays.appendChild(emptyCell);
	}

	if (currentDate.getFullYear() === todayYear && currentDate.getMonth() === todayMonth) {
		prevMonthBtn.disabled = true;
		prevMonthBtn.classList.add('disabled');
	} else {
		prevMonthBtn.disabled = false;
		prevMonthBtn.classList.remove('disabled');
	}
}

prevMonthBtn.addEventListener('click', () => {
	currentDate.setMonth(currentDate.getMonth() - 1);
	updateCalendar();
});

nextMonthBtn.addEventListener('click', () => {
	currentDate.setMonth(currentDate.getMonth() + 1);
	updateCalendar();
});

updateCalendar();
