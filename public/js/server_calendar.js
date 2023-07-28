var day_preview_days = new Set();

// calendar_userids array & calendar_timezone set in inline JS

/* Get current time as minutes since midnight */
function calendar_gettime() {
    let date = new Date();
    return (date.getHours()*60) + date.getMinutes();
}

/* Load the calendar */
function calendar_load(date) {
    window.location.hash = date;
    is_loading = date; // Block earlier loads

    let calendar_gui = document.querySelector(".calendar-content");
    calendar_gui.innerText = "";
    calendar_gui.classList.add("loading");

    let request = new XMLHttpRequest();
    request.addEventListener("load", function() {
        if (request.readyState === XMLHttpRequest.DONE && request.status == 200) {
            let response = JSON.parse(request.responseText);
            if("error" in response) {
                console.log("Error when loading calendar:", response["error"]);
                return;
            }
            if(response["date"] == is_loading) { // This is the date currently being loaded - don't want earlier load to override
                let calendar_gui = document.querySelector(".calendar-content");
                calendar_gui.classList.remove("loading");

                let events = response["events"];
                events.forEach((user_events, user_i) => {
                    user_events.forEach((event) => {
                        let event_gui = document.createElement("div");
                        event_gui.classList.add("event");
                        event_gui.classList.add(event["type"]);
                        event_gui.style.setProperty("--starttime", event["start"]);
                        event_gui.style.setProperty("--endtime", event["end"]);
                        event_gui.style.setProperty("--col", user_i);

                        calendar_gui.append(event_gui);
                    });
                });
                let free_slots = response["free_slots"];
                free_slots.forEach((free_slot) => {
                    let slot_gui = document.createElement("div");
                    slot_gui.classList.add("free");
                    slot_gui.style.setProperty("--starttime", free_slot["start"]);
                    slot_gui.style.setProperty("--endtime", free_slot["end"]);

                    slot_gui.innerText = free_slot["description"];

                    calendar_gui.append(slot_gui);
                });

                if(response["date"] == new Date().toISOString().split("T")[0]) {
                    // Today - show now indicator
                    let now_indicator = document.createElement("div");
                    now_indicator.id = "now-indicator";
                    now_indicator.style.setProperty("--time", calendar_gettime());
                    calendar_gui.append(now_indicator);
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
        "user_ids": calendar_userids
    }));

    if(!day_preview_days.has(date)) {
        // New week
        day_preview_days.clear()
        // Load day previews
        dateObj = new Date(date + "T00:00:00Z");
        let daysSinceMonday = (dateObj.getDay() + 6) % 7; // getDay gets days since Sunday
        dateObj.setDate(dateObj.getDate() - daysSinceMonday);

        for(let i = 0; i < 7; i++) {
            document.getElementById("day-preview_date_" + i).innerText = dateObj.getDate();
            let imgElem = document.getElementById("day-preview_" + i);

            let dateStr = dateObj.toISOString().split("T")[0];
            day_preview_days.add(dateStr);

            imgElem.src = "/img/day-preview-placeholder.png";
            imgElem.src = calendar_get_daypreviewbg(dateStr);
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
