# BrooklynAI — Google Integrations Master Plan

**Status:** PLAN ONLY / NOT IMPLEMENTED
**Audit basis:** only files explicitly supplied in this conversation.
No repository, terminal, Git, filesystem, Google Cloud, or live Google API access is assumed.

**Labels used in this document:**

- **APPROVED** — approved by the project owner.
- **VERIFIED** — supported by supplied source or official Google documentation.
- **RECOMMENDED** — proposed technical approach.
- **UNVERIFIED** — not confirmed in this session; short inline note where relevant.
- **PENDING** — design decision deferred until its phase.

---

## 1. Approved Decisions Summary

| Decision | Value |
|---|---|
| Gmail release scope | Only `send_email` |
| Gmail scope | Only `https://www.googleapis.com/auth/gmail.send` |
| Gmail other features | Source preserved; comment out definitions, handler mappings, trigger registration, legacy restricted routes |
| Drive target scope | `drive.file` (per-file access only) |
| Drive API version | v3 |
| Picker | Included; Web and Desktop/Mobile distinguished |
| Existing workflows | May be deleted (no real users, no customer workflows) |
| Preserved data | Users, Google connections, OAuth states, unrelated data |
| Action granularity | Every distinct user-facing operation is a separate Catalog Action |
| Other integrations | Drive, Sheets, Docs, Forms, Calendar, Analytics per approved scope matrix |

### 1.1 Action granularity rule

**APPROVED**

Every distinct user-facing operation must have its own `ActionDefinition` and its own entry in the Integration Catalog. Multiple Catalog Actions may share the same Google API method or the same internal service class. They must not be merged into a single Catalog Action.

This applies uniformly to all integrations. Implementations may reuse API clients, DTOs, and handlers; only the Catalog Actions must remain separate.

### 1.2 Total user-facing actions (current release planning)

| Integration | Action count |
|---|---|
| Gmail | 1 (`send_email`) |
| Drive | 14 |
| Sheets | 21 |
| Docs | 6 |
| Forms | 5 |
| Calendar | 6 |
| Analytics | 6 |
| **Total** | **59** |

---

## 2. Verified Baseline

**VERIFIED**

- Modular Monolith + DDD under `app/Modules/` (Identity, Integrations, Connections, Automation, Execution).
- Runtime: `SchedulerTickService` → `TriggerCoordinator` → `TriggerStrategyRegistry` → `RunWorkflowAction` → `ActionInvoker` → `HandlerRegistry` → `ActionHandler`.
- `Connections` handles OAuth state, encrypted credentials, and `CapabilityScopeMap`-driven scopes.
- `CatalogProjector` projects `IntegrationCatalog` into `GET /api/catalog`.
- `WorkflowCapabilityValidator` validates definition-level `requiredScopes` against connection scopes.
- `WorkflowTriggerActionCompatibilityValidator` enforces `allowedTriggerKeys`.
- `RunWorkflowAction` executes captured snapshots; idempotency via `workflow_id + idempotency_key`.
- `WorkflowModel` uses `SoftDeletes`.
- `executions.workflow_id` → `restrictOnDelete`; `execution_steps.execution_id` → `cascadeOnDelete`; `workflow_triggers.workflow_id` and `workflow_steps.workflow_id` → `cascadeOnDelete`; `executions.retry_of_id` → `nullOnDelete`.
- `workflow_triggers.poll_cursor` is `bigint`.

---

## 3. Gmail — Send-Only Release

### 3.1 Scope

**APPROVED**

- Expose exactly one Gmail action: `send_email`.
- Request exactly one Gmail scope: `https://www.googleapis.com/auth/gmail.send`.

### 3.2 Official scope classifications

**VERIFIED** — `https://developers.google.com/workspace/gmail/api/auth/scopes`

| Scope | Classification |
|---|---|
| `gmail.send` | Sensitive |
| `gmail.labels` | Non-sensitive |
| `gmail.readonly` | Restricted |
| `gmail.compose` | Restricted |
| `gmail.modify` | Restricted |

### 3.3 Per-feature audit and disablement

**VERIFIED** from supplied source.

| Feature | Actual API call(s) | Actual scope | Release action |
|---|---|---|---|
| `send_email` | `users.messages.send` | `gmail.send` | **Keep enabled** |
| `reply_to_email` | `users.threads.get` + `users.messages.send` | `gmail.readonly`/`gmail.modify`/`gmail.metadata` + `gmail.send` | Comment out. Declared `gmail.send` only understates requirement. |
| `send_attachment_links_email` | `users.messages.send` only | `gmail.send` | Comment out — depends on disabled attachment trigger (product decision, not scope) |
| `create_draft` | `users.drafts.create` | `gmail.compose`/`gmail.modify` | Comment out |
| `mark_as_read`, `mark_as_unread`, `archive`, `trash`, `add_label`, `remove_label` | `users.messages.modify` / `users.messages.trash` | `gmail.modify` | Comment out |
| `create_label` | `users.labels.create` | `gmail.labels`/`gmail.modify` | Comment out |
| `new_email_received` trigger | `users.messages.list/get` | `gmail.readonly`/`gmail.modify` | Comment out |
| `new_email_received_with_attachment` trigger | Above + `users.messages.attachments.get` | `gmail.readonly` + `drive.file` | Comment out |
| `GET /api/gmail/labels` (legacy) | `users.labels.list` | `gmail.readonly`/`gmail.labels` | Comment out |
| `POST /api/email/send` (legacy) | `users.messages.send` | `gmail.send` | May remain |

