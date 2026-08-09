<script>
    (function () {
        if (window.__ngrokHeaderPatched) {
            return;
        }
        window.__ngrokHeaderPatched = true;

        const header = 'ngrok-skip-browser-warning';
        const value = 'true';

        const originalFetch = window.fetch.bind(window);
        window.fetch = function (input, init) {
            init = init || {};
            const headers = new Headers(init.headers || {});
            headers.set(header, value);
            init.headers = headers;

            return originalFetch(input, init);
        };

        const originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function () {
            this.addEventListener('loadstart', function setNgrokHeader() {
                try {
                    this.setRequestHeader(header, value);
                } catch (e) {
                    //
                }
                this.removeEventListener('loadstart', setNgrokHeader);
            });
            return originalOpen.apply(this, arguments);
        };
    })();
</script>
