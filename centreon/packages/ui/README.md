# centreon-ui

A repository of Centreon UI Components

# Linting

To lint the code with ESlint, run:

`pnpm eslint`

You can also fix fixable linter errors by running:

`pnpm eslint:fix`

# Storybook

You are using Storybook to visualize our components through stories.

To start Storybook server, run:

`pnpm storybook`

# Tests

We have two kind of tests:
 - Unit tests provided by Jest
 - End to End tests provided by Storyshot using Jest. Storyshot is an addon of Storybook that compares graphically our stories.

To run Unit tests:

`pnpm test`

or

`pnpm t`

To run End to End tests:
  - Build Storybook : `pnpm build:storybook`
  - Run all Storyshot tests : `pnpm test:storyshot`

You can also test one or more Components using the following syntax:

```bash
pnpm test:storyshot -- "Title" # Run Storyshot tests about Title component
pnpm test:storyshot -- "Breadcrumb|Title" # Run Storyshot tests about Title and Breadcrumb components
```
