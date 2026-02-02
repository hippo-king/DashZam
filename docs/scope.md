# DashZam — Application Scope Document

## 1) Overview

DashZam is a live rink schedule display that pulls remote event data, highlights current live events, and presents a clean, high-contrast view for distance readability. It supports automatic token authentication, scheduled refreshes, and rule-based annotations for special events like ice resurfacing and close-rink procedures.

## 2) Goals

- Provide a clear, always-updated rink schedule.
- Surface the current live event per rink with high visibility.
- Apply rule-driven annotations (ice cuts / close rink) to guide operators.
- Support dark theme and large-format display readability.
- Offer a mock data mode for safe testing without live API calls.

## 3) Target Users

- Ice rink operators and drivers
- Facility staff monitoring live schedules
- Administrators validating data integrity

## 4) Key Features

### 4.1 Live Event Display

- Prominent “Live Now” callout per rink.
- Current event title, time range, and progress bar.
- Optional alerts for special rules (e.g., Spokane Braves games requiring ice cuts).

**Screenshot Placeholder:**
![Live Event Card](./images/live-event-card.png)

### 4.2 Rink Schedule View

- Upcoming and ongoing events listed per rink.
- Close Rink events highlighted with lock icon.
- Resurface events clearly differentiated.
- Time-only format for quick scan.

**Screenshot Placeholder:**
![Rink Schedule Columns](./images/rink-schedule.png)

### 4.3 Rule-Based Annotations

- Detect “Takedown” events and label as “Ice Resurfacing”.
- Mark the final takedown per rink as “Close Rink”.
- Special “Spokane Braves vs” rule with required ice cuts alert.

**Screenshot Placeholder:**
![Special Rules Banner](./images/rules-banner.png)

### 4.4 Dark Theme

- Global dark theme for reduced eye strain.
- High-contrast text and UI elements for distance viewing.

**Screenshot Placeholder:**
![Dark Theme](./images/dark-theme.png)

### 4.5 API Authentication & Refresh

- Automatic client_credentials authentication.
- 24-hour bearer tokens with refresh handling.
- Periodic page refresh aligned just after the minute.

**Screenshot Placeholder:**
![API Settings](./images/api-settings.png)

### 4.6 Mock Data Mode

- Toggle between live API data and mock events.
- Mock data uses the current time rounded to the previous 15 minutes.
- Useful for testing Live/Close/Resurface scenarios.

**Screenshot Placeholder:**
![Mock Data Toggle](./images/mock-mode.png)

## 5) Data Sources & Integrations

- Remote API via base URL with `/auth/token` and event endpoints.
- Client ID/Secret stored per user.
- Scheduled fetches and “Fetch Now” manual action.

## 6) User Flows

### 6.1 Operator Viewing Live Schedule

1. Open live events view.
2. Monitor Live Now cards per rink.
3. Confirm Close Rink and Resurface cues.

### 6.2 Admin Updating API Credentials

1. Navigate to API Settings.
2. Enter base URL, client ID, client secret.
3. Save and test fetch.

## 7) Non-Functional Requirements

- **Availability:** Must load quickly on large displays.
- **Readability:** Large type, high contrast, minimal clutter.
- **Reliability:** Refresh aligned to near-real-time data.
- **Security:** Token storage encrypted.

## 8) Config & Flags

- `EVENTS_USE_MOCK=true|false` — switch between mock and live data.

## 9) Future Enhancements (Optional)

- Slim schedule for conference room rentals.
- Per-event resurface schedule timeline.
- Role-based permissions for settings changes.

## 10) Open Questions

- Exact policy for non-Braves special rules.
- Final design for lock/close-rink indicators.
- Desired refresh cadence beyond top-of-minute updates.

---

## Screenshot Notes

Add images under `docs/images/` and update the markdown image paths above.
