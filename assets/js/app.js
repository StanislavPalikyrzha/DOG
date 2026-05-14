import { jsonRequest, request } from "./api.js";
import { formToJson, notify } from "./utils.js";

const state = {
    user: null,
    stats: null,
};

const refs = {
    loginSection: document.querySelector("#loginSection"),
    appSection: document.querySelector("#appSection"),
    loginForm: document.querySelector("#loginForm"),
    loginMessage: document.querySelector("#loginMessage"),
    clearLoginButton: document.querySelector("#clearLoginButton"),
    demoButtons: document.querySelectorAll("[data-demo-username]"),
    logoutButton: document.querySelector("#logoutButton"),
    refreshButton: document.querySelector("#refreshButton"),
    statsGrid: document.querySelector("#statsGrid"),
    sessionBadge: document.querySelector("#sessionBadge"),
};

function clearLoginForm({ focus = false } = {}) {
    refs.loginForm.reset();
    refs.loginForm.elements.fake_username.value = "";
    refs.loginForm.elements.fake_password.value = "";
    refs.loginForm.elements.username.value = "";
    refs.loginForm.elements.password.value = "";
    notify(refs.loginMessage, "");

    if (focus) {
        refs.loginForm.elements.username.focus();
    }
}

function renderStats() {
    if (!state.stats) {
        refs.statsGrid.innerHTML = "";
        return;
    }

    const cards = [
        ["Users", state.stats.users],
        ["Templates", state.stats.templates],
        ["Documents", state.stats.documents],
        ["My Documents", state.stats.my_documents],
    ];

    refs.statsGrid.innerHTML = cards
        .map(([label, value]) => `
            <article class="stat-card">
                <span>${label}</span>
                <strong>${value}</strong>
            </article>
        `)
        .join("");
}

async function loadDashboard() {
    if (!state.user) {
        return;
    }

    const payload = await request("dashboard");
    state.stats = payload.stats || null;
    renderStats();
}

function updateAuthView() {
    const authenticated = Boolean(state.user);
    refs.loginSection.classList.toggle("hidden", authenticated);
    refs.appSection.classList.toggle("hidden", !authenticated);
    refs.logoutButton.classList.toggle("hidden", !authenticated);
    refs.sessionBadge.textContent = state.user ? `${state.user.full_name} (${state.user.role})` : "Guest";

    if (authenticated) {
        loadDashboard().catch(handleError);
    } else {
        state.stats = null;
        renderStats();
        clearLoginForm({ focus: true });
    }
}

async function fetchSession() {
    const payload = await request("auth/me");
    state.user = payload.user || null;
    updateAuthView();
}

function handleError(error) {
    console.error(error);
    alert(error.message || "An error occurred.");
}

refs.loginForm.addEventListener("submit", async (event) => {
    event.preventDefault();
    notify(refs.loginMessage, "Signing in...");

    try {
        const payload = formToJson(refs.loginForm);
        const response = await jsonRequest("auth/login", "POST", payload);
        state.user = response.user || null;
        notify(refs.loginMessage, response.message || "Signed in.");
        updateAuthView();
    } catch (error) {
        notify(refs.loginMessage, error.message || "Unable to sign in.", true);
    }
});

refs.clearLoginButton.addEventListener("click", () => {
    clearLoginForm({ focus: true });
});

refs.demoButtons.forEach((button) => {
    button.addEventListener("click", () => {
        refs.loginForm.elements.username.value = button.dataset.demoUsername || "";
        refs.loginForm.elements.password.value = button.dataset.demoPassword || "";
        refs.loginForm.elements.password.focus();
        notify(refs.loginMessage, "");
    });
});

refs.logoutButton.addEventListener("click", async () => {
    try {
        await jsonRequest("auth/logout", "POST", {});
        state.user = null;
        updateAuthView();
    } catch (error) {
        handleError(error);
    }
});

refs.refreshButton.addEventListener("click", () => {
    loadDashboard().catch(handleError);
});

window.addEventListener("pageshow", () => {
    if (!state.user) {
        clearLoginForm();
    }
});

fetchSession().catch(() => {
    updateAuthView();
});