### 3.4 Disablement table (implementation actions)

| Path | Required change | Evidence |
|---|---|---|
| `GmailIntegration.php` definitions | Comment out every `ActionDefinition` except `send_email`; comment out both `TriggerDefinition` | Verified |
| `HandlerRegistry.php` | Comment out every Gmail mapping except `GmailSendEmailHandler` | Verified |
| `ExecutionServiceProvider` / `TriggerStrategyRegistry` | Comment out `GmailPollStrategy` registration | Verified |
| `CapabilityScopeMap.php` | Reduce `gmail` to only `gmail.send`; comment out restricted scopes | Verified |
| Legacy `GET /api/gmail/labels` | Comment out route and controller | Verified |
| Direct callers of restricted Gmail services | **UNVERIFIED** — Phase 1 acceptance includes an exhaustive search across all PHP files for direct calls to `GmailPollStrategy`, `GmailMessageReader`, `GmailMessageModifier`, `GmailLabelCreator`, `GmailAttachmentReader`, `GmailAttachmentMaterializer`, `GmailEmailSender::reply`, `GmailEmailSender::createDraft`, and direct `new GoogleGmailService(...)` outside `HandlerRegistry`. Any hits must be commented out. |

**Note:** `GmailEmailSender::send()` and `SendGmailEmailAction` remain untouched. `GmailEmailSender::reply()` and `GmailEmailSender::createDraft()` are preserved as methods but must have no reachable caller after Phase 1.

### 3.5 Official method references

| Method | URL |
|---|---|
| `users.messages.send` | `https://developers.google.com/gmail/api/reference/rest/v1/users.messages/send` |
| `users.threads.get` | `https://developers.google.com/gmail/api/reference/rest/v1/users.threads/get` |
| `users.drafts.create` | `https://developers.google.com/gmail/api/reference/rest/v1/users.drafts/create` |
| `users.messages.modify` | `https://developers.google.com/gmail/api/reference/rest/v1/users.messages/modify` |
| `users.labels.create` | `https://developers.google.com/gmail/api/reference/rest/v1/users.labels/create` |
| `users.labels.list` | `https://developers.google.com/gmail/api/reference/rest/v1/users.labels/list` |
| `users.messages.attachments.get` | `https://developers.google.com/gmail/api/reference/rest/v1/users.messages.attachments/get` |

---

## 4. Drive — Actions and Triggers

**Scope:** `https://www.googleapis.com/auth/drive.file`
**API version:** v3
**Access limitation:** Only files created by BrooklynAI or selected/shared via Picker. No Drive-wide access.

### 4.1 Actions (14 separate Catalog Actions)

Each action has its own Catalog key, input schema, output schema, and description. Multiple actions share the same API method; that is not grounds for merging them.

| # | Action (Catalog) | API method | `drive.file` | Notes |
|---|---|---|---|---|
| 1 | Create File | `files.create` | Yes | App-created files only; returns file ID |
| 2 | Upload File | `files.create` | Yes | Reuse `GoogleDriveFileUploader`; binary upload |
| 3 | Get File Details | `files.get` | Yes | App-accessible files only |
| 4 | Download File | `files.get` (`alt=media`) | Yes | Return metadata + storage reference; do not pollute execution output with binary |
| 5 | Update File Metadata | `files.update` | Yes | `description`, `properties`, `starred`, etc. — not `name`, not `parents` |
| 6 | Copy File | `files.copy` | Yes | App-accessible source only |
| 7 | Move File | `files.update` (`addParents`/`removeParents`) | Yes | Distinct action from Rename and Update Metadata |
| 8 | Rename File | `files.update` (`name`) | Yes | Distinct action from Move and Update Metadata |
| 9 | Delete File | `files.delete` | Yes | App-accessible files only |
| 10 | Create Folder | `files.create` (`mimeType=application/vnd.google-apps.folder`) | Yes | App-created folders only |
| 11 | List Accessible Files | `files.list` | Yes | Limited to app-accessible files |
| 12 | Search Accessible Files | `files.list` (`q=`) | Yes | Limited to app-accessible files |
| 13 | Share File | `permissions.create` | Yes | User permissions apply; no public sharing by default |
| 14 | Export Google Workspace Files | `files.export` | Yes | **10 MB limit** — `https://developers.google.com/drive/api/reference/rest/v3/files/export` |

