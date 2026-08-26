# User guide

## Access and permissions

GLPI administrator profiles receive the Ticket Migration rights once when the plugin is installed or upgraded. The plugin is then available under **Tools > Ticket Migration**, and its configuration shortcut is shown on the Plugins page.

The configuration shortcut opens the plugin dashboard. It provides direct access to profiles and migration history, summarizes profiles, active CSV sources and retained revisions, checks protected-storage availability, and describes the currently testable workflow.

To delegate access, open **Administration > Profiles**, select a central-interface profile, then use its **Ticket Migration** tab. The configuration page always performs an authorization check even though GLPI displays its shortcut.

## Create a profile

Open **Tools > Ticket Migration > Profiles**, select **New profile**, provide a name and logical source, then save. Private profiles are visible only to their owner and remain scoped to the active GLPI entity.

The profile list follows GLPI's selection and massive-action pattern. Select one or more projects to archive, restore, clone their configuration, or delete projects that have no execution history. Archived projects move to a separate read-only view and keep their complete audit trail. Clones preserve configuration and mapping decisions without copying the CSV file; upload a structurally matching revision to reuse those mappings. Deletion is refused as soon as a run or external reference exists.

## Upload and preview a CSV

Open the saved profile and select **Upload CSV**. Choose the delimiter, encoding, and whether the first logical row contains headers. The uploaded source is stored under GLPI protected plugin storage with a random internal name; only a bounded ten-row preview is rendered.

The preview reads at most eleven data rows to determine whether the display is truncated, then renders no more than ten. This limit affects only the screen: the complete CSV remains stored for later mapping, dry run, and execution. Use **Return to profile** or **Replace or test another CSV** to continue navigating the current workflow.

Only CSV payloads materially consume storage. Activating a replacement starts a 30-day retention period for the preceding revision. The configuration dashboard reports stored bytes and cleanup candidates; administrators with deletion rights may clean only revisions that are expired, inactive, and unused by every run. Mapping, run, and external-reference audit records are retained.

The preview displays positional column identities and a schema fingerprint. Duplicate header names remain distinct because their numeric positions are part of the schema. A validated upload becomes the profile's active source and enables **Continue to mapping**.

## Resume a profile and manage CSV revisions

Profiles are saved automatically at their current workflow step. Reopening a profile proposes the next useful action and reuses its active CSV; uploading again is optional. **Manage CSV sources** lists all retained revisions for that profile. You can preview a revision, select it as active, or delete a non-active revision that has never been used by a migration run.

Selecting a revision with the same schema fingerprint preserves the mapping. Selecting a structurally different revision requires mapping validation again. The dashboard therefore distinguishes active sources from stored revision files.

## Map CSV columns

Select a functional GLPI target for each positional CSV column, or explicitly leave it ignored. The external ticket identifier and ticket title targets are mandatory. Map the original ticket body to **Main description**.

Description consolidation is enabled by default for historical migrations. It produces a **Historical data imported** section from mapped and unmapped source fields, a safe HTML separator, then the original description in its own section. Empty values are omitted. You can include or exclude mapped/unmapped fields, position historical data before or after the main description, and exclude technical or sensitive columns individually. All source labels and values are escaped before HTML generation.

The mapping page also enables an empty-title fallback by default. When the mapped title is empty for a row, the plugin generates `Ticket —` followed by the configured number of words from the main description. When the description is empty too, it uses `Ticket` followed by the external identifier. Existing non-empty titles are preserved unchanged.

Choose a **Default ticket entity** when creating or editing a migration profile. It is a fallback, not an override. The plan first honors an explicitly mapped entity, then the resolved requester's preferred GLPI entity when it belongs to the user's Habilitations, then one unique authorization. Only without a usable requester entity does it use a migration-profile location/entity association, the entity configured directly on the GLPI location, or one exact unique legacy hierarchy match. The profile default is last. Every conflict between requester and location evidence is reported in the plan preview.

Mapped opening, resolution, and closing dates are normalized from supported French or ISO forms to GLPI's date-time format. Invalid dates block the plan, while their original source representation remains visible in the structured historical description.

Direct positional mapping, controlled-value correspondence, pilot execution, and guarded final import are enabled. The recommended sequence is to inspect several row plans, perform one explicitly confirmed pilot import, verify the resulting GLPI ticket, then prepare the resumable final import.

## Match controlled values

After field mapping, **Continue to value correspondence** scans the active CSV in streaming mode and lists distinct values only for controlled targets. Map enumerations to official GLPI values and confirm suggested existing users, groups, entities, locations, or categories. When there is exactly one exact or normalized-exact match it is preselected, but it is accepted only when you save the form. If no useful suggestion exists, use the GLPI search field below the correspondence to find an authorized object in the complete GLPI list. Every discovered value must be resolved or explicitly ignored. No user is ever created by the plugin.

When locations are mapped, the same screen displays **Location and entity associations**. GLPI stores an `entities_id` scope on each location, normally inherited from the active entity when the location is created. GLPI 11 does not expose this value as a standard editable field on the Location form, so it must not be mistaken for an explicit business mapping visible to administrators. For a legacy/global location, select an optional entity association scoped to this migration profile. This is a safeguard for tickets without a usable requester that must be routed to a child of the project's main entity: a resolved requester entity always takes precedence. When no association is selected, the screen names the project default entity explicitly instead of displaying GLPI's ambiguous root entity value.

