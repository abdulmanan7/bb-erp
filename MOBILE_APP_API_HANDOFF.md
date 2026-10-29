# Employee Mobile App: Backend API Handoff

This document is for the mobile app developer. It describes everything the backend now provides for the employee app. The full product requirements are in `mobile-app-req.md`. Where this document and that spec differ, this document reflects what is actually implemented.

## 1. Base information

- **Base URL:** `https://<domain>/api` (local dev: `http://localhost:8080/api`)
- **Format:** JSON request and response bodies. Send `Content-Type: application/json`.
- **Timestamps:** all server timestamps are UTC ISO-8601 with a `Z` suffix, for example `2026-10-27T10:58:35Z`. Convert to the project's timezone for display (the `projectTimezone` field is provided on attendance records). Date-only fields (`startDate`, `endDate`, `attendanceDate`) are `YYYY-MM-DD`.
- **Pagination:** list endpoints accept `page` (default 1) and `perPage` (default 20, max 100) and return `{ items, page, perPage, total }`.
- **Errors:** every error returns `{ "error": "<human message>", "code": "<MACHINE_CODE>" }`. The `code` is optional on some errors. Show `error` to the user and branch on `code`.
- **Authentication:** every endpoint except `login`, `refresh`, and `logout` requires `Authorization: Bearer <accessToken>`.
- **Roles:** mobile endpoints require an employee account. Admin accounts get `403`.

## 2. Authentication and sessions

Sessions last up to **365 days** from the first login, unless revoked. Access tokens last **15 minutes** and are refreshed silently.

### Login

`POST /mobile/login`

```json
{ "username": "ali", "password": "secret", "deviceId": "unique-device-id-8+chars", "platform": "android", "deviceName": "Pixel 8" }
```

- `platform` must be `android` or `ios`.
- `deviceId` must be 8 to 100 characters. Generate it once per install and keep it.

Response:

```json
{
  "user": { "role": "employee", "uid": "...", "name": "Ali", "username": "ali", "id": "<employeeId>" },
  "tokens": {
    "accessToken": "...",
    "accessExpiresAt": "2026-10-27T11:13:35Z",
    "refreshToken": "<sessionId>.<secret>",
    "refreshExpiresAt": "2027-10-27T10:58:35Z"
  }
}
```

- `401 INVALID_CREDENTIALS`: wrong username or password.
- `429 RATE_LIMITED`: too many failed attempts (10 per 15 minutes per IP and username).

### Refresh

`POST /mobile/refresh` with `{ "refreshToken": "..." }` returns a **new** `tokens` object.

- **Store the new refresh token every time.** The old one is revoked immediately.
- Presenting an old, already-used refresh token revokes the whole session. This protects against token theft.
- Serialize refresh calls. If several requests hit 401 at once, only one should call `refresh`, and the others should wait for it.
- `401 SESSION_EXPIRED`: sign-in is required.

**Recommended flow:** before each call, if `accessExpiresAt` is within about 60 seconds, refresh first. If any call returns 401, refresh once and retry it. If refresh fails, send the user to login.

### Logout

`POST /mobile/logout` with `{ "refreshToken": "..." }`. This revokes the session and the device's push tokens. It always returns `{ "ok": true }`. Clear local tokens and the offline queue policy as appropriate.

### Account changes

- If an admin changes the employee's password, all existing mobile sessions are revoked. The app gets 401 and must sign in again.
- If an admin disables the employee's login, the employee gets 401 on all calls.
- The same employee can be signed in on several devices at once.

## 3. Device config and push tokens

### Tracking config

`GET /mobile/config` returns the values that drive tracking. Fetch it after login and periodically. Do not hard-code them.

```json
{
  "trackingIntervalSeconds": 180,
  "syncIntervalSeconds": 300,
  "maxAccuracyMeters": 50,
  "maxLocationAgeSeconds": 120,
  "maxLocationBatch": 200,
  "serverTime": "2026-10-27T10:58:35Z"
}
```

Use `serverTime` to detect a skewed device clock.

### Push tokens

- `POST /mobile/push-tokens` with `{ "token": "<FCM token>", "platform": "android|ios", "deviceId": "...", "deviceInfo": "..." }`. Call this after login and on every FCM token refresh.
- `DELETE /mobile/push-tokens` with `{ "token": "..." }`. Call this when the user turns notifications off.
- Push is delivered through **Firebase Cloud Messaging**. On iOS, FCM uses APNs, so the app needs a valid APNs key uploaded to Firebase.