### 4.2 Triggers (polling)

| Trigger | Mechanism | Note |
|---|---|---|
| File Created | `changes.list` polling (v3) | App-accessible changes only |
| File Updated | `changes.list` polling (v3) | App-accessible changes only |
| **File Removed from Access** | `changes.list` polling (v3) | **VERIFIED** — `removed: true` means “removed from the change list by deletion or loss of access.” Renamed from `File Deleted` because permanent deletion cannot be distinguished. |

**UNVERIFIED:** Whether `files.get` reliably returns `404` for permanently deleted files versus access loss. Phase 3 sandbox test required before any future definitive `File Deleted` trigger is added.

**References:** `https://developers.google.com/workspace/drive/api/reference/rest/v3/changes/list`, `https://developers.google.com/workspace/drive/api/guides/about-changelogs`.

---

## 5. Sheets — Actions and Triggers

**Scope:** `https://www.googleapis.com/auth/drive.file`

### 5.1 Actions (21 separate Catalog Actions)

**VERIFIED** — each action uses a Sheets API method that accepts `drive.file`. Actions sharing `spreadsheets.batchUpdate` (Create Sheet, Rename Sheet, Delete Sheet, Delete Rows, Conditional Formatting, Format Row) remain distinct Catalog entries.

| # | Action (Catalog) | API method | `drive.file` | Notes |
|---|---|---|---|---|
| 1 | Create Spreadsheet | `spreadsheets.create` | Yes | Creates in user's root Drive — see §5.3 |
| 2 | Get Spreadsheet Details | `spreadsheets.get` | Yes | Returns title, properties, sheets |
| 3 | List Spreadsheet Tabs | `spreadsheets.get` (`fields=sheets.properties`) | Yes | Tab metadata only |
| 4 | Create Sheet (Tab) | `spreadsheets.batchUpdate` (`addSheet`) | Yes | Distinct action |
| 5 | Rename Sheet (Tab) | `spreadsheets.batchUpdate` (`updateSheetProperties`) | Yes | Distinct action |
| 6 | Delete Sheet (Tab) | `spreadsheets.batchUpdate` (`deleteSheet`) | Yes | Distinct action |
| 7 | Read Rows | `values.get` | Yes | Reads rows from a specified sheet/range |
| 8 | Get Row Details | `values.get` | Yes | Single row by index |
| 9 | Get Rows From Range | `values.batchGet` | Yes | Multiple ranges |
| 10 | Lookup Spreadsheet Rows | `values.get` + app-level matching | Yes | Filter by column values |
| 11 | Add New Row | `values.append` | Yes | Single row |
| 12 | Add Multiple Rows | `values.append` | Yes | Batch append |
| 13 | Update Spreadsheet Row | `values.update` | Yes | Single row overwrite |
| 14 | Update Multiple Rows | `values.update` (or `batchUpdate`) | Yes | Multi-row overwrite |
| 15 | Batch Update Cell Values | `values.batchUpdate` | Yes | Multiple ranges in one call |
| 16 | Clear Spreadsheet Row | `values.clear` | Yes | Clear one row |
| 17 | Delete Rows | `spreadsheets.batchUpdate` (`deleteDimension`) | Yes | Distinct action |
| 18 | Add Conditional Formatting Rule | `spreadsheets.batchUpdate` (`addConditionalFormatRule`) | Yes | Distinct action |
| 19 | Format Spreadsheet Row | `spreadsheets.batchUpdate` (`repeatCell`) | Yes | Distinct action |
| 20 | Copy Sheet To Spreadsheet | `sheets.copyTo` | Yes | Copy tab between spreadsheets |
| 21 | Create Spreadsheet From Template | `files.copy` + `spreadsheets.get` | Yes | Compound operation |

**VERIFIED:** All listed methods accept `drive.file` per official Sheets API reference (`https://developers.google.com/sheets/api/reference/rest/v4/`). Access is limited to files created by the app or selected/shared via Picker.

### 5.2 Triggers (polling + snapshot)

| Trigger | Mechanism |
|---|---|
| Spreadsheet Changed | Drive `changes.list` (v3) + `spreadsheets.get` |
| New Row Detected | Polling + snapshot compare |
| Row Updated | Polling + snapshot compare |

**PENDING DESIGN (Phase 4):** stable row identity (user-provided ID column vs. content hash), reorder handling, duplicate suppression, pagination consistency, partial-failure retry.

**VERIFIED:** Google does not provide native row-level Sheets notifications. Row triggers are polling only.

### 5.3 Inline note on `spreadsheets.create`

**UNVERIFIED:** `spreadsheets.create` creates in the user's root Drive and does not accept a parent-folder parameter. Folder-targeted creation would require an additional Drive `files.create` call. Phase 4 sandbox confirms behavior; until confirmed, document that "Create Spreadsheet" creates in root Drive unless a folder-targeted variant is added.

