jest.mock('@centreon/ui-context', () => ({
  ...jest.requireActual('./packages/ui-context'),
  ThemeMode: {
    light: 'light'
  }
}));
