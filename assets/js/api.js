const API_URL = "api.php";

async function parseResponse(response) {
    const contentType = response.headers.get("content-type") || "";

    if (!contentType.includes("application/json")) {
        const text = await response.text();
        throw new Error(text || "The server returned a non-JSON response.");
    }

    const payload = await response.json();

    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || "Request failed.");
    }

    return payload;
}

export async function request(route = "", options = {}) {
    const response = await fetch(`${API_URL}?route=${encodeURIComponent(route)}`, {
        credentials: "same-origin",
        ...options,
    });

    return parseResponse(response);
}

export function jsonRequest(route, method = "GET", body = null) {
    return request(route, {
        method,
        headers: {
            "Content-Type": "application/json",
        },
        body: body ? JSON.stringify(body) : null,
    });
}
