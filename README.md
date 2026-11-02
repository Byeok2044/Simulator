# Linear Regression Simulator

A PHP linear regression tool with manual entry, CSV upload, predictions, and SVG plots. The UI lives in one HTML page with separate CSS files. No JavaScript is required.

Run locally with PHP 8.2 or later:

```sh
php -S 127.0.0.1:8093 -t api
```

Open http://127.0.0.1:8093. Choose a CSV and click **Upload CSV** to import the data and generate results immediately. The first two columns are X and Y; optional text headers become axis labels. Comma, semicolon, and tab separators are detected automatically. Quoted fields, multiline fields, UTF-8 BOM, and scientific notation are supported. Files are processed temporarily without permanent storage.

Limits: 2 MB per CSV, 5,000 imported points, 10,000 source records, and 200 columns. Values must be finite with magnitude at most 1e100. Missing or invalid numeric values produce an error. Extra columns are ignored.

You can also edit values directly, add/remove rows, or load sample data, then click **Run**. Larger datasets show 100 editable rows per page; calculations include all pages. Axis labels, predictions, and extra plot controls are in expandable sections. Graphs support regression/residual views, zoom, residual overlays, and SVG download. RMSE is sqrt(SSE / n); R-squared is undefined for constant Y. X values must vary.

File organization:

- `api/pages/regression.html`: the complete simulator page.
- `api/assets/styles.css`: UI styling, served through `?asset=styles` for the Vercel rewrite.
- `api/assets/plot.css`: graph styling, embedded in SVG downloads.
- `api/view.php` and `api/render.php`: prepare and safely display page values.
- `api/controller.php`, `api/csv.php`, `api/regression.php`, and `api/plot.php`: PHP functionality.

The existing Vercel configuration routes requests to `api/index.php`.
