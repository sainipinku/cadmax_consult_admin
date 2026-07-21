# Construction ERP Phase 3 and Phase 4 Research

## Purpose

This document records the repo audit, integration research, architecture decisions, and implementation guardrails for expanding the Construction ERP from the current Phase 1 and Phase 2 foundation into:

- Phase 3: Construction Execution
- Phase 4: Finance, Analytics, Notifications, Storage, Maps, and Production Readiness

It is intentionally opinionated and tied to the current Laravel + Inertia + React codebase.

## Current Repo State

### What already exists in this repo

The current repo already contains a working Construction ERP foundation for:

- company registration
- client registration
- project creation
- budget approval
- project team assignment
- survey planning
- survey execution data capture APIs
- survey submission review
- drafting jobs
- drawing revisions
- drawing approvals
- project-scoped construction permissions
- basic construction activity logs

### Construction domain already implemented

Current construction tables and models cover:

- `construction_companies`
- `construction_roles`
- `construction_permissions`
- `construction_role_permissions`
- `construction_clients`
- `construction_projects`
- `construction_member_role_assignments`
- `construction_project_budgets`
- `construction_project_team_members`
- `construction_documents`
- `construction_activity_logs`
- `construction_survey_plans`
- `construction_survey_plan_members`
- `construction_survey_visits`
- `construction_survey_entries`
- `construction_survey_measurements`
- `construction_survey_submissions`
- `construction_drafting_jobs`
- `construction_drawing_revisions`
- `construction_drawing_approvals`

### Construction domain still missing

The repo does not yet contain dedicated Phase 3 or Phase 4 structures for:

- execution tasks and milestones
- daily progress / DPR
- construction attendance
- vehicle master, trip logs, route history, fuel, maintenance
- equipment master, allocation, return, maintenance
- vendors, purchase requests, purchase orders
- stock ledger, material inward, issue, return, consumption
- quality inspections and site checklists
- finance chart-of-accounts style entities
- invoices, invoice items, payments, taxes, expenses
- project profitability and billing summaries
- notification templates / event-driven notification dispatch
- analytics aggregates / reporting snapshots
- production document storage on AWS S3
- full GPS map visualization for field execution beyond survey

## Architectural Constraints

The following rules must stay true in all later work:

1. Every business table must connect to `project_id` unless it is a true master table.
2. Every write action must create a construction activity log.
3. Every approval must have an explicit workflow status.
4. Every field action must preserve GPS metadata where applicable.
5. Every uploaded document must be stored as a document record and later moved to S3-backed storage.
6. Every module must enforce project-scoped or company-scoped permissions.
7. Later phases must not bypass the current Phase 1 and Phase 2 status flow.

## Recommended Domain Expansion

### Phase 3 modules

Recommended new modules:

- Execution Planning
- Daily Progress Reporting
- Site Attendance
- Task Assignment
- Material Management
- Purchase and Vendor Management
- Vehicle Tracking
- Equipment Allocation
- Quality and Inspection

### Phase 4 modules

Recommended new modules:

- Accounts and Billing
- Invoices and GST
- Expenses and Payments
- Project P&L
- Dashboards and Reports
- Notifications and Escalations
- AWS S3 document storage
- Google Maps monitoring
- Firebase push notifications
- backup and monitoring readiness

## Recommended New Database Plan

### Phase 3 table set

Recommended new tables:

- `construction_execution_plans`
- `construction_execution_tasks`
- `construction_execution_task_assignees`
- `construction_daily_progress_reports`
- `construction_daily_progress_items`
- `construction_attendance_records`
- `construction_quality_inspections`
- `construction_quality_check_items`
- `construction_vehicle_masters`
- `construction_vehicle_allocations`
- `construction_vehicle_trip_logs`
- `construction_vehicle_route_points`
- `construction_vehicle_fuel_logs`
- `construction_vehicle_maintenance_logs`
- `construction_equipment_masters`
- `construction_equipment_allocations`
- `construction_equipment_returns`
- `construction_equipment_maintenance_logs`
- `construction_vendors`
- `construction_purchase_requests`
- `construction_purchase_request_items`
- `construction_purchase_orders`
- `construction_purchase_order_items`
- `construction_material_categories`
- `construction_material_masters`
- `construction_material_stocks`
- `construction_material_stock_movements`
- `construction_material_issues`
- `construction_material_issue_items`
- `construction_material_consumption_logs`