Value-mapping categories are displayed as expandable blocks. Each header reports `processed/total`; for example, `5/7` means that two source values still require a decision. Completed blocks remain easy to identify without keeping large correspondence tables open.

The optional location/entity safeguard uses the same visual convention: saved explicit associations are highlighted in green with an **Association saved** badge, a changed selection is marked **Ready to save**, and the expandable header reports the number of explicit associations over the number of resolved GLPI locations. The migration-profile form provides direct access to field mapping as soon as an active CSV source exists; reopening value correspondence is no longer required merely to navigate backwards.

The page summarizes analyzed values, perfect automatic matches, saved manual associations, and remaining decisions globally and by category. A saved manual reference or explicit ignore is counted as completed manual work. Perfect unique references are kept out of the manual worklist and are validated together when the form is saved. The complete GLPI selector appears only after choosing manual selection. The last saved analysis and its source filename remain visible, making it possible to compare the result after preparing and activating a revised CSV.

Use **Save progress** at any time to persist only the decisions already completed. Blank rows remain pending, previously saved decisions are preserved, and the workflow does not advance. **Validate and continue** is the final action: it requires every current value to be resolved or explicitly ignored before enabling the migration-plan preview.

A complete row is highlighted in green with a check badge. **Association saved** identifies a persisted decision; **Ready to save** identifies a complete choice changed during the current page session. The no-exact-match warning disappears after a valid manual GLPI object is selected.

When several GLPI users share the same first and last name, suggested choices include both the GLPI login and numeric ID after the display name, for example `RIVRON François — francois.rivron@example.org (#123)`.

For requester, assigned-technician, and actor-group columns, select how multiple actors are separated during field mapping. Automatic mode supports semicolons, pipes, and line breaks. It also safely detects comma-separated lists containing at least one e-mail, including mixed values such as `support team, user@example.org`; every non-empty component is shown and mapped independently, then becomes a separate ticket actor. Choose comma explicitly for lists containing only non-email logins or labels; automatic mode preserves names formatted as `Last name, First name`.

The distinct-value display is limited to 200 values per field. Reaching that limit normally prevents validation and indicates that the selected column or strategy must be refined. For an actor field, you may explicitly choose **Keep resolved associations and omit only unresolved actors**. Every automatic or manual association is still saved and applied to imported tickets, including decisions made in the same submission. Only source actors without any saved resolution are omitted and reported as warnings; the category statistics count them separately as omitted rather than remaining. The plugin never creates missing users.

## Preview the first migration plan

After saving all value correspondences, inspect immutable plans row by row with the previous and next controls. A prominent summary shows the source and resolved GLPI requester, technician, location, and entity, including the entity-resolution origin. The resulting GLPI ticket title is displayed immediately below so empty source titles and fallback-generated titles can be reviewed. Plan messages are separated into **Alert** (red or amber, requiring attention), **Information** (blue, explaining a deterministic choice), and **Validated** (green, confirming a successful automatic or configured rule). The complete authoritative JSON plan retains the same structured categories. Navigation and preview are read-only, stream only up to the requested row, and never store CSV rows in the PHP session; ticket creation occurs only after the separate confirmed pilot action.

An operator with both the Ticket Migration import right and GLPI's ticket-creation right can select **Import this pilot row** on an error-free plan. The confirmation creates one real ticket through GLPI's internal lifecycle with notifications and current automatic routing disabled for that object. The source identifier is written to GLPI 11's native **External ID** ticket field and to the plugin's profile-scoped ledger. The plugin records the run, diagnostics, canonical source-row hash, external identifier, and created ticket ID transactionally. After success, the button becomes a link to the ticket and the same external identifier cannot create another pilot or final ticket. A failed creation stores a failed technical run item but no external reference. Concurrent clicks are serialized and shown as an import already in progress rather than as a failure.

Workflow footers use the same convention on every step: neutral outlined buttons on the left go back or open secondary management pages, blue buttons save the current work, and the green button on the right advances or performs the explicitly confirmed pilot action.

Replaying the same external IDs with identical canonical row hashes skips them. Changed rows are reported and are not updated automatically in V1.

## Run the final import

From the migration-plan preview, select **Prepare final import**. The preparation page counts the complete CSV and explains notification and recovery behavior. The plugin does not create or inspect a backup: the mandatory checkbox records only that the connected operator accepts responsibility for suitable backup preparation. The server stores that GLPI user and timestamp on the run.

The progress page processes 25 rows per short request. It can be closed and reopened from **Tools > Ticket Migration > Migration runs** without losing completed work. Pause stops before the next batch; resume continues at the persisted offset using the source file and complete mapping snapshot frozen when the run was created.

Every source row receives an auditable state: imported, already imported, changed, or failed. Individual failures do not stop later rows. Notifications are disabled on every created ticket; the progress page is the single overall result notification. **Export row trace** downloads all status and diagnostic codes without copying ticket descriptions.

If a failure requires a source or mapping correction, allow the run to collect the other errors, correct the profile, and start a new final run. Previously registered external identifiers with the same row hash are skipped, while failed rows are attempted again. A known identifier with changed source content is deliberately reported for manual review and is never updated automatically.
