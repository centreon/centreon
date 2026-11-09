# ResourceForm

The forms of the monitoring resource pages (Hosts, Host Templates, Services,
and Service Templates to come) are built from **field blocks**. A page lists
its blocks and places them in its sections. Everything else is shared here.

`ConfigurationBase` stays generic (HostGroups, Commands…): fields of
monitoring resources do not go there.

## A field block

A `FieldBlock` (`models.ts`) owns a few fields end to end:

- `defaultValues`;
- `detailDecoders` and `detailKeyMap`: how it reads the detail back;
- `getInputs(context)`: the inputs it hands over, keyed by a `BlockId`;
- `getSchema(context)`: its validation rules;
- `toPayload(values, context)`: its slice of the API payload;
- optional `isAvailable(context)`: when false, the block is left out entirely
  (cloud, mass change);
- optional `massChange`: its update mode fields, defaults and warning.

`blocks.ts` merges the blocks of a page: defaults, decoded detail and payloads
are merged deeply, and schemas on the same key are concatenated. So several
blocks may share one object (`checkOptions`, `notifications`,
`dataProcessing`…), but **two blocks never own the same field**: the last one
would silently win.

Name a block after what it holds (`notesAndIcons`), never after where it is
shown.

## Where a block goes

- **`ResourceForm/fieldBlocks/`** as soon as **a second page** needs it. Move
  it there instead of copying it, in the PR that adds the second consumer.
- **The page's `Form/blocks/`** while only that page uses it (`hostIdentity`,
  `locked`, `snmpTraps`…).

When pages differ on a shared block, add an **option** for that difference
(`hasInheritedCommand`, `isCommandRequired`, `validateArguments`, the
endpoints…), off by default so the other pages keep their behaviour. Never a
"which form am I" flag (`isTemplate`, `isService`).

A block that renders a page's own component takes that component as an
option, as `OrderedTemplates` takes its endpoints and edit action.

## A page

Each page keeps:

- `Form/sections/index.ts`: its `ResourceOptions` (`baseEndpoint`, `idPrefix`)
  and its `blocks` list;
- `Form/layout.ts`: the one place that puts blocks into the displayed sections
  (`placeBlocks`), with the grid helpers of `grid.ts`;
- thin wrappers that pass `blocks` to the shared plumbing: default values,
  validation schema, form inputs, detail decoder, payload and mass change.

## Tests every page adds

- `layout.test.ts`: every block is placed exactly once.
- `sections/fieldOwnership.test.ts`: `getFieldsOwnedTwice` (`testUtils.ts`)
  finds no field written by two blocks, for every platform and mass change
  context. Copy `Hosts/Form/sections/fieldOwnership.test.ts`.
- Block-level Jest tests for a shared block's options
  (`fieldBlocks/relationList.test.ts`).
