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
pnpm test:storyshot -- --story "Title" # Run Storyshot tests about Title component
pnpm test:storyshot -- --story "Breadcrumb|Title" # Run Storyshot tests about Title and Breadcrumb components
```


# Add stories

- Create a file named `index.stories.tsx` along side your component
      
- Add a title, the component and argTypes
  
  ```typescript
  export default {
    title: 'MyComponent',
    Component: MyComponent,
    argTypes: {
      propA: { control: 'text' },
      propB: { control: 'number' },
    },
  };
  ```

- Create a playground for your component

  ```typescript
    const Template: ComponentStory<typeof MyComponent> = (args) => (
      <MyComponent {...args} />
    );

    export const Playground = Template.bind({});
  ```

- Then add your story

  ```typescript
    export const basic = Template.bind({});
    basic.args = { propA: 'test', propB: 0 };
  ```

# Run tests

There are two kinds of tests in Centreon UI.
- Jest + RTL: Component testing
- Storyshots: When running them, storybook will capture a snapshot for each and compares them with the older ones


### Run all the test (Jest + Storyshots)

```bash
pnpm build:storybook && pnpm t
```

### Run storyshots following one or more story title</Typography>

```bash
pnpm build:storybook && pnpm test:storyshot -- --story "MyComponent"
```
_or_
```bash
pnpm build:storybook && pnpm test:storyshot -- --story "MyComponent|MyOtherComponent"
```
   

### Update snapshots

```bash
pnpm build:storybook && pnpm t -- -u
```
_or_
```bash
pnpm build:storybook && pnpm test:storyshot -- --story "MyComponent" -u
```
_or_
```bash
pnpm build:storybook && pnpm test:storyshot -- --story "MyComponent|MyOtherComponent" -u
```
