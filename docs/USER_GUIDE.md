# User guide

## Access and permissions

GLPI administrator profiles receive the Ticket Migration rights once when the plugin is installed or upgraded. The plugin is then available under **Tools > Ticket Migration**, and its configuration shortcut is shown on the Plugins page.

The configuration shortcut opens the plugin dashboard. It provides direct access to profiles and migration history, summarizes stored profiles and CSV sources, checks protected-storage availability, and describes the currently testable workflow.

To delegate access, open **Administration > Profiles**, select a central-interface profile, then use its **Ticket Migration** tab. The configuration page always performs an authorization check even though GLPI displays its shortcut.

## Create a profile

Open **Tools > Ticket Migration > Profiles**, select **New profile**, provide a name and logical source, then save. Private profiles are visible only to their owner and remain scoped to the active GLPI entity.

## Upload and preview a CSV

Open the saved profile and select **Upload CSV**. Choose the delimiter, encoding, and whether the first logical row contains headers. The uploaded source is stored under GLPI protected plugin storage with a random internal name; only a bounded ten-row preview is rendered.

The preview displays positional column identities and a schema fingerprint. Duplicate header names remain distinct because their numeric positions are part of the schema.

Mapping, dry run, and execution are not enabled yet. The planned continuation is: map positional columns, validate reference values, execute a full dry run, then explicitly start the resumable import.

Replaying the same external IDs with identical canonical row hashes skips them. Changed rows are reported and are not updated automatically in V1.