**References:** method pages under `https://developers.google.com/sheets/api/reference/rest/v4/`.

---

## 6. Docs — Actions and Triggers

**Scope:** `https://www.googleapis.com/auth/drive.file`

### 6.1 Actions (6 separate Catalog Actions)

| # | Action (Catalog) | API method | `drive.file` | Notes |
|---|---|---|---|---|
| 1 | Create Document | `documents.create` | Yes | App-created docs only |
| 2 | Get Document Details | `documents.get` | Yes | Metadata only (title, documentId, revisionId) |
| 3 | Read Document Content | `documents.get` | Yes | Extract body content; distinct action from Get Details |
| 4 | Update Document Content | `documents.batchUpdate` | Yes | Insert, delete, replace |
| 5 | Export Document | `files.export` | Yes | Supports PDF, DOCX, etc. |
| 6 | Copy Document | `files.copy` | Yes | Copy app-accessible doc |

**VERIFIED:** Docs methods accept `drive.file` per official Docs API reference (`https://developers.google.com/docs/api/reference/rest/v1/`).

### 6.2 Triggers

- **File-level** (Document Created / Updated / Deleted): Drive `changes.list` (v3) + `documents.get` for access validation.
- **Content-level** (if required): polling `documents.get` and hashing — **PENDING DESIGN (Phase 5).**

**VERIFIED:** Google Docs API does not provide native content-change notifications.

---

## 7. Forms — Actions and Triggers

**Scope:** `https://www.googleapis.com/auth/drive.file`

### 7.1 Actions (5 separate Catalog Actions)

| # | Action (Catalog) | API method | `drive.file` | Notes |
|---|---|---|---|---|
| 1 | Get Form Details | `forms.get` | Yes | Returns form structure |
| 2 | Add Questions | `forms.batchUpdate` (`createItem`) | Yes | Distinct action |
| 3 | Update Form | `forms.batchUpdate` (`updateItem`, `updateFormInfo`) | Yes | Distinct action |
| 4 | Read Form Responses | `forms.responses.list` | Yes | List with filters |
| 5 | Get Response Details | `forms.responses.get` | Yes | Single response |

**VERIFIED:** Forms methods accept `drive.file` per official Forms API reference (`https://developers.google.com/forms/api/reference/rest/v1/`).

### 7.2 Trigger — New Form Response (polling)

**VERIFIED** from `https://developers.google.com/forms/api/reference/rest/v1/forms.responses/list`:

- `filter=timestamp > N` / `timestamp >= N` (RFC3339 UTC).
- `pageSize` (up to 5,000 if unset).
- `pageToken` is pagination only, **not** an incremental cursor.

### 7.3 Polling algorithm (best-effort, not Google-guaranteed)

1. Persist per trigger:
   - `last_seen_submit_time` (max `lastSubmittedTime` across fully processed pages).
   - `processed_response_ids` — **bounded rolling window (recommended 90 days)**; do not store all IDs forever.

2. Poll:

   ```text
   filter = timestamp >= (last_seen_submit_time - overlap)
   ```

   Overlap covers boundary collisions. Choose overlap based on measured visibility delay — **UNVERIFIED**, measure in Phase 8 sandbox.

3. Complete **all** pages (`pageToken` loop) before advancing the checkpoint.

4. Skip any response whose `responseId` is in `processed_response_ids`.

5. Advance `last_seen_submit_time` **only** to the max observed `lastSubmittedTime`. Do **not** advance to `now`.

6. If no responses: do not advance the checkpoint. Retry next tick with the same filter.

**Failure mode documented:** advancing to `now` could skip delayed-visibility responses with older timestamps. The plan does not claim guaranteed delivery.

**Watches:** Forms watches publish to Cloud Pub/Sub and expire after 7 days; renewal via `watches.renew` before expiry. Deferred; polling is the recommended MVP mechanism.

**Reference:** `https://developers.google.com/workspace/forms/api/guides/push-notifications`.

---

## 8. Calendar — Actions and Triggers

### 8.1 Least-privilege scopes (verified)

| Operation | Method | Scope |
|---|---|---|
| List Calendars | `calendarList.list` | `calendar.calendarlist.readonly` |
| Read Events | `events.list`, `events.get` | `calendar.events.readonly` |
| Write Events | `events.insert`, `events.update`, `events.delete` | `calendar.events` |

**VERIFIED:** `calendarList.list` accepts `calendar.calendarlist.readonly` per `https://developers.google.com/workspace/calendar/api/v3/reference/calendarList/list`.

### 8.2 Actions (6 separate Catalog Actions)

