export function notify(target, message, isError = false) {
    if (!target) {
        return;
    }

    target.textContent = message;
    target.classList.toggle("error-text", isError);
}

export function formToJson(form) {
    const formData = new FormData(form);
    const payload = {};

    for (const [key, value] of formData.entries()) {
        payload[key] = value;
    }

    return payload;
}
