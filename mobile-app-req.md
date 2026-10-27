# Employee Mobile Application & Workforce Attendance Management

## 1. Objective

We are planning to launch an **Employee Mobile Application** integrated with our existing web-based administration system.

The primary purpose is to allow employees to:

- Check in and check out from assigned project locations.
- Automatically share GPS coordinates during working hours.
- Allow administrators to monitor employee locations and attendance.
- View personal attendance history.
- Receive notifications and company announcements.
- Submit and track leave requests.
- Stay logged in for an extended period without needing to authenticate every day.

The admin panel will manage projects, employee assignments, attendance records, location tracking, announcements, notifications, and leave requests.

**Important:** Before making changes, thoroughly analyze the existing codebase, database schema, authentication mechanism, API architecture, admin panel, and mobile application (if already present).

Reuse existing modules, components, services, authentication flows, and database structures wherever possible. Do not create duplicate functionality or unnecessarily modify working features.

---

# 2. Project Management — Admin Panel

Implement or extend a **Projects** module within the admin panel sidebar.

### 2.1 Project Listing

Administrators should be able to:

- View all projects.
- Search and filter projects.
- Create new projects.
- Edit existing projects.
- Activate or deactivate projects.
- Assign employees to projects.
- View employees assigned to each project.

### 2.2 Project Creation and Editing

Each project should contain:

- Project ID (system generated).
- Project name (required).
- Project description (optional).
- Project address.
- Latitude (required).
- Longitude (required).
- Allowed attendance radius in meters (configurable).
- Project status (active/inactive).
- Assigned employees.
- Created and updated timestamps.

### 2.3 Map-Based Location Selection

Provide a map interface allowing administrators to:

- Search for an address.
- Select a location using a map pin.
- Automatically populate latitude and longitude.
- Manually adjust coordinates.
- Configure an allowed attendance radius.
- See the permitted attendance area visually on the map.

Use the existing map provider if one is already configured in the project.

### 2.4 Employee Assignment

- Administrators can assign one or multiple employees to a project.
- An employee may be assigned to multiple projects.
- Only active and authorized project assignments should be available during attendance check-in.
- Maintain employee-project assignment records.

---

# 3. Employee Mobile Application — Navigation

Create or extend the mobile application's side navigation menu.

### Required Menu Items

1. Dashboard
2. Attendance
3. Attendance History
4. Projects / My Projects
5. Leave Requests
6. Notifications
7. Announcements
8. My Profile
9. Logout

Use existing navigation components and design conventions where available.

### Dashboard

Display useful information including:

- Employee name.
- Current attendance status (Checked In / Checked Out).
- Current checked-in project.
- Check-in time.
- Total working duration for the current session.
- GPS/location tracking status.
- Quick Check In / Check Out action.
- Recent notifications and announcements.

Avoid duplicating attendance controls unnecessarily if they already exist in the application.

---

# 4. Attendance — Employee Check-In and Check-Out

## 4.1 Check-In Workflow

When an employee opens the Attendance screen:

1. Display their current attendance status.
2. Display assigned active projects.
3. Allow the employee to select the project where they are working.
4. Retrieve the device's current GPS coordinates.
5. Retrieve GPS accuracy information.
6. Compare the employee's coordinates with the selected project's configured coordinates and attendance radius.
7. Allow or reject check-in according to location validation rules.

### Check-In Conditions

A successful check-in requires:

- The employee is authenticated.
- The employee is active.
- The project is active.
- The employee is assigned to the selected project.
- The device has the necessary location permission.
- A sufficiently accurate and recent GPS location is available.
- The employee is within the project's configured attendance boundary.
- The employee does not already have an active attendance session.

Use a server-side geospatial distance calculation, such as the Haversine formula or an equivalent supported geospatial function.

Do not trust location validation performed only on the mobile client.

### Successful Check-In

Store:

- Employee ID.
- Project ID.
- Check-in timestamp (server generated, UTC).
- Check-in latitude.
- Check-in longitude.
- GPS accuracy.
- Attendance status.
- Device/session identification where appropriate.

Display a success confirmation containing the project name and check-in time.

### Check-In Rejection

Provide clear messages for:

- Employee outside the allowed project area.
- Location permission denied.
- GPS unavailable.
- Poor location accuracy.
- Project not assigned.
- Already checked in.
- Network connectivity unavailable.

