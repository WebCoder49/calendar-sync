var weekPreviewDays = new Set();

// calendar_userIDs array & calendar_timezone set in inline JS

/* Get current time as minutes since midnight */
function calendar_getTime() {
    let date = new Date();
    return (date.getHours()*60) + date.getMinutes();
}

/* Convert a number time (e.g. 435, number of minutes since midnight) to a string time (e.g. "07:15") */
function calendar_timeNum2Str(numTime) {
    hours = Math.floor(numTime / 60);
    mins = numTime % 60;
    return hours.toString().padStart(2, '0') + ":" + mins.toString().padStart(2, '0');
    // return hours.toString() + ":" + mins.toString();
}
/* Convert a number duration of minutes (e.g. 435) to a readable string */
function calendar_durationNum2Str(numTime) {
    hours = Math.floor(numTime / 60);
    mins = numTime % 60;
    if(hours == 0) {
        return `${mins}min`;
    }
    if(mins == 0) {
        return `${hours}hr`;
    }
    return `${hours}hr, ${mins}min`;
}

/* Generate the description for a free slot based on the slot JSON. */
function calendar_freeSlotDescription(slot) { // TODO: Finish with function for duration
    return `${calendar_timeNum2Str(slot["start"])}-${calendar_timeNum2Str(slot["end"])} (${calendar_durationNum2Str(slot["end"]-slot["start"])})`;
}

/* Load the calendar */
function calendar_load(date) {
    document.location.hash = date;

    let isLoading = date; // Block earlier loads

    let calendarGUI = document.querySelector(".calendar-content");
    calendarGUI.classList.add("loading");

    let request = new XMLHttpRequest();
    request.addEventListener("load", function() {
        if (request.readyState === XMLHttpRequest.DONE && request.status == 200) {
            let response = JSON.parse(request.responseText);
            if("error" in response) {
                console.log("Error when loading calendar:", response["error"]);
                return;
            }
            if(response["date"] == isLoading) { // This is the date currently being loaded - don't want earlier load to override
                let calendarGUI = document.querySelector(".calendar-content");
                calendarGUI.innerText = "";
                calendarGUI.classList.remove("loading");

                let events = response["events"];
                events.forEach((userEvents, userI) => {
                    userEvents.forEach((event) => {
                        let eventGUI = document.createElement("div");
                        eventGUI.classList.add("event");
                        eventGUI.classList.add(event["type"]);
                        eventGUI.style.setProperty("--starttime", event["start"]);
                        eventGUI.style.setProperty("--endtime", event["end"]);
                        eventGUI.style.setProperty("--col", userI);

                        calendarGUI.append(eventGUI);
                    });
                });
                let freeSlots = response["freeSlots"];
                freeSlots.forEach((freeSlot) => {
                    let slotGUI = document.createElement("div");
                    slotGUI.classList.add("free");
                    slotGUI.style.setProperty("--starttime", freeSlot["start"]);
                    slotGUI.style.setProperty("--endtime", freeSlot["end"]);

                    slotGUI.innerText = calendar_freeSlotDescription(freeSlot);

                    calendarGUI.append(slotGUI);
                });

                if(response["date"] == new Date().toISOString().split("T")[0]) {
                    // Today - show now indicator
                    let nowIndicator = document.createElement("div");
                    nowIndicator.id = "now-indicator";
                    nowIndicator.style.setProperty("--time", calendar_getTime());
                    calendarGUI.append(nowIndicator);
                }
            }
        } else {
            console.log("Couldn't load calendar:", request.responseText);
        }
    });
    request.open("POST", "/api/calendars/json");
    request.setRequestHeader(
        "X-CSRF-TOKEN", document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    );
    request.setRequestHeader(
        "Content-Type", "application/json"
    );
    request.setRequestHeader(
        "Accept", "application/json"
    );

    request.send(JSON.stringify({
        "date": date,
        "timezone": calendar_timezone,
        "userIDs": calendar_userIDs
    }));

    if(!weekPreviewDays.has(date)) {
        // New week
        weekPreviewDays.clear()
        // Load day previews
        dateObj = new Date(date + "T00:00:00Z");
        let daysSinceMonday = (dateObj.getDay() + 6) % 7; // getDay gets days since Sunday
        dateObj.setDate(dateObj.getDate() - daysSinceMonday);

        for(let i = 0; i < 7; i++) {
            document.getElementById("day-preview_date_" + i).innerText = dateObj.getDate();
            let imgElem = document.getElementById("day-preview_" + i);

            let dateStr = dateObj.toISOString().split("T")[0];
            weekPreviewDays.add(dateStr);

            imgElem.src = "/img/day-preview-placeholder.png";
            imgElem.src = calendar_getDayPreviewBG(dateStr);
            if(i == daysSinceMonday) {
                imgElem.parentElement.classList.add("today");
            } else {
                imgElem.parentElement.classList.remove("today");
            }

            imgElem.parentElement.setAttribute("data-date", dateStr);

            dateObj.setDate(dateObj.getDate() + 1); // Increment
        }
    } else {
        dateObj = new Date(date + "T00:00:00Z");
        let daysSinceMonday = (dateObj.getDay() + 6) % 7; // getDay gets days since Sunday
        document.querySelector(".day-preview.today").classList.remove("today");
        document.getElementById("day-preview_" + daysSinceMonday).parentElement.classList.add("today");
    }
}

document.querySelector(".calendar-content").onscroll = function() {
    document.querySelector(".calendar .side-labels").style.setProperty("--scrolled-by", document.querySelector(".calendar-content").scrollTop + "px");
};

document.getElementById("filter_date").onchange = function() {
    calendar_load(document.getElementById("filter_date").value);
};

document.getElementById("goto_today").onclick = function() {
    document.getElementById("filter_date").value = new Date().toISOString().split("T")[0]; // Turn into ISO string and remove time so yyyy-mm-dd.
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
};
document.getElementById("goto_prevday").onclick = function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setDate(date.getDate() - 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
};
document.getElementById("goto_prevweek").onclick = function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setDate(date.getDate() - 7);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
};
document.getElementById("goto_prevmonth").onclick = function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setMonth(date.getMonth() - 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
};
document.getElementById("goto_nextday").onclick = function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setDate(date.getDate() + 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
};
document.getElementById("goto_nextweek").onclick = function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setDate(date.getDate() + 7);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
};
document.getElementById("goto_nextmonth").onclick = function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setMonth(date.getMonth() + 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
};

Array.from(document.getElementsByClassName("day-preview")).forEach(function(elem) {
    elem.addEventListener("click", function() {
        let date = this.getAttribute("data-date");
        if(date != undefined) {
            document.getElementById("filter_date").value = date;
            document.getElementById("filter_date").dispatchEvent(new Event("change"));
        }
    }.bind(elem));
});

if(window.location.hash != "#" && window.location.hash != "") {
    document.getElementById("filter_date").value = window.location.hash.split("#")[1];
} else {
    document.getElementById("filter_date").value = new Date().toISOString().split("T")[0];
}
var is_loading = document.getElementById("filter_date").value; // Date currently being loaded
{
    calendar_load(document.getElementById("filter_date").value);
}
