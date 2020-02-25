# centreon-ui

A repository of Centreon UI Components

# Linting

To lint the code with ESlint, run:

`npm run eslint`

You can also fix fixable linter errors by running:

`npm run eslint:fix`

# Storybook

You are using Storybook to visualize our components through stories.

To start Storybook server, run:

`npm run storybook`

# Tests

We have two kind of tests:
 - Unit tests provided by Jest
 - End to End tests provided by Storyshot using Jest. Storyshot is an addon of Storybook that compares graphically our stories.

To run Unit tests:

`npm run test`

or

`npm test`

or

`npm t`

To run End to End tests:
  - Build Storybook : `npm run build:storybook`
  - Run all Storyshot tests : `npm run test:storyshot`

You can also test one or more Components using the following syntax:

```bash
npm run test:storyshot -- "Title" # Run Storyshot tests about Title component
npm run test:storyshot -- "Breadcrumb|Title" # Run Storyshot tests about Title and Breadcrumb components
```