Examples:

**Outside project area:**

"You are currently outside the allowed attendance area for this project. Please move within the project location and try again."

**Location unavailable:**

"We couldn't verify your current location. Please enable location services and try again."

---

# 5. Employee Check-Out

Employees must be able to check out of their active attendance session.

When checking out:

1. Retrieve the latest available GPS location.
2. Validate location freshness and accuracy.
3. Calculate the distance from the assigned project's coordinates.
4. Record the employee's exact check-out timestamp.
5. Store check-out coordinates and GPS accuracy.
6. Calculate total attendance duration.
7. Close the attendance session.
8. Stop continuous location tracking.

### Check-Out Location Rules

Check-out location must be recorded and the application must identify whether the employee is inside or outside the project boundary.

**Do not automatically prevent employees from ending a shift just because they are outside the project area.** Record an out-of-boundary exception for administrator review instead, unless the existing business rules explicitly require another behavior.

Handle duplicate check-out attempts safely.

---

# 6. Continuous Employee GPS Tracking During Work Hours

This is one of the most important features.

After a successful employee check-in, the mobile application should start collecting and sending location coordinates to the backend during the active attendance session.

### 6.1 Tracking Behavior

- Start tracking after successful check-in.
- Continue tracking during the active work session, subject to OS permissions and restrictions.
- Stop tracking immediately after successful check-out.
- Do not continuously track employees outside active attendance sessions.
- Support tracking when the app is in the background, within Android and iOS platform limitations.
- Use configurable location collection and synchronization intervals.

Do not use aggressive continuous GPS polling if a more battery-efficient approach is available.

### 6.2 Location Information

Each location record should contain:

- Employee ID.
- Attendance session ID.
- Project ID.
- Latitude.
- Longitude.
- GPS accuracy.
- Device-recorded timestamp.
- Server-received timestamp.
- Tracking source/status if required.

### 6.3 Suggested Tracking Strategy

Use a configurable tracking interval with a reasonable initial default, such as every 2–5 minutes while checked in.

Optimize using:

- Distance-based location changes.
- Battery-efficient location APIs.
- Background location capabilities.
- Network-aware synchronization.
- Duplicate coordinate filtering where appropriate.

The tracking frequency must be configurable without modifying core business logic.

### 6.4 Background Tracking

Implement using platform-appropriate capabilities:

**Android:**
- Appropriate foreground location service where required.
- Required location permissions.
- Persistent notification when foreground service tracking is active.
- Proper handling of background restrictions.

**iOS:**
- Core Location background capabilities where permitted.
- Appropriate permission requests and explanations.
- Support for location updates within iOS background execution limits.

Do not promise uninterrupted location transmission if the operating system restricts or terminates the application.

### 6.5 Offline Tracking

If the device loses internet connectivity:

- Store captured tracking records locally in an encrypted or otherwise appropriately protected queue.
- Retry synchronization when connectivity returns.
- Preserve the original capture timestamps.
- Prevent duplicate records using unique event identifiers.
- Clearly identify delayed/offline records in the backend.
- Apply reasonable storage and retry limits.

Never fabricate missing location records.

### 6.6 Tracking Status

The employee should be able to see:

- Location tracking active.
- Location tracking paused/interrupted.
- Location permission required.
- Last successful synchronization.

Provide clear disclosures explaining when and why employee locations are collected.

---

# 7. Admin Panel — Live Employee Location Monitoring

Create or extend an admin attendance monitoring interface.

### Admin Capabilities

Administrators should be able to:

- View currently checked-in employees.
- View the project where each employee is working.
- See the employee's last reported GPS location on a map.
- See last location update timestamp.
- See whether the location is inside or outside the assigned project boundary.
- View check-in time.
- View elapsed working time.
- View whether tracking data is current, stale, unavailable, or delayed.
- Filter by employee, project, date, and attendance status.

### Map Display

Show employee markers and configured project boundaries.

Marker details should include:

- Employee name.
- Project name.
- Current/last recorded coordinates.
- Last updated time.
- Attendance status.
- Distance from the project center where relevant.

**Important:** Treat map locations as last-known positions, not guaranteed real-time locations. Display timestamps and stale-data indicators.

