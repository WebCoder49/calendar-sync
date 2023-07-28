window.setInterval(function() {
    let now_indicator = document.getElementById("now-indicator");
    if(now_indicator != undefined) { // Only on calendar pages
        now_indicator.style.setProperty("--time", calendar_gettime());
    }
}, 60000)
