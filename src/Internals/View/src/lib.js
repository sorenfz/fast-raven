class Lib {
    static CSRF_TOKEN = null;
    static LANG_INTERNAL = null;
    static CURRENT_LANGUAGE = null;

    /**
     * Send a request to an API endpoint.
     * @param {string} api - URL of the API endpoint.
     * @param {string} method - HTTP method to use (e.g. GET, POST, PUT, DELETE).
     * @param {Object} [data] - Optional data to send with the request.
     * @returns {Promise} A promise that resolves with the response from the API endpoint.
     */
    static request(api, method, data = undefined) {
        return new Promise(function(resolve, reject){
            $.ajax({
                url: api,
                method: method,
                data: JSON.stringify(data) ?? data,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': Lib.CSRF_TOKEN ?? ''
                },
                success: function(response){
                    resolve(response);
                },
                error: function(xhr, status, error){
                    reject(error);
                }
            });
        });
    }

    /**
     * Upload file(s) to an API endpoint using FormData.
     * @param {string} api - URL of the API endpoint.
     * @param {File|FileList|HTMLInputElement} file - File(s) to upload or input element.
     * @param {string} [fieldName] - Field name for backend access (default: 'file' or 'files[]').
     * @param {Object} [extraData] - Optional additional form data.
     * @returns {Promise} A promise that resolves with the response from the API endpoint.
     */
    static uploadFile(api, file, fieldName = null, extraData = {}) {
        return new Promise(function(resolve, reject) {
            const formData = new FormData();
            
            if (file instanceof HTMLInputElement) {
                file = file.files.length === 1 ? file.files[0] : file.files;
            }
            
            if (file instanceof FileList) {
                for (let i = 0; i < file.length; i++) {
                    formData.append(fieldName || 'files[]', file[i]);
                }
            } else if (file instanceof File) {
                formData.append(fieldName || 'file', file);
            }
            
            for (const [key, value] of Object.entries(extraData)) {
                formData.append(key, value);
            }
            
            $.ajax({
                url: api,
                method: 'POST',
                data: formData,
                headers: {
                    'X-CSRF-TOKEN': Lib.CSRF_TOKEN ?? ''
                },
                processData: false,
                contentType: false,
                success: function(response) {
                    resolve(response);
                },
                error: function(xhr, status, error) {
                    reject(error);
                }
            });
        });
    }

    /**
     * Change the language: persists the choice in the "lang" cookie (read by the backend on the next render)
     * and swaps the content of all elements with a data-lang attribute.
     * @param {string} lang - Language code (e.g., 'en', 'es').
     */
    static changeLanguage(lang) {
        if (!Lib.LANG_INTERNAL || !Lib.LANG_INTERNAL[lang]) return;

        Lib.CURRENT_LANGUAGE = lang;
        document.cookie = "lang=" + lang + "; path=/; max-age=31536000; SameSite=Lax; Secure";
        document.documentElement.lang = lang;

        const translations = Lib.LANG_INTERNAL[lang];
        $('[data-lang]').each(function() {
            const key = $(this).data('lang');
            if (translations[key] !== undefined) $(this).html(translations[key]);
        });
    }
}

$(document).ready(function() {
    Lib.CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]')?.content;
    Lib.LANG_INTERNAL = JSON.parse(document.getElementById("__lang-internal")?.textContent ?? "{}");
    Lib.CURRENT_LANGUAGE = document.documentElement.lang;
});
