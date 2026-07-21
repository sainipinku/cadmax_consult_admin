# Construction ERP Phase 3 and Phase 4 Implementation Roadmap

## Objective

Translate the approved Construction ERP roadmap into delivery batches that fit the current Laravel + Inertia codebase without breaking the Phase 1 and Phase 2 work already implemented.

## Delivery Principle

Do not build Phase 3 and Phase 4 as one monolith.

Build in vertical slices:

1. migration + model layer
2. service and workflow layer
3. web UI
4. mobile/API endpoints
5. notifications and reports
6. verification and seed updates

## Batch 0 - Stabilization

### Goal

Lock the current Construction ERP base before expanding schema again.

### Scope

- verify current Phase 1 and Phase 2 route coverage
- add missing tests around project, survey, drafting, and permission middleware
- centralize activity log writes
- centralize document writes
- finalize construction permission slug map for later modules

### Output

- stable foundation for later migrations

## Batch 1 - Phase 3 Core Execution Schema

### Goal

Add the project execution backbone.

### Database

- `construction_execution_plans`
- `construction_execution_tasks`
- `construction_execution_task_assignees`
- `construction_daily_progress_reports`
- `construction_daily_progress_items`
- `construction_attendance_records`

### Backend

- models and relations
- execution status flow service
- task assignment service
- DPR save / submit / approve flow
- attendance GPS validation hooks
- activity log integration

### Web UI

- Super Admin execution dashboard
- Admin execution dashboard
- task board by project
- DPR list and detail
- attendance approval screen

### Mobile/API

- assigned tasks
- employee check-in
- task progress update
- DPR entry submit

## Batch 2 - Material and Purchase Management

### Goal

Cover materials, stock, vendors, and purchasing.

### Database

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

### Backend

- PR -> PO approval workflow
- stock movement service
- material issue service
- project-level material consumption aggregation

### Web UI

- vendor master
- purchase request board
- purchase order board
- stock ledger view
- material issue / consumption screens

### Mobile/API

- material receive confirmation
- site material issue confirmation
- material consumption entry

## Batch 3 - Vehicles and Equipment

### Goal

Digitize site mobility and equipment allocation.

### Database

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

### Backend

- trip start / end flow
- route point ingestion
- fuel log service
- maintenance workflow
- equipment issue / return flow

### Web UI

- vehicle register
- live trip monitoring
- fuel and maintenance view
- equipment allocation board
- equipment maintenance register

### Mobile/API

- driver trip start / stop
- route point batch upload
- fuel log submission
- vehicle issue report
- equipment return confirmation

## Batch 4 - Quality and Site Controls

### Goal

Add inspection and site quality workflows before finance starts closing work.

### Database

- `construction_quality_inspections`
- `construction_quality_check_items`

### Backend

- inspection templates
- site inspection submission
- approval and revision loop
- quality score calculation

### Web UI

- inspection planner
- site checklist review
- defect / revision tracker

### Mobile/API

- checklist submit
- photo-backed inspection evidence

## Batch 5 - Phase 4 Finance and Billing

### Goal

Build GST-aware billing and expense tracking.

### Database

- `construction_invoice_sequences`
- `construction_invoices`
- `construction_invoice_items`
- `construction_invoice_taxes`
- `construction_payments`
- `construction_expense_categories`
- `construction_expenses`
- `construction_project_financial_summaries`

### Backend

- invoice numbering service
- GST tax calculation service
- payment posting service
- expense booking flow
- profitability summary refresh

### Web UI

- billing dashboard
- invoice create / approve / export
- payment register
- expense register
- project P&L view

### Mobile/API

- expense capture
- payment acknowledgement if needed later

## Batch 6 - Notifications, Analytics, and Reports

### Goal

Make the ERP operationally useful at scale.

### Database

- `construction_notification_templates`
- `construction_notification_events`
- `construction_notification_deliveries`
- `construction_device_tokens`
- `construction_report_snapshots`
- `construction_project_kpi_snapshots`

### Backend

- event-driven notification dispatch
- queued FCM senders
- report snapshot jobs
- dashboard KPI aggregators

### Web UI

- notification logs
- KPI dashboard
- daily / weekly / monthly reports
- finance and operations reports

### Mobile/API

- notification inbox
- device token registration

## Batch 7 - Production Integrations

### Goal

Move the ERP to production-grade file and integration handling.