### Location History

Allow authorized administrators to review location history for a selected employee and attendance session.

Display:

- Recorded coordinates.
- Tracking timestamps.
- Map markers or route visualization.
- Any reported out-of-boundary events.
- Missing or interrupted tracking periods.

Use role-based permissions and prevent unauthorized access to employee location information.

---

# 8. Attendance History — Employee Mobile Application

Employees should be able to view their own attendance records.

### Attendance Listing

Display:

- Attendance date.
- Project name.
- Check-in time.
- Check-out time.
- Total working duration.
- Attendance status.
- Location validation status where applicable.

Provide:

- Date-range filtering.
- Project filtering.
- Pagination or efficient infinite scrolling.
- Empty states.
- Loading and error states.

### Attendance Details

When an employee selects an attendance record, show:

- Project information.
- Check-in time.
- Check-out time.
- Total work duration.
- Check-in location status.
- Check-out location status.
- Any relevant attendance exceptions.

Employees must only be able to access their own attendance history.

---

# 9. Authentication — Long-Lived Employee Sessions

Employees should not have to log in every day.

Implement a secure authentication/session persistence mechanism designed to keep an employee signed in for up to **365 days**, unless their session is revoked or invalidated.

### Security Requirements

Do not implement this by issuing a single access token valid for one year.

Instead, use:

- Short-lived access tokens.
- Secure refresh tokens.
- Refresh token rotation.
- Secure token storage using Android Keystore / iOS Keychain or the framework's equivalent.
- Automatic silent token refresh.
- Server-side refresh session management.

### Expected Behavior

- Employees log in using existing credentials.
- Authentication persists when the app closes and reopens.
- Employees do not need to log in every day.
- Token refresh occurs automatically as required.
- An authorized session can remain renewable for up to 365 days.
- Session expiry or revocation requires login again.
- Logout invalidates the relevant refresh session.
- Disabled users and revoked sessions lose access.
- Password changes and security-sensitive account events should invalidate sessions according to the application's security policy.

Reuse the existing authentication provider and session infrastructure if available.

Do not weaken the security of admin users or other account types to support mobile persistence.

---

# 10. Notifications — Mobile Application

Employees should receive notifications generated by the existing admin panel or backend.

### Requirements

- Integrate the existing notification system where possible.
- Implement mobile push notifications using the appropriate provider, such as Firebase Cloud Messaging and APNs.
- Associate device push tokens with authenticated employees.
- Support token refresh and device registration changes.
- Avoid duplicate notifications.

### Notification Listing

Provide a notification screen displaying:

- Notification title.
- Short description.
- Date/time.
- Read/unread status.
- Notification category if supported.

### Notification Details

Employees should be able to:

- Open a notification.
- View relevant details.
- Mark it as read.
- Navigate to associated content when applicable.

### Navigation

Tapping a push notification should open the relevant mobile screen through appropriate deep linking/navigation.

---

# 11. Announcements — Admin Panel and Mobile Application

Administrators should be able to publish announcements that become available to relevant employees.

### Admin Panel

Create or reuse an announcements module with:

- Announcement title.
- Announcement content.
- Optional attachment or image if existing infrastructure supports it.
- Published date.
- Status (draft/published, if required).
- Target employees or audience.
- Created by.
- Created/updated timestamps.

Administrators should be able to create, edit, publish, and view announcements.

Only published announcements targeted to an employee should be visible to that employee.

### Employee Mobile Application

Implement an Announcements menu containing:

**Announcement Listing**
- Announcement title.
- Short preview.
- Published date.
- Read/unread indicator if supported.
- Most recent announcements first.

**Announcement Detail**
- Full announcement title.
- Full description/content.
- Published date.
- Attachment or image where supported.

### Push Notification

When an announcement is published, send a push notification to its intended mobile audience.

Avoid sending duplicate push notifications when an already-published announcement is edited unless explicitly required.

---

# 12. Leave Request Management — Employee Mobile Application

Employees should be able to submit leave requests through the mobile application.

### Leave Request Form

Include:

- Leave type.
- Start date.
- End date.
- Reason/description.
- Optional attachment if already supported by the backend.

Reuse existing leave types and leave validation rules.

### Leave Request Submission

Employees can:

