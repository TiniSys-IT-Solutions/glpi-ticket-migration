# References

Reviewed on 2026-08-19:

- [GLPI developer documentation](https://glpi-developer-documentation.readthedocs.io/en/latest/plugins/index.html): plugin layout, lifecycle, database, permissions, Twig, and GLPI 11 security requirements.
- [GLPI 11 upgrade guide](https://glpi-developer-documentation.readthedocs.io/en/master/upgradeguides/glpi-11.0.html#crafting-plugins-urls): canonical `/plugins/{key}` URLs independent from marketplace filesystem placement.
- [pluginsGLPI/empty](https://github.com/pluginsGLPI/empty), commit `c565a286e3e38f42749aa4e91e3bf2db5c773b8c`: current PHP 8.2 baseline, tooling and repository conventions.
- [pluginsGLPI/example](https://github.com/pluginsGLPI/example), commit `f81c04bd2ba9e9c66f118e08e89a04cf6180bf3e`: GLPI 11 hooks, namespaced item types, menu/config pages, and profile-right integration.
- [pluginsGLPI/datainjection](https://github.com/pluginsGLPI/datainjection), commit `77ab26f436d624cc9917571355fa7aaad39e9035`: backend/model/mapping separation, CSV configuration, preview, batch progress, reusable public/private models, and injection result reporting.
- Local Data Injection 2.15.10 at `/home/Doonix/DooSys_GitHub/0.Source/GLPI/glpi-datainjection/datainjection`: confirmed the value of positional mappings, readiness steps and browser batch feedback. Its full-file/session batching is deliberately not reused; Ticket Migration stores files and progress persistently and streams rows.
- Local GLPI 11.0.8 source at `/home/Doonix/DooSys_GitHub/0.Source/GLPI/glpi-11.0.8/glpi`: current ticket lifecycle and the `Ticket_User`, `Group_Ticket`, `ITILFollowup`, `TicketTask`, `ITILSolution`, `Document_Item`, and `Ticket_Ticket` object model.

Data Injection is used only as a conceptual reference. No class, table, or copied code from it is used. Ticket Migration's key departure is its one-row-to-one-aggregate `MigrationPlan` pipeline rather than one-row-to-one-GLPI-object injection.
