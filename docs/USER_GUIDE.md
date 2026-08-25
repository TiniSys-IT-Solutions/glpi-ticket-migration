# User guide

## Access and permissions

GLPI administrator profiles receive the Ticket Migration rights once when the plugin is installed or upgraded. The plugin is then available under **Tools > Ticket Migration**, and its configuration shortcut is shown on the Plugins page.

The configuration shortcut opens the plugin dashboard. It provides direct access to profiles and migration history, summarizes profiles, active CSV sources and retained revisions, checks protected-storage availability, and describes the currently testable workflow.

To delegate access, open **Administration > Profiles**, select a central-interface profile, then use its **Ticket Migration** tab. The configuration page always performs an authorization check even though GLPI displays its shortcut.

## Create a profile

Open **Tools > Ticket Migration > Profiles**, select **New profile**, provide a name and logical source, then save. Private profiles are visible only to their owner and remain scoped to the active GLPI entity.

## Upload and preview a CSV

Open the saved profile and select **Upload CSV**. Choose the delimiter, encoding, and whether the first logical row contains headers. The uploaded source is stored under GLPI protected plugin storage with a random internal name; only a bounded ten-row preview is rendered.

The preview reads at most eleven data rows to determine whether the display is truncated, then renders no more than ten. This limit affects only the screen: the complete CSV remains stored for later mapping, dry run, and execution. Use **Return to profile** or **Replace or test another CSV** to continue navigating the current workflow.

The preview displays positional column identities and a schema fingerprint. Duplicate header names remain distinct because their numeric positions are part of the schema. A validated upload becomes the profile's active source and enables **Continue to mapping**.

## Resume a profile and manage CSV revisions

Profiles are saved automatically at their current workflow step. Reopening a profile proposes the next useful action and reuses its active CSV; uploading again is optional. **Manage CSV sources** lists all retained revisions for that profile. You can preview a revision, select it as active, or delete a non-active revision that has never been used by a migration run.

Selecting a revision with the same schema fingerprint preserves the mapping. Selecting a structurally different revision requires mapping validation again. The dashboard therefore distinguishes active sources from stored revision files.

## Map CSV columns

Select a functional GLPI target for each positional CSV column, or explicitly leave it ignored. The external ticket identifier and ticket title targets are mandatory. Map the original ticket body to **Main description**.

Description consolidation is enabled by default for historical migrations. It produces a **Historical data imported** section from mapped and unmapped source fields, a safe HTML separator, then the original description in its own section. Empty values are omitted. You can include or exclude mapped/unmapped fields, position historical data before or after the main description, and exclude technical or sensitive columns individually. All source labels and values are escaped before HTML generation.

Direct positional mapping and controlled-value correspondence are enabled. Pilot execution, full dry run, and import remain disabled until the guarded GLPI executor is validated. The planned continuation is: inspect a first-row plan, perform an explicitly confirmed pilot import, execute a full dry run, then start the resumable import.

## Match controlled values

After field mapping, **Continue to value correspondence** scans the active CSV in streaming mode and lists distinct values only for controlled targets. Map enumerations to official GLPI values and confirm suggested existing users, groups, entities, locations, or categories. When there is exactly one exact or normalized-exact match it is preselected, but it is accepted only when you save the form. If no useful suggestion exists, use the GLPI search field below the correspondence to find an authorized object in the complete GLPI list. Every discovered value must be resolved or explicitly ignored. No user is ever created by the plugin.

For requester, assigned-technician, and actor-group columns, select how multiple actors are separated during field mapping. Automatic mode supports semicolons, pipes, and line breaks. Choose comma explicitly when the export uses commas, because automatic comma splitting would damage names formatted as `Last name, First name`.

The distinct-value display is limited to 200 values per field. Reaching that limit normally prevents validation and indicates that the selected column or strategy must be refined. For an actor field, you may explicitly choose **Import tickets without actors from this source field**. That choice omits the corresponding requester, technician, or group role for the entire source field; it does not create users and the immutable plan reports unresolved actors as warnings.

## Preview the first migration plan

After saving all value correspondences, preview the immutable plan generated for the first CSV data row. It shows ticket data, actors, external reference, warnings, and errors. This preview is read-only and creates no GLPI ticket. Pilot import remains disabled until the guarded executor is validated against GLPI 11.0.8.

Replaying the same external IDs with identical canonical row hashes skips them. Changed rows are reported and are not updated automatically in V1.
