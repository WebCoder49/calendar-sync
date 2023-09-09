// Move now time-of-day indicator
window.setInterval(function() {
    let now_indicator = document.getElementById("now-indicator");
    if(now_indicator != undefined) { // Only on calendar pages
        now_indicator.style.setProperty("--time", calendar_getTime());
    }
}, 60000)

window.addEventListener("load", function() {
    document.body.addEventListener("scroll", function() {
        if(document.body.scrollTop > 350) {
            document.querySelector("hgroup").classList.add("closed");
        } else {
            document.querySelector("hgroup").classList.remove("closed");
        }
    });

    document.body.addEventListener("scroll", function() {
        if(document.querySelector(".calendar") !== null) {
            if(document.querySelector(".calendar-container").getBoundingClientRect().top <= 0) {
                document.querySelector(".calendar .top-labels").classList.add("sticky");
            } else {
                document.querySelector(".calendar .top-labels").classList.remove("sticky");
            }
        }
    });
});