### Scope

- S3-backed document storage
- signed private download URLs
- Google Maps route and site views
- Firebase notification delivery
- backup / monitoring readiness

### Code work

- `ConstructionDocumentService` with disk abstraction
- signed document access endpoints
- map data provider service
- notification queue jobs

## Model Plan

Recommended model namespaces:

- `App\Models\Construction\Execution\*`
- `App\Models\Construction\Inventory\*`
- `App\Models\Construction\Fleet\*`
- `App\Models\Construction\Equipment\*`
- `App\Models\Construction\Finance\*`
- `App\Models\Construction\Reporting\*`

Reason:

This keeps the construction domain readable as the number of models grows.

## Controller Plan

Recommended controller structure:

- `App\Http\Controllers\SuperAdmin\Construction\Execution\*`
- `App\Http\Controllers\Admin\Construction\Execution\*`
- `App\Http\Controllers\SuperAdmin\Construction\Inventory\*`
- `App\Http\Controllers\Admin\Construction\Inventory\*`
- `App\Http\Controllers\SuperAdmin\Construction\Finance\*`
- `App\Http\Controllers\Admin\Construction\Finance\*`
- `App\Http\Controllers\Api\Mobile\Construction\*`

## React Page Plan

### Super Admin

- `SuperAdmin/Construction/Execution/Dashboard.jsx`
- `SuperAdmin/Construction/Execution/Tasks.jsx`
- `SuperAdmin/Construction/Execution/DailyProgress.jsx`
- `SuperAdmin/Construction/Inventory/Vendors.jsx`
- `SuperAdmin/Construction/Inventory/Purchases.jsx`
- `SuperAdmin/Construction/Inventory/Materials.jsx`
- `SuperAdmin/Construction/Fleet/Vehicles.jsx`
- `SuperAdmin/Construction/Equipment/Index.jsx`
- `SuperAdmin/Construction/Finance/Dashboard.jsx`
- `SuperAdmin/Construction/Finance/Invoices.jsx`
- `SuperAdmin/Construction/Finance/Expenses.jsx`
- `SuperAdmin/Construction/Reports/Index.jsx`

### Admin

- `Admin/Construction/Execution/Dashboard.jsx`
- `Admin/Construction/Execution/Tasks.jsx`
- `Admin/Construction/Execution/DailyProgress.jsx`
- `Admin/Construction/Inventory/Purchases.jsx`
- `Admin/Construction/Inventory/Materials.jsx`
- `Admin/Construction/Fleet/Vehicles.jsx`
- `Admin/Construction/Equipment/Index.jsx`
- `Admin/Construction/Finance/Invoices.jsx`
- `Admin/Construction/Finance/Expenses.jsx`

## API Plan

### Keep current mobile API group and extend it

Recommended additions:

- `/mobile/construction/tasks/*`
- `/mobile/construction/attendance/*`
- `/mobile/construction/dpr/*`
- `/mobile/construction/materials/*`
- `/mobile/construction/trips/*`
- `/mobile/construction/equipment/*`
- `/mobile/construction/expenses/*`
- `/mobile/construction/notifications/*`

## Seed Plan

Add only the permissions needed per module.

Recommended next permission groups:

- execution task manage / view
- dpr manage / review
- attendance manage / review
- vendor manage
- purchase request manage / approve
- material stock manage
- vehicle manage / track
- equipment manage
- finance invoice manage / approve
- payment manage
- expense manage
- report view
- notification manage
- integration settings manage

## Non-Functional Requirements

### Performance

- route points and KPI dashboards must not query raw large tables for every request
- index every `project_id`, status, and date field used in dashboards
- use background jobs for reports and notifications

### Security

- private documents by default
- maps keys restricted by app type and API
- project-bound authorization on every module
- no finance write endpoints without permission middleware

### Auditability

- all approval, rejection, and financial state transitions must log actor and timestamp
- invoices should never silently renumber

## Approval Gate

Do not start Batch 1 schema changes until this roadmap is approved.

Reason:

Phase 3 and Phase 4 touch many high-coupling areas. If the schema goes in before the batch order is agreed, later cleanup cost will be high.

## Recommended Next Action

After approval, execute:

1. Batch 0 stabilization
2. Batch 1 execution schema
3. Batch 1 web UI

That gives the fastest real progress without breaking the current Construction ERP flow.
