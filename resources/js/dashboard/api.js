export class ApiError extends Error {
    constructor(status, message, errors = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors;
    }
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function setCsrfToken(token) {
    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
}

export async function request(method, url, body = null, { idempotencyKey = null } = {}) {
    const headers = {
        Accept: 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
        'X-Requested-With': 'XMLHttpRequest',
    };

    // FormData يمرّ كما هو: تحديد Content-Type يدوياً يمحو حدّ الأجزاء الذي
    // يولّده المتصفح، فيصل الطلب إلى الخادم بلا ملف.
    const isFormData = typeof FormData !== 'undefined' && body instanceof FormData;

    if (body !== null && !isFormData) {
        headers['Content-Type'] = 'application/json';
    }

    if (idempotencyKey) {
        headers['Idempotency-Key'] = idempotencyKey;
    }

    let response;

    try {
        response = await fetch(url, {
            method,
            headers,
            credentials: 'same-origin',
            body: body === null ? undefined : (isFormData ? body : JSON.stringify(body)),
        });
    } catch {
        throw new ApiError(0, 'تعذر الاتصال بالخادم');
    }

    let payload = null;

    try {
        payload = await response.json();
    } catch {
        // empty or non-JSON body
    }

    if (!response.ok) {
        throw new ApiError(response.status, payload?.message ?? 'حدث خطأ غير متوقع', payload?.errors ?? {});
    }

    return payload;
}

export function uploadWithProgress(url, formData, onProgress = () => {}) {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();

        xhr.open('POST', url);
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        });

        xhr.addEventListener('load', () => {
            let payload = null;

            try {
                payload = JSON.parse(xhr.responseText);
            } catch {
                // non-JSON body
            }

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve(payload);
            } else {
                reject(new ApiError(xhr.status, payload?.message ?? 'فشل رفع الملف', payload?.errors ?? {}));
            }
        });

        xhr.addEventListener('error', () => reject(new ApiError(0, 'تعذر الاتصال بالخادم')));
        xhr.addEventListener('abort', () => reject(new ApiError(0, 'تم إلغاء الرفع')));

        xhr.send(formData);
    });
}