## 4. Projects

`GET /mobile/projects` returns the projects the employee may check in to: active projects with an active assignment.

```json
{ "projects": [ { "id": "...", "name": "Site A", "address": "...", "timezone": "Asia/Karachi" } ] }
```

Project coordinates and radius are deliberately not returned. The geofence is enforced on the server only.

## 5. Attendance

### Current status

`GET /mobile/attendance/status` returns `{ "session": <session> | null, "serverTime": "..." }`.

### Check-in

`POST /mobile/attendance/check-in`

```json
{
  "projectId": "...",
  "latitude": 24.8607,
  "longitude": 67.0011,
  "accuracy": 8,
  "capturedAt": "2026-10-27T10:58:35Z",
  "isMocked": false,
  "eventId": "ci-7f3a9c1e",
  "deviceName": "Pixel 8"
}
```

- `eventId` must be 8 to 64 characters: letters, digits, `-`, or `_`. Generate it once per user action (for example, a UUID without dashes) and **reuse it when retrying**. A replay with the same `eventId` returns `200` with the existing session. It does not create a second check-in.
- `capturedAt` is the device time the GPS fix was taken. The server rejects fixes older than `maxLocationAgeSeconds`.
- `isMocked`: set this from the platform API (Android `Location.isMock()`, iOS `CLLocation.sourceInformation?.isSimulatedBySoftware`). Mocked check-ins are rejected.
- Response: `{ "session": <session> }`.

### Check-out

`POST /mobile/attendance/check-out` takes the same body (minus `projectId`).

- Check-out never fails because of location. If the fix is missing, stale, inaccurate, mocked, or outside the boundary, the check-out still succeeds and the session records an exception. Do not block the employee from leaving.
- Check-out with a repeated `eventId` returns the existing session. Check-out with no open session returns `409 NO_ACTIVE_SESSION`. Treat that as "already checked out": refresh status and show the result.
- Response: `{ "session": <session> }`.

### Session object

```json
{
  "id": "...",
  "employeeId": "...",
  "employeeName": "Ali",
  "designation": "Engineer",
  "projectId": "...",
  "projectName": "Site A",
  "projectTimezone": "Asia/Karachi",
  "attendanceDate": "2026-10-27",
  "status": "active | closed",
  "assignmentActive": true,
  "checkIn":  { "at": "...Z", "accuracy": 8, "distanceM": 15.01, "inside": true },
  "checkOut": { "at": "...Z", "accuracy": 9, "distanceM": 5100.38, "inside": false } ,
  "durationSeconds": 3600,
  "exceptions": ["OUTSIDE_BOUNDARY", "MOCKED_LOCATION"]
}
```

- `durationSeconds` is **on-site time**: time spent inside the project boundary, from location points. It is recalculated as location batches arrive, so it is not a live timer. Label it "On site", not "Elapsed".
- `checkOut` is `null` while the session is active.
- `exceptions` values: `OUTSIDE_BOUNDARY`, `POOR_ACCURACY`, `STALE_LOCATION`, `LOCATION_MISSING`, `MOCKED_LOCATION`, `AUTO_CLOSED`. Show them as "Attendance exceptions" in history detail.
- **Auto-close:** each employee has a shift length (set by admin, default 8 hours). If a session is still open when the shift length has passed, the server closes it at the shift end and sets `AUTO_CLOSED`. The app must not assume an active session stays active. Always refresh status when the app is opened.
- Coordinates are not returned to the employee.

### Location tracking upload (offline queue)

While checked in, the app collects location points and uploads them in batches.

`POST /mobile/attendance/locations`

```json
{
  "sessionId": "...",
  "locations": [
    { "eventId": "loc-000123-abc", "latitude": 24.8609, "longitude": 67.0013, "accuracy": 9, "capturedAt": "2026-10-27T11:00:00Z", "isMocked": false }
  ]
}
```

- Send at most `maxLocationBatch` points per request (200 by default).
- Response:

```json
{
  "accepted":   ["loc-000123-abc"],
  "duplicates": ["loc-000122-abc"],
  "rejected":   [ { "eventId": "...", "reason": "OUTSIDE_SESSION_WINDOW" } ]
}
```