| # | Action (Catalog) | API method | Scope |
|---|---|---|---|
| 1 | List Calendars | `calendarList.list` | `calendar.calendarlist.readonly` |
| 2 | List Events | `events.list` | `calendar.events.readonly` |
| 3 | Get Event Details | `events.get` | `calendar.events.readonly` |
| 4 | Create Event | `events.insert` | `calendar.events` |
| 5 | Update Event | `events.update` (or `events.patch`) | `calendar.events` |
| 6 | Delete Event | `events.delete` | `calendar.events` |

### 8.3 Triggers (sync-token polling)

**VERIFIED** from `https://developers.google.com/workspace/calendar/api/v3/reference/events/list`:

- `syncToken` from final `nextSyncToken`.
- `showDeleted` cannot be `false` with `syncToken`.
- Expired token → `410 GONE` → full resync.
- `nextPageToken` for pagination; `nextSyncToken` only on final page.
- Some query params cannot combine with `syncToken`.

**Algorithm:**

1. Initial full sync: `events.list` without `syncToken`; paginate; save `nextSyncToken`.
2. Incremental: `events.list` with saved `syncToken`; process `status="cancelled"` as deletions; paginate; save new `nextSyncToken`.
3. On `410 GONE`: clear `syncToken` and any derived state; perform full sync.
4. Advance checkpoint only after the final page.
5. Dedupe by `event.id` + `event.updated` (or `etag`).

**Triggers:** Event Created, Event Updated, Event Deleted.

**Reference:** `https://developers.google.com/workspace/calendar/api/guides/sync`.

---

## 9. Analytics — Actions and Triggers

### 9.1 Scope

`https://www.googleapis.com/auth/analytics.readonly`

### 9.2 Actions (6 separate Catalog Actions)

| # | Action (Catalog) | API method | Notes |
|---|---|---|---|
| 1 | List Analytics Accounts | Admin API `accountSummaries.list` | Returns account summaries |
| 2 | List Properties | Admin API `properties.list` (or derived from account summaries) | Properties under accounts |
| 3 | Get Property Details | Admin API `properties.get` | Full property metadata |
| 4 | List Custom Dimensions | Admin API `customDimensions.list` | Property custom dimensions |
| 5 | Run Analytics Reports | Data API `runReport` | Reports with dimensions/metrics |
| 6 | List Key Events | Admin API `properties.keyEvents.list` | Key Event definitions |

### 9.3 Trigger — Key Event Created

**VERIFIED:** `properties.keyEvents.list` returns **Key Event definitions**, not conversion occurrences.

Polling compares the current definition list to the last snapshot. Detection is configuration-level: "a new Key Event definition was added to the property."

**Do not describe this trigger as detecting actual conversions.** If conversion-level detection is later required, it is a separate feature using the Data API, not this trigger.

**Reference:** `https://developers.google.com/analytics/devguides/config/admin/v1/rest/v1beta/properties.keyEvents/list`.

---

## 10. Google Picker

**VERIFIED** from `https://developers.google.com/workspace/drive/picker/guides/overview`:

| Feature | Web | Desktop/Mobile |
|---|---|---|
| Technology | JavaScript library | OAuth redirects |
| Rendering | Integrated | System browser (no WebView) |
| Auth | `setOAuthToken` | `trigger_onepick=true` |
| Scopes | Flexible | **Only `drive.file`** |
| Response | JS callback | Redirect URI / custom URL scheme |

**Plan:**

- Web: JS Picker with `drive.file`.
- Mobile: `trigger_onepick=true` OAuth flow; cannot embed web Picker.
- Backend: validate selected IDs with `files.get` before use; store IDs in workflow `config` JSON. No new table.
- Revoked access: step fails with a safe provider error; no credential leakage.
- Do not claim Picker is implemented.

**Reference:** `https://developers.google.com/workspace/drive/picker/guides/desktop-mobile-picker`.

---

## 11. Runtime State

**PENDING — implement with first non-Gmail polling trigger.**

`workflow_triggers.poll_cursor` is `bigint` and cannot store:

- Drive `pageToken` (string)
- Calendar `syncToken` (string)
- Sheets/Docs/Analytics snapshots (JSON)
- Forms last timestamp + response ID set (JSON/string set)

Recommended when needed: nullable `runtime_state JSON` on `workflow_triggers`.

**Design rules when implemented:**

- Per trigger row.
- Updated only after all pages processed successfully.
- Retry does not advance state.
- Invalid sync token → clear state, full resync.
- Concurrency: `SELECT … FOR UPDATE` on the trigger row, or a cache lock per trigger ID.

**Do not add in the Gmail send-only release.**

---

## 12. Workflow Database Cleanup

**APPROVED:** Existing workflows may be deleted. Users, connections, OAuth states, and unrelated data must not be deleted.

**VERIFIED from supplied migrations:**

- `workflows.user_id` → `cascadeOnDelete`
- `workflow_triggers.workflow_id` → `cascadeOnDelete`
- `workflow_steps.workflow_id` → `cascadeOnDelete`
- `executions.workflow_id` → `restrictOnDelete`
- `executions.user_id` → `cascadeOnDelete`
- `execution_steps.execution_id` → `cascadeOnDelete`
- `executions.retry_of_id` → `nullOnDelete`
- `WorkflowModel` uses `SoftDeletes`.