- Select leave type.
- Select requested dates.
- Enter a reason.
- Submit a request.
- View validation messages.

### Leave History

Show:

- Leave type.
- Start/end dates.
- Requested duration.
- Submission date.
- Approval status.
- Administrator remarks where available.

### Leave Status

Use the existing workflow. Typical statuses may include:

- Pending.
- Approved.
- Rejected.
- Cancelled.

Do not introduce new approval statuses without checking the current backend implementation.

### Admin Integration

Submitted requests must appear within the existing admin leave management workflow.

Admins should be able to review and approve/reject according to existing permissions.

Employees should receive notifications when their leave request status changes.

---

# 13. Backend API and Database Requirements

Inspect the current database schema before creating migrations.

Reuse existing employee, user, project, attendance, notification, announcement, and leave entities whenever practical.

If necessary, extend the data model to support:

### Projects
- Project details.
- Geographic coordinates.
- Attendance radius.
- Status.

### Project Assignments
- Employee ID.
- Project ID.
- Assignment status.

### Attendance Sessions
- Employee ID.
- Project ID.
- Check-in details.
- Check-out details.
- Attendance status.
- Duration.
- Location validation results.

### Attendance Location Logs
- Attendance session ID.
- GPS coordinates.
- Accuracy.
- Capture timestamp.
- Receive timestamp.
- Event identifier.

### Mobile Sessions
- Employee ID.
- Refresh session/token metadata.
- Expiry.
- Revocation status.

### Device Push Tokens
- Employee ID.
- Device information.
- Push token.
- Platform.

Use the existing database naming conventions, ORM, migration strategy, and API patterns.

### API Requirements

Expose or extend APIs for:

- Project listing and assignment.
- Project management.
- Employee check-in.
- Employee check-out.
- Current attendance status.
- Attendance history and details.
- Location tracking uploads.
- Admin attendance monitoring.
- Employee location history.
- Authentication refresh.
- Push token registration.
- Notification listing/details.
- Announcement listing/details.
- Leave request creation/listing/details.

Ensure:

- Server-side validation.
- Proper authorization.
- Employee data isolation.
- Idempotent attendance operations.
- Pagination.
- Standardized responses.
- Appropriate database indexing.
- Secure logging.

---

# 14. Critical Edge Cases

Handle the following scenarios:

1. Employee attempts to check in outside the project boundary.
2. Employee has location permissions disabled.
3. Employee has inaccurate GPS coordinates.
4. Employee has already checked in.
5. Employee attempts check-out without an active session.
6. Employee loses internet during an active session.
7. The app is backgrounded during tracking.
8. The app is terminated or restricted by the operating system.
9. Employee restarts the phone during an active session.
10. Employee's access token expires while checked in.
11. The same employee logs in on another device.
12. Project location or radius changes during an active session.
13. Employee becomes unassigned from a project during an active session.
14. Employee's account is deactivated during a session.
15. Duplicate location updates arrive.
16. Location updates arrive out of order.
17. Employee attempts to manipulate client-side attendance validation.
18. Employee checks out outside the project area.
19. GPS tracking stops unexpectedly.
20. Notification delivery fails.
21. Employee submits overlapping leave requests.
22. Attendance spans midnight or multiple calendar days.
23. Device timezone differs from the project timezone.
24. Device location appears simulated or suspicious.

Use server-generated attendance transaction times and explicit timezone conversion for display and reporting.

Do not silently infer attendance or invent tracking records when the system has insufficient data.

---

# 15. Privacy, Security, and Performance

Employee location data is sensitive and must be handled carefully.

Implement:

- Location tracking only during an active attendance session.
- Clear employee disclosure and required platform permissions.
- Visible tracking status.
- Restricted administrator access.
- Appropriate access audits.
- Encrypted transport (HTTPS).
- Secure local data storage.
- Configurable location data retention.
- Data minimization.
- Device-session security.
- Rate limiting for location APIs.
- Efficient database queries.
- Battery-efficient location collection.
- Proper treatment of background location restrictions.

Never expose employee coordinates through unauthenticated APIs.

Where platform policy or applicable privacy requirements prevent a requested tracking behavior, implement the supported alternative and document the limitation.

---

# 16. UI/UX Requirements

Follow the existing application's design system and styling.

