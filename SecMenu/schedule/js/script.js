var calendar;
var Calendar = FullCalendar.Calendar;
var events = [];

$(function () {
    if (!!scheds) {
        Object.keys(scheds).map(k => {
            var row = scheds[k];
            events.push({
                id: k,
                title: row.title,
                start: row.start_datetime,
                end: row.end_datetime
            });
        });
    }

    calendar = new Calendar(document.getElementById('calendar'), {
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,dayGridWeek,list'
        },
        selectable: true,
        editable: true,
        themeSystem: 'standard',
        events: events,
        eventClick: function (info) {
            const event = scheds[info.event.id];
            if (event) {
                $('#title').text(event.title);
                $('#description').text(event.description);
                $('#start').text(event.sdate);
                $('#end').text(event.edate);
                $('#reschedule').attr('data-id', info.event.id);
                $('#delete').attr('data-id', info.event.id);
                $('#event-details-modal').removeClass('hidden');
            } else {
                alert("Event is undefined");
            }
        },
        eventDidMount: function (info) {
            // Optional: hook for styling events
        }
    });

    calendar.render();

    // Reset the form
    $('#schedule-form').on('reset', function () {
        $(this).find('input:hidden').val('');
        $(this).find('input:visible').first().focus();
    });

    // Reschedule Button
    $('#reschedule').click(function () {
    const id = $(this).attr('data-id');
    location.href = reschedule_hearing.php?edit_id=${id};
  });

    // Delete Button
    $('#delete').click(function () {
        const id = $(this).attr('data-id');
        if (scheds[id]) {
            if (confirm("Are you sure to delete this scheduled event?")) {
                location.href = "./delete_schedule.php?id=" + id;
            }
        } else {
            alert("Event is undefined");
        }
    });
});

// Close modal function
function closeModal() {
    $('#event-details-modal').addClass('hidden');
}