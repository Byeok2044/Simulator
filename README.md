# Linear Regression Simulator

A dependency-free PHP simulator with manual data entry, CSV import, predictions, and SVG plots. Functionality and tests use PHP, the simulator page uses one complete HTML file, and styling uses separate CSS files. No JavaScript, Node, or browser-side calculations are used.

Run locally with PHP 8.2 or later:

```sh
php -S 127.0.0.1:8093 -t api
```

Open http://127.0.0.1:8093. The existing Vercel configuration routes requests to `api/index.php`.

File organization:

- `api/pages/regression.html`: the complete simulator page, including its header, forms, CSV preview, editable table, results, plot controls, and footer. There are no separate component templates or PHP code in the HTML.
- `api/assets/styles.css`: page styling, served through `?asset=styles` so it works with the existing Vercel rewrite.
- `api/assets/plot.css`: graph styling, embedded in PHP-generated SVGs to keep downloads self-contained.
- `api/view.php`: prepares the page's display values.
- `api/render.php`: fills the page placeholders and display loops, escaping user values. The HTML comments marked `IF`, `EACH`, and `END` control which sections PHP displays; all markup stays together in the page file.
- `api/controller.php`, `api/csv.php`, `api/regression.php`, and `api/plot.php`: request handling, CSV import, calculations, and SVG generation.

Choose a CSV (up to 2 MB), select its separator and header setting, and click **Preview CSV**. Choose the X and Y columns, then click **Replace table with CSV data**. Click **Run** to fit the model. PHP handles file uploads, parsing, validation, import, regression, predictions, plot generation, and downloads. Uploaded files are processed temporarily without permanent storage. Preview data travels in the form between requests, so no persistent session storage is required.

Quoted fields, escaped quotes, multiline fields, UTF-8 BOM, scientific notation, comma/semicolon/tab separators, and files without headers are supported. Invalid records are rejected unless skipping is explicitly selected. Limits: 5,000 selected numeric pairs, 10,000 source data records, and 200 columns. Values must be finite with magnitude at most 1e100.

The editable table shows 100 rows per page to stay within PHP's default form-field limit. Every action preserves edits on the current page. Run fits the entire dataset, including other pages. Add/remove rows and sample data also use PHP form submissions.

Plot controls support regression/residual views, residual overlays, grid/line toggles, centered zoom, reset, point details, custom axis labels, and self-contained SVG download. Click **Update plot** after changing options. Every plot or prediction action recalculates using the current data. On narrow screens, scroll the plot horizontally. RMSE is sqrt(SSE / n). Correlation and R-squared display N/A for constant Y. Identical X values cannot be fit.

Run checks (PHP DOM is needed for the SVG and HTTP checks):

```sh
php tests/regression.php
php tests/csv.php
php tests/plot.php
php tests/http.php
```

The HTTP checks start a temporary local PHP server and test uploads, imports, predictions, plot controls, SVG downloads, 5,000-point datasets, pagination, and edits. They require local socket access.