### Phase 4 table set

Recommended new tables:

- `construction_invoice_sequences`
- `construction_invoices`
- `construction_invoice_items`
- `construction_invoice_taxes`
- `construction_payments`
- `construction_expense_categories`
- `construction_expenses`
- `construction_project_financial_summaries`
- `construction_report_snapshots`
- `construction_notification_templates`
- `construction_notification_events`
- `construction_notification_deliveries`
- `construction_device_tokens`
- `construction_project_kpi_snapshots`
- `construction_integration_settings`

## Table Design Rules

### Common columns

Almost every transactional table should include:

- `company_id`
- `project_id`
- status / workflow state
- actor fields like `created_by_member_id` or `approved_by_member_id`
- timestamps
- optional remarks / notes

### GPS-enabled records

Any field activity table should include:

- `latitude`
- `longitude`
- `gps_accuracy_meters`
- `captured_at`
- `captured_by_member_id`
- optional `map_snapshot_document_id`

This applies to:

- attendance
- daily progress
- trip start / stop
- equipment issue / return if done on site
- material issue / consumption if captured in the field
- inspections

### Approval-enabled records

Approval-heavy tables should use an explicit status enum with timestamps and actors, for example:

- `draft`
- `submitted`
- `under_review`
- `approved`
- `revision_requested`
- `rejected`
- `closed`

This applies to:

- purchase requests
- purchase orders
- DPR approval
- inspection approval
- invoices where internal finance review is required

## Execution Workflow Design

### Phase 3 recommended flow

`Drawing Approved -> Execution Plan -> Task Breakdown -> Team Allocation -> Daily Progress -> Material Issue -> Vehicle / Equipment Usage -> Quality Inspection`

### Phase 4 recommended flow

`Measured Work / Billing Milestone -> Invoice Draft -> GST Validation -> Invoice Approval -> Client Billing -> Payment Receipt -> Expense Booking -> Profitability Update -> Closure`

## UI Recommendations

### Super Admin Portal

New Super Admin UI areas:

- execution overview dashboard
- vehicle master + live allocation
- equipment master + maintenance board
- material stock overview
- vendor + purchase approval board
- finance approval dashboard
- KPI dashboard
- reports center
- integration settings

### Admin / Supervisor Portal

New Admin UI areas:

- assigned execution dashboard
- task board by project
- daily progress reporting
- attendance approvals
- vehicle issue and trip monitoring
- equipment issue / return
- purchase request creation
- material issue and consumption
- site quality inspection
- invoice request / billing view

### Mobile surfaces required

Recommended mobile app slices:

- Surveyor: keep current Phase 2 flow
- Driver: trip start, trip stop, route tracking, fuel, issue reporting
- Draft Person: keep current drafting flow
- Site Employee: check-in, task update, DPR item update, material receipt confirmation
- Supervisor mobile or responsive web: attendance, inspections, approvals

## Integration Research Findings

### AWS S3

The repo already has Laravel S3 disk configuration in `config/filesystems.php`, but the current `.env.example` does not yet include a complete production-ready construction document strategy.

Recommended document architecture:

- keep only metadata and storage path in DB
- never store full public URLs in DB
- use S3 object paths like `construction/{project_code}/{module}/{uuid}`
- use private objects by default
- generate time-limited download URLs for protected documents
- keep separate logical folders for:
  - survey evidence
  - drawings
  - DPR attachments
  - invoices
  - inspection reports

Research notes:

- Laravel already supports S3 disks directly through the filesystem abstraction.
- The current app can switch from local to S3 without changing document ownership logic if the document service is centralized.
- Signed URL delivery is the preferred pattern for protected files.

Reference links:

- Laravel filesystem storage guidance: https://laravel.com/docs/12.x/filesystem
- S3 production pattern overview: https://www.gurpreetsandhu.tech/blog/laravel-file-uploads-with-aws-s3-cdn-and-signed-urls-complete-guide

### Firebase Cloud Messaging

The repo already includes:

- `kreait/firebase-php`
- `kreait/laravel-firebase`

Recommended notification architecture:

- store device tokens in a construction-scoped table
- emit app events on workflow changes
- queue notification delivery
- support topic-like project notifications later, but begin with direct device token targeting
- separate notification types:
  - assignment
  - approval requested
  - approval completed
  - task overdue
  - trip deviation alert
  - invoice generated