### 12.1 Deletion procedure

**RECOMMENDED** — inside a transaction:

1. Identify target workflow IDs.
2. Delete `execution_steps` for executions of those workflows (or rely on cascade when deleting executions).
3. Delete `executions` for those workflows. **Required** because `restrictOnDelete`.
4. Delete `workflow_triggers` and `workflow_steps` (or rely on cascade from `workflows`).
5. `forceDelete()` the `workflows` rows. **Note:** `forceDelete` is required because `SoftDeletes` causes normal `delete()` to only soft-delete. Confirm hard-delete intent before executing.

Do **not** delete `users`, `connections`, `oauth_states`, `personal_access_tokens`, or any other table.

**RECOMMENDED:** Database backup before deletion.

**Execution method (migration, seeder, or manual DBA):** PENDING — operator decision. Any method must be reversible via backup.

---

## 13. Architecture Impact

| Component | Impact |
|---|---|
| `IntegrationCatalog` | Reflects commented-out Gmail definitions; adds per-action Drive/Sheets/Docs/Forms/Calendar/Analytics definitions |
| `ActionDefinition` / `TriggerDefinition` | Comment out restricted Gmail entries; add 58 new non-Gmail action definitions |
| `CapabilityScopeMap` | `gmail` → only `gmail.send`; add `drive`, `sheets`, `forms`, `calendar`, `docs`, `analytics` capabilities |
| `WorkflowCapabilityValidator` | No change |
| `WorkflowTriggerActionCompatibilityValidator` | No change |
| `CatalogProjector` | No change |
| `HandlerRegistry` | Comment out restricted Gmail mappings; add new handlers per action |
| `ActionInvoker` / `CatalogActionInvoker` | No change |
| `TriggerStrategyRegistry` | Comment out `GmailPollStrategy`; register new strategies in later phases |
| `SchedulerTickService` / `TriggerCoordinator` | No change |
| `RunWorkflowAction` | No change |
| `GoogleCredentialResolver` | No change |
| OAuth / connection handling | New capabilities added; `gmail` scoped to `gmail.send` |
| DB schema | Only `runtime_state` JSON when first non-Gmail polling trigger is implemented |

---

## 14. Implementation Phases

### Phase 1 — Gmail Send-Only Release

**Independent. Can start immediately. No database operations in this phase.**

**Objective:** Expose only `send_email` with only `gmail.send`.

**Modify:**

- `app/Modules/Integrations/Infrastructure/Google/GmailIntegration.php`
- `app/Modules/Execution/Infrastructure/Invokers/HandlerRegistry.php`
- `app/Modules/Execution/Infrastructure/Providers/ExecutionServiceProvider.php`
- `app/Modules/Integrations/Application/Services/CapabilityScopeMap.php`
- `app/Modules/Integrations/Http/Routes/api.php` (`GET /api/gmail/labels`)

**Acceptance criteria:**

- `GET /api/catalog` Gmail entry has exactly one action (`send_email`) and zero triggers.
- `CapabilityScopeMap['gmail']` = `['https://www.googleapis.com/auth/gmail.send']`.
- `send_email` workflow executes end-to-end.
- Exhaustive search confirms no reachable direct caller of restricted Gmail services.
- Existing source files for restricted features remain in repository, commented out.
- Full test suite green.
- No database schema change and no workflow data deletion in this phase.

---

### Phase 2 — Workflow Database Cleanup

**Independent. Can run parallel with Phase 1.**

**Objective:** Delete existing workflows per §12.

**Acceptance criteria:**

- `workflows`, `workflow_triggers`, `workflow_steps`, `executions`, `execution_steps` are empty for target IDs.
- `users`, `connections`, `oauth_states` untouched.
- Backup taken.
- `forceDelete` usage explicitly approved.

---

### Phase 3 — Drive + Picker

**Prerequisites:** Phase 1.

**Objective:** Implement 14 Drive actions, 3 Drive polling triggers, and Picker validation.

**Add:**

- 14 Drive Catalog Actions (§4.1)
- `GoogleDriveChangePollStrategy`
- Picker backend validation (in-workflow `files.get` check; no new endpoint required for MVP)
- `drive` capability (already present)

**Sandbox verify:** `files.export` 10 MB limit; `files.list` limited to app-accessible files; `drive.file` for each of the 14 actions.

**Acceptance criteria:**

- All **14** Drive actions are individually selectable in `GET /api/catalog` and each has its own `action_key`.
- Three Drive triggers work via polling (`File Created`, `File Updated`, `File Removed from Access`).
- Picker-selected file IDs are validated with `files.get`.
- Full test suite green.

---

### Phase 4 — Sheets

**Prerequisites:** Phase 3.

