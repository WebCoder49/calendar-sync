/* Seamless links that link to other pages without unloading */
function seamless_linkClicked(target_a) {
    if(new URL(target_a.href).origin == new URL(window.location).origin) {
        // Same-site so seamless
        window.history.pushState({}, "", target_a.href);

        seamless_load(target_a.pathname + target_a.search + target_a.hash);

        return false; // Not default behaviour
    }
    return true; // default behaviour
}

/* Seamlessly load this page. */
function seamless_loadThisPage() {
    seamless_load(window.location.pathname + window.location.search + window.location.hash);
}

/* Seamlessly load a page by its path in this domain */
function seamless_load(path) {
    document.getElementById("seamless-progress").className = "halfloaded";

    let request = new XMLHttpRequest();
    request.addEventListener("load", function() {
        if (request.readyState === XMLHttpRequest.DONE && request.status == 200) {
            // Seamlessly load page in callback
            document.querySelector("main").innerHTML = this.responseText;
            document.body.classList.remove("unloaded");
            document.querySelector("title").innerHTML = document.querySelector("main h1").innerHTML + " | Calendar Sync";
            document.querySelector("header").classList.remove("open");
            if(document.querySelector(".calendar") == undefined) {
                document.querySelector("hgroup").classList.add("search-disabled");
            } else {
                document.querySelector("hgroup").classList.remove("search-disabled");
                document.querySelector("search").scrollIntoView();
            }
            if(document.getElementById("filter_view_as").value == "summary") {
                document.querySelector(".calendar-container").classList.add("summary");
            }
            document.querySelectorAll("main script").forEach((elem) => {
                // Credits: https://stackoverflow.com/a/26716182
                if (elem.src != "") {
                    var tag = document.createElement("script");
                    tag.src = elem.src;
                    document.getElementsByTagName("head")[0].appendChild(tag);
                }
                else {
                    eval.call(window, elem.innerHTML); // Global (window) scope
                }
            })

            let randomToken = Math.random(); // So this load can be uniquely identified
            document.getElementById("seamless-progress").className = `fullyloaded r-${randomToken}`;
            window.setTimeout(() => {
                if(document.getElementById("seamless-progress").classList.contains(`r-${randomToken}`)) {
                    // No loading since so clear progress bar
                    document.getElementById("seamless-progress").className = "";
                }
            }, 500);

        } else {
            window.location = "/_seamless" + path; // View error
        }
    });
    request.open("GET", "/_seamless" + path);
    request.send();
}

/* Create new hyperlink element for <a is="seamless-a"> which seamlessly
loads same-domain pages. */
class SeamlessAnchorElement extends HTMLAnchorElement {
    constructor() {
        super();
        this.onclick = function(evt) {
            return seamless_linkClicked(this);
        }.bind(this);
    }
}

customElements.define("seamless-a", SeamlessAnchorElement, {extends: "a"});

window.addEventListener("load", seamless_loadThisPage);
