# Integration tests (optional)

Live calls against a running LecabeInvoicing API are **optional** (**opcional**). The default test suite uses **mocked HTTP** only (`MockHandler`); no network or e-mail provider is required for `composer test`.

To explore the live HTTP contract (**OpenAPI 0.8.x**), open **`{BASE_URL}/docs`** (Scalar) on your API instance when documentation is enabled (`ENABLE_API_DOCS`).

If you add integration tests later, configure:

- **`LECABE_INVOICING_BASE_URL`** — API root (e.g. `https://api.example.com` or `http://127.0.0.1:3000`)
- **`LECABE_INVOICING_API_KEY`** — same value sent as **`X-API-Key`** and **`Authorization: Bearer`**

Keep integration tests behind an explicit env flag or separate PHPUnit suite so CI stays offline-friendly.