The mobile application must be:

- Simple and easy to navigate.
- Optimized for field employees.
- Responsive to different screen sizes.
- Clear about attendance and tracking status.
- Consistent across Android and iOS.
- Accessible with readable labels and suitable touch targets.

Provide appropriate:

- Loading states.
- Error messages.
- Empty states.
- Success confirmations.
- Permission explanations.
- Offline indicators.
- Network retry states.

Prefer reusable components rather than duplicating UI logic.

---

# 17. Implementation Instructions for Devin

## Phase 1 — Codebase Analysis

Before implementing anything:

1. Analyze the entire relevant project structure.
2. Identify the existing frontend and backend frameworks.
3. Determine whether a mobile application already exists.
4. Identify current authentication and refresh-token behavior.
5. Review employee and project entities.
6. Review existing attendance functionality.
7. Review notification and announcement implementations.
8. Review leave request functionality.
9. Identify reusable services and components.
10. Identify any missing requirements or technical constraints.

Provide a concise architecture assessment and implementation plan.

## Phase 2 — Admin Panel and Backend

Implement necessary backend/database changes and admin features for:

- Project and geographic boundary management.
- Employee-project assignment.
- Attendance sessions.
- Location tracking and monitoring.
- Announcements and notifications.
- Leave request integration.

## Phase 3 — Mobile Application

Implement:

- Navigation.
- Dashboard.
- Project selection.
- Check-in/check-out.
- Background location tracking.
- Attendance history.
- Notifications.
- Announcement listing/detail.
- Leave request creation/history.
- Secure persistent login.

## Phase 4 — Integration and Testing

Verify complete workflows between:

**Mobile Application → Backend APIs → Database → Admin Panel**

Test:

- GPS boundary validation.
- Attendance persistence.
- Continuous location uploads.
- Background tracking behavior.
- Offline synchronization.
- Authentication/session renewal.
- Push notifications.
- Announcements.
- Leave submission and status updates.
- Permissions and role-based access.

Add unit, integration, and end-to-end tests where supported by the repository.

Perform device-level validation for Android/iOS location behavior when test devices or emulators are available.

## Phase 5 — Final Verification

Before considering the implementation complete:

- Run applicable builds.
- Run tests.
- Run linting and type checking.
- Verify database migrations.
- Review security-sensitive changes.
- Confirm existing modules continue functioning.
- Check for duplicate code.
- Verify API contracts.
- Document environment variables and external services.
- Document platform-specific setup steps.

Do not claim a feature is fully verified if physical-device testing or required service credentials were unavailable.

---

# 18. Definition of Done

The implementation is complete when:

- Admins can create projects with coordinates and configurable attendance boundaries.
- Admins can assign employees to projects.
- Employees can check in with server-validated GPS coordinates.
- Employees can check out and have their location recorded.
- Active attendance sessions collect and synchronize location updates.
- Administrators can monitor last-known employee locations.
- Employees can view their attendance history.
- Mobile authentication persists securely without daily login.
- Push notifications are integrated.
- Published announcements appear in the mobile application.
- Employees can submit and track leave requests.
- Existing application functionality remains intact.
- Security, authorization, and important error scenarios are covered.
- Implementation changes and testing limitations are documented.

---

# 19. Agent Execution Rules

Follow these instructions throughout implementation:

1. **Do not blindly generate new modules.** Examine and extend existing functionality first.
2. Do not replace the current technology stack without a justified technical need.
3. Do not break existing endpoints, database behavior, or admin workflows.
4. Use existing coding standards, conventions, reusable components, and libraries.
5. Use database migrations for schema changes.
6. Keep API and business logic separate from UI components.
7. Implement GPS boundary enforcement on the backend.
8. Avoid insecure one-year access tokens.
9. Never fabricate GPS coordinates or attendance data.
10. Do not collect continuous employee location data outside active attendance sessions.
11. Implement in small, testable increments.
12. After each implementation phase, inspect changes and run relevant checks.
13. Fix errors before moving to dependent modules.
14. Identify assumptions explicitly when business rules are not defined.
15. Do not mark work as complete solely because code was generated.

**Start by inspecting the repository and producing a clear gap analysis, implementation sequence, and list of existing components that can be reused. Then proceed incrementally with implementation and verification.**