# User guide

Ticket Migration is not operational yet. The planned workflow is: create a reusable migration profile, configure CSV parsing, map positional columns, validate reference values, preview generated tickets, execute a full dry run, then explicitly start the resumable import.

Replaying the same external IDs with identical canonical row hashes skips them. Changed rows are reported and are not updated automatically in V1.