- **Remove events from the queue when they appear in `accepted` or `duplicates`.** Both mean the server has them. Re-sending a duplicate is safe.
- `rejected` reasons: `INVALID_EVENT`, `INVALID_LOCATION`, `OUTSIDE_SESSION_WINDOW` (captured more than 60 seconds before check-in, or after check-out). Drop these from the queue. Do not retry them.
- The server computes `isDelayed` itself (points received more than 5 minutes after capture). The app does not need to flag this.
- Use stable `eventId`s. Duplicate detection depends on them.
- Points must not be invented for gaps. If the OS stops location delivery, the gap is recorded as an interruption.

**Recommended tracking behavior**
- Start tracking only after a successful check-in. Stop it on check-out.
- Use `trackingIntervalSeconds` as the collection interval. Use distance filtering if the platform supports it, to save battery.
- Sync every `syncIntervalSeconds` while online. Sync immediately on reconnect.
- Keep the queue encrypted at rest. Use Android Keystore and iOS Keychain for keys.
- Android: run tracking in a foreground service with a persistent notification. Request background location with a clear disclosure.
- iOS: use Core Location background updates. Expect OS throttling. The backend tolerates gaps and reports them.
- The app should show: tracking active, paused or interrupted, permission required, and last successful sync time.

### History

- `GET /mobile/attendance/history?from=YYYY-MM-DD&to=YYYY-MM-DD&projectId=...&page=1&perPage=20`: paginated list of session objects.
- `GET /mobile/attendance/history/{id}`: one session, `{ "session": <session> }`. Use it for the detail screen.

## 6. Leave requests

- `GET /mobile/leave-types` returns `{ "leaveTypes": [ { "id": "lt-sick", "name": "Sick Leave" } ] }`. Seeded types are Annual, Sick, Casual, and Unpaid.
- `POST /mobile/leaves` with `{ "leaveTypeId": "lt-sick", "startDate": "2026-11-02", "endDate": "2026-11-04", "reason": "..." }`. Returns `201 { "leave": <leave> }`.
  - Reason must be 5 to 500 characters. Maximum 365 days per request.
  - Overlapping a pending or approved request returns `409 LEAVE_OVERLAP`.
- `GET /mobile/leaves?status=pending|approved|rejected|cancelled` (paginated).
- `GET /mobile/leaves/{id}`.
- `POST /mobile/leaves/{id}/cancel`. Only `pending` requests can be cancelled. Otherwise `409 LEAVE_NOT_CANCELLABLE`.

Leave object:

```json
{
  "id": "...", "leaveTypeId": "lt-sick", "leaveTypeName": "Sick Leave",
  "startDate": "2026-11-02", "endDate": "2026-11-04", "days": 3,
  "reason": "...", "status": "pending | approved | rejected | cancelled",
  "adminRemarks": "", "reviewedAt": null, "createdAt": "...Z"
}
```

Statuses are exactly these four. Show `adminRemarks` when present.

## 7. Announcements

- `GET /mobile/announcements` (paginated). Only published announcements visible to this employee (all-employee ones plus any targeted to them). Newest first.
- `GET /mobile/announcements/{id}` returns `{ "announcement": <announcement> }`. Opening it marks it read.

Announcement object: `id`, `title`, `content` (may contain simple HTML from the admin editor, so sanitize or render as plain text), `audience`, `status`, `publishedAt`, `createdBy`, `createdAt`, `updatedAt`, `read` (boolean).

Announcements have no attachments or images yet.

## 8. Notifications

- `GET /mobile/notifications?unread=1&page=1&perPage=20` (paginated).
- `GET /mobile/notifications/{id}` returns `{ "notification": ... }`. Opening it marks it read.

Notification object: `id`, `category` (`leave`, `announcement`, ...), `title`, `body`, `referenceType`, `referenceId`, `read`, `createdAt`.

**Deep linking from push:** the push `data` payload contains `notificationId`, `category`, `referenceType`, and `referenceId`. Map these to screens:
- `category=leave` with `referenceType=leave`: open the leave detail for `referenceId`.
- `category=announcement` with `referenceType=announcement`: open the announcement detail for `referenceId`.

Each notification is created once per event, so the app does not receive duplicate notifications for the same leave decision or announcement publish.

## 9. Screen mapping

| Spec screen | Endpoints |
|---|---|
| Dashboard | `attendance/status`, `notifications?perPage=5`, `announcements?perPage=5` |
| Attendance | `projects`, `attendance/status`, `check-in`, `check-out`, plus tracking and the offline queue |
| Attendance History | `attendance/history`, `attendance/history/{id}` |
| My Projects | `projects` |
| Leave Requests | `leave-types`, `leaves`, `leaves` POST, `leaves/{id}`, `leaves/{id}/cancel` |
| Notifications | `notifications`, `notifications/{id}` |
| Announcements | `announcements`, `announcements/{id}` |
| My Profile | Name and designation are in `status` and history responses. Profile editing is not provided. |
| Logout | `POST /mobile/logout`, then clear local data |

