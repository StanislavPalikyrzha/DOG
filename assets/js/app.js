import { jsonRequest, request } from "./api.js";
import { escapeHtml, formToJson, notify } from "./utils.js";

const state = {
    user: null,
    stats: null,
    users: [],
    templates: [],
    activeSection: "dashboard",
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
    sectionTitle: document.querySelector("#sectionTitle"),
    navButtons: document.querySelectorAll(".nav-button"),
    statsGrid: document.querySelector("#statsGrid"),
    sessionBadge: document.querySelector("#sessionBadge"),
    usersList: document.querySelector("#usersList"),
    templatesList: document.querySelector("#templatesList"),
    userForm: document.querySelector("#userForm"),
    userResetButton: document.querySelector("#userResetButton"),
    templateForm: document.querySelector("#templateForm"),
    templateResetButton: document.querySelector("#templateResetButton"),
    templateEditorPanel: document.querySelector("#templateEditorPanel"),
};

function isAdmin() {
    return state.user?.role === "admin";
}

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

function setSection(name) {
    const targetButton = document.querySelector(`[data-section="${name}"]`);
    const isBlocked = targetButton?.dataset.adminOnly === "true" && !isAdmin();

    state.activeSection = isBlocked ? "dashboard" : name;
    refs.sectionTitle.textContent =
        document.querySelector(`[data-section="${state.activeSection}"]`)?.textContent || "Dashboard";

    document.querySelectorAll(".app-section").forEach((section) => {
        section.classList.toggle("hidden", section.id !== `${state.activeSection}Section`);
    });

    refs.navButtons.forEach((button) => {
        button.classList.toggle("active", button.dataset.section === state.activeSection);
    });
}

function updateRoleGatedUi() {
    refs.navButtons.forEach((button) => {
        const adminOnly = button.dataset.adminOnly === "true";
        button.classList.toggle("hidden", adminOnly && !isAdmin());
    });

    refs.templateEditorPanel.classList.toggle("hidden", !isAdmin());

    if (!isAdmin() && state.activeSection === "users") {
        setSection("dashboard");
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
    ].filter(([label]) => isAdmin() || label !== "Users");

    refs.statsGrid.innerHTML = cards
        .map(
            ([label, value]) => `
                <article class="stat-card">
                    <span>${escapeHtml(label)}</span>
                    <strong>${escapeHtml(value)}</strong>
                </article>
            `
        )
        .join("");
}

function renderUsers() {
    if (!isAdmin()) {
        refs.usersList.innerHTML = '<p class="empty-state">This section is available to administrators only.</p>';
        return;
    }

    refs.usersList.innerHTML = state.users.length
        ? state.users
              .map(
                  (user) => `
                    <article class="list-card">
                        <div>
                            <strong>${escapeHtml(user.full_name)}</strong>
                            <span>${escapeHtml(user.username)} • ${escapeHtml(user.role)} • ${user.is_active ? "active" : "blocked"}</span>
                        </div>
                        <div class="mini-actions">
                            <button class="ghost-button" type="button" data-user-edit="${user.id}">Edit</button>
                            <button class="ghost-button danger-button" type="button" data-user-delete="${user.id}">Delete</button>
                        </div>
                    </article>
                `
              )
              .join("")
        : '<p class="empty-state">No users found.</p>';
}

function renderTemplates() {
    refs.templatesList.innerHTML = state.templates.length
        ? state.templates
              .map(
                  (template) => `
                    <article class="list-card">
                        <div>
                            <strong>${escapeHtml(template.title)}</strong>
                            <span>${escapeHtml(template.category)} • roles: ${escapeHtml(template.allowed_roles.join(", "))}</span>
                            <small>${escapeHtml(template.description || "No description")}</small>
                        </div>
                        <div class="mini-actions">
                            ${isAdmin() ? `<button class="ghost-button" type="button" data-template-edit="${template.id}">Edit</button>` : ""}
                            ${isAdmin() ? `<button class="ghost-button danger-button" type="button" data-template-delete="${template.id}">Delete</button>` : ""}
                        </div>
                    </article>
                `
              )
              .join("")
        : '<p class="empty-state">No templates found.</p>';
}

function resetUserForm() {
    refs.userForm.reset();
    refs.userForm.elements.id.value = "";
    refs.userForm.elements.is_active.checked = true;
}

function resetTemplateForm() {
    refs.templateForm.reset();
    refs.templateForm.elements.id.value = "";
    refs.templateForm.elements.allowed_roles.value = "admin,editor,viewer";
    refs.templateForm.elements.is_active.checked = true;
}