Research notes:

- the maintained Laravel package still uses the `kreait/laravel-firebase` package name, even though the GitHub org moved in 2026
- Firebase recommends sending from a trusted server environment and handling retries with exponential backoff
- FCM supports direct token, topic, and condition targeting

Reference links:

- Package status: https://packagist.org/packages/kreait/laravel-firebase
- FCM server guidance: https://firebase.google.com/docs/cloud-messaging/server

### Google Maps and GPS

The repo already includes `@react-google-maps/api`, so maps can be used immediately in current React pages.

Recommended usage split:

- browser key for embedded map rendering only
- server-side key or protected service calls for geocoding / distance / routing if needed
- use separate keys per app surface where possible

Recommended Phase 3 usage:

- survey plan location maps
- trip path visualization
- attendance map
- vehicle current position
- project site geofence display
- deviation alerts on vehicle routes later

Research notes:

- Google recommends separate API keys, API restrictions, and application restrictions
- server-side web service calls should not be exposed directly from the client
- map-heavy pages should lazy-load map surfaces to control cost and improve performance

Reference links:

- Maps security best practices: https://developers.google.com/maps/api-security-best-practices
- Maps optimization guidance: https://developers.google.com/maps/optimization-guide

### GST / Invoice Compliance

Phase 4 billing must not be built as a generic invoice CRUD screen. It needs Indian GST-aware invoice structure.

Minimum invoice data model requirements:

- supplier name, address, GSTIN
- unique invoice number per financial year
- issue date
- recipient details
- billing address and delivery address
- place of supply
- HSN / SAC
- taxable value
- tax rates and tax amounts
- reverse charge flag
- total invoice amount
- signature / digital sign metadata

Reference links:

- CBIC invoice rules: https://cbic-gst.gov.in/gst-invoice-rules.html
- Rule 46 reference: https://taxinformation.cbic.gov.in/content/html/tax_repository/gst/rules/cgst_rules/active/chapter6/rule46_v1.00.html

## Recommended Service Layer Additions

Introduce dedicated construction services instead of growing controllers directly:

- `ConstructionDocumentService`
- `ConstructionActivityLogService`
- `ConstructionStatusFlowService`
- `ConstructionNotificationService`
- `ConstructionMapService`
- `ConstructionBillingService`
- `ConstructionInventoryService`
- `ConstructionVehicleTrackingService`
- `ConstructionProjectKpiService`

Why this approach:

- faster controller maintenance
- easier queue integration
- cleaner S3 and notification migration
- one source of truth for activity log writes

## Reporting and Analytics Strategy

Do not calculate every dashboard card live from transactional tables once Phase 3 and 4 data volume grows.

Recommended approach:

- keep transactional truth tables normalized
- generate KPI snapshots daily or event-driven
- use summary tables for:
  - project progress percentage
  - planned vs actual material usage
  - vehicle utilization
  - equipment downtime
  - invoice outstanding
  - expense totals
  - project margin

## Security and Operational Risks

### High-priority risks

1. S3 documents stored publicly by mistake
2. Maps API keys exposed or unrestricted
3. raw FCM tokens stored without lifecycle cleanup
4. finance records without immutable status transitions
5. invoice numbering collisions across financial years
6. GPS fields captured but never validated
7. dashboards querying raw route point tables directly

### Required guardrails

- project and company ownership checks on every query
- queue-based notifications
- soft-delete avoidance on financial ledger records unless legally safe
- immutable invoice number generation service
- centralized document upload and retrieval service
- strict validation around tax fields and financial states

## Recommended Build Order

Even though the final roadmap includes all phases, implementation should continue in this order:

1. stabilize Phase 1 and Phase 2 foundations already added
2. add Phase 3 execution masters and transactions
3. add Phase 3 operational dashboards
4. add Phase 4 billing and expenses
5. add Phase 4 analytics and reports
6. switch document storage to S3-backed service
7. enable Firebase event notifications
8. deepen map monitoring and route visualization

## Practical Conclusion

The repo is ready to extend, but not ready to safely "do all phases" in one direct jump without another schema and service planning pass.

The right next move is:

- approve the Phase 3 and Phase 4 blueprint
- implement the new schema in controlled batches
- add service layer and queue-backed notifications
- then roll UI and mobile/API changes module by module

This avoids rework and keeps the Construction ERP centered on the project lifecycle exactly as intended.
