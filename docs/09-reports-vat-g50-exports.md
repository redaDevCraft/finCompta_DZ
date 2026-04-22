# 09 - Reports, VAT/G50, and Exports

## Purpose

Explain reporting modules, VAT/G50-oriented logic, and asynchronous export workflow.

## Reports Covered

- VAT report
- Bilan
- Aged receivables/payables
- Analytic trial balance
- Management predictions

## VAT and G50 Positioning

- VAT computations are built by `VatReportService`.
- Tax rates include reporting metadata (`G50-L*`) used for compliance-oriented mapping.
- Current implementation focuses on VAT aggregation and exports; it is not a full standalone G50 form editor.

## Async Export Architecture

Large exports are queued:

1. User requests export.
2. App creates `report_run`.
3. Background job generates artifact.
4. User tracks status in report runs page.
5. User downloads final file.

```mermaid
flowchart TD
    UserExport[UserExportRequest] --> QueueCreate[ReportRunServiceQueue]
    QueueCreate --> JobWorker[ReportsQueueWorker]
    JobWorker --> ArtifactReady[FileStoredAndRunCompleted]
    ArtifactReady --> RunsPage[ReportsRunsPage]
    RunsPage --> Download[DownloadEndpoint]
```

## Rate-Limit Protections

Exports and poll/download endpoints are throttled to protect worker capacity and bandwidth.

## Edge Cases

- User closes page during generation: run continues server-side.
- Job failure: run status records failure and message.
- Expired artifact: download fails gracefully and should be regenerated.

## Beginner note

Reports summarize accounting records already posted. If source entries are wrong or missing, report outputs will reflect that.

## Developer note

New heavy reports should follow the existing `ReportRunService` + queue model instead of synchronous generation.

## Related Files

- `app/Http/Controllers/ReportController.php`
- `app/Http/Controllers/ReportRunController.php`
- `app/Services/VatReportService.php`
- `app/Services/BilanService.php`
- `app/Services/AnalyticReportService.php`
- `app/Services/Reports/ReportRunService.php`
- `app/Jobs/Reports/*`
- `resources/js/Pages/Reports/*`

