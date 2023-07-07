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

# Tests architecture

There are two kinds of tests in Centreon UI.
- Jest + RTL and Cypress: Component testing. We want to migrate from Jest to Cypress
- Chromatic: Chromatic is a tool that snapshots our stories and better handle snapshots changes using a review process


### Run Jest tests

```bash
pnpm t
```

### Run Cypress tests

```bash
pnpm cypress:ui # Opens the Cypress controlled browser to debug tests
pnpm cypress:cli # Runs tests in the terminal
```