**Objective:** Implement 21 Sheets actions and 3 row triggers.

**Add:**

- 21 Sheets Catalog Actions (§5.1)
- `GoogleSheetsPollStrategy`
- Sheets snapshot design per §5.2
- `sheets` capability with `drive.file`
- `runtime_state` JSON migration (first polling trigger requiring string/JSON state)

**Sandbox verify:** `spreadsheets.create` folder behavior; row identity strategy.

**Acceptance criteria:**

- All **21** Sheets actions are individually selectable in `GET /api/catalog` and each has its own `action_key`.
- Row triggers detect new/updated/deleted rows via snapshot.
- Duplicate suppression works.
- Full test suite green.

---

### Phase 5 — Docs

**Prerequisites:** Phase 3.

**Objective:** Implement 6 Docs actions and file-level triggers.

**Add:**

- 6 Docs Catalog Actions (§6.1)
- `GoogleDocsChangePollStrategy` (file-level via Drive `changes.list` v3)
- `docs` capability with `drive.file`

**PENDING DESIGN:** content-level detection if required.

**Acceptance criteria:**

- All **6** Docs actions are individually selectable in `GET /api/catalog` and each has its own `action_key`.
- File-level triggers detect create/update/delete via Drive changes.
- Full test suite green.

---

### Phase 6 — Calendar

**Prerequisites:** Phase 1.

**Objective:** Implement 6 Calendar actions and sync-token triggers.

**Add:**

- 6 Calendar Catalog Actions (§8.2)
- `GoogleCalendarPollStrategy`
- `calendar` capability with least-privilege scopes

**Acceptance criteria:**

- All **6** Calendar actions are individually selectable in `GET /api/catalog` and each has its own `action_key`.
- Event triggers use sync tokens; `410 GONE` triggers full resync.
- Dedupe via event ID + `updated`.
- Full test suite green.

---

### Phase 7 — Analytics

**Prerequisites:** Phase 1.

**Objective:** Implement 6 Analytics actions and key event polling.

**Add:**

- 6 Analytics Catalog Actions (§9.2)
- `GoogleAnalyticsKeyEventPollStrategy`
- `analytics` capability

**Acceptance criteria:**

- All **6** Analytics actions are individually selectable in `GET /api/catalog` and each has its own `action_key`.
- Key Event trigger detects new key event **definitions**, not conversions.
- Full test suite green.

---

### Phase 8 — Forms

**Prerequisites:** Phase 1.

**Objective:** Implement 5 Forms actions and response polling.

**Add:**

- 5 Forms Catalog Actions (§7.1)
- `GoogleFormsResponsePollStrategy`
- `forms` capability with `drive.file`

**Acceptance criteria:**

- All **5** Forms actions are individually selectable in `GET /api/catalog` and each has its own `action_key`.
- Response trigger uses `timestamp >=` with overlap, full pagination before checkpoint, bounded ID dedupe.
- No claim of guaranteed delivery.
- Full test suite green.

---

### Phase 9 — Final Regression

**Prerequisites:** Phases 1–8.

**Objective:** Full regression, Swagger update, docs finalization.

**Acceptance criteria:**

- Full suite green.
- `GET /api/catalog` exposes exactly **59** user-facing actions across all integrations (1 Gmail + 14 Drive + 21 Sheets + 6 Docs + 5 Forms + 6 Calendar + 6 Analytics).
- Swagger reflects new catalog entries.
- `docs/02_IMPLEMENTATION_STATE.md`, `docs/04_PHASE_GATES.md`, `docs/06_MOBILE_API_CONTRACT.md` updated.
- No credential leakage in execution output.

---

## 15. Risks

| Risk | Mitigation |
|---|---|
| Direct caller bypasses Gmail disablement | Phase 1 exhaustive search |
| Merging distinct Catalog Actions | Code review verifies each action has its own `action_key` |
| Sheets `spreadsheets.create` folder nuance | Phase 4 sandbox test |
| Forms delayed visibility | Overlap window; do not advance to `now` |
| Forms ID set unbounded growth | 90-day rolling retention |
| `forceDelete` required for hard delete | Explicit operator approval |
| Drive `files.export` 10 MB limit | Document limitation |
| Calendar `410 GONE` | Full resync on error |
| Drive `removed` ambiguity | Trigger named "File Removed from Access" |
| Analytics key events are definitions | Document configuration-level detection |

---

## 16. Official Documentation References

