# Host custom macros: domain rules

These rules hold for every operation that creates or changes a host's macros, whatever the entry point.

## 1. Concepts

- A **custom macro** is a name/value pair that a host exposes to the engine as `$_HOST<NAME>$`. It may be flagged as a **password**.
- A host's macros come from two places:
  - **Own macros**: defined on the host itself. They are the only macros the host stores.
  - **Inherited macros**: received from its host templates or from check commands. A host can see them but never modifies them.
- The **effective macros** of a host are its own macros, plus the inherited macros that none of its own macros shadows by name.
- A host template follows the same model. It owns its own macros and inherits the rest along its own template line.

## 2. Identity

1. A macro's name is case-insensitive. It is trimmed and stored upper-cased.
2. A host has **at most one macro per name**, counting its own and inherited macros together. An own macro with the same name as an inherited one shadows it.
3. A macro is identified by its **origin** (own, template or command) together with its **id** within that origin. Its name does not identify it, because a macro can be renamed.
4. Macros have no order. A host's macros form a set keyed by name.

## 3. Naming

1. A name must not be empty.
2. Its full form `$_HOST<NAME>$` must fit in 255 characters. No character set is enforced, to stay compatible with existing templates.
3. A name must not collide with a reserved engine macro (the standard macros).

## 4. Values

1. A value is at most 4096 characters. An empty value is a valid value.
2. A value must never be a reference to a secret store. The only way to keep a secret is to keep the stored one (rule 6.3).
3. Two macros are **equivalent** when they have the same name, the same stored value and the same password flag. Values are always compared in their stored form, never after resolving a secret.

## 5. Inheritance

1. **Template priority.** A host walks its full template line, starting with the nearest template. The nearest template that defines a name wins over any template farther away.
2. **Command macros come last.** Check commands only provide names that no template defines. Among commands, the first one that declares a name wins. Commands are considered in this order:
   1. the host's own check command or, if it has none, that of the first template in its line that has one;
   2. then the check commands of the service templates linked to each template in the line.
3. Only check commands contribute macros. A command reference that points to nothing contributes nothing.
4. An inherited command macro has an empty value and is never a password. It is expected to be filled in by the host.
5. Inheritance is always evaluated against the templates and check command the host has **after** the change, not before.

## 6. Changing a host's macros

1. **Creating** a macro gives the host a new own macro.
2. **Changing an own macro** updates it in place. It keeps its identity, even when it is renamed.
3. **Keeping a secret.** When an existing password macro is changed without a new value, it keeps its stored value. This is the only case in which a value can be left out. A macro that is not a stored password always needs an explicit value.
4. **Overriding an inherited macro.** Changing an inherited macro never modifies its source. Instead, the host gets a new own macro with the same name. If the host already overrides that name, that override is replaced rather than duplicated.
5. **Overrides must differ from what they shadow.** An own macro that is equivalent to the inherited macro of the same name is redundant. It is not stored, and the host relies on inheritance instead. Restating an inherited macro therefore never creates a copy.
6. Command macros are matched **by name**, not by id. Their ids are unstable and carry nothing a write needs. If a template in the line defines the same name, the template wins, so an empty value then becomes a real override.
7. **Removing.** Only own macros can be removed. Removing an override makes the inherited macro visible again. An inherited macro cannot be removed from a host.
8. A change cannot refer to a macro the host neither owns nor inherits.
9. A change must not leave the host with two own macros of the same name. Such a change is rejected or resolved silently.

## 7. Secrets

These rules apply when a secret store (vault) is enabled. Without one, password values are stored as is.

1. **One entry per host.** All of a host's secrets (SNMP community and password macros) are stored under a single entry that belongs to the host. The key for a macro is `_HOST<NAME>`.
2. **Secrets are never shared.** An own password macro always refers to the host's own entry. When the host overrides an inherited password and keeps its value, the secret is **copied** into the host's entry. Changing or deleting the template then never affects the host.
3. A secret already stored in the host's entry stays where it is, even after a rename. If a rename makes two macros claim the same key, the renamed macro is moved under its own name.
4. An empty password is stored as an empty value, with nothing written to the vault.
5. A secret that no macro uses any more is deleted from the host's entry. This happens when the macro is removed, when it becomes redundant and goes back to being inherited, or when it is no longer a password. An entry left empty is purged.
6. Every validation happens before any secret is written, so a rejected change never leaves an orphan secret behind.
7. A password value is never returned to a reader.

## 8. Change detection

1. Two sets of macros are the same when every name maps to an equivalent macro. A different order is not a change.
2. Writing a secret counts as a change, even when the stored reference ends up identical.
