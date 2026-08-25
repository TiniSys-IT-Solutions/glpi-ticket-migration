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

Select a functional GLPI target for each positional CSV column, or explicitly leave it ignored. The external ticket identifier and ticket title targets are mandatory. Saving a complete mapping makes the profile ready for the future dry-run step; it still creates no GLPI ticket.

Direct positional mapping is enabled. Resolver/value mapping, dry run, and execution remain disabled until their validation stages are implemented. The planned continuation is: validate reference values, execute a full dry run, then explicitly start the resumable import.

Replaying the same external IDs with identical canonical row hashes skips them. Changed rows are reported and are not updated automatically in V1.