| Topic | URL |
|---|---|
| Gmail scopes | `https://developers.google.com/workspace/gmail/api/auth/scopes` |
| Gmail `users.messages.send` | `https://developers.google.com/gmail/api/reference/rest/v1/users.messages/send` |
| Gmail `users.threads.get` | `https://developers.google.com/gmail/api/reference/rest/v1/users.threads/get` |
| Gmail `users.drafts.create` | `https://developers.google.com/gmail/api/reference/rest/v1/users.drafts/create` |
| Gmail `users.messages.modify` | `https://developers.google.com/gmail/api/reference/rest/v1/users.messages/modify` |
| Gmail `users.labels.create` | `https://developers.google.com/gmail/api/reference/rest/v1/users.labels/create` |
| Gmail `users.messages.attachments.get` | `https://developers.google.com/gmail/api/reference/rest/v1/users.messages.attachments/get` |
| Drive scopes | `https://developers.google.com/drive/api/guides/api-specific-auth` |
| Drive `files.create` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/files/create` |
| Drive `files.get` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/files/get` |
| Drive `files.update` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/files/update` |
| Drive `files.copy` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/files/copy` |
| Drive `files.delete` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/files/delete` |
| Drive `files.list` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/files/list` |
| Drive `files.export` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/files/export` |
| Drive `permissions.create` (v3) | `https://developers.google.com/drive/api/reference/rest/v3/permissions/create` |
| Drive `changes.list` (v3) | `https://developers.google.com/workspace/drive/api/reference/rest/v3/changes/list` |
| Drive changelogs guide | `https://developers.google.com/workspace/drive/api/guides/about-changelogs` |
| Sheets API reference | `https://developers.google.com/sheets/api/reference/rest/v4/` |
| Docs API reference | `https://developers.google.com/docs/api/reference/rest/v1/` |
| Forms `responses.list` | `https://developers.google.com/forms/api/reference/rest/v1/forms.responses/list` |
| Forms push notifications | `https://developers.google.com/workspace/forms/api/guides/push-notifications` |
| Calendar scopes | `https://developers.google.com/workspace/calendar/api/auth` |
| Calendar `calendarList.list` | `https://developers.google.com/workspace/calendar/api/v3/reference/calendarList/list` |
| Calendar `events.list` | `https://developers.google.com/workspace/calendar/api/v3/reference/events/list` |
| Calendar sync guide | `https://developers.google.com/workspace/calendar/api/guides/sync` |
| Analytics keyEvents.list | `https://developers.google.com/analytics/devguides/config/admin/v1/rest/v1beta/properties.keyEvents/list` |
| Picker overview | `https://developers.google.com/workspace/drive/picker/guides/overview` |
| Picker desktop/mobile | `https://developers.google.com/workspace/drive/picker/guides/desktop-mobile-picker` |

---

## 17. Inline Notes on Unverified Items

- **Gmail direct-caller search:** UNVERIFIED until Phase 1 exhaustive search completes.
- **Sheets `spreadsheets.create` folder behavior:** UNVERIFIED; Phase 4 sandbox.
- **Forms response visibility delay:** UNVERIFIED; Phase 8 sandbox; use conservative overlap.
- **Drive definitive deletion detection:** UNVERIFIED; trigger uses "File Removed from Access."
- **Docs content-level change detection:** PENDING DESIGN; file-level only in MVP.
- **Sheets row identity:** PENDING DESIGN; Phase 4.
- **Runtime state concurrency:** PENDING DESIGN; implement with first non-Gmail polling trigger.
- **Workflow deletion execution method:** PENDING operator decision; backup required.

None of these block Phase 1 or Phase 2.

---

## 18. Final Restrictions

This document is a plan only. No PHP files, routes, migrations, configuration, packages, OAuth configuration, database structures, branches, commits, or Google integrations were created or modified. No implementation is authorized by this document.

---

## Implementation Order

Start here, in this order:

1. **Phase 1 — Gmail Send-Only Release**
   - Independent. No database operations.
   - Comment out restricted Gmail definitions, handlers, trigger registration, legacy restricted routes.
   - Limit `CapabilityScopeMap['gmail']` to `gmail.send`.
   - Run exhaustive direct-caller search.
   - Ship when full suite green and only `send_email` is reachable.

2. **Phase 2 — Workflow Cleanup**
   - Independent; can run in parallel with Phase 1.
   - Backup, then targeted delete: `execution_steps` → `executions` → `workflow_triggers`/`workflow_steps` → `forceDelete` `workflows`.
   - Preserve users, connections, OAuth states.

3. **Phase 3 — Drive + Picker**
   - Depends only on Phase 1.
   - Adds 14 Drive actions, 3 polling triggers, Picker validation.

4. **Phases 4–8 — Sheets, Docs, Calendar, Analytics, Forms**
   - Phases 4 and 5 depend on Phase 3 (Drive polling pattern reused).
   - Phases 6, 7, 8 depend only on Phase 1.
   - May be reordered or run in parallel based on product priority.

5. **Phase 9 — Final Regression**
   - Depends on Phases 1–8.
   - Confirms 59 total Catalog Actions across all integrations.

**First action to take:** Begin Phase 1. Open `GmailIntegration.php`, comment out all `ActionDefinition` entries except `send_email`, comment out both `TriggerDefinition` entries, and proceed through the disablement table in §3.4.