function fillUserForm(userId) {
    const user = state.users.find((item) => item.id === Number(userId));
    if (!user) {
        return;
    }

    refs.userForm.elements.id.value = user.id;
    refs.userForm.elements.full_name.value = user.full_name;
    refs.userForm.elements.username.value = user.username;
    refs.userForm.elements.password.value = "";
    refs.userForm.elements.role.value = user.role;
    refs.userForm.elements.is_active.checked = Boolean(user.is_active);
    setSection("users");
}

function fillTemplateForm(templateId) {
    const template = state.templates.find((item) => item.id === Number(templateId));
    if (!template || !isAdmin()) {
        return;
    }

    refs.templateForm.elements.id.value = template.id;
    refs.templateForm.elements.title.value = template.title;
    refs.templateForm.elements.slug.value = template.slug;
    refs.templateForm.elements.category.value = template.category;
    refs.templateForm.elements.description.value = template.description || "";
    refs.templateForm.elements.placeholders.value = template.placeholders.join(",");
    refs.templateForm.elements.allowed_roles.value = template.allowed_roles.join(",");
    refs.templateForm.elements.is_active.checked = Boolean(template.is_active);
    refs.templateForm.elements.html_content.value = template.html_content;
    setSection("templates");
}

async function loadDashboard() {
    if (!state.user) {
        return;
    }

    const [statsPayload, templatesPayload] = await Promise.all([
        request("dashboard"),
        request("templates"),
    ]);

    state.stats = statsPayload.stats || null;
    state.templates = templatesPayload.items || [];

    if (isAdmin()) {
        const usersPayload = await request("users");
        state.users = usersPayload.items || [];
    } else {
        state.users = [];
    }

    renderAll();
}

function renderAll() {
    refs.sessionBadge.textContent = state.user ? `${state.user.full_name} (${state.user.role})` : "Guest";
    updateRoleGatedUi();
    renderStats();
    renderUsers();
    renderTemplates();
}

function updateAuthView() {
    const authenticated = Boolean(state.user);
    refs.loginSection.classList.toggle("hidden", authenticated);
    refs.appSection.classList.toggle("hidden", !authenticated);
    refs.logoutButton.classList.toggle("hidden", !authenticated);
    refs.sessionBadge.textContent = state.user ? `${state.user.full_name} (${state.user.role})` : "Guest";
    updateRoleGatedUi();

    if (authenticated) {
        loadDashboard().catch(handleError);
    } else {
        state.stats = null;
        state.users = [];
        state.templates = [];
        setSection("dashboard");
        renderAll();
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

refs.navButtons.forEach((button) => {
    button.addEventListener("click", () => setSection(button.dataset.section));
});

refs.userResetButton.addEventListener("click", resetUserForm);
refs.templateResetButton.addEventListener("click", resetTemplateForm);

refs.userForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    try {
        const data = formToJson(refs.userForm);
        if (data.id) {
            await jsonRequest(`users/${data.id}`, "PUT", data);
        } else {
            await jsonRequest("users", "POST", data);
        }

        resetUserForm();
        await loadDashboard();
        setSection("users");
    } catch (error) {
        handleError(error);
    }
});

refs.templateForm.addEventListener("submit", async (event) => {
    event.preventDefault();

    try {
        const data = formToJson(refs.templateForm);
        if (data.id) {
            await jsonRequest(`templates/${data.id}`, "PUT", data);
        } else {
            await jsonRequest("templates", "POST", data);
        }

        resetTemplateForm();
        await loadDashboard();
        setSection("templates");
    } catch (error) {
        handleError(error);
    }
});

document.addEventListener("click", async (event) => {
    const userEdit = event.target.closest("[data-user-edit]");
    const userDelete = event.target.closest("[data-user-delete]");
    const templateEdit = event.target.closest("[data-template-edit]");
    const templateDelete = event.target.closest("[data-template-delete]");

    if (userEdit) {
        fillUserForm(userEdit.dataset.userEdit);
    }

    if (userDelete) {
        if (!window.confirm("Delete this user?")) {
            return;
        }

        try {
            await jsonRequest(`users/${userDelete.dataset.userDelete}`, "DELETE", {});
            await loadDashboard();
        } catch (error) {
            handleError(error);
        }
    }

    if (templateEdit) {
        fillTemplateForm(templateEdit.dataset.templateEdit);
    }

    if (templateDelete) {
        if (!window.confirm("Delete this template?")) {
            return;
        }

        try {
            await jsonRequest(`templates/${templateDelete.dataset.templateDelete}`, "DELETE", {});
            await loadDashboard();
        } catch (error) {
            handleError(error);
        }
    }
});

window.addEventListener("pageshow", () => {
    if (!state.user) {
        clearLoginForm();
    }
});

fetchSession().catch(() => {
    updateAuthView();
});