## 10. Error codes

| HTTP | `code` | Meaning and app behavior |
|---|---|---|
| 400 | (none) | Validation error. Show `error`. |
| 401 | `INVALID_CREDENTIALS` | Wrong login. |
| 401 | `SESSION_EXPIRED` | Refresh token dead. Send the user to login. |
| 401 | (none, "Not logged in") | Access token invalid or expired. Refresh once and retry. |
| 403 | `PROJECT_NOT_ASSIGNED` | Not assigned to that project. |
| 403 | (none) | Wrong role (admin account). Not expected for employees. |
| 404 | `PROJECT_NOT_FOUND`, `SESSION_NOT_FOUND` | Refresh local state. |
| 409 | `ALREADY_CHECKED_IN` | Refresh status and show "already checked in". |
| 409 | `NO_ACTIVE_SESSION` | Refresh status. Treat as already checked out. |
| 409 | `LEAVE_OVERLAP`, `LEAVE_NOT_CANCELLABLE`, `ALREADY_PUBLISHED` | Show `error`. |
| 422 | `OUTSIDE_BOUNDARY` | "You are currently outside the allowed attendance area for this project. Please move within the project location and try again." |
| 422 | `LOCATION_REQUIRED` | "We couldn't verify your current location. Please enable location services and try again." |
| 422 | `LOCATION_STALE` | Refresh the GPS fix and retry. |
| 422 | `POOR_ACCURACY` | "GPS accuracy is too low. Move to an open area and try again." |
| 422 | `MOCKED_LOCATION` | Mock or simulated location detected. Explain that mock locations are not allowed. |
| 422 | `PROJECT_INACTIVE` | Project was deactivated. Refresh projects. |
| 422 | `INVALID_BATCH` | Batch empty or larger than `maxLocationBatch`. Split it. |
| 429 | `RATE_LIMITED` | Wait before retrying login. |
| Network | (none) | No connectivity. Check-in and check-out need the network. Location points stay queued. |

Notes on requirement scenarios:
- **Network loss during check-in:** the check-in cannot be recorded without the server. Show an error. Do not record it locally as checked in.
- **Network loss during an active session:** keep location points queued. Check-out also needs the network. Queue it with the same `eventId` and retry on reconnect.
- **Duplicate check-out taps:** the same `eventId` is safe. Two different `eventId`s return `409 NO_ACTIVE_SESSION` for the second.

## 11. Requirements the app must handle

- Every employee-facing timestamp comes from the server. Use `serverTime` from status or config to correct device clock skew.
- Show a clear disclosure before requesting location permission: when location is collected (during active attendance only), why, and that it is shared with administrators.
- Show tracking status visibly during an active session.
- Do not track outside an active session. Stop tracking immediately on check-out.
- Handle the OS killing the app or the phone restarting during a session. On launch, call `attendance/status`. If a session is still active, resume tracking.
- If the employee is unassigned from a project or the project is deactivated mid-session, the server keeps accepting the session's location points and the admin sees the assignment flag. The app does not need to close the session. Check-out still works.
- Do not store the refresh token in plain storage. Use Keychain and Keystore.

## 12. Backend items still open

Tell the backend side before relying on these:

- **FCM push:** notifications are stored in-app regardless. Push is skipped until the server has `mobile.fcmProjectId` and `mobile.fcmServiceAccountPath` set. Push delivery has not been tested against a real Firebase project yet.
- **No push retry:** a failed push is marked `failed` and not retried.
- **Leave and announcement attachments:** not supported.
- **Employee deactivation:** there is no separate active flag. Disabling an employee's login blocks their mobile access.
- **Admin screens** for Projects, Live Attendance, Leave review, and Announcements are built but have not been manually reviewed in a browser.

## 13. Test accounts and environment

Create employee accounts from the admin panel (Employees → enable login). Then create a project, assign the employee, and use the mobile login endpoint.

The seeded admin account (`BBAccounts`) cannot use mobile endpoints.

Run the migration `migrate_attendance_mobile.sql` on the target database before testing. It is also appended to `schema.sql` for new installs.
