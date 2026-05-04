document.addEventListener("DOMContentLoaded", setupHubPage);

function setupHubPage() {
    if (localStorage.isBlack) {
        $("html").attr("data-is-dark", false);
    }

    const popularTabstrip = document.querySelector(".kd-tabstrip-popular");

    if (popularTabstrip) {
        popularTabstrip.addEventListener("click", activatePopularTab);
    }

    const allTabstrip = document.querySelector(".kd-tabstrip-all");

    if (allTabstrip) {
        allTabstrip.addEventListener("click", activateAllTab);
    }

    const hashValue = window.location.hash;
    if (hashValue == "") {
        return;
    }

    const tab = document.querySelector("[data-id='" + hashValue.substring(1) + "']");
    if (!tab) {
        return;
    }

    tab.click();
}

function activatePopularTab(e) {
    activateTab(e, ".kd-popular");
}

function activateAllTab(e) {
    if (!e.target.closest("li").querySelector("input")) {
        activateTab(e, ".kd-all");
    }
}

function activateTab(e, containerSelector) {
    const newActiveTab = e.target.closest("li");
    const tabs = newActiveTab.closest("ul").querySelectorAll("li");
    const containers = document.querySelectorAll(containerSelector);

    let newActiveTabIndex = 0;

    for (let i = 0; i < containers.length; i++) {
        if (tabs[i].classList.contains("kd-active")) {
            tabs[i].classList.remove("kd-active");
        }

        if (containers[i].classList.contains("kd-active")) {
            containers[i].classList.remove("kd-active");
        }

        if (tabs[i] == newActiveTab) {
            newActiveTabIndex = i;
        }
    }

    tabs[newActiveTabIndex].classList.add("kd-active");
    containers[newActiveTabIndex].classList.add("kd-active");
    updateUrlWithoutTrailingSlash(tabs[newActiveTabIndex].getAttribute("data-id"));
}

function updateUrlWithoutTrailingSlash(hashValue) {
    const newHash = hashValue.startsWith('#') ? hashValue.substring(1) : hashValue;

    let currentPath = window.location.pathname;
    if (currentPath.endsWith('/')) {
        currentPath = currentPath.slice(0, -1);
    }

    const newRelativeUrl = currentPath + '#' + newHash;

    const stateObject = { route: newHash }; 
    history.pushState(stateObject, "", newRelativeUrl);
}

const filterInput = $("input.filter-components");

filterInput.on("input", function () {
    const filterValue = filterInput.val().toLowerCase();
    const categories = $(".kd-all-c div.kd-category-list");

    categories.each(function () {
        const category = $(this);
        const components = category.find("ul li");

        let hasVisibleComponents = false;
        components.each(function () {
            const component = $(this);
            const isVisible = component.text().toLowerCase().indexOf(filterValue) > -1;
            component.toggle(isVisible);
            if (isVisible) {
                hasVisibleComponents = true;
            }
        });
        category.toggle(hasVisibleComponents);
    });
});