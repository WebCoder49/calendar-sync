// calendar_userids array & calendar_timezone set in inline JS
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
            }
        } else {
            console.log("Couldn't load calendar:", request.responseText);
        }
    });
    request.open("POST", "/api/calendars");
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
}

document.querySelector(".calendar-content").addEventListener("scroll", function() {
    document.querySelector(".calendar .side-labels").style.setProperty("--scrolled-by", document.querySelector(".calendar-content").scrollTop + "px");
});

document.getElementById("filter_date").addEventListener("change", function() {
    calendar_load(document.getElementById("filter_date").value);
});

document.getElementById("goto_today").addEventListener("click", function() {
    document.getElementById("filter_date").value = new Date().toISOString().split("T")[0]; // Turn into ISO string and remove time so yyyy-mm-dd.
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
});
document.getElementById("goto_prev").addEventListener("click", function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setDate(date.getDate() - 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
});
document.getElementById("goto_prevmonth").addEventListener("click", function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setMonth(date.getMonth() - 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
});
document.getElementById("goto_next").addEventListener("click", function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setDate(date.getDate() + 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
});
document.getElementById("goto_nextmonth").addEventListener("click", function() {
    let date = new Date(document.getElementById("filter_date").value);
    date.setMonth(date.getMonth() + 1);
    document.getElementById("filter_date").value = date.toISOString().split("T")[0];
    document.getElementById("filter_date").dispatchEvent(new Event("change"));
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
